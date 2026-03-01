<style>
  [x-cloak] { display: none !important; }
  .table-row-hover:hover { background-color: #1e293b; }
</style>
<!-- ebLoader -->
<script src="{$WEB_ROOT}/modules/addons/eazybackup/templates/assets/js/ui.js"></script>

<div class="min-h-screen bg-slate-950 text-gray-300" x-data="usersV2Manager()" x-init="init()">
  {* <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div> *}
  <div class="container mx-auto px-4 pb-10 pt-6 relative pointer-events-auto">

    <!-- Navigation -->
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

    <!-- Card -->
    <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">

      <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
        <div class="flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 mr-2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
          </svg>
          <h1 class="text-2xl font-semibold text-white">Users</h1>
          <span class="ml-3 px-2 py-1 bg-slate-700 text-slate-300 text-sm rounded" x-text="filteredUsers.length + ' users'"></span>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto min-w-0">
          <div class="relative">
            <input type="text" x-model="searchTerm" placeholder="Search users..."
                   class="bg-slate-800 text-gray-300 placeholder-slate-400 focus:ring-sky-500 focus:border-sky-500 block w-full sm:w-48 lg:w-64 px-4 py-2 border border-slate-600 rounded-md focus:outline-none">
            <svg class="absolute right-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
          </div>

          <button @click="openDeleteSelectedUsers()"
                  :disabled="!canDeleteSelectedUsers"
                  class="px-4 py-2 rounded-md bg-slate-900 border border-slate-700 hover:bg-slate-800/60 text-slate-100 text-sm font-semibold inline-flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap">
            Delete
          </button>

          <button @click="openCreateUser()"
                  class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold inline-flex items-center gap-2 whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Create User
          </button>
        </div>
      </div>

      <!-- Users Table (styled like Agents table) -->
      <div class="overflow-x-auto rounded-lg border border-slate-800">
        <table class="min-w-full divide-y divide-slate-800 text-sm">
          <thead class="bg-slate-900/80 text-slate-300">
            <tr>
              <th class="px-4 py-3 text-left font-medium w-10" @click.stop>
                <input type="checkbox" class="text-sky-600" :checked="allSelectableChecked" @change="toggleSelectAll($event)">
              </th>
              <th class="px-4 py-3 text-left font-medium">Username</th>
              <th class="px-4 py-3 text-left font-medium">Account ID</th>
              <th class="px-4 py-3 text-left font-medium">Buckets</th>
              <th class="px-4 py-3 text-left font-medium">Storage</th>
              <th class="px-4 py-3 text-left font-medium">Access keys</th>
              <th class="px-4 py-3 text-right font-medium">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <template x-for="u in filteredUsers" :key="u.username">
              <tr class="hover:bg-slate-800/50 cursor-pointer" @click="openUser(u)">
                <td class="px-4 py-3" @click.stop>
                  <input type="checkbox"
                         class="text-sky-600"
                         :disabled="(u.total_buckets || 0) > 0 || u.is_root || u.manage_locked"
                         :checked="selectedUsernames.includes(u.username)"
                         @change="toggleUserSelection(u, $event)">
                </td>
                <td class="px-4 py-3 text-slate-200">
                  <div class="flex items-center gap-2">
                    <span x-text="u.display_name || u.username"></span>
                    <span x-show="u.is_system_managed" x-cloak class="inline-flex items-center rounded-full bg-indigo-900/40 text-indigo-300 text-[11px] px-2 py-0.5 border border-indigo-700/40">
                      System Managed
                    </span>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <span class="font-mono text-slate-200" x-text="u.tenant_id ? String(u.tenant_id) : '—'"></span>
                </td>
                <td class="px-4 py-3 text-slate-200" x-text="u.total_buckets || 0"></td>
                <td class="px-4 py-3 text-slate-200" x-html="u.total_storage || '0 B'"></td>
                <td class="px-4 py-3 text-slate-200" x-text="(u.access_keys || []).length"></td>
                <td class="px-4 py-3 text-right" @click.stop>
                  <template x-if="!u.manage_locked">
                    <button class="text-sky-400 hover:underline" @click="openUser(u)">Manage</button>
                  </template>
                  <template x-if="u.manage_locked">
                    <span class="text-slate-500 text-xs">Locked</span>
                  </template>
                </td>
              </tr>
            </template>
            <tr x-show="filteredUsers.length === 0" x-cloak>
              <td colspan="7" class="px-4 py-8 text-center text-slate-400">No users found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Toast (visible above modals) -->
  <div class="fixed top-4 right-4 z-[9999]" x-cloak>
    <div x-show="toast.visible" x-transition
         class="rounded-md px-4 py-3 text-white shadow-lg min-w-[260px]"
         :class="{
            'bg-green-600': toast.type==='success',
            'bg-red-600': toast.type==='error',
            'bg-blue-600': toast.type==='info'
         }">
      <div class="flex items-start justify-between">
        <div class="pr-2" x-text="toast.message"></div>
        <button type="button" class="ml-2 text-white/80 hover:text-white" @click="toast.visible=false" aria-label="Close">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
    </div>
  </div>

  <!-- Right-side panel: User details -->
  <div x-show="panel.open" x-cloak class="fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/70" @click="closeUser()"></div>
    <div class="absolute right-0 top-0 h-full w-full max-w-3xl bg-slate-950 border-l border-slate-800/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] overflow-y-auto">
      <div class="flex items-center justify-between p-4 border-b border-slate-800">
        <div class="min-w-0">
          <div class="text-white font-semibold text-lg truncate" x-text="panel.user?.display_name || panel.user?.username || 'User'"></div>
          <div class="text-xs text-slate-400 mt-1">
            <span class="text-slate-500">Account ID:</span>
            <span class="font-mono text-slate-200" x-text="panel.user?.tenant_id ? String(panel.user.tenant_id) : '—'"></span>
          </div>
          <div x-show="panel.user?.is_system_managed" x-cloak class="mt-2">
            <span class="inline-flex items-center rounded-full bg-indigo-900/40 text-indigo-300 text-[11px] px-2 py-0.5 border border-indigo-700/40">System Managed</span>
          </div>
        </div>
        <button class="text-slate-300 hover:text-white" @click="closeUser()">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <div class="p-4 space-y-4">
        <!-- Summary -->
        <div class="rounded-2xl border border-slate-800 bg-slate-900/40 p-4">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <div class="text-xs text-slate-500">Buckets</div>
              <div class="text-sm text-slate-100" x-text="panel.user?.total_buckets || 0"></div>
            </div>
            <div>
              <div class="text-xs text-slate-500">Storage</div>
              <div class="text-sm text-slate-100" x-html="panel.user?.total_storage || '0 B'"></div>
            </div>
            <div>
              <div class="text-xs text-slate-500">Access keys</div>
              <div class="text-sm text-slate-100" x-text="(panel.user?.access_keys || []).length"></div>
            </div>
          </div>
        </div>

        <!-- Access keys card -->
        <div class="rounded-2xl border border-slate-800 bg-slate-900/40 p-4">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-white">Access keys</h3>
            <button class="text-xs bg-sky-600 hover:bg-sky-700 text-white px-3 py-2 rounded-md"
                    :disabled="panel.user?.manage_locked"
                    :class="{ 'opacity-50 cursor-not-allowed': panel.user?.manage_locked }"
                    @click="openCreateKey(panel.user)"
                    x-text="panel.user?.is_root ? (panel.user?.has_root_key ? 'Rotate access key' : 'Create access key') : '+ Create access key'">
            </button>
          </div>

          <div x-show="panel.user?.is_root" x-cloak>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-slate-800">
                <thead class="text-xs text-slate-400">
                  <tr>
                    <th class="py-2 pr-4 text-left">Key</th>
                    <th class="py-2 pr-4 text-left">Created</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-sm">
                  <tr>
                    <td class="py-2 pr-4 font-mono text-slate-200" x-text="panel.user?.root_access_key_hint || '—'"></td>
                    <td class="py-2 pr-4 text-slate-400" x-text="formatDate(panel.user?.root_access_key_created_at)"></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="mt-3 text-xs text-slate-400">
              For security, the secret key is shown <span class="text-slate-200 font-semibold">only once</span> when you rotate the key.
            </div>
          </div>

          <div x-show="!panel.user?.is_root" x-cloak>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-slate-800">
                <thead class="text-xs text-slate-400">
                  <tr>
                    <th class="py-2 pr-4 text-left">Key</th>
                    <th class="py-2 pr-4 text-left">Description</th>
                    <th class="py-2 pr-4 text-left">Permission</th>
                    <th class="py-2 pr-4 text-left">Created</th>
                    <th class="py-2 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-sm">
                  <template x-for="k in (panel.user?.access_keys || [])" :key="k.key_id">
                    <tr>
                      <td class="py-2 pr-4 font-mono text-slate-200" x-text="k.access_key_hint || '—'"></td>
                      <td class="py-2 pr-4 text-slate-200" x-text="k.description || '—'"></td>
                      <td class="py-2 pr-4 text-slate-200" x-text="permissionLabel(k.permission)"></td>
                      <td class="py-2 pr-4 text-slate-400" x-text="formatDate(k.created_at)"></td>
                      <td class="py-2 text-right">
                        <button class="text-rose-400 hover:text-rose-300 text-sm"
                                :disabled="panel.user?.manage_locked"
                                :class="{ 'opacity-50 cursor-not-allowed hover:text-rose-400': panel.user?.manage_locked }"
                                @click="confirmDeleteKey(panel.user, k)">
                          Delete
                        </button>
                      </td>
                    </tr>
                  </template>
                  <tr x-show="(panel.user?.access_keys || []).length === 0" x-cloak>
                    <td colspan="5" class="py-4 text-center text-slate-400 text-sm">No access keys yet.</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="mt-3 text-xs text-slate-400">
              For security, the secret key is shown <span class="text-slate-200 font-semibold">only once</span> when you create a key.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Create User modal -->
  <div x-show="modals.createUser" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/75">
    <div class="w-full max-w-lg rounded-2xl border border-slate-800 bg-slate-950 p-6" @click.away="modals.createUser = false">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-white">Create User</h3>
        <button class="text-slate-300 hover:text-white" @click="modals.createUser = false">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      <div class="space-y-3">
        <div>
          <label class="block text-sm text-slate-300 mb-1">Username</label>
          <input type="text" x-model="createUserForm.username" placeholder="e.g., acme-corp"
                 class="w-full bg-slate-900 border border-slate-700 text-slate-200 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-sky-500">
          <div class="text-xs text-slate-500 mt-1">This is the user identifier shown in your dashboard.</div>
        </div>
      </div>
      <div class="flex justify-end gap-2 mt-6">
        <button class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-md" @click="modals.createUser = false">Cancel</button>
        <button class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold inline-flex items-center gap-2" @click="submitCreateUser()">Create</button>
      </div>
    </div>
  </div>

  <!-- Create Access Key modal -->
  <div x-show="modals.createKey" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="w-full max-w-xl rounded-2xl border border-slate-800 bg-slate-950 p-6" @click.away="modals.createKey = false">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-white">Create access key</h3>
        <button class="text-slate-300 hover:text-white" @click="modals.createKey = false">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <div class="space-y-4">
        <div class="text-sm text-slate-300">
          Creating a new key will show the secret key <span class="text-slate-100 font-semibold">once</span>. Save it securely.
        </div>
        <div>
          <label class="block text-sm text-slate-300 mb-1">Description</label>
          <input type="text" x-model="createKeyForm.description" placeholder="e.g., backup server"
                 class="w-full bg-slate-900 border border-slate-700 text-slate-200 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-sky-500">
        </div>
        <div>
          <label class="block text-sm text-slate-300 mb-1">Permission</label>
          <div class="relative" x-data="{ open:false }">
            <button type="button"
                    class="w-full bg-slate-900 border border-slate-700 text-slate-200 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-sky-500 flex items-center justify-between"
                    @click="open=!open">
              <span x-text="permissionLabel(createKeyForm.permission)"></span>
              <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
              </svg>
            </button>
            <div x-show="open" x-cloak @click.away="open=false"
                 class="absolute z-50 mt-2 w-full rounded-md bg-slate-900 border border-slate-700 shadow-lg overflow-hidden">
              <button type="button" class="w-full text-left px-3 py-2 hover:bg-slate-800/60"
                      @click="createKeyForm.permission='full'; open=false">
                <div class="flex flex-col">
                  <span class="text-sm font-medium text-slate-100">Full</span>
                  <span class="text-xs text-slate-400 mt-0.5">Read + write + list + manage buckets.</span>
                </div>
              </button>
              <button type="button" class="w-full text-left px-3 py-2 hover:bg-slate-800/60"
                      @click="createKeyForm.permission='read'; open=false">
                <div class="flex flex-col">
                  <span class="text-sm font-medium text-slate-100">Read</span>
                  <span class="text-xs text-slate-400 mt-0.5">Download and list objects.</span>
                </div>
              </button>
              <button type="button" class="w-full text-left px-3 py-2 hover:bg-slate-800/60"
                      @click="createKeyForm.permission='write'; open=false">
                <div class="flex flex-col">
                  <span class="text-sm font-medium text-slate-100">Write</span>
                  <span class="text-xs text-slate-400 mt-0.5">Upload objects.</span>
                </div>
              </button>
              <button type="button" class="w-full text-left px-3 py-2 hover:bg-slate-800/60"
                      @click="createKeyForm.permission='readwrite'; open=false">
                <div class="flex flex-col">
                  <span class="text-sm font-medium text-slate-100">Read/Write</span>
                  <span class="text-xs text-slate-400 mt-0.5">Upload + download + list objects.</span>
                </div>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-2 mt-6">
        <button class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-md" @click="modals.createKey = false">Cancel</button>
        <button class="bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-md" @click="submitCreateKey()">Create key</button>
      </div>
    </div>
  </div>

  <!-- Show new key modal (one-time) -->
  <div x-show="modals.showKey" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/75">
    <div class="w-full max-w-2xl rounded-2xl border border-slate-800 bg-slate-950 p-6" @click.away="modals.showKey = false">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-white">Save your access key</h3>
        <button class="text-slate-300 hover:text-white" @click="modals.showKey = false">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <div class="rounded-xl border border-amber-700/60 bg-amber-900/10 p-3 text-amber-200 text-sm mb-4">
        This is the <strong>only</strong> time you can view the secret key. Store it securely.
      </div>

      <div class="space-y-3">
        <div>
          <label class="block text-xs text-slate-400 mb-1">Access key</label>
          <div class="flex gap-2">
            <input type="text" readonly :value="newKey.access_key"
                   class="w-full rounded-md bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default px-3 py-2 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 text-sm text-slate-200 font-mono">
            <button class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-3 py-2 rounded-md text-sm"
                    @click="copyText(newKey.access_key)">Copy</button>
          </div>
        </div>
        <div>
          <label class="block text-xs text-slate-400 mb-1">Secret key</label>
          <div class="flex gap-2">
            <input type="text" readonly :value="newKey.secret_key"
                   class="w-full rounded-md bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default px-3 py-2 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 text-sm text-slate-200 font-mono">
            <button class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-3 py-2 rounded-md text-sm"
                    @click="copyText(newKey.secret_key)">Copy</button>
          </div>
        </div>
      </div>

      <div class="flex justify-end mt-6">
        <button class="bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-md" @click="modals.showKey = false">Done</button>
      </div>
    </div>
  </div>

  <!-- Password modal -->
  <div x-show="modals.password" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/75">
    <div class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-950 p-6" @click.away="modals.password = false">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-white">Verify password</h3>
        <button class="text-slate-300 hover:text-white" @click="modals.password = false">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      <div class="text-sm text-slate-300 mb-3">Enter your account password to continue.</div>
      <div x-show="passwordError" x-cloak class="bg-rose-600 text-white px-3 py-2 rounded mb-3 text-sm" x-text="passwordError"></div>
      <input type="password" x-model="passwordForm.password" placeholder="Account password"
             class="w-full bg-slate-900 border border-slate-700 text-slate-200 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-sky-500">
      <div class="flex justify-end gap-2 mt-4">
        <button class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-md" @click="modals.password = false">Cancel</button>
        <button class="bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-md" @click="submitPassword()">Verify</button>
      </div>
    </div>
  </div>

  <!-- Confirm delete key modal -->
  <div x-show="modals.confirmDelete" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/75">
    <div class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-950 p-6" @click.away="modals.confirmDelete = false">
      <div class="flex items-center gap-3 mb-3">
        <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.35 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
        </svg>
        <h3 class="text-lg font-semibold text-white">Delete access key</h3>
      </div>
      <div class="text-sm text-slate-300">
        Delete key <span class="font-mono text-slate-100" x-text="deleteTarget.key?.access_key_hint || ''"></span>?
        This may break apps using it.
      </div>
      <div class="flex justify-end gap-2 mt-5">
        <button class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-md" @click="modals.confirmDelete = false">Cancel</button>
        <button class="bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded-md" @click="requestDeleteKey()">Delete</button>
      </div>
    </div>
  </div>

  <!-- Confirm delete users modal -->
  <div x-show="modals.confirmDeleteUsers" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/75">
    <div class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-950 p-6" @click.away="modals.confirmDeleteUsers = false">
      <div class="flex items-center gap-3 mb-3">
        <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.35 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
        </svg>
        <h3 class="text-lg font-semibold text-white">Delete users</h3>
      </div>
      <div class="text-sm text-slate-300">
        Delete <span class="text-slate-100 font-semibold" x-text="selectedUsernames.length"></span> selected users?
        This cannot be undone.
      </div>
      
      <div class="flex justify-end gap-2 mt-5">
        <button class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-md" @click="modals.confirmDeleteUsers = false">Cancel</button>
        <button class="bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded-md" @click="requestDeleteSelectedUsers()">Delete</button>
      </div>
    </div>
  </div>

  <!-- ebLoader integration -->
  <div x-effect="
    (function(){
      try {
        if (loading) {
          if (window.ebShowLoader) window.ebShowLoader(document.body, (loadingText || 'Loading…'));
        } else {
          if (window.ebHideLoader) window.ebHideLoader(document.body);
        }
      } catch(_) {}
    })()
  "></div>
