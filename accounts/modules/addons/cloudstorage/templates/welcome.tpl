<div class="min-h-screen bg-slate-950 text-gray-300">    
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 pb-8 min-h-screen flex items-center">                
                
                <div class="relative z-[10000] rounded-3xl border border-slate-700/80 bg-slate-900/90 shadow-[0_18px_60px_rgba(0,0,0,0.6)] max-w-4xl mx-auto px-8 py-8">
        
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
                    <style>
                        #eb-card-overlay #stripeElements .StripeElement {
                            display: block;
                            width: 100%;
                            padding: 0.625rem 0.75rem;
                            border: 1px solid #334155;
                            color: #e5e7eb;
                            background-color: rgba(15, 23, 42, 0.8);
                            border-radius: 0.5rem;
                            outline: none;
                        }
                        #eb-card-overlay #stripeElements .form-group,
                        #eb-card-overlay #stripeElements .row {
                            margin-bottom: 12px;
                        }
                        #eb-card-overlay #stripeElements label {
                            display: block;
                            margin-bottom: 6px;
                        }
                        #eb-card-overlay #stripeElements .StripeElement--focus {
                            border-color: #22c55e;
                            box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.6);
                        }
                        #eb-card-overlay #stripeElements .StripeElement--invalid {
                            border-color: #fbbf24;
                        }
                    </style>

                    <h1 class="text-3xl text-white font-semibold mb-2">Welcome to eazyBackup</h1>
                    <!-- Product selection -->
                    <div id="eb-product-select" class="mb-8">
                        <p class="text-base text-slate-300 mb-6">
                        Choose the product that best fits your needs. 
                        You can add other products later.
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Cloud Backup -->
                            <button type="button"
                                    class="group relative rounded-xl border-2 border-slate-600 bg-slate-800/70 px-5 py-5 text-left transition-all duration-200 hover:border-emerald-400 hover:bg-slate-800 hover:shadow-lg hover:shadow-emerald-500/10 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-slate-900"
                                    data-choice="backup"
                                    onclick="ebChooseProduct(this)">
                                <!-- Most Popular Badge -->
                                <div class="absolute -top-3 left-4 px-3 py-0.5 bg-gradient-to-r from-emerald-500 to-sky-500 text-white text-xs font-semibold rounded-full shadow-md">
                                    Most Popular
                                </div>
                                <div class="flex items-start gap-4">
                                    <!-- Icon -->
                                    <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-emerald-500/20 flex items-center justify-center text-emerald-400 group-hover:bg-emerald-500/30 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-lg font-semibold text-white group-hover:text-emerald-300 transition-colors">Cloud Backup</div>
                                        <div class="text-sm text-slate-300 mt-2 leading-relaxed">
                                            Back up Windows, macOS, and servers directly to eazyBackup's Canadian cloud with encryption, retention policies, and fast restores.
                                        </div>
                                        <div class="text-sm text-emerald-400/90 mt-3 font-medium">
                                            <span class="text-slate-400">Best for:</span> End users, MSPs, and IT teams using the eazyBackup app to protect PCs and servers.
                                        </div>
                                    </div>
                                </div>
                            </button>

                            <!-- Cloud Storage -->
                            <button type="button"
                                    class="group relative rounded-xl border-2 border-slate-600 bg-slate-800/70 px-5 py-5 text-left transition-all duration-200 hover:border-sky-400 hover:bg-slate-800 hover:shadow-lg hover:shadow-sky-500/10 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900"
                                    data-choice="storage"
                                    onclick="ebChooseProduct(this)">
                                <div class="flex items-start gap-4">
                                    <!-- Icon -->
                                    <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-sky-500/20 flex items-center justify-center text-sky-400 group-hover:bg-sky-500/30 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-lg font-semibold text-white group-hover:text-sky-300 transition-colors">Cloud Storage</div>
                                        <div class="text-sm text-slate-300 mt-2 leading-relaxed">
                                            S3-compatible object storage (e3) for backups, archives, and application data stored in Canada.
                                        </div>
                                        <div class="text-sm text-sky-400/90 mt-3 font-medium">
                                            <span class="text-slate-400">Best for:</span> Teams who already have their own backup software or need a secure storage target.
                                        </div>
                                    </div>
                                </div>
                            </button>

                            <!-- Microsoft 365 Backup -->
                            <button type="button"
                                    class="group relative rounded-xl border-2 border-slate-600 bg-slate-800/70 px-5 py-5 text-left transition-all duration-200 hover:border-violet-400 hover:bg-slate-800 hover:shadow-lg hover:shadow-violet-500/10 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 focus:ring-offset-slate-900"
                                    data-choice="ms365"
                                    onclick="ebChooseProduct(this)">
                                <div class="flex items-start gap-4">
                                    <!-- Icon -->
                                    <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-violet-500/20 flex items-center justify-center text-violet-400 group-hover:bg-violet-500/30 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z" />
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-lg font-semibold text-white group-hover:text-violet-300 transition-colors">Microsoft 365 Backup</div>
                                        <div class="text-sm text-slate-300 mt-2 leading-relaxed">
                                            Protect Exchange, OneDrive, SharePoint, and Teams with automated backups, retention, and point-in-time restores—managed from the eazyBackup dashboard.
                                        </div>
                                        <div class="text-sm text-violet-400/90 mt-3 font-medium">
                                            <span class="text-slate-400">Best for:</span> Organisations that need reliable M365 recovery beyond Microsoft's built-in retention.
                                        </div>
                                    </div>
                                </div>
                            </button>

                            <!-- Cloud-to-Cloud Backup -->
                            <button type="button"
                                    class="group relative rounded-xl border-2 border-slate-600 bg-slate-800/70 px-5 py-5 text-left transition-all duration-200 hover:border-amber-400 hover:bg-slate-800 hover:shadow-lg hover:shadow-amber-500/10 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-slate-900"
                                    data-choice="cloud2cloud"
                                    onclick="ebChooseProduct(this)">
                                <div class="flex items-start gap-4">
                                    <!-- Icon -->
                                    <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-amber-500/20 flex items-center justify-center text-amber-400 group-hover:bg-amber-500/30 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-lg font-semibold text-white group-hover:text-amber-300 transition-colors">Cloud-to-Cloud Backup</div>
                                        <div class="text-sm text-slate-300 mt-2 leading-relaxed">
                                            Back up and replicate data from Google Drive, Dropbox, and S3 providers into your Canadian e3 buckets on a schedule.
                                        </div>
                                        <div class="text-sm text-amber-400/90 mt-3 font-medium">
                                            <span class="text-slate-400">Best for:</span> Organisations consolidating cloud data into one secure backup vault.
                                        </div>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Set Password Slide-over -->
                    <div id="eb-setpw-overlay" class="fixed inset-0 z-[9999] hidden flex items-stretch justify-end">
                        <div class="absolute inset-0 bg-black/60" onclick="ebPwClose()"></div>
                        <div id="eb-setpw-panel" class="relative z-[10000] h-full w-full max-w-xl rounded-l-2xl border border-slate-700 bg-slate-900/95 text-white shadow-2xl shadow-black/60 backdrop-blur-sm transform transition-transform duration-300 ease-out translate-x-full overflow-y-auto">
                            <div class="pointer-events-none absolute inset-x-0 -top-px h-px bg-gradient-to-r from-[#FE5000]/0 via-[#FE5000]/80 to-amber-400/0"></div>
                            <div class="px-8 py-8 sm:px-10 sm:py-10">
                                <div class="flex items-start justify-between">
                                    <h2 id="eb-setpw-title" class="text-xl font-bold tracking-tight text-white">Set your account password</h2>
                                    <button type="button" class="text-slate-400 hover:text-white text-2xl leading-none" onclick="ebPwClose()" aria-label="Close">&times;</button>
                                </div>
                                <p id="eb-username-hint" class="mt-2 text-sm text-slate-400 hidden"></p>
                                <div id="eb-pw-general-error" class="mt-4 hidden rounded-lg border border-rose-500/60 bg-rose-500/10 px-4 py-3 text-sm text-rose-100"></div>
                                <form id="eb-setpw-form" class="mt-6 space-y-5 text-sm" onsubmit="return ebPwSubmit(event);">
                                    <input type="hidden" name="product_choice" id="eb-product-choice" value="">
                                    <div id="eb-username-row" class="space-y-2 hidden">
                                        <label for="eb-username" class="block text-sm font-medium text-slate-200">Username</label>
                                        <input id="eb-username" name="username" type="text" autocomplete="username"
                                               class="block w-full rounded-lg border border-slate-600 bg-slate-800/60 px-4 py-3 text-base text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-[#FE5000] focus:border-[#FE5000] transition-all duration-200"
                                               placeholder="Choose a username (a-z, 0-9, ., _, -)" />
                                        <p class="text-xs text-slate-400">Allowed: letters, numbers, ., _, -; min 8 characters.</p>
                                        <p id="eb-err-username" class="hidden text-xs text-rose-400 mt-1"></p>
                                    </div>
                                    <div class="space-y-2">
                                        <label for="eb-newpw" class="block text-sm font-medium text-slate-200">New password</label>
                                        <input id="eb-newpw" name="new_password" type="password" autocomplete="new-password"
                                               class="block w-full rounded-lg border border-slate-600 bg-slate-800/60 px-4 py-3 text-base text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-[#FE5000] focus:border-[#FE5000] transition-all duration-200"
                                               placeholder="Choose a strong password" required />
                                        <p id="eb-err-newpw" class="hidden text-xs text-rose-400 mt-1"></p>
                                    </div>
                                    <div class="space-y-2">
                                        <label for="eb-newpw2" class="block text-sm font-medium text-slate-200">Confirm password</label>
                                        <input id="eb-newpw2" name="new_password_confirm" type="password" autocomplete="new-password"
                                               class="block w-full rounded-lg border border-slate-600 bg-slate-800/60 px-4 py-3 text-base text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-[#FE5000] focus:border-[#FE5000] transition-all duration-200"
                                               placeholder="Re-enter your password" required />
                                        <p id="eb-err-newpw2" class="hidden text-xs text-rose-400 mt-1"></p>
                                    </div>
                                    <div class="pt-4 flex items-center justify-between">
                                        <button id="eb-pw-submit" type="submit" class="inline-flex items-center justify-center gap-2 rounded-full px-6 py-3 text-base font-semibold shadow-lg shadow-[#FE5000]/20 bg-[#FE5000] hover:bg-[#e54700] text-white transition-all duration-200 hover:scale-[1.02]">Save password and continue</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- Storage Plan Selection Slide-over (for Cloud Storage only) -->
                    <div id="eb-storage-plan-overlay" class="fixed inset-0 z-[9999] hidden flex items-stretch justify-end">
                        <div class="absolute inset-0 bg-black/60" onclick="ebStoragePlanClose()"></div>
                        <div id="eb-storage-plan-panel" class="relative z-[10000] h-full w-full max-w-xl rounded-l-2xl border border-slate-700 bg-slate-900/95 text-white shadow-2xl shadow-black/60 backdrop-blur-sm transform transition-transform duration-300 ease-out translate-x-full overflow-y-auto">
                            <!-- Orange accent line -->
                            <div class="pointer-events-none absolute inset-x-0 -top-px h-px bg-gradient-to-r from-[#FE5000]/0 via-[#FE5000]/80 to-amber-400/0"></div>
                            <div class="px-8 py-8 sm:px-10 sm:py-10">
                                <div class="flex items-start justify-between mb-6">
                                    <div>
                                        <h2 class="text-2xl font-bold tracking-tight text-white">Choose Your Plan</h2>
                                        <p class="mt-2 text-sm text-slate-300">Select how you'd like to start with e3 Cloud Storage.</p>
                                    </div>
                                    <button type="button" class="text-slate-400 hover:text-white text-2xl leading-none" onclick="ebStoragePlanClose()" aria-label="Close">&times;</button>
                                </div>

                                <input type="hidden" id="eb-storage-tier" value="">

                                <div class="space-y-5">
                                    <!-- Choice 1: Free Trial -->
                                    <button type="button" 
                                            onclick="ebSelectStorageTier('trial_limited')"
                                            class="group w-full rounded-xl border-2 border-slate-600 bg-slate-800/70 p-6 text-left transition-all duration-200 hover:border-[#FE5000]/70 hover:shadow-[0_0_20px_rgba(254,80,0,0.15)] focus:outline-none focus:ring-2 focus:ring-[#FE5000] focus:ring-offset-2 focus:ring-offset-slate-900">
                                        <div class="flex items-start gap-4">
                                            <!-- Icon -->
                                            <div class="flex-shrink-0 w-14 h-14 rounded-xl bg-slate-700/50 flex items-center justify-center text-slate-300 group-hover:text-[#FE5000] transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </div>
                                            <div class="flex-1">
                                                <div class="text-xl font-semibold text-white group-hover:text-[#FE5000] transition-colors">Free Trial</div>
                                                <p class="text-sm text-slate-400 mt-1">Try Cloud Storage risk-free</p>
                                                <ul class="mt-4 space-y-2">
                                                    <li class="flex items-center gap-2 text-sm text-slate-300">
                                                        <svg class="w-5 h-5 text-emerald-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        30 days free
                                                    </li>
                                                    <li class="flex items-center gap-2 text-sm text-slate-300">
                                                        <svg class="w-5 h-5 text-emerald-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        1 TiB storage limit
                                                    </li>
                                                    <li class="flex items-center gap-2 text-sm text-slate-300">
                                                        <svg class="w-5 h-5 text-emerald-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        No credit card required
                                                    </li>
                                                </ul>
                                                <div class="mt-5">
                                                    <span class="inline-flex items-center gap-2 text-sm font-semibold text-[#FE5000] group-hover:underline">
                                                        Start Free Trial
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                                        </svg>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </button>

                                    <!-- Choice 2: Ready to Purchase -->
                                    <button type="button" 
                                            onclick="ebSelectStorageTier('trial_unlimited')"
                                            class="group relative w-full rounded-xl border-2 border-[#FE5000]/50 bg-slate-800/70 p-6 text-left transition-all duration-200 hover:border-[#FE5000] hover:shadow-[0_0_25px_rgba(254,80,0,0.2)] focus:outline-none focus:ring-2 focus:ring-[#FE5000] focus:ring-offset-2 focus:ring-offset-slate-900">
                                        <!-- Recommended Badge -->
                                        <div class="absolute -top-3 right-6 px-3 py-1 bg-[#FE5000] text-white text-xs font-semibold rounded-full shadow-md">
                                            Recommended
                                        </div>
                                        <div class="flex items-start gap-4">
                                            <!-- Icon -->
                                            <div class="flex-shrink-0 w-14 h-14 rounded-xl bg-[#FE5000]/15 flex items-center justify-center text-[#FE5000]">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" />
                                                </svg>
                                            </div>
                                            <div class="flex-1">
                                                <div class="text-xl font-semibold text-white group-hover:text-[#FE5000] transition-colors">Ready to Purchase?</div>
                                                <p class="text-sm text-slate-400 mt-1">Full access with no limits</p>
                                                <ul class="mt-4 space-y-2">
                                                    <li class="flex items-center gap-2 text-sm text-slate-300">
                                                        <svg class="w-5 h-5 text-emerald-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        30 days free trial
                                                    </li>
                                                    <li class="flex items-center gap-2 text-sm text-slate-300">
                                                        <svg class="w-5 h-5 text-emerald-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        <strong class="text-white">Unlimited</strong> storage
                                                    </li>
                                                    <li class="flex items-center gap-2 text-sm text-slate-300">
                                                        <svg class="w-5 h-5 text-emerald-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        Pay only for what you use after trial
                                                    </li>
                                                </ul>
                                                <div class="mt-5">
                                                    <span class="inline-flex items-center gap-2 text-sm font-semibold text-[#FE5000] group-hover:underline">
                                                        Add Payment Method
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                                        </svg>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </button>
                                </div>

                                <p class="mt-6 text-center text-xs text-slate-500">
                                    You can upgrade or add a payment method at any time from your dashboard.
                                </p>
                            </div>
                        </div>
                    </div>
                    <!-- Add Card Slide-over -->
                    <div id="eb-card-overlay" class="fixed inset-0 z-[9998] hidden flex items-stretch justify-end">
                        <div class="absolute inset-0 bg-black/60" onclick="ebCardClose()"></div>
                        <div id="eb-card-panel" class="relative z-[10000] h-full w-full max-w-xl rounded-l-2xl border border-slate-700 bg-slate-900/95 text-white shadow-2xl shadow-black/60 backdrop-blur-sm transform transition-transform duration-300 ease-out translate-x-full overflow-y-auto">
                            <div class="pointer-events-none absolute inset-x-0 -top-px h-px bg-gradient-to-r from-[#FE5000]/0 via-[#FE5000]/80 to-amber-400/0"></div>
                            <div class="px-8 py-8 sm:px-10 sm:py-10">
                                <div class="flex items-start justify-between">
                                    <h2 class="text-xl font-bold tracking-tight text-white">Add a payment method</h2>
                                    <button type="button" class="text-slate-400 hover:text-white text-2xl leading-none" onclick="ebCardClose()" aria-label="Close">&times;</button>
                                </div>
                                <p class="mt-2 text-sm text-slate-300">Securely add a card to start your e3 Cloud Storage trial with unlimited storage.</p>
                                <div id="eb-card-error" class="mt-4 hidden rounded-lg border border-rose-500/60 bg-rose-500/10 px-4 py-3 text-sm text-rose-100"></div>
                                <form id="eb-addcard-form" class="frm-credit-card-input mt-5 space-y-4 text-sm" method="post" action="{routePath('account-paymentmethods-add')}" target="eb-card-target" onsubmit="return ebCardSubmit(event);">
                                    <input type="hidden" name="token" value="{$token|default:$csrfToken}" />
                                    <input type="radio" name="type" value="token_stripe" data-tokenised="true" data-gateway="stripe" checked class="hidden" aria-hidden="true" />
                                    <input type="hidden" name="paymentmethod" id="eb-paymentmethod" value="stripe" />
                                    <input type="hidden" name="billingcontact" id="ebBillingContact" value="0" />
                                    <div class="gateway-errors assisted-cc-input-feedback hidden mb-3 rounded-md border border-rose-500/60 bg-rose-500/10 px-3 py-2 text-xs text-rose-100 text-center"></div>
                                    <div class="cc-details"></div>
                                    <input type="hidden" name="billing_name" id="ebBillingName" value="">
                                    <input type="hidden" name="billing_address_1" id="ebBillingAddress1" value="">
                                    <input type="hidden" name="billing_address_2" id="ebBillingAddress2" value="">
                                    <input type="hidden" name="billing_city" id="ebBillingCity" value="">
                                    <input type="hidden" name="billing_state" id="ebBillingState" value="">
                                    <input type="hidden" name="billing_postcode" id="ebBillingPostcode" value="">
                                    <input type="hidden" name="billing_country" id="ebBillingCountry" value="">
                                    <div class="pt-5 flex items-center justify-between">
                                        <button id="eb-card-submit" type="submit" class="inline-flex items-center justify-center gap-2 rounded-full px-6 py-3 text-base font-semibold shadow-lg shadow-[#FE5000]/20 bg-[#FE5000] hover:bg-[#e54700] text-white transition-all duration-200 hover:scale-[1.02]">Save card and continue</button>
                                        <button type="button" class="text-sm text-slate-400 hover:text-white transition-colors" onclick="ebCardClose()">Back</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <iframe id="eb-card-target" name="eb-card-target" class="hidden" title="Card submission"></iframe>
                    <script>
                    window.EB_WEB_ROOT = '{$WEB_ROOT}';
                    window.EB_CSRF_TOKEN = '{$token|default:$csrfToken}';
                    if (window.csrfToken && !window.EB_CSRF_TOKEN) {
                        window.EB_CSRF_TOKEN = window.csrfToken;
                    }
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
                        // Storage Plan Selection Drawer functions
                        function ebStoragePlanOpen(){
                            try {
                                var ov = document.getElementById('eb-storage-plan-overlay');
                                var p  = document.getElementById('eb-storage-plan-panel');
                                if (ov) { ov.classList.remove('hidden'); }
                                if (p) {
                                    requestAnimationFrame(function(){
                                        p.classList.remove('translate-x-full');
                                        p.classList.add('translate-x-0');
                                    });
                                }
                            } catch(_) {}
                        }
                        function ebStoragePlanClose(){
                            try {
                                var ov = document.getElementById('eb-storage-plan-overlay');
                                var p  = document.getElementById('eb-storage-plan-panel');
                                if (p) {
                                    p.classList.add('translate-x-full');
                                    p.classList.remove('translate-x-0');
                                }
                                setTimeout(function(){
                                    if (ov) { ov.classList.add('hidden'); }
                                }, 300);
                            } catch(_) {}
                        }
                        function ebSelectStorageTier(tier){
                            // Store the selected tier
                            document.getElementById('eb-storage-tier').value = tier;
                            ebStoragePlanClose();
                            
                            if (tier === 'trial_unlimited') {
                                // User wants to add payment method - show card drawer
                                setTimeout(function(){
                                    ebRequireCardForStorage();
                                }, 350);
                            } else {
                                // Free trial - skip CC, go directly to password
                                setTimeout(function(){
                                    ebPwOpen();
                                }, 350);
                            }
                        }
                        function ebSetError(id,msg){ var el=document.getElementById(id); if(!el) return; if(msg){ el.textContent=msg; el.classList.remove('hidden'); } else { el.textContent=''; el.classList.add('hidden'); } }
                        function ebDisableSubmit(dis){ try{ var b=document.getElementById('eb-pw-submit'); if(b){ b.disabled=!!dis; if(dis){ b.classList.add('opacity-50','cursor-not-allowed'); } else { b.classList.remove('opacity-50','cursor-not-allowed'); } } }catch(_){} }
                        var ebCardSubmitting = false;
                        var ebStripeInitDone = false;
                        var ebStripeKey = '';
                        var ebStripeInitInFlight = false;
                        function ebCardOpen(){
                            try {
                                var ov = document.getElementById('eb-card-overlay');
                                var p  = document.getElementById('eb-card-panel');
                                if (ov) { ov.classList.remove('hidden'); }
                                if (p) {
                                    requestAnimationFrame(function(){
                                        p.classList.remove('translate-x-full');
                                        p.classList.add('translate-x-0');
                                    });
                                }
                            } catch(_) {}
                        }
                        function ebCardClose(){
                            try {
                                var ov = document.getElementById('eb-card-overlay');
                                var p  = document.getElementById('eb-card-panel');
                                if (p) {
                                    p.classList.add('translate-x-full');
                                    p.classList.remove('translate-x-0');
                                }
                                setTimeout(function(){
                                    if (ov) { ov.classList.add('hidden'); }
                                }, 300);
                            } catch(_) {}
                        }
                        function ebSetCardError(msg){
                            var el = document.getElementById('eb-card-error');
                            if (!el) return;
                            if (msg) { el.textContent = msg; el.classList.remove('hidden'); }
                            else { el.textContent = ''; el.classList.add('hidden'); }
                        }
                        function ebSyncCsrfToken(){
                            try {
                                var t = window.csrfToken || window.EB_CSRF_TOKEN || '';
                                var input = document.querySelector('#eb-addcard-form input[name="token"]');
                                if (input && t) { input.value = t; }
                            } catch(_) {}
                        }
                        function ebJoinRoot(path){
                            var root = (window.EB_WEB_ROOT || '').replace(/\/+$/,'');
                            return root + path;
                        }
                        function ebApplyBillingDefaults(billing){
                            if (!billing) return;
                            try { document.getElementById('ebBillingName').value = billing.billing_name || ''; } catch(_) {}
                            try { document.getElementById('ebBillingAddress1').value = billing.billing_address_1 || ''; } catch(_) {}
                            try { document.getElementById('ebBillingAddress2').value = billing.billing_address_2 || ''; } catch(_) {}
                            try { document.getElementById('ebBillingCity').value = billing.billing_city || ''; } catch(_) {}
                            try { document.getElementById('ebBillingState').value = billing.billing_state || ''; } catch(_) {}
                            try { document.getElementById('ebBillingPostcode').value = billing.billing_postcode || ''; } catch(_) {}
                            try { document.getElementById('ebBillingCountry').value = billing.billing_country || ''; } catch(_) {}
                        }
                        function ebPatchStripeElement(el){
                            if (!el || typeof el.hasRegisteredListener === 'function') return;
                            var listeners = {};
                            var origAdd = el.addEventListener ? el.addEventListener.bind(el) : null;
                            var origRemove = el.removeEventListener ? el.removeEventListener.bind(el) : null;
                            el.addEventListener = function(type, handler){
                                listeners[type] = true;
                                if (origAdd) { return origAdd(type, handler); }
                            };
                            el.removeEventListener = function(type, handler){
                                listeners[type] = false;
                                if (origRemove) { return origRemove(type, handler); }
                            };
                            el.hasRegisteredListener = function(type){
                                return !!listeners[type];
                            };
                        }
                        function ebApplyStripeDarkTheme(){
                            var styledAnything = false;
                            if (window.card && typeof card.update === 'function') {
                                card.update({
                                    style: {
                                        base: {
                                            color: '#e5e7eb',
                                            iconColor: '#22c55e',
                                            '::placeholder': {
                                                color: '#6b7280'
                                            },
                                            fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                                            fontSize: '14px'
                                        },
                                        invalid: {
                                            color: '#fca5a5',
                                            iconColor: '#fca5a5'
                                        }
                                    }
                                });
                                styledAnything = true;
                            }
                            if (window.cardExpiryElements && typeof cardExpiryElements.update === 'function') {
                                cardExpiryElements.update({
                                    style: {
                                        base: {
                                            color: '#e5e7eb',
                                            '::placeholder': {
                                                color: '#6b7280'
                                            }
                                        },
                                        invalid: {
                                            color: '#fca5a5'
                                        }
                                    }
                                });
                                styledAnything = true;
                            }
                            if (window.cardCvcElements && typeof cardCvcElements.update === 'function') {
                                cardCvcElements.update({
                                    style: {
                                        base: {
                                            color: '#e5e7eb',
                                            '::placeholder': {
                                                color: '#6b7280'
                                            }
                                        },
                                        invalid: {
                                            color: '#fca5a5'
                                        }
                                    }
                                });
                                styledAnything = true;
                            }
                            return styledAnything;
                        }
                        function ebEnsureStripeCss(){
                            if (document.getElementById('eb-stripe-css')) return;
                            var link = document.createElement('link');
                            link.id = 'eb-stripe-css';
                            link.rel = 'stylesheet';
                            link.href = ebJoinRoot('/modules/gateways/stripe/stripe.css');
                            document.head.appendChild(link);
                        }
                        function ebLoadScriptOnce(src){
                            return new Promise(function(resolve, reject){
                                var existing = document.querySelector('script[src="' + src + '"]');
                                if (existing) {
                                    if (existing.getAttribute('data-loaded') === '1') { resolve(); return; }
                                    existing.addEventListener('load', function(){ existing.setAttribute('data-loaded','1'); resolve(); });
                                    existing.addEventListener('error', function(){ reject(new Error('load_failed')); });
                                    return;
                                }
                                var s = document.createElement('script');
                                s.src = src;
                                s.async = true;
                                s.onload = function(){ s.setAttribute('data-loaded','1'); resolve(); };
                                s.onerror = function(){ reject(new Error('load_failed')); };
                                document.head.appendChild(s);
                            });
                        }
                        async function ebEnsureWhmcsScripts(){
                            if (!window.jQuery) {
                                await ebLoadScriptOnce(ebJoinRoot('/assets/js/jquery.min.js'));
                            }
                            if (!window.WHMCS || !window.WHMCS.utils) {
                                await ebLoadScriptOnce(ebJoinRoot('/assets/js/whmcs.js'));
                            }
                            if (typeof window.whmcsBaseUrl === 'undefined' || window.whmcsBaseUrl === '') {
                                window.whmcsBaseUrl = (window.EB_WEB_ROOT || '').replace(/\/+$/,'');
                            }
                        }
                        async function ebEnsureStripeInit(status){
                            if (ebStripeInitInFlight) return false;
                            if (ebStripeInitDone && ebStripeKey && ebStripeKey === (status && status.stripe_publishable_key)) {
                                return true;
                            }
                            var publishableKey = (status && status.stripe_publishable_key) ? String(status.stripe_publishable_key) : '';
                            if (!publishableKey) {
                                ebSetCardError('Stripe is unavailable. Please contact support.');
                                return false;
                            }
                            ebStripeInitInFlight = true;
                            try {
                                await ebEnsureWhmcsScripts();
                                ebEnsureStripeCss();
                                await ebLoadScriptOnce('https://js.stripe.com/v3/');
                                await ebLoadScriptOnce(ebJoinRoot('/modules/gateways/stripe/stripe.js'));
                                if (!window.Stripe) {
                                    ebSetCardError('Stripe failed to load. Please try again.');
                                    ebStripeInitInFlight = false;
                                    return false;
                                }
                                if (!window.stripe || ebStripeKey !== publishableKey) {
                                    window.stripe = Stripe(publishableKey);
                                    window.elements = stripe.elements();
                                    window.card = elements.create('cardNumber');
                                    window.cardExpiryElements = elements.create('cardExpiry');
                                    window.cardCvcElements = elements.create('cardCvc');
                                    ebPatchStripeElement(window.card);
                                    ebPatchStripeElement(window.cardExpiryElements);
                                    ebPatchStripeElement(window.cardCvcElements);
                                }
                                ebStripeKey = publishableKey;
                                window.lang = window.lang || {
                                    creditCardInput: 'Card number',
                                    creditCardExpiry: 'Expiry',
                                    creditCardCvc: 'CVC'
                                };
                                window.csrfToken = window.EB_CSRF_TOKEN || window.csrfToken || '';
                                window.amount = '000';
                                window.paymentRequestButtonEnabled = true;
                                window.paymentRequestAmountDue = 0;
                                window.paymentRequestDescription = 'e3 Cloud Storage Trial';
                                var currency = (status && status.currency_code) ? String(status.currency_code).toLowerCase() : 'usd';
                                window.paymentRequestCurrency = currency;
                                if (typeof window.defaultErrorMessage === 'undefined') {
                                    window.defaultErrorMessage = 'Payment method could not be saved. Please try again.';
                                }
                                if (typeof initStripe === 'function') {
                                    initStripe();
                                    try {
                                        if (window.jQuery) {
                                            jQuery('#eb-addcard-form').off('submit.stripe');
                                        }
                                    } catch(_) {}
                                    if (typeof enablePaymentRequestButton === 'function') {
                                        enablePaymentRequestButton();
                                    }
                                }
                                if (typeof window.handlePaymentRequestAsSetupIntent === 'function' && !window.ebStripePRBWrapped) {
                                    window.ebStripePRBWrapped = true;
                                    var originalPRB = window.handlePaymentRequestAsSetupIntent;
                                    window.handlePaymentRequestAsSetupIntent = function(event){
                                        try {
                                            if (event && event.paymentMethod && event.paymentMethod.id) {
                                                window.ebStripeLastPaymentMethodId = event.paymentMethod.id;
                                            }
                                        } catch(_) {}
                                        return originalPRB(event);
                                    };
                                }
                                if (typeof window.stripeResponseHandler === 'function' && !window.ebStripeResponseWrapped) {
                                    window.ebStripeResponseWrapped = true;
                                    var originalResponse = window.stripeResponseHandler;
                                    window.stripeResponseHandler = function(token){
                                        if (window.ebStripeLastPaymentMethodId) {
                                            var pm = window.ebStripeLastPaymentMethodId;
                                            window.ebStripeLastPaymentMethodId = '';
                                            // Use simplified flow: save PaymentMethod directly via our custom API
                                            // Pass null for cardDetails, it will be retrieved inside ebFinalizePaymentMethod
                                            ebFinalizePaymentMethod(pm, null).then(function(res){
                                                if (res && res.status !== 'success' && res.message) {
                                                    ebSetCardError(res.message);
                                                }
                                            }).catch(function(e){
                                                ebSetCardError('Payment method could not be saved. Please try again.');
                                            });
                                            return;
                                        }
                                        return originalResponse(token);
                                    };
                                }
                                if (!ebApplyStripeDarkTheme()) {
                                    var attempts = 0;
                                    var iv = setInterval(function(){
                                        attempts++;
                                        if (ebApplyStripeDarkTheme() || attempts > 40) {
                                            clearInterval(iv);
                                        }
                                    }, 150);
                                }
                                ebStripeInitDone = true;
                                ebStripeInitInFlight = false;
                                return true;
                            } catch (e) {
                                ebStripeInitInFlight = false;
                                ebSetCardError('Stripe failed to load. Please try again.');
                                return false;
                            }
                        }
                        async function ebFetchPaymentStatus(){
                            try{
                                const resp = await fetch('modules/addons/cloudstorage/api/paymentmethod_status.php', {
                                    method:'POST',
                                    headers:{'Content-Type':'application/x-www-form-urlencoded'},
                                    body:''
                                });
                                const data = await resp.json();
                                return data;
                            }catch(e){
                                return { status: 'error', message: 'network' };
                            }
                        }
                        function ebPreparePasswordUi(choice){
                            var showUser = (choice==='backup' || choice==='ms365');
                            var unameRow = document.getElementById('eb-username-row');
                            var hint = document.getElementById('eb-username-hint');
                            if (unameRow) { if (showUser) unameRow.classList.remove('hidden'); else unameRow.classList.add('hidden'); }
                            if (hint) { hint.textContent=''; hint.classList.add('hidden'); }
                            var unameInput = document.getElementById('eb-username'); if (unameInput) unameInput.value='';
                            document.getElementById('eb-setpw-title').textContent = showUser ? 'Set your eazyBackup username and password' : 'Set your account password';
                        }
                        async function ebRequireCardForStorage(){
                            var status = await ebFetchPaymentStatus();
                            if (status && status.status === 'success' && status.has_card) {
                                ebPwOpen();
                                return true;
                            }
                            if (!status || status.status !== 'success') {
                                ebSetCardError('Unable to load payment form. Please try again.');
                            } else {
                                ebSetCardError('');
                                ebApplyBillingDefaults(status.billing || {});
                            }
                            ebPwClose();
                            ebCardOpen();
                            await ebEnsureStripeInit(status);
                            return false;
                        }
                        function ebDisableCardSubmit(dis){
                            try {
                                var b = document.getElementById('eb-card-submit');
                                if (!b) return;
                                b.disabled = !!dis;
                                if (dis) { b.classList.add('opacity-50','cursor-not-allowed'); }
                                else { b.classList.remove('opacity-50','cursor-not-allowed'); }
                            } catch(_) {}
                        }
                        async function ebGetStripeRemoteToken(paymentMethodId){
                            // Call /stripe/payment/add to attach PaymentMethod to Stripe Customer and get a remote token
                            var form = document.getElementById('eb-addcard-form');
                            if (!form) return { status: 'error', message: 'form_missing' };
                            ebSyncCsrfToken();
                            var data = new URLSearchParams(new FormData(form));
                            data.append('payment_method_id', paymentMethodId);
                            var addUrl = (window.WHMCS && WHMCS.utils && WHMCS.utils.getRouteUrl)
                                ? WHMCS.utils.getRouteUrl('/stripe/payment/add')
                                : ebJoinRoot('/index.php?rp=/stripe/payment/add');
                            var resp = await fetch(addUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: data.toString()
                            });
                            var json = await resp.json();
                            if (json && json.success && json.token) {
                                return { status: 'success', token: json.token };
                            }
                            if (json && json.validation_feedback) {
                                return { status: 'error', message: json.validation_feedback };
                            }
                            return { status: 'error', message: 'stripe_payment_add_failed' };
                        }
                        async function ebRetrievePaymentMethodDetails(paymentMethodId){
                            // Retrieve full PaymentMethod details from Stripe to get card info
                            if (!window.stripe) {
                                console.warn('Stripe not initialized, cannot retrieve PaymentMethod details');
                                return null;
                            }
                            try {
                                // Stripe.js v3 method to retrieve PaymentMethod with card details
                                var result = await stripe.retrievePaymentMethod(paymentMethodId);
                                console.log('PaymentMethod retrieval result:', result);
                                if (result && result.paymentMethod && result.paymentMethod.card) {
                                    var card = result.paymentMethod.card;
                                    var details = {
                                        last4: card.last4 || '0000',
                                        exp_month: card.exp_month || 12,
                                        exp_year: card.exp_year || 2030,
                                        brand: card.brand || 'unknown'
                                    };
                                    console.log('Extracted card details:', details);
                                    return details;
                                }
                                console.warn('PaymentMethod card details not found in response');
                            } catch(e) {
                                console.warn('Could not retrieve PaymentMethod details:', e);
                            }
                            // Return placeholder values as fallback
                            return {
                                last4: '0000',
                                exp_month: 12,
                                exp_year: 2030,
                                brand: 'unknown'
                            };
                        }
                        async function ebSaveCardViaApi(remoteToken, cardDetails){
                            // Use our custom add_paymentmethod.php API which calls localAPI('AddPayMethod')
                            // This bypasses the WHMCS core /account/paymentmethods/add route that expects HTML form submission
                            var params = { remote_storage_token: remoteToken };
                            if (cardDetails) {
                                params.card_last_four = cardDetails.last4 || '';
                                params.card_exp_month = cardDetails.exp_month || '';
                                params.card_exp_year = cardDetails.exp_year || '';
                                params.card_brand = cardDetails.brand || '';
                            }
                            var resp = await fetch('modules/addons/cloudstorage/api/add_paymentmethod.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams(params).toString()
                            });
                            var json = await resp.json();
                            if (json && json.status === 'success') {
                                return { status: 'success', paymethodid: json.paymethodid };
                            }
                            return { status: 'error', message: (json && json.message) ? json.message : 'add_paymentmethod_failed' };
                        }
                        async function ebFinalizePaymentMethod(paymentMethodId, existingCardDetails){
                            // Step 0: Use existing card details or retrieve from Stripe
                            var cardDetails = existingCardDetails;
                            if (!cardDetails || !cardDetails.last4 || cardDetails.last4 === '0000') {
                                cardDetails = await ebRetrievePaymentMethodDetails(paymentMethodId);
                            }
                            console.log('Using card details:', cardDetails);
                            
                            // Step 1: Get remote token via /stripe/payment/add (attaches PM to Stripe Customer)
                            var tokenRes = await ebGetStripeRemoteToken(paymentMethodId);
                            if (tokenRes.status !== 'success' || !tokenRes.token) {
                                return { status: 'error', message: tokenRes.message || 'stripe_payment_add_failed' };
                            }
                            // Step 2: Save via our custom API with card details (bypasses problematic WHMCS core route)
                            var saveResult = await ebSaveCardViaApi(tokenRes.token, cardDetails);
                            if (saveResult.status !== 'success') {
                                return { status: 'error', message: saveResult.message || 'save_failed' };
                            }
                            // Brief pause then verify
                            await ebSleep(500);
                            var status = await ebFetchPaymentStatus();
                            if (!status || status.status !== 'success' || !status.has_card) {
                                await ebSleep(800);
                                status = await ebFetchPaymentStatus();
                            }
                            if (status && status.status === 'success' && status.has_card) {
                                ebCardClose();
                                ebPwOpen();
                                return { status: 'success' };
                            }
                            return { status: 'error', message: 'verify_failed' };
                        }
                        async function ebSubmitCardViaApi(){
                            var form = document.getElementById('eb-addcard-form');
                            if (!form) return { status: 'error', message: 'form_missing' };
                            ebSyncCsrfToken();
                            if (!window.stripe || !window.card) {
                                return { status: 'error', message: 'stripe_unavailable' };
                            }
                            var data = new URLSearchParams(new FormData(form));
                            var setupUrl = (window.WHMCS && WHMCS.utils && WHMCS.utils.getRouteUrl)
                                ? WHMCS.utils.getRouteUrl('/stripe/setup/intent')
                                : ebJoinRoot('/index.php?rp=/stripe/setup/intent');
                            var setupResp = await fetch(setupUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: data.toString()
                            });
                            var setupJson = await setupResp.json();
                            if (!setupJson || !setupJson.success || !setupJson.setup_intent) {
                                return { status: 'error', message: setupJson && setupJson.validation_feedback ? setupJson.validation_feedback : 'setup_intent_failed' };
                            }
                            var setupResult = await stripe.handleCardSetup(setupJson.setup_intent, card);
                            console.log('handleCardSetup result:', setupResult);
                            if (setupResult.error) {
                                return { status: 'error', message: setupResult.error.message || 'card_setup_failed' };
                            }
                            var pmId = '';
                            var cardDetails = null;
                            
                            // Try to extract payment method ID and card details from the result
                            if (setupResult.setupIntent) {
                                var pm = setupResult.setupIntent.payment_method;
                                if (typeof pm === 'object' && pm !== null) {
                                    // payment_method is expanded, extract details directly
                                    pmId = pm.id || '';
                                    if (pm.card) {
                                        cardDetails = {
                                            last4: pm.card.last4 || '0000',
                                            exp_month: pm.card.exp_month || 12,
                                            exp_year: pm.card.exp_year || 2030,
                                            brand: pm.card.brand || 'unknown'
                                        };
                                        console.log('Card details from setupIntent:', cardDetails);
                                    }
                                } else if (typeof pm === 'string') {
                                    // payment_method is just an ID
                                    pmId = pm;
                                }
                            }
                            
                            if (!pmId) {
                                return { status: 'error', message: 'payment_method_missing' };
                            }
                            
                            // Save via our flow: /stripe/payment/add (works) then our custom API
                            // Pass cardDetails if we got them from the setupIntent
                            return await ebFinalizePaymentMethod(pmId, cardDetails);
                        }
                        async function ebCardSubmit(ev){
                            if (ev && ev.preventDefault) ev.preventDefault();
                            if (ebCardSubmitting) return false;
                            ebCardSubmitting = true;
                            ebSetCardError('');
                            ebDisableCardSubmit(true);
                            try {
                                await ebEnsureWhmcsScripts();
                                await ebEnsureStripeInit(await ebFetchPaymentStatus());
                                var result = await ebSubmitCardViaApi();
                                if (result && result.status === 'success') {
                                    var status = await ebFetchPaymentStatus();
                                    if (status && status.status === 'success' && status.has_card) {
                                        ebCardClose();
                                        ebPwOpen();
                                    } else {
                                        ebSetCardError('We saved your card but could not verify it yet. Please refresh and try again.');
                                    }
                                } else {
                                    var msg = (result && (result.message || result.error)) ? String(result.message || result.error) : '';
                                    ebSetCardError(msg && msg !== 'setup_intent_failed' ? msg : 'We could not save your card. Please try again.');
                                }
                            } catch (e) {
                                ebSetCardError('We could not save your card. Please try again.');
                            } finally {
                                ebCardSubmitting = false;
                                ebDisableCardSubmit(false);
                            }
                            return false;
                        }
                        function ebSleep(ms){ return new Promise(function(r){ setTimeout(r, ms); }); }
                        function ebExtractIframeError(){
                            try {
                                var iframe = document.getElementById('eb-card-target');
                                if (!iframe) return '';
                                var doc = iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document);
                                if (!doc || !doc.body) return '';
                                var alert = doc.querySelector('.alert,.alert-danger,.alert-error,.error,.clientalert,.messagebox,.payment-error');
                                if (alert && alert.textContent) return alert.textContent.trim();
                                var text = (doc.body.textContent || '').trim();
                                if (text) {
                                    if (text.length > 240) return text.slice(0, 240) + '…';
                                    return text;
                                }
                            } catch(_) {}
                            return '';
                        }
                        async function ebHandleCardIframeLoad(){
                            if (!ebCardSubmitting) return;
                            ebCardSubmitting = false;
                            var status = await ebFetchPaymentStatus();
                            if (!status || status.status !== 'success' || !status.has_card) {
                                await ebSleep(800);
                                status = await ebFetchPaymentStatus();
                            }
                            if (!status || status.status !== 'success' || !status.has_card) {
                                await ebSleep(1200);
                                status = await ebFetchPaymentStatus();
                            }
                            if (status && status.status === 'success' && status.has_card) {
                                ebCardClose();
                                ebPwOpen();
                                return;
                            }
                            var err = ebExtractIframeError();
                            ebSetCardError(err ? err : 'We could not save your card. Please try again.');
                        }
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
                                    ebPreparePasswordUi(data.product_choice);
                                    if (data.product_choice === 'storage') {
                                        // Show plan selection drawer for Cloud Storage
                                        ebStoragePlanOpen();
                                    } else {
                                        ebPwOpen();
                                    }
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
                                // Get storage tier for Cloud Storage product
                                const storageTierEl = document.getElementById('eb-storage-tier');
                                const storageTier = storageTierEl ? storageTierEl.value : '';
                                // Front-end validation to match backend (comet)
                                const needsUser = (choice==='backup' || choice==='ms365');
                                if (needsUser) {
                                    const reUser = /^[A-Za-z0-9_.-]{8,}$/;
                                    if (!reUser.test(uname)) {
                                        const msg = 'Backup username must be at least 8 characters and may contain only a-z, A-Z, 0-9, _, ., -';
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
                                    body: new URLSearchParams({ product_choice: choice, username: uname, new_password: p1, new_password_confirm: p2, storage_tier: storageTier })
                                });
                                const data = await resp.json();
                                if((data && data.status)==='success' && data.redirectUrl){
                                    ebPwClose(); window.location.href = data.redirectUrl;
                                    return false;
                                }
                                if (data && data.requires_payment_method) {
                                    ebPwClose();
                                    ebPreparePasswordUi(choice);
                                    await ebRequireCardForStorage();
                                    ebDisableSubmit(false);
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
                        document.addEventListener('DOMContentLoaded', function(){
                            var iframe = document.getElementById('eb-card-target');
                            if (iframe) {
                                iframe.addEventListener('load', function(){
                                    ebHandleCardIframeLoad();
                                });
                            }
                        });
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
