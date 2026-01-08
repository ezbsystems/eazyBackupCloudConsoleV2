{* Device Groups: Manage Groups slide-over drawer (Phase 1) *}

<div id="ebdg-drawer-root" x-data x-cloak>
<template x-if="$store && $store.ebDeviceGroups">
  <div>
    <!-- Backdrop -->
    <div x-show="$store.ebDeviceGroups && $store.ebDeviceGroups.drawerOpen"
         x-transition.opacity
         class="fixed inset-0 z-[10050] bg-black/60"
         @click="$store.ebDeviceGroups.closeDrawer()"
         aria-hidden="true"></div>

    <!-- Drawer -->
    <div x-show="$store.ebDeviceGroups && $store.ebDeviceGroups.drawerOpen"
         class="fixed inset-y-0 right-0 z-[10060] w-full sm:max-w-[520px] bg-slate-950/95 border-l border-slate-800 shadow-2xl transform transition ease-out duration-200"
         :class="($store.ebDeviceGroups && $store.ebDeviceGroups.drawerOpen) ? 'translate-x-0' : 'translate-x-full'">

      <div class="h-full flex flex-col">
        <!-- Header -->
        <div class="px-5 py-4 border-b border-slate-800">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="text-lg font-semibold text-slate-100">Manage Groups</div>
              <div class="text-xs text-slate-400 mt-0.5">Organize devices by client/company</div>
            </div>
            <div class="flex items-center gap-2">
              <button type="button"
                      class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-800 bg-slate-900/40 text-slate-300 hover:bg-slate-900/70 hover:text-white transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50"
                      title="One group per device. Deleting a group moves devices to Ungrouped."
                      aria-label="Help">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25h1.5v5.25h-1.5V11.25ZM12 8.25h.008v.008H12V8.25Z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
              </button>
              <button type="button"
                      class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-800 bg-slate-900/40 text-slate-300 hover:bg-slate-900/70 hover:text-white transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50"
                      @click="$store.ebDeviceGroups.closeDrawer()"
                      aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Primary actions -->
        <div class="px-5 py-4 border-b border-slate-800">
          <div class="flex items-center gap-2">
            <button type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold shadow-sm ring-1 ring-emerald-500/40 bg-gradient-to-r from-emerald-500 via-emerald-400 to-sky-400 text-slate-950 transition transform hover:-translate-y-px hover:shadow-lg active:translate-y-0 active:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/60"
                    @click="$store.ebDeviceGroups.startCreate()">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
              </svg>
              New Group
            </button>

            <div class="flex-1">
              <input type="text"
                     x-model="$store.ebDeviceGroups.search"
                     placeholder="Search groups…"
                     class="w-full px-3 py-2 rounded-lg border border-slate-800 bg-slate-900/50 text-slate-200 text-sm focus:outline-none focus:ring-0 focus:border-sky-600" />
            </div>
          </div>

          <!-- Inline create form -->
          <div x-show="$store.ebDeviceGroups.creating" x-transition class="mt-3 rounded-xl border border-slate-800 bg-slate-900/40 p-3">
            <div class="text-xs font-medium text-slate-300 mb-2">Create group</div>
            <div class="flex items-center gap-2">
              <input id="ebdg-new-name"
                     type="text"
                     x-model="$store.ebDeviceGroups.newName"
                     placeholder="Group name (required)"
                     @keydown.enter.prevent="$store.ebDeviceGroups.create()"
                     @keydown.escape.prevent="$store.ebDeviceGroups.cancelCreate()"
                     class="flex-1 px-3 py-2 rounded-lg border border-slate-800 bg-slate-900/60 text-slate-200 text-sm focus:outline-none focus:ring-0 focus:border-sky-600" />
              <button type="button"
                      class="px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-100 text-sm disabled:opacity-60 disabled:cursor-not-allowed"
                      :disabled="$store.ebDeviceGroups.savingCreate"
                      @click="$store.ebDeviceGroups.create()">
                Create
              </button>
              <button type="button"
                      class="px-3 py-2 rounded-lg border border-slate-800 bg-transparent hover:bg-slate-900/60 text-slate-200 text-sm"
                      @click="$store.ebDeviceGroups.cancelCreate()">
                Cancel
              </button>
            </div>
            <div class="mt-2 text-[11px] text-slate-500">Names must be unique (case-insensitive). Devices can belong to one group or Ungrouped.</div>
          </div>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto px-5 py-4">
          <!-- Loading -->
          <div x-show="$store.ebDeviceGroups.loading" class="space-y-2">
            <div class="h-10 rounded-lg border border-slate-800 bg-slate-900/40 animate-pulse"></div>
            <div class="h-10 rounded-lg border border-slate-800 bg-slate-900/40 animate-pulse"></div>
            <div class="h-10 rounded-lg border border-slate-800 bg-slate-900/40 animate-pulse"></div>
          </div>

          <!-- Empty state -->
          <div x-show="!$store.ebDeviceGroups.loading && ($store.ebDeviceGroups.groups || []).length === 0"
               x-transition
               class="py-10 text-center">
            <div class="mx-auto w-12 h-12 rounded-2xl border border-slate-800 bg-slate-900/40 flex items-center justify-center text-slate-300">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
              </svg>
            </div>
            <div class="mt-4 text-slate-100 font-semibold">No groups yet</div>
            <div class="mt-1 text-sm text-slate-400">Create groups to organise devices by client or company.</div>
            <button type="button"
                    class="mt-5 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold shadow-sm ring-1 ring-emerald-500/40 bg-gradient-to-r from-emerald-500 via-emerald-400 to-sky-400 text-slate-950 transition"
                    @click="$store.ebDeviceGroups.startCreate()">
              Create your first group
            </button>
            <div class="mt-4 text-xs text-slate-500">You can assign devices inline, in bulk, or by dragging devices into groups.</div>
          </div>

          <!-- Group list -->
          <div x-show="!$store.ebDeviceGroups.loading && ($store.ebDeviceGroups.groups || []).length > 0" class="space-y-2">
            <template x-for="g in $store.ebDeviceGroups.filteredGroups()" :key="g.id">
              <div class="rounded-xl border border-slate-800 bg-slate-900/30 hover:bg-slate-900/45 transition"
                   :class="$store.ebDeviceGroups.dragId && ($store.ebDeviceGroups.dragId === g.id) ? 'ring-1 ring-sky-500/40' : ''"
                   draggable="true"
                   @dragstart="$store.ebDeviceGroups.dragStart(g.id)"
                   @dragend="$store.ebDeviceGroups.dragEnd()"
                   @dragover.prevent
                   @drop.prevent="$store.ebDeviceGroups.dropBefore(g.id)">
                <div class="px-3 py-2 flex items-center gap-2">
                  <!-- Drag handle -->
                  <div class="text-slate-500 cursor-grab select-none" title="Drag to reorder">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                      <circle cx="8" cy="7" r="1.5"/><circle cx="16" cy="7" r="1.5"/>
                      <circle cx="8" cy="12" r="1.5"/><circle cx="16" cy="12" r="1.5"/>
                      <circle cx="8" cy="17" r="1.5"/><circle cx="16" cy="17" r="1.5"/>
                    </svg>
                  </div>

                  <!-- Name / rename -->
                  <div class="flex-1 min-w-0">
                    <template x-if="$store.ebDeviceGroups.renameId !== g.id">
                      <div class="flex items-center justify-between gap-2">
                        <div class="truncate text-slate-100 font-medium"
                             @dblclick="$store.ebDeviceGroups.startRename(g.id)"
                             x-text="g.name"></div>
                        <div class="text-xs text-slate-400 tabular-nums" x-text="(g.count || 0) + ' device(s)'"></div>
                      </div>
                    </template>
                    <template x-if="$store.ebDeviceGroups.renameId === g.id">
                      <div class="flex items-center gap-2">
                        <input :id="'ebdg-rename-input-' + g.id"
                               type="text"
                               x-model="$store.ebDeviceGroups.renameValue"
                               @keydown.enter.prevent="$store.ebDeviceGroups.commitRename()"
                               @keydown.escape.prevent="$store.ebDeviceGroups.cancelRename()"
                               class="flex-1 px-3 py-2 rounded-lg border border-slate-700 bg-slate-900/60 text-slate-200 text-sm focus:outline-none focus:ring-0 focus:border-sky-600" />
                        <button type="button"
                                class="px-2.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-100 text-sm disabled:opacity-60 disabled:cursor-not-allowed"
                                :disabled="$store.ebDeviceGroups.savingRename"
                                @click="$store.ebDeviceGroups.commitRename()">
                          Save
                        </button>
                        <button type="button"
                                class="px-2.5 py-2 rounded-lg border border-slate-800 bg-transparent hover:bg-slate-900/60 text-slate-200 text-sm"
                                @click="$store.ebDeviceGroups.cancelRename()">
                          Cancel
                        </button>
                      </div>
                    </template>
                  </div>

                  <!-- Actions -->
                  <div class="relative" x-data="{ open:false }" @click.away="open=false">
                    <button type="button"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-800 bg-slate-900/40 text-slate-300 hover:bg-slate-900/70 hover:text-white transition"
                            @click="open=!open"
                            aria-label="Group actions">
                      <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM12 12.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM12 18.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z"/>
                      </svg>
                    </button>
                    <div x-show="open" x-transition class="absolute right-0 mt-2 w-44 rounded-lg border border-slate-800 bg-slate-950 shadow-xl overflow-hidden z-10">
                      <button type="button" class="w-full text-left px-3 py-2 text-sm text-slate-200 hover:bg-slate-900/60"
                              @click="open=false; $store.ebDeviceGroups.startRename(g.id)">
                        Rename
                      </button>
                      <button type="button" class="w-full text-left px-3 py-2 text-sm text-rose-200 hover:bg-rose-500/10"
                              @click="open=false; $store.ebDeviceGroups.promptDelete(g.id)">
                        Delete
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>

        <!-- Footer note -->
        <div class="px-5 py-3 border-t border-slate-800 text-xs text-slate-500">
          Devices without a group appear under <span class="text-slate-300">Ungrouped</span>.
        </div>
      </div>

      <!-- Delete confirmation overlay (inside drawer) -->
      <div x-show="$store.ebDeviceGroups.deleteId" x-cloak class="absolute inset-0 bg-black/50 flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-xl border border-slate-800 bg-slate-950 shadow-2xl">
          <div class="px-5 py-4 border-b border-slate-800">
            <div class="text-slate-100 font-semibold">Delete group</div>
            <div class="mt-1 text-sm text-slate-400">
              Delete group "<span class="text-slate-200 font-medium" x-text="$store.ebDeviceGroups.deleteName"></span>"?
            </div>
          </div>
          <div class="px-5 py-4 text-sm text-slate-300 space-y-2">
            <div>
              Devices in this group will be moved to <span class="text-slate-200 font-medium">Ungrouped</span>.
            </div>
            <template x-if="$store.ebDeviceGroups.deleteCount > 0">
              <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-amber-200">
                This group contains <span class="font-semibold" x-text="$store.ebDeviceGroups.deleteCount"></span> device(s).
              </div>
            </template>
          </div>
          <div class="px-5 py-3 border-t border-slate-800 flex justify-end gap-2">
            <button type="button"
                    class="px-4 py-2 rounded-lg border border-slate-800 bg-transparent hover:bg-slate-900/60 text-slate-200"
                    @click="$store.ebDeviceGroups.cancelDelete()">
              Cancel
            </button>
            <button type="button"
                    class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white disabled:opacity-60 disabled:cursor-not-allowed"
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
