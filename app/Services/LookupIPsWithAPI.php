<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class LookupIPsWithAPI
{

    protected bool $debug = false;

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
     * Perform a bulk lookup of all IP addresses in this array, and add data to response.
     *
     * @param array $data Parsed array of data (ip => [count, requests])
     *
     * @return array
     */
    public function lookup(array $data): array
    {
        // Array of IP addresses to look up
        $ips = array_keys($data);

        // Check if cached data exists
        $cacheKey = 'ip_lookup_' . md5(implode(',', $ips));
        $cachedResults = cache()->get($cacheKey);
        if ($cachedResults) {
            if ($this->debug) {
                Log::notice('Found cached results from IP API lookup...');
            }
            return $cachedResults;
        }
        if ($this->debug) {
            Log::notice('No cache hit.  Count of IPs to look up via IP: ' . count($ips));
        }


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

        // Run through our $data array, and insert more data from API lookups
        foreach ($data as $ip => $row) {
            if (!$ip || $ip == '-' || empty($row)) {
                continue;
            }

            $fetched = $apiResults[$ip];

            // Selected data to include:
            if (isset($fetched['company']) && $fetched['company']['name']) {
                $data[$ip]['provider'] = $fetched['company']['name'];
            }
            if (isset($fetched['location'])) {
                if (!array_key_exists('country', $data[$ip])) {
                    $data[$ip]['country'] = '';
                }
                if (isset($fetched['location']['country_code'])) {
                    $flag = strtolower($fetched['location']['country_code']);
                    $data[$ip]['country'] .= "<span class=\"fi fi-$flag\"></span>";
                }
                if ($fetched['location']['country']) {
                    $data[$ip]['country'] .= $fetched['location']['country'];
                }

            }
            // Now set up our flags
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
            $data[$ip]['flags'] = implode(' ', $flags);
        }

        // Cache the results for 5 minutes
        cache()->put($cacheKey, $data, now()->addMinutes(10));

        if ($this->debug) {
            Log::notice('Merged data:');
            Log::notice($data);
        }

        return $data;
    }

}
