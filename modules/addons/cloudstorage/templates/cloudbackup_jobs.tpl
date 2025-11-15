<div class="min-h-screen bg-[#11182759] text-gray-300">
    <div class="container mx-auto px-4 pb-8">
        <!-- Navigation Tabs -->
        <div class="mb-6 border-b border-slate-700">
            <nav class="flex space-x-8" aria-label="Cloud Backup Navigation">
                <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs"
                   class="py-4 px-1 border-b-2 font-medium text-sm {if $smarty.get.view == 'cloudbackup_jobs' or empty($smarty.get.view)}border-sky-500 text-sky-400{else}border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-300{/if}">
                    Jobs
                </a>
                <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_runs"
                   class="py-4 px-1 border-b-2 font-medium text-sm {if $smarty.get.view == 'cloudbackup_runs'}border-sky-500 text-sky-400{else}border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-300{/if}">
                    Run History
                </a>
                <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_settings"
                   class="py-4 px-1 border-b-2 font-medium text-sm {if $smarty.get.view == 'cloudbackup_settings'}border-sky-500 text-sky-400{else}border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-300{/if}">
                    Settings
                </a>
            </nav>
        </div>

        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center mb-6">
            <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                </svg>
                <h2 class="text-2xl font-semibold text-white ml-2">Cloud Backup Jobs</h2>
            </div>
            <button
                type="button"
                onclick="openCreateJobModal()"
                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md flex items-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Create Job</span>
            </button>
        </div>

        <!-- Global Message Container -->
        <div id="globalMessage" class="text-white px-4 py-2 rounded-md mb-6 hidden" role="alert"></div>

        <!-- Alpine Toast Notification -->
        <div x-data="{
                visible: false,
                message: '',
                type: 'info',
                timeout: null,
                show(msg, t = 'info') {
                    this.message = msg;
                    this.type = t;
                    this.visible = true;
                    if (this.timeout) clearTimeout(this.timeout);
                    this.timeout = setTimeout(() => { this.visible = false; }, t === 'error' ? 7000 : 4000);
                }
            }"
             x-init="window.toast = {
                success: (m) => show(m, 'success'),
                error: (m) => show(m, 'error'),
                info: (m) => show(m, 'info')
             }"
             class="fixed top-4 right-4 z-[9999]"
             x-cloak>
            <div x-show="visible"
                 x-transition:enter="transform transition ease-out duration-300"
                 x-transition:enter-start="translate-y-2 opacity-0"
                 x-transition:enter-end="translate-y-0 opacity-100"
                 x-transition:leave="transform transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0 opacity-100"
                 x-transition:leave-end="translate-y-2 opacity-0"
                 class="rounded-md px-4 py-3 text-white shadow-lg min-w-[300px] max-w-[500px]"
                 :class="{
                    'bg-green-600': type === 'success',
                    'bg-red-600': type === 'error',
                    'bg-blue-600': type === 'info'
                 }">
                <div class="flex items-start justify-between">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg x-show="type === 'success'" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <svg x-show="type === 'error'" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <svg x-show="type === 'info'" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <p class="ml-3 text-sm font-medium" x-text="message"></p>
                    </div>
                    <button @click="visible = false" class="ml-4 inline-flex text-white hover:text-gray-200 focus:outline-none">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Jobs Container -->
        <div class="grid grid-cols-1 gap-6">
            {if count($jobs) > 0}
                {foreach from=$jobs item=job}
                    <div class="job-row bg-slate-800 rounded-lg border border-slate-700 shadow-lg p-4" id="jobRow{$job.id}">
                        <div class="flex justify-between items-center mb-4">
                            <div class="flex items-center">
                                <h4 class="text-xl font-semibold text-white">{$job.name}</h4>
                                <span class="ml-3 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    {if $job.status eq 'active'}bg-green-700 text-green-200
                                    {elseif $job.status eq 'paused'}bg-yellow-700 text-yellow-200
                                    {else}bg-gray-700 text-gray-200{/if}">
                                    {$job.status|ucfirst}
                                </span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button
                                    onclick="startRun({$job.id})"
                                    class="bg-sky-700 hover:bg-sky-600 text-gray-200 px-3 py-1 rounded-md"
                                    {if $job.status neq 'active'}disabled{/if}
                                >
                                    Run Now
                                </button>
                                <button
                                    onclick="editJob({$job.id})"
                                    class="bg-gray-700 hover:bg-gray-600 text-gray-200 px-3 py-1 rounded-md"
                                >
                                    Edit
                                </button>
                                <button
                                    onclick="toggleJobStatus({$job.id}, '{$job.status}')"
                                    class="bg-yellow-700 hover:bg-yellow-600 text-gray-200 px-3 py-1 rounded-md"
                                >
                                    {if $job.status eq 'active'}Pause{else}Resume{/if}
                                </button>
                                <button
                                    onclick="deleteJob({$job.id})"
                                    class="bg-red-700 hover:bg-red-600 text-gray-200 px-3 py-1 rounded-md"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-5 gap-4">
                            <div>
                                <h6 class="text-sm font-medium text-slate-400">Source</h6>
                                <span class="text-md font-medium text-slate-300">{$job.source_display_name}</span>
                                <span class="text-xs text-slate-500 ml-2">({$job.source_type})</span>
                            </div>
                            <div>
                                <h6 class="text-sm font-medium text-slate-400">Destination</h6>
                                <span class="text-md font-medium text-slate-300">
                                    {if $job.dest_bucket_name}
                                        {$job.dest_bucket_name}
                                    {else}
                                        Bucket #{$job.dest_bucket_id}
                                    {/if}
                                </span>
                                {if $job.dest_prefix}
                                    <span class="text-xs text-slate-500 ml-2">/{$job.dest_prefix}</span>
                                {/if}
                            </div>
                            <div>
                                <h6 class="text-sm font-medium text-slate-400">Mode</h6>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    {if $job.backup_mode eq 'archive'}bg-purple-700 text-purple-200
                                    {else}bg-blue-700 text-blue-200{/if}">
                                    {if $job.backup_mode eq 'archive'}Archive{else}Sync{/if}
                                </span>
                                {if $job.encryption_enabled}
                                    <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-700 text-green-200" title="Encrypted">
                                        🔒
                                    </span>
                                {/if}
                                {if $job.retention_mode neq 'none'}
                                    <div class="mt-1 text-xs text-slate-500">
                                        Retention: {if $job.retention_mode eq 'keep_last_n'}Last {$job.retention_value} runs
                                        {elseif $job.retention_mode eq 'keep_days'}{$job.retention_value} days
                                        {else}{$job.retention_mode}{/if}
                                    </div>
                                {/if}
                            </div>
                            <div>
                                <h6 class="text-sm font-medium text-slate-400">Schedule</h6>
                                <span class="text-md font-medium text-slate-300">
                                    {if $job.schedule_type eq 'manual'}Manual
                                    {elseif $job.schedule_type eq 'daily'}Daily
                                    {elseif $job.schedule_type eq 'weekly'}Weekly
                                    {else}{$job.schedule_type}{/if}
                                </span>
                            </div>
                            <div>
                                <h6 class="text-sm font-medium text-slate-400">Last Run</h6>
                                {if $job.last_run}
                                    <span class="text-md font-medium text-slate-300">
                                        {$job.last_run.started_at|date_format:"%d %b %Y %H:%M"}
                                    </span>
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                        {if $job.last_run.status eq 'success'}bg-green-700 text-green-200
                                        {elseif $job.last_run.status eq 'failed'}bg-red-700 text-red-200
                                        {elseif $job.last_run.status eq 'running'}bg-blue-700 text-blue-200
                                        {else}bg-gray-700 text-gray-200{/if}">
                                        {$job.last_run.status|ucfirst}
                                    </span>
                                {else}
                                    <span class="text-md font-medium text-slate-500">Never</span>
                                {/if}
                            </div>
                        </div>
                    </div>
                {/foreach}
            {else}
                <div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg p-8 text-center">
                    <p class="text-slate-400">No backup jobs found. Create your first job to get started.</p>
                </div>
            {/if}
        </div>
    </div>
