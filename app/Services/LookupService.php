<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class LookupService
{

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
     * Lookup
     *
     * @param string $inputText Input from user
     *
     * @return array
     */
    public function lookup(string $inputText): array
    {
        $crunched = $this->crunchInput($inputText);

        //Log::notice('Crunched results...');
        //Log::notice($crunched);

        // Prepare to make API call
        //$ips = array_column($crunched, 'ip');
        $ips = array_keys($crunched);

        // Check if cached data exists
        $cacheKey = 'ip_lookup_' . md5(implode(',', $ips));
        //$cachedResults = false;
        // TODO THIS:
        $cachedResults = cache()->get($cacheKey);
        if ($cachedResults) {
            //return $this->mergeResults($data, $cachedResults);
            Log::notice('Found cached results...');
            //Log::notice($cachedResults);
            return $cachedResults;
        }

        Log::notice('No cache.  Count of IPs to look up: ' . count($ips));


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

        //Log::notice('API results...');
        //Log::notice($apiResults);

        // Insert more information into $data from the API results
        $data = array();
        foreach ($apiResults as $lookup) {
            if (!is_array($lookup)) {
                continue;
            } else {
                //Log::notice('Lookup not array?');
                //Log::notice($lookup);
            }
            $ip = $lookup['ip'];
            //Log::notice("Lookup IP: " . $ip);
            //Log::notice($lookup);
            $flags = [];
            if ($lookup['is_crawler']) {
                $flags[] = '<span title="Crawler Detected">🕷️</span>';
            }
            if ($lookup['is_proxy']) {
                $flags[] = '<span title="Proxy Detected">🛡️</span>';
            }
            if ($lookup['is_tor']) {
                $flags[] = '<span title="Tor Exit Node">🌐</span>';
            }
            if ($lookup['is_vpn']) {
                $flags[] = '<span title="VPN Detected">🔒</span>';
            }
            if ($lookup['is_abuser']) {
                $flags[] = '<span title="Abuser Detected">⚠️</span>';
            }

            $row = array(
                'ip' => $ip,
                'count' => $crunched[$ip]['count'],
                'flags' => implode(' ', $flags),
                'country' => '',
                'provider' => '',
                'requests' => $crunched[$ip]['requests'],
            );

            if (isset($lookup['location']) && isset($lookup['location']['country_code'])) {
                $flag = strtolower($lookup['location']['country_code']);
                $row['country'] .= "<span class=\"fi fi-$flag\"></span>";
            }
            if (isset($lookup['location']) && $lookup['location']['country']) {
                $row['country'] .= $lookup['location']['country'];
            }
            if (isset($lookup['company']) && $lookup['company']['name']) {
                $row['provider'] = $lookup['company']['name'];
            }

            $data[$ip] = $row;
        }

        // Cache the results for 5 minutes
        cache()->put($cacheKey, $data, now()->addMinutes(10));

        // Merge the API results with the input data
        //return $this->mergeResults($data, $apiResults);

        //Log::notice("Updated Data");
        //Log::notice($data);

        return $data;
    }

    /**
     * Crunch the input, to extract unique IPs, with counter & requests.
     *
     * @param string $inputText Input from user
     *
     * @return array
     */
    public function crunchInput(string $inputText)
    {
        $lines = explode("\n", $inputText);
        $ipData = [];

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            //echo '<pre>', print_r($parts, true), '</pre>';

            if (isset($parts[11], $parts[13], $parts[14], $parts[15])) {
                $ip = $parts[11];
                $request = "{$parts[13]} {$parts[14]} {$parts[15]}";

                if (!isset($ipData[$ip])) {
                    $ipData[$ip] = [
                        'count' => 0,
                        'requests' => [],
                    ];
                }

                $ipData[$ip]['count'] += 1;
                $ipData[$ip]['requests'][] = $request;
            }
        }

        // We're going to skip some IPs.
        $min_connections = config('app.MIN_IP_CONNECTIONS');
        $ignore_ips = config('app.IGNORE_IP_ADDRESSES');
        if ($ignore_ips) {
            $ignore_ips = explode(',', $ignore_ips);
        }
        foreach ($ipData as $ip => $data) {
            if (in_array($ip, $ignore_ips) || $data['count'] < $min_connections) {
                unset($ipData[$ip]);
            }
        }


        return collect($ipData)->map(
            function ($data, $ip) {
                return [
                    'count' => $data['count'],
                    'requests' => implode('<br>', array_unique($data['requests'])),
                ];
            }
        )->sortByDesc('count')->all(); // Retains the keys
    }

}
