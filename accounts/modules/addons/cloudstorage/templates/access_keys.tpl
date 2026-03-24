<div class="eb-page">
    <div class="eb-page-inner relative pointer-events-auto pb-10">
        <div class="eb-panel">
            <div class="eb-panel-nav">
                {include file="modules/addons/cloudstorage/templates/partials/core_nav.tpl" cloudstorageActivePage='access_keys'}
            </div>
                

            <div class="eb-page-header">
                <div>
                    <h1 class="eb-page-title">Access Keys</h1>
                    <p class="eb-page-description">Generate and rotate the S3 credentials used for Cloud Storage buckets and tools.</p>
                </div>
            </div>

            <!-- Service URL -->
            <div class="eb-subpanel mb-8">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="eb-section-title">Service URL</div>
                        <p class="mt-1 text-sm text-slate-400">Use this endpoint with your S3-compatible clients and automation.</p>
                    </div>
                    <div class="flex w-full flex-col gap-3 sm:flex-row lg:w-auto lg:min-w-[28rem]">
                        <input type="text" id="serviceURL" value="s3.ca-central-1.eazybackup.com" class="eb-input eb-type-mono min-w-0 flex-1" readonly>
                        <button
                            type="button"
                            class="eb-btn eb-btn-secondary eb-btn-icon"
                            onclick="copyToClipboard('serviceURL', 'copyIconServiceURL')"
                            title="Copy Service URL"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                            </svg>
                            <span id="copyIconServiceURL" class="sr-only"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Access Keys Table -->
            <div class="eb-table-shell p-0">
                <div class="overflow-x-auto">
                    <table class="eb-table">
                        <thead>
                            <tr>
                                <th scope="col">Owner</th>
                                <th scope="col">Account ID</th>
                                <th scope="col">Access Key</th>
                                <th scope="col">Date Created</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <span class="text-sm text-white">Root user</span>
                                </td>
                                <td>
                                    <span class="text-sm text-white font-mono">{if $user->tenant_id}{$user->tenant_id}{else}—{/if}</span>
                                </td>
                                <td class="relative">
                                    <input type="text"
                                           value="{if $HAS_PRIMARY_KEY && $accessKey->is_user_generated && !empty($accessKey->access_key_hint)}{$accessKey->access_key_hint}{/if}"
                                           placeholder="—"
                                           class="eb-input eb-type-mono w-full"
                                           id="accessKey" readonly>
                                </td>
                                <td>
                                    {if $HAS_PRIMARY_KEY && $accessKey->is_user_generated}
                                        <span class="text-sm text-gray-400" title="Creation Date">{$accessKey->created_at|date_format:"%d %b %Y %I:%M %p"}</span>
                                    {else}
                                        <span class="text-sm text-gray-500">—</span>
                                    {/if}
                                </td>
                                <td>
                                    <div class="flex space-x-2">
                                        <button
                                            type="button"
                                            class="eb-btn eb-btn-primary eb-btn-sm"
                                            onclick="openModal('updateKeysModal')"
                                        >
                                            {if $HAS_PRIMARY_KEY}Create new key{else}Create your first key{/if}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div id="loading-overlay" class="eb-loading-overlay">
            <div class="eb-loading-card">
                <div class="eb-loading-spinner"></div>
                <div class="text-sm text-slate-300">Loading access keys…</div>
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
             class="fixed top-4 right-4 z-[60]">
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
        <div class="hidden fixed inset-0 z-50 flex items-center justify-center px-4 eb-modal-backdrop" id="updateKeysModal">
            <div class="eb-modal eb-modal--confirm">
                <div class="eb-modal-header">
                    <div>
                        <h5 class="eb-modal-title">{if $HAS_PRIMARY_KEY}Create new access key{else}Create your first access key{/if}</h5>
                        <p class="eb-modal-subtitle">Key rotation revokes the current secret and issues a new one immediately.</p>
                    </div>
                    <button type="button" onclick="closeModal('updateKeysModal')" class="eb-modal-close" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="eb-modal-body">
                    <p class="text-sm text-slate-300">
                        {if $HAS_PRIMARY_KEY}
                            Creating a new key revokes the current key and generates a new one.
                        {else}
                            Create your access key to start using S3 tools.
                        {/if}
                        You will be shown the secret key only once.
                    </p>
                </div>
                <div class="eb-modal-footer">
                    <button
                        type="button"
                        class="eb-btn eb-btn-secondary"
                        onclick="closeModal('updateKeysModal')"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="eb-btn eb-btn-danger-solid"
                        id="confirmRollKeys"
                    >
                        Confirm
                    </button>
                </div>
            </div>
        </div>

        <!-- One-time new key modal -->
        <div class="hidden fixed inset-0 z-50 flex items-center justify-center px-4 eb-modal-backdrop" id="newKeysModal">
            <div class="eb-modal" style="max-width: 44rem;">
                <div class="eb-modal-header">
                    <div>
                        <h5 class="eb-modal-title">Save your new key</h5>
                        <p class="eb-modal-subtitle">This is the only time the secret key will be shown. Store it somewhere secure before closing.</p>
                    </div>
                    <button type="button" onclick="closeModal('newKeysModal')" class="eb-modal-close" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="eb-modal-body">
                    <div class="eb-alert eb-alert--warning mb-6">
                        <div>
                            <div class="eb-alert-title">One-time secret display</div>
                            <div class="text-sm">You will not be able to retrieve this secret key again after closing this window.</div>
                        </div>
                    </div>
                    <div class="space-y-5">
                        <div>
                            <label class="eb-field-label">Access key</label>
                            <div class="flex items-center gap-3">
                                <input type="text" id="newAccessKey" class="eb-input eb-type-mono w-full" readonly>
                                <button
                                    type="button"
                                    class="eb-btn eb-btn-secondary eb-btn-icon"
                                    onclick="copyToClipboard('newAccessKey', 'copyIconNewAccessKey')"
                                    aria-label="Copy access key"
                                    title="Copy access key"
                                >
                                    <span id="copyIconNewAccessKey" class="inline-flex">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                                        </svg>
                                    </span>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="eb-field-label">Secret key</label>
                            <div class="flex items-center gap-3">
                                <input type="text" id="newSecretKey" class="eb-input eb-type-mono w-full" readonly>
                                <button
                                    type="button"
                                    class="eb-btn eb-btn-secondary eb-btn-icon"
                                    onclick="copyToClipboard('newSecretKey', 'copyIconNewSecretKey')"
                                    aria-label="Copy secret key"
                                    title="Copy secret key"
                                >
                                    <span id="copyIconNewSecretKey" class="inline-flex">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                                        </svg>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="eb-modal-footer">
                    <button type="button" class="eb-btn eb-btn-primary" onclick="closeModal('newKeysModal')">Done</button>
                </div>
            </div>
        </div>

        {* Password Slide-Over *}
        <div id="passwordSlideover" x-data="{ isOpen: false }" x-init="
            window.addEventListener('open-decrypt-slideover', () => { isOpen = true });
            window.addEventListener('close-decrypt-slideover', () => { isOpen = false });
        " x-show="isOpen" class="fixed inset-0 z-50" style="display:none;">
            <!-- Backdrop -->
            <div class="absolute inset-0 eb-drawer-backdrop"
                 x-show="isOpen"
                 x-transition.opacity
                 onclick="closeDecryptSlideover()"></div>
            <!-- Panel -->
            <div class="absolute right-0 top-0 h-full eb-drawer eb-drawer--wide overflow-y-auto"
                 x-show="isOpen"
                 x-transition:enter="transform transition ease-in-out duration-300"
                 x-transition:enter-start="translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transform transition ease-in-out duration-300"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="translate-x-full">
                <div class="eb-drawer-header">
                    <div>
                        <div class="eb-drawer-title">Verify Password</div>
                        <p class="mt-1 text-sm text-slate-400">Confirm your identity to generate new access keys.</p>
                    </div>
                    <button class="eb-modal-close" onclick="closeDecryptSlideover()" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="eb-drawer-body">
                    <div class="mb-6">
                        <div id="passwordErrorMessage" class="rounded-lg border border-rose-500/60 bg-rose-500/10 px-4 py-3 text-sm text-rose-100 hidden" role="alert"></div>
                    </div>
                    <div class="space-y-5">
                        <div>
                            <input type="hidden" id="action" value="rollkeys">
                            <label for="password" class="eb-field-label">Account password</label>
                            <input
                                type="password"
                                class="eb-input w-full"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                required
                            >
                        </div>
                    </div>
                </div>
                <div class="eb-drawer-footer justify-end">
                    <button type="button" class="eb-btn eb-btn-secondary" onclick="closeDecryptSlideover()">Cancel</button>
                    <button
                        type="button"
                        class="eb-btn eb-btn-primary"
                        id="submitPassword"
                    >
                        Verify & Generate Keys
                    </button>
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

        // Modified copyToClipboard function to preserve the input's original type
        // and to restore the *original* icon markup after showing a temporary success state.
        function copyToClipboard(id, iconId) {
            var input = document.getElementById(id);
            var icon = iconId ? document.getElementById(iconId) : null;
            if (!input) return;

            // Cache the original icon once so we always restore the exact SVG the template rendered.
            if (icon && !icon.dataset.originalHtml) {
                icon.dataset.originalHtml = icon.innerHTML;
            }

            var originalType = input.type;
            // If it's a password field, reveal it temporarily.
            if (originalType === 'password') {
                input.type = 'text';
            }

            input.select();
            input.setSelectionRange(0, 99999);

            navigator.clipboard.writeText(input.value).then(function() {
                if (icon) {
                    // Success state icon (SVG, consistent sizing with the original icon).
                    icon.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 text-green-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    `;
                }

                // Restore the original input type.
                input.type = originalType;

                setTimeout(function() {
                    if (icon && icon.dataset.originalHtml) {
                        icon.innerHTML = icon.dataset.originalHtml;
                    }
                }, 2000);
            }).catch(function(err) {
                // Restore the original input type even on failure.
                input.type = originalType;
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
