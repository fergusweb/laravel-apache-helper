<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\LookupService;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Log;



class LookupTool extends Component
{
    public $inputText = '';
    public $parsedResults = [];

    public $selectedIps = [];


    protected $lookupService;



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
     * Perform the lookup action
     *
     * @return void
     */
    public function lookup()
    {
        $this->validate();
        $lookupService = app(LookupService::class);
        $this->parsedResults = $lookupService->lookup($this->inputText);
    }

    /**
     * When the 'ttl-updated' event is dispatched from CommandBox component, trigger the ips-updated again.
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

    /**
     * Render the view
     *
     * @return void
     */
    public function render()
    {
        return view('livewire.lookup-tool');
    }
}