</div>

<!-- Create Job Modal -->
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden" id="createJobModal">
    <div class="bg-gray-800 rounded-lg shadow-lg w-full max-w-2xl max-h-[85vh] overflow-y-auto p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-white">Create Backup Job</h2>
            <button type="button" onclick="closeModal('createJobModal')" class="text-slate-300 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div id="jobCreationMessage" class="bg-red-600 text-white px-4 py-2 rounded-md mb-4 hidden"></div>
        <form id="createJobForm">
            <input type="hidden" name="client_id" value="{$client_id}">
            <input type="hidden" name="s3_user_id" value="{$s3_user_id}">
            
            <!-- Step 1: Source Type -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Source Type</label>
                <select name="source_type" id="sourceType" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    <option value="s3_compatible">S3-Compatible Storage</option>
                    <option value="aws">Amazon S3 (AWS)</option>
                    <option value="sftp">SFTP/SSH Server</option>
                </select>
            </div>

            <!-- Step 2: Source Details -->
            <div id="sourceDetails">
                <!-- Security Warning: Read-Only Access Keys -->
                <div class="mb-4 p-4 bg-slate-800 border-2 border-orange-600 rounded-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-sm font-bold text-orange-400 mb-1">⚠️ SECURITY WARNING: Use READ-ONLY Access Keys Only</h3>
                            <p class="text-sm text-gray-200 mb-2">
                                <strong>CRITICAL:</strong> The access keys you provide below must have <strong>READ-ONLY</strong> permissions. 
                                These credentials will only be used to read data from your source storage for backup purposes.
                            </p>
                            <ul class="text-xs text-gray-300 list-disc list-inside space-y-1">
                                <li>Never use access keys with write, delete, or modify permissions</li>
                                <li>Create dedicated read-only access keys specifically for backups</li>
                                <li>Using write-enabled keys creates a security risk and is not recommended</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- S3-Compatible fields -->
                <div id="s3Fields" class="source-type-fields">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Display Name</label>
                        <input type="text" name="source_display_name" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="e.g., Wasabi Production" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Endpoint URL</label>
                        <input type="text" name="s3_endpoint" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="https://s3.wasabisys.com" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Region</label>
                        <input type="text" name="s3_region" value="us-east-1" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="us-east-1" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Access Key ID</label>
                        <input type="text" name="s3_access_key" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Secret Access Key</label>
                        <input type="password" name="s3_secret_key" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Bucket Name</label>
                        <input type="text" name="s3_bucket" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Path/Prefix (optional)</label>
                        <input type="text" name="s3_path" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="backups/">
                    </div>
                </div>

                <!-- AWS fields -->
                <div id="awsFields" class="source-type-fields hidden">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Display Name</label>
                        <input type="text" name="aws_display_name" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="e.g., AWS S3 Production" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Region</label>
                        <input type="text" name="aws_region" value="us-east-1" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="us-east-1" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Access Key ID</label>
                        <input type="text" name="aws_access_key" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Secret Access Key</label>
                        <input type="password" name="aws_secret_key" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Bucket Name</label>
                        <input type="text" name="aws_bucket" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Path/Prefix (optional)</label>
                        <input type="text" name="aws_path" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="backups/">
                    </div>
                </div>

                <!-- SFTP fields -->
                <div id="sftpFields" class="source-type-fields hidden">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Display Name</label>
                        <input type="text" name="sftp_display_name" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="e.g., Customer NAS" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Hostname</label>
                        <input type="text" name="sftp_host" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Port</label>
                        <input type="number" name="sftp_port" value="22" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Username</label>
                        <input type="text" name="sftp_username" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Password</label>
                        <input type="password" name="sftp_password" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Remote Path</label>
                        <input type="text" name="sftp_path" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="/backups/" required>
                    </div>
                </div>
            </div>

            <!-- Step 3: Destination -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Destination Bucket</label>
                <select name="dest_bucket_id" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    {foreach from=$buckets item=bucket}
                        <option value="{$bucket->id}">{$bucket->name}</option>
                    {/foreach}
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Destination Prefix</label>
                <input type="text" name="dest_prefix" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="backups/source-name/" required>
            </div>

            <!-- Step 4: Backup Mode -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Backup Mode</label>
                <select name="backup_mode" id="backupMode" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600">
                    <option value="sync">Sync (Incremental)</option>
                    <option value="archive">Archive (Compressed)</option>
                </select>
                <p class="mt-1 text-xs text-slate-400">
                    <strong>Sync:</strong> Transfers files incrementally, preserving structure. <strong>Archive:</strong> Creates a compressed archive file per run.
                </p>
            </div>

            <!-- Step 4b: Encryption -->
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="encryption_enabled" value="1" class="w-4 h-4 text-sky-600 bg-gray-700 border-gray-600 rounded focus:ring-sky-500 focus:ring-2">
                    <span class="ml-2 text-sm font-medium text-slate-300">Enable Encryption (Crypt Backend)</span>
                </label>
                <p class="mt-1 ml-6 text-xs text-slate-400">
                    Encrypts backup data using rclone's crypt backend. <strong>Warning:</strong> Encryption cannot be disabled after enabling. Ensure you keep your encryption password secure.
                </p>
            </div>

            <!-- Step 4c: Validation -->
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="validation_enabled" value="1" id="validationEnabled" class="w-4 h-4 text-sky-600 bg-gray-700 border-gray-600 rounded focus:ring-sky-500 focus:ring-2">
                    <span class="ml-2 text-sm font-medium text-slate-300">Enable Post-Run Validation</span>
                </label>
                <p class="mt-1 ml-6 text-xs text-slate-400">
                    Runs rclone check after each backup to verify data integrity. This may increase backup time but ensures data consistency.
                </p>
            </div>

            <!-- Step 4d: Retention Policy -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Retention Policy</label>
                <select name="retention_mode" id="retentionMode" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" onchange="onRetentionModeChange()">
                    <option value="none">No Retention</option>
                    <option value="keep_last_n">Keep Last N Runs</option>
                    <option value="keep_days">Keep for N Days</option>
                </select>
                <div id="retentionValueContainer" class="mt-2 hidden">
                    <input type="number" name="retention_value" id="retentionValue" min="1" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="Enter number">
                    <p class="mt-1 text-xs text-slate-400" id="retentionHelp"></p>
                </div>
            </div>

            <!-- Step 5: Schedule -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Schedule</label>
                <select name="schedule_type" id="scheduleType" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600">
                    <option value="manual">Manual Only</option>
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                </select>
            </div>
            <div id="scheduleOptions" class="mb-4 hidden">
                <div class="mb-2">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Time</label>
                    <input type="time" name="schedule_time" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600">
                </div>
                <div id="weeklyOption" class="mb-2 hidden">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Weekday</label>
                    <select name="schedule_weekday" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600">
                        <option value="1">Monday</option>
                        <option value="2">Tuesday</option>
                        <option value="3">Wednesday</option>
                        <option value="4">Thursday</option>
                        <option value="5">Friday</option>
                        <option value="6">Saturday</option>
                        <option value="7">Sunday</option>
                    </select>
                </div>
            </div>

            <!-- Job Name -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Job Name</label>
                <input type="text" name="name" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
            </div>

            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="closeModal('createJobModal')" class="bg-gray-700 hover:bg-gray-600 text-gray-200 px-4 py-2 rounded-md">Cancel</button>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md">Create Job</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Job Slide-Over (dynamically populated) -->
