<?php

namespace App\Services;

use App\Services\LookupIPsWithAPI;
use App\Services\ParseInputs;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
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
     * @param LookupIPsWithAPI $api Load the api automatically
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
     * @return \Illuminate\Support\Collection<TKey, TValue>
     */
    public function parse($data = array())
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
    }

    /**
     * Scrape Pages, using concurrency & caching
     *
     * @return array
     */
    public function scrape(): array
    {
        $combined = array();
        $responses = array();


        // Loop through URLs and check for cached results
        foreach ($this->status_pages as $url) {
            $cacheKey = 'status_' . md5($url);
            $responses[$url] = Cache::remember(
                $cacheKey, 300, function () use ($url) {
                    return null; // Placeholder to ensure missing cache entries are fetched below
                }
            );
            if ($responses[$url]) {
                $combined = array_merge($combined, $responses[$url]);
                if ($this->debug) {
                    Log::notice('Found cached results for: ' . $url);
                }
            }
        }
        // Filter out URLs that were not found in the cache
        $uncachedUrls = array_keys(array_filter($responses, fn($value) => $value === null));

        // Fetch remaining URLs concurrently
        if (!empty($uncachedUrls)) {
            $apiResponses = Http::pool(
                function ($pool) use ($uncachedUrls) {
                    return array_map(fn($url) => $pool->get($url), $uncachedUrls);
                }
            );

            // Process and store responses in cache
            foreach ($uncachedUrls as $index => $url) {
                $response = $apiResponses[$index];

                if ($response->successful()) {
                    if ($this->debug) {
                        Log::notice('Found cached results for: ' . $url);
                    }
                    // Not this:
                    $responses[$url] = $response->body();
                    $parsedData = $this->parseApacheStatusTable($response);
                    $responses[$url] = $parsedData;
                    $combined = array_merge($combined, $parsedData);

                    // Cache for next time.
                    Cache::put('status_' . md5($url), $parsedData, now()->addSeconds(30));
                } else {
                    Log::warning('Failed to fetch: ' . $url);
                    Log::warning($response->status());
                }
            }
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
