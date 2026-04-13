<!-- ebLoader -->
<script src="{$WEB_ROOT}/modules/addons/eazybackup/templates/assets/js/ui.js"></script>

<div class="eb-page" x-data="usersManager()">
	{* <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div> *}
	<div class="eb-page-inner relative pointer-events-auto pb-10">
		<div class="eb-panel">
            <div class="eb-panel-nav">
                {include file="modules/addons/cloudstorage/templates/partials/core_nav.tpl" cloudstorageActivePage='users'}
            </div>
		<div class="eb-page-header">
			<div class="min-w-0 flex-1">
				<h1 class="eb-page-title">Manage Users</h1>
				<p class="eb-page-description">Create storage users, manage API credentials, and review subuser access for each account.</p>
			</div>
			<div class="flex w-full min-w-0 flex-col items-stretch gap-3 sm:items-end lg:w-auto">
				<span class="eb-badge eb-badge--neutral self-start sm:self-end" x-text="filteredUsers.length + ' users'"></span>
				<div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
					<div class="eb-input-wrap w-full sm:w-48 lg:w-64">
						<div class="eb-input-icon">
							<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
							</svg>
						</div>
						<input type="text"
							x-model="searchTerm"
							placeholder="Search users..."
							class="eb-input eb-input-has-icon w-full">
					</div>
					<div class="relative" x-data="{ open: false }">
						<button type="button" @click="open = !open" @click.away="open = false"
							class="eb-btn eb-btn-secondary eb-btn-sm">
							<svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
							</svg>
							Filter
						</button>
						<div x-show="open" x-cloak
							class="eb-dropdown-menu absolute right-0 z-50 mt-2 w-48 scrollbar_thin">
							<div class="p-2">
								<label class="eb-inline-choice mb-2">
									<input type="checkbox" x-model="filters.hasKeys" class="eb-check-input">
									<span>Has API Keys</span>
								</label>
								<label class="eb-inline-choice mb-2">
									<input type="checkbox" x-model="filters.hasSubusers" class="eb-check-input">
									<span>Has Subusers</span>
								</label>
								<label class="eb-inline-choice">
									<input type="checkbox" x-model="filters.hasStorage" class="eb-check-input">
									<span>Has Storage</span>
								</label>
							</div>
						</div>
					</div>
					<div x-show="selectedUsers.length > 0" class="flex items-center gap-2">
						<span class="eb-type-caption" x-text="selectedUsers.length + ' selected'"></span>
						<button type="button" @click="bulkDelete"
							class="eb-btn eb-btn-danger-solid eb-btn-sm">
							Delete Selected
						</button>
					</div>
					<button type="button" onclick="openCreateUserSlideover()"
						class="eb-btn eb-btn-success eb-btn-sm whitespace-nowrap">
						<svg class="mr-2 inline h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
						</svg>
						Create User
					</button>
				</div>
			</div>
		</div>

		<!-- Create User Slide-Over -->
		<div id="createUserSlideover" x-data="{ isOpen: false }" x-init="
			window.addEventListener('open-create-user-slideover', () => { isOpen = true });
			window.addEventListener('close-create-user-slideover', () => { isOpen = false });
		" x-show="isOpen" class="fixed inset-0 z-50" style="display: none;">
			<!-- Backdrop -->
			<div class="absolute inset-0 eb-drawer-backdrop"
				 x-show="isOpen"
				 x-transition.opacity
				 onclick="closeCreateUserSlideover()"></div>
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
					    <h3 class="eb-drawer-title">Create User</h3>
                        <p class="eb-type-caption mt-1">Provision a new Cloud Storage user before generating keys or subusers.</p>
                    </div>
					<button type="button" class="eb-modal-close" onclick="closeCreateUserSlideover()" aria-label="Close">
						<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
						</svg>
					</button>
				</div>
				<div class="eb-drawer-body">
					<div id="createUserMessage" class="eb-alert eb-alert--danger mb-4 hidden"></div>

					<div class="mb-4">
						<label class="eb-field-label">Username</label>
						<input type="text" x-model="newUser.username" placeholder="e.g., acme-corp"
							   class="eb-input w-full" required>
						
					</div>
                </div>
                <div class="eb-drawer-footer justify-end">
					<button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" onclick="closeCreateUserSlideover()">Cancel</button>
					<button type="button" class="eb-btn eb-btn-success eb-btn-sm" @click="createUser(); closeCreateUserSlideover()">Confirm</button>
				</div>
			</div>
		</div>

		<!-- Alert Messages -->
		<div x-show="alert.show" x-cloak x-transition
			class="eb-alert mb-6"
			:class="alert.type === 'success' ? 'eb-alert--success' : 'eb-alert--danger'">
			<svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" x-show="alert.type === 'success'" aria-hidden="true">
				<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
			</svg>
			<svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" x-show="alert.type !== 'success'" aria-hidden="true">
				<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
			</svg>
			<div><span x-text="alert.message"></span></div>
		</div>

		<!-- Users Table -->
		<div class="eb-table-shell overflow-hidden">
			<!-- Table Controls -->
			<div class="eb-table-toolbar flex-col items-center justify-between gap-3 sm:flex-row">
				<div class="flex flex-wrap items-center gap-4">
					<label class="eb-inline-choice">
						<input type="checkbox"
							:checked="selectedUsers.length === filteredUsers.length && filteredUsers.length > 0"
							@change="toggleSelectAll"
							class="eb-check-input">
						<span class="eb-type-caption">Select All</span>
					</label>
					<div class="flex flex-wrap items-center gap-2">
						<span class="eb-type-caption">Sort by:</span>
						<select x-model="sortBy"
							class="eb-select min-w-[120px] py-1 scrollbar_thin">
							<option value="username">Username</option>
							<option value="total_buckets">Buckets</option>
							<option value="total_storage">Storage</option>
							<option value="keys_count">API Keys</option>
							<option value="subusers_count">Subusers</option>
						</select>
						<button type="button" @click="sortOrder = sortOrder === 'asc' ? 'desc' : 'asc'"
							class="eb-table-sort-button">
							<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
									x-bind:d="sortOrder === 'asc' ? 'M3 4l9 16 9-16H3z' : 'M21 20L12 4 3 20h18z'"></path>
							</svg>
						</button>
					</div>
				</div>
				<div class="eb-type-caption">
					<span>Showing </span>
					<span x-text="((currentPage - 1) * pageSize) + 1"></span>
					<span> to </span>
					<span x-text="Math.min(currentPage * pageSize, filteredUsers.length)"></span>
					<span> of </span>
					<span x-text="filteredUsers.length"></span>
					<span> users</span>
				</div>
			</div>

			<div class="overflow-x-auto">
				<table class="eb-table min-w-full">
					<thead>
						<tr>
							<th class="w-12"></th>
							<th>Username</th>
							<th>Buckets</th>
							<th>Storage</th>
							<th>API Keys</th>
							<th>Subusers</th>
							<th>Actions</th>
						</tr>
					</thead>
					<template x-for="(user, index) in paginatedUsers" :key="user.username">
						<tbody>
							<tr class="eb-expand-row"
								:class="{
									'is-open': expandedRows.includes(user.username),
									'is-selected': selectedUsers.includes(user.username)
								}"
								@click="toggleExpanded(user.username)">
								<td class="whitespace-nowrap" @click.stop>
									<input type="checkbox" :value="user.username" x-model="selectedUsers" class="eb-check-input">
								</td>
								<td class="whitespace-nowrap">
									<div class="flex items-center">
										<svg class="eb-expand-chevron mr-3" viewBox="0 0 24 24" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
										<div class="min-w-0">
											<div class="eb-table-primary">
												<span class="eb-text-muted font-normal">Username:</span>
												<span x-text="user.username"></span>
											</div>
											<div class="eb-user-meta-line mt-1" @click.stop>
												<span>Account ID</span>
												<span class="eb-table-mono" x-text="user.tenant_id ? String(user.tenant_id) : '—'"></span>
												<button type="button"
													x-show="user.tenant_id"
													@click.stop="copyToClipboard(String(user.tenant_id))"
													class="eb-btn eb-btn-icon eb-btn-sm"
													title="Copy Account ID">
													<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
														<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M16 3h2a2 2 0 012 2v10a2 2 0 01-2 2H10a2 2 0 01-2-2V5a2 2 0 012-2h2" />
													</svg>
												</button>
											</div>
										</div>
									</div>
								</td>
								<td class="whitespace-nowrap">
									<span x-text="user.total_buckets || '0'"></span>
								</td>
								<td class="whitespace-nowrap">
									<span x-html="user.total_storage || '0 B'"></span>
								</td>
								<td class="whitespace-nowrap">
									<span x-text="user.keys ? user.keys.length : '0'"></span>
								</td>
								<td class="whitespace-nowrap">
									<span x-text="user.subusers ? user.subusers.length : '0'"></span>
								</td>
								<td class="whitespace-nowrap" @click.stop>
									<div class="flex items-center gap-1">
										<button type="button" @click="createPrimaryKey(user.username)" class="eb-btn eb-btn-icon eb-btn-sm is-success" title="Generate API Keys">
											<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
											</svg>
										</button>
										<button type="button" @click="createSubuser(user.username)" class="eb-btn eb-btn-icon eb-btn-sm" title="Create Subuser">
											<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
											</svg>
										</button>
										<button type="button" @click="deleteUser(user.username)" class="eb-btn eb-btn-icon eb-btn-sm is-danger" title="Delete User">
											<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4a2 2 0 012 2v2H8V5a2 2 0 012-2z"></path>
											</svg>
										</button>
									</div>
								</td>
							</tr>

							<tr x-show="expandedRows.includes(user.username)" x-cloak class="eb-expand-detail">
								<td colspan="7">
									<div class="eb-expand-detail-inner">
									<div class="mb-4 flex flex-wrap items-center gap-x-6 gap-y-2 eb-type-caption">
										<div class="flex items-center gap-2">
											<span class="eb-text-muted">Account ID</span>
											<span class="eb-table-mono" x-text="user.tenant_id ? String(user.tenant_id) : '—'"></span>
											<button type="button"
												x-show="user.tenant_id"
												@click.stop="copyToClipboard(String(user.tenant_id))"
												class="eb-btn eb-btn-icon eb-btn-sm"
												title="Copy Account ID">
												<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M16 3h2a2 2 0 012 2v10a2 2 0 01-2 2H10a2 2 0 01-2-2V5a2 2 0 012-2h2" />
												</svg>
											</button>
										</div>
									</div>
									<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
										<div>
											<div class="mb-3 flex items-center justify-between">
												<h4 class="eb-type-h4">API Keys</h4>
												<button type="button" @click="createPrimaryKey(user.username)" class="eb-btn eb-btn-primary eb-btn-xs">
													+ Generate API Keys
												</button>
											</div>
											<div class="scrollbar_thin max-h-64 space-y-2 overflow-y-auto">
												<template x-for="key in user.keys || []" :key="key.key_id">
													<div class="eb-subpanel !p-3">
														<div class="mb-3 flex items-start justify-between gap-2">
															<div class="eb-type-caption">API Key Pair</div>
															<div class="flex flex-wrap justify-end gap-1">
																<button type="button" @click="viewKey(key.key_id, 'primary', user.username)"
																	x-show="!key.decrypted"
																	class="eb-btn eb-btn-info eb-btn-xs shrink-0" title="Decrypt Keys">
																	<svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 6 0z"></path>
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
																	</svg>
																	<span class="hidden sm:inline">Decrypt</span>
																</button>
																<button type="button" @click="hideKey(key.key_id, 'primary', user.username)"
																	x-show="key.decrypted"
																	class="eb-btn eb-btn-orange eb-btn-xs" title="Hide Keys">
																	<svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L8.464 8.464M14.121 14.121l1.414 1.414M3 3l18 18"></path>
																	</svg>
																	<span class="hidden sm:inline">Hide</span>
																</button>
																<button type="button" @click="deleteKey(key.key_id, 'primary', user.username)" class="eb-btn eb-btn-danger eb-btn-xs" title="Delete Key">
																	<svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4a2 2 0 012 2v2H8V5a2 2 0 012-2z"></path>
																	</svg>
																	<span class="hidden sm:inline">Delete</span>
																</button>
															</div>
														</div>
														<div class="mb-3">
															<label class="eb-field-label">Access Key</label>
															<div class="flex items-center gap-2">
																<input type="text" readonly
																	:value="key.decrypted ? key.access_key : '••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••'"
																	class="eb-input eb-type-mono min-h-0 flex-1 select-all py-1 text-xs"
																	:class="{ 'is-success': key.decrypted }">
																<button type="button" @click="copyToClipboard(key.access_key)"
																	x-show="key.decrypted"
																	class="eb-btn eb-btn-secondary eb-btn-xs shrink-0"
																	title="Copy Access Key">
																	<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3 w-3" aria-hidden="true">
																		<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
																	</svg>
																</button>
															</div>
														</div>
														<div>
															<label class="eb-field-label">Secret Key</label>
															<div class="flex items-center gap-2">
																<input type="text" readonly
																	:value="key.decrypted ? key.secret_key : '••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••'"
																	class="eb-input eb-type-mono min-h-0 flex-1 select-all py-1 text-xs"
																	:class="{ 'is-success': key.decrypted }">
																<button type="button" @click="copyToClipboard(key.secret_key)"
																	x-show="key.decrypted"
																	class="eb-btn eb-btn-secondary eb-btn-xs shrink-0"
																	title="Copy Secret Key">
																	<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3 w-3" aria-hidden="true">
																		<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
																	</svg>
																</button>
															</div>
														</div>
													</div>
												</template>
												<div x-show="!user.keys || user.keys.length === 0" class="eb-app-empty !py-4">
													<p class="eb-app-empty-copy">No API keys</p>
												</div>
											</div>
										</div>
										
										<div>
											<div class="mb-3 flex items-center justify-between">
												<h4 class="eb-type-h4">Subusers</h4>
												<button type="button" @click="createSubuser(user.username)" class="eb-btn eb-btn-primary eb-btn-xs">
													+ Create Subuser
												</button>
											</div>
											<div class="scrollbar_thin max-h-64 space-y-1 overflow-y-auto" x-show="(user.subusers || []).length > 1">
												<template x-for="subuser in user.subusers || []" :key="subuser.key_id">
													<div class="eb-subpanel overflow-hidden !p-0">
														<div class="flex items-center justify-between gap-2 p-2">
															<div class="min-w-0 flex-1">
																<div class="eb-table-primary truncate" x-text="subuser.subuser"></div>
																<div class="mt-0.5 flex flex-wrap items-center gap-2 eb-type-caption">
																	<span x-text="subuser.permission"></span>
																	<span x-show="subuser.decrypted" class="eb-badge eb-badge--success eb-badge--dot">Decrypted</span>
																</div>
															</div>
															<div class="ml-2 flex shrink-0 items-center gap-1">
																<button type="button" @click="viewKey(subuser.key_id, 'subuser', user.username)"
																	x-show="!subuser.decrypted"
																	class="eb-btn eb-btn-icon eb-btn-sm"
																	title="Decrypt Keys">
																	<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 6 0z"></path>
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
																	</svg>
																</button>
																<button type="button" @click="hideKey(subuser.key_id, 'subuser', user.username)"
																	x-show="subuser.decrypted"
																	class="eb-btn eb-btn-icon eb-btn-sm"
																	title="Hide Keys">
																	<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L8.464 8.464M14.121 14.121l1.414 1.414M3 3l18 18"></path>
																	</svg>
																</button>
																<button type="button" @click="deleteKey(subuser.key_id, 'subuser', user.username)"
																	class="eb-btn eb-btn-icon eb-btn-sm is-danger"
																	title="Delete Subuser">
																	<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4a2 2 0 012 2v2H8V5a2 2 0 012-2z"></path>
																	</svg>
																</button>
															</div>
														</div>
													<div x-show="subuser.decrypted" x-transition class="space-y-3 border-t border-[var(--eb-border-default)] p-3">
															<div>
																<label class="eb-field-label">Access Key</label>
																<div class="flex items-center gap-2">
																	<input type="text" readonly :value="subuser.access_key"
																		class="eb-input eb-type-mono is-success min-h-0 flex-1 select-all py-1 text-xs">
																	<button type="button" @click="copyToClipboard(subuser.access_key)"
																		class="eb-btn eb-btn-secondary eb-btn-xs shrink-0"
																		title="Copy Access Key">
																		<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3 w-3" aria-hidden="true">
																			<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
																		</svg>
																	</button>
																</div>
															</div>
															<div>
																<label class="eb-field-label">Secret Key</label>
																<div class="flex items-center gap-2">
																	<input type="text" readonly :value="subuser.secret_key"
																		class="eb-input eb-type-mono is-success min-h-0 flex-1 select-all py-1 text-xs">
																	<button type="button" @click="copyToClipboard(subuser.secret_key)"
																		class="eb-btn eb-btn-secondary eb-btn-xs shrink-0"
																		title="Copy Secret Key">
																		<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3 w-3" aria-hidden="true">
																			<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
																		</svg>
																	</button>
																</div>
															</div>
														</div>
													</div>
												</template>
											</div>

											<div class="scrollbar_thin max-h-64 space-y-2 overflow-y-auto" x-show="(user.subusers || []).length <= 1">
												<template x-for="subuser in user.subusers || []" :key="subuser.key_id + '-single'">
													<div class="eb-subpanel !p-3">
														<div class="mb-3 flex items-start justify-between gap-2">
															<div class="min-w-0 flex-1">
																<div class="eb-table-primary" x-text="subuser.subuser"></div>
																<div class="eb-type-caption mt-0.5" x-text="subuser.permission"></div>
															</div>
															<div class="flex shrink-0 flex-wrap justify-end gap-1">
																<button type="button" @click="viewKey(subuser.key_id, 'subuser', user.username)"
																	x-show="!subuser.decrypted"
																	class="eb-btn eb-btn-info eb-btn-xs" title="Decrypt Keys">
																	<svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 6 0z"></path>
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
																	</svg>
																	<span class="hidden sm:inline">Decrypt</span>
																</button>
																<button type="button" @click="hideKey(subuser.key_id, 'subuser', user.username)"
																	x-show="subuser.decrypted"
																	class="eb-btn eb-btn-orange eb-btn-xs" title="Hide Keys">
																	<svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L8.464 8.464M14.121 14.121l1.414 1.414M3 3l18 18"></path>
																	</svg>
																	<span class="hidden sm:inline">Hide</span>
																</button>
																<button type="button" @click="deleteKey(subuser.key_id, 'subuser', user.username)" class="eb-btn eb-btn-danger eb-btn-xs" title="Delete Key">
																	<svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4a2 2 0 012 2v2H8V5a2 2 0 012-2z"></path>
																	</svg>
																	<span class="hidden sm:inline">Delete</span>
																</button>
															</div>
														</div>
														<div class="mb-3">
															<label class="eb-field-label">Access Key</label>
															<div class="flex items-center gap-2">
																<input type="text" readonly
																	:value="subuser.decrypted ? subuser.access_key : '••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••'"
																	class="eb-input eb-type-mono min-h-0 flex-1 select-all py-1 text-xs"
																	:class="{ 'is-success': subuser.decrypted }">
																<button type="button" @click="copyToClipboard(subuser.access_key)"
																	x-show="subuser.decrypted"
																	class="eb-btn eb-btn-secondary eb-btn-xs shrink-0"
																	title="Copy Access Key">
																	<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3 w-3" aria-hidden="true">
																		<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
																	</svg>
																</button>
															</div>
														</div>
														<div>
															<label class="eb-field-label">Secret Key</label>
															<div class="flex items-center gap-2">
																<input type="text" readonly
																	:value="subuser.decrypted ? subuser.secret_key : '••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••'"
																	class="eb-input eb-type-mono min-h-0 flex-1 select-all py-1 text-xs"
																	:class="{ 'is-success': subuser.decrypted }">
																<button type="button" @click="copyToClipboard(subuser.secret_key)"
																	x-show="subuser.decrypted"
																	class="eb-btn eb-btn-secondary eb-btn-xs shrink-0"
																	title="Copy Secret Key">
																	<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3 w-3" aria-hidden="true">
																		<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
																	</svg>
																</button>
															</div>
														</div>
													</div>
												</template>
												<div x-show="!user.subusers || user.subusers.length === 0" class="eb-app-empty !py-4">
													<p class="eb-app-empty-copy">No subusers</p>
												</div>
											</div>
										</div>
									</div>
									</div>
								</td>
							</tr>
						</tbody>
					</template>
				</table>
			</div>

			<div class="eb-table-pagination border-t border-[var(--eb-border-default)] !mt-0">
				<div class="flex items-center gap-2">
					<span class="eb-type-caption">Show:</span>
					<select x-model="pageSize" class="eb-select min-w-[70px] py-1 scrollbar_thin">
						<option value="10">10</option>
						<option value="25">25</option>
						<option value="50">50</option>
						<option value="100">100</option>
					</select>
					<span class="eb-type-caption">per page</span>
				</div>
				<div class="flex flex-wrap items-center gap-2">
					<button type="button" @click="currentPage = Math.max(1, currentPage - 1)"
						:disabled="currentPage === 1"
						class="eb-table-pagination-button">
						Previous
					</button>
					<template x-for="page in pageNumbers" :key="page">
						<button type="button" @click="currentPage = page"
							:class="page === currentPage ? 'eb-btn eb-btn-primary eb-btn-xs' : 'eb-table-pagination-button'"
							x-text="page">
						</button>
					</template>
					<button type="button" @click="currentPage = Math.min(totalPages, currentPage + 1)"
						:disabled="currentPage === totalPages"
						class="eb-table-pagination-button">
						Next
					</button>
				</div>
			</div>
		</div>

		</div>

		<!-- ebLoader integration (show/hide based on Alpine loading state) -->
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

		<div x-show="modals.createSubuser" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
			<div class="eb-modal-backdrop fixed inset-0" @click="modals.createSubuser = false" aria-hidden="true"></div>
			<div class="eb-modal relative z-10 w-full max-w-lg" role="dialog" aria-modal="true" aria-labelledby="users-create-subuser-title" @click.away="modals.createSubuser = false">
				<div class="eb-modal-header">
					<div>
						<h2 id="users-create-subuser-title" class="eb-modal-title">Create Subuser</h2>
					</div>
					<button type="button" class="eb-modal-close" @click="modals.createSubuser = false" aria-label="Close">
						<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
						</svg>
					</button>
				</div>
				<div class="eb-modal-body">
					<div x-show="modalError.message" x-cloak class="eb-alert eb-alert--danger mb-4" role="alert">
						<svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
							<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
						</svg>
						<div><span x-text="modalError.message"></span></div>
					</div>
					<form id="users-create-subuser-form" @submit.prevent="submitCreateSubuser">
						<div class="mb-4">
							<label class="eb-field-label" for="users-subuser-username">Username</label>
							<input id="users-subuser-username" type="text" x-model="subuserForm.username" placeholder="Enter username" class="eb-input w-full">
						</div>
						<div class="mb-0">
							<label class="eb-field-label" for="users-subuser-permission">Permissions</label>
							<select id="users-subuser-permission" x-model="subuserForm.permission" class="eb-select w-full scrollbar_thin">
								<option value="full">Full</option>
								<option value="read">Read</option>
								<option value="write">Write</option>
								<option value="readwrite">Read Write</option>
							</select>
						</div>
					</form>
				</div>
				<div class="eb-modal-footer">
					<button type="submit" form="users-create-subuser-form" class="eb-btn eb-btn-success eb-btn-sm flex-1">Create Subuser</button>
					<button type="button" @click="modals.createSubuser = false" class="eb-btn eb-btn-secondary eb-btn-sm flex-1">Cancel</button>
				</div>
			</div>
		</div>

		<div x-show="modals.password" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
			<div class="eb-modal-backdrop fixed inset-0" @click="modals.password = false" aria-hidden="true"></div>
			<div class="eb-modal relative z-10 w-full max-w-lg" role="dialog" aria-modal="true" aria-labelledby="users-password-modal-title" @click.away="modals.password = false">
				<div class="eb-modal-header">
					<div>
						<h2 id="users-password-modal-title" class="eb-modal-title">Verify Password</h2>
					</div>
					<button type="button" class="eb-modal-close" @click="modals.password = false" aria-label="Close">
						<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
						</svg>
					</button>
				</div>
				<div class="eb-modal-body">
					<div x-show="modalError.message" x-cloak class="eb-alert eb-alert--danger mb-4" role="alert">
						<svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
							<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
						</svg>
						<div><span x-text="modalError.message"></span></div>
					</div>
					<form id="users-password-form" @submit.prevent="submitPassword">
						<p class="eb-type-body mb-4">Enter your account password to proceed.</p>
						<label class="eb-field-label" for="users-account-password">Account password</label>
						<input id="users-account-password" type="password" x-model="passwordForm.password" placeholder="Account Password" class="eb-input w-full">
					</form>
				</div>
				<div class="eb-modal-footer">
					<button type="submit" form="users-password-form" class="eb-btn eb-btn-primary eb-btn-sm flex-1">Verify</button>
					<button type="button" @click="modals.password = false" class="eb-btn eb-btn-secondary eb-btn-sm flex-1">Cancel</button>
				</div>
			</div>
		</div>

		<div x-show="modals.confirm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
			<div class="eb-modal-backdrop fixed inset-0" @click="modals.confirm = false" aria-hidden="true"></div>
			<div class="eb-modal eb-modal--confirm relative z-10 w-full" role="dialog" aria-modal="true" aria-labelledby="users-confirm-modal-title" @click.away="modals.confirm = false">
				<div class="eb-modal-header">
					<div class="flex min-w-0 items-start gap-3">
						<span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange shrink-0" aria-hidden="true">
							<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.35 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
							</svg>
						</span>
						<div class="min-w-0">
							<h2 id="users-confirm-modal-title" class="eb-modal-title" x-text="confirmModal.title">Confirm Action</h2>
						</div>
					</div>
					<button type="button" class="eb-modal-close" @click="modals.confirm = false" aria-label="Close">&times;</button>
				</div>
				<div class="eb-modal-body">
					<p class="eb-type-body" x-text="confirmModal.message">Are you sure?</p>
				</div>
				<div class="eb-modal-footer">
					<button type="button" @click="confirmModal.onConfirm(); modals.confirm = false" class="eb-btn eb-btn-danger-solid eb-btn-sm flex-1">Confirm</button>
					<button type="button" @click="modals.confirm = false" class="eb-btn eb-btn-secondary eb-btn-sm flex-1">Cancel</button>
				</div>
			</div>
		</div>
	</div>