<div id="editJobSlideover" x-data="{ isOpen: false }" x-show="isOpen" class="fixed inset-0 z-50" style="display: none;">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black bg-opacity-50"
         x-show="isOpen"
         x-transition.opacity
         onclick="closeEditSlideover()"></div>
    <!-- Panel -->
    <div class="absolute right-0 top-0 h-full w-full max-w-xl bg-gray-800 border-l border-slate-700 shadow-xl overflow-y-auto"
         x-show="isOpen"
         x-transition:enter="transform transition ease-in-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transform transition ease-in-out duration-300"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full">
        <div class="flex items-center justify-between p-4 border-b border-slate-700">
            <h3 class="text-lg font-semibold text-white">Edit Backup Job</h3>
            <button class="text-slate-300 hover:text-white" onclick="closeEditSlideover()">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="p-4">
            <div id="editJobMessage" class="bg-red-600 text-white px-4 py-2 rounded-md mb-4 hidden"></div>
            <form id="editJobForm" onsubmit="return false;">
                <input type="hidden" id="edit_job_id" name="job_id" />

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Job Name</label>
                    <input type="text" id="edit_name" name="name" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" required />
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Source Type</label>
                    <select id="edit_source_type" name="source_type" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" onchange="onEditSourceTypeChange()">
                        <option value="s3_compatible">S3-Compatible Storage</option>
                        <option value="aws">Amazon S3 (AWS)</option>
                        <option value="sftp">SFTP/SSH Server</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Source Display Name</label>
                    <input type="text" id="edit_source_display_name" name="source_display_name" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" required />
                </div>

                <!-- Security Warning: Read-Only Access Keys -->
                <div class="mb-4 p-4 bg-slate-800 border-2 border-orange-600 rounded-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-sm font-bold text-orange-400 mb-1">⚠️ SECURITY WARNING: Use READ-ONLY Access Keys Only</h3>
                            <p class="text-sm text-gray-200 mb-2">
                                <strong>CRITICAL:</strong> The access keys you provide below must have <strong>READ-ONLY</strong> permissions. 
                                These credentials will only be used to read data from your source storage for backup purposes.
                            </p>
                            <ul class="text-xs text-gray-300 list-disc list-inside space-y-1">
                                <li>Never use access keys with write, delete, or modify permissions</li>
                                <li>Create dedicated read-only access keys specifically for backups</li>
                                <li>Using write-enabled keys creates a security risk and is not recommended</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div id="edit_s3_fields" class="mb-4">
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Endpoint URL</label>
                        <input type="text" id="edit_s3_endpoint" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="https://s3.wasabisys.com" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Region</label>
                        <input type="text" id="edit_s3_region" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="us-east-1" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Access Key ID</label>
                        <input type="text" id="edit_s3_access_key" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="Leave blank to keep existing" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Secret Access Key</label>
                        <input type="password" id="edit_s3_secret_key" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="Leave blank to keep existing" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Bucket Name</label>
                        <input type="text" id="edit_s3_bucket" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Path/Prefix (optional)</label>
                        <input type="text" id="edit_s3_path" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="backups/" />
                    </div>
                </div>

                <div id="edit_aws_fields" class="mb-4 hidden">
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Region</label>
                        <input type="text" id="edit_aws_region" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="us-east-1" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Access Key ID</label>
                        <input type="text" id="edit_aws_access_key" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="Leave blank to keep existing" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Secret Access Key</label>
                        <input type="password" id="edit_aws_secret_key" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="Leave blank to keep existing" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Bucket Name</label>
                        <input type="text" id="edit_aws_bucket" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Path/Prefix (optional)</label>
                        <input type="text" id="edit_aws_path" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="backups/" />
                    </div>
                </div>

                <div id="edit_sftp_fields" class="mb-4 hidden">
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Hostname</label>
                        <input type="text" id="edit_sftp_host" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Port</label>
                        <input type="number" id="edit_sftp_port" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" value="22" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Username</label>
                        <input type="text" id="edit_sftp_username" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Password</label>
                        <input type="password" id="edit_sftp_password" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="Leave blank to keep existing" />
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Destination Bucket</label>
                    <select id="edit_dest_bucket_id" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2"></select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Destination Prefix</label>
                    <input type="text" id="edit_dest_prefix" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="backups/source-name/" />
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Backup Mode</label>
                    <select id="edit_backup_mode" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2">
                        <option value="sync">Sync (Incremental)</option>
                        <option value="archive">Archive (Compressed)</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-400">
                        <strong>Sync:</strong> Transfers files incrementally, preserving structure. <strong>Archive:</strong> Creates a compressed archive file per run.
                    </p>
                </div>

                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" id="edit_encryption_enabled" value="1" class="w-4 h-4 text-sky-600 bg-gray-700 border-gray-600 rounded focus:ring-sky-500 focus:ring-2">
                        <span class="ml-2 text-sm font-medium text-slate-300">Enable Encryption (Crypt Backend)</span>
                    </label>
                    <p class="mt-1 ml-6 text-xs text-slate-400">
                        Encrypts backup data using rclone's crypt backend. <strong>Warning:</strong> Encryption cannot be disabled after enabling. Ensure you keep your encryption password secure.
                    </p>
                </div>

                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" id="edit_validation_enabled" value="1" class="w-4 h-4 text-sky-600 bg-gray-700 border-gray-600 rounded focus:ring-sky-500 focus:ring-2">
                        <span class="ml-2 text-sm font-medium text-slate-300">Enable Post-Run Validation</span>
                    </label>
                    <p class="mt-1 ml-6 text-xs text-slate-400">
                        Runs rclone check after each backup to verify data integrity. This may increase backup time but ensures data consistency.
                    </p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Retention Policy</label>
                    <select id="edit_retention_mode" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" onchange="onEditRetentionModeChange()">
                        <option value="none">No Retention</option>
                        <option value="keep_last_n">Keep Last N Runs</option>
                        <option value="keep_days">Keep for N Days</option>
                    </select>
                    <div id="edit_retention_value_container" class="mt-2 hidden">
                        <input type="number" id="edit_retention_value" min="1" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="Enter number">
                        <p class="mt-1 text-xs text-slate-400" id="edit_retention_help"></p>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Schedule</label>
                    <select id="edit_schedule_type" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" onchange="onEditScheduleChange()">
                        <option value="manual">Manual</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                    </select>
                </div>
                <div id="edit_schedule_options" class="mb-4 hidden">
                    <div class="mb-2">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Time</label>
                        <input type="time" id="edit_schedule_time" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" />
                    </div>
                    <div id="edit_weekly_option">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Weekday</label>
                        <select id="edit_schedule_weekday" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2">
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                            <option value="6">Saturday</option>
                            <option value="7">Sunday</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" class="bg-gray-700 hover:bg-gray-600 text-gray-200 px-4 py-2 rounded-md" onclick="closeEditSlideover()">Cancel</button>
                    <button type="button" class="bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-md" onclick="saveEditedJob()">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
{literal}
function openCreateJobModal() {
    document.getElementById('createJobModal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

document.getElementById('sourceType').addEventListener('change', function() {
    const s3Fields = document.getElementById('s3Fields');
    const awsFields = document.getElementById('awsFields');
    const sftpFields = document.getElementById('sftpFields');
    // Helper to enable/disable inputs in a group
    function setGroupEnabled(groupEl, enabled) {
        if (!groupEl) return;
        const controls = groupEl.querySelectorAll('input, select, textarea, button');
        controls.forEach(el => {
            if (enabled) {
                el.removeAttribute('disabled');
            } else {
                el.setAttribute('disabled', 'disabled');
            }
        });
    }
    if (this.value === 's3_compatible') {
        s3Fields.classList.remove('hidden');
        setGroupEnabled(s3Fields, true);
        awsFields.classList.add('hidden');
        setGroupEnabled(awsFields, false);
        sftpFields.classList.add('hidden');
        setGroupEnabled(sftpFields, false);
    } else if (this.value === 'aws') {
        s3Fields.classList.add('hidden');
        setGroupEnabled(s3Fields, false);
        awsFields.classList.remove('hidden');
        setGroupEnabled(awsFields, true);
        sftpFields.classList.add('hidden');
        setGroupEnabled(sftpFields, false);
    } else {
        s3Fields.classList.add('hidden');
        setGroupEnabled(s3Fields, false);
        awsFields.classList.add('hidden');
        setGroupEnabled(awsFields, false);
        sftpFields.classList.remove('hidden');
        setGroupEnabled(sftpFields, true);
    }
});

document.getElementById('scheduleType').addEventListener('change', function() {
    const scheduleOptions = document.getElementById('scheduleOptions');
    const weeklyOption = document.getElementById('weeklyOption');
    if (this.value === 'manual') {
        scheduleOptions.classList.add('hidden');
    } else {
        scheduleOptions.classList.remove('hidden');
        if (this.value === 'weekly') {
            weeklyOption.classList.remove('hidden');
        } else {
            weeklyOption.classList.add('hidden');
        }
    }
});
document.getElementById('createJobForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const sourceType = formData.get('source_type');
    
    // Build source_config JSON
    let sourceConfig = {};
    let sourceDisplayName = '';
    let sourcePath = '';
    
    if (sourceType === 's3_compatible') {
        sourceConfig = {
            endpoint: formData.get('s3_endpoint'),
            access_key: formData.get('s3_access_key'),
            secret_key: formData.get('s3_secret_key'),
            bucket: formData.get('s3_bucket'),
            region: formData.get('s3_region') || 'us-east-1'
        };
        sourceDisplayName = formData.get('source_display_name');
        const s3Bucket = formData.get('s3_bucket') || '';
        const s3Prefix = formData.get('s3_path') || '';
        sourcePath = s3Prefix ? (s3Bucket + '/' + s3Prefix) : s3Bucket;
    } else if (sourceType === 'aws') {
        sourceConfig = {
            access_key: formData.get('aws_access_key'),
            secret_key: formData.get('aws_secret_key'),
            bucket: formData.get('aws_bucket'),
            region: formData.get('aws_region') || 'us-east-1'
        };
        sourceDisplayName = formData.get('aws_display_name');
        const awsBucket = formData.get('aws_bucket') || '';
        const awsPrefix = formData.get('aws_path') || '';
        sourcePath = awsPrefix ? (awsBucket + '/' + awsPrefix) : awsBucket;
    } else if (sourceType === 'sftp') {
        sourceConfig = {
            host: formData.get('sftp_host'),
            port: parseInt(formData.get('sftp_port')) || 22,
            user: formData.get('sftp_username'),
            pass: formData.get('sftp_password')
        };
        sourceDisplayName = formData.get('sftp_display_name');
        sourcePath = formData.get('sftp_path');
    }
    
    const jobData = {
        name: formData.get('name'),
        source_type: sourceType,
        source_display_name: sourceDisplayName,
        source_config: JSON.stringify(sourceConfig),
        source_path: sourcePath,
        dest_bucket_id: formData.get('dest_bucket_id'),
        dest_prefix: formData.get('dest_prefix'),
        backup_mode: formData.get('backup_mode') || 'sync',
        encryption_enabled: formData.get('encryption_enabled') ? '1' : '0',
        validation_mode: formData.get('validation_enabled') ? 'post_run' : 'none',
        retention_mode: formData.get('retention_mode') || 'none',
        retention_value: formData.get('retention_value') || null,
        schedule_type: formData.get('schedule_type'),
        schedule_time: formData.get('schedule_time') || null,
        schedule_weekday: formData.get('schedule_weekday') || null,
        client_id: formData.get('client_id'),
        s3_user_id: formData.get('s3_user_id')
    };
    
    fetch('modules/addons/cloudstorage/api/cloudbackup_create_job.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(jobData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            location.reload();
        } else {
            document.getElementById('jobCreationMessage').textContent = data.message || 'Failed to create job';
            document.getElementById('jobCreationMessage').classList.remove('hidden');
        }
    })
    .catch(error => {
        document.getElementById('jobCreationMessage').textContent = 'An error occurred';
        document.getElementById('jobCreationMessage').classList.remove('hidden');
    });
});

