{* Cloud NAS - My Drives Tab *}

{* Agent prerequisite check *}
<template x-if="!hasAgent">
    <div class="eb-alert eb-alert--warning mb-6">
        <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>
        <div>
            <div class="eb-alert-title">Agent Required</div>
            <p class="eb-type-body !mb-0 mt-1">
                Cloud NAS requires the Windows backup agent to be installed and connected.
                <a href="index.php?m=cloudstorage&page=e3backup&view=agents" class="eb-link">Set up an agent</a>
                to get started.
            </p>
        </div>
    </div>
</template>

{* Summary Cards *}
<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="eb-stat-card">
        <div class="eb-stat-label">Active Mounts</div>
        <div class="eb-stat-value" x-text="mounts.filter(m => m.status === 'mounted').length">0</div>
    </div>
    <div class="eb-stat-card">
        <div class="eb-stat-label">Configured Drives</div>
        <div class="eb-stat-value" x-text="mounts.length">0</div>
    </div>
    <div class="eb-stat-card">
        <div class="eb-stat-label">Cache Used</div>
        <div class="eb-stat-value !text-2xl !font-semibold !tracking-normal" x-text="cacheUsed">0 GB</div>
    </div>
    <div class="eb-stat-card"
         :class="agentOnline ? '!border-[var(--eb-success-border)]' : ''">
        <div class="eb-stat-label"
             :class="agentOnline ? '!text-[var(--eb-success-text)]' : ''">Agent Status</div>
        <div class="mt-1 flex items-center gap-2">
            <span class="eb-status-dot shrink-0"
                  :class="agentOnline ? 'eb-status-dot--active' : 'eb-status-dot--inactive'"></span>
            <span class="eb-type-body font-semibold text-[var(--eb-text-primary)]"
                  x-text="agentOnline ? 'Online' : 'Offline'"></span>
        </div>
    </div>
</div>

{* Loading state *}
<template x-if="loadingMounts">
    <div class="flex items-center justify-center py-12">
        <svg class="h-8 w-8 animate-spin text-[var(--eb-primary)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>
</template>

