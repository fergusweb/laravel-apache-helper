<div>
    <form>
        <fieldset>
            <legend>Paste list of IPs or rows from apache-status</legend>

            <textarea id="inputText" wire:model.change="inputText" rows="6" placeholder="Paste data here..."></textarea>

            @error('inputText')
                <p class="error">{{ $message }}</p>
            @enderror
        </fieldset>
    </form>

    @if (!empty($parsedResults))
        <table>
            <thead>
                <tr>
                    <th>&nbsp;</th>
                    <th>IP Address</th>
                    <th>Count</th>
                    <th>Flags</th>
                    <th>Country</th>
                    <th>Provider</th>
                    <th>Requests</th>
                </tr>
            </thead>
            <tbody x-data>
                @foreach ($parsedResults as $result)
                    <tr wire:click="toggleIp('{{ $result['ip'] }}')"
                        @click="$el.querySelector('input[type=checkbox]').click()">
                        <td class="cb">
                            <input type="checkbox" id="check_{{ $result['ip'] }}" value="{{ $result['ip'] }}"
                                wire:model="selectedIps" />
                        </td>
                        <td class="ip">
                            {{ $result['ip'] }}
                        </td>
                        <td>
                            {{ $result['count'] }}
                        </td>
                        <td>
                            TODO: Flags
                        </td>
                        <td>
                            TODO: Country
                        </td>
                        <td>
                            TODO: Provider
                        </td>
                        <td class="requests">
                            {!! $result['requests'] !!}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    <style>
        table td.ip {
            cursor: pointer;
        }

        .copy-notice {
            position: absolute;
            background: #000;
            color: #fff;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            opacity: 1;
            transition: opacity 0.5s ease;
            z-index: 1000;

            &.fade-out {
                opacity: 0;
            }
        }
    </style>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Attach a click event listener to the document
        document.addEventListener('click', (event) => {
            // Check if the clicked element has the class 'ip'
            if (event.target.classList.contains('ip')) {
                // Get the text content of the clicked cell
                const ipText = event.target.textContent.trim();

                // Copy the text to the clipboard
                navigator.clipboard.writeText(ipText).then(() => {
                    console.log(`Copied to clipboard: ${ipText}`);

                    // Show "Copied!" feedback
                    const copiedMessage = document.createElement('span');
                    copiedMessage.textContent = 'Copied!';
                    copiedMessage.classList.add('copy-notice');

                    // Position the message in the top-right corner of the clicked cell
                    const rect = event.target.getBoundingClientRect();
                    copiedMessage.style.position = 'absolute';
                    copiedMessage.style.top = `${rect.top + window.scrollY}px`;
                    copiedMessage.style.left =
                        `${rect.right + window.scrollX - 60}px`; // Adjust for message width

                    document.body.appendChild(copiedMessage);

                    // Fade out and remove the message after 1500ms
                    setTimeout(() => {
                        copiedMessage.classList.add('fade-out');
                        setTimeout(() => {
                            copiedMessage.remove();
                        }, 500); // Wait for fade-out transition to complete
                    }, 1000);
                }).catch((err) => {
                    console.error('Failed to copy to clipboard:', err);
                });
            }
        });
    });
</script>