// Ensure correct enabled/disabled state when modal opens and on initial load
function applyInitialSourceState() {
    const sourceTypeEl = document.getElementById('sourceType');
    if (sourceTypeEl) {
        const event = new Event('change');
        sourceTypeEl.dispatchEvent(event);
    }
}

// Apply when modal opens
const originalOpenCreateJobModal = openCreateJobModal;
openCreateJobModal = function() {
    originalOpenCreateJobModal();
    applyInitialSourceState();
};

// Apply on initial page load
document.addEventListener('DOMContentLoaded', applyInitialSourceState);

function startRun(jobId) {
    fetch('modules/addons/cloudstorage/api/cloudbackup_start_run.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({job_id: jobId})
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            window.location.href = 'index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_live&run_id=' + data.run_id;
        } else {
            alert(data.message || 'Failed to start run');
        }
    });
}

function toggleJobStatus(jobId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'paused' : 'active';
    fetch('modules/addons/cloudstorage/api/cloudbackup_update_job.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams([['job_id', jobId], ['status', newStatus]])
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            location.reload();
        } else {
            alert(data.message || 'Failed to update job');
        }
    });
}

function deleteJob(jobId) {
    if (!confirm('Are you sure you want to delete this job?')) {
        return;
    }
    fetch('modules/addons/cloudstorage/api/cloudbackup_delete_job.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams([['job_id', jobId]])
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            location.reload();
        } else {
            alert(data.message || 'Failed to delete job');
        }
    });
}

