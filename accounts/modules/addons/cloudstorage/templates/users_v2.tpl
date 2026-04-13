<!-- ebLoader -->
<script src="{$WEB_ROOT}/modules/addons/eazybackup/templates/assets/js/ui.js"></script>

<div class="eb-page overflow-x-hidden" x-data="usersV2Manager()" x-init="init()">
  {* <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div> *}
  <div class="eb-page-inner relative max-w-full pb-10">

    <div class="eb-panel">
      <div class="eb-panel-nav">
        {include file="modules/addons/cloudstorage/templates/partials/core_nav.tpl" cloudstorageActivePage='users'}
      </div>

      <div class="eb-page-header">
        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-3">
          <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange shrink-0" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
            </svg>
          </span>
          <div class="min-w-0">
            <h1 class="eb-page-title">Users</h1>
          </div>
          <span class="eb-badge eb-badge--neutral shrink-0" x-text="filteredUsers.length + ' users'"></span>
        </div>

        <form class="contents" autocomplete="off" onsubmit="return false;" aria-label="User list actions">
          <div class="flex w-full min-w-0 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center lg:w-auto">
          <div class="eb-input-wrap w-full sm:w-48 lg:w-64">
            <div class="eb-input-icon">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
              </svg>
            </div>
            <input id="users-v2-search-filter"
                   type="search"
                   name="cloudstorage_users_filter"
                   autocomplete="search"
                   autocorrect="off"
                   autocapitalize="off"
                   spellcheck="false"
                   x-model="searchTerm"
                   placeholder="Search users..."
                   class="eb-input eb-input-has-icon w-full">
          </div>

          <button type="button" @click="openDeleteSelectedUsers()"
                  :disabled="!canDeleteSelectedUsers"
                  class="eb-btn eb-btn-secondary eb-btn-sm whitespace-nowrap disabled:cursor-not-allowed">
            Delete
          </button>

          <button type="button" @click="openCreateUser()" class="eb-btn eb-btn-success eb-btn-sm inline-flex items-center gap-2 whitespace-nowrap">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Create User
          </button>
        </div>
        </form>
      </div>

      <div class="eb-table-shell">
        <div class="overflow-x-auto">
          <table class="eb-table min-w-full">
            <thead>
              <tr>
                <th class="w-10" @click.stop>
                  <input type="checkbox" class="eb-check-input" :checked="allSelectableChecked" @change="toggleSelectAll($event)">
                </th>
                <th>Username</th>
                <th>Account ID</th>
                <th>Buckets</th>
                <th>Storage</th>
                <th>Access keys</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <template x-for="u in filteredUsers" :key="u.username">
                <tr class="cursor-pointer" @click="openUser(u)">
                  <td @click.stop>
                    <input type="checkbox"
                           class="eb-check-input"
                           :disabled="(u.total_buckets || 0) > 0 || u.is_root || u.manage_locked"
                           :checked="selectedUsernames.includes(u.username)"
                           @change="toggleUserSelection(u, $event)">
                  </td>
                  <td class="eb-table-primary">
                    <span x-text="publicUserLabel(u)"></span>
                  </td>
                  <td><span class="eb-table-mono" x-text="publicAccountIdText(u)"></span></td>
                  <td x-text="u.total_buckets || 0"></td>
                  <td x-html="u.total_storage || '0 B'"></td>
                  <td x-text="(u.access_keys || []).length"></td>
                  <td class="text-right" @click.stop>
                    <template x-if="!u.manage_locked">
                      <button type="button" class="eb-link text-sm" @click="openUser(u)">Manage</button>
                    </template>
                    <template x-if="u.manage_locked">
                      <span class="eb-type-disabled text-xs">Locked</span>
                    </template>
                  </td>
                </tr>
              </template>
              <tr x-show="filteredUsers.length === 0" x-cloak>
                <td colspan="7" class="!p-0">
                  <div class="eb-app-empty">
                    <div class="eb-app-empty-title">No users found</div>
                    <p class="eb-app-empty-copy">Try another search or create a user to get started.</p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="pointer-events-none fixed right-4 top-4 z-[9999]" x-cloak>
    <div x-show="toast.visible" x-transition
         class="pointer-events-auto eb-toast min-w-[260px]"
         :class="{
            'eb-toast--success': toast.type==='success',
            'eb-toast--danger': toast.type==='error',
            'eb-toast--info': toast.type==='info'
         }">
      <span class="min-w-0 flex-1" x-text="toast.message"></span>
      <button type="button" class="eb-btn eb-btn-ghost eb-btn-xs shrink-0 !px-2 !py-1" @click="toast.visible=false" aria-label="Close">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </button>
    </div>
  </div>

  <div x-show="panel.open" x-cloak class="fixed inset-0 z-50 flex justify-end">
    <div class="eb-drawer-backdrop fixed inset-0" @click="closeUser()" aria-hidden="true"></div>
    <div class="eb-drawer eb-drawer--panel relative z-10 h-full w-full !max-w-3xl overflow-y-auto">
      <div class="eb-drawer-header">
        <div class="min-w-0">
          <h2 class="eb-drawer-title truncate" x-text="panel.user ? publicUserLabel(panel.user) : 'User'"></h2>
          <div class="eb-user-meta-line mt-1" x-show="panel.user && !isRootStorageUser(panel.user)" x-cloak>
            <span>Account ID</span>
            <span class="eb-table-mono" x-text="publicAccountIdText(panel.user)"></span>
          </div>
        </div>
        <button type="button" class="eb-modal-close shrink-0" @click="closeUser()" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <div class="eb-drawer-body space-y-4">
        <div class="eb-subpanel !p-4">
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <div class="eb-type-caption">Buckets</div>
              <div class="eb-type-body mt-0.5" x-text="panel.user?.total_buckets || 0"></div>
            </div>
            <div>
              <div class="eb-type-caption">Storage</div>
              <div class="eb-type-body mt-0.5" x-html="panel.user?.total_storage || '0 B'"></div>
            </div>
            <div>
              <div class="eb-type-caption">Access keys</div>
              <div class="eb-type-body mt-0.5" x-text="(panel.user?.access_keys || []).length"></div>
            </div>
          </div>
        </div>

        <div class="eb-subpanel !p-4">
          <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h3 class="eb-type-h4">Access keys</h3>
            <button type="button" class="eb-btn eb-btn-primary eb-btn-xs"
                    :disabled="panel.user?.manage_locked"
                    :class="{ 'pointer-events-none opacity-50': panel.user?.manage_locked }"
                    @click="openCreateKey(panel.user)"
                    x-text="panel.user?.is_root ? (panel.user?.has_root_key ? 'Rotate access key' : 'Create access key') : '+ Create access key'">
            </button>
          </div>

          <div x-show="panel.user?.is_root" x-cloak>
            <div class="eb-table-shell">
              <div class="overflow-x-auto">
                <table class="eb-table min-w-full">
                  <thead>
                    <tr>
                      <th>Key</th>
                      <th>Created</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td class="eb-table-mono" x-text="panel.user?.root_access_key_hint || '—'"></td>
                      <td class="eb-type-caption" x-text="formatDate(panel.user?.root_access_key_created_at)"></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <p class="eb-field-help mt-3">For security, the secret key is shown <span class="eb-meta-strong">only once</span> when you rotate the key.</p>
          </div>

          <div x-show="!panel.user?.is_root" x-cloak>
            <div class="eb-table-shell">
              <div class="overflow-x-auto">
                <table class="eb-table min-w-full">
                  <thead>
                    <tr>
                      <th>Key</th>
                      <th>Description</th>
                      <th>Permission</th>
                      <th>Created</th>
                      <th class="text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <template x-for="k in (panel.user?.access_keys || [])" :key="k.key_id">
                      <tr>
                        <td class="eb-table-mono" x-text="k.access_key_hint || '—'"></td>
                        <td x-text="k.description || '—'"></td>
                        <td x-text="permissionLabel(k.permission)"></td>
                        <td class="eb-type-caption" x-text="formatDate(k.created_at)"></td>
                        <td class="text-right">
                          <button type="button" class="eb-btn eb-btn-danger eb-btn-xs"
                                  :disabled="panel.user?.manage_locked"
                                  :class="{ 'pointer-events-none opacity-50': panel.user?.manage_locked }"
                                  @click="confirmDeleteKey(panel.user, k)">
                            Delete
                          </button>
                        </td>
                      </tr>
                    </template>
                    <tr x-show="(panel.user?.access_keys || []).length === 0" x-cloak>
                      <td colspan="5" class="!p-0">
                        <div class="eb-app-empty !py-6">
                          <p class="eb-app-empty-copy">No access keys yet.</p>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <p class="eb-field-help mt-3">For security, the secret key is shown <span class="eb-meta-strong">only once</span> when you create a key.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div x-show="modals.createUser" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="eb-modal-backdrop fixed inset-0" @click="modals.createUser = false" aria-hidden="true"></div>
    <div class="eb-modal relative z-10 w-full max-w-lg" role="dialog" aria-modal="true" aria-labelledby="users-v2-create-user-title" @click.away="modals.createUser = false">
      <div class="eb-modal-header">
        <div>
          <h2 id="users-v2-create-user-title" class="eb-modal-title">Create User</h2>
        </div>
        <button type="button" class="eb-modal-close" @click="modals.createUser = false" aria-label="Close">&times;</button>
      </div>
      <div class="eb-modal-body">
        <div>
          <label class="eb-field-label" for="users-v2-create-username">Username</label>
          <input id="users-v2-create-username" type="text" name="cloudstorage_new_user_id" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" x-model="createUserForm.username" placeholder="e.g., acme-corp" class="eb-input w-full">
          <p class="eb-field-help">This is the user identifier shown in your dashboard.</p>
        </div>
      </div>
      <div class="eb-modal-footer">
        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="modals.createUser = false">Cancel</button>
        <button type="button" class="eb-btn eb-btn-success eb-btn-sm" @click="submitCreateUser()">Create</button>
      </div>
    </div>
  </div>

  <div x-show="modals.createKey" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="eb-modal-backdrop fixed inset-0" @click="modals.createKey = false" aria-hidden="true"></div>
    <div class="eb-modal relative z-10 w-full max-w-xl" role="dialog" aria-modal="true" aria-labelledby="users-v2-create-key-title" @click.away="modals.createKey = false">
      <div class="eb-modal-header">
        <div>
          <h2 id="users-v2-create-key-title" class="eb-modal-title">Create access key</h2>
        </div>
        <button type="button" class="eb-modal-close" @click="modals.createKey = false" aria-label="Close">&times;</button>
      </div>
      <div class="eb-modal-body space-y-4">
        <p class="eb-type-body">Creating a new key will show the secret key <span class="eb-meta-strong">once</span>. Save it securely.</p>
        <div>
          <label class="eb-field-label" for="users-v2-key-description">Description</label>
          <input id="users-v2-key-description" type="text" name="cloudstorage_key_description" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" x-model="createKeyForm.description" placeholder="e.g., backup server" class="eb-input w-full">
        </div>
        <div>
          <span class="eb-field-label">Permission</span>
          <div class="relative mt-1" x-data="{ open:false }">
            <button type="button" class="eb-menu-trigger"
                    @click="open=!open">
              <span x-text="permissionLabel(createKeyForm.permission)"></span>
              <svg class="h-4 w-4 shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
              </svg>
            </button>
            <div x-show="open" x-cloak @click.away="open=false"
                 class="eb-dropdown-menu absolute z-50 mt-2 w-full overflow-hidden p-1">
              <button type="button" class="eb-menu-item !h-auto w-full !flex-col !items-start !gap-1 !py-2 text-left"
                      @click="createKeyForm.permission='full'; open=false">
                <span class="eb-type-body">Full</span>
                <span class="eb-type-caption mt-0.5">Read + write + list + manage buckets.</span>
              </button>
              <button type="button" class="eb-menu-item !h-auto w-full !flex-col !items-start !gap-1 !py-2 text-left"
                      @click="createKeyForm.permission='read'; open=false">
                <span class="eb-type-body">Read</span>
                <span class="eb-type-caption mt-0.5">Download and list objects.</span>
              </button>
              <button type="button" class="eb-menu-item !h-auto w-full !flex-col !items-start !gap-1 !py-2 text-left"
                      @click="createKeyForm.permission='write'; open=false">
                <span class="eb-type-body">Write</span>
                <span class="eb-type-caption mt-0.5">Upload objects.</span>
              </button>
              <button type="button" class="eb-menu-item !h-auto w-full !flex-col !items-start !gap-1 !py-2 text-left"
                      @click="createKeyForm.permission='readwrite'; open=false">
                <span class="eb-type-body">Read/Write</span>
                <span class="eb-type-caption mt-0.5">Upload + download + list objects.</span>
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="eb-modal-footer">
        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="modals.createKey = false">Cancel</button>
        <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" @click="submitCreateKey()">Create key</button>
      </div>
    </div>
  </div>

  <div x-show="modals.showKey" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="eb-modal-backdrop fixed inset-0" @click="modals.showKey = false" aria-hidden="true"></div>
    <div class="eb-modal relative z-10 w-full max-w-2xl" role="dialog" aria-modal="true" aria-labelledby="users-v2-show-key-title" @click.away="modals.showKey = false">
      <div class="eb-modal-header">
        <div>
          <h2 id="users-v2-show-key-title" class="eb-modal-title">Save your access key</h2>
        </div>
        <button type="button" class="eb-modal-close" @click="modals.showKey = false" aria-label="Close">&times;</button>
      </div>
      <div class="eb-modal-body space-y-4">
        <div class="eb-alert eb-alert--warning" role="status">
          <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
          </svg>
          <div>
            <div class="eb-alert-title">One-time display</div>
            <p>This is the <strong>only</strong> time you can view the secret key. Store it securely.</p>
          </div>
        </div>
        <div>
          <label class="eb-field-label" for="users-v2-new-access-key">Access key</label>
          <div class="flex flex-wrap gap-2">
            <input id="users-v2-new-access-key" type="text" name="cloudstorage_display_access_key" autocomplete="off" readonly :value="newKey.access_key" class="eb-input eb-type-mono min-w-0 flex-1 cursor-default">
            <button type="button" class="eb-btn eb-btn-copy eb-btn-sm shrink-0" @click="copyText(newKey.access_key)">Copy</button>
          </div>
        </div>
        <div>
          <label class="eb-field-label" for="users-v2-new-secret-key">Secret key</label>
          <div class="flex flex-wrap gap-2">
            <input id="users-v2-new-secret-key" type="text" name="cloudstorage_display_secret_key" autocomplete="off" readonly :value="newKey.secret_key" class="eb-input eb-type-mono min-w-0 flex-1 cursor-default">
            <button type="button" class="eb-btn eb-btn-copy eb-btn-sm shrink-0" @click="copyText(newKey.secret_key)">Copy</button>
          </div>
        </div>
      </div>
      <div class="eb-modal-footer">
        <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" @click="modals.showKey = false">Done</button>
      </div>
    </div>
  </div>

  <div x-show="modals.password" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="eb-modal-backdrop fixed inset-0" @click="modals.password = false" aria-hidden="true"></div>
    <div class="eb-modal relative z-10 w-full max-w-md" role="dialog" aria-modal="true" aria-labelledby="users-v2-password-title" @click.away="modals.password = false">
      <div class="eb-modal-header">
        <div>
          <h2 id="users-v2-password-title" class="eb-modal-title">Verify password</h2>
        </div>
        <button type="button" class="eb-modal-close" @click="modals.password = false" aria-label="Close">&times;</button>
      </div>
      <div class="eb-modal-body">
        <p class="eb-type-body mb-3">Enter your account password to continue.</p>
        <div x-show="passwordError" x-cloak class="eb-alert eb-alert--danger mb-3" role="alert">
          <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
          </svg>
          <div x-text="passwordError"></div>
        </div>
        <label class="eb-field-label" for="users-v2-account-pw">Account password</label>
        <input id="users-v2-account-pw" type="password" name="cloudstorage_account_verify_pw" autocomplete="current-password" x-model="passwordForm.password" placeholder="Account password" class="eb-input w-full">
      </div>
      <div class="eb-modal-footer">
        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="modals.password = false">Cancel</button>
        <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" @click="submitPassword()">Verify</button>
      </div>
    </div>
  </div>

  <div x-show="modals.confirmDelete" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="eb-modal-backdrop fixed inset-0" @click="modals.confirmDelete = false" aria-hidden="true"></div>
    <div class="eb-modal eb-modal--confirm relative z-10 w-full" role="dialog" aria-modal="true" aria-labelledby="users-v2-confirm-delete-key-title" @click.away="modals.confirmDelete = false">
      <div class="eb-modal-header">
        <div class="flex min-w-0 items-start gap-3">
          <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange shrink-0" aria-hidden="true">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.35 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
          </span>
          <h2 id="users-v2-confirm-delete-key-title" class="eb-modal-title">Delete access key</h2>
        </div>
        <button type="button" class="eb-modal-close" @click="modals.confirmDelete = false" aria-label="Close">&times;</button>
      </div>
      <div class="eb-modal-body">
        <p class="eb-type-body">
          Delete key <span class="eb-table-mono" x-text="deleteTarget.key?.access_key_hint || ''"></span>?
          This may break apps using it.
        </p>
      </div>
      <div class="eb-modal-footer">
        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="modals.confirmDelete = false">Cancel</button>
        <button type="button" class="eb-btn eb-btn-danger-solid eb-btn-sm" @click="requestDeleteKey()">Delete</button>
      </div>
    </div>
  </div>

  <div x-show="modals.confirmDeleteUsers" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="eb-modal-backdrop fixed inset-0" @click="modals.confirmDeleteUsers = false" aria-hidden="true"></div>
    <div class="eb-modal eb-modal--confirm relative z-10 w-full" role="dialog" aria-modal="true" aria-labelledby="users-v2-confirm-delete-users-title" @click.away="modals.confirmDeleteUsers = false">
      <div class="eb-modal-header">
        <div class="flex min-w-0 items-start gap-3">
          <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange shrink-0" aria-hidden="true">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.35 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
          </span>
          <h2 id="users-v2-confirm-delete-users-title" class="eb-modal-title">Delete users</h2>
        </div>
        <button type="button" class="eb-modal-close" @click="modals.confirmDeleteUsers = false" aria-label="Close">&times;</button>
      </div>
      <div class="eb-modal-body">
        <p class="eb-type-body">
          Delete <span class="eb-meta-strong" x-text="selectedUsernames.length"></span> selected users?
          This cannot be undone.
        </p>
      </div>
      <div class="eb-modal-footer">
        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="modals.confirmDeleteUsers = false">Cancel</button>
        <button type="button" class="eb-btn eb-btn-danger-solid eb-btn-sm" @click="requestDeleteSelectedUsers()">Delete</button>
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

    isRootStorageUser(u) {
      return !!(u && (u.is_root || u.is_system_managed));
    },
    publicUserLabel(u) {
      if (this.isRootStorageUser(u)) return 'Root user';
      return (u.display_name || u.username || '');
    },
    publicAccountIdText(u) {
      if (!u || this.isRootStorageUser(u)) return '—';
      return u.tenant_id != null && u.tenant_id !== '' ? String(u.tenant_id) : '—';
    },

    get filteredUsers() {
      const term = (this.searchTerm || '').trim().toLowerCase();
      if (!term) return this.users;
      return this.users.filter(u => {
        const name = (u.username || '').toLowerCase();
        const label = (u.display_name || '').toLowerCase();
        const publicLabel = (this.publicUserLabel(u) || '').toLowerCase();
        return name.includes(term) || label.includes(term) || publicLabel.includes(term);
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


