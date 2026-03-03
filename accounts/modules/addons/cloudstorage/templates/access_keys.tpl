<div class="min-h-screen bg-slate-950 text-gray-300 overflow-x-hidden">
    {* <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div> *}
    <div class="container mx-auto max-w-full px-4 pb-10 pt-6 relative pointer-events-auto">

        <!-- Glass Container -->
        <div class="w-full max-w-full min-w-0 overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
            <div class="-mx-6 -mt-6 mb-6 rounded-t-3xl border-b border-slate-800/80 bg-slate-900/50 px-6 py-3">
                <nav class="flex flex-wrap items-center gap-1" aria-label="Cloud Storage Navigation">
                    <a href="index.php?m=cloudstorage&page=dashboard"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $smarty.get.page == 'dashboard' || !$smarty.get.page}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                        </svg>
                        <span class="text-sm font-medium">Dashboard</span>
                    </a>
                    <a href="index.php?m=cloudstorage&page=buckets"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $smarty.get.page == 'buckets'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                        </svg>
                        <span class="text-sm font-medium">Buckets</span>
                    </a>
                    <a href="index.php?m=cloudstorage&page=access_keys"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $smarty.get.page == 'access_keys'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                        </svg>
                        <span class="text-sm font-medium">Access Keys</span>
                    </a>
                    <a href="index.php?m=cloudstorage&page=users"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $smarty.get.page == 'users'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                        <span class="text-sm font-medium">Users</span>
                    </a>
                    <a href="index.php?m=cloudstorage&page=billing"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $smarty.get.page == 'billing'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <span class="text-sm font-medium">Billing</span>
                    </a>
                    <a href="index.php?m=cloudstorage&page=history"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $smarty.get.page == 'history'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span class="text-sm font-medium">Historical Stats</span>
                    </a>
                </nav>
            </div>
                

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
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">Date Created</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-white">Root user</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-white">{$user->tenant_id}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap relative">
                                    <input type="text"
                                           value="{if $HAS_PRIMARY_KEY && $accessKey->is_user_generated && !empty($accessKey->access_key_hint)}{$accessKey->access_key_hint}{/if}"
                                           placeholder="—"
                                           class="w-full rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-sky-500 font-mono"
                                           id="accessKey" readonly>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {if $HAS_PRIMARY_KEY && $accessKey->is_user_generated}
                                        <span class="text-sm text-gray-400" title="Creation Date">{$accessKey->created_at|date_format:"%d %b %Y %I:%M %p"}</span>
                                    {else}
                                        <span class="text-sm text-gray-500">—</span>
                                    {/if}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex space-x-2">
                                        <button
                                            type="button"
                                            class="btn-accent"
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
        <div id="loading-overlay" class="fixed inset-0 bg-gray-900/75 flex items-center justify-center z-50">
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
        <div class="fixed inset-0 bg-black/75 flex items-center justify-center z-50 hidden" id="updateKeysModal">
            <div class="bg-gray-800 rounded-lg shadow-lg w-full max-w-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h5 class="text-xl font-semibold text-white">{if $HAS_PRIMARY_KEY}Create new access key{else}Create your first access key{/if}</h5>
                    <button type="button" onclick="closeModal('updateKeysModal')" class="text-gray-400 hover:text-white focus:outline-none">
                        <!-- Close Icon SVG -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div>
                    <p class="text-sm mb-4">
                        {if $HAS_PRIMARY_KEY}
                            Creating a new key revokes the current key and generates a new one.
                        {else}
                            Create your access key to start using S3 tools.
                        {/if}
                        You will be shown the secret key only once.
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
        <div class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 hidden" id="newKeysModal">
            <div class="relative bg-slate-900/95 rounded-2xl border border-slate-700 shadow-2xl shadow-black/60 w-full max-w-xl mx-4">
                <!-- Orange accent line -->
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-[#FE5000]/0 via-[#FE5000]/80 to-amber-400/0 rounded-t-2xl"></div>
                <div class="p-8">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h5 class="text-2xl font-bold text-white">Save your new key</h5>
                            <p class="mt-2 text-sm text-slate-300">
                                This is the <strong class="text-white">only</strong> time you can view the secret key. Store it securely.
                            </p>
                        </div>
                        <button type="button" onclick="closeModal('newKeysModal')" class="text-slate-400 hover:text-white text-2xl leading-none focus:outline-none transition-colors">
                            &times;
                        </button>
                    </div>
                    <!-- Warning banner -->
                    <div class="mb-6 flex items-center gap-3 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3">
                        <svg class="w-5 h-5 text-amber-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span class="text-sm text-amber-200">You won't be able to see this secret key again after closing this window.</span>
                    </div>
                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Access key</label>
                            <div class="flex items-center gap-3">
                                <input type="text" id="newAccessKey" class="w-full rounded-lg bg-slate-800/60 border border-slate-600 px-4 py-3 text-base text-white font-mono focus:outline-none focus:ring-2 focus:ring-[#FE5000] focus:border-[#FE5000] transition-all duration-200" readonly>
                                <button
                                    type="button"
                                    class="shrink-0 rounded-lg bg-slate-800 border border-slate-600 p-3 text-slate-300 hover:text-[#FE5000] hover:border-[#FE5000]/50 focus:outline-none focus:ring-2 focus:ring-[#FE5000] transition-all duration-200"
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
                            <label class="block text-sm font-medium text-slate-300 mb-2">Secret key</label>
                            <div class="flex items-center gap-3">
                                <input type="text" id="newSecretKey" class="w-full rounded-lg bg-slate-800/60 border border-slate-600 px-4 py-3 text-base text-white font-mono focus:outline-none focus:ring-2 focus:ring-[#FE5000] focus:border-[#FE5000] transition-all duration-200" readonly>
                                <button
                                    type="button"
                                    class="shrink-0 rounded-lg bg-slate-800 border border-slate-600 p-3 text-slate-300 hover:text-[#FE5000] hover:border-[#FE5000]/50 focus:outline-none focus:ring-2 focus:ring-[#FE5000] transition-all duration-200"
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
                    <div class="flex justify-end mt-8">
                        <button type="button" class="btn-orange-matte" onclick="closeModal('newKeysModal')">Done</button>
                    </div>
                </div>
            </div>
        </div>

        {* Password Slide-Over *}
        <div id="passwordSlideover" x-data="{ isOpen: false }" x-init="
            window.addEventListener('open-decrypt-slideover', () => { isOpen = true });
            window.addEventListener('close-decrypt-slideover', () => { isOpen = false });
        " x-show="isOpen" class="fixed inset-0 z-50" style="display:none;">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/60"
                 x-show="isOpen"
                 x-transition.opacity
                 onclick="closeDecryptSlideover()"></div>
            <!-- Panel -->
            <div class="absolute right-0 top-0 h-full w-full max-w-xl rounded-l-2xl border border-slate-700 bg-slate-900/95 text-white shadow-2xl shadow-black/60 backdrop-blur-sm overflow-y-auto"
                 x-show="isOpen"
                 x-transition:enter="transform transition ease-in-out duration-300"
                 x-transition:enter-start="translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transform transition ease-in-out duration-300"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="translate-x-full">
                <div class="px-8 py-8 sm:px-10 sm:py-10">
                    <div class="flex items-start justify-between mb-6">
                        <div>
                            <h3 class="text-xl font-bold text-white">Verify Password</h3>
                            <p class="mt-2 text-sm text-slate-300">Confirm your identity to generate new access keys.</p>
                        </div>
                        <button class="text-slate-400 hover:text-white text-2xl leading-none transition-colors" onclick="closeDecryptSlideover()">
                            &times;
                        </button>
                    </div>
                    <div id="passwordErrorMessage" class="rounded-lg border border-rose-500/60 bg-rose-500/10 px-4 py-3 text-sm text-rose-100 mb-6 hidden" role="alert"></div>
                    <div class="space-y-5">
                        <input type="hidden" id="action" value="rollkeys">
                        <div class="space-y-2">
                            <label for="password" class="block text-sm font-medium text-slate-200">Account password</label>
                            <input
                                type="password"
                                class="block w-full rounded-lg border border-slate-600 bg-slate-800/60 px-4 py-3 text-base text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-[#FE5000] focus:border-[#FE5000] transition-all duration-200"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                required
                            >
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-8">
                        <button type="button" class="px-5 py-2.5 text-sm font-medium text-slate-300 hover:text-white transition-colors" onclick="closeDecryptSlideover()">Cancel</button>
                        <button
                            type="button"
                            class="btn-orange-matte"
                            id="submitPassword"
                        >
                            Verify & Generate Keys
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
