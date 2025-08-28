<script src="https://unpkg.com/@popperjs/core@2"></script>
<script src="https://unpkg.com/tippy.js@6"></script>
<script src="{$WEB_ROOT}/assets/js/tooltips.js"></script>

<div class="min-h-screen bg-slate-800 text-gray-300">
  <div class="container mx-auto px-4 pb-8">

    <div class="flex flex-col sm:flex-row h-16 mx-12 justify-between items-start sm:items-center">
      <!-- Navigation Horizontal -->
      <div class="flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
          class="size-6">
          <path stroke-linecap="round" stroke-linejoin="round"
            d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
        </svg>
        <h2 class="text-2xl font-semibold text-white ml-2">Provision New Services</h2>
      </div>
    </div>

    <!-- Loading Overlay -->
    {* <div id="loading-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50">
      <div class="flex items-center">
            <div class="text-gray-300 text-lg">Please Wait...</div>
            <svg class="animate-spin h-8 w-8 text-gray-300 ml-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
        </div>
    </div> *}

    <!-- Content Container -->
    <div class="mx-8 space-y-12">
      <div
        class="min-h-[calc(100vh-14rem)] h-full p-6 xl:p-12 bg-[#11182759] rounded-lg border border-slate-700 shadow-lg max-w-6xl">
        <div class="flex flex-col lg:flex-row gap-8" x-data="{
                loading: false,
                open: false,
                billingTerm: '',         // holds “monthly” or “annual”
                termOpen: false,         // toggles the term menu
                termOptions: [           // list of choices
                  { value: 'monthly', label: 'Monthly' },
                  { value: 'annual',  label: 'Annual'  },
                ],                
                selectedProduct: '{if !empty($POST.product)}{$POST.product}{/if}',
                selectedName: 'Choose a Service',
                selectedIcon: '',
                selectedDesc: '',
                productType: '',
                termDisabled : false,
                init() {
                  /* handle page‑load pre‑selection */
                  if (this.productType === 'ms365') {
                    this.forceMonthly();
                  }

                  /* react every time the product type changes */
                  this.$watch('productType', (val) => {
                    if (val === 'ms365') {
                      this.forceMonthly();
                    } else {
                      this.termDisabled = false;      // re‑enable menu
                    }
                  });
                },

                /* — NEW HELPER — */
                forceMonthly() {
                  this.billingTerm = 'monthly';
                  this.termDisabled = true;
                  this.termOpen = false;              // make sure the drop‑down is closed
                }
              }">
          <div class="w-full lg:w-1/2 col-form">
            <!-- Loader -->
            <div id="loader" class="loader text-center hidden">
              <img src="{$BASE_PATH_IMG}/loader.svg" alt="Loading..." class="mx-auto mb-2">
              <p class="text-gray-300">Processing your request… This may take up to 60 seconds.</p>
            </div>

            <div class="col-form w-full max-w-lg">
              {if !empty($errors["error"])}
                <div class="bg-red-700 text-gray-100 px-4 py-3 rounded mb-4">
                  {$errors["error"]}
                </div>
              {/if}


              {* hide until Alpine kicks in *}
              <style>
                [x-cloak] {
                  display: none !important;
                }
              </style>

              <form id="createorder" method="post" action="{$modulelink}&a=createorder" class="space-y-4 xl:space-y-8">
                <!-- Product Selection -->
                <div class="{if !empty($errors['product'])}border-red-500{/if}">
                  <label for="product" class="block text-sm font-medium text-gray-300 mb-1">
                    Choose a Service
                  </label>

                  <div class="relative mb-8">
                    <input type="hidden" id="productSelect" name="product" x-model="selectedProduct">

                    <!-- Closed button -->
                    <button type="button" @click="open = !open"
                      class="flex items-center w-full px-3 py-2 border border-slate-700 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600 {if !empty($errors['product'])}border-red-500{/if}">
                      <template x-if="selectedIcon">
                        <span class="flex-shrink-0 mr-3" x-html="selectedIcon"></span>
                      </template>
                      <div class="flex-1 text-left">
                        <div class="text-sm" x-text="selectedName"></div>
                        <template x-if="selectedDesc">
                          <div class="text-xs text-gray-400" x-text="selectedDesc"></div>
                        </template>
                      </div>
                    </button>

                    <!-- Dropdown menu -->
                    <div x-show="open" @click.away="open = false"
                      class="absolute z-10 w-full mt-1 bg-[#151f2e] border border-sky-600 rounded shadow-lg max-h-60 overflow-auto">
                      {foreach $categories.whitelabel as $product}
                        <div @click="
                                          selectedProduct = '{$product.pid}';
                                          selectedName    = '{$product.name}';
                                          selectedDesc    = 'Whitelabel backup client and Control Panel';
                                          selectedIcon    = $event.currentTarget.querySelector('svg').outerHTML;
                                          productType     = 'white';
                                          open            = false
                                        " class="relative group flex items-start px-3 py-2 cursor-pointer">
                          <span
                            class="absolute left-0 inset-y-0 w-1 bg-sky-500 opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                          <div class="flex-shrink-0">
                            <!-- your whitelabel SVG -->
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                              stroke="currentColor" class="w-6 h-6 text-gray-300 mr-3">
                              <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" />
                            </svg>
                          </div>
                          <div class="ml-1">
                            <div class="text-sm text-gray-100">{$product.name}</div>
                            <div class="text-xs text-gray-400">Whitelabel backup client and Control Panel</div>
                          </div>
                        </div>
                      {/foreach}

                      {foreach $categories.eazybackup as $product}
                        <div @click="
                                          selectedProduct = '{$product.pid}';
                                          selectedName    = `eazyBackup {$product.name}`;
                                          selectedDesc    = 'Cloud to Cloud backup for Microsoft 365 data';
                                          selectedIcon    = $event.currentTarget.querySelector('svg').outerHTML;
                                          productType     = 'ms365';
                                          open            = false
                                        " class="relative group flex items-start px-3 py-2 cursor-pointer">
                          <span
                            class="absolute left-0 inset-y-0 w-1 bg-sky-500 opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                          <div class="flex-shrink-0">
                            <!-- your eazyBackup SVG -->
                            <svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="24" height="24"
                              viewBox="0 0 50 50" style="fill:currentColor;" class="text-orange-600 mr-3">
                              <path d="M20.13,32.5c-2.79-1.69-4.53-4.77-4.53-8.04V8.9c0-1.63,0.39-3.19,1.11-4.57L7.54,9.88C4.74,11.57,3,14.65,3,17.92v14.15
                                        c0,1.59,0.42,3.14,1.16,4.5c0.69,1.12,1.67,2.06,2.88,2.74c2.53,1.42,5.51,1.36,7.98-0.15l8.02-4.9L20.13,32.5z M42.84,27.14
                                        l-8.44-5.05v2.29c0,3.25-1.72,6.33-4.49,8.02l-13.84,8.47c-1.52,0.93-3.19,1.42-4.87,1.46l8.93,5.41c1.5,0.91,3.19,1.36,4.87,1.36
                                        s3.37-0.45,4.87-1.36l9.08-5.5l3.52-2.13c0.27-0.16,0.53-0.34,0.78-0.54c0.08-0.05,0.16-0.11,0.23-0.16
                                        c0.65-0.53,1.23-1.13,1.71-1.79c0.02-0.03,0.04-0.06,0.06-0.09c0.77-1.19,1.2-2.59,1.19-4.06C46.43,30.85,45.09,28.48,42.84,27.14z
                                        M42.46,9.88l-9.57-5.79l-3.02-1.83C29.45,2,29.01,1.79,28.56,1.61c-0.49-0.21-1-0.37-1.51-0.47c-1.84-0.38-3.76-0.08-5.46,0.89
                                        c-2.5,1.43-3.99,3.99-3.99,6.87v9.6l2.8-1.65c2.84-1.67,6.36-1.66,9.19,0.03l14.28,8.54c1.29,0.78,2.35,1.81,3.12,3.02L47,17.92
                                        C47,14.65,45.26,11.57,42.46,9.88z"></path>
                            </svg>
                          </div>
                          <div class="ml-1">
                            <div class="text-sm text-gray-100">eazyBackup {$product.name}</div>
                            <div class="text-xs text-gray-400">Cloud to Cloud backup for Microsoft 365 data</div>
                          </div>
                        </div>
                      {/foreach}

                      {if in_array($clientsdetails.groupid,[2,3,4,5,6,7])}
                        {foreach $categories.obc as $product}
                          <div @click="
                          selectedProduct = '{$product.pid}';
                          selectedName    = `OBC {$product.name}`;
                          selectedDesc    = 'Cloud to Cloud backup with OBC branded Control Panel';
                          selectedIcon    = $event.currentTarget.querySelector('svg').outerHTML;
                          productType     = 'ms365';
                          open            = false
                        " class="relative group flex items-start px-3 py-2 cursor-pointer">
                            <span
                              class="absolute left-0 inset-y-0 w-1 bg-sky-500 opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                            <div class="flex-shrink-0">
                              <!-- OBC SVG -->
                              <svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="24" height="24"
                                viewBox="0 0 50 50" style="fill:currentColor;" class="text-indigo-600 mr-3">
                                <!-- same path as above -->
                                <path d="M20.13,32.5c-2.79-1.69-4.53-4.77-4.53-8.04V8.9c0-1.63,0.39-3.19,1.11-4.57L7.54,9.88C4.74,11.57,3,14.65,3,17.92v14.15
                                          c0,1.59,0.42,3.14,1.16,4.5c0.69,1.12,1.67,2.06,2.88,2.74c2.53,1.42,5.51,1.36,7.98-0.15l8.02-4.9L20.13,32.5z M42.84,27.14
                                          l-8.44-5.05v2.29c0,3.25-1.72,6.33-4.49,8.02l-13.84,8.47c-1.52,0.93-3.19,1.42-4.87,1.46l8.93,5.41c1.5,0.91,3.19,1.36,4.87,1.36
                                          s3.37-0.45,4.87-1.36l9.08-5.5l3.52-2.13c0.27-0.16,0.53-0.34,0.78-0.54c0.08-0.05,0.16-0.11,0.23-0.16
                                          c0.65-0.53,1.23-1.13,1.71-1.79c0.02-0.03,0.04-0.06,0.06-0.09c0.77-1.19,1.2-2.59,1.19-4.06C46.43,30.85,45.09,28.48,42.84,27.14z
                                          M42.46,9.88l-9.57-5.79l-3.02-1.83C29.45,2,29.01,1.79,28.56,1.61c-0.49-0.21-1-0.37-1.51-0.47c-1.84-0.38-3.76-0.08-5.46,0.89
                                          c-2.5,1.43-3.99,3.99-3.99,6.87v9.6l2.8-1.65c2.84-1.67,6.36-1.66,9.19,0.03l14.28,8.54c1.29,0.78,2.35,1.81,3.12,3.02L47,17.92
                                          C47,14.65,45.26,11.57,42.46,9.88z"></path>
                              </svg>
                            </div>
                            <div class="ml-1">
                              <div class="text-sm text-gray-100">OBC {$product.name}</div>
                              <div class="text-xs text-gray-400">Cloud to Cloud backup with OBC branded Control Panel</div>
                            </div>
                          </div>
                        {/foreach}
                      {/if}

                    </div>

                    {if !empty($errors['product'])}
                      <span id="product-help" class="text-red-500 text-sm">{$errors['product']}</span>
                    {/if}

                  </div>
                </div>




                <!-- Username Field -->
                <div class="mb-8 {if !empty($errors.username)}border-red-500{/if}">
                  <div class="flex items-center">
                    <!-- Label + Tooltip Icon -->
                    <label for="username" class="w-1/4 text-sm font-medium text-gray-300 flex items-center">
                      Username
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-5 h-5 text-gray-400 ml-2 cursor-pointer"
                        data-tippy-content="At least 6 characters; letters, numbers, underscore, dot, or dash.">
                        <path stroke-linecap="round" stroke-linejoin="round"
                          d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                      </svg>
                    </label>

                    <!-- Input -->
                    <div class="w-3/4">
                      <input type="text" id="username" name="username" placeholder="Username"
                        class="w-full px-3 py-2 border border-slate-700 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600 {if !empty($errors.username)}border-red-500{/if}"
                        {if !empty($POST.username)}value="{$POST.username|escape:"html"}" {/if}>
                      {if !empty($errors.username)}
                        <p class="text-red-500 text-xs mt-1">{$errors.username}</p>
                      {/if}
                    </div>
                  </div>
                </div>

                <!-- Reporting‑email -->
                <div class="mb-8">
                  <div class="flex items-center">
                    <label for="reportemail" class="w-1/4 text-sm font-medium text-gray-300 flex items-center">
                      Email for Reports
                    </label>
                    <div class="w-3/4">
                      <input type="email" id="reportemail" name="reportemail" placeholder="backupreports@example.com"
                        value="{$POST.reportemail|escape:'html'}" class="w-full px-3 py-2 border
                                  {if !empty($errors.reportemail)}border-red-500{else}border-slate-700{/if}
                                  text-gray-300 bg-[#11182759] rounded focus:outline-none
                                  focus:ring-0 focus:border-sky-600" required>
                    </div>
                  </div>

                  {if !empty($errors.reportemail)}
                    <p class="text-red-500 text-xs mt-1">{$errors.reportemail}</p>
                  {/if}
                </div>

                {* hide until Alpine initializes *}
                <style>
                  [x-cloak] {
                    display: none !important;
                  }
                </style>

                <div x-data="{ldelim} showPassword: false, showConfirmPassword: false {rdelim}"
                  class="mb-8 {if !empty($errors.password) || !empty($errors.confirmpassword)}border-red-500{/if}">
                  <div class="flex items-center">
                    <!-- Left label + tooltip icon -->
                    <label for="password" class="w-1/4 text-sm font-medium text-gray-300 flex items-center">
                      Password
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-5 h-5 text-gray-400 ml-2 cursor-pointer"
                        data-tippy-content="At least 8 characters, mix of upper/lowercase, numbers & symbols.">
                        <path stroke-linecap="round" stroke-linejoin="round"
                          d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                      </svg>
                    </label>

                    <!-- Inputs -->
                    <div class="w-3/4 flex space-x-4">
                      <!-- Create Password -->
                      <div class="flex-1 relative">
                        <input :type="showPassword ? 'text' : 'password'" id="password" name="password"
                          placeholder="Create password"
                          class="w-full px-3 py-2 border border-slate-700 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600 {if !empty($errors.password)}border-red-500{/if}">
                        <button type="button" @click="showPassword = !showPassword" tabindex="-1"
                          class="absolute inset-y-0 right-3 flex items-center text-gray-400">
                          <!-- Hidden Icon -->
                          <svg x-show="!showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-slate-500 w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 
                              7.244 19.5 12 19.5c.993 0 1.953-.138 
                              2.863-.395M6.228 6.228A10.451 10.451 0 0 1 
                              12 4.5c4.756 0 8.773 3.162 10.065 
                              7.498a10.522 10.522 0 0 1-4.293 
                              5.774M6.228 6.228l-3.228-3.228m3.228 
                              3.228l3.65 3.65m7.894 7.894L21 
                              21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 
                              1 0-4.243-4.243m4.242 4.242L9.88 
                              9.88" />
                          </svg>
                          <!-- Visible Icon -->
                          <svg x-show="showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-slate-500 w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 
                              7.51 7.36 4.5 12 4.5c4.638 0 8.573 
                              3.007 9.963 7.178.07.207.07.431 
                              0 .639C20.577 16.49 16.64 19.5 12 
                              19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                          </svg>
                        </button>

                        {if !empty($errors.password)}
                          <p class="text-red-500 text-xs mt-1">{$errors.password}</p>
                        {/if}
                      </div>

                      <!-- Confirm Password -->
                      <div class="flex-1 relative">
                        <input :type="showConfirmPassword ? 'text' : 'password'" id="confirmpassword"
                          name="confirmpassword" placeholder="Confirm password"
                          class="w-full px-3 py-2 border border-slate-700 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600 {if !empty($errors.confirmpassword)}border-red-500{/if}">
                        <button type="button" @click="showConfirmPassword = !showConfirmPassword" tabindex="-1"
                          class="absolute inset-y-0 right-3 flex items-center text-gray-400">
                          <!-- Hidden Icon -->
                          <svg x-show="!showConfirmPassword" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-slate-500 w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 
                              7.244 19.5 12 19.5c.993 0 1.953-.138 
                              2.863-.395M6.228 6.228A10.451 10.451 0 0 1 
                              12 4.5c4.756 0 8.773 3.162 10.065 
                              7.498a10.522 10.522 0 0 1-4.293 
                              5.774M6.228 6.228l-3.228-3.228m3.228 
                              3.228l3.65 3.65m7.894 7.894L21 
                              21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 
                              1 0-4.243-4.243m4.242 4.242L9.88 
                              9.88" />
                          </svg>
                          <!-- Visible Icon -->
                          <svg x-show="showConfirmPassword" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-slate-500 w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 
                              7.51 7.36 4.5 12 4.5c4.638 0 8.573 
                              3.007 9.963 7.178.07.207.07.431 
                              0 .639C20.577 16.49 16.64 19.5 12 
                              19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                          </svg>
                        </button>

                        {if !empty($errors.confirmpassword)}
                          <p class="text-red-500 text-xs mt-1">{$errors.confirmpassword}</p>
                        {/if}
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Billing Term Selection -->
                <div class="mb-8">
                  <div class="flex items-center">
                    <label for="billing-term" class="w-1/4 text-sm font-medium text-gray-300">
                      Billing Term
                    </label>
                    <div class="w-3/4 relative">
                      <!-- bind to the root Alpine state -->
                      <input type="hidden" name="billingterm" x-model="billingTerm">

                      <!-- toggle button -->
                      <button type="button" :disabled="termDisabled" @click="!termDisabled && (termOpen = !termOpen)"
                        class="flex items-center w-full px-3 py-2 border
              border-slate-700 text-gray-300 bg-[#11182759] rounded
              focus:outline-none focus:ring-0 focus:border-sky-600
              transition
              " :class="termDisabled
                ? 'opacity-60 cursor-not-allowed bg-slate-700 pointer-events-none'
                : 'hover:bg-slate-700/50'">
                        <span class="flex-1 text-left" x-text="billingTerm
          ? termOptions.find(o => o.value === billingTerm).label
          : 'Select billing term'"></span>
                        <svg class="w-5 h-5 ml-2 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none"
                          viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>

                      <!-- dropdown menu -->
                      <!-- dropdown menu -->
                      <div x-show="termOpen" @click.away="termOpen = false" x-cloak
                        class="absolute z-10 w-full mt-1 bg-[#151f2e] border border-sky-600 rounded shadow-lg">
                        <template x-for="opt in termOptions" :key="opt.value">
                          <div @click="billingTerm = opt.value; termOpen = false"
                            class="relative group flex items-center px-4 py-2 cursor-pointer">
                            <!-- little blue bar -->
                            <span class="absolute left-0 inset-y-0 w-1 bg-sky-500
               opacity-0 transition-opacity duration-200
               group-hover:opacity-100"></span>
                            <!-- option label -->
                            <span class="flex-1 ml-2 text-gray-300 group-hover:text-white" x-text="opt.label"></span>
                          </div>
                        </template>
                      </div>


                      <!-- validation error -->
                      {if !empty($errors['billingterm'])}
                        <p class="text-red-500 text-xs mt-1">Please select a billing term.</p>
                      {/if}
                    </div>
                  </div>
                  <!-- Annual fine-print -->
                  <p x-show="billingTerm === 'annual'" x-cloak class="mt-2 ml-1 text-xs text-gray-400">
                    Annual plans are subject to prorated charges for increased usage during the billing term.
                  </p>
                </div>





                <!-- Next Due Date (readonly) -->
                <div class="mb-8">
                  <div class="flex items-center">
                    <label for="nextduedate" class="w-1/4 text-sm font-medium text-gray-300">
                      Next Due Date
                    </label>
                    <div class="w-3/4">
                      <input id="nextduedate" type="text" disabled x-bind:value="
          billingTerm
            ? (() => {
                const d = new Date();
                d.setMonth(d.getMonth() + 1);
                return d.toISOString().slice(0,10);
              })()
            : ''
        " class="w-full px-3 py-2 border border-slate-700 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                        placeholder="—" />
                    </div>
                  </div>
                </div>





                <!-- Submit Button -->
                <button type="submit" :disabled="loading"
                  class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-700"
                  :class="loading 
                      ? 'opacity-50 cursor-not-allowed bg-green-600' 
                      : 'bg-green-600 hover:bg-green-700'">
                  <!-- swap text -->
                  <span x-text="loading ? 'Processing…' : 'Confirm'"></span>

                  <!-- spinner only when loading -->
                  <svg x-show="loading" class="animate-spin h-5 w-5 ml-2 text-white" xmlns="http://www.w3.org/2000/svg"
                    fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                  </svg>
                </button>
              </form>
            </div>
          </div>

          <!-- right column: dynamic price list -->
          <div class="w-full lg:w-1/2 text-gray-400 space-y-4 mt-12">
            <!-- White-label pricing -->
            <div x-show="productType === 'white'">
              <div class="max-w-lg mx-auto pr-6 pl-6 rounded-lg text-gray-300">
                <h3 class="text-xl font-semibold mb-4">Usage-Based Billing</h3>
                <p class="text-gray-400 mb-4">
                  Your plan includes a <strong>$9.45 / mo</strong> base fee for 1TB storage, any additional services are
                  billed dynamically according to what you use:
                </p>
                <ul class="list-disc list-inside text-gray-400 mb-4 space-y-1">
                  <li><span class="font-medium">Device:</span> $3.50 per device, monthly</li>
                  <li><span class="font-medium">Disk Image (Hyper-V):</span> $4.99 per device, monthly</li>
                  <li><span class="font-medium">Hyper-V:</span> $4.99 per guest VM, monthly</li>
                  <li><span class="font-medium">VMware backups:</span> $8.50 per guest VM, monthly</li>
                  <li><span class="font-medium">Cloud Storage:</span> $9.45 per TB, monthly</li>
                </ul>
                <p class="text-gray-400 mb-4">
                  You’re free to use all features without restriction; charges will automatically adjust each billing
                  cycle to match your actual consumption.
                </p>
                <p class="text-gray-400">
                  <strong>Tip:</strong> You can set hard quotas on the My Services page to cap usage if you like.
                </p>
              </div>
            </div>

            <div x-show="productType === 'ms365'">
              <div class="max-w-lg mx-auto pr-6 pl-6 rounded-lg text-gray-300">
                <h3 class="text-xl font-semibold mb-4">MS 365 Backup Pricing</h3>
                <p class="text-gray-400 mb-4">
                  Your plan includes a <strong>$3.50 / mo</strong> base fee for a single User, billing for additional
                  Users will be charged according to what you use:
                </p>
                <ul class="list-disc list-inside text-gray-400 mb-4 space-y-1">
                  <li><span class="font-medium">Per User:</span> $3.50 per monthly</li>
                  <li><span class="font-medium">Storage:</span> Unlimited per User</li>
                  <li><span class="font-medium">Retention:</span> 1 year (30 daily snapshots and 52 weekly snapshots)
                  </li>
                </ul>
                <p class="text-gray-400 mb-4">
                  Additional data retnetion can be purchased, please contact sales for more information.
                </p>
              </div>
            </div>

            <!-- fallback or placeholder -->
            <div x-show="!productType">
              <p class="italic text-gray-500">Select a service on the left to see pricing here.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Footer Section -->
{* <footer class="h-36 bg-gray-700 lg:border-t border-slate-700">
  </footer> *}
</div>

{* Initialize Tippy somewhere after your scripts *}
<script>
  document.addEventListener('DOMContentLoaded', function() {
    tippy('[data-tippy-content]', {
      theme: 'light-border',
      placement: 'right',
      delay: [200, 50],
    });
  });
</script>