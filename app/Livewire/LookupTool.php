<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\LookupIPsWithAPI;
use Illuminate\Support\Facades\Log;



class LookupTool extends Component
{
    public $inputText = '';
    public $parsedResults = [];


    // Optional: Validation rules
    protected $rules = [
        'inputText' => 'required|string|min:3',
    ];



    /**
     * Run when the input/textarea text is updated
     *
     * @return void
     */
    public function updatedInputText()
    {
        $this->lookup();
    }



    /**
     * Perform the lookup action
     *
     * @return void
     */
    public function lookup()
    {
        //$this->validate();
        //$lookupService = app(LookupService::class);
        //$this->parsedResults = $lookupService->lookup($this->inputText);
        $api = new LookupIPsWithAPI;
        $data = $this->crunchInput($this->inputText);
        $this->parsedResults = $api->lookup($data);
    }



    /**
     * Render the view
     *
     * @return void
     */
    public function render()
    {
        return view('livewire.lookup-tool');
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
            $line = trim($line);
            $parts = preg_split('/\s+/', trim($line));

            //echo '<pre>', print_r($parts, true), '</pre>';
            Log::notice('Input line:');
            Log::notice($parts);

            if (isset($parts[0]) && isset($parts[1])) {
                $ip = $parts[1];
                $count = $parts[0];
                $ipData[$ip] = [
                    'ip' => $ip,
                    'count' => $count,
                    'requests' => [],
                ];
            } else if (isset($parts[11], $parts[13], $parts[14], $parts[15])) {
                $ip = $parts[11];
                $request = "{$parts[13]} {$parts[14]} {$parts[15]}";

                if (!isset($ipData[$ip])) {
                    $ipData[$ip] = [
                        'ip' => $ip,
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
                    'ip' => $ip,
                    'count' => $data['count'],
                    'requests' => implode('<br>', array_unique($data['requests'])),
                ];
            }
        )->sortByDesc('count')->all(); // Retains the keys
    }
}
