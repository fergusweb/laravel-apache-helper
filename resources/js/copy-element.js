document.addEventListener("DOMContentLoaded", () => {
    // Add an event listener to the parent container
    document.body.addEventListener("click", (event) => {
        const target = event.target;

        // Check if the clicked element is a .copy span
        if (target.classList.contains("copy")) {
            // Copy the text content to the clipboard
            const text = target.textContent;
            navigator.clipboard
                .writeText(text)
                .then(() => {
                    // Display a "copied" message
                    showCopiedMessage(target);
                })
                .catch((err) => {
                    console.error("Failed to copy text: ", err);
                });
        }
    });

    function showCopiedMessage(element) {
        // Create a message element
        const message = document.createElement("div");
        message.className = "copied-message";
        message.textContent = "Copied!";

        // Position the message on top of the clicked element
        const rect = element.getBoundingClientRect();
        message.style.top = `${rect.top - 25 + window.scrollY}px`;
        message.style.left = `${rect.left + rect.width / 2 - 30 + window.scrollX}px`;
        document.body.appendChild(message);

        // Fade out and remove the message after 2 seconds
        setTimeout(() => {
            message.style.opacity = "0";
            setTimeout(() => {
                message.remove();
            }, 500); // Match the CSS transition duration
        }, 2000);
    }
});
