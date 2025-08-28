<!-- accounts\modules\addons\eazybackup\templates\userdetails.tpl -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Details</title>
    <!-- Include Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Include Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <!-- Include Alpine.js -->
    <script src="//unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <!-- Custom Styles -->
    <style>
        [x-cloak] { display: none; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="max-w-7xl mx-auto p-6">
        <div class="bg-white shadow-lg p-6 rounded-lg">
            <!-- Header Section -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Service Management</h1>
            </div>

            <!-- Tabs Navigation (Existing Navigation if any) -->
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
                    <i class="fas fa-spinner fa-spin text-blue-500"></i>
                    <span class="ml-2 text-gray-700">Loading details...</span>
                </div>

                <!-- Error Message -->
                <div id="detailsError" class="text-center text-red-500 hidden">
                    Failed to load user details. Please try again later.
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->

    <!-- Reset Password Modal -->
    <div x-data="{ open: $store.modals.resetPassword }" x-show="open" @keydown.escape.window="open = false" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="resetPasswordModalLabel" role="dialog" aria-modal="true" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 text-center sm:block sm:p-0">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="open = false"></div>

            <!-- Modal Panel -->
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form class="p-6" method="post">
                    <!-- Modal Header -->
                    <div class="flex justify-between items-center mb-4">
                        <h5 class="text-lg font-medium text-gray-900" id="resetPasswordModalLabel">Password Reset for <span id="resetPasswordUsername"></span></h5>
                        <button type="button" class="text-gray-500 hover:text-gray-700" @click="open = false">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div>
                        <!-- Error Alert -->
                        <div id="passwordErrorMessage" class="hidden bg-red-100 text-red-700 px-4 py-2 rounded mb-4">
                            <!-- Dynamic error message -->
                        </div>

                        <input type="hidden" id="resetpasswordserviceId" name="id" value="" />

                        <!-- New Password Field -->
                        <div class="mb-4">
                            <label for="inputNewPassword1" class="block text-gray-700 font-medium mb-2">
                                <i class="fas fa-lock mr-2 text-blue-500"></i> New Password
                            </label>
                            <div class="flex">
                                <input type="password" class="w-full border border-gray-300 rounded-l-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" id="inputNewPassword1" name="newpw" autocomplete="off" placeholder="Enter new password" required />
                                <button type="button" class="bg-gray-100 border border-gray-300 rounded-r-md px-3 py-2 hover:bg-gray-200 generate-password" data-targetfields="inputNewPassword1,inputNewPassword2" title="Generate Password">
                                    <i class="fas fa-key"></i>
                                </button>
                            </div>
                            <!-- Password Strength Meter -->
                            {include file="$template/includes/pwstrength.tpl"}
                            <small id="newPasswordHelp" class="text-gray-500">Your password must be 8-20 characters long, contain letters and numbers.</small>
                        </div>

                        <!-- Confirm New Password Field -->
                        <div class="mb-4">
                            <label for="inputNewPassword2" class="block text-gray-700 font-medium mb-2">
                                <i class="fas fa-lock-open mr-2 text-blue-500"></i> Confirm New Password
                            </label>
                            <input type="password" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" id="inputNewPassword2" name="confirmpw" autocomplete="off" placeholder="Confirm new password" required />
                            <div id="confirmPasswordMessage" class="text-red-600 mt-1">
                                <!-- Error message for password mismatch -->
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex justify-end mt-6 space-x-2">
                        <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600 flex items-center">
                            <i class="fas fa-save mr-2"></i> Save Changes
                        </button>
                        <button type="button" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300 flex items-center" @click="open = false">
                            <i class="fas fa-times mr-2"></i> Close
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Rename Device Modal -->
    <div x-data="{ open: $store.modals.renameDevice }" x-show="open" @keydown.escape.window="open = false" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-labelledby="renameDeviceModalLabel" aria-modal="true" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 text-center sm:block sm:p-0">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="open = false"></div>

            <!-- Modal Panel -->
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-blue-600 text-white px-4 py-3 flex justify-between items-center">
                    <h4 class="text-lg font-medium" id="renameDeviceModalLabel">Rename Device</h4>
                    <button type="button" class="text-white hover:text-gray-200" @click="open = false">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="px-6 py-4">
                    <form method="post" action="#">
                        <input type="hidden" id="serviceId" name="serviceId" value="" />
                        <input type="hidden" id="deviceId" name="deviceId" value="" />
                        
                        <div class="mb-4">
                            <label for="devicename" class="block text-gray-700 font-medium mb-2">Enter a new name for the selected device:</label>
                            <input type="text" id="devicename" name="devicename" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                        </div>
                    </form>
                </div>
                <div class="flex justify-end mt-6 space-x-2 px-6 py-3 bg-gray-50">
                    <button type="button" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 flex items-center" id="devicerenamesubmit">
                        <i class="fas fa-save mr-2"></i> Save Changes
                    </button>
                    <button type="button" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300 flex items-center" @click="open = false">
                        <i class="fas fa-times mr-2"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Email Modal -->
    <div x-data="{ open: $store.modals.addEmail }" x-show="open" @keydown.escape.window="open = false" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="addEmailModalLabel" role="dialog" aria-modal="true" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 text-center sm:block sm:p-0">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="open = false"></div>

            <!-- Modal Panel -->
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="post" action="#" class="p-6">
                    <!-- Modal Header -->
                    <div class="flex justify-between items-center mb-4">
                        <h5 class="text-lg font-medium text-gray-900" id="addEmailModalLabel">Add Email</h5>
                        <button type="button" class="text-gray-500 hover:text-gray-700" @click="open = false">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div>
                        <!-- Email Address Field -->
                        <div class="mb-4">
                            <label for="emailAddress" class="block text-gray-700 font-medium mb-2">Email Address</label>
                            <input type="email" id="emailAddress" name="emailAddress" placeholder="Enter email" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Additional Fields (Add more as needed) -->
                        <!-- Example: -->
                        <!--
                        <div class="mb-4">
                            <label for="emailType" class="block text-gray-700 font-medium mb-2">Email Type</label>
                            <select id="emailType" name="emailType"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="personal">Personal</option>
                                <option value="work">Work</option>
                            </select>
                        </div>
                        -->
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex justify-end mt-6 space-x-2">
                        <button type="button" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300" @click="open = false">
                            <i class="fas fa-times mr-2"></i> Close
                        </button>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 flex items-center">
                            <i class="fas fa-plus mr-2"></i> Add Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript to Load User Details and Handle Modals -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
           

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
            })
            .catch(error => {
                console.error('Error loading user details:', error);
                document.getElementById('detailsLoader').classList.add('hidden');
                document.getElementById('detailsError').classList.remove('hidden');
            });

            // Initialize Alpine.js stores for modals
            Alpine.store('modals', {
                addEmail: false,
                resetPassword: false,
                renameDevice: false
            });

            // Handle Rename Device Modal Opening
            document.addEventListener('click', function(e) {
                if (e.target.closest('.rename_device')) {
                    e.preventDefault();
                    const button = e.target.closest('.rename_device');
                    const serviceId = button.getAttribute('data-serviceid');
                    const deviceId = button.getAttribute('data-deviceid');
                    const deviceName = button.getAttribute('data-devicename');

                    document.getElementById('serviceId').value = serviceId;
                    document.getElementById('deviceId').value = deviceId;
                    document.getElementById('devicename').value = deviceName;
                    document.getElementById('renameDeviceModalLabel').textContent = `Rename Device: ${deviceName}`;

                    // Open the modal
                    Alpine.store('modals').renameDevice = true;
                }
            });

            // Handle Add Email Modal Opening
            document.getElementById('addEmailButton').addEventListener('click', function() {
                Alpine.store('modals').addEmail = true;
            });

            // Handle Save Profile Button
            document.getElementById('saveProfile').addEventListener('click', function() {
                // Implement save profile logic here
                alert('Profile changes saved!');
            });

            // Handle Save Devices Button
            document.getElementById('saveDevices').addEventListener('click', function() {
                // Implement save devices logic here
                alert('Device changes saved!');
            });

            // Handle Revoke Device
            document.addEventListener('click', function(e) {
                if (e.target.closest('.revoke_device')) {
                    e.preventDefault();
                    const button = e.target.closest('.revoke_device');
                    const deviceId = button.getAttribute('data-deviceid');

                    if (confirm('Are you sure you want to revoke this device?')) {
                        // Implement revoke device logic here (e.g., AJAX request)
                        alert(`Device ID ${deviceId} revoked.`);
                        // Optionally, remove the device row from the table
                        button.closest('tr').remove();
                    }
                }
            });
        });
    </script>
</body>
</html>
