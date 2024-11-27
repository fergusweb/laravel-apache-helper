<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class LookupIPsWithAPI
{

    protected bool $debug = true;

    /**
     * API URL - add to your config or .env
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->apiUrl = config('app.IP_API_URL').'?key='.config('app.IP_API_KEY');
    }



    /**
     * Lookup these IP addresses to fetch data from API.
     *
     * @param array $ipData Parsed array of data (ip => [count, requests])
     *
     * @return array
     */
    public function lookup(array $ipData): array
    {
        // Get list of IP addresses
        $ips = array_keys($ipData);

        // Remove any we have saved, they don't need to be fetched
        $removedIPs = array();
        foreach ($ips as $key => $ip) {
            // Skip things that are not an IP // TODO: proper regex validation?
            if (!$ip || !$this->isValidIP($ip)) {
                unset($ips[$key]);
                continue;
            }
            // Check if we have data saved?
            $loaded = $this->loadIP($ip);
            // If so, remove this IP from the query
            if ($loaded) {
                unset($ips[$key]);
                $removedIPs[] = $ip;
                if ($this->debug) {
                    Log::debug('Removing IP '.$ip.' from the batch lookup');
                }
                continue;
            }
            if ($this->debug) {
                Log::debug('Will look up: '.$ip);
            }
        }

        // Break up IPs into batches of 100
        $batches = array_chunk($ips, 100);

        // Loop through batches to perform lookups
        $responses = array();
        foreach ($batches as $index => $batch) {
            if ($this->debug) {
                Log::debug('Looking up ' . count($batch) .' IPs...');
            }
            $response = $this->loadFromAPI($batch);

            foreach ($response as $key => $data) {
                $responses[$key] = $data;
            }
        }
        if ($this->debug) {
            Log::debug('Combined responses:');
            Log::debug($responses);
        }

        // Fill in IP data with our responses
        $ipData = $this->fillData($ipData, $responses);

        // Now we need to add any cached IP data back in.
        if ($this->debug) {
            Log::notice('Need to load cached data for these removed IPs:');
            Log::notice($removedIPs);
        }
        foreach ($removedIPs as $ip) {
            $data = $this->loadIP($ip);
            if ($data) {
                $ipData[$ip] = $data;
            }
        }

        if ($this->debug) {
            Log::notice('Full ipData:');
            Log::notice($ipData);
        }

        return $ipData;
    }


    /**
     * Check if $ip is a valid IP v4
     *
     * @param string $ip IP address to check
     *
     * @return boolean
     */
    public function isValidIP(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }


    /**
     * Load cached IP address if present
     *
     * @param string $ip IP address
     *
     * @return array|boolean
     */
    public function loadIP(string $ip): array|bool
    {
        $cacheKey = 'ip_'.$ip;
        $data = cache()->get($cacheKey);
        if ($data) {
            if ($this->debug) {
                Log::debug('Found cached data for IP: '.$ip);
            }
            return $data;
        }
        return false;
    }


    /**
     * Cache the IP address
     *
     * @param string $ip   IP address
     * @param array  $data Data to save
     *
     * @return boolean
     */
    public function saveIP(string $ip, array $data): bool
    {
        $cacheKey = 'ip_'.$ip;
        cache()->put($cacheKey, $data, now()->addHours(12));
        if ($this->debug) {
            Log::debug('Adding data to cache for IP: '.$ip);
        }
        return false;
    }


    /**
     * Fill in $ipData with data from $apiResponse
     *
     * @param array $ipData      Array of data (ip => [count, requests])
     * @param array $apiResponse Data from API lookup
     *
     * @return array
     */
    public function fillData(array $ipData, array $apiResponse, bool $save=true): array
    {
        foreach ($ipData as $ip => $row) {
            // Shorthand for data from results
            if (!array_key_exists($ip, $apiResponse)) {
                Log::warning('IP: '.$ip. ' is not in $apiResponse');
                continue;
            }
            $fetched = $apiResponse[$ip];

            Log::warning('Filling data for IP: '.$ip);
            Log::warning($row);
            Log::warning($fetched);


            // Add the Provider company name
            if (isset($fetched['company']) && $fetched['company']['name']) {
                $row['provider'] = $fetched['company']['name'];
            }
            // Add the Country plus a flag
            if (isset($fetched['location'])) {
                if (!array_key_exists('country', $row)) {
                    $row['country'] = '';
                }
                if (isset($fetched['location']['country_code'])) {
                    $flag = strtolower($fetched['location']['country_code']);
                    $row['country'] .= "<span class=\"fi fi-$flag\"></span>";
                }
                if ($fetched['location']['country']) {
                    $row['country'] .= $fetched['location']['country'];
                }

            }
            // Set up the flags to use for this IP
            $flags = [];
            if ($fetched['is_crawler']) {
                $flags[] = '<span title="Crawler Detected">🕷️</span>';
            }
            if ($fetched['is_proxy']) {
                $flags[] = '<span title="Proxy Detected">🛡️</span>';
            }
            if ($fetched['is_tor']) {
                $flags[] = '<span title="Tor Exit Node">🌐</span>';
            }
            if ($fetched['is_vpn']) {
                $flags[] = '<span title="VPN Detected">🔒</span>';
            }
            if ($fetched['is_abuser']) {
                $flags[] = '<span title="Abuser Detected">⚠️</span>';
            }
            $row['flags'] = implode(' ', $flags);
            $ipData[$ip] = $row;
            if ($save) {
                $this->saveIP($ip, $row);
            }
        }
        return $ipData;
    }

    /**
     * Perform the API lookup for $ips and return array of results
     *
     * @param array $ips IP addresses to query
     *
     * @return array
     */
    public function loadFromAPI(array $ips): array
    {
        // Now do the API lookup
        $response = Http::post(
            $this->apiUrl, [
                'ips' => $ips,
            ]
        );

        // Handle unsuccessful responses
        if (!$response->successful()) {
            throw new \Exception('Failed to fetch IP lookup data: ' . $response->body());
        }

        // Parse the API response
        $apiResults = $response->json();

        if ($this->debug) {
            Log::notice('API Response:');
            Log::notice($apiResults);
        }

        return $apiResults;
    }

}
