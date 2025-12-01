<div class="min-h-screen bg-slate-950 text-gray-300">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 pb-8 min-h-screen flex items-center"> 

            
                
                
                <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] max-w-4xl mx-auto px-6 py-6">
        
                    <!-- Global Message Container (Always Present) -->
                    <div id="globalMessage" class="text-white px-4 py-2 rounded-md mb-6 hidden" role="alert"></div>
                    <!-- Alpine Toasts -->
                    <!-- ebLoader -->
                    <script src="{$WEB_ROOT}/modules/addons/eazybackup/templates/assets/js/ui.js"></script>
                    <script>
                    {literal}
                    // Fallback Alpine data for toasts when toastCenter() isn't globally available
                    if (typeof window.toastCenter !== 'function') {
                        window.toastCenter = function(){
                            return {
                                toasts: [],
                                init(){},
                                remove(){},
                            };
                        };
                    }
                    {/literal}
                    </script>
                    <div x-data="toastCenter()" x-init="init()" class="pointer-events-none fixed top-4 inset-x-0 z-[70] flex justify-center">
                        <template x-for="t in toasts" :key="t.id">
                            <div
                                x-show="t.show"
                                x-transition.opacity.duration.200ms
                                class="pointer-events-auto mb-2 rounded-md px-4 py-2 text-sm shadow-lg"
                                :class="t.type === 'success' ? 'bg-emerald-600 text-white' : (t.type === 'error' ? 'bg-rose-600 text-white' : 'bg-slate-700 text-white')"
                                @click="remove(t.id)"
                            >
                                <span x-text="t.message"></span>
                            </div>
                        </template>
                    </div>
                    <!-- Toast container for window.showToast fallback -->
                    <div id="toast-container" class="pointer-events-none fixed top-4 right-4 z-[71] space-y-2"></div>

                    <h1 class="text-2xl text-white font-semibold mb-6">Welcome to eazyBackup</h1>
                    <!-- Product selection -->
                    <div id="eb-product-select" class="mb-8">
                        <p class="text-sm text-white mb-3">
                        To set up your account, start by choosing the product you’d like to configure. 
                        You can add other products later.
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Cloud Backup -->
                            <button type="button"
                                    class="rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-3 text-left hover:border-emerald-400/60 hover:bg-slate-900"
                                    data-choice="backup"
                                    onclick="ebChooseProduct(this)">
                                <div class="text-slate-100 font-medium">Cloud Backup</div>
                                <div class="text-xs text-slate-400 mt-1">
                                    Includes the eazyBackup client software to back up computers and servers directly to our cloud.
                                </div>
                            </button>

                            <!-- Cloud Storage -->
                            <button type="button"
                                    class="rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-3 text-left hover:border-emerald-400/60 hover:bg-slate-900"
                                    data-choice="storage"
                                    onclick="ebChooseProduct(this)">
                                <div class="text-slate-100 font-medium">Cloud Storage</div>
                                <div class="text-xs text-slate-400 mt-1">
                                    S3-compatible object storage for customers who want to bring their own backup or archive software.
                                </div>
                            </button>

                            <!-- Microsoft 365 Backup -->
                            <button type="button"
                                    class="rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-3 text-left hover:border-emerald-400/60 hover:bg-slate-900"
                                    data-choice="ms365"
                                    onclick="ebChooseProduct(this)">
                                <div class="text-slate-100 font-medium">Microsoft 365 Backup</div>
                                <div class="text-xs text-slate-400 mt-1">
                                    Cloud-to-cloud backup for Exchange, OneDrive, SharePoint, and Teams, managed from the eazyBackup dashboard.
                                </div>
                            </button>

                            <!-- Cloud-to-Cloud Backup -->
                            <button type="button"
                                    class="rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-3 text-left hover:border-emerald-400/60 hover:bg-slate-900"
                                    data-choice="cloud2cloud"
                                    onclick="ebChooseProduct(this)">
                                <div class="text-slate-100 font-medium">Cloud-to-Cloud Backup</div>
                                <div class="text-xs text-slate-400 mt-1">
                                    Back up and replicate data from Google Drive, Dropbox, and S3-compatible storage (AWS, Wasabi, Backblaze, etc.) into your e3 buckets on a schedule.
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Set Password Slide-over -->
                    <div id="eb-setpw-overlay" class="fixed inset-0 z-[9999] hidden flex items-stretch justify-end">
                        <div class="absolute inset-0 bg-black/60" onclick="ebPwClose()"></div>
                        <div id="eb-setpw-panel" class="relative z-[10000] h-full w-full max-w-md rounded-l-2xl border border-slate-800 bg-slate-900/90 text-white shadow-2xl shadow-black/60 backdrop-blur-sm transform transition-transform duration-300 ease-out translate-x-full">
                            <div class="pointer-events-none absolute inset-x-0 -top-px h-px bg-gradient-to-r from-emerald-400/0 via-emerald-400/70 to-sky-400/0"></div>
                            <div class="px-6 py-6 sm:px-7 sm:py-7">
                                <div class="flex items-start justify-between">
                                    <h2 id="eb-setpw-title" class="text-lg font-semibold tracking-tight text-slate-50">Set your account password</h2>
                                    <button type="button" class="text-slate-400 hover:text-slate-200" onclick="ebPwClose()" aria-label="Close">&times;</button>
                                </div>
                                <p id="eb-username-hint" class="mt-1 text-xs text-slate-400 hidden"></p>
                                <div id="eb-pw-general-error" class="mt-4 hidden rounded-md border border-rose-500/60 bg-rose-500/10 px-3 py-2 text-xs text-rose-100"></div>
                                <form id="eb-setpw-form" class="mt-5 space-y-4 text-sm" onsubmit="return ebPwSubmit(event);">
                                    <input type="hidden" name="product_choice" id="eb-product-choice" value="">
                                    <div id="eb-username-row" class="space-y-1.5 hidden">
                                        <label for="eb-username" class="block text-xs font-medium text-slate-200">Username</label>
                                        <input id="eb-username" name="username" type="text" autocomplete="username"
                                               class="block w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2.5 text-sm text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                               placeholder="Choose a username (a-z, 0-9, ., _, -)" />
                                        <p class="text-[11px] text-slate-400">Allowed: letters, numbers, ., _, -; min 6 characters.</p>
                                        <p id="eb-err-username" class="hidden text-[11px] text-rose-400 mt-1"></p>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label for="eb-newpw" class="block text-xs font-medium text-slate-200">New password</label>
                                        <input id="eb-newpw" name="new_password" type="password" autocomplete="new-password"
                                               class="block w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2.5 text-sm text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                               placeholder="Choose a strong password" required />
                                        <p id="eb-err-newpw" class="hidden text-[11px] text-rose-400 mt-1"></p>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label for="eb-newpw2" class="block text-xs font-medium text-slate-200">Confirm password</label>
                                        <input id="eb-newpw2" name="new_password_confirm" type="password" autocomplete="new-password"
                                               class="block w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2.5 text-sm text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                               placeholder="Re-enter your password" required />
                                        <p id="eb-err-newpw2" class="hidden text-[11px] text-rose-400 mt-1"></p>
                                    </div>
                                    <div class="pt-3 flex items-center justify-between">
                                        <button id="eb-pw-submit" type="submit" class="inline-flex items-center justify-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold shadow-sm ring-1 ring-emerald-500/40 bg-gradient-to-r from-emerald-500 via-emerald-400 to-sky-400 text-slate-950">Save password and continue</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <script>
                    {literal}
                        function ebPwOpen(){
                            try {
                                var ov = document.getElementById('eb-setpw-overlay');
                                var p  = document.getElementById('eb-setpw-panel');
                                if (ov) { ov.classList.remove('hidden'); }
                                if (p) {
                                    // allow layout to apply before transitioning
                                    requestAnimationFrame(function(){
                                        p.classList.remove('translate-x-full');
                                        p.classList.add('translate-x-0');
                                    });
                                }
                            } catch(_) {}
                        }
                        function ebPwClose(){
                            try {
                                var ov = document.getElementById('eb-setpw-overlay');
                                var p  = document.getElementById('eb-setpw-panel');
                                if (p) {
                                    p.classList.add('translate-x-full');
                                    p.classList.remove('translate-x-0');
                                }
                                // wait for transition to finish before hiding overlay
                                setTimeout(function(){
                                    if (ov) { ov.classList.add('hidden'); }
                                }, 300);
                            } catch(_) {}
                        }
                        function ebSetError(id,msg){ var el=document.getElementById(id); if(!el) return; if(msg){ el.textContent=msg; el.classList.remove('hidden'); } else { el.textContent=''; el.classList.add('hidden'); } }
                        function ebDisableSubmit(dis){ try{ var b=document.getElementById('eb-pw-submit'); if(b){ b.disabled=!!dis; if(dis){ b.classList.add('opacity-50','cursor-not-allowed'); } else { b.classList.remove('opacity-50','cursor-not-allowed'); } } }catch(_){} }
                        async function ebChooseProduct(btn){
                            var choice = (btn && btn.getAttribute('data-choice')) || '';
                            if(!choice) return;
                            try{
                                const resp = await fetch('modules/addons/cloudstorage/api/selectproduct.php', {
                                    method:'POST',
                                    headers:{'Content-Type':'application/x-www-form-urlencoded'},
                                    body: new URLSearchParams({ product_choice: choice })
                                });
                                const data = await resp.json();
                                if((data && data.status)==='success'){
                                    document.getElementById('eb-product-choice').value = data.product_choice || choice;
                                    // Show username input only for backup/ms365
                                    var showUser = (data.product_choice==='backup' || data.product_choice==='ms365');
                                    var unameRow = document.getElementById('eb-username-row');
                                    var hint = document.getElementById('eb-username-hint');
                                    if (unameRow) { if (showUser) unameRow.classList.remove('hidden'); else unameRow.classList.add('hidden'); }
                                    if (hint) { hint.textContent=''; hint.classList.add('hidden'); }
                                    var unameInput = document.getElementById('eb-username'); if (unameInput) unameInput.value='';
                                    document.getElementById('eb-setpw-title').textContent = showUser ? 'Set your eazyBackup username and password' : 'Set your account password';
                                    ebPwOpen();
                                }else{
                                    window.alert('Unable to save selection. Please try again.');
                                }
                            }catch(e){
                                window.alert('Unable to save selection. Please try again.');
                            }
                        }
                        async function ebPwSubmit(ev){
                            ev.preventDefault();
                            ebSetError('eb-pw-general-error',''); ebSetError('eb-err-username',''); ebSetError('eb-err-newpw',''); ebSetError('eb-err-newpw2','');
                            ebDisableSubmit(true);
                            try{
                                const choice = document.getElementById('eb-product-choice').value || '';
                                const uname  = document.getElementById('eb-username').value || '';
                                const p1     = document.getElementById('eb-newpw').value || '';
                                const p2     = document.getElementById('eb-newpw2').value || '';
                                // Front-end validation to match backend (comet)
                                const needsUser = (choice==='backup' || choice==='ms365');
                                if (needsUser) {
                                    const reUser = /^[A-Za-z0-9_.-]{6,}$/;
                                    if (!reUser.test(uname)) {
                                        const msg = 'Backup username must be at least 6 characters and may contain only a-z, A-Z, 0-9, _, ., -';
                                        ebSetError('eb-err-username', msg);
                                        try { if (window.showToast) window.showToast(msg, 'error'); } catch(_){}
                                        ebDisableSubmit(false);
                                        return false;
                                    }
                                }
                                if ((p1||'').length < 8) {
                                    const msg = 'Password must be at least 8 characters long.';
                                    ebSetError('eb-err-newpw', msg);
                                    try { if (window.showToast) window.showToast(msg, 'error'); } catch(_){}
                                    ebDisableSubmit(false);
                                    return false;
                                }
                                if (p1 !== p2) {
                                    const msg = 'Passwords do not match.';
                                    ebSetError('eb-err-newpw2', msg);
                                    try { if (window.showToast) window.showToast(msg, 'error'); } catch(_){}
                                    ebDisableSubmit(false);
                                    return false;
                                }
                                // Show loader while submitting/provisioning
                                try { if (window.ebShowLoader) window.ebShowLoader(document.body, 'Creating your account…'); } catch(_){}
                                const resp = await fetch('modules/addons/cloudstorage/api/setpassword_and_provision.php', {
                                    method:'POST',
                                    headers:{'Content-Type':'application/x-www-form-urlencoded'},
                                    body: new URLSearchParams({ product_choice: choice, username: uname, new_password: p1, new_password_confirm: p2 })
                                });
                                const data = await resp.json();
                                if((data && data.status)==='success' && data.redirectUrl){
                                    ebPwClose(); window.location.href = data.redirectUrl;
                                    return false;
                                }
                                const errs = (data && data.errors) ? data.errors : {};
                                if(errs.username) ebSetError('eb-err-username', errs.username);
                                if(errs.general) ebSetError('eb-pw-general-error', errs.general);
                                if(errs.new_password) ebSetError('eb-err-newpw', errs.new_password);
                                if(errs.new_password_confirm) ebSetError('eb-err-newpw2', errs.new_password_confirm);
                                if(!errs.general && !errs.username && !errs.new_password && !errs.new_password_confirm){
                                    ebSetError('eb-pw-general-error', (data && data.message) ? String(data.message) : 'Failed to update password.');
                                }
                            }catch(e){
                                ebSetError('eb-pw-general-error', 'Request failed. Please try again.');
                            }finally{
                                try { if (window.ebHideLoader) window.ebHideLoader(document.body); } catch(_){}
                                ebDisableSubmit(false);
                            }
                            return false;
                        }
                    {/literal}
                    </script>
                    {* <div class="signup-content">
                        <div class="success mb-6 flex justify-center items-center text-white h-24 w-24 bg-[radial-gradient(#fe7800_0%,#fe5000_100%)] mx-auto rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-12">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                    
                        </div>                        
                    </div> *}
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