function editJob(jobId) {
    ensureEditPanel();
    openEditSlideover(jobId);
}

function ensureEditPanel() {
    const panel = document.getElementById('editJobSlideover');
    if (!panel) return; // markup already in DOM
    // copy dest bucket options from create form if available
    var srcSel = document.querySelector('select[name="dest_bucket_id"]');
    var dstSel = document.getElementById('edit_dest_bucket_id');
    if (srcSel && dstSel) {
        dstSel.innerHTML = srcSel.innerHTML;
    }
}

function openEditSlideover(jobId) {
    const msg = document.getElementById('editJobMessage');
    msg.classList.add('hidden');
    msg.textContent = '';

    const panel = document.getElementById('editJobSlideover');
    if (!panel) {
        console.error('Edit panel not found');
        return;
    }
    
    // Always force show the panel and its children first (override Alpine's x-show)
    panel.style.setProperty('display', 'block', 'important');
    
    // Show backdrop
    const backdrop = panel.querySelector('.absolute.inset-0.bg-black.bg-opacity-50') || 
                     panel.querySelector('.absolute.inset-0');
    if (backdrop) backdrop.style.setProperty('display', 'block', 'important');
    
    // Show panel content
    const panelContent = panel.querySelector('.absolute.right-0.top-0') || 
                         panel.querySelector('.absolute.right-0');
    if (panelContent) panelContent.style.setProperty('display', 'block', 'important');
    
    // Then set Alpine state for transitions (if Alpine is available)
    if (window.Alpine) {
        // Initialize Alpine on this element if needed
        if (!panel.__x) {
            if (typeof Alpine.initTree === 'function') {
                Alpine.initTree(panel);
            }
        }
        
        // Set the reactive state after a brief delay to ensure Alpine is ready
        setTimeout(() => {
            if (panel.__x && panel.__x.$data) {
                panel.__x.$data.isOpen = true;
            }
        }, 0);
    }

    // Reset form and set job ID
    document.getElementById('edit_job_id').value = jobId;
    const form = document.getElementById('editJobForm');
    form.reset();
    document.getElementById('edit_job_id').value = jobId;

    // Populate select options from existing create form select
    const srcSel = document.querySelector('select[name="dest_bucket_id"]');
    const dstSel = document.getElementById('edit_dest_bucket_id');
    if (srcSel && dstSel) {
        dstSel.innerHTML = srcSel.innerHTML;
    }

    // Fetch job data and populate form
    fetch('modules/addons/cloudstorage/api/cloudbackup_get_job.php?job_id=' + encodeURIComponent(jobId))
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success' || !data.job) {
                msg.textContent = data.message || 'Failed to load job details';
                msg.classList.remove('hidden');
                if (window.toast) window.toast.error(data.message || 'Failed to load job details');
                return;
            }
            const j = data.job; const s = data.source || {};
            document.getElementById('edit_name').value = j.name || '';
            document.getElementById('edit_source_display_name').value = j.source_display_name || '';
            document.getElementById('edit_source_type').value = j.source_type || 's3_compatible';
            onEditSourceTypeChange();

            if (j.source_type === 's3_compatible') {
                document.getElementById('edit_s3_endpoint').value = s.endpoint || '';
                document.getElementById('edit_s3_region').value = s.region || 'us-east-1';
                document.getElementById('edit_s3_bucket').value = s.bucket || '';
                const parts = (j.source_path || '').split('/');
                const b = parts.shift() || '';
                const p = parts.join('/');
                if (!document.getElementById('edit_s3_bucket').value) document.getElementById('edit_s3_bucket').value = b;
                document.getElementById('edit_s3_path').value = p || '';
            } else if (j.source_type === 'aws') {
                document.getElementById('edit_aws_region').value = s.region || 'us-east-1';
                document.getElementById('edit_aws_bucket').value = s.bucket || '';
                const parts2 = (j.source_path || '').split('/');
                const b2 = parts2.shift() || '';
                const p2 = parts2.join('/');
                if (!document.getElementById('edit_aws_bucket').value) document.getElementById('edit_aws_bucket').value = b2;
                document.getElementById('edit_aws_path').value = p2 || '';
            } else if (j.source_type === 'sftp') {
                document.getElementById('edit_sftp_host').value = s.host || '';
                document.getElementById('edit_sftp_port').value = s.port || 22;
                document.getElementById('edit_sftp_username').value = s.user || '';
                document.getElementById('edit_sftp_path').value = j.source_path || '';
            }

            // destination
            if (dstSel) {
                for (let i = 0; i < dstSel.options.length; i++) {
                    if (String(dstSel.options[i].value) === String(j.dest_bucket_id)) {
                        dstSel.selectedIndex = i;
                        break;
                    }
                }
            }
            document.getElementById('edit_dest_prefix').value = j.dest_prefix || '';

            // backup mode
            const bm = document.getElementById('edit_backup_mode');
            if (bm) {
                bm.value = j.backup_mode || 'sync';
            }

            // encryption
            const enc = document.getElementById('edit_encryption_enabled');
            if (enc) {
                enc.checked = (j.encryption_enabled == 1 || j.encryption_enabled === true);
            }

            // validation
            const val = document.getElementById('edit_validation_enabled');
            if (val) {
                val.checked = (j.validation_mode == 'post_run' || j.validation_mode === 'post_run');
            }

            // retention
            const retMode = document.getElementById('edit_retention_mode');
            if (retMode) {
                retMode.value = j.retention_mode || 'none';
                onEditRetentionModeChange();
            }
            const retVal = document.getElementById('edit_retention_value');
            if (retVal && j.retention_value) {
                retVal.value = j.retention_value;
            }

            // schedule
            const st = document.getElementById('edit_schedule_type');
            if (st) {
                st.value = j.schedule_type || 'manual';
                onEditScheduleChange();
            }
            if (j.schedule_time) document.getElementById('edit_schedule_time').value = j.schedule_time;
            if (j.schedule_weekday) document.getElementById('edit_schedule_weekday').value = j.schedule_weekday;
        })
        .catch(() => {
            if (window.toast) {
                window.toast.error('Error loading job details');
            } else {
                document.getElementById('globalMessage').textContent = 'Error loading job details';
                document.getElementById('globalMessage').classList.remove('hidden');
                setTimeout(()=>{ document.getElementById('globalMessage').classList.add('hidden'); }, 2500);
            }
        });
}

