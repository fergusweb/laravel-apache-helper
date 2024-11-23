<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Log;


class CommandBox extends Component
{


    public $ttl = '12h'; // Default TTL
    public $ttl_values = [
        '30m'   => '30 mins',
        '1h'    => '1 hour',
        '4h'    => '4 hours',
        '8h'    => '8 hours',
        '12h'   => '12 hours',
        '24h'   => '24 hours',
        '2d'    => '2 days',
        '7d'    => '7 days',
        '30d'   => '30 days',
    ];

    public $command = 'Tick some boxes...';




    /**
     * When the 'ips-updated' event is dispatched from LookupTool component, update the command box.
     *
     * @param array $ips Array of IP addresses
     *
     * @return void
     */
    #[On('ips-updated')]
    public function updateIPsUpdated($ips)
    {
        //Log::notice($ips);
        //$this->command = implode(PHP_EOL, $ips);
        $commands = [];
        foreach ($ips as $ip) {
            $commands[] = "csf -td $ip $this->ttl";
        }
        $this->command = implode(' ; ', $commands);
    }

    public function render()
    {
        return view('livewire.command-box');
    }
}
