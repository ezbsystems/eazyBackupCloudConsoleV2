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




<!-- Modal Reset Password -->
<div id="reset-password" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form class="using-password-strength" method="post" role="form">
                <!-- Modal Header -->
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="resetPasswordModalLabel">Password Reset for {$service.username}</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body">
                    <!-- Alert for Error Messages -->
                    <div id="passworderrorMessage" class="alert alert-danger d-none" role="alert">
                    <!-- Dynamic error message will be inserted here -->
                    </div>
                    
                    <input type="hidden" id="resetpasswordserviceId" name="id" value="" />
                    <input type="hidden" name="AuthType" value="Password" />
                    <input type="hidden" name="modulechangepassword" value="true" />

                    <!-- New Password Field -->
                    <div class="form-group">
                        <label for="inputNewPassword1"><i class="fas fa-lock mr-2 text-primary"></i>{$LANG.newpassword}</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="inputNewPassword1" name="newpw" autocomplete="off" placeholder="Enter new password" required />
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary generate-password" data-targetfields="inputNewPassword1,inputNewPassword2" title="Generate Password">
                                    <i class="fas fa-key"></i>
                                </button>
                            </div>
                        </div>
                        <!-- Password Strength Meter -->
                        {include file="$template/includes/pwstrength.tpl"}
                        <small id="newPasswordHelp" class="form-text text-muted">Your password must be 8-20 characters long, contain letters and numbers.</small>
                    </div>

                    <!-- Confirm New Password Field -->
                    <div class="form-group">
                        <label for="inputNewPassword2"><i class="fas fa-lock-open mr-2 text-primary"></i>{$LANG.confirmnewpassword}</label>
                        <input type="password" class="form-control" id="inputNewPassword2" name="confirmpw" autocomplete="off" placeholder="Confirm new password" required />
                        <div id="inputNewPassword2Msg" class="invalid-feedback">
                            <!-- Error message for password mismatch -->
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save mr-2"></i>{$LANG.clientareasavechanges}
                    </button>
                    <button type="button" class="btn btn-primary" data-dismiss="modal">
                        <i class="fas fa-times mr-2"></i>Close
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Additional modals can be included here -->



<!-- Modal Rename Device-->
<div id="rename_device" class="modal fade" role="dialog" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content panel panel-primary">
            <div class="modal-header panel-heading">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Rename Device</h4>
            </div>
            <div class="modal-body panel-body">
                {* <div class="container"> *}
                <form class="" method="post" action="#">
                    <input type="hidden" id="serviceId" name="serviceId " />
                    <input type="hidden" id="deviceId" name="deviceId " />
                    <label for="inputPassword5" class="form-label">Enter a new name for the selected device:</label>
                    <input type="text" id="devicename" name="devicename" class="form-control">
                    {* <div class="form-group text-center">
                        <button type="button" id="devicerenamesubmit" class="btn btn-success save_changes_btn"> Save Changes</button>
                    </div> *}
                </form>
                {* </div> *}
            </div>
            <div class="modal-footer panel-footer">
                <button type="button" class="btn btn-primary" id="devicerenamesubmit">Save Changes</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
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
