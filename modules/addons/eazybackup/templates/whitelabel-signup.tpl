<!-- accounts/modules/addons/eazybackup/templates/whitelabel-signup.tpl -->
<div class="flex flex-col flex-1 overflow-y-auto bg-gray-700">
  <div class="flex items-center justify-center min-h-screen px-4">
    <div class="w-full">
      <!-- Heading Container -->
      <div class="main-section-header-tabs shadow rounded-t-md border-b border-gray-700 bg-gray-800 mx-4 mt-4 pt-4 pr-4 pl-4">
        <h1 class="text-xl font-semibold text-gray-300">
          Backup Client and Control Panel Branding
        </h1>
      </div>

      <!-- Content Container -->
      <div class="bg-gray-800 shadow rounded-b p-4 mx-4 mb-4">
        <!-- Loader icon, hidden by default -->
        <div id="loader" class="loader text-center hidden">
          <img src="{$BASE_PATH_IMG}/loader.svg" alt="Loading..." class="mx-auto mb-2">
          <p class="text-gray-300">Processing your request...</p>
        </div>

        <div class="col-form w-full max-w-lg">
          {if !empty($errors["error"])}
            <div class="bg-red-700 text-gray-100 px-4 py-3 rounded mb-4">
              {$errors["error"]}
            </div>
          {/if}

          {if !empty($successMessage)}
            <div class="bg-green-700 text-gray-100 px-4 py-3 rounded mb-4">
              {$successMessage}
            </div>
          {/if}


          <div class="max-w-lg mx-auto bg-gray-700 border-2 border-sky-500 text-gray-200 p-4 rounded-lg shadow-md mb-4">
          <div class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
              <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
            </svg>
        
              <div>                  
                  <p class="text-sm">
                        Visit our 
                      <a href="https://docs.eazybackup.com/eazybackup-rebranding/backup-client-and-control-panel-branding" target="_blank" class="text-sky-400 underline font-medium">Knowledge Base</a> for step-by-step instructions.
                  </p>
              </div>
          </div>
      </div>

              <!-- White-Label Requirements Toggle -->
              {literal}
                <div x-data="{ open: false }" class="max-w-lg mx-auto mb-4">
                  <button
                    @click="open = !open"
                    class="flex items-center w-full px-4 py-2 bg-gray-700 border border-sky-500 rounded-lg hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-sky-400"
                  >
                    <svg
                      :class="open ? 'rotate-180' : ''"
                      class="h-5 w-5 text-sky-400 transition-transform"
                      xmlns="http://www.w3.org/2000/svg"
                      fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 9l-7 7-7-7" />
                    </svg>
                    <span class="ml-3 text-gray-200 font-medium">White Label Service Requirements</span>
                  </button>
        
                  <div
                    x-show="open"
                    x-transition
                    class="mt-4 bg-gray-800 border border-sky-500 p-4 rounded-lg text-gray-200 space-y-2 text-sm"
                  >
                    <ul class="list-disc list-inside space-y-1">
                      <li>
                        <strong>Existing Partners:</strong>  
                        Have at least 10 active user accounts at the time you enroll in the white-label program (or reach 10 accounts within your mutually agreed timeframe).
                      </li>
                      <li>
                        <strong>New Partners:</strong>  
                        Add at least 10 user accounts within 30 days of enrolling in the white-label program (or within your mutually agreed timeframe).
                      </li>
                      <li>
                        <strong>Maintenance Fee:</strong>  
                        If you do not meet the minimum account requirement within the agreed period—whether you are a new or existing partner—a $45 monthly maintenance fee will apply.
                      </li>
                    </ul>
                  </div>
                </div>
                {/literal}
          <!-- Company Name -->
          <div class="mb-4 {if !empty($errors["company_name"])}border-red-500{/if}">
            <label for="company_name" class="block text-sm font-medium text-gray-300 mb-1">
              Company Name *
            </label>
            <input
              type="text"
              id="company_name"
              name="company_name"
              class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600"
              value="{$POST.company_name|escape:'html'}"
            >
            {if !empty($errors["company_name"])}
              <span class="text-red-500 text-sm">{$errors["company_name"]}</span>
            {/if}
          </div>

          <form id="whitelabelSignupForm" method="post" action="{$modulelink}&a=whitelabel-signup" enctype="multipart/form-data" class="space-y-4">
            <!-- Product Name -->
            <div class="mb-4 {if !empty($errors["product_name"])}border-red-500{/if}">
              <label for="product_name" class="block text-sm font-medium text-gray-300 mb-1">
                Product Name *
              </label>
              <input
                type="text"
                id="product_name"
                name="product_name"
                required
                aria-required="true"
                class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                value="{$POST.product_name|escape:'html'}"
              >
              {if !empty($errors["product_name"])}
                <span class="text-red-500 text-sm">{$errors["product_name"]}</span>
              {/if}
            </div>

            <!-- Help URL -->
            <div class="mb-4 {if !empty($errors["help_url"])}border-red-500{/if}">
              <label for="help_url" class="block text-sm font-medium text-gray-300 mb-1">
                Help URL
              </label>
              <input
                type="url"
                id="help_url"
                name="help_url"
                class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                value="{$POST.help_url|escape:'html'}"
              >
              {if !empty($errors["help_url"])}
                <span class="text-red-500 text-sm">{$errors["help_url"]}</span>
              {/if}
            </div>
            
            <!-- EULA -->
            <div class="mb-4 {if !empty($errors["eula"])}border-red-500{/if}">
              <label for="eula" class="block text-sm font-medium text-gray-300 mb-1">
                EULA
              </label>
              <textarea
                id="eula"
                name="eula"
                rows="4"
                class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600"
              >{$POST.eula|escape:'html'}</textarea>
              {if !empty($errors["eula"])}
                <span class="text-red-500 text-sm">{$errors["eula"]}</span>
              {/if}
            </div>

            <!-- Tile Background (Hex Color) -->
            <div class="mb-4">
              <label for="tile_background" class="block text-sm font-medium text-gray-300 mb-1">
                Tile Background (Hex)
              </label>
              <div class="flex items-center">
                <input
                  type="text"
                  id="tile_background"
                  name="tile_background"
                  class="w-1/2 px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                  value="{$POST.tile_background|escape:'html'}"
                  placeholder="#FFFFFF"
                >
                <input
                  type="color"
                  id="tile_background_picker"
                  class="ml-2 h-10 w-10 border"
                  value="{$POST.tile_background|default:'#FFFFFF'}"
                >
              </div>              
            </div>

            <!-- Header (Existing file field above already handles header image) -->
            <!-- Icon (Windows) -->
            <div class="mb-4 {if !empty($errors["icon_windows"])}border-red-500{/if}">
              <label for="icon_windows" class="block text-sm font-medium text-gray-300 mb-1">
                Icon (Windows)
              </label>
              <input
                type="file"
                id="icon_windows"
                name="icon_windows"
                accept=".jpg,.jpeg,.png,.svg,.ico"
                class="block w-full text-sm text-slate-500 file:mr-4 file:py-1 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-medium file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100"
              >
              {if !empty($errors["icon_windows"])}
                <span class="text-red-500 text-sm">{$errors["icon_windows"]}</span>
              {/if}
            </div>

            <!-- Icon (macOS) -->
            <div class="mb-4 {if !empty($errors["icon_macos"])}border-red-500{/if}">
              <label for="icon_macos" class="block text-sm font-medium text-gray-300 mb-1">
                Icon (macOS)
              </label>
              <input
                type="file"
                id="icon_macos"
                name="icon_macos"
                accept=".jpg,.jpeg,.png,.svg,.ico"
                class="block w-full text-sm text-slate-500 file:mr-4 file:py-1 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-medium file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100"
              >
              {if !empty($errors["icon_macos"])}
                <span class="text-red-500 text-sm">{$errors["icon_macos"]}</span>
              {/if}
            </div>

            <!-- Menu Bar Icon (macOS) -->
            <div class="mb-4 {if !empty($errors["menu_bar_icon_macos"])}border-red-500{/if}">
              <label for="menu_bar_icon_macos" class="block text-sm font-medium text-gray-300 mb-1">
                Menu Bar Icon (macOS)
              </label>
              <input
                type="file"
                id="menu_bar_icon_macos"
                name="menu_bar_icon_macos"
                accept=".jpg,.jpeg,.png,.svg,.ico"
                class="block w-full text-sm text-slate-500 file:mr-4 file:py-1 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-medium file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100"
              >
              {if !empty($errors["menu_bar_icon_macos"])}
                <span class="text-red-500 text-sm">{$errors["menu_bar_icon_macos"]}</span>
              {/if}
            </div>

            <!-- Logo Image (100x32) -->
            <div class="mb-4 {if !empty($errors["logo_image"])}border-red-500{/if}">
              <label for="logo_image" class="block text-sm font-medium text-gray-300 mb-1">
                Logo Image (100x32)
              </label>
              <input
                type="file"
                id="logo_image"
                name="logo_image"
                accept=".jpg,.jpeg,.png,.svg,.ico"
                class="block w-full text-sm text-slate-500 file:mr-4 file:py-1 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-medium file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100"
              >
              {if !empty($errors["logo_image"])}
                <span class="text-red-500 text-sm">{$errors["logo_image"]}</span>
              {/if}
            </div>

            <!-- Tile Image (150x150) -->
            <div class="mb-4 {if !empty($errors["tile_image"])}border-red-500{/if}">
              <label for="tile_image" class="block text-sm font-medium text-gray-300 mb-1">
                Tile Image (150x150)
              </label>
              <input
                type="file"
                id="tile_image"
                name="tile_image"
                accept=".jpg,.jpeg,.png,.svg,.ico"
                class="block w-full text-sm text-slate-500 file:mr-4 file:py-1 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-medium file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100"
              >
              {if !empty($errors["tile_image"])}
                <span class="text-red-500 text-sm">{$errors["tile_image"]}</span>
              {/if}
            </div>

            <!-- Background Logo -->
            <div class="mb-4 {if !empty($errors["background_logo"])}border-red-500{/if}">
              <label for="background_logo" class="block text-sm font-medium text-gray-300 mb-1">
                Background Logo
              </label>
              <input
                type="file"
                id="background_logo"
                name="background_logo"
                accept=".jpg,.jpeg,.png,.svg,.ico"
                class="block w-full text-sm text-slate-500 file:mr-4 file:py-1 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-medium file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100"
              >
              {if !empty($errors["background_logo"])}
                <span class="text-red-500 text-sm">{$errors["background_logo"]}</span>
              {/if}
            </div>

            <!-- App Icon Image (256x256) -->
            <div class="mb-4 {if !empty($errors["app_icon_image"])}border-red-500{/if}">
              <label for="app_icon_image" class="block text-sm font-medium text-gray-300 mb-1">
                App Icon Image (256x256)
              </label>
              <input
                type="file"
                id="app_icon_image"
                name="app_icon_image"
                accept=".jpg,.jpeg,.png,.svg,.ico"
                class="block w-full text-sm text-slate-500 file:mr-4 file:py-1 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-medium file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100"
              >
              {if !empty($errors["app_icon_image"])}
                <span class="text-red-500 text-sm">{$errors["app_icon_image"]}</span>
              {/if}
            </div>

            <!-- Header (Image Upload) -->
            <div class="mb-4 {if !empty($errors["header"])}border-red-500{/if}">

              <h1 class="text-xl font-semibold text-gray-300 border-b border-gray-700 mb-4">
                Control Panel Branding
              </h1>

              <!-- Custom Control Panel Domain (Read-only with Copy Button) -->
              <div class="mb-4">
                <label for="custom_domain" class="block text-sm font-medium text-gray-300 mb-1">
                  Custom Control Panel Domain
                </label>
                <div class="flex items-center">
                  <input type="text" id="custom_domain" name="custom_domain" value="{$custom_domain}" readonly class="w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600">
                  <button type="button" id="copyButton" class="ml-2 inline-flex items-center px-3 py-2 border border-transparent text-xs font-medium rounded text-white bg-sky-600 hover:bg-sky-700 focus:outline-none"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                </svg>
                </button>
                </div>
                <ul>
                <li class="text-xs text-gray-300">We generate a unique subdomain for your account.</li>
                <li class="text-xs text-gray-300">To access it using your own domain, create a CNAME record in your DNS settings.</li>
              </ul>
              </div>

              <!-- Page Title -->
              <div class="mb-4 {if !empty($errors["page_title"])}border-red-500{/if}">
                <label for="page_title" class="block text-sm font-medium text-gray-300 mb-1">
                  Control Panel Page Title *
                </label>
                <input
                  type="text"
                  id="page_title"
                  name="page_title"
                  required
                  aria-required="true"
                  class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                  value="{$POST.page_title|escape:'html'}"
                >
                {if !empty($errors["page_title"])}
                  <span class="text-red-500 text-sm">{$errors["page_title"]}</span>
                {/if}
              </div>


              <label for="header" class="block text-sm font-medium text-gray-300 mb-1">
                Header Image
              </label>
              <input
                type="file"
                id="header"
                name="header"
                accept=".jpg,.jpeg,.png,.svg,.ico"
                class="block w-full text-sm text-slate-500 file:mr-4 file:py-1 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-medium file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100"
              >
              {if !empty($errors["header"])}
                <span class="text-red-500 text-sm">{$errors["header"]}</span>
              {/if}
            </div>

            <!-- Header Color -->
            <div class="mb-4">
              <label for="header_color" class="block text-sm font-medium text-gray-300 mb-1">
                Header Color (Hex)
              </label>
              <div class="flex items-center">
                <input
                  type="text"
                  id="header_color"
                  name="header_color"
                  class="w-1/2 px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                  value="{$POST.header_color|escape:'html'}"
                  placeholder="#FFFFFF"
                >
                <input
                  type="color"
                  id="header_color_picker"
                  class="ml-2 h-10 w-10 border"
                  value="{$POST.header_color|default:'#FFFFFF'}"
                >
              </div>              
            </div>

            <!-- Accent Color -->
            <div class="mb-4">
              <label for="accent_color" class="block text-sm font-medium text-gray-300 mb-1">
                Accent Color (Hex)
              </label>
              <div class="flex items-center">
                <input
                  type="text"
                  id="accent_color"
                  name="accent_color"
                  class="w-1/2 px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                  value="{$POST.accent_color|escape:'html'}"
                  placeholder="#FFFFFF"
                >
                <input
                  type="color"
                  id="accent_color_picker"
                  class="ml-2 h-10 w-10 border"
                  value="{$POST.accent_color|default:'#FFFFFF'}"
                >
              </div>              
            </div>

            <!-- Tab Icon -->
            <div class="mb-4 {if !empty($errors["tab_icon"])}border-red-500{/if}">
              <label for="tab_icon" class="block text-sm font-medium text-gray-300 mb-1">
                Tab Icon
              </label>
              <input
                type="file"
                id="tab_icon"
                name="tab_icon"
                accept=".jpg,.jpeg,.png,.svg,.ico"
                class="block w-full text-sm text-slate-500 file:mr-4 file:py-1 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-medium file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100"
              >
              {if !empty($errors["tab_icon"])}
                <span class="text-red-500 text-sm">{$errors["tab_icon"]}</span>
              {/if}
            </div>
            <!-- Custom SMTP Server Section -->
            <div class="mb-4">
              <button type="button" id="toggleSmtp" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none">
                Custom SMTP Server (optional)
              </button>
            </div>
            <div id="smtpSection" class="hidden border border-gray-600 p-4 rounded">
              <!-- Send as (display name) -->
              <div class="mb-4">
                <label for="smtp_sendas_name" class="block text-sm font-medium text-gray-300 mb-1">
                  Send as (display name)
                </label>
                <input type="text" id="smtp_sendas_name" name="smtp_sendas_name" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600" value="{$POST.smtp_sendas_name|escape:'html'}">
              </div>
              <!-- Send as (email) -->
              <div class="mb-4">
                <label for="smtp_sendas_email" class="block text-sm font-medium text-gray-300 mb-1">
                  Send as (email)
                </label>
                <input type="email" id="smtp_sendas_email" name="smtp_sendas_email" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600" value="{$POST.smtp_sendas_email|escape:'html'}">
              </div>
              <!-- SMTP Server and Port -->
              <div class="mb-4 flex space-x-4">
                <div class="w-2/3">
                  <label for="smtp_server" class="block text-sm font-medium text-gray-300 mb-1">
                    SMTP Server
                  </label>
                  <input type="text" id="smtp_server" name="smtp_server" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600" value="{$POST.smtp_server|escape:'html'}">
                </div>
                <div class="w-1/3">
                  <label for="smtp_port" class="block text-sm font-medium text-gray-300 mb-1">
                    Port
                  </label>
                  <input type="number" id="smtp_port" name="smtp_port" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600" value="{$POST.smtp_port|escape:'html'}">
                </div>
              </div>
              <!-- SMTP Username -->
              <div class="mb-4">
                <label for="smtp_username" class="block text-sm font-medium text-gray-300 mb-1">
                  Username
                </label>
                <input type="text" id="smtp_username" name="smtp_username" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600" value="{$POST.smtp_username|escape:'html'}">
              </div>
              <!-- SMTP Password -->
              <div class="mb-4">
                <label for="smtp_password" class="block text-sm font-medium text-gray-300 mb-1">
                  Password
                </label>
                <input type="password" id="smtp_password" name="smtp_password" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600">
              </div>
              <!-- Security -->
              <div class="mb-4">
                <label for="smtp_security" class="block text-sm font-medium text-gray-300 mb-1">
                  Security
                </label>
                <select id="smtp_security" name="smtp_security" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600">
                  <option value="SSL/TLS" {if $POST.smtp_security == "SSL/TLS"}selected{/if}>SSL/TLS</option>
                  <option value="STARTTLS" {if $POST.smtp_security == "STARTTLS"}selected{/if}>STARTTLS</option>
                  <option value="Plain" {if $POST.smtp_security == "Plain"}selected{/if}>Plain</option>
                </select>
              </div>
            </div>

            <!-- Submit Button -->
            <button
              type="submit"
              class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700"
            >
              Confirm
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- JavaScript to show loader, sync color inputs, copy domain, and toggle SMTP section -->
<script>
  document.getElementById("whitelabelSignupForm").addEventListener("submit", function(event) {
    document.getElementById("loader").classList.remove("hidden");
    document.querySelector(".col-form").style.display = "none";
  });

  // Sync text and color picker for hex inputs
  function syncColor(textInputId, colorPickerId, previewId) {
    var textInput = document.getElementById(textInputId);
    var colorPicker = document.getElementById(colorPickerId);
    var preview = document.getElementById(previewId);
    textInput.addEventListener("input", function() {
      colorPicker.value = textInput.value;
      preview.style.backgroundColor = textInput.value;
    });
    colorPicker.addEventListener("input", function() {
      textInput.value = colorPicker.value;
      preview.style.backgroundColor = colorPicker.value;
    });
  }
  syncColor("header_color", "header_color_picker", "header_color_preview");
  syncColor("accent_color", "accent_color_picker", "accent_color_preview");
  syncColor("tile_background", "tile_background_picker", "tile_background_preview");

  // Copy custom domain to clipboard
  document.getElementById("copyButton").addEventListener("click", function() {
    var domainField = document.getElementById("custom_domain");
    domainField.select();
    domainField.setSelectionRange(0, 99999);
    document.execCommand("copy");
    alert("Copied: " + domainField.value);
  });

  // Toggle Custom SMTP Server section
  document.getElementById("toggleSmtp").addEventListener("click", function() {
    var smtpSection = document.getElementById("smtpSection");
    if (smtpSection.classList.contains("hidden")) {
      smtpSection.classList.remove("hidden");
      this.textContent = "Hide SMTP Server Settings";
    } else {
      smtpSection.classList.add("hidden");
      this.textContent = "Custom SMTP Server (optional)";
    }
  });
</script>

<style>
  .col-form {
    /* Additional custom styling if needed */
  }
</style>
