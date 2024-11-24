<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
class TableResultsIps extends Component
{

    public $selectedIps = [];
    public $results;

    /**
     * Mount function
     *
     * @param array $results Data to display in table
     *
     * @return void
     */
    public function mount($results=array())
    {
        $this->results = $results;
    }


    /**
     * Render the view
     *
     * @return void
     */
    public function render()
    {
        return view('livewire.table-results-ips', ['results'=> $this->results]);
    }


    /**
     * Run when a row is clicked to toggle the checkbox
     *
     * @param string $ip IP address value
     *
     * @return void
     */
    public function toggleIp($ip)
    {

        if (!is_array($this->selectedIps)) {
            $this->selectedIps = array();
        }
        if (in_array($ip, $this->selectedIps)) {
            $this->selectedIps = array_filter($this->selectedIps, fn($selectedIp) => $selectedIp !== $ip);
        } else {
            $this->selectedIps[] = $ip;
        }

        //Log::notice("Toggling IP: " . $ip);
        //Log::notice($this->selectedIps);
        // Dispatch this event, so the CommandBox component can be updated.
        $this->dispatch('ips-updated', $this->selectedIps);
    }

    /**
     * When 'ttl-updated' event dispatched from CommandBox component,
     * trigger the ips-updated again.
     *
     * @param string $ttl TTL Value
     *
     * @return void
     */
    #[On('ttl-updated')]
    public function ttlUpdateEvent($ttl = '')
    {
        $this->dispatch('ips-updated', $this->selectedIps);
    }
}