{* Mounted Drives Grid *}
<template x-if="!loadingMounts">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <template x-for="mount in mounts" :key="mount.id">
            <div class="group relative rounded-[var(--eb-radius-xl)] border bg-[var(--eb-bg-card)] p-5 transition"
                 :class="{
                    'border-[var(--eb-border-orange)] shadow-[var(--eb-shadow-md)]': mount.status === 'mounted',
                    'border-[var(--eb-warning-border)]': mount.status === 'mounting' || mount.status === 'unmounting',
                    'border-[var(--eb-border-default)] hover:border-[var(--eb-border-emphasis)]': mount.status === 'unmounted',
                    'border-[var(--eb-danger-border)]': mount.status === 'error'
                 }">

                {* Drive letter badge (overlaps top-left card edge; solid fills so card border does not show through) *}
                <div class="absolute -left-2 -top-3 z-10 flex h-12 w-12 items-center justify-center rounded-[var(--eb-radius-lg)] text-lg font-bold shadow-[var(--eb-shadow-md)]"
                     :class="{
                        'border border-[var(--eb-border-brand)] bg-[var(--eb-primary)] text-[var(--eb-text-inverse)]': mount.status === 'mounted',
                        'border border-[var(--eb-warning-border)] bg-[var(--eb-warning-bg)] text-[var(--eb-warning-text)]': mount.status === 'mounting' || mount.status === 'unmounting',
                        'border border-[var(--eb-border-subtle)] bg-[var(--eb-bg-raised)] text-[var(--eb-text-muted)]': mount.status === 'unmounted',
                        'border border-[var(--eb-danger-border)] bg-[var(--eb-danger-bg)] text-[var(--eb-danger-text)]': mount.status === 'error'
                     }">
                    <span x-text="mount.drive_letter + ':'"></span>
                </div>

                {* Status indicator *}
                <div class="absolute right-4 top-4">
                    <span class="eb-status-dot"
                          :class="{
                            'eb-status-dot--active': mount.status === 'mounted',
                            'eb-status-dot--warning': mount.status === 'mounting' || mount.status === 'unmounting',
                            'eb-status-dot--inactive': mount.status === 'unmounted',
                            'eb-status-dot--error': mount.status === 'error'
                          }"></span>
                </div>

                <div class="mt-6">
                    <h3 class="eb-type-h3 truncate" x-text="mount.bucket_name" :title="mount.bucket_name"></h3>
                    <p class="eb-type-caption mt-1 flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3.5 w-3.5 shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                        </svg>
                        <span x-text="mount.prefix || '/ (root)'"></span>
                    </p>
                </div>

                {* Status text *}
                <p class="eb-type-caption mt-2 font-medium"
                   :class="{
                      '!text-[var(--eb-success-text)]': mount.status === 'mounted',
                      '!text-[var(--eb-warning-text)]': mount.status === 'mounting' || mount.status === 'unmounting',
                      '!text-[var(--eb-text-muted)]': mount.status === 'unmounted',
                      '!text-[var(--eb-danger-text)]': mount.status === 'error'
                   }">
                    <span x-show="mount.status === 'mounted'">Mounted</span>
                    <span x-show="mount.status === 'mounting'">Mounting...</span>
                    <span x-show="mount.status === 'unmounting'">Unmounting...</span>
                    <span x-show="mount.status === 'unmounted'">Not mounted</span>
                    <span x-show="mount.status === 'error'" x-text="'Error: ' + (mount.error || 'Unknown')"></span>
                </p>

                {* Mount config pills *}
                <div class="mt-4 flex flex-wrap gap-2">
                    <span x-show="mount.read_only" class="eb-badge eb-badge--warning">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mr-1 h-3 w-3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                        Read-only
                    </span>
                    <span x-show="mount.cache_mode && mount.cache_mode !== 'off'" class="eb-badge eb-badge--info">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mr-1 h-3 w-3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                        </svg>
                        VFS Cache
                    </span>
                    <span x-show="mount.persistent" class="eb-badge eb-badge--premium">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mr-1 h-3 w-3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Auto-mount
                    </span>
                </div>

                {* Actions *}
                <div class="mt-5 flex gap-2">
                    <template x-if="mount.status !== 'mounted' && mount.status !== 'mounting' && mount.status !== 'unmounting'">
                        <button type="button"
                                @click="mountDrive(mount.id)"
                                :disabled="!agentOnline"
                                class="eb-btn eb-btn-success eb-btn-sm flex flex-1 items-center justify-center gap-2 disabled:cursor-not-allowed disabled:opacity-50">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                            </svg>
                            <span>Mount</span>
                        </button>
                    </template>
                    <template x-if="mount.status === 'mounted'">
                        <button type="button"
                                @click="unmountDrive(mount.id)"
                                class="eb-btn eb-btn-secondary eb-btn-sm flex flex-1 items-center justify-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" />
                            </svg>
                            Unmount
                        </button>
                    </template>
                    <template x-if="mount.status === 'mounting' || mount.status === 'unmounting'">
                        <button type="button" disabled class="eb-btn eb-btn-secondary eb-btn-sm flex flex-1 cursor-not-allowed items-center justify-center gap-2 opacity-60">
                            <svg class="h-4 w-4 animate-spin shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-text="mount.status === 'mounting' ? 'Mounting...' : 'Unmounting...'"></span>
                        </button>
                    </template>
                    <button type="button"
                            @click="editMount(mount.id)"
                            class="eb-btn eb-btn-icon eb-btn-sm shrink-0"
                            title="Edit mount settings">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                        </svg>
                    </button>
                    <button type="button"
                            @click="deleteMount(mount.id)"
                            class="eb-btn eb-btn-icon eb-btn-sm is-danger shrink-0"
                            title="Delete mount configuration">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                    </button>
                </div>
            </div>
        </template>

        {* Empty state / Add New Card *}
        <div @click="hasAgent && openMountWizard()"
             class="group flex min-h-[240px] flex-col items-center justify-center rounded-[var(--eb-radius-xl)] border-2 border-dashed p-5 transition"
             :class="hasAgent ? 'cursor-pointer border-[var(--eb-border-default)] hover:border-[var(--eb-border-orange)]' : 'cursor-not-allowed border-[var(--eb-border-subtle)] opacity-50'">
            <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-[var(--eb-bg-raised)] transition group-hover:bg-[var(--eb-primary-soft)]">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-7 w-7 text-[var(--eb-text-muted)] transition group-hover:text-[var(--eb-primary)]">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
            </div>
            <p class="eb-type-body font-medium text-[var(--eb-text-muted)] transition group-hover:text-[var(--eb-text-primary)]">Mount a new drive</p>
            <p class="eb-type-caption mt-1">Connect a bucket as a local drive</p>
        </div>
    </div>
</template>
