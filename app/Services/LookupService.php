<?php

namespace App\Services;

class LookupService
{
    public function lookup(string $inputText): array
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

        return collect($ipData)->map(
            function ($data, $ip) {
                return [
                'ip' => $ip,
                'count' => $data['count'],
                'requests' => implode('<br>', array_unique($data['requests'])),
                ];
            }
        )->sortByDesc('count')->values()->all();
    }
}