</div>

<script>
var usersData = {if $users}{$users|json_encode}{else}[]{/if};
</script>

{literal}
<script>
function usersV2Manager() {
  return {
    users: usersData || [],
    searchTerm: '',
    loading: false,
    loadingText: 'Loading…',
    toast: {
      visible: false,
      message: '',
      type: 'info',
      timeout: null,
      show(msg, t) {
        this.message = msg || '';
        this.type = t || 'info';
        this.visible = true;
        if (this.timeout) clearTimeout(this.timeout);
        this.timeout = setTimeout(() => { this.visible = false; }, this.type === 'error' ? 7000 : 4000);
      }
    },
    panel: { open: false, user: null },
    modals: { createUser: false, createKey: false, showKey: false, password: false, confirmDelete: false, confirmDeleteUsers: false },
    createUserForm: { username: '' },
    createKeyForm: { username: '', description: '', permission: 'full' },
    newKey: { access_key: '', secret_key: '' },
    passwordForm: { password: '' },
    passwordError: '',
    pendingAction: null, // pending action requiring password verification
    deleteTarget: { user: null, key: null },
    selectedUsernames: [],

    init() {
      // Normalize counts / arrays
      this.users = (this.users || []).map(u => {
        u.access_keys = u.access_keys || [];
        u.display_name = u.display_name || u.username;
        u.is_root = !!u.is_root;
        u.is_system_managed = !!u.is_system_managed;
        u.manage_locked = !!u.manage_locked;
        u.has_root_key = !!u.has_root_key;
        u.root_access_key_hint = u.root_access_key_hint || '';
        u.root_access_key_created_at = u.root_access_key_created_at || null;
        return u;
      });
    },

    get filteredUsers() {
      const term = (this.searchTerm || '').trim().toLowerCase();
      if (!term) return this.users;
      return this.users.filter(u => {
        const name = (u.username || '').toLowerCase();
        const label = (u.display_name || '').toLowerCase();
        return name.includes(term) || label.includes(term);
      });
    },

    toastSuccess(message) { this.toast.show(message, 'success'); },
    toastError(message) { this.toast.show(message, 'error'); },
    toastInfo(message) { this.toast.show(message, 'info'); },

    get allSelectableChecked() {
      const selectable = this.filteredUsers
        .filter(u => (u.total_buckets || 0) === 0 && !u.is_root && !u.manage_locked)
        .map(u => u.username);
      if (selectable.length === 0) return false;
      return selectable.every(u => this.selectedUsernames.includes(u));
    },

    toggleSelectAll(evt) {
      const checked = !!evt.target.checked;
      const selectable = this.filteredUsers
        .filter(u => (u.total_buckets || 0) === 0 && !u.is_root && !u.manage_locked)
        .map(u => u.username);
      if (checked) {
        const set = new Set(this.selectedUsernames);
        selectable.forEach(u => set.add(u));
        this.selectedUsernames = Array.from(set);
      } else {
        this.selectedUsernames = this.selectedUsernames.filter(u => !selectable.includes(u));
      }
    },

    toggleUserSelection(user, evt) {
      const checked = !!evt.target.checked;
      if (user.is_root || user.manage_locked) return;
      const uname = user.username;
      if (!uname) return;
      if (checked) {
        if (!this.selectedUsernames.includes(uname)) this.selectedUsernames.push(uname);
      } else {
        this.selectedUsernames = this.selectedUsernames.filter(u => u !== uname);
      }
    },

    get canDeleteSelectedUsers() {
      if (this.selectedUsernames.length === 0) return false;
      const selected = this.selectedUsernames.map(u => this.users.find(x => x.username === u)).filter(Boolean);
      return selected.length > 0 && selected.every(u => (u.total_buckets || 0) === 0 && !u.is_root && !u.manage_locked);
    },

    openUser(u) {
      this.panel.user = u;
      this.panel.open = true;
    },
    closeUser() {
      this.panel.open = false;
      this.panel.user = null;
    },

    openCreateUser() {
      this.createUserForm = { username: '' };
      this.modals.createUser = true;
    },

    submitCreateUser() {
      const uname = (this.createUserForm.username || '').trim();
      if (!uname) {
        this.toastError('Please enter a username');
        return;
      }
      this.loading = true;
      this.loadingText = 'Creating user…';
      this.apiCall('modules/addons/cloudstorage/api/managedusers.php', {
        action: 'addtenant',
        username: uname,
        name: uname
      }).then(res => {
        if (res.status === 'success') {
          const newUser = {
            username: res.data.username,
            display_name: res.data.username,
            is_root: false,
            tenant_id: res.data.tenant_id || null,
            total_buckets: 0,
            total_storage: '0 B',
            access_keys: []
          };
          this.users.unshift(newUser);
          this.modals.createUser = false;
          this.toastSuccess(res.message || 'User created.');
        } else {
          this.toastError(res.message || 'Failed to create user.');
        }
      }).catch(() => {
        this.toastError('Failed to create user.');
      }).finally(() => {
        this.loading = false;
      });
    },

    openDeleteSelectedUsers() {
      if (!this.canDeleteSelectedUsers) {
        this.toastError('Select one or more users with 0 buckets to delete.');
        return;
      }
      this.modals.confirmDeleteUsers = true;
    },

    requestDeleteSelectedUsers() {
      this.modals.confirmDeleteUsers = false;
      this.pendingAction = { type: 'deleteUsers', payload: { usernames: [...this.selectedUsernames] } };
      this.openPassword();
    },

    async executeDeleteSelectedUsers(usernames) {
      if (!Array.isArray(usernames) || usernames.length === 0) return;
      this.loading = true;
      this.loadingText = 'Deleting users…';
      let anyFail = false;
      for (const uname of usernames) {
        const u = this.users.find(x => x.username === uname);
        if (u && (u.total_buckets || 0) > 0) {
          anyFail = true;
          continue;
        }
        try {
          const res = await this.apiCall('modules/addons/cloudstorage/api/managedusers.php', {
            action: 'deletetenant',
            username: uname
          });
          if (!res || res.status !== 'success') {
            anyFail = true;
          } else {
            this.users = this.users.filter(x => x.username !== uname);
            this.selectedUsernames = this.selectedUsernames.filter(x => x !== uname);
            if (this.panel.user && this.panel.user.username === uname) {
              this.closeUser();
            }
          }
        } catch (_) {
          anyFail = true;
        }
      }
      this.loading = false;
      if (anyFail) {
        this.toastError('Some users could not be deleted.');
      } else {
        this.toastSuccess('Selected users deleted.');
      }
    },

    openCreateKey(user) {
      if (!user) return;
      if (user.manage_locked) {
        this.toastError('This user is system managed and cannot be modified.');
        return;
      }
      if (user.is_root) {
        this.requestRollRootKey(user);
        return;
      }
      this.createKeyForm = { username: user.username, description: '', permission: 'full' };
      this.modals.createKey = true;
    },

    requestRollRootKey(user) {
      if (!user) return;
      this.pendingAction = { type: 'rollRootKey', payload: { username: user.username } };
      this.executeRollRootKey();
    },

    executeRollRootKey() {
      this.loading = true;
      this.loadingText = 'Rotating access key…';
      this.apiCall('modules/addons/cloudstorage/api/rollkey.php', {})
        .then(res => {
          if (res.status === 'success') {
            const hint = res.data?.access_key_hint || '';
            const now = new Date().toISOString();
            if (this.panel.user && this.panel.user.is_root) {
              this.panel.user.root_access_key_hint = hint;
              this.panel.user.root_access_key_created_at = now;
              this.panel.user.has_root_key = !!hint;
              this.panel.user.access_keys = hint ? [{ key_id: 'root', access_key_hint: hint, created_at: now }] : [];
            }
            this.pendingAction = null;
            this.toastSuccess(res.message || 'Access key updated.');
          } else if ((res.message || '').toLowerCase().includes('verify your password')) {
            this.pendingAction = { type: 'rollRootKey', payload: {} };
            this.openPassword();
          } else {
            this.pendingAction = null;
            this.toastError(res.message || 'Failed to update access key.');
          }
        })
        .catch(() => {
          this.toastError('Failed to update access key.');
        })
        .finally(() => {
          this.loading = false;
        });
    },

    submitCreateKey() {
      const u = this.panel.user;
      if (!u) return;
      const description = (this.createKeyForm.description || '').trim();
      const permission = this.createKeyForm.permission || 'full';
      this.loading = true;
      this.loadingText = 'Creating access key…';
      this.apiCall('modules/addons/cloudstorage/api/tenant_access_keys.php', {
        action: 'create',
        username: u.username,
        description,
        permission
      }).then(res => {
        if (res.status === 'success') {
          const d = res.data || {};
          // Update list (do not store secret in state beyond modal)
          u.access_keys = u.access_keys || [];
          u.access_keys.unshift({
            key_id: d.key_id,
            access_key_hint: d.access_key_hint,
            description: d.description,
            permission: d.permission,
            created_at: d.created_at
          });
          this.modals.createKey = false;
          this.newKey = { access_key: d.access_key || '', secret_key: d.secret_key || '' };
          this.modals.showKey = true;
          this.toastSuccess(res.message || 'Access key created.');
        } else if ((res.message || '').toLowerCase().includes('verify your password')) {
          this.pendingAction = { type: 'createKey', payload: { username: u.username, description, permission } };
          this.openPassword();
        } else {
          this.toastError(res.message || 'Failed to create key.');
        }
      }).catch(() => {
        this.toastError('Failed to create key.');
      }).finally(() => {
        this.loading = false;
      });
    },

    confirmDeleteKey(user, key) {
      if (user?.manage_locked) {
        this.toastError('This user is system managed and cannot be modified.');
        return;
      }
      this.deleteTarget = { user, key };
      this.modals.confirmDelete = true;
    },

    requestDeleteKey() {
      const u = this.deleteTarget.user;
      const k = this.deleteTarget.key;
      if (!u || !k) return;
      this.modals.confirmDelete = false;
      // Always require password verification for delete
      this.pendingAction = { type: 'deleteKey', payload: { username: u.username, key_id: k.key_id } };
      this.openPassword();
    },

    executeDeleteKey(u, keyId) {
      this.loading = true;
      this.loadingText = 'Deleting access key…';
      this.apiCall('modules/addons/cloudstorage/api/tenant_access_keys.php', {
        action: 'delete',
        username: u.username,
        key_id: keyId
      }).then(res => {
        if (res.status === 'success') {
          u.access_keys = (u.access_keys || []).filter(x => String(x.key_id) !== String(keyId));
          this.toastSuccess(res.message || 'Access key deleted.');
        } else {
          this.toastError(res.message || 'Failed to delete key.');
        }
      }).catch(() => {
        this.toastError('Failed to delete key.');
      }).finally(() => {
        this.loading = false;
      });
    },

    openPassword() {
      this.passwordForm = { password: '' };
      this.passwordError = '';
      this.modals.password = true;
    },

    submitPassword() {
      const pw = (this.passwordForm.password || '').trim();
      if (!pw) {
        this.passwordError = 'Please enter your password.';
        return;
      }
      this.loading = true;
      this.loadingText = 'Verifying password…';
      this.apiCall('modules/addons/cloudstorage/api/validatepassword.php', { password: pw })
        .then(res => {
          if (res.status === 'success') {
            this.modals.password = false;
            const pending = this.pendingAction;
            this.pendingAction = null;
            if (pending && pending.type === 'createKey') {
              // Retry create key
              const u = this.panel.user;
              if (u && pending.payload) {
                this.createKeyForm = { username: u.username, description: pending.payload.description || '', permission: pending.payload.permission || 'full' };
                this.submitCreateKey();
              }
            } else if (pending && pending.type === 'deleteKey') {
              const u = this.panel.user;
              if (u && pending.payload) {
                const keyId = pending.payload.key_id;
                this.executeDeleteKey(u, keyId);
              }
            } else if (pending && pending.type === 'deleteUsers') {
              const usernames = pending.payload?.usernames || [];
              this.executeDeleteSelectedUsers(usernames);
            } else if (pending && pending.type === 'rollRootKey') {
              this.executeRollRootKey();
            }
          } else {
            this.passwordError = res.message || 'Password verification failed.';
          }
        })
        .catch(() => {
          this.passwordError = 'Password verification failed.';
        })
        .finally(() => {
          this.loading = false;
        });
    },

    permissionLabel(p) {
      if (p === 'readwrite') return 'Read/Write';
      if (p === 'read') return 'Read';
      if (p === 'write') return 'Write';
      return 'Full';
    },

    formatDate(v) {
      if (!v) return '—';
      const s = String(v);
      if (s.startsWith('0000-00-00')) return '—';
      try {
        const d = new Date(v);
        if (isNaN(d.getTime())) return String(v);
        return d.toLocaleString();
      } catch (_) {
        return String(v);
      }
    },

    apiCall(url, data) {
      const formData = new URLSearchParams();
      Object.keys(data || {}).forEach(k => formData.append(k, data[k]));
      return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
      }).then(r => r.json());
    },

    copyText(text) {
      const t = (text || '').toString();
      if (!t) return;
      navigator.clipboard.writeText(t).then(() => {
        this.toastSuccess('Copied to clipboard.');
      }).catch(() => {
        this.toastError('Failed to copy.');
      });
    }
  };
}
</script>
{/literal}


