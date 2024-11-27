<?php
/**
 * Helper class to parse input (from scraping /server-status/, or copy/pasting in put)
 */
namespace App\Services;

use App\Services\LookupIPsWithAPI;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ParseInputs
{

    protected bool $debug = false;


    protected string $ipv4Pattern = '/\b(?:\d{1,3}\.){3}\d{1,3}\b/';
    protected array $possibleHttpProtocols = ['http/1.1', 'h2'];
    protected array $possibleHttpMethods = ['POST', 'GET', 'PUT', 'DELETE', 'PATCH', 'PRI', 'HEAD', 'OPTIONS', 'TRACE', 'CONNECT'];

    protected int $min_connections = 2;
    protected array $ignore_ips = ['127.0.0.1'];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->min_connections = config('app.MIN_IP_CONNECTIONS');
        $ignore_ips = config('app.IGNORE_IP_ADDRESSES');
        if ($ignore_ips) {
            $this->ignore_ips = explode(',', $ignore_ips);
        }
    }

    /**
     * Find the IPv4 address in a line of text
     *
     * @param string $line Line of text
     *
     * @return bool|string
     */
    public function extractIp(string $line='')
    {
        $line = trim($line);
        $ip = false;
        if (preg_match($this->ipv4Pattern, $line, $matches)) {
            // Validate the IP to ensure it's a valid IPv4 address
            if (filter_var($matches[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ip = $matches[0];
            }
        }
        return $ip;
    }

    /**
     * Filter ipData to remove ignored IPs or less than min_connections
     *
     * @param array $ipData IP addresses
     *
     * @return array
     */
    public function removeIgnoredIps($ipData)
    {
        if ($this->debug) {
            log::notice($ipData);
        }
        foreach ($ipData as $ip => $data) {
            if (in_array($ip, $this->ignore_ips) || $data['count'] < $this->min_connections) {
                if ($this->debug) {
                    Log::notice('Removing IP: '.$ip);
                }
                unset($ipData[$ip]);
            }
        }
        return $ipData;
    }


    /**
     * Make ipData array into a collection
     *
     * @param array $ipData ipData to sort into collection
     *
     * @return \Illuminate\Support\Collection<TKey, TValue>
     */
    public function makeCollection(array $ipData)
    {
        $collection = collect($ipData)->map(
            function ($data, $ip) {
                $data['requests'] = implode('<br>', array_unique($data['requests']));
                $data['requests'] = $this->highlightRequests($data['requests']);
                return $data;
            }
        )->sortByDesc('count')->all();
        return $collection;
    }

    /**
     * Highlight problematic strings in requst like moon.php, etc.
     *
     * @param string $requests String to filter
     *
     * @return string
     */
    public function highlightRequests(string $requests): string
    {
        $flaggedWords = [
            'defence.php', 'dialog.php', 'moon.php', 'tools.php',
            'wp-login.php', 'SimplePie', 'xx.php', 'xxx.php', 'bypass.php',
            'wlwmanifest.xml', 'xmlrpc.php', 'byp.php', 'duck.php', 'upload.php',
            'wsoyanz.php', 'wp-config.php',
            '.php'
        ];

        // Replace flagged words with the highlighted version, case-insensitively
        foreach ($flaggedWords as $word) {
            $requests = str_ireplace($word, '<span class="highlight">' . $word . '</span>', $requests);
        }

        return $requests;
    }


    /**
     * Primary function to crunch user input
     *
     * @param string $inputText Lines of text to process
     *
     * @return array
     */
    public function crunchUserInput(string $inputText)
    {
        $ipData = [];
        $lines = explode("\n", $inputText);

        foreach ($lines as $line) {
            // Skip any line that doesn't have an IP
            if (!$ip = $this->extractIp($line)) {
                continue;
            }
            // Crunch the line
            $ipRow = $this->crunchUserInputLine($line, $ip);
            if (!$ipRow) {
                continue;
            }
            if ($ipRow['ip']) {
                $ip = $ipRow['ip'];
                // Either add or merge the data in for this row
                if (!array_key_exists($ip, $ipData)) {
                    $ipData[$ip] = $ipRow;
                } else {
                    $ipData[$ip] = array(
                        'ip' => $ip,
                        'count' => $ipData[$ip]['count'] + $ipRow['count'],
                        'requests' => array_merge($ipData[$ip]['requests'], $ipRow['requests'])
                    );
                }
            }
        }


        // Remove any unwanted IPs
        $ipData = $this->removeIgnoredIps($ipData);

        if ($this->debug) {
            Log::notice('ipData: ');
            Log::notice($ipData);
        }


        // Turn array into collection
        $collection = $this->makeCollection($ipData);

        if ($this->debug) {
            Log::notice('Collection: ');
            Log::notice($collection);
        }

        return $collection;
    }

    /**
     * Crunch one line of text to get the ipData
     *
     * @param string $line Line to crunch
     * @param string $ip   IP address we've extracted from this line
     *
     * @return array|null
     */
    public function crunchUserInputLine(string $line, string $ip='')
    {
        $ip = false;
        $line = trim($line);

        // Ensure line has an IP address
        if (!$ip = $this->extractIp($line)) {
            return null;
        }
        if ($this->debug) {
            Log::notice('Found IP ' . $ip.' in line: '. $line);
        }

        $ipRow = array(
            'ip' => $ip,
            'count' => 1,
            'requests' => [],
        );

        // Explode the line by spaces, and see what we can find
        $parts = preg_split('/\s+/', trim($line));
        if ($this->debug) {
            Log::notice($parts);
        }

        // Skip this if it matches a line from Apache Server Status
        if ($parts[0] == 'Apache' && $parts[1] == 'Server' && $parts[2] == 'Status') {
            return null;
        }

        // If we get format like: count IP
        // netstat -ntu | awk '{print $5}' | cut -d: -f1 | sort | uniq -c | sort -n
        if (is_numeric($parts[0]) && $parts[1] == $ip) {
            $ipRow['count'] = $parts[0];
            return $ipRow;
        }

        // Looking for output from apache-status pages
        //0-2    -    0/0/3855    .    0.00    9428    1283    2464512    0.0    0.00    98.94    159.223.22.214    http/1.1
        //0-2    -    0/0/3888    .    0.00    9428    1292    2677274    0.0    0.00    96.45    159.223.22.214    http/1.1    site.com.au:443    GET /contact/ HTTP/1.1
        else if ($parts[11]==$ip && in_array($parts[12], $this->possibleHttpProtocols)) {
            $request = '';
            if (array_key_exists(15, $parts)) {
                $request = "{$parts[13]} {$parts[14]} {$parts[15]}";
            }
            //$ipRow['count'] = 1;
            $ipRow['requests'][] = $request;
            return $ipRow;
        }
        return null;
    }

    /**
     * Parse the scraped data
     *
     * @param array $data Data from the scrape() function
     *
     * @return \Illuminate\Support\Collection<TKey, TValue>
     */
    public function crunchScraperInput(array $data)
    {
        $ipData = array();

        if ($this->debug) {
            Log::notice('Parsing data with ' . count($data) .' rows');
            Log::notice($data);
        }
        foreach ($data as $row) {
            if ($this->debug) {
                Log::notice('Parsing row...');
                Log::notice($row);
            }
            // Data from row
            $ip = $row[11];
            $domain = $row[13];
            $uri = $row[14];
            $request = "$domain $uri";

            // If IP is not yet in array, prepare it.
            if (!array_key_exists($ip, $ipData)) {
                $ipData[$ip] = array(
                    'ip' => $ip,
                    'count' => 0,
                    'requests' => [],
                );
            }

            // Add data to array
            $ipData[$ip]['count']++;
            if (!empty($request)) {
                $ipData[$ip]['requests'][] = $request;
            }

        }

        // Remove any unwanted IPs
        $ipData = $this->removeIgnoredIps($ipData);

        // Turn array into collection
        $collection = $this->makeCollection($ipData);

        if ($this->debug) {
            Log::notice('ipData to Collection: ');
            Log::notice($collection);
        }

        return $collection;
    }




}
