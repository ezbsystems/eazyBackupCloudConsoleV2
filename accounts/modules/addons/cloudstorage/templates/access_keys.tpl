<div class="min-h-screen bg-slate-950 text-gray-300">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 pb-10 pt-6 relative pointer-events-auto">
        <!-- Cloud Storage Navigation -->
        <div class="mb-6">
            <nav class="inline-flex rounded-full bg-slate-900/80 p-1 text-xs font-medium text-slate-400" aria-label="Cloud Storage Navigation">
                <a href="index.php?m=cloudstorage&page=dashboard"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.page == 'dashboard'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Dashboard
                </a>
                <a href="index.php?m=cloudstorage&page=buckets"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.page == 'buckets'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Buckets
                </a>                
                <a href="index.php?m=cloudstorage&page=access_keys"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.page == 'access_keys'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Access Keys
                </a>
                <a href="index.php?m=cloudstorage&page=users"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.page == 'users'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Users
                </a>
                <a href="index.php?m=cloudstorage&page=billing"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.page == 'billing'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Billing
                </a>
                <a href="index.php?m=cloudstorage&page=history"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.page == 'history'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Historical Stats
                </a>
            </nav>
        </div>

        <!-- Glass Container -->
        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
                

            <!-- Service URL -->
            <div class="mb-8 border border-slate-800/80 bg-slate-900/70 rounded-md shadow-lg p-4 flex items-center justify-between">
                <div class="flex items-center">
                    <span class="text-lg font-semibold text-white mr-2">Service URL:</span>
                    <input type="text" id="serviceURL" value="s3.ca-central-1.eazybackup.com" class="w-full px-3 py-2 border border-gray-600 bg-[#11182759] text-gray-300 rounded focus:outline-none focus:ring-0 focus:border-sky-600 min-w-[300px]" readonly>
                </div>
                <button
                    class="bg-gray-700 hover:bg-gray-600 text-gray-300 px-3 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                    onclick="copyToClipboard('serviceURL', 'copyIconServiceURL')"
                    title="Copy Service URL"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                    </svg>
                    <span id="copyIconServiceURL" class="sr-only"></span>
                </button>
            </div>

            <!-- Access Keys Table -->
            <div class="border border-slate-800/80 bg-slate-900/70 rounded-lg shadow-lg p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-700">
                        <thead class="">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">Owner</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">Account ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">Access Key</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">Secret Key</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">Date Created</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800">
                            {if $accessKey !== null}
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-white">{$username}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-white">{$user->tenant_id}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap relative">
                                        <input type="text"
                                               value="{if !empty($accessKey->access_key_hint)}{$accessKey->access_key_hint}{else}Hidden{/if}"
                                               class="w-full rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-sky-500 font-mono"
                                               id="accessKey" readonly>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap relative">
                                        <input type="text"
                                               value="Hidden"
                                               class="w-full rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-sky-500 font-mono"
                                               id="secretKey" readonly>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-400" title="Creation Date">{$accessKey->created_at|date_format:"%d %b %Y %I:%M %p"}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex space-x-2">
                                            <button
                                                type="button"
                                                class="text-white hover:text-gray-300 px-3 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-400"
                                                onclick="openModal('updateKeysModal')"
                                            >
                                                Create new key
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            {/if}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div id="loading-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50">
            <div class="flex items-center">
                <div class="text-gray-300 text-lg">Loading...</div>
                <svg class="animate-spin h-8 w-8 text-gray-300 ml-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
            </div>
        </div>

        <!-- Alpine Toast (global) -->
        <div x-data="{
                visible:false,
                message:'',
                type:'info',
                timeout:null,
                show(msg,t='info'){
                    this.message=msg; this.type=t; this.visible=true;
                    if(this.timeout) clearTimeout(this.timeout);
                    this.timeout=setTimeout(()=>{ this.visible=false; }, t==='error'?7000:4000);
                }
            }"
             x-init="window.toast={
                success:(m)=>show(m,'success'),
                error:(m)=>show(m,'error'),
                info:(m)=>show(m,'info')
             }"
             class="fixed top-4 right-4 z-50">
            <div x-show="visible" x-transition
                 class="rounded-md px-4 py-3 text-white shadow-lg min-w-[260px]"
                 :class="{
                    'bg-green-600': type==='success',
                    'bg-red-600': type==='error',
                    'bg-blue-600': type==='info'
                 }">
                <div class="flex items-start justify-between">
                    <div class="pr-2" x-text="message"></div>
                    <button type="button" class="ml-2 text-white/80 hover:text-white" @click="visible=false" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>



        <!-- Create New Key Modal -->
        <div class="fixed inset-0 bg-black/75 flex items-center justify-center z-50 hidden" id="updateKeysModal">
            <div class="bg-gray-800 rounded-lg shadow-lg w-full max-w-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h5 class="text-xl font-semibold text-yellow-300">Create new access key</h5>
                    <button type="button" onclick="closeModal('updateKeysModal')" class="text-gray-400 hover:text-white focus:outline-none">
                        <!-- Close Icon SVG -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div>
                    <p class="mb-4">
                        Creating a new key revokes the current key and generates a new one. You will be shown the secret key only once.
                    </p>
                </div>
                <div class="flex justify-end space-x-2">
                    <button
                        type="button"
                        class="bg-gray-700 hover:bg-gray-600 text-gray-300 px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                        onclick="closeModal('updateKeysModal')"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                        id="confirmRollKeys"
                    >
                        Confirm
                    </button>
                </div>
            </div>
        </div>

        <!-- One-time new key modal -->
        <div class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden" id="newKeysModal">
            <div class="bg-gray-800 rounded-lg shadow-lg w-full max-w-xl p-6">
                <div class="flex justify-between items-center mb-4">
                    <h5 class="text-xl font-semibold text-green-300">Save your new key</h5>
                    <button type="button" onclick="closeModal('newKeysModal')" class="text-gray-400 hover:text-white focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="mb-4 rounded-md bg-yellow-900/20 border border-yellow-700/40 p-3 text-yellow-200 text-sm">
                    This is the <strong>only</strong> time you can view the secret key. Store it securely.
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Access key</label>
                        <input type="text" id="newAccessKey" class="w-full rounded-md bg-slate-900/70 border border-slate-700 px-3 py-2 text-sm text-slate-200 font-mono" readonly>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Secret key</label>
                        <input type="text" id="newSecretKey" class="w-full rounded-md bg-slate-900/70 border border-slate-700 px-3 py-2 text-sm text-slate-200 font-mono" readonly>
                    </div>
                </div>
                <div class="flex justify-end mt-6">
                    <button type="button" class="bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-md" onclick="closeModal('newKeysModal')">Done</button>
                </div>
            </div>
        </div>

        {* Password Slide-Over *}
        <div id="passwordSlideover" x-data="{ isOpen: false }" x-init="
            window.addEventListener('open-decrypt-slideover', () => { isOpen = true });
            window.addEventListener('close-decrypt-slideover', () => { isOpen = false });
        " x-show="isOpen" class="fixed inset-0 z-50" style="display:none;">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/75"
                 x-show="isOpen"
                 x-transition.opacity
                 onclick="closeDecryptSlideover()"></div>
            <!-- Panel -->
            <div class="absolute right-0 top-0 h-full w-full max-w-md bg-slate-950 border-l border-slate-800/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] overflow-y-auto"
                 x-show="isOpen"
                 x-transition:enter="transform transition ease-in-out duration-300"
                 x-transition:enter-start="translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transform transition ease-in-out duration-300"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="translate-x-full">
                <div class="flex items-center justify-between p-4 border-b border-slate-700">
                    <h3 class="text-lg font-semibold text-white">Verify Password</h3>
                    <button class="text-slate-300 hover:text-white" onclick="closeDecryptSlideover()">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="p-4">
                    <div id="passwordErrorMessage" class="bg-red-600 text-white px-4 py-2 rounded-md mb-4 hidden" role="alert"></div>
                    <div class="mb-4">
                        <input type="hidden" id="action" value="rollkeys">
                        <label for="password" class="mb-1 block text-sm font-medium text-gray-400">Enter your account password to continue</label>
                        <input
                            type="password"
                            class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                            id="password"
                            name="password"
                            required
                        >
                    </div>
                    <div class="flex justify-end space-x-2 mt-6">
                        <button type="button" class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-md" onclick="closeDecryptSlideover()">Cancel</button>
                        <button
                            type="button"
                            class="btn-primary"
                            id="submitPassword"
                        >
                            Submit
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Custom Scripts -->
{literal}
    <script>

        // Hide the loading overlay when the page has fully loaded
        window.addEventListener('load', function() {
            hideLoader();
        });

        // Modified copyToClipboard function to preserve the input's original type.
        function copyToClipboard(id, iconId) {
            var input = document.getElementById(id);
            var icon = document.getElementById(iconId);
            var originalType = input.type;
            // If it's a password field, reveal it temporarily.
            if(originalType === 'password'){
                input.type = 'text';
            }
            input.select();
            input.setSelectionRange(0, 99999);

            navigator.clipboard.writeText(input.value).then(function() {
                if (icon) {
                    icon.innerHTML = `
                        <!-- Clipboard Check Icon SVG -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    `;
                }
                // Restore the original input type.
                input.type = originalType;
                setTimeout(function() {
                    if (icon) {
                        icon.innerHTML = `
                            <!-- Clipboard Icon SVG -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h6a2 2 0 012 2v2" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8M12 12h.01" />
                            </svg>
                        `;
                    }
                }, 2000);
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
            });
        }

        // Toggle Modal Functions
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('hidden');
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.add('hidden');
        }

        // Slide-over open/close for password verification
        function openDecryptSlideover(){
            try { window.dispatchEvent(new CustomEvent('open-decrypt-slideover')); } catch(_) {}
        }
        function closeDecryptSlideover(){
            try { window.dispatchEvent(new CustomEvent('close-decrypt-slideover')); } catch(_) {}
        }

        // validate password
        jQuery('#submitPassword').click(function() {
            jQuery(this).prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
            const action = jQuery('#action').val();

            if (action == 'rollkeys') {
                jQuery.ajax({
                    url: 'modules/addons/cloudstorage/api/validatepassword.php',
                    method: 'POST',
                    data: {'password': jQuery('#password').val()},
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            closeDecryptSlideover()
                            rollKeys();
                        } else {
                            window.toast.error(response.message || 'Password verification failed.');
                        }
                        jQuery('#submitPassword').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                    },
                    error: function(xhr, status, error) {
                        window.toast.error(error || 'Password verification failed.');
                        jQuery('#submitPassword').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                    }
                });
            } else {
                window.toast.error('Action is not appropriate.');
            }
        });

        // Roll Keys
        jQuery('#confirmRollKeys').click(function() {
            closeModal('updateKeysModal');
            jQuery('#action').val('rollkeys')
            jQuery('#alertMessage').text('').addClass('hidden');
            jQuery('#passwordErrorMessage').text('').addClass('hidden');
            jQuery('#password').val('');
            openDecryptSlideover();
        });

        // roll keys api
        function rollKeys() {
            jQuery.ajax({
                url: 'modules/addons/cloudstorage/api/rollkey.php',
                method: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response.status == 'fail') {
                        window.toast.error(response.message || 'Failed to roll keys.');
                        return;
                    }
                    window.toast.success(response.message || 'Access keys rolled.');
                    // Update UI hint and show one-time secrets in a modal
                    try {
                        if (response.data && response.data.access_key_hint) {
                            jQuery('#accessKey').val(response.data.access_key_hint);
                        }
                        jQuery('#secretKey').val('Hidden');
                        if (response.data && response.data.access_key && response.data.secret_key) {
                            jQuery('#newAccessKey').val(response.data.access_key);
                            jQuery('#newSecretKey').val(response.data.secret_key);
                            openModal('newKeysModal');
                        }
                    } catch (e) {}
                },
                error: function(xhr, status, error) {
                    window.toast.error(error || 'Request failed.');
                }
            });
        }

        // Show Loader and Refresh Function
        function showLoaderAndRefresh() {
            const overlay = document.getElementById('loading-overlay');
            overlay.classList.remove('hidden');
            // Simulate a refresh action
            setTimeout(() => {
                overlay.classList.add('hidden');
                location.reload();
            }, 2000); // 2 seconds delay for demonstration
        }
    </script>
{/literal}
