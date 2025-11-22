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
            <h2 class="text-2xl font-semibold text-white">Backup Settings</h2>
        </div>

        <!-- Global Message Container -->
        <div id="globalMessage" class="text-white px-4 py-2 rounded-md mb-6 hidden" role="alert"></div>

        {if isset($message)}
            <div class="{if $message.type eq 'success'}bg-green-600{else}bg-red-600{/if} text-gray-200 px-4 py-3 rounded-md mb-6" role="alert">
                {$message.text}
            </div>
        {/if}

        <div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg p-6">
            <form method="post" action="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_settings">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Notification Emails</label>
                    <input
                        type="text"
                        name="default_notify_emails"
                        value="{$settings.default_notify_emails}"
                        class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600"
                        placeholder="email1@example.com, email2@example.com"
                    >
                    <p class="mt-1 text-sm text-slate-400">Comma-separated list of email addresses</p>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-slate-300 mb-3">Notify On</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                name="default_notify_on_success"
                                value="1"
                                {if $settings.default_notify_on_success}checked{/if}
                                class="mr-2 bg-gray-700 border-gray-600 text-sky-600 focus:ring-sky-500"
                            >
                            <span class="text-slate-300">Success</span>
                        </label>
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                name="default_notify_on_warning"
                                value="1"
                                {if $settings.default_notify_on_warning}checked{/if}
                                class="mr-2 bg-gray-700 border-gray-600 text-sky-600 focus:ring-sky-500"
                            >
                            <span class="text-slate-300">Warning</span>
                        </label>
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                name="default_notify_on_failure"
                                value="1"
                                {if $settings.default_notify_on_failure}checked{/if}
                                class="mr-2 bg-gray-700 border-gray-600 text-sky-600 focus:ring-sky-500"
                            >
                            <span class="text-slate-300">Failure</span>
                        </label>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Default Timezone</label>
                    <select
                        name="default_timezone"
                        class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600"
                    >
                        <option value="">Use server default</option>
                        <option value="UTC" {if $settings.default_timezone eq 'UTC'}selected{/if}>UTC</option>
                        <option value="America/New_York" {if $settings.default_timezone eq 'America/New_York'}selected{/if}>America/New_York</option>
                        <option value="America/Chicago" {if $settings.default_timezone eq 'America/Chicago'}selected{/if}>America/Chicago</option>
                        <option value="America/Denver" {if $settings.default_timezone eq 'America/Denver'}selected{/if}>America/Denver</option>
                        <option value="America/Los_Angeles" {if $settings.default_timezone eq 'America/Los_Angeles'}selected{/if}>America/Los_Angeles</option>
                        <option value="Europe/London" {if $settings.default_timezone eq 'Europe/London'}selected{/if}>Europe/London</option>
                        <option value="Europe/Paris" {if $settings.default_timezone eq 'Europe/Paris'}selected{/if}>Europe/Paris</option>
                        <option value="Asia/Tokyo" {if $settings.default_timezone eq 'Asia/Tokyo'}selected{/if}>Asia/Tokyo</option>
                    </select>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Max Concurrent Jobs (Per Client)</label>
                    <input
                        type="number"
                        name="per_client_max_concurrent_jobs"
                        value="{$settings.per_client_max_concurrent_jobs}"
                        min="1"
                        class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600"
                        placeholder="Leave empty to use global limit"
                    >
                    <p class="mt-1 text-sm text-slate-400">Maximum number of backup jobs that can run simultaneously for your account. Leave empty to use the global system limit.</p>
                </div>

                <div class="flex justify-end space-x-2">
                    <a
                        href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs"
                        class="bg-gray-700 hover:bg-gray-600 text-gray-200 px-4 py-2 rounded-md"
                    >
                        Cancel
                    </a>
                    <button
                        type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md"
                    >
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

