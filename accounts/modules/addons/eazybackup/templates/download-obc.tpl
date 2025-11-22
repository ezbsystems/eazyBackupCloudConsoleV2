<!-- Include Font Awesome -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
<!-- Include Material Design Icons -->
<link href="https://cdn.jsdelivr.net/npm/@mdi/font@6.5.95/css/materialdesignicons.min.css" rel="stylesheet">



<!-- Optional: Styling for tooltips -->
<style>
/* Hide tooltips by default */
.tooltip-content {
    display: none;
}

/* Show tooltip on group hover */
.group:hover .tooltip-content {
    display: block;
}

/* Positioning and styling for tooltips */
.tooltip-content {
    position: absolute;
    bottom: 125%; /* Adjust as needed */
    left: 50%;
    transform: translateX(-50%);
    background-color: #4B5563; /* Tailwind's gray-700 */
    color: white;
    padding: 0.5rem;
    border-radius: 0.25rem;
    white-space: nowrap;
    z-index: 10;
    font-size: 0.75rem;
}

</style>

<div class="flex justify-center items-center min-h-screen bg-gray-700" x-data="{ openModal: null }" @keydown.escape="openModal = null">
    <div class="bg-gray-800 shadow rounded-lg p-8 text-center max-w-4xl">
        <h1 class="text-2xl text-gray-100 font-semibold mb-6">Download the OBC client</h1>
        <div class="signup-content">
            <div class="success mb-6 flex justify-center items-center text-white h-24 w-24 bg-[radial-gradient(#4f46e5_0%,#4338ca_100%)] mx-auto rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-12">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
            </div>

            <div class="download">
                <p class="text-md text-gray-100 mb-6">
                    Download the OBC client for your platform using the buttons below.<br>
                    We're here if you need any assistance!
                </p>

               <!-- New Download Buttons Triggering Modals -->
               <div class="dialog-content mini-downloads-widget">
                    <h3 class="text-lg text-gray-100 font-medium mb-4">Download client software</h3>
                    <div class="flex justify-center divide-x divide-indigo-700">
                        <!-- Windows Button -->
                        <button 
                            @click="openModal = 'windows'" 
                            class="flex items-center bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold py-2 px-4 text-sm rounded-l-md transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <i class="mdi mdi-microsoft-windows mr-2"></i> Windows
                        </button>

                        <!-- Linux Button -->
                        <button 
                            @click="openModal = 'linux'" 
                            class="flex items-center bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold py-2 px-4 text-sm transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <i class="mdi mdi-linux mr-2"></i> Linux
                        </button>

                        <!-- macOS Button -->
                        <button 
                            @click="openModal = 'macos'" 
                            class="flex items-center bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold py-2 px-4 text-sm transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <i class="mdi mdi-apple mr-2"></i> macOS
                        </button>

                        <!-- Synology Button -->
                        <button 
                            @click="openModal = 'synology'" 
                            class="flex items-center bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold py-2 px-4 text-sm rounded-r-md transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <i class="mdi mdi-server mr-2"></i> Synology
                        </button>
                    </div>
                </div>
                <!-- End of New Download Buttons -->
            </div>
        </div>
    </div>

    <!-- Modals Container with Alpine.js -->
    <!-- Windows Modal -->
    <div x-show="openModal === 'windows'" class="fixed inset-0 flex items-center justify-center z-50 lg:ml-64" x-cloak>
        <div @click.away="openModal = null" class="bg-gray-800 rounded-lg w-full max-w-3xl mx-4">
            <div class="flex justify-between items-center bg-indigo-600 text-white p-4 rounded-t-lg">
                <h5 class="text-gray-100 text-lg font-semibold flex items-center">
                    <i class="fa-brands fa-windows mr-2"></i> Download client software - Windows
                </h5>
                <button @click="openModal = null" class="text-white text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <div class="mb-4">
                    <h2 class="text-gray-100 text-gray-100 text-xl font-semibold">Windows</h2>
                    <div class="flex space-x-2 mt-2">
                        <!-- Tooltip Icons -->
                        <div class="relative group">
                            <i class="fas fa-desktop text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the OBC desktop app.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-terminal text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the OBC command-line client.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-globe text-gray-100"></i>
                            <span class="tooltip-content">
                                Can be controlled and configured from the OBC web admin interface.
                            </span>
                        </div>
                    </div>
                </div>
                <div class="download-button-container dl-windows my-4 flex flex-wrap justify-start">
                    <a class="flex items-center bg-indigo-500 hover:bg-indigo-600 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://panel.obcbackup.com/dl/1">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> Any CPU
                    </a>
                    <a class="flex items-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://panel.obcbackup.com/dl/5">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> x86_64 only
                    </a>
                    <a class="flex items-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://panel.obcbackup.com/dl/3">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> x86_32 only
                    </a>
                </div>
                <hr class="my-4">
                <div>
                    <h3 class="text-gray-100 text-gray-100 text-lg font-medium mb-2">System Requirements</h3>
                    <ul class="text-gray-100 text-gray-100 list-disc list-inside text-left">
                        <li>CPU: x86_64 — x86_32 (+SSE2)</li>
                        <li>Screen resolution: 1024x600</li>
                        <li>Operating system: Windows 7, Windows Server 2008 R2 or newer</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Linux Modal -->
    <div x-show="openModal === 'linux'" class="fixed inset-0 flex items-center justify-center z-50 lg:ml-64" x-cloak>
        <div @click.away="openModal = null" class="bg-gray-800 rounded-lg w-full max-w-3xl mx-4">
            <div class="flex justify-between items-center bg-indigo-600 text-white p-4 rounded-t-lg">
                <h5 class="text-lg font-semibold flex items-center">
                    <i class="fa-brands fa-linux mr-2"></i> Download client software - Linux
                </h5>
                <button @click="openModal = null" class="text-white text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <div class="mb-4">
                    <h2 class="text-gray-100 text-xl font-semibold">Linux</h2>
                    <div class="flex space-x-2 mt-2">
                        <!-- Tooltip Icons -->
                        <div class="relative group">
                            <i class="fas fa-desktop text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the OBC Linux desktop app with GUI.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-terminal text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the OBC command-line client.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-globe text-gray-100"></i>
                            <span class="tooltip-content">
                                Can be controlled and configured from the OBC Control Panel interface.
                            </span>
                        </div>
                    </div>
                </div>
                <div class="download-button-container dl-linux my-4">
                    <!-- .deb Installer -->
                    <h6 class="text-gray-100 text-md font-medium mb-2">.deb</h6>
                    <div class="flex items-center mb-4 flex-wrap">
                        <!-- Download Button -->
                        <a class="flex items-center bg-indigo-400 hover:bg-indigo-600 text-white text-sm font-semibold py-2 px-4 rounded mr-3 mb-2" href="https://panel.obcbackup.com/dl/21">
                            <i class="fa-solid fa-file-arrow-down mr-2"></i> Download
                        </a>
                        <!-- Copy Buttons -->
                        <button class="flex items-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 px-4 rounded mr-2 mb-2 copy-btn" data-clipboard-text="curl -O -J -d 'SelfAddress=https%3A%2F%2Fpanel.obcbackup.com%2F&Platform=21' -X POST 'https://panel.obcbackup.com/api/v1/admin/branding/generate-client/by-platform'">
                            <i class="fa-regular fa-copy mr-1"></i> Copy as cURL
                        </button>
                        <button class="flex items-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 px-4 rounded mb-2 copy-btn" data-clipboard-text="wget --content-disposition --post-data 'SelfAddress=https%3A%2F%2Fpanel.obcbackup.com%2F&Platform=21' 'https://panel.obcbackup.com/api/v1/admin/branding/generate-client/by-platform'">
                            <i class="fa-regular fa-copy mr-1"></i> Copy as wget
                        </button>
                    </div>

                    <hr class="my-4">

                    <!-- .tar.gz Installer -->
                    <h6 class="text-gray-100 text-md font-medium mb-2">.tar.gz</h6>
                    <div class="flex items-center mb-4 flex-wrap">
                        <!-- Download Button -->
                        <a class="flex items-center bg-indigo-400 hover:bg-indigo-600 text-white text-sm font-semibold py-2 px-4 rounded mr-3 mb-2" href="https://panel.obcbackup.com/dl/7">
                            <i class="fa-solid fa-file-arrow-down mr-2"></i> Download
                        </a>
                        <!-- Copy Buttons -->
                        <button class="flex items-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 px-4 rounded mr-2 mb-2 copy-btn" data-clipboard-text="curl -O -J -d 'SelfAddress=https%3A%2F%2Fpanel.obcbackup.com%2F&Platform=7' -X POST 'https://panel.obcbackup.com/api/v1/admin/branding/generate-client/by-platform'">
                            <i class="fa-regular fa-copy mr-1"></i> Copy as cURL
                        </button>
                        <button class="flex items-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 px-4 rounded mb-2 copy-btn" data-clipboard-text="wget --content-disposition --post-data 'SelfAddress=https%3A%2F%2Fpanel.obcbackup.com%2F&Platform=7' 'https://panel.obcbackup.com/api/v1/admin/branding/generate-client/by-platform'">
                            <i class="fa-regular fa-copy mr-1"></i> Copy as wget
                        </button>
                    </div>
                </div>

                <hr class="my-4">
                <div>
                    <h3 class="text-gray-100 text-lg font-medium mb-2">System Requirements</h3>
                    <ul class="text-gray-100 list-disc list-inside text-left">
                        <li>CPU: x86_64 — x86_32 (+SSE2) — ARM 32 (v6kl/v7l +vfp) — ARM 64</li>                        
                        <li>Operating system: Ubuntu 16.04+, Debian 9+, CentOS 7+, Fedora 30+</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- macOS Modal -->
    <div x-show="openModal === 'macos'" class="fixed inset-0 flex items-center justify-center z-50 lg:ml-64" x-cloak>
        <div @click.away="openModal = null" class="bg-gray-800 rounded-lg w-full max-w-3xl mx-4">
            <div class="flex justify-between items-center bg-indigo-600 text-white p-4 rounded-t-lg">
                <h5 class="text-lg font-semibold flex items-center">
                    <i class="fa-brands fa-apple mr-2"></i> Download client software - macOS
                </h5>
                <button @click="openModal = null" class="text-white text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <div class="mb-4">
                    <h2 class="text-gray-100 text-gray-100 text-xl font-semibold">macOS</h2>
                    <div class="flex space-x-2 mt-2">
                        <!-- Tooltip Icons -->
                        <div class="relative group">
                            <i class="fas fa-desktop text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the OBC desktop app.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-terminal text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the OBC command-line client.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-globe text-gray-100"></i>
                            <span class="tooltip-content">
                                Can be controlled and configured from the OBC web admin interface.
                            </span>
                        </div>
                    </div>
                </div>
                <div class="download-button-container dl-macos my-4 flex flex-wrap">
                    <a class="flex items-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://panel.obcbackup.com/dl/8">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> x86_64
                    </a>
                    <a class="flex items-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://panel.obcbackup.com/dl/20">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> Apple Silicon
                    </a>
                </div>
                <hr class="my-4">
                <div>
                    <h3 class="text-gray-100 text-lg font-medium mb-2">System Requirements</h3>
                    <ul class="text-gray-100 list-disc list-inside text-left">
                        <li>CPU: Intel or Apple Silicon</li>
                        <li>Screen resolution: 1024x600</li>
                        <li>Operating system: macOS 10.12 or newer</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Synology Modal -->
    <div x-show="openModal === 'synology'" class="fixed inset-0 flex items-center justify-center z-50 lg:ml-64" x-cloak>
        <div @click.away="openModal = null" class="bg-gray-800 rounded-lg w-full max-w-3xl mx-4">
            <div class="flex justify-between items-center bg-indigo-600 text-white p-4 rounded-t-lg">
                <h5 class="text-lg font-semibold flex items-center">
                    <i class="fa-solid fa-server mr-2"></i> Download client software - Synology
                </h5>
                <button @click="openModal = null" class="text-white text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <div class="mb-4">
                    <h2 class="text-gray-100 text-xl font-semibold">Synology</h2>
                    <div class="flex space-x-2 mt-2">
                        <!-- Tooltip Icons -->
                        <div class="relative group">
                            <i class="fas fa-terminal text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the OBC command-line client.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-globe text-gray-100"></i>
                            <span class="tooltip-content">
                                Can be controlled and configured from the OBC web admin interface.
                            </span>
                        </div>
                    </div>
                </div>
                <div class="download-button-container dl-synology my-4 flex flex-wrap">
                    <a class="flex items-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://panel.obcbackup.com/dl/18">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> DSM 6
                    </a>
                    <a class="flex items-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://panel.obcbackup.com/dl/19">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> DSM 7
                    </a>
                </div>
                <hr class="my-4">
                <div>
                    <h3 class="text-gray-100 text-lg font-medium mb-2">System Requirements</h3>
                    <ul class="text-gray-100 list-disc list-inside text-left">
                        <li>Operating system: DSM 6 — DSM 7</li>
                        <li>CPU: x86_64 — x86_32 — ARMv7 — ARMv8</li>                        
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Copy to Clipboard -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Function to copy text to clipboard
    function copyToClipboard(text) {
        if (!navigator.clipboard) {
            // Fallback for older browsers
            var textArea = document.createElement("textarea");
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
            } catch (err) {
                console.error('Fallback: Oops, unable to copy', err);
            }
            document.body.removeChild(textArea);
            return;
        }
        navigator.clipboard.writeText(text).then(function () {
            // Success
        }, function (err) {
            console.error('Async: Could not copy text: ', err);
        });
    }

    // Select all copy buttons
    var copyButtons = document.querySelectorAll('.copy-btn');

    copyButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var textToCopy = this.getAttribute('data-clipboard-text');
            copyToClipboard(textToCopy);

            // Change button text to indicate success
            var originalContent = this.innerHTML;
            this.innerHTML = '<i class="fa-regular fa-copy mr-1"></i> Copied!';
            this.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
            this.classList.add('bg-green-500', 'hover:bg-green-700');

            // Revert back after 2 seconds
            setTimeout(() => {
                this.innerHTML = originalContent;
                this.classList.remove('bg-green-500', 'hover:bg-green-700');
                this.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
            }, 2000);
        });
    });
});
</script>