function closeEditSlideover() {
    const panel = document.getElementById('editJobSlideover');
    if (!panel) return;
    
    // Force hide the panel and its children using !important (override any inline styles)
    panel.style.setProperty('display', 'none', 'important');
    
    // Hide backdrop (the div with bg-black bg-opacity-50)
    const backdrop = panel.querySelector('.absolute.inset-0.bg-black.bg-opacity-50') || 
                     panel.querySelector('.absolute.inset-0');
    if (backdrop) backdrop.style.setProperty('display', 'none', 'important');
    
    // Hide panel content (the slide-over panel itself)
    const panelContent = panel.querySelector('.absolute.right-0.top-0') || 
                         panel.querySelector('.absolute.right-0');
    if (panelContent) panelContent.style.setProperty('display', 'none', 'important');
    
    // Also set Alpine state for consistency (if Alpine is available)
    if (panel.__x && panel.__x.$data) {
        panel.__x.$data.isOpen = false;
    }
}

function onEditSourceTypeChange() {
    const t = document.getElementById('edit_source_type').value;
    const s3 = document.getElementById('edit_s3_fields');
    const aws = document.getElementById('edit_aws_fields');
    const sftp = document.getElementById('edit_sftp_fields');
    const setEnabled = (el, on) => { if (!el) return; el.querySelectorAll('input,select,textarea,button').forEach(e => on ? e.removeAttribute('disabled') : e.setAttribute('disabled','disabled')); };
    if (t === 's3_compatible') { s3.classList.remove('hidden'); setEnabled(s3,true); aws.classList.add('hidden'); setEnabled(aws,false); sftp.classList.add('hidden'); setEnabled(sftp,false); }
    else if (t === 'aws') { s3.classList.add('hidden'); setEnabled(s3,false); aws.classList.remove('hidden'); setEnabled(aws,true); sftp.classList.add('hidden'); setEnabled(sftp,false); }
    else { s3.classList.add('hidden'); setEnabled(s3,false); aws.classList.add('hidden'); setEnabled(aws,false); sftp.classList.remove('hidden'); setEnabled(sftp,true); }
}

