<aside class="wrapper command-box">
    <h2>Command box</h2>
    <label class="duration">
        CSF duration:
        <select class="csf_ttl" wire:model="ttl" x-data
            @change="dispatchEvent(new CustomEvent('ttl-updated', { detail: { value: $el.value } }));">
            @foreach ($ttl_values as $key => $value)
                <option value="{{ $key }}" {{ $key == $ttl ? 'selected' : '' }}>{{ $value }}</option>
            @endforeach
        </select>
    </label>
    <p class="copy">
        <button>Copy to Clipboard</button>
    </p>
    <pre class="output">{{ $command }}</pre>

</aside>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Select the button and pre element
        const copyButton = document.querySelector('.copy button');
        const outputPre = document.querySelector('.output');

        copyButton.addEventListener('click', () => {
            // Get the content of the <pre> element
            const commandText = outputPre.textContent.trim();

            // Copy the content to the clipboard
            navigator.clipboard.writeText(commandText).then(() => {
                // Change the button text to "Copied!"
                const originalText = copyButton.textContent;
                copyButton.textContent = 'Copied!';

                // Revert the button text back to the original after 1 second
                setTimeout(() => {
                    copyButton.textContent = originalText;
                }, 1000);
            }).catch((err) => {
                console.error('Failed to copy to clipboard:', err);
            });
        });
    });
</script>

