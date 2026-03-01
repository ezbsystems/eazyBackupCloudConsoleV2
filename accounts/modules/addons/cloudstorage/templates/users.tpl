<style>
    [x-cloak] { display: none !important; }
    .table-row-hover:hover { background-color: #1e293b; }
    .expanded-row { background-color: #0f172a; }
</style>
<!-- ebLoader -->
<script src="{$WEB_ROOT}/modules/addons/eazybackup/templates/assets/js/ui.js"></script>

<div class="min-h-screen bg-slate-950 text-gray-300" x-data="usersManager()">
	{* <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div> *}
	<div class="container mx-auto px-4 pb-10 pt-6 relative pointer-events-auto">

		<!-- Cloud Storage Navigation (moved above content) -->


		
		<!-- Cloud Storage Navigation -->
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
		
		<!-- Glass Container -->
		<div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
		<!-- Header Section -->
		<div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
			<div class="flex items-center">
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 mr-2">
					<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
				</svg>
				<h1 class="text-2xl font-semibold text-white">Manage Users</h1>
				<span class="ml-3 px-2 py-1 bg-slate-700 text-slate-300 text-sm rounded" x-text="filteredUsers.length + ' users'"></span>
			</div>
		

		<!-- Actions Bar -->
			<div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto min-w-0">
				<!-- Search -->
				<div class="relative">
					<input type="text" 
						x-model="searchTerm" 
						placeholder="Search users..."
						class="bg-slate-800 text-gray-300 placeholder-slate-400 focus:ring-sky-500 focus:border-sky-500 block w-full sm:w-48 lg:w-64 px-4 py-2 border border-slate-600 rounded-md focus:outline-none">
					<svg class="absolute right-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
					</svg>
				</div>
				
				<!-- Filter Dropdown -->
				<div class="relative" x-data="{ open: false }">
					<button @click="open = !open" @click.away="open = false"
						class="bg-slate-800 hover:bg-slate-700 text-gray-300 px-4 py-2 border border-slate-600 rounded-md focus:outline-none flex items-center">
						<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
						</svg>
						Filter
					</button>
					<div x-show="open" x-cloak
						class="absolute right-0 z-50 mt-2 w-48 bg-slate-800 border border-slate-600 rounded-md shadow-lg scrollbar_thin">
						<div class="p-2">
							<label class="flex items-center mb-2">
								<input type="checkbox" x-model="filters.hasKeys" class="mr-2 text-sky-600">
								<span class="text-sm">Has API Keys</span>
							</label>
							<label class="flex items-center mb-2">
								<input type="checkbox" x-model="filters.hasSubusers" class="mr-2 text-sky-600">
								<span class="text-sm">Has Subusers</span>
							</label>
							<label class="flex items-center">
								<input type="checkbox" x-model="filters.hasStorage" class="mr-2 text-sky-600">
								<span class="text-sm">Has Storage</span>
							</label>
						</div>
					</div>
				</div>
				
				<!-- Bulk Actions -->
				<div x-show="selectedUsers.length > 0" class="flex items-center gap-2">
					<span class="text-sm text-slate-400" x-text="selectedUsers.length + ' selected'"></span>
					<button @click="bulkDelete" 
						class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-md text-sm">
						Delete Selected
					</button>
				</div>
				
				<!-- Create User Button -->
				<button onclick="openCreateUserSlideover()"
					class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md focus:outline-none whitespace-nowrap">
					<svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
					</svg>
					Create User
				</button>
			</div>
		</div>

		<!-- Create User Slide-Over -->
		<div id="createUserSlideover" x-data="{ isOpen: false }" x-init="
			window.addEventListener('open-create-user-slideover', () => { isOpen = true });
			window.addEventListener('close-create-user-slideover', () => { isOpen = false });
		" x-show="isOpen" class="fixed inset-0 z-50" style="display: none;">
			<!-- Backdrop -->
			<div class="absolute inset-0 bg-black/75"
				 x-show="isOpen"
				 x-transition.opacity
				 onclick="closeCreateUserSlideover()"></div>
			<!-- Panel -->
			<div class="absolute right-0 top-0 h-full w-full max-w-xl bg-slate-950 border-l border-slate-800/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] overflow-y-auto"
				 x-show="isOpen"
				 x-transition:enter="transform transition ease-in-out duration-300"
				 x-transition:enter-start="translate-x-full"
				 x-transition:enter-end="translate-x-0"
				 x-transition:leave="transform transition ease-in-out duration-300"
				 x-transition:leave-start="translate-x-0"
				 x-transition:leave-end="translate-x-full">
				<div class="flex items-center justify-between p-4 border-b border-slate-700">
					<h3 class="text-lg font-semibold text-white">Create User</h3>
					<button class="text-slate-300 hover:text-white" onclick="closeCreateUserSlideover()">
						<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
						</svg>
					</button>
				</div>
				<div class="p-4">
					<style>
					#createUserSlideover ::placeholder { color: #94a3b8; opacity: 1; }
					#createUserSlideover .border-slate-700 { border-color: rgba(51,65,85,1); }
					#createUserSlideover input[type="text"] {
						background-color: rgb(15 23 42) !important;
						border-color: rgba(51,65,85,1) !important;
						color: #e2e8f0 !important;
					}
					#createUserSlideover input:focus {
						outline: none !important;
						border-color: rgb(14 165 233 / 1) !important;
						box-shadow: 0 0 0 1px rgb(14 165 233 / 1) !important;
					}
					</style>

					<div id="createUserMessage" class="bg-red-600 text-white px-4 py-2 rounded-md mb-4 hidden"></div>

					<div class="mb-4">
						<label class="block text-sm font-medium text-slate-300 mb-2">Username</label>
						<input type="text" x-model="newUser.username" placeholder="e.g., acme-corp"
							   class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
						
					</div>

					<div class="flex justify-end space-x-2 mt-6">
						<button type="button" class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-md" onclick="closeCreateUserSlideover()">Cancel</button>
						<button type="button" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md" @click="createUser(); closeCreateUserSlideover()">Confirm</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Alert Messages -->
		<div x-show="alert.show" x-cloak x-transition
			class="px-4 py-3 rounded-md mb-6" 
			:class="alert.type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'">
			<span x-text="alert.message"></span>
		</div>

		<!-- Users Table -->
		<div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg overflow-hidden">
			<!-- Table Controls -->
			<div class="flex flex-col sm:flex-row justify-between items-center p-4 border-b border-slate-700 gap-3">
				<div class="flex items-center gap-4">
					<!-- Select All -->
					<label class="flex items-center">
						<input type="checkbox" 
							:checked="selectedUsers.length === filteredUsers.length && filteredUsers.length > 0"
							@change="toggleSelectAll"
							class="mr-2 text-sky-600">
						<span class="text-sm text-slate-400">Select All</span>
					</label>
					
					<!-- Sort Controls -->
					<div class="flex items-center gap-2">
						<span class="text-sm text-slate-400">Sort by:</span>
						<select x-model="sortBy" 
							class="bg-slate-700 border border-slate-600 text-gray-300 text-sm rounded pl-3 pr-8 py-1 min-w-[120px] scrollbar_thin appearance-none">
							<option value="username">Username</option>
							<option value="total_buckets">Buckets</option>
							<option value="total_storage">Storage</option>
							<option value="keys_count">API Keys</option>
							<option value="subusers_count">Subusers</option>
						</select>
						<button @click="sortOrder = sortOrder === 'asc' ? 'desc' : 'asc'"
							class="text-slate-400 hover:text-slate-200">
							<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
									x-bind:d="sortOrder === 'asc' ? 'M3 4l9 16 9-16H3z' : 'M21 20L12 4 3 20h18z'"></path>
							</svg>
						</button>
					</div>
				</div>
				
				<!-- Pagination Info -->
				<div class="text-sm text-slate-400">
					<span>Showing </span>
					<span x-text="((currentPage - 1) * pageSize) + 1"></span>
					<span> to </span>
					<span x-text="Math.min(currentPage * pageSize, filteredUsers.length)"></span>
					<span> of </span>
					<span x-text="filteredUsers.length"></span>
					<span> users</span>
				</div>
			</div>

			<!-- Table -->
			<div class="overflow-x-auto">
				<table class="min-w-full divide-y divide-slate-700">
					<thead class="bg-slate-900">
						<tr>
							<th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider w-12">
								<!-- Checkbox column -->
							</th>
							<th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">
								Username
							</th>
							<th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">
								Buckets
							</th>
							<th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">
								Storage
							</th>
							<th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">
								API Keys
							</th>
							<th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">
								Subusers
							</th>
							<th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">
								Actions
							</th>
						</tr>
					</thead>
					<!-- User Rows with Inline Expanded Sections -->
					<template x-for="(user, index) in paginatedUsers" :key="user.username">
						<tbody class="bg-slate-800 divide-y divide-slate-700">
							<!-- Main Row -->
							<tr class="table-row-hover cursor-pointer" 
								:class="{ 'bg-slate-700': selectedUsers.includes(user.username) }"
								@click="toggleExpanded(user.username)">
								<td class="px-6 py-4 whitespace-nowrap" @click.stop>
									<input type="checkbox" :value="user.username" x-model="selectedUsers" class="text-sky-600">
								</td>
								<td class="px-6 py-4 whitespace-nowrap">
									<div class="flex items-center">
										<div class="mr-3 text-slate-400">
											<svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-90': expandedRows.includes(user.username) }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
											</svg>
										</div>
										<div>
											<div class="text-sm font-medium text-white">
												<span class="text-slate-500 font-normal">Username:</span>
												<span x-text="user.username"></span>
											</div>
											<div class="mt-1 flex items-center gap-2 text-xs text-slate-400" @click.stop>
												<span class="text-slate-500">Account ID</span>
												<span class="font-mono text-slate-300" x-text="user.tenant_id ? String(user.tenant_id) : '—'"></span>
												<button
													x-show="user.tenant_id"
													@click.stop="copyToClipboard(String(user.tenant_id))"
													class="text-slate-400 hover:text-slate-200 p-1 bg-slate-800 hover:bg-slate-700 rounded"
													title="Copy Account ID">
													<svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
														<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M16 3h2a2 2 0 012 2v10a2 2 0 01-2 2H10a2 2 0 01-2-2V5a2 2 0 012-2h2" />
													</svg>
												</button>
											</div>
										</div>
									</div>
								</td>
								<td class="px-6 py-4 whitespace-nowrap">
									<span class="text-sm text-gray-300" x-text="user.total_buckets || '0'"></span>
								</td>
								<td class="px-6 py-4 whitespace-nowrap">
									<span class="text-sm text-gray-300" x-html="user.total_storage || '0 B'"></span>
								</td>
								<td class="px-6 py-4 whitespace-nowrap">
									<span class="text-sm text-gray-300" x-text="user.keys ? user.keys.length : '0'"></span>
								</td>
								<td class="px-6 py-4 whitespace-nowrap">
									<span class="text-sm text-gray-300" x-text="user.subusers ? user.subusers.length : '0'"></span>
								</td>
								<td class="px-6 py-4 whitespace-nowrap" @click.stop>
									<div class="flex items-center space-x-2">
										<button @click="createPrimaryKey(user.username)" class="text-green-400 hover:text-green-300 p-1" title="Generate API Keys">
											<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
											</svg>
										</button>
										<button @click="createSubuser(user.username)" class="text-blue-400 hover:text-blue-300 p-1" title="Create Subuser">
											<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
											</svg>
										</button>
										<button @click="deleteUser(user.username)" class="text-red-400 hover:text-red-300 p-1" title="Delete User">
											<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4a2 2 0 012 2v2H8V5a2 2 0 012-2z"></path>
											</svg>
										</button>
									</div>
								</td>
							</tr>

							<!-- Expanded Row - Appears directly under main row -->
							<tr x-show="expandedRows.includes(user.username)" x-cloak class="expanded-row">
								<td colspan="7" class="px-6 py-4">
									<div class="mb-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs">
										<div class="flex items-center gap-2">
											<span class="text-slate-500">Account ID</span>
											<span class="font-mono text-slate-200" x-text="user.tenant_id ? String(user.tenant_id) : '—'"></span>
											<button
												x-show="user.tenant_id"
												@click.stop="copyToClipboard(String(user.tenant_id))"
												class="text-slate-400 hover:text-slate-200 p-1 bg-slate-800 hover:bg-slate-700 rounded"
												title="Copy Account ID">
												<svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M16 3h2a2 2 0 012 2v10a2 2 0 01-2 2H10a2 2 0 01-2-2V5a2 2 0 012-2h2" />
												</svg>
											</button>
										</div>
									</div>
									<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
										<!-- API Keys Section -->
										<div>
											<div class="flex justify-between items-center mb-3">
												<h4 class="text-sm font-semibold text-white">API Keys</h4>
												<button @click="createPrimaryKey(user.username)" class="text-xs bg-sky-600 hover:bg-sky-700 text-white px-2 py-1 rounded">
													+ Generate API Keys
												</button>
											</div>
											<div class="space-y-2 max-h-64 overflow-y-auto scrollbar_thin">
												<template x-for="key in user.keys || []" :key="key.key_id">
													<div class="bg-slate-700 rounded p-3">
														<div class="flex justify-between items-start mb-3">
															<div class="flex-1">
																<div class="text-xs text-slate-400">API Key Pair</div>
															</div>
															<div class="flex gap-2">
																<button @click="viewKey(key.key_id, 'primary', user.username)" 
																	x-show="!key.decrypted"
																	class="text-cyan-400 hover:text-cyan-300 text-xs px-2 py-1 bg-slate-800 rounded flex items-center gap-1" title="Decrypt Keys">
																	<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 6 0z"></path>
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
																	</svg>
																	<span class="hidden sm:inline">Decrypt</span>
																</button>
																<button @click="hideKey(key.key_id, 'primary', user.username)" 
																	x-show="key.decrypted"
																	class="text-orange-400 hover:text-orange-300 text-xs px-2 py-1 bg-slate-800 rounded flex items-center gap-1" title="Hide Keys">
																	<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L8.464 8.464M14.121 14.121l1.414 1.414M3 3l18 18"></path>
																	</svg>
																	<span class="hidden sm:inline">Hide</span>
																</button>
																<button @click="deleteKey(key.key_id, 'primary', user.username)" class="text-red-400 hover:text-red-300 text-xs px-2 py-1 bg-slate-800 rounded flex items-center gap-1" title="Delete Key">
																	<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4a2 2 0 012 2v2H8V5a2 2 0 012-2z"></path>
																	</svg>
																	<span class="hidden sm:inline">Delete</span>
																</button>
															</div>
														</div>
														
														<!-- Access Key Field -->
														<div class="mb-3">
															<label class="block text-xs text-slate-400 mb-1">Access Key</label>
															<div class="flex items-center gap-2">
																<input type="text" readonly 
																	:value="key.decrypted ? key.access_key : '••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••'"
																	class="flex-1 bg-slate-800 border border-slate-600 text-slate-300 text-xs px-2 py-1 rounded font-mono select-all"
																	:class="{ 'text-green-500 border-green-600': key.decrypted }">
																<button @click="copyToClipboard(key.access_key)" 
																	x-show="key.decrypted"
																	class="text-slate-400 hover:text-slate-200 text-xs px-2 py-1 bg-slate-600 hover:bg-slate-500 border border-slate-500 rounded"
																	title="Copy Access Key">
																	<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
																		<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
																	</svg>
																</button>
															</div>
														</div>
														
														<!-- Secret Key Field -->
														<div>
															<label class="block text-xs text-slate-400 mb-1">Secret Key</label>
															<div class="flex items-center gap-2">
																<input type="text" readonly 
																	:value="key.decrypted ? key.secret_key : '••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••'"
																	class="flex-1 bg-slate-800 border border-slate-600 text-slate-300 text-xs px-2 py-1 rounded font-mono select-all"
																	:class="{ 'text-green-500 border-green-600': key.decrypted }">
																<button @click="copyToClipboard(key.secret_key)" 
																	x-show="key.decrypted"
																	class="text-slate-400 hover:text-slate-200 text-xs px-2 py-1 bg-slate-600 hover:bg-slate-500 border border-slate-500 rounded"
																	title="Copy Secret Key">
																	<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
																		<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
																	</svg>
																</button>
															</div>
														</div>
													</div>
												</template>
												<div x-show="!user.keys || user.keys.length === 0" class="text-center text-slate-400 py-4 text-sm">
													No API keys
												</div>
											</div>
										</div>
										
										<!-- Subusers Section -->
										<div>
											<div class="flex justify-between items-center mb-3">
												<h4 class="text-sm font-semibold text-white">Subusers</h4>
												<button @click="createSubuser(user.username)" class="text-xs bg-sky-600 hover:bg-sky-700 text-white px-2 py-1 rounded">
													+ Create Subuser
												</button>
											</div>
											<!-- Compact List View for Multiple Subusers -->
											<div class="space-y-1 max-h-64 overflow-y-auto scrollbar_thin" x-show="(user.subusers || []).length > 1">
												<template x-for="subuser in user.subusers || []" :key="subuser.key_id">
													<div class="bg-slate-700 border border-slate-600 rounded-md">
														<!-- Compact Header -->
														<div class="flex items-center justify-between p-2">
															<div class="flex-1 min-w-0">
																<div class="text-sm font-medium text-white truncate" x-text="subuser.subuser"></div>
																<div class="flex items-center gap-2 text-xs text-slate-400">
																	<span x-text="subuser.permission"></span>
																	<span x-show="subuser.decrypted" class="text-green-400 font-medium">● Decrypted</span>
																</div>
															</div>
															<div class="flex items-center gap-1 ml-2">
																<button @click="viewKey(subuser.key_id, 'subuser', user.username)" 
																	x-show="!subuser.decrypted"
																	class="text-cyan-400 hover:text-cyan-300 p-1.5 bg-slate-800 hover:bg-slate-700 rounded" 
																	title="Decrypt Keys">
																	<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 6 0z"></path>
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
																	</svg>
																</button>
																<button @click="hideKey(subuser.key_id, 'subuser', user.username)" 
																	x-show="subuser.decrypted"
																	class="text-orange-400 hover:text-orange-300 p-1.5 bg-slate-800 hover:bg-slate-700 rounded" 
																	title="Hide Keys">
																	<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L8.464 8.464M14.121 14.121l1.414 1.414M3 3l18 18"></path>
																	</svg>
																</button>
																<button @click="deleteKey(subuser.key_id, 'subuser', user.username)" 
																	class="text-red-400 hover:text-red-300 p-1.5 bg-slate-800 hover:bg-slate-700 rounded" 
																	title="Delete Subuser">
																	<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4a2 2 0 012 2v2H8V5a2 2 0 012-2z"></path>
																	</svg>
																</button>
															</div>
														</div>
														
													<!-- Expandable Keys Section - Only show when decrypted -->
													<div x-show="subuser.decrypted" x-transition class="border-t border-slate-600 p-3 space-y-3">
															<!-- Access Key -->
															<div>
																<label class="block text-xs text-slate-400 mb-1">Access Key</label>
																<div class="flex items-center gap-2">
																																	<input type="text" readonly :value="subuser.access_key"
																	class="flex-1 bg-slate-800 border border-green-600 text-green-500 text-xs px-2 py-1 rounded font-mono select-all">
																	<button @click="copyToClipboard(subuser.access_key)" 
																		class="text-slate-400 hover:text-slate-200 p-1.5 bg-slate-600 hover:bg-slate-500 border border-slate-500 rounded"
																		title="Copy Access Key">
																		<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
																			<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
																		</svg>
																	</button>
																</div>
															</div>
															
															<!-- Secret Key -->
															<div>
																<label class="block text-xs text-slate-400 mb-1">Secret Key</label>
																<div class="flex items-center gap-2">
																																	<input type="text" readonly :value="subuser.secret_key"
																	class="flex-1 bg-slate-800 border border-green-600 text-green-500 text-xs px-2 py-1 rounded font-mono select-all">
																	<button @click="copyToClipboard(subuser.secret_key)" 
																		class="text-slate-400 hover:text-slate-200 p-1.5 bg-slate-600 hover:bg-slate-500 border border-slate-500 rounded"
																		title="Copy Secret Key">
																		<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
																			<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
																		</svg>
																	</button>
																</div>
															</div>
														</div>
													</div>
												</template>
											</div>

											<!-- Original Card View for Single Subuser -->
											<div class="space-y-2 max-h-64 overflow-y-auto scrollbar_thin" x-show="(user.subusers || []).length <= 1">
												<template x-for="subuser in user.subusers || []" :key="subuser.key_id + '-single'">
													<div class="bg-slate-700 rounded p-3">
														<div class="flex justify-between items-start mb-3">
															<div class="flex-1">
																<div class="text-sm text-white" x-text="subuser.subuser"></div>
																<div class="text-xs text-slate-400" x-text="subuser.permission"></div>
															</div>
															<div class="flex gap-2">
																<button @click="viewKey(subuser.key_id, 'subuser', user.username)" 
																	x-show="!subuser.decrypted"
																	class="text-cyan-400 hover:text-cyan-300 text-xs px-2 py-1 bg-slate-800 rounded flex items-center gap-1" title="Decrypt Keys">
																	<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 6 0z"></path>
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
																	</svg>
																	<span class="hidden sm:inline">Decrypt</span>
																</button>
																<button @click="hideKey(subuser.key_id, 'subuser', user.username)" 
																	x-show="subuser.decrypted"
																	class="text-orange-400 hover:text-orange-300 text-xs px-2 py-1 bg-slate-800 rounded flex items-center gap-1" title="Hide Keys">
																	<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L8.464 8.464M14.121 14.121l1.414 1.414M3 3l18 18"></path>
																	</svg>
																	<span class="hidden sm:inline">Hide</span>
																</button>
																<button @click="deleteKey(subuser.key_id, 'subuser', user.username)" class="text-red-400 hover:text-red-300 text-xs px-2 py-1 bg-slate-800 rounded flex items-center gap-1" title="Delete Key">
																	<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
																		<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4a2 2 0 012 2v2H8V5a2 2 0 012-2z"></path>
																	</svg>
																	<span class="hidden sm:inline">Delete</span>
																</button>
															</div>
														</div>
														
														<!-- Access Key Field -->
														<div class="mb-3">
															<label class="block text-xs text-slate-400 mb-1">Access Key</label>
															<div class="flex items-center gap-2">
																<input type="text" readonly 
																	:value="subuser.decrypted ? subuser.access_key : '••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••'"
																	class="flex-1 bg-slate-800 border border-slate-600 text-slate-300 text-xs px-2 py-1 rounded font-mono select-all"
																	:class="{ 'text-green-300 border-green-600': subuser.decrypted }">
																<button @click="copyToClipboard(subuser.access_key)" 
																	x-show="subuser.decrypted"
																	class="text-slate-400 hover:text-slate-200 text-xs px-2 py-1 bg-slate-600 hover:bg-slate-500 border border-slate-500 rounded"
																	title="Copy Access Key">
																	<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
																		<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
																	</svg>
																</button>
															</div>
														</div>
														
														<!-- Secret Key Field -->
														<div>
															<label class="block text-xs text-slate-400 mb-1">Secret Key</label>
															<div class="flex items-center gap-2">
																<input type="text" readonly 
																	:value="subuser.decrypted ? subuser.secret_key : '••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••'"
																	class="flex-1 bg-slate-800 border border-slate-600 text-slate-300 text-xs px-2 py-1 rounded font-mono select-all"
																	:class="{ 'text-green-500 border-green-600': subuser.decrypted }">
																<button @click="copyToClipboard(subuser.secret_key)" 
																	x-show="subuser.decrypted"
																	class="text-slate-400 hover:text-slate-200 text-xs px-2 py-1 bg-slate-600 hover:bg-slate-500 border border-slate-500 rounded"
																	title="Copy Secret Key">
																	<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
																		<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
																	</svg>
																</button>
															</div>
														</div>
													</div>
												</template>
												<div x-show="!user.subusers || user.subusers.length === 0" class="text-center text-slate-400 py-4 text-sm">
													No subusers
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

			<!-- Table Footer with Pagination -->
			<div class="flex flex-col sm:flex-row justify-between items-center p-4 border-t border-slate-700 gap-3">
				<div class="flex items-center gap-2">
					<span class="text-sm text-slate-400">Show:</span>
					<select x-model="pageSize" class="bg-slate-700 border border-slate-600 text-gray-300 text-sm rounded pl-3 pr-8 py-1 min-w-[70px] scrollbar_thin appearance-none">
						<option value="10">10</option>
						<option value="25">25</option>
						<option value="50">50</option>
						<option value="100">100</option>
					</select>
					<span class="text-sm text-slate-400">per page</span>
				</div>
				
				<div class="flex items-center gap-2">
					<button @click="currentPage = Math.max(1, currentPage - 1)" 
						:disabled="currentPage === 1"
						class="px-3 py-1 bg-slate-700 hover:bg-slate-600 disabled:opacity-50 disabled:cursor-not-allowed text-sm rounded">
						Previous
					</button>
					
					<template x-for="page in pageNumbers" :key="page">
						<button @click="currentPage = page" 
							:class="page === currentPage ? 'bg-sky-600 text-white' : 'bg-slate-700 hover:bg-slate-600'"
							class="px-3 py-1 text-sm rounded" x-text="page">
						</button>
					</template>
					
					<button @click="currentPage = Math.min(totalPages, currentPage + 1)" 
						:disabled="currentPage === totalPages"
						class="px-3 py-1 bg-slate-700 hover:bg-slate-600 disabled:opacity-50 disabled:cursor-not-allowed text-sm rounded">
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

		<!-- Create Subuser Modal -->
		<div x-show="modals.createSubuser" x-cloak
			class="fixed inset-0 bg-black/75 flex items-center justify-center z-50">
			<div class="bg-gray-800 rounded-lg shadow-lg w-full max-w-lg p-6" @click.away="modals.createSubuser = false">
				<div class="flex justify-between items-center mb-6">
					<h2 class="text-xl font-semibold text-white">Create Subuser</h2>
					<button @click="modals.createSubuser = false" class="text-gray-400 hover:text-white">
						<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
						</svg>
					</button>
				</div>
				
				<div x-show="modalError.message" x-cloak class="bg-red-600 text-white px-4 py-2 rounded-md mb-4">
					<span x-text="modalError.message"></span>
				</div>
				
				<form @submit.prevent="submitCreateSubuser">
					<div class="mb-4">
						<label class="block text-gray-400 mb-2">Username</label>
						<input type="text" x-model="subuserForm.username" placeholder="Enter username" 
							class="w-full px-3 py-2 border border-gray-600 bg-[#11182759] text-gray-300 rounded focus:outline-none">
					</div>
					<div class="mb-6">
						<label class="block text-gray-400 mb-2">Permissions</label>
						<select x-model="subuserForm.permission" 
							class="w-full pl-3 pr-8 py-2 border border-gray-600 bg-[#11182759] text-gray-300 rounded focus:outline-none scrollbar_thin appearance-none">
							<option value="full">Full</option>
							<option value="read">Read</option>
							<option value="write">Write</option>
							<option value="readwrite">Read Write</option>
						</select>
					</div>
					<div class="flex gap-3">
						<button type="submit" 
							class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md">
							Create Subuser
						</button>
						<button type="button" @click="modals.createSubuser = false" 
							class="flex-1 bg-slate-600 hover:bg-slate-700 text-white px-4 py-2 rounded-md">
							Cancel
						</button>
					</div>
				</form>
			</div>
		</div>

		<!-- Password Modal -->
		<div x-show="modals.password" x-cloak
			class="fixed inset-0 bg-black/75 flex items-center justify-center z-50">
			<div class="bg-gray-800 rounded-lg shadow-lg w-full max-w-lg p-6" @click.away="modals.password = false">
				<div class="flex justify-between items-center mb-6">
					<h2 class="text-xl font-semibold text-white">Verify Password</h2>
					<button @click="modals.password = false" class="text-gray-400 hover:text-white">
						<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
						</svg>
					</button>
				</div>
				
				<div x-show="modalError.message" x-cloak class="bg-red-600 text-white px-4 py-2 rounded-md mb-4">
					<span x-text="modalError.message"></span>
				</div>
				
				<form @submit.prevent="submitPassword">
					<div class="mb-6">
						<p class="text-gray-300 mb-4">Enter your account password to proceed.</p>
						<input type="password" x-model="passwordForm.password" placeholder="Account Password" 
							class="w-full px-3 py-2 border border-gray-600 bg-[#11182759] text-gray-300 rounded focus:outline-none">
					</div>
					<div class="flex gap-3">
						<button type="submit" 
							class="flex-1 bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-md">
							Verify
						</button>
						<button type="button" @click="modals.password = false" 
							class="flex-1 bg-slate-600 hover:bg-slate-700 text-white px-4 py-2 rounded-md">
							Cancel
						</button>
					</div>
				</form>
			</div>
		</div>

		<!-- Confirmation Modal -->
		<div x-show="modals.confirm" x-cloak
			class="fixed inset-0 bg-black/75 flex items-center justify-center z-50">
			<div class="bg-gray-800 rounded-lg shadow-lg w-full max-w-md p-6" @click.away="modals.confirm = false">
				<div class="flex items-center mb-4">
					<svg class="w-6 h-6 text-yellow-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.35 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
					</svg>
					<h2 class="text-lg font-semibold text-white" x-text="confirmModal.title">Confirm Action</h2>
				</div>
				<p class="text-gray-300 mb-6" x-text="confirmModal.message">Are you sure?</p>
				<div class="flex gap-3">
					<button @click="confirmModal.onConfirm(); modals.confirm = false" 
						class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md">
						Confirm
					</button>
					<button @click="modals.confirm = false" 
						class="flex-1 bg-slate-600 hover:bg-slate-700 text-white px-4 py-2 rounded-md">
						Cancel
					</button>
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
