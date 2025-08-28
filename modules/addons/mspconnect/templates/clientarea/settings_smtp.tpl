<div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg">
    <div class="p-6 border-b border-slate-700">
        <div class="flex items-start">
            <div class="flex-shrink-0 border-2 border-emerald-600 p-3 rounded-md">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-emerald-600 size-8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.32 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                </svg>
            </div>
            <div class="ml-4">
                <h4 class="text-lg font-medium text-white">SMTP Configuration</h4>
                <span class="text-sm text-slate-400">Configure email settings for customer notifications</span>
            </div>
        </div>
    </div>

    <form method="POST" class="p-6">
        <input type="hidden" name="tab" value="smtp">
        
        <div class="mb-6 p-4 bg-slate-700 rounded-lg border border-slate-600">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-blue-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                <div>
                    <h5 class="text-sm font-medium text-slate-200 mb-1">About SMTP Settings</h5>
                    <p class="text-xs text-slate-400">Configure your own SMTP server to send branded emails to your customers. These settings will be used for welcome emails, invoice notifications, password resets, and other automated messages.</p>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- SMTP Host -->
            <div>
                <label for="smtp_host" class="block text-sm font-medium text-slate-300 mb-2">SMTP Host</label>
                <input type="text" name="smtp_host" id="smtp_host"
                    value="{$smtp_config->smtp_host|default:''|escape}"
                    placeholder="smtp.gmail.com"
                    class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                <p class="text-xs text-slate-400 mt-1">Your SMTP server hostname</p>
            </div>

            <!-- SMTP Port -->
            <div>
                <label for="smtp_port" class="block text-sm font-medium text-slate-300 mb-2">SMTP Port</label>
                <select name="smtp_port" id="smtp_port" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                    <option value="25" {if ($smtp_config->smtp_port|default:587) == 25}selected{/if}>25 (Standard)</option>
                    <option value="587" {if ($smtp_config->smtp_port|default:587) == 587}selected{/if}>587 (Submission)</option>
                    <option value="465" {if ($smtp_config->smtp_port|default:587) == 465}selected{/if}>465 (SSL)</option>
                    <option value="2525" {if ($smtp_config->smtp_port|default:587) == 2525}selected{/if}>2525 (Alternative)</option>
                </select>
                <p class="text-xs text-slate-400 mt-1">Common ports: 587 (TLS), 465 (SSL), 25 (Standard)</p>
            </div>

            <!-- SMTP Encryption -->
            <div>
                <label for="smtp_encryption" class="block text-sm font-medium text-slate-300 mb-2">Encryption</label>
                <select name="smtp_encryption" id="smtp_encryption" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                    <option value="none" {if ($smtp_config->smtp_encryption|default:'tls') == 'none'}selected{/if}>None</option>
                    <option value="tls" {if ($smtp_config->smtp_encryption|default:'tls') == 'tls'}selected{/if}>TLS (Recommended)</option>
                    <option value="ssl" {if ($smtp_config->smtp_encryption|default:'tls') == 'ssl'}selected{/if}>SSL</option>
                </select>
                <p class="text-xs text-slate-400 mt-1">TLS is recommended for most providers</p>
            </div>

            <!-- SMTP Username -->
            <div>
                <label for="smtp_username" class="block text-sm font-medium text-slate-300 mb-2">SMTP Username</label>
                <input type="text" name="smtp_username" id="smtp_username"
                    value="{$smtp_config->smtp_username|default:''|escape}"
                    placeholder="your-email@example.com"
                    class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                <p class="text-xs text-slate-400 mt-1">Usually your email address</p>
            </div>

            <!-- SMTP Password -->
            <div class="md:col-span-2">
                <label for="smtp_password" class="block text-sm font-medium text-slate-300 mb-2">SMTP Password</label>
                <div class="relative">
                    <input type="password" name="smtp_password" id="smtp_password"
                        placeholder="{if $smtp_config}Leave blank to keep current password{else}Enter SMTP password{/if}"
                        class="w-full px-3 py-2 pr-10 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                    <button type="button" onclick="togglePasswordVisibility('smtp_password')" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                        <svg id="smtp_password_eye_open" class="h-5 w-5 text-slate-400 hover:text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        <svg id="smtp_password_eye_closed" class="h-5 w-5 text-slate-400 hover:text-slate-200 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L8.464 8.464M18.364 18.364l-2.829-2.829M18.364 18.364L21 21M8.464 8.464L3 3"></path>
                        </svg>
                    </button>
                </div>
                <p class="text-xs text-slate-400 mt-1">
                    {if $smtp_config}
                        Password is currently saved. Leave blank to keep existing password.
                    {else}
                        Enter your SMTP server password or app-specific password
                    {/if}
                </p>
            </div>

            <!-- Status -->
            <div>
                <label for="smtp_status" class="block text-sm font-medium text-slate-300 mb-2">Status</label>
                <select name="smtp_status" id="smtp_status" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                    <option value="active" {if ($smtp_config->status|default:'active') == 'active'}selected{/if}>Active</option>
                    <option value="inactive" {if ($smtp_config->status|default:'active') == 'inactive'}selected{/if}>Inactive</option>
                </select>
                <p class="text-xs text-slate-400 mt-1">Set to inactive to disable email sending</p>
            </div>

            <!-- Test Connection -->
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Connection Test</label>
                <button type="button" onclick="testSmtpConnection()" id="test-smtp-btn" 
                    class="w-full bg-slate-600 hover:bg-slate-700 text-white px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 focus:ring-offset-slate-800 flex items-center justify-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Test Connection
                </button>
                <p class="text-xs text-slate-400 mt-1">Send a test email to verify settings</p>
            </div>
        </div>

        <!-- Common SMTP Providers Help -->
        <div class="mt-8 p-4 bg-slate-700 rounded-lg border border-slate-600">
            <h5 class="text-sm font-medium text-slate-200 mb-3">Common SMTP Provider Settings</h5>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
                <div>
                    <strong class="text-slate-300">Gmail</strong><br>
                    <span class="text-slate-400">
                        Host: smtp.gmail.com<br>
                        Port: 587 (TLS)<br>
                        Encryption: TLS
                    </span>
                </div>
                <div>
                    <strong class="text-slate-300">Outlook/Hotmail</strong><br>
                    <span class="text-slate-400">
                        Host: smtp-mail.outlook.com<br>
                        Port: 587 (TLS)<br>
                        Encryption: TLS
                    </span>
                </div>
                <div>
                    <strong class="text-slate-300">Yahoo</strong><br>
                    <span class="text-slate-400">
                        Host: smtp.mail.yahoo.com<br>
                        Port: 587 (TLS)<br>
                        Encryption: TLS
                    </span>
                </div>
            </div>
        </div>

        <div class="mt-8 flex justify-end space-x-4">
            <button type="button" onclick="testSmtpConnection()" 
                class="bg-slate-600 hover:bg-slate-700 text-white px-6 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 focus:ring-offset-slate-800">
                Test Settings
            </button>
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-slate-800">
                Save SMTP Settings
            </button>
        </div>
    </form>
</div> 