</div>
<script>
function openCreateUserSlideover(){
	try { window.dispatchEvent(new CustomEvent('open-create-user-slideover')); } catch(_) {}
}
function closeCreateUserSlideover(){
	try { window.dispatchEvent(new CustomEvent('close-create-user-slideover')); } catch(_) {}
}
</script>

<script>
// Initialize users data from server
var usersData = {if $users}{$users|json_encode}{else}[]{/if};

function usersManager() {
    return {
        // Data properties
        users: usersData || [],
        searchTerm: '',
        sortBy: 'username',
        sortOrder: 'asc',
        selectedUsers: [],
        expandedRows: [],
        currentPage: 1,
        pageSize: 25,
        loading: false,
        loadingText: 'Loading...',
        showCreateForm: false,
        
        // Filters object
        filters: {
            hasKeys: false,
            hasSubusers: false,
            hasStorage: false
        },
        
        // Modals state
        modals: {
            createSubuser: false,
            password: false,
            confirm: false
        },
        
        // Form data
        newUser: {
            name: '',
            username: ''
        },
        subuserForm: {
            username: '',
            permission: 'full',
            parentUser: ''
        },
        passwordForm: {
            password: '',
            action: '',
            keyId: '',
            keyType: '',
            username: ''
        },
        
        // Modal data
        confirmModal: {
            title: '',
            message: '',
            onConfirm: function() {}
        },
        modalError: {
            message: ''
        },
        
        // Alert message state
        alert: {
            show: false,
            type: 'success',
            message: ''
        },

        // Computed properties using getters
        get filteredUsers() {
            var self = this;
            var filtered = this.users.filter(function(user) {
                // Search filter
                if (self.searchTerm) {
                    var searchLower = self.searchTerm.toLowerCase();
                    if (user.username.toLowerCase().indexOf(searchLower) === -1) {
                        return false;
                    }
                }
                
                // Advanced filters
                if (self.filters.hasKeys && (!user.keys || user.keys.length === 0)) {
                    return false;
                }
                if (self.filters.hasSubusers && (!user.subusers || user.subusers.length === 0)) {
                    return false;
                }
                if (self.filters.hasStorage && (!user.total_storage || user.total_storage === '0 B')) {
                    return false;
                }
                
                return true;
            });

            // Sort
            return filtered.sort(function(a, b) {
                var aVal = a[self.sortBy] || '';
                var bVal = b[self.sortBy] || '';
                
                // Handle numeric sorting
                if (self.sortBy.indexOf('_count') > -1 || self.sortBy === 'total_buckets') {
                    aVal = parseInt(aVal) || 0;
                    bVal = parseInt(bVal) || 0;
                }
                
                if (self.sortOrder === 'desc') {
                    return bVal > aVal ? 1 : -1;
                }
                return aVal > bVal ? 1 : -1;
            });
        },

        get totalPages() {
            return Math.ceil(this.filteredUsers.length / this.pageSize);
        },

        get pageNumbers() {
            var pages = [];
            var start = Math.max(1, this.currentPage - 2);
            var end = Math.min(this.totalPages, this.currentPage + 2);
            
            for (var i = start; i <= end; i++) {
                pages.push(i);
            }
            return pages;
        },

        get paginatedUsers() {
            var start = (this.currentPage - 1) * this.pageSize;
            var end = start + this.pageSize;
            return this.filteredUsers.slice(start, end);
        },

        // Methods
        init: function() {
            var self = this;
            console.log('Initializing users manager with data:', this.users.length, 'users');
            // Initialize any data transformations
            if (this.users && Array.isArray(this.users)) {
                this.users = this.users.map(function(user) {
                    return Object.assign({}, user, {
                        keys_count: user.keys ? user.keys.length : 0,
                        subusers_count: user.subusers ? user.subusers.length : 0
                    });
                });
                console.log('Users initialized successfully:', this.users.length, 'users');
            } else {
                console.warn('No users data found, initializing empty array');
                this.users = [];
            }
        },

        showAlert: function(message, type) {
            var self = this;
            this.alert = {
                show: true,
                type: type || 'success',
                message: message
            };
            
            setTimeout(function() {
                self.alert.show = false;
            }, 5000);
        },

        resetNewUser: function() {
            this.newUser = { name: '', username: '' };
        },

        toggleSelectAll: function() {
            var self = this;
            if (this.selectedUsers.length === this.filteredUsers.length) {
                this.selectedUsers = [];
            } else {
                this.selectedUsers = this.filteredUsers.map(function(user) {
                    return user.username;
                });
            }
        },

        toggleExpanded: function(username) {
            var index = this.expandedRows.indexOf(username);
            if (index > -1) {
                this.expandedRows.splice(index, 1);
            } else {
                this.expandedRows.push(username);
            }
        },

        // User Management
        createUser: function() {
            var self = this;
            var uname = (this.newUser.username || '').trim();
            if (!uname) {
                this.showAlert('Please enter a username', 'error');
                return;
            }
            // Use username as account name
            this.newUser.name = uname;
            this.newUser.username = uname;

            this.loading = true;
            this.loadingText = 'Creating user...';
            
            this.apiCall('modules/addons/cloudstorage/api/managedusers.php', {
                username: uname,
                name: uname,
                action: 'addtenant'
            }).then(function(response) {
                if (response.status === 'success') {
                    // Add the new user to the list
                    var newUser = {
                        username: response.data.username,
                        tenant_id: response.data.tenant_id || null,
                        total_buckets: 0,
                        total_storage: '0 B',
                        keys: response.data.key_id ? [{ key_id: response.data.key_id }] : [],
                        subusers: [],
                        keys_count: response.data.key_id ? 1 : 0,
                        subusers_count: 0
                    };
                    self.users.unshift(newUser);
                    
                    self.showCreateForm = false;
                    self.resetNewUser();
                    self.showAlert(response.message);
                    try { window.dispatchEvent(new CustomEvent('close-create-user-slideover')); } catch (_){}
                } else {
                    self.showAlert(response.message, 'error');
                }
            }).catch(function(error) {
                self.showAlert('Failed to create user', 'error');
            }).finally(function() {
                self.loading = false;
            });
        },

        deleteUser: function(username) {
            var self = this;
            this.confirmModal = {
                title: 'Delete User',
                message: 'Are you sure you want to delete user "' + username + '"? This action cannot be undone.',
                onConfirm: function() {
                    self.confirmDeleteUser(username);
                }
            };
            this.modals.confirm = true;
        },

        confirmDeleteUser: function(username) {
            var self = this;
            this.loading = true;
            this.loadingText = 'Deleting user...';
            
            this.apiCall('modules/addons/cloudstorage/api/managedusers.php', {
                username: username,
                action: 'deletetenant'
            }).then(function(response) {
                if (response.status === 'success') {
                    // Remove user from list
                    self.users = self.users.filter(function(user) {
                        return user.username !== username;
                    });
                    // Remove from selected if present
                    self.selectedUsers = self.selectedUsers.filter(function(u) {
                        return u !== username;
                    });
                    // Remove from expanded if present
                    self.expandedRows = self.expandedRows.filter(function(u) {
                        return u !== username;
                    });
                    
                    self.showAlert(response.message);
                } else {
                    self.showAlert(response.message, 'error');
                }
            }).catch(function(error) {
                self.showAlert('Failed to delete user', 'error');
            }).finally(function() {
                self.loading = false;
            });
        },

        bulkDelete: function() {
            var self = this;
            if (this.selectedUsers.length === 0) return;
            
            this.confirmModal = {
                title: 'Delete Users',
                message: 'Are you sure you want to delete ' + this.selectedUsers.length + ' selected users? This action cannot be undone.',
                onConfirm: function() {
                    self.confirmBulkDelete();
                }
            };
            this.modals.confirm = true;
        },

        confirmBulkDelete: function() {
            var self = this;
            this.loading = true;
            this.loadingText = 'Deleting users...';
            
            var deletePromises = this.selectedUsers.map(function(username) {
                return self.apiCall('modules/addons/cloudstorage/api/managedusers.php', {
                    username: username,
                    action: 'deletetenant'
                });
            });
            
            Promise.all(deletePromises).then(function() {
                // Remove users from list
                self.users = self.users.filter(function(user) {
                    return self.selectedUsers.indexOf(user.username) === -1;
                });
                self.selectedUsers = [];
                self.showAlert('Selected users deleted successfully');
            }).catch(function(error) {
                self.showAlert('Some users could not be deleted', 'error');
            }).finally(function() {
                self.loading = false;
            });
        },

        // Key Management
        createPrimaryKey: function(username) {
            var self = this;
            this.confirmModal = {
                title: 'Generate API Keys',
                message: 'Generate new API keys for "' + username + '"? These keys will provide full access to all buckets owned by this user.',
                onConfirm: function() {
                    self.confirmCreatePrimaryKey(username);
                }
            };
            this.modals.confirm = true;
        },

        confirmCreatePrimaryKey: function(username) {
            var self = this;
            this.loading = true;
            this.loadingText = 'Creating API keys...';
            
            this.apiCall('modules/addons/cloudstorage/api/managedusers.php', {
                username: username,
                type: 'primary',
                action: 'addkey'
            }).then(function(response) {
                if (response.status === 'success') {
                    // Update the user in the list
                    var userIndex = self.users.findIndex(function(u) {
                        return u.username === username;
                    });
                    if (userIndex > -1) {
                        if (!self.users[userIndex].keys) {
                            self.users[userIndex].keys = [];
                        }
                        self.users[userIndex].keys.push({ key_id: response.data.key_id });
                        self.users[userIndex].keys_count = self.users[userIndex].keys.length;
                    }
                    
                    self.showAlert(response.message);
                } else {
                    self.showAlert(response.message, 'error');
                }
            }).catch(function(error) {
                self.showAlert('Failed to create API keys', 'error');
            }).finally(function() {
                self.loading = false;
            });
        },

        createSubuser: function(username) {
            this.subuserForm = {
                username: '',
                permission: 'full',
                parentUser: username
            };
            this.modalError.message = '';
            this.modals.createSubuser = true;
        },

        submitCreateSubuser: function() {
            var self = this;
            if (!this.subuserForm.username.trim()) {
                this.modalError.message = 'Please enter a username';
                return;
            }

            this.loading = true;
            this.loadingText = 'Creating subuser...';
            
            this.apiCall('modules/addons/cloudstorage/api/managedusers.php', {
                username: this.subuserForm.parentUser,
                subusername: this.subuserForm.username,
                type: 'subuser',
                access: this.subuserForm.permission,
                action: 'addkey'
            }).then(function(response) {
                if (response.status === 'success') {
                    // Update the user in the list
                    var userIndex = self.users.findIndex(function(u) {
                        return u.username === self.subuserForm.parentUser;
                    });
                    if (userIndex > -1) {
                        if (!self.users[userIndex].subusers) {
                            self.users[userIndex].subusers = [];
                        }
                        self.users[userIndex].subusers.push({
                            key_id: response.data.key_id,
                            subuser: response.data.subusername,
                            permission: response.data.permission
                        });
                        self.users[userIndex].subusers_count = self.users[userIndex].subusers.length;
                    }
                    
                    self.modals.createSubuser = false;
                    self.showAlert(response.message);
                } else {
                    self.modalError.message = response.message;
                }
            }).catch(function(error) {
                self.modalError.message = 'Failed to create subuser';
            }).finally(function() {
                self.loading = false;
            });
        },

        viewKey: function(keyId, keyType, username) {
            // Always require password verification for viewing keys (do not rely on localStorage)
            this.passwordForm = {
                password: '',
                action: 'viewkey',
                keyId: keyId,
                keyType: keyType,
                username: username
            };
            this.modalError.message = '';
            this.modals.password = true;
        },

        executeViewKey: function(keyId, keyType, username) {
            var self = this;
            this.loading = true;
            this.loadingText = 'Fetching keys...';
            
            this.apiCall('modules/addons/cloudstorage/api/managedusers.php', {
                id: keyId,
                type: keyType,
                username: username,
                action: 'decryptkey'
            }).then(function(response) {
                if (response.status === 'success') {
                    // Find the user and update the key with decrypted values
                    var targetUser = self.users.find(function(user) {
                        return user.username === username;
                    });
                    
                    if (targetUser) {
                        if (keyType === 'primary' && targetUser.keys) {
                            // Update primary key
                            var targetKey = targetUser.keys.find(function(key) {
                                return key.key_id === keyId;
                            });
                            if (targetKey) {
                                targetKey.decrypted = true;
                                targetKey.access_key = response.keys.access_key;
                                targetKey.secret_key = response.keys.secret_key;
                            }
                        } else if (keyType === 'subuser' && targetUser.subusers) {
                            // Update subuser key
                            var targetSubuser = targetUser.subusers.find(function(subuser) {
                                return subuser.key_id === keyId;
                            });
                            if (targetSubuser) {
                                targetSubuser.decrypted = true;
                                targetSubuser.access_key = response.keys.access_key;
                                targetSubuser.secret_key = response.keys.secret_key;
                            }
                        }
                    }
                    
                    self.showAlert('Keys decrypted successfully! You can now copy them.', 'success');
                } else {
                    self.showAlert(response.message, 'error');
                }
            }).catch(function(error) {
                self.showAlert('Failed to retrieve keys', 'error');
            }).finally(function() {
                self.loading = false;
            });
        },

        hideKey: function(keyId, keyType, username) {
            var self = this;
            // Find the user and hide the key by setting decrypted to false
            var targetUser = this.users.find(function(user) {
                return user.username === username;
            });
            
            if (targetUser) {
                if (keyType === 'primary' && targetUser.keys) {
                    // Hide primary key
                    var targetKey = targetUser.keys.find(function(key) {
                        return key.key_id === keyId;
                    });
                    if (targetKey) {
                        targetKey.decrypted = false;
                        delete targetKey.access_key;
                        delete targetKey.secret_key;
                    }
                } else if (keyType === 'subuser' && targetUser.subusers) {
                    // Hide subuser key
                    var targetSubuser = targetUser.subusers.find(function(subuser) {
                        return subuser.key_id === keyId;
                    });
                    if (targetSubuser) {
                        targetSubuser.decrypted = false;
                        delete targetSubuser.access_key;
                        delete targetSubuser.secret_key;
                    }
                }
            }
            
            this.showAlert('Keys hidden successfully.', 'success');
        },

        deleteKey: function(keyId, keyType, username) {
            var self = this;
            var keyTypeText = keyType === 'primary' ? 'API key' : 'subuser';
            this.confirmModal = {
                title: 'Delete Key',
                message: 'Are you sure you want to delete this ' + keyTypeText + '? This action cannot be undone.',
                onConfirm: function() {
                    self.confirmDeleteKey(keyId, keyType, username);
                }
            };
            this.modals.confirm = true;
        },

        confirmDeleteKey: function(keyId, keyType, username) {
            var self = this;
            this.loading = true;
            this.loadingText = 'Deleting key...';
            
            this.apiCall('modules/addons/cloudstorage/api/managedusers.php', {
                id: keyId,
                type: keyType,
                username: username,
                action: 'deletekey'
            }).then(function(response) {
                if (response.status === 'success') {
                    // Update the user in the list
                    var userIndex = self.users.findIndex(function(u) {
                        return u.username === username;
                    });
                    if (userIndex > -1) {
                        if (keyType === 'primary') {
                            self.users[userIndex].keys = (self.users[userIndex].keys || []).filter(function(k) {
                                return k.key_id !== keyId;
                            });
                            self.users[userIndex].keys_count = self.users[userIndex].keys.length;
                        } else {
                            self.users[userIndex].subusers = (self.users[userIndex].subusers || []).filter(function(s) {
                                return s.key_id !== keyId;
                            });
                            self.users[userIndex].subusers_count = self.users[userIndex].subusers.length;
                        }
                    }
                    
                    self.showAlert(response.message);
                } else {
                    self.showAlert(response.message, 'error');
                }
            }).catch(function(error) {
                self.showAlert('Failed to delete key', 'error');
            }).finally(function() {
                self.loading = false;
            });
        },

        submitPassword: function() {
            var self = this;
            if (!this.passwordForm.password.trim()) {
                this.modalError.message = 'Please enter your password';
                return;
            }

            this.apiCall('modules/addons/cloudstorage/api/validatepassword.php', {
                password: this.passwordForm.password
            }).then(function(response) {
                if (response.status === 'success') {
                    self.modals.password = false;
                    
                    if (self.passwordForm.action === 'viewkey') {
                        self.executeViewKey(self.passwordForm.keyId, self.passwordForm.keyType, self.passwordForm.username);
                    }
                } else {
                    self.modalError.message = response.message;
                }
            }).catch(function(error) {
                self.modalError.message = 'Invalid password';
            });
        },

        // Utility methods
        apiCall: function(url, data) {
            var formData = new URLSearchParams();
            for (var key in data) {
                formData.append(key, data[key]);
            }
            
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            }).then(function(response) {
                return response.json();
            });
        },

        copyToClipboard: function(text) {
            var self = this;
            if (text && text.trim()) {
                navigator.clipboard.writeText(text).then(function() {
                    self.showAlert('Key copied to clipboard!', 'success');
                }, function(err) {
                    console.error('Failed to copy text: ', err);
                    self.showAlert('Failed to copy to clipboard', 'error');
                });
            } else {
                self.showAlert('No key available to copy', 'error');
            }
        }
    };
}
</script>