function onEditScheduleChange() {
    const t = document.getElementById('edit_schedule_type').value;
    const opts = document.getElementById('edit_schedule_options');
    const weekly = document.getElementById('edit_weekly_option');
    if (t === 'manual') { opts.classList.add('hidden'); }
    else { opts.classList.remove('hidden'); if (t === 'weekly') { weekly.classList.remove('hidden'); } else { weekly.classList.add('hidden'); } }
}

function onEditRetentionModeChange() {
    const mode = document.getElementById('edit_retention_mode').value;
    const container = document.getElementById('edit_retention_value_container');
    const help = document.getElementById('edit_retention_help');
    if (mode === 'none') {
        container.classList.add('hidden');
    } else {
        container.classList.remove('hidden');
        if (mode === 'keep_last_n') {
            help.textContent = 'Keep only the N most recent successful backup runs. Older runs will be automatically deleted.';
        } else if (mode === 'keep_days') {
            help.textContent = 'Keep backup data for N days. Data older than this will be automatically deleted.';
        }
    }
}

function saveEditedJob() {
    const panel = document.getElementById('editJobSlideover');
    const msg = document.getElementById('editJobMessage');
    msg.classList.add('hidden'); msg.textContent='';

    const jobId = document.getElementById('edit_job_id').value;
    const payload = new URLSearchParams();
    payload.set('job_id', jobId);
    payload.set('name', (document.getElementById('edit_name').value || '').trim());
    const stype = document.getElementById('edit_source_type').value;
    payload.set('source_type', stype);
    payload.set('source_display_name', (document.getElementById('edit_source_display_name').value || '').trim());

    if (stype === 's3_compatible') {
        const bucket = (document.getElementById('edit_s3_bucket').value || '').trim();
        const prefix = (document.getElementById('edit_s3_path').value || '').trim();
        payload.set('source_path', prefix ? (bucket + '/' + prefix) : bucket);
        const ep = (document.getElementById('edit_s3_endpoint').value || '').trim();
        const rg = (document.getElementById('edit_s3_region').value || 'us-east-1').trim();
        const ak = (document.getElementById('edit_s3_access_key').value || '').trim();
        const sk = (document.getElementById('edit_s3_secret_key').value || '').trim();
        if (ep) payload.set('s3_endpoint', ep);
        if (rg) payload.set('s3_region', rg);
        if (bucket) payload.set('s3_bucket', bucket);
        if (ak) payload.set('s3_access_key', ak);
        if (sk) payload.set('s3_secret_key', sk);
    } else if (stype === 'aws') {
        const bucket2 = (document.getElementById('edit_s3_bucket').value || document.getElementById('edit_aws_bucket').value || '').trim();
        const prefix2 = (document.getElementById('edit_aws_path').value || '').trim();
        payload.set('source_path', prefix2 ? (bucket2 + '/' + prefix2) : bucket2);
        const rg2 = (document.getElementById('edit_aws_region').value || 'us-east-1').trim();
        const ak2 = (document.getElementById('edit_aws_access_key').value || '').trim();
        const sk2 = (document.getElementById('edit_aws_secret_key').value || '').trim();
        if (rg2) payload.set('aws_region', rg2);
        if (bucket2) payload.set('aws_bucket', bucket2);
    } else if (stype === 'sftp') {
        const host = (document.getElementById('edit_sftp_host').value || '').trim();
        const port = parseInt(document.getElementById('edit_sftp_port').value) || 22;
        const user = (document.getElementById('edit_sftp_username').value || '').trim();
        const pass = (document.getElementById('edit_sftp_password').value || '').trim();
        if (host) payload.set('sftp_host', host);
        if (port) payload.set('sftp_port', port);
        if (user) payload.set('sftp_username', user);
        if (pass) payload.set('sftp_password', pass);
    }

    // destination
    const destBucketId = document.getElementById('edit_dest_bucket_id').value;
    const destPrefix = document.getElementById('edit_dest_prefix').value;
    if (destBucketId) payload.set('dest_bucket_id', destBucketId);
    if (destPrefix) payload.set('dest_prefix', destPrefix);

    // backup mode
    const backupMode = document.getElementById('edit_backup_mode').value;
    if (backupMode) payload.set('backup_mode', backupMode);

    // encryption
    const encryptionEnabled = document.getElementById('edit_encryption_enabled').checked;
    payload.set('encryption_enabled', encryptionEnabled ? '1' : '0');

    // validation
    const validationEnabled = document.getElementById('edit_validation_enabled').checked;
    payload.set('validation_mode', validationEnabled ? 'post_run' : 'none');

    // retention
    const retentionMode = document.getElementById('edit_retention_mode').value;
    const retentionValue = document.getElementById('edit_retention_value').value;
    payload.set('retention_mode', retentionMode);
    if (retentionMode !== 'none' && retentionValue) {
        payload.set('retention_value', retentionValue);
    }

    // schedule
    const scheduleType = document.getElementById('edit_schedule_type').value;
    payload.set('schedule_type', scheduleType);
    if (scheduleType === 'daily' || scheduleType === 'weekly') {
        const time = document.getElementById('edit_schedule_time').value;
        const weekday = document.getElementById('edit_schedule_weekday').value;
        if (time) payload.set('schedule_time', time);
        if (weekday) payload.set('schedule_weekday', weekday);
    }

    fetch('modules/addons/cloudstorage/api/cloudbackup_update_job.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: payload
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Show success toast
            if (window.toast) {
                window.toast.success('Job updated successfully');
            }
            // Close the slide-over
            closeEditSlideover();
            // Update the job row in place (optional - could also just reload)
            updateJobRowInPlace(jobId, data.job);
        } else {
            // Show error toast
            const errorMsg = data.message || 'Failed to save changes';
            if (window.toast) {
                window.toast.error(errorMsg);
            } else {
                msg.textContent = errorMsg;
                msg.classList.remove('hidden');
            }
        }
    })
    .catch(error => {
        const errorMsg = 'An error occurred while saving';
        if (window.toast) {
            window.toast.error(errorMsg);
        } else {
            msg.textContent = errorMsg;
            msg.classList.remove('hidden');
        }
    });
}

function updateJobRowInPlace(jobId, updatedJob) {
    // Optionally update the job row without full page reload
    // For now, we'll just close the panel and show toast
    // Full update would require fetching the updated job and updating the DOM
    // This is a placeholder for future enhancement
}
{/literal}
</script>


