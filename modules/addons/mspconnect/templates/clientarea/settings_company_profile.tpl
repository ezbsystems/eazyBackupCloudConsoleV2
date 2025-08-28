<div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg">
    <div class="p-6 border-b border-slate-700">
        <div class="flex items-start">
            <div class="flex-shrink-0 border-2 border-sky-600 p-3 rounded-md">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-sky-600 size-8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m2.25-18h15M5.25 21V9.375c0-.621.504-1.125 1.125-1.125h3.375m-5.625 12h10.5m-10.5 0V11.25m0 9.75h10.5m0 0V9.375c0-.621.504-1.125 1.125-1.125h3.375M8.25 21V9.375c0-.621.504-1.125 1.125-1.125h3.375m0 0V21.75M15 10.5h3.375c.621 0 1.125.504 1.125 1.125v6.75c0 .621-.504 1.125-1.125 1.125h-3.375M15 10.5V21.75" />
                </svg>
            </div>
            <div class="ml-4">
                <h4 class="text-lg font-medium text-white">Company Information</h4>
                <span class="text-sm text-slate-400">Update your company details and branding</span>
            </div>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" class="p-6">
        <input type="hidden" name="tab" value="company">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Company Name -->
            <div>
                <label for="company_name" class="block text-sm font-medium text-slate-300 mb-2">Company Name *</label>
                <input type="text" name="company_name" id="company_name" required
                    value="{$company_profile->company_name|default:''|escape}"
                    class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
            </div>

            <!-- Contact Email -->
            <div>
                <label for="contact_email" class="block text-sm font-medium text-slate-300 mb-2">Contact Email *</label>
                <input type="email" name="contact_email" id="contact_email" required
                    value="{$company_profile->contact_email|default:''|escape}"
                    class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
            </div>

            <!-- Phone -->
            <div>
                <label for="phone" class="block text-sm font-medium text-slate-300 mb-2">Phone Number</label>
                <input type="tel" name="phone" id="phone"
                    value="{$company_profile->phone|default:''|escape}"
                    class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
            </div>

            <!-- Website -->
            <div>
                <label for="website" class="block text-sm font-medium text-slate-300 mb-2">Website</label>
                <input type="url" name="website" id="website"
                    value="{$company_profile->website|default:''|escape}"
                    placeholder="https://www.example.com"
                    class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
            </div>

            <!-- Address -->
            <div class="md:col-span-2">
                <label for="address" class="block text-sm font-medium text-slate-300 mb-2">Street Address</label>
                <input type="text" name="address" id="address"
                    value="{$company_profile->address|default:''|escape}"
                    class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
            </div>

            <!-- City -->
            <div>
                <label for="city" class="block text-sm font-medium text-slate-300 mb-2">City</label>
                <input type="text" name="city" id="city"
                    value="{$company_profile->city|default:''|escape}"
                    class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
            </div>

            <!-- State/Province -->
            <div>
                <label for="state" class="block text-sm font-medium text-slate-300 mb-2">State/Province</label>
                <select name="state" id="state" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                    <option value="">Select State/Province</option>
                    <!-- States will be populated by JavaScript based on country selection -->
                </select>
                <input type="text" name="state_text" id="state_text" style="display: none;"
                    value="{$company_profile->state|default:''|escape}"
                    placeholder="Enter State/Province"
                    class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
            </div>

            <!-- Postal Code -->
            <div>
                <label for="postal_code" class="block text-sm font-medium text-slate-300 mb-2">Postal Code</label>
                <input type="text" name="postal_code" id="postal_code"
                    value="{$company_profile->postal_code|default:''|escape}"
                    class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
            </div>

            <!-- Country -->
            <div>
                <label for="country" class="block text-sm font-medium text-slate-300 mb-2">Country</label>
                <select name="country" id="country" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                    <option value="">Select Country</option>
                    <!-- Countries will be populated by JavaScript -->
                </select>
            </div>

            <!-- Logo Upload -->
            <div class="md:col-span-2">
                <label for="logo" class="block text-sm font-medium text-slate-300 mb-2">Company Logo</label>
                
                <!-- Current Logo Display -->
                <div id="current-logo-container" class="mb-4" {if !$current_logo_url}style="display: none;"{/if}>
                    <div class="flex items-center space-x-4 p-4 bg-slate-700 rounded-md border border-slate-600">
                        <img id="current-logo-preview" src="{$current_logo_url|default:''}" alt="Current Logo" class="max-h-20 max-w-40 border border-slate-500 rounded-md">
                        <div class="flex-1">
                            <p class="text-sm text-slate-300 font-medium">Current Logo</p>
                            <p class="text-xs text-slate-400 mt-1">This logo is used in your customer portal, invoices, and emails</p>
                        </div>
                        <button type="button" onclick="removeLogo()" class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-slate-800">
                            Remove
                        </button>
                    </div>
                </div>

                <!-- New Logo Upload -->
                <div class="space-y-2">
                    <input type="file" name="logo" id="logo" accept="image/*" onchange="previewLogo(this)"
                        class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-sky-600 file:text-white hover:file:bg-sky-700">
                    <p class="text-xs text-slate-400">Upload JPEG, PNG, GIF, or WebP. Max size: 2MB</p>
                </div>

                <!-- New Logo Preview -->
                <div id="new-logo-preview-container" class="mt-4" style="display: none;">
                    <div class="flex items-center space-x-4 p-4 bg-emerald-900 bg-opacity-20 border border-emerald-700 rounded-md">
                        <img id="new-logo-preview" alt="New Logo Preview" class="max-h-20 max-w-40 border border-emerald-500 rounded-md">
                        <div class="flex-1">
                            <p class="text-sm text-emerald-300 font-medium">New Logo Preview</p>
                            <p class="text-xs text-emerald-400 mt-1">This will replace your current logo when you save</p>
                        </div>
                        <button type="button" onclick="clearNewLogo()" class="bg-slate-600 hover:bg-slate-700 text-white px-3 py-2 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 focus:ring-offset-slate-800">
                            Clear
                        </button>
                    </div>
                </div>

                <!-- Hidden field for logo removal -->
                <input type="hidden" name="remove_logo" id="remove_logo" value="">
            </div>
        </div>

        <div class="mt-8 flex justify-end">
            <button type="submit" class="bg-sky-600 hover:bg-sky-700 text-white px-6 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-800">
                Save Company Profile
            </button>
        </div>
    </form>
</div> 