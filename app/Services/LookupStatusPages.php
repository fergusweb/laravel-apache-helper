<?php

namespace App\Services;

use App\Services\LookupIPsWithAPI;
use App\Services\ParseInputs;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class LookupStatusPages
{


    protected bool $debug = false;

    /**
     * Status page URLs to scrape
     *
     * @var array
     */
    public $status_pages = [];


    /**
     * Constructor
     *
     * @param LookupIPsWithAPI $api
     */
    public function __construct(protected LookupIPsWithAPI $api,)
    {
        $this->status_pages = explode(',', config('app.APACHE_STATUS_URLS'));
    }


    /**
     * Helper to call the API lookup
     *
     * @param array $data Array of ip=>[count, requests]
     *
     * @return array
     */
    public function lookupWithAPI($data = array())
    {
        $data = $this->api->lookup($data);
        return $data;
    }

    /**
     * Parse the scraped data
     *
     * @param array $data Data from the scrape() function
     *
     * @return array
     */
    public function parse($data = array()): array
    {
        $parsedData = array();
        // If no data supplied, we'll scrape now.
        if (!$data || empty($data)) {
            $data = $this->scrape();
        }

        // Use our parser utility
        $parser = new ParseInputs;
        $ipData = $parser->crunchScraperInput($data);
        return $ipData;

        /*
        if ($this->debug) {
            Log::notice('Parsing data with ' . count($data) .' rows');
        }
        foreach ($data as $key => $row) {
            $ip = $row[11];
            $domain = $row['13'];
            $uri = $row['14'];
            $request = "$domain $uri";

            // If IP is not yet in array, prepare it.
            if (!array_key_exists($ip, $parsedData)) {
                $parsedData[$ip] = array(
                    'ip' => null,
                    'count' => 0,
                    'requests' => [],
                );
            }
            // Add data to array
            $parsedData[$ip]['ip'] = $ip;
            $parsedData[$ip]['count']++;
            if (!empty($request)) {
                $parsedData[$ip]['requests'][] = $request;
            }

        }

        //Log::notice('Parsed Data:');
        //Log::notice($parsedData);

        // Run through and clean up excluded IPs.
        $min_connections = config('app.MIN_IP_CONNECTIONS');
        $ignore_ips = config('app.IGNORE_IP_ADDRESSES');
        if ($ignore_ips) {
            $ignore_ips = explode(',', $ignore_ips);
        }
        foreach ($parsedData as $ip => $data) {
            if (in_array($ip, $ignore_ips) || $data['count'] < $min_connections) {
                unset($parsedData[$ip]);
            }
        }

        //return $parsedData;
        return collect($parsedData)->map(
            function ($data, $ip) {
                return [
                    'ip' => $ip,
                    'count' => $data['count'],
                    'requests' => implode('<br>', array_unique($data['requests'])),
                ];
            }
        )->sortByDesc('count')->all(); // Retains the keys
        */

    }

    /**
     * Scrape Pages
     *
     * @return array
     */
    public function scrape(): array
    {
        $combined = array();
        foreach ($this->status_pages as $url) {
            // Use cached data if available
            $cacheKey = 'status_' . md5($url);
            $cachedData = cache()->get($cacheKey);
            if ($cachedData) {
                if ($this->debug) {
                    Log::notice('Found cached results for: ' . $url);
                }
                return $cachedData;
            }
            // Perform lookup
            if ($this->debug) {
                Log::notice('Scraping page: ' . $url);
            }
            $response = Http::get($url);
            // Handle unsuccessful responses
            if (!$response->successful()) {
                throw new \Exception('Failed to fetch IP lookup data: ' . $response->body());
            }
            // Do any processing?
            $array = $this->parseApacheStatusTable($response);

            // Save response
            cache()->put($cacheKey, $array, now()->addSeconds(30));
        }
        return $combined;
    }

    /**
     * Use DOMDocument to parse the response, extract the table as an array
     *
     * @param \Illuminate\Http\Client\Response $response Response
     *
     * @return array
     */
    public function parseApacheStatusTable(\Illuminate\Http\Client\Response $response): array
    {
        // Get the response from the Apache Status Page
        //$response = Http::get($url);

        // Initialize DOMDocument
        $dom = new \DOMDocument();
        @$dom->loadHTML($response->body()); // Suppress warnings for malformed HTML

        // Find the table
        $tables = $dom->getElementsByTagName('table');

        // Assuming the desired table is the first one
        //$table = $tables->item(0); // No, it's the second table.
        $table = $tables->item(1);

        if (!$table) {
            return []; // Return an empty array if no table found
        }

        // Parse table rows
        $rows = $table->getElementsByTagName('tr');
        $result = [];

        foreach ($rows as $row) {
            $columns = $row->getElementsByTagName('td'); // Use <td> for data cells
            $rowData = [];

            foreach ($columns as $column) {
                $rowData[] = trim($column->textContent); // Extract and trim cell text
            }

            if (!empty($rowData)) {
                $result[] = $rowData; // Add row to the result if not empty
            }
        }

        return $result; // Return parsed table as an array
    }


}
