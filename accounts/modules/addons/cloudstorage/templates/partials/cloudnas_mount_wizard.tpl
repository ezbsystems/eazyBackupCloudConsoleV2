{* Cloud NAS - Mount Wizard Modal *}

<div x-show="showMountWizard" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4"
     @keydown.escape.window="showMountWizard = false">
    <div class="eb-modal-backdrop absolute inset-0" @click="showMountWizard = false"></div>

    <div class="eb-modal relative z-10 w-full max-w-lg !overflow-visible !p-0 flex flex-col max-h-[min(90vh,720px)]"
         @click.stop
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95">

        {* Header *}
        <div class="eb-modal-header !mb-0 shrink-0">
            <div class="flex min-w-0 items-center gap-2">
                <span class="eb-icon-box eb-icon-box--sm eb-icon-box--default shrink-0" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z" />
                    </svg>
                </span>
                <h2 class="eb-modal-title" x-text="newMount.id ? 'Edit Mount' : 'Mount Cloud Drive'"></h2>
            </div>
            <button type="button" class="eb-modal-close" @click="showMountWizard = false" aria-label="Close">&times;</button>
        </div>

        {* Steps Indicator *}
        <div class="shrink-0 border-b border-[var(--eb-border-subtle)] bg-[var(--eb-bg-chrome)] px-6 py-4">
            <div class="flex items-center justify-between">
                <template x-for="(step, i) in ['Select Bucket', 'Configure', 'Review']" :key="i">
                    <div class="flex items-center">
                        <div class="flex items-center">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold transition"
                                 :class="wizardStep > i ? 'bg-[var(--eb-primary)] text-[var(--eb-text-inverse)]' : wizardStep === i ? 'bg-[var(--eb-primary-soft)] text-[var(--eb-primary)] ring-2 ring-[var(--eb-border-orange)]' : 'bg-[var(--eb-bg-card)] text-[var(--eb-text-muted)]'">
                                <template x-if="wizardStep > i">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </template>
                                <template x-if="wizardStep <= i">
                                    <span x-text="i + 1"></span>
                                </template>
                            </div>
                            <span class="ml-2 hidden text-xs sm:inline"
                                  :class="wizardStep >= i ? 'text-[var(--eb-text-primary)]' : 'text-[var(--eb-text-muted)]'"
                                  x-text="step"></span>
                        </div>
                        <template x-if="i < 2">
                            <div class="mx-2 h-px w-8 sm:w-12"
                                 :class="wizardStep > i ? 'bg-[var(--eb-primary)]' : 'bg-[var(--eb-border-default)]'"></div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {* Step Content (overflow visible so teleported menus are not clipped; inner block scrolls) *}
        <div class="eb-modal-body min-h-0 max-h-[60vh] flex-1 !overflow-visible !pt-6">
            <div class="max-h-[min(60vh,28rem)] overflow-y-auto pr-1">

            {* Step 1: Select Bucket *}
            <div x-show="wizardStep === 0">
                {* Agent selector *}
                <div class="mb-5">
                    <label class="eb-field-label">Select Agent</label>
                    <div x-data="{
                            agentOpen: false,
                            menuTop: 0,
                            menuLeft: 0,
                            menuWidth: 0,
                            syncAgentMenuPos() {
                                const el = this.$refs.agentTrigger;
                                if (!el) return;
                                const r = el.getBoundingClientRect();
                                this.menuTop = r.bottom + 8;
                                this.menuLeft = r.left;
                                this.menuWidth = r.width;
                            },
                            toggleAgentMenu() {
                                this.agentOpen = !this.agentOpen;
                                if (this.agentOpen) this.$nextTick(() => this.syncAgentMenuPos());
                            }
                        }"
                        class="relative">
                        <button type="button"
                                x-ref="agentTrigger"
                                class="eb-menu-trigger w-full"
                                @click="toggleAgentMenu()"
                                aria-haspopup="listbox"
                                :aria-expanded="agentOpen">
                            <span class="min-w-0 truncate text-left"
                                  x-text="newMount.agent_uuid ? agents.find(a => a.agent_uuid == newMount.agent_uuid)?.hostname || newMount.agent_uuid : 'Select an agent...'"
                                  :class="newMount.agent_uuid ? 'text-[var(--eb-text-primary)]' : 'text-[var(--eb-text-muted)]'"></span>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                 class="h-4 w-4 shrink-0 text-[var(--eb-text-muted)] transition-transform" :class="agentOpen && 'rotate-180'">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <template x-teleport="body">
                            <div x-show="showMountWizard && agentOpen"
                                 x-transition
                                 @click.outside="if (!$refs.agentTrigger || !$refs.agentTrigger.contains($event.target)) agentOpen = false"
                                 class="eb-dropdown-menu fixed !max-h-[min(50vh,16rem)] !min-w-0 overflow-y-auto p-1"
                                 style="display: none;"
                                 role="listbox"
                                 :style="{ position: 'fixed', top: menuTop + 'px', left: menuLeft + 'px', width: menuWidth + 'px', minWidth: menuWidth + 'px', zIndex: 200 }">
                                <template x-for="agent in agents" :key="agent.agent_uuid || agent.hostname">
                                    <button type="button"
                                            class="eb-menu-item w-full justify-between !rounded-[var(--eb-radius-md)]"
                                            :class="newMount.agent_uuid == (agent.agent_uuid || '') && 'is-active'"
                                            role="option"
                                            @click="newMount.agent_uuid = agent.agent_uuid || ''; selectedAgentUuid = agent.agent_uuid || ''; agentOpen = false; calculateAvailableDriveLetters().then(() => { if (availableDriveLetters.length) newMount.drive_letter = availableDriveLetters[0]; })">
                                        <div class="flex min-w-0 flex-1 items-center gap-2">
                                            <span class="eb-status-dot shrink-0"
                                                  :class="agent.status === 'active' ? 'eb-status-dot--active' : 'eb-status-dot--inactive'"></span>
                                            <span class="truncate" x-text="agent.hostname || agent.device_name || (agent.agent_uuid || 'Unknown agent')"></span>
                                        </div>
                                        <svg x-show="newMount.agent_uuid == (agent.agent_uuid || '')" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                {* Bucket selector *}
                <div class="mb-5">
                    <label class="eb-field-label">Select Bucket</label>
                    <div x-data="{
                            bucketOpen: false,
                            search: '',
                            menuTop: 0,
                            menuLeft: 0,
                            menuWidth: 0,
                            syncBucketMenuPos() {
                                const el = this.$refs.bucketTrigger;
                                if (!el) return;
                                const r = el.getBoundingClientRect();
                                this.menuTop = r.bottom + 8;
                                this.menuLeft = r.left;
                                this.menuWidth = r.width;
                            },
                            toggleBucketMenu() {
                                this.bucketOpen = !this.bucketOpen;
                                if (this.bucketOpen) this.$nextTick(() => this.syncBucketMenuPos());
                            }
                        }"
                        class="relative">
                        <button type="button"
                                x-ref="bucketTrigger"
                                class="eb-menu-trigger w-full"
                                @click="toggleBucketMenu()"
                                aria-haspopup="listbox"
                                :aria-expanded="bucketOpen">
                            <span class="min-w-0 truncate text-left"
                                  x-text="newMount.bucket || 'Choose a bucket...'"
                                  :class="newMount.bucket ? 'text-[var(--eb-text-primary)]' : 'text-[var(--eb-text-muted)]'"></span>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                 class="h-4 w-4 shrink-0 text-[var(--eb-text-muted)] transition-transform" :class="bucketOpen && 'rotate-180'">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <template x-teleport="body">
                            <div x-show="showMountWizard && bucketOpen"
                                 x-transition
                                 @click.outside="if (!$refs.bucketTrigger || !$refs.bucketTrigger.contains($event.target)) bucketOpen = false"
                                 class="eb-dropdown-menu fixed !min-w-0 !max-w-none flex flex-col overflow-hidden !p-0"
                                 style="display: none;"
                                 role="listbox"
                                 :style="{ position: 'fixed', top: menuTop + 'px', left: menuLeft + 'px', width: menuWidth + 'px', minWidth: menuWidth + 'px', zIndex: 200, maxHeight: 'min(55vh, 22rem)' }">
                                <div class="shrink-0 border-b border-[var(--eb-border-subtle)] p-2">
                                    <input type="text" x-model="search" placeholder="Search buckets..."
                                           class="eb-input !py-2 text-xs" />
                                </div>
                                <div class="max-h-[min(45vh,18rem)] overflow-y-auto p-1">
                                    <template x-if="loadingBuckets">
                                        <div class="px-3 py-4 text-center eb-type-caption text-[var(--eb-text-muted)]">Loading buckets...</div>
                                    </template>
                                    <template x-if="!loadingBuckets && buckets.length === 0">
                                        <div class="px-3 py-4 text-center eb-type-caption text-[var(--eb-text-muted)]">No buckets found</div>
                                    </template>
                                    <template x-for="bucket in buckets.filter(b => !search || b.name.toLowerCase().includes(search.toLowerCase()))" :key="bucket.name">
                                        <button type="button"
                                                class="eb-menu-item w-full justify-between !rounded-[var(--eb-radius-md)]"
                                                :class="newMount.bucket === bucket.name && 'is-active'"
                                                role="option"
                                                @click="newMount.bucket = bucket.name; bucketOpen = false; search = ''">
                                            <div class="flex min-w-0 flex-1 items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0 text-[var(--eb-text-muted)]">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                                </svg>
                                                <span class="truncate" x-text="bucket.name"></span>
                                            </div>
                                            <svg x-show="newMount.bucket === bucket.name" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {* Prefix input *}
                <div>
                    <label class="eb-field-label">Prefix / Subfolder (optional)</label>
                    <input type="text" x-model="newMount.prefix" placeholder="e.g., documents/ or backups/2024/"
                           class="eb-input" />
                    <p class="eb-field-help">Mount only a specific folder within the bucket. Leave empty to mount the entire bucket.</p>
                </div>
            </div>

            {* Step 2: Configure *}
            <div x-show="wizardStep === 1">
                {* Drive letter selector *}
                <div class="mb-5">
                    <label class="eb-field-label">Drive Letter</label>
                    <div x-data="{
                            letterOpen: false,
                            menuTop: 0,
                            menuLeft: 0,
                            menuWidth: 0,
                            syncLetterMenuPos() {
                                const el = this.$refs.letterTrigger;
                                if (!el) return;
                                const r = el.getBoundingClientRect();
                                this.menuTop = r.bottom + 8;
                                this.menuLeft = r.left;
                                this.menuWidth = r.width;
                            },
                            toggleLetterMenu() {
                                this.letterOpen = !this.letterOpen;
                                if (this.letterOpen) this.$nextTick(() => this.syncLetterMenuPos());
                            }
                        }"
                        class="relative">
                        <button type="button"
                                x-ref="letterTrigger"
                                class="eb-menu-trigger w-full"
                                @click="toggleLetterMenu()"
                                aria-haspopup="listbox"
                                :aria-expanded="letterOpen">
                            <span class="font-semibold text-[var(--eb-text-primary)]" x-text="newMount.drive_letter + ':'"></span>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                 class="h-4 w-4 shrink-0 text-[var(--eb-text-muted)] transition-transform" :class="letterOpen && 'rotate-180'">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <template x-teleport="body">
                            <div x-show="showMountWizard && letterOpen"
                                 x-transition
                                 @click.outside="if (!$refs.letterTrigger || !$refs.letterTrigger.contains($event.target)) letterOpen = false"
                                 class="eb-dropdown-menu fixed !max-h-[min(50vh,16rem)] !min-w-0 overflow-y-auto p-2"
                                 style="display: none;"
                                 :style="{ position: 'fixed', top: menuTop + 'px', left: menuLeft + 'px', width: menuWidth + 'px', minWidth: menuWidth + 'px', zIndex: 200 }">
                                <div class="grid grid-cols-6 gap-1">
                                    <template x-for="letter in availableDriveLetters" :key="letter">
                                        <button type="button"
                                                class="eb-btn eb-btn-xs min-w-0"
                                                :class="newMount.drive_letter === letter ? 'eb-btn-primary' : 'eb-btn-secondary'"
                                                @click="newMount.drive_letter = letter; letterOpen = false">
                                            <span x-text="letter + ':'"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                    <p class="eb-field-help">Select which drive letter to use for this mount</p>
                </div>

                {* Options *}
                <div class="space-y-4">
                    <label class="eb-inline-choice cursor-pointer">
                        <input type="checkbox" x-model="newMount.read_only" class="eb-check-input" />
                        <span>
                            <span class="eb-type-body text-[var(--eb-text-primary)]">Read-only mode</span>
                            <span class="mt-0.5 block eb-type-caption">Prevent modifications to files on this drive</span>
                        </span>
                    </label>

                    <label class="eb-inline-choice cursor-pointer">
                        <input type="checkbox" x-model="newMount.persistent" class="eb-check-input" />
                        <span>
                            <span class="eb-type-body text-[var(--eb-text-primary)]">Mount on startup</span>
                            <span class="mt-0.5 block eb-type-caption">Auto-reconnect this drive when Windows starts</span>
                        </span>
                    </label>

                    <label class="eb-inline-choice cursor-pointer">
                        <input type="checkbox" x-model="newMount.enable_cache" class="eb-check-input" />
                        <span>
                            <span class="eb-type-body text-[var(--eb-text-primary)]">Enable VFS caching</span>
                            <span class="mt-0.5 block eb-type-caption">Cache files locally for better performance</span>
                        </span>
                    </label>
                </div>
            </div>

            {* Step 3: Review *}
            <div x-show="wizardStep === 2">
                <div class="eb-card-raised !p-0 overflow-hidden">
                    <div class="border-b border-[var(--eb-border-subtle)] bg-[var(--eb-bg-chrome)] p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-orange)] bg-[var(--eb-primary-soft)] shadow-[var(--eb-shadow-sm)]">
                                <span class="eb-type-h3 text-[var(--eb-primary)]" x-text="newMount.drive_letter + ':'"></span>
                            </div>
                            <div class="min-w-0">
                                <p class="eb-type-h3 truncate" x-text="newMount.bucket"></p>
                                <p class="mt-0.5 eb-type-caption" x-text="newMount.prefix || 'Root folder'"></p>
                            </div>
                        </div>
                    </div>
                    <div class="eb-kv-list p-4">
                        <div class="eb-kv-row">
                            <span class="eb-kv-label">Agent</span>
                            <span class="eb-kv-value" x-text="agents.find(a => a.agent_uuid == newMount.agent_uuid)?.hostname || newMount.agent_uuid"></span>
                        </div>
                        <div class="eb-kv-row">
                            <span class="eb-kv-label">Drive Letter</span>
                            <span class="eb-kv-value" x-text="newMount.drive_letter + ':'"></span>
                        </div>
                        <div class="eb-kv-row">
                            <span class="eb-kv-label">Access Mode</span>
                            <span class="eb-kv-value" x-text="newMount.read_only ? 'Read-only' : 'Read/Write'"></span>
                        </div>
                        <div class="eb-kv-row">
                            <span class="eb-kv-label">Auto-mount</span>
                            <span class="eb-kv-value" x-text="newMount.persistent ? 'Yes' : 'No'"></span>
                        </div>
                        <div class="eb-kv-row">
                            <span class="eb-kv-label">VFS Caching</span>
                            <span class="eb-kv-value" x-text="newMount.enable_cache ? 'Enabled' : 'Disabled'"></span>
                        </div>
                    </div>
                </div>

                <div class="eb-alert eb-alert--info mt-4 !mb-0">
                    <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                    <div>
                        <p class="eb-type-caption !mb-0">The drive will be mounted automatically on your Windows PC.</p>
                    </div>
                </div>
            </div>
            </div>
        </div>

        {* Footer *}
        <div class="eb-modal-footer !mt-0 shrink-0 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-h-[36px] items-center">
                <button x-show="wizardStep > 0" @click="wizardStep--"
                        type="button"
                        class="eb-btn eb-btn-secondary eb-btn-sm">
                    Back
                </button>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <button @click="showMountWizard = false"
                        type="button"
                        class="eb-btn eb-btn-secondary eb-btn-sm">
                    Cancel
                </button>
                <button x-show="wizardStep < 2" @click="wizardStep++"
                        :disabled="!canProceed"
                        type="button"
                        class="eb-btn eb-btn-primary eb-btn-sm disabled:cursor-not-allowed disabled:opacity-50">
                    Next
                </button>
                <button x-show="wizardStep === 2" @click="createMount()"
                        type="button"
                        class="eb-btn eb-btn-success eb-btn-sm inline-flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                    </svg>
                    Mount Drive
                </button>
            </div>
        </div>
    </div>
</div>
