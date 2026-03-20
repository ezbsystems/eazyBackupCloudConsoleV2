{* Device Groups: Manage Groups slide-over drawer *}

<div id="ebdg-drawer-root" x-data x-cloak>
<template x-if="$store && $store.ebDeviceGroups">
  <div>
    <div x-show="$store.ebDeviceGroups && $store.ebDeviceGroups.drawerOpen"
         x-transition.opacity
         class="eb-drawer-backdrop fixed inset-0 z-[10050]"
         @click="$store.ebDeviceGroups.closeDrawer()"
         aria-hidden="true"></div>

    <div x-show="$store.ebDeviceGroups && $store.ebDeviceGroups.drawerOpen"
         class="eb-drawer eb-drawer--wide fixed inset-y-0 right-0 z-[10060] max-w-full transform transition ease-out duration-200"
         :class="($store.ebDeviceGroups && $store.ebDeviceGroups.drawerOpen) ? 'translate-x-0' : 'translate-x-full'">

      <div class="eb-drawer-header">
        <div class="min-w-0">
          <div class="eb-drawer-title">Manage Groups</div>
          <div class="eb-modal-subtitle">Organize devices by client or company using the shared grouping workflow.</div>
        </div>
        <button type="button"
                class="eb-modal-close"
                @click="$store.ebDeviceGroups.closeDrawer()"
                aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <div class="eb-drawer-body space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
          <button type="button" class="eb-btn eb-btn-success" @click="$store.ebDeviceGroups.startCreate()">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            New Group
          </button>
          <div class="flex-1">
            <input type="text" x-model="$store.ebDeviceGroups.search" placeholder="Search groups..." class="eb-input" />
          </div>
        </div>

        <div x-show="$store.ebDeviceGroups.creating" x-transition class="eb-subpanel">
          <div class="eb-section-intro !mb-3">
            <h3 class="eb-section-title">Create group</h3>
            <p class="eb-section-description">Names must be unique. Devices can belong to one group or remain ungrouped.</p>
          </div>
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <input id="ebdg-new-name"
                   type="text"
                   x-model="$store.ebDeviceGroups.newName"
                   placeholder="Group name (required)"
                   @keydown.enter.prevent="$store.ebDeviceGroups.create()"
                   @keydown.escape.prevent="$store.ebDeviceGroups.cancelCreate()"
                   class="eb-input flex-1" />
            <button type="button"
                    class="eb-btn eb-btn-success"
                    :disabled="$store.ebDeviceGroups.savingCreate"
                    @click="$store.ebDeviceGroups.create()">
              Create
            </button>
            <button type="button" class="eb-btn eb-btn-ghost" @click="$store.ebDeviceGroups.cancelCreate()">
              Cancel
            </button>
          </div>
        </div>

        <div x-show="$store.ebDeviceGroups.loading" class="space-y-2">
          <div class="h-10 rounded-lg border border-[var(--eb-border-default)] bg-[var(--eb-bg-overlay)] animate-pulse"></div>
          <div class="h-10 rounded-lg border border-[var(--eb-border-default)] bg-[var(--eb-bg-overlay)] animate-pulse"></div>
          <div class="h-10 rounded-lg border border-[var(--eb-border-default)] bg-[var(--eb-bg-overlay)] animate-pulse"></div>
        </div>

        <div x-show="!$store.ebDeviceGroups.loading && ($store.ebDeviceGroups.groups || []).length === 0"
             x-transition
             class="eb-app-empty py-10">
          <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl border border-[var(--eb-border-default)] bg-[var(--eb-bg-overlay)] text-[var(--eb-text-muted)]">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
            </svg>
          </div>
          <div class="eb-app-empty-title mt-4">No groups yet</div>
          <div class="eb-app-empty-copy">Create groups to organise devices by client or company.</div>
          <button type="button" class="eb-btn eb-btn-success mt-5" @click="$store.ebDeviceGroups.startCreate()">
            Create your first group
          </button>
          <div class="eb-field-help mt-4">You can assign devices inline, in bulk, or by dragging devices into groups.</div>
        </div>

        <div x-show="!$store.ebDeviceGroups.loading && ($store.ebDeviceGroups.groups || []).length > 0" class="space-y-2">
          <template x-for="g in $store.ebDeviceGroups.filteredGroups()" :key="g.id">
            <div class="eb-card"
                 :class="$store.ebDeviceGroups.dragId && ($store.ebDeviceGroups.dragId === g.id) ? 'ring-1 ring-sky-500/40' : ''"
                 draggable="true"
                 @dragstart="$store.ebDeviceGroups.dragStart(g.id)"
                 @dragend="$store.ebDeviceGroups.dragEnd()"
                 @dragover.prevent
                 @drop.prevent="$store.ebDeviceGroups.dropBefore(g.id)">
              <div class="flex items-center gap-2">
                <div class="cursor-grab select-none text-[var(--eb-text-disabled)]" title="Drag to reorder">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <circle cx="8" cy="7" r="1.5"/><circle cx="16" cy="7" r="1.5"/>
                    <circle cx="8" cy="12" r="1.5"/><circle cx="16" cy="12" r="1.5"/>
                    <circle cx="8" cy="17" r="1.5"/><circle cx="16" cy="17" r="1.5"/>
                  </svg>
                </div>

                <div class="min-w-0 flex-1">
                  <template x-if="$store.ebDeviceGroups.renameId !== g.id">
                    <div class="flex items-center justify-between gap-2">
                      <div class="truncate font-medium text-[var(--eb-text-primary)]"
                           @dblclick="$store.ebDeviceGroups.startRename(g.id)"
                           x-text="g.name"></div>
                      <div class="text-xs tabular-nums text-[var(--eb-text-muted)]" x-text="(g.count || 0) + ' device(s)'"></div>
                    </div>
                  </template>
                  <template x-if="$store.ebDeviceGroups.renameId === g.id">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                      <input :id="'ebdg-rename-input-' + g.id"
                             type="text"
                             x-model="$store.ebDeviceGroups.renameValue"
                             @keydown.enter.prevent="$store.ebDeviceGroups.commitRename()"
                             @keydown.escape.prevent="$store.ebDeviceGroups.cancelRename()"
                             class="eb-input flex-1" />
                      <button type="button"
                              class="eb-btn eb-btn-secondary"
                              :disabled="$store.ebDeviceGroups.savingRename"
                              @click="$store.ebDeviceGroups.commitRename()">
                        Save
                      </button>
                      <button type="button" class="eb-btn eb-btn-ghost" @click="$store.ebDeviceGroups.cancelRename()">
                        Cancel
                      </button>
                    </div>
                  </template>
                </div>

                <div class="relative" x-data="{ open:false }" @click.away="open=false">
                  <button type="button" class="eb-btn eb-btn-icon" @click="open=!open" aria-label="Group actions">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM12 12.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM12 18.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z"/>
                    </svg>
                  </button>
                  <div x-show="open" x-transition class="eb-menu absolute right-0 mt-2 w-44 z-10">
                    <button type="button" class="eb-menu-item" @click="open=false; $store.ebDeviceGroups.startRename(g.id)">
                      Rename
                    </button>
                    <button type="button" class="eb-menu-item is-danger" @click="open=false; $store.ebDeviceGroups.promptDelete(g.id)">
                      Delete
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>

      <div class="eb-drawer-footer text-xs text-[var(--eb-text-muted)]">
        Devices without a group appear under <span class="font-medium text-[var(--eb-text-secondary)]">Ungrouped</span>.
      </div>

      <div x-show="$store.ebDeviceGroups.deleteId" x-cloak class="absolute inset-0 flex items-center justify-center p-4 eb-modal-backdrop">
        <div class="eb-modal eb-modal--confirm">
          <div class="eb-modal-header">
            <div>
              <div class="eb-modal-title">Delete group</div>
              <div class="eb-modal-subtitle">
                Delete group "<span class="font-medium text-[var(--eb-text-primary)]" x-text="$store.ebDeviceGroups.deleteName"></span>"?
              </div>
            </div>
          </div>
          <div class="eb-modal-body space-y-3 text-sm text-[var(--eb-text-secondary)]">
            <div>Devices in this group will be moved to <span class="font-medium text-[var(--eb-text-primary)]">Ungrouped</span>.</div>
            <template x-if="$store.ebDeviceGroups.deleteCount > 0">
              <div class="eb-alert eb-alert--warning">
                This group contains <span class="font-semibold" x-text="$store.ebDeviceGroups.deleteCount"></span> device(s).
              </div>
            </template>
          </div>
          <div class="eb-modal-footer">
            <button type="button" class="eb-btn eb-btn-ghost" @click="$store.ebDeviceGroups.cancelDelete()">Cancel</button>
            <button type="button"
                    class="eb-btn eb-btn-danger-solid"
                    :disabled="$store.ebDeviceGroups.deleting"
                    @click="$store.ebDeviceGroups.confirmDelete()">
              Delete
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
</div>
