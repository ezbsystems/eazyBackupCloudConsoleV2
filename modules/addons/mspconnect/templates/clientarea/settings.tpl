<style>
    [x-cloak] { display: none !important; }
</style>
<div class="min-h-screen bg-[#11182759] text-slate-200">
    <div class="container mx-auto px-4 pb-8">
        
        <!-- Loading Overlay -->
        <div id="loading-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden">
            <div class="flex items-center">
                <div class="text-gray-300 text-lg">Loading...</div>
                <svg class="animate-spin h-8 w-8 text-gray-300 ml-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
            </div>
        </div>

        <!-- Message Container -->
        <div id="message-container" class="hidden fixed top-4 right-4 z-50 max-w-sm w-full">
            <div class="rounded-lg p-4 text-white shadow-lg border border-opacity-20">
                <!-- Message content will be inserted here -->
            </div>
        </div>

        <!-- Header -->
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center mb-8">
            <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <h2 class="text-2xl font-semibold text-white">Settings</h2>
            </div>
        </div>

        <!-- Fixed Container for Tabs and Content -->
        <div class="w-full max-w-7xl mx-auto">
            <!-- Tabs -->
            <div class="mb-8">
                <nav class="flex space-x-8" aria-label="Tabs">
                    <a href="#company" onclick="showTab('company')" id="tab-company" class="tab-link active py-2 px-1 border-b-2 border-sky-600 font-medium text-sm text-sky-400 whitespace-nowrap">
                        Company Profile
                    </a>
                    <a href="#smtp" onclick="showTab('smtp')" id="tab-smtp" class="tab-link py-2 px-1 border-b-2 border-transparent font-medium text-sm text-slate-400 hover:text-slate-200 hover:border-slate-300 whitespace-nowrap">
                        Email Settings
                    </a>
                </nav>
            </div>

            <!-- Tab Content Container -->
            <div class="tab-content-wrapper">
                <!-- Company Profile Tab -->
                <div id="content-company" class="tab-content">
                    {include file="accounts/modules/addons/mspconnect/templates/clientarea/settings_company_profile.tpl"}
                </div>

                <!-- SMTP Settings Tab -->
                <div id="content-smtp" class="tab-content hidden">
                    {include file="accounts/modules/addons/mspconnect/templates/clientarea/settings_smtp.tpl"}
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/modules/addons/mspconnect/assets/js/settings.js"></script>
<script>
// Initialize messages from URL parameters
document.addEventListener('DOMContentLoaded', function() {
    {if $success_message}
        showMessage('{$success_message|addslashes}', 'message-container', 'success');
    {/if}
    {if $error_message}
        showMessage('{$error_message|addslashes}', 'message-container', 'error');
    {/if}
    
    // Initialize country/state functionality
    if (typeof initializeCountryState === 'function') {
        initializeCountryState();
    }
    
    // Initialize logo preview
    if (typeof initializeLogoPreview === 'function') {
        initializeLogoPreview();
    }
});

function showTab(tabName) {
    // Hide all tab contents
    const contents = document.querySelectorAll('.tab-content');
    contents.forEach(content => content.classList.add('hidden'));
    
    // Remove active class from all tabs
    const tabs = document.querySelectorAll('.tab-link');
    tabs.forEach(tab => {
        tab.classList.remove('active', 'border-sky-600', 'text-sky-400');
        tab.classList.add('border-transparent', 'text-slate-400');
    });
    
    // Show selected tab content
    document.getElementById('content-' + tabName).classList.remove('hidden');
    
    // Add active class to selected tab
    const activeTab = document.getElementById('tab-' + tabName);
    activeTab.classList.add('active', 'border-sky-600', 'text-sky-400');
    activeTab.classList.remove('border-transparent', 'text-slate-400');
}
</script> 