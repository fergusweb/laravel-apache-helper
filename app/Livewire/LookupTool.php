<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\LookupIPsWithAPI;
use App\Services\ParseInputs;



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
        $parser = new ParseInputs;
        $ipData = $parser->crunchUserInput($inputText);
        return $ipData;
    }
}
