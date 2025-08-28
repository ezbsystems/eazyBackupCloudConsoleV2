<div class="bg-white shadow-lg p-6 rounded-lg">
    <!-- Header Section -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Service Management</h1>
    </div>

    <!-- Tabs Navigation -->
    <div class="border-b mb-6">
        <ul class="flex space-x-4">
            <li>
                <a href="{$WEB_ROOT}/clientarea.php?action=products" data-tab="tab1" class="py-2 px-4 border-b-2 text-gray-600 hover:border-gray-300 hover:text-gray-800">
                    My Accounts
                </a>
            </li>
            <li>
                <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=services" data-tab="tab2" class="py-2 px-4 border-b-2 border-blue-500 text-blue-500 font-semibold">
                    Servers
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content Section -->
    <div class="bg-gray-50 p-4 rounded-lg">
        <!-- Messages -->
        <div id="successMessage" class="mb-4"></div>
        <div id="errorMessage" class="mb-4"></div>

        <!-- User Details Content -->
        <div id="userDetails" class="overflow-x-auto">
            <!-- Content will be loaded here via AJAX -->
        </div>

        <!-- Loading Indicator -->
        <div class="flex justify-center items-center my-4" id="detailsLoader">
            <i class="fas fa-spinner fa-spin"></i>
            <span class="ml-2">Loading details...</span>
        </div>

        <!-- Error Message -->
        <div id="detailsError" class="text-center text-red-500 hidden">
            Failed to load user details. Please try again later.
        </div>
    </div>
</div>

<!-- JavaScript to Load User Details -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Function to get query parameters
        function getQueryParam(param) {
            let params = new URLSearchParams(window.location.search);
            return params.get(param);
        }

        const serviceId = getQueryParam('id');
        if (!serviceId) {
            document.getElementById('detailsError').classList.remove('hidden');
            document.getElementById('detailsLoader').classList.add('hidden');
            return;
        }

        // Show loader
        document.getElementById('detailsLoader').classList.remove('hidden');
        document.getElementById('userDetails').classList.add('hidden');
        document.getElementById('detailsError').classList.add('hidden');

        // Fetch user details via AJAX
        fetch('/modules/servers/comet/ajax/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({ id: serviceId })
        })
        .then(response => response.text())
        .then(data => {
            document.getElementById('userDetails').innerHTML = data;
            document.getElementById('detailsLoader').classList.add('hidden');
            document.getElementById('userDetails').classList.remove('hidden');
            // Initialize any necessary JavaScript for the loaded content
            initializeTabs();
        })
        .catch(error => {
            console.error('Error loading user details:', error);
            document.getElementById('detailsLoader').classList.add('hidden');
            document.getElementById('detailsError').classList.remove('hidden');
        });

        // Function to initialize tabs using Tailwind (e.g., with Alpine.js or another library)
        function initializeTabs() {
            // Example using simple JavaScript for tab functionality
            const tabs = document.querySelectorAll('[data-tab]');
            const tabContents = document.querySelectorAll('.tab-pane');

            tabs.forEach(tab => {
                tab.addEventListener('click', function(event) {
                    event.preventDefault();
                    const target = this.getAttribute('href');

                    // Remove active classes
                    tabs.forEach(t => t.classList.remove('border-blue-500', 'text-blue-500', 'font-semibold'));
                    tabContents.forEach(content => content.classList.add('hidden'));

                    // Add active classes to the clicked tab
                    this.classList.add('border-blue-500', 'text-blue-500', 'font-semibold');

                    // Show the target tab content
                    document.querySelector(target).classList.remove('hidden');
                });
            });
        }
    });
</script>
