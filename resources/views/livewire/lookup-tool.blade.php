<div>
    <form>
        <fieldset>

            @if (empty($inputText))
                <legend>Paste list of IPs or rows from apache-status</legend>

                <textarea id="inputText" wire:model.change="inputText" rows="6" spellCheck="false" placeholder="Paste data here..."></textarea>
            @else
                <a class="button" href="{{ url('/lookup') }}">Reload</a>
            @endif

            @error('inputText')
                <p class="error">{{ $message }}</p>
            @enderror
        </fieldset>
    </form>

    @if (!empty($parsedResults))
        @livewire(TableResultsIps::class, ['results' => $parsedResults])
    @endif

</div>

