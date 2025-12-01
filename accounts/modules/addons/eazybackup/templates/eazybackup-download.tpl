<div class="min-h-screen bg-slate-950 text-gray-300" x-data="{ openModal: null }" @keydown.escape="openModal = null">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
        <div class="container mx-auto px-4 pb-8 min-h-screen flex items-center">  

            
                            
<div class="relative rounded-3xl border border-slate-800/80 bg-slate-900/80 backdrop-blur-md shadow-[0_18px_60px_rgba(0,0,0,0.6)] max-w-4xl mx-auto px-6 py-7 lg:px-8 lg:py-8">
    <!-- Optional success pill floating over the card -->
    <div class="absolute -top-4 left-6 inline-flex items-center gap-2 rounded-full border border-emerald-500/40 bg-slate-950/90 px-3 py-1 text-xs text-emerald-300 shadow-lg">
        <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
        <span>New backup account created</span>
    </div>

    <!-- Global Message Container (Always Present) -->
    <div id="globalMessage" class="text-white px-4 py-2 rounded-md mb-4 hidden" role="alert"></div>

    <!-- Alpine Toasts (you already have this, just moved up slightly) -->
    <script src="{$WEB_ROOT}/modules/addons/eazybackup/templates/assets/js/ui.js"></script>
    <div x-data="toastCenter()" x-init="init()" class="pointer-events-none fixed top-4 inset-x-0 z-[70] flex justify-center">
        <template x-for="t in toasts" :key="t.id">
            <div
                x-show="t.show"
                x-transition.opacity.duration.200ms
                class="pointer-events-auto mb-2 rounded-md px-4 py-2 text-sm shadow-lg"
                :class="t.type === 'success' ? 'bg-emerald-600 text-white' : (t.type === 'error' ? 'bg-rose-600 text-white' : 'bg-slate-700 text-slate-100')"
                @click="remove(t.id)"
            >
                <span x-text="t.message"></span>
            </div>
        </template>
    </div>

    <!-- Main layout: instructions on the left, downloads on the right -->
    <div class="flex flex-col gap-8 lg:flex-row lg:items-start">
        <!-- Left: welcome + steps -->
        <div class="flex-1 space-y-6">
            <div class="flex items-center gap-4">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-[radial-gradient(circle_at_30%_0%,#fecc80_0%,#fe7800_35%,#fe5000_100%)] shadow-[0_0_40px_rgba(248,113,22,0.6)]">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-9 w-9 text-white">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl text-slate-50 font-semibold">
                        Welcome to eazyBackup
                    </h1>
                    <p class="mt-1 text-sm text-slate-300">
                        Your account is ready. Download the client and run your first backup in a few minutes.
                    </p>
                </div>
            </div>

            <div class="space-y-4">
                <p class="text-sm text-slate-200">
                    Getting started is simple:
                </p>
                <ol class="list-decimal pl-5 space-y-2 text-sm text-slate-100">
                    <li>Download the eazyBackup client for your platform.</li>
                    <li>Install the software on your device or server.</li>
                    <li>
                        Sign in with your username
                        <span class="whitespace-nowrap font-semibold">
                            {$clientsdetails.email|default:$loggedinuser.email}
                        </span>
                        and the password you just set.
                    </li>
                    <li>
                        Create a <span class="font-semibold">Protected Item</span> to choose what you want to back up.
                    </li>
                </ol>

                <p class="text-xs text-slate-400">
                    New to eazyBackup?
                    <a href="https://docs.eazybackup.com/guides/getting-started-guide"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="text-emerald-400 hover:text-emerald-300 underline underline-offset-2">
                        Read the Getting Started Guide
                    </a>.
                </p>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900/70 px-4 py-3">
                <div class="text-xs font-semibold text-slate-300 mb-2 flex items-center gap-2">
                    <span class="inline-flex h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                    Recommended best practices
                </div>
                <ul class="list-disc pl-5 space-y-1.5 text-xs text-slate-300">
                    <li>Schedule backups to run daily outside business hours.</li>
                    <li>Exclude temporary or cache folders to improve performance.</li>
                    <li>After your first backup, perform a small test restore.</li>
                    <li>Enable 2FA in the client area for extra account security.</li>
                </ul>
            </div>
        </div>

        <!-- Right: download widget -->
        <div class="w-full lg:max-w-sm">
            <div class="rounded-2xl border border-slate-800 bg-slate-900/90 px-4 py-4 shadow-inner">
                <h3 class="text-sm font-medium text-slate-100 mb-1">
                    Download client software
                </h3>
                <p class="text-xs text-slate-400 mb-4">
                    Choose your platform. You can install the client on multiple devices.
                </p>

                <!-- OS segmented control -->
                <div class="inline-flex w-full rounded-xl bg-slate-900 border border-slate-800/80 p-1 text-xs">
                    <button
                        @click="openModal = 'windows'"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg px-2 py-2 font-medium text-slate-100 bg-orange-600/90 hover:bg-orange-600 shadow-[0_0_22px_rgba(248,113,22,0.45)] transition-all">
                        <i class="mdi mdi-microsoft-windows text-base"></i>
                        <span>Windows</span>
                    </button>
                    <button
                        @click="openModal = 'linux'"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg px-2 py-2 font-medium text-slate-200 hover:bg-slate-800 transition-all">
                        <i class="mdi mdi-linux text-base"></i>
                        <span>Linux</span>
                    </button>
                    <button
                        @click="openModal = 'macos'"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg px-2 py-2 font-medium text-slate-200 hover:bg-slate-800 transition-all">
                        <i class="mdi mdi-apple text-base"></i>
                        <span>macOS</span>
                    </button>
                    <button
                        @click="openModal = 'synology'"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg px-2 py-2 font-medium text-slate-200 hover:bg-slate-800 transition-all">
                        <i class="mdi mdi-server text-base"></i>
                        <span>Synology</span>
                    </button>
                </div>

                <!-- Footnote/help text -->
                <div class="mt-4 space-y-1.5 text-[11px] text-slate-400">
                    <p class="flex items-center gap-1">
                        <i class="fa-regular fa-circle-check text-[10px] text-emerald-400"></i>
                        <span>All backup data is encrypted using AES-256-CTR with a key derived from your account password.</span>
                    </p>
                    <p class="flex items-center gap-1">
                        <i class="fa-regular fa-circle-question text-[10px] text-slate-500"></i>
                        <span>Unsure which version to choose? Start with Windows if this is your main workstation.</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
  
</div>

    <!-- Modals Container with Alpine.js -->
    <!-- Windows Modal -->
    <div x-show="openModal === 'windows'" class="fixed inset-0 flex items-center justify-center z-50 lg:ml-64" x-cloak>
        <div @click.away="openModal = null" class="bg-gray-800 rounded-lg w-full max-w-3xl mx-4">
            <div class="flex justify-between items-center bg-orange-600 text-white p-4 rounded-t-lg">
                <h5 class="text-gray-100 text-lg font-semibold flex items-center">
                    <i class="fa-brands fa-windows mr-2"></i> Download client software - Windows
                </h5>
                <button @click="openModal = null" class="text-white text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <div class="mb-4">
                    <h2 class="text-gray-100 text-xl font-semibold">Windows</h2>
                    <div class="flex space-x-2 mt-2">
                        <!-- Tooltip Icons -->
                        <div class="relative group">
                            <i class="fas fa-desktop text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the eazyBackup desktop app.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-terminal text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the eazyBackup command-line client.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-globe text-gray-100"></i>
                            <span class="tooltip-content">
                                Can be controlled and configured from the eazyBackup web admin interface.
                            </span>
                        </div>
                    </div>
                </div>
                <div class="download-button-container dl-windows my-4 flex flex-wrap justify-start">
                    <a class="flex items-center bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://csw.eazybackup.ca/dl/1">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> Any CPU
                    </a>
                    <a class="flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://csw.eazybackup.ca/dl/5">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> x86_64 only
                    </a>
                    <a class="flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://csw.eazybackup.ca/dl/3">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> x86_32 only
                    </a>
                </div>
                <hr class="my-4">
                <div>
                    <h3 class="text-gray-100 text-lg font-medium mb-2">System Requirements</h3>
                    <ul class="text-gray-100 list-disc list-inside text-left">
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
            <div class="flex justify-between items-center bg-orange-600 text-white p-4 rounded-t-lg">
                <h5 class="text-gray-100 text-lg font-semibold flex items-center">
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
                                Includes the eazyBackup Linux desktop app with GUI.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-terminal text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the eazyBackup command-line client.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-globe text-gray-100"></i>
                            <span class="tooltip-content">
                                Can be controlled and configured from the eazyBackup Control Panel interface.
                            </span>
                        </div>
                    </div>
                </div>
                <div class="download-button-container dl-linux my-4">
                    <!-- .deb Installer -->
                    <h6 class="text-gray-100 text-md font-medium mb-2">.deb</h6>
                    <div class="flex items-center mb-4 flex-wrap">
                        <!-- Download Button -->
                        <a class="flex items-center bg-orange-400 hover:bg-orange-600 text-white text-sm font-semibold py-2 px-4 rounded mr-3 mb-2" href="https://csw.eazybackup.ca/dl/21">
                            <i class="fa-solid fa-file-arrow-down mr-2"></i> Download
                        </a>
                        <!-- Copy Buttons -->
                        <button class="flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 px-4 rounded mr-2 mb-2 copy-btn" data-clipboard-text="curl -O -J -d 'SelfAddress=https%3A%2F%2Fcsw.eazybackup.ca%2F&Platform=21' -X POST 'https://csw.eazybackup.ca/api/v1/admin/branding/generate-client/by-platform'">
                            <i class="fa-regular fa-copy mr-1"></i> Copy as cURL
                        </button>
                        <button class="flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 px-4 rounded mb-2 copy-btn" data-clipboard-text="wget --content-disposition --post-data 'SelfAddress=https%3A%2F%2Fcsw.eazybackup.ca%2F&Platform=21' 'https://csw.eazybackup.ca/api/v1/admin/branding/generate-client/by-platform'">
                            <i class="fa-regular fa-copy mr-1"></i> Copy as wget
                        </button>
                    </div>

                    <hr class="my-4">

                    <!-- .tar.gz Installer -->
                    <h6 class="text-gray-100 text-md font-medium mb-2">.tar.gz</h6>
                    <div class="flex items-center mb-4 flex-wrap">
                        <!-- Download Button -->
                        <a class="flex items-center bg-orange-400 hover:bg-orange-600 text-white text-sm font-semibold py-2 px-4 rounded mr-3 mb-2" href="https://csw.eazybackup.ca/dl/7">
                            <i class="fa-solid fa-file-arrow-down mr-2"></i> Download
                        </a>
                        <!-- Copy Buttons -->
                        <button class="flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 px-4 rounded mr-2 mb-2 copy-btn" data-clipboard-text="curl -O -J -d 'SelfAddress=https%3A%2F%2Fcsw.eazybackup.ca%2F&Platform=7' -X POST 'https://csw.eazybackup.ca/api/v1/admin/branding/generate-client/by-platform'">
                            <i class="fa-regular fa-copy mr-1"></i> Copy as cURL
                        </button>
                        <button class="flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 px-4 rounded mb-2 copy-btn" data-clipboard-text="wget --content-disposition --post-data 'SelfAddress=https%3A%2F%2Fcsw.eazybackup.ca%2F&Platform=7' 'https://csw.eazybackup.ca/api/v1/admin/branding/generate-client/by-platform'">
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
            <div class="flex justify-between items-center bg-orange-600 text-white p-4 rounded-t-lg">
                <h5 class="text-gray-100 text-lg font-semibold flex items-center">
                    <i class="fa-brands fa-apple mr-2"></i> Download client software - macOS
                </h5>
                <button @click="openModal = null" class="text-white text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <div class="mb-4">
                    <h2 class="text-gray-100 text-xl font-semibold">macOS</h2>
                    <div class="flex space-x-2 mt-2">
                        <!-- Tooltip Icons -->
                        <div class="relative group">
                            <i class="fas fa-desktop text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the eazyBackup desktop app.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-terminal text-gray-100"></i>
                            <span class="tooltip-content">
                                Includes the eazyBackup command-line client.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-globe text-gray-100"></i>
                            <span class="tooltip-content">
                                Can be controlled and configured from the eazyBackup web admin interface.
                            </span>
                        </div>
                    </div>
                </div>
                <div class="download-button-container dl-macos my-4 flex flex-wrap">
                    <a class="flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://csw.eazybackup.ca/dl/8">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> x86_64
                    </a>
                    <a class="flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://csw.eazybackup.ca/dl/20">
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
            <div class="flex justify-between items-center bg-orange-600 text-white p-4 rounded-t-lg">
                <h5 class="text-gray-100 text-lg font-semibold flex items-center">
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
                                Includes the eazyBackup command-line client.
                            </span>
                        </div>
                        <div class="relative group">
                            <i class="fas fa-globe text-gray-100"></i>
                            <span class="tooltip-content">
                                Can be controlled and configured from the eazyBackup web admin interface.
                            </span>
                        </div>
                    </div>
                </div>
                <div class="download-button-container dl-synology my-4 flex flex-wrap">
                    <a class="flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://csw.eazybackup.ca/dl/18">
                        <i class="fa-solid fa-file-arrow-down mr-2"></i> DSM 6
                    </a>
                    <a class="flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold py-2 px-4 rounded m-1" href="https://csw.eazybackup.ca/dl/19">
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
            this.classList.remove('bg-orange-600', 'hover:bg-orange-700');
            this.classList.add('bg-green-500', 'hover:bg-green-700');

            // Revert back after 2 seconds
            setTimeout(() => {
                this.innerHTML = originalContent;
                this.classList.remove('bg-green-500', 'hover:bg-green-700');
                this.classList.add('bg-orange-600', 'hover:bg-orange-700');
            }, 2000);
        });
    });
});
</script>
