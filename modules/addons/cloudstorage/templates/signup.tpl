<style>
select{   
    background: 
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23D1D5DB' viewBox='0 0 20 20'%3E%3Cpath d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z'/%3E%3C/svg%3E") no-repeat right 0.75rem center / 1.25em 1.25em,
    #f3f4f6 !important;
    background-position: right 0.75rem center;
    background-size: 1.25em 1.25em;    
    padding-right: 2.5rem;
    -moz-appearance: none; 
    -webkit-appearance: none; 
    appearance: none;
}
</style>

<div id="cloudstorage-signup" class="min-h-screen flex items-center justify-center bg-white p-6">
  <div class="shadow-lg rounded-lg overflow-hidden max-w-6xl w-full flex flex-col md:flex-row">
    <!-- Left Column -->
    <div class="w-full min-[850px]:w-2/5 min-[1400px]:w-1/2 p-8 bg-gray-100 text-gray-600">
      <h1 class="text-4xl font-bold mb-6">Start a 30-day free trial</h1>
      <p class="text-xl mb-6">eazyBackup e3 – Always Hot, Canadian-Certified S3 Cloud Storage</p>
      
      <!-- Features Section -->
      <div class="space-y-6">        
        <div>
          <h2 class="flex items-center text-md font-semibold mb-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
              stroke="currentColor" class="h-6 w-6 text-sky-600 mr-2">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            Canadian Owned & Controlled Goods Certified
          </h2>
        </div>        
        <div>
          <h2 class="flex items-center text-md font-semibold mb-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
              stroke="currentColor" class="h-6 w-6 text-sky-600 mr-2">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            Pay-as-you-go billing with a minimum of 1TiB for $9 CAD/month
          </h2>
        </div>        
        <div>
          <h2 class="flex items-center text-md font-semibold mb-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
              stroke="currentColor" class="h-6 w-6 text-sky-600 mr-2">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            No Ingress/Egress/API Fees
          </h2>
        </div>        
        <div>
          <h2 class="flex items-center text-md font-semibold mb-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
              stroke="currentColor" class="h-6 w-6 text-sky-600 mr-2">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            Easily transfer large datasets with assisted data migration
          </h2>
        </div>
      </div>
    </div>

    <!-- Form Column -->
    <div class="w-full min-[850px]:w-3/5 min-[1400px]:w-1/2 bg-gray-100 p-8">
      {* Show a general message if desired *}
      {if !empty($message)}
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
          {$message}
        </div>
      {/if}

      <h1 class="text-gray-600 text-2xl font-semibold mb-4">Create Your e3 Storage Account</h1>
      <form method="post" action="index.php?m=cloudstorage&page=handlesignup" class="space-y-6">
        <!-- Row 1: First Name and Last Name -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <!-- First Name -->
          <div class="flex flex-col">            
          <input type="text"
          id="first_name" name="first_name" placeholder="First Name *" pattern="[A-Za-z]+" title="Enter your first name." required aria-required="true"
              class="bg-gray-100 text-gray-600 placeholder-gray-400 focus:ring-sky-500 focus:border-sky-500 block w-full sm:w-auto px-4 py-2 mr-3 border-b border-gray-600 focus:outline-none focus:ring-0 focus:border-sky-600"
              value="{$POST.first_name|default:''}" />
            {if !empty($errors.first_name)}
              <span class="text-red-500 text-sm mt-1">{$errors.first_name}</span>
            {/if}
          </div>
          <!-- Last Name -->
          <div class="flex flex-col">            
            <input type="text" id="last_name" name="last_name" placeholder="Last Name *" pattern="[A-Za-z]+" title="Enter your last name." required aria-required="true"
              class="bg-gray-100 text-gray-600 placeholder-gray-400 focus:ring-sky-500 focus:border-sky-500 block w-full sm:w-auto px-4 py-2 mr-3 border-b border-gray-600 focus:outline-none focus:ring-0 focus:border-sky-600"
              value="{$POST.last_name|default:''}" />
            {if !empty($errors.last_name)}
              <span class="text-red-500 text-sm mt-1">{$errors.last_name}</span>
            {/if}
          </div>
        </div>

        <!-- Row 2: Username and Phone Number -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <!-- Username -->
            <div class="flex flex-col">            
              <input
                type="text"
                id="username"
                name="username"
                placeholder="Username *"
                required
                aria-required="true"
                pattern="[A-Za-z0-9]+"
                title="Username may only contain letters and numbers."
                class="bg-gray-100 text-gray-600 placeholder-gray-400 focus:ring-sky-500 focus:border-sky-500 block w-full sm:w-auto px-4 py-2 mr-3 border-b border-gray-600 focus:outline-none focus:ring-0 focus:border-sky-600"
                value="{$POST.username|default:''}"
              />
              {if !empty($errors.username)}
                <span class="text-red-500 text-sm mt-1">{$errors.username}</span>
              {/if}
        </div>
          <!-- Phone Number -->
          <div class="flex flex-col ">            
            <input type="text" id="phone" name="phone" placeholder="Phone *" required aria-required="true"
              class="bg-gray-100 text-gray-600 placeholder-gray-400 focus:ring-sky-500 focus:border-sky-500 block w-full px-4 py-2 mr-3 border-b border-gray-600 focus:outline-none focus:ring-0 focus:border-sky-600"
              value="{$POST.phone|default:''}" />
            {if !empty($errors.phone)}
              <span class="text-red-500 text-sm mt-1">{$errors.phone}</span>
            {/if}
          </div>
        </div>

        <div class="hidden">
          <label for="hp_field">Leave this field empty</label>
          <input type="text" id="hp_field" name="hp_field" tabindex="-1" autocomplete="off">
        </div>

        <!-- Row 3: Email and Country -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <!-- Email -->
          <div class="flex flex-col">           
            <input type="text" id="email" name="email" placeholder="Email"
              class="bg-gray-100 text-gray-600 placeholder-gray-400 focus:ring-sky-500 focus:border-sky-500 block w-full sm:w-auto px-4 py-2 mr-3 border-b border-gray-600 focus:outline-none focus:ring-0 focus:border-sky-600"
              value="{$POST.email|default:''}" />
            {if !empty($errors.email)}
              <span class="text-red-500 text-sm mt-1">{$errors.email}</span>
            {/if}
          </div>
          <!-- Country -->
          <div class="flex flex-col">            
            <select id="country" name="country" placeholder="Country"
            class="bg-gray-100 text-gray-600 placeholder-gray-400 focus:ring-sky-500 focus:border-sky-500 block w-full sm:w-auto px-4 py-2 mr-3 border-b border-gray-600 focus:outline-none focus:ring-0 focus:border-sky-600">
            <option value="">-- Select Country --</option>
            <!-- North America & Others -->            
            <option value="CA" {if $POST.country == 'CA'}selected{/if}>Canada</option>
            <option value="GB" {if $POST.country == 'GB'}selected{/if}>United Kingdom</option>
            <option value="AU" {if $POST.country == 'AU'}selected{/if}>Australia</option>
            <option value="AU" {if $POST.country == 'NZ'}selected{/if}>New Zealand</option>
            
            <!-- South America -->
            <option value="BR" {if $POST.country == 'BR'}selected{/if}>Brazil</option>
            <option value="AR" {if $POST.country == 'AR'}selected{/if}>Argentina</option>
            <option value="CL" {if $POST.country == 'CL'}selected{/if}>Chile</option>
            <option value="CO" {if $POST.country == 'CO'}selected{/if}>Colombia</option>
            <option value="PE" {if $POST.country == 'PE'}selected{/if}>Peru</option>
            <option value="EC" {if $POST.country == 'EC'}selected{/if}>Ecuador</option>
            <option value="UY" {if $POST.country == 'UY'}selected{/if}>Uruguay</option>
            <option value="VE" {if $POST.country == 'VE'}selected{/if}>Venezuela</option>
            <option value="PY" {if $POST.country == 'PY'}selected{/if}>Paraguay</option>
            
            <!-- Europe -->
            <option value="DE" {if $POST.country == 'DE'}selected{/if}>Germany</option>
            <option value="FR" {if $POST.country == 'FR'}selected{/if}>France</option>
            <option value="IT" {if $POST.country == 'IT'}selected{/if}>Italy</option>
            <option value="ES" {if $POST.country == 'ES'}selected{/if}>Spain</option>
            <option value="NL" {if $POST.country == 'NL'}selected{/if}>Netherlands</option>
            <option value="BE" {if $POST.country == 'BE'}selected{/if}>Belgium</option>
            <option value="CH" {if $POST.country == 'CH'}selected{/if}>Switzerland</option>
            <option value="SE" {if $POST.country == 'SE'}selected{/if}>Sweden</option>
            <option value="NO" {if $POST.country == 'NO'}selected{/if}>Norway</option>
            <option value="DK" {if $POST.country == 'DK'}selected{/if}>Denmark</option>
            <option value="AT" {if $POST.country == 'AT'}selected{/if}>Austria</option>
            <option value="PT" {if $POST.country == 'PT'}selected{/if}>Portugal</option>
            <option value="US" {if $POST.country == 'US'}selected{/if}>United States</option>  
          </select>
            {if !empty($errors.country)}
              <span class="text-red-500 text-sm mt-1">{$errors.country}</span>
            {/if}
          </div>
        </div>

        <!-- Row 4: Password and Confirm Password -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">          
          <div class="relative flex flex-col">
            <input
              type="password"
              id="password"
              name="password"
              placeholder="Password *"
              required
              class="bg-gray-100 text-gray-600 placeholder-gray-400 focus:ring-sky-500 focus:border-sky-500 block w-full px-4 py-2 border-b border-gray-600 focus:outline-none focus:ring-0 focus:border-sky-600 pr-10"
              value="{$POST.password|default:''}"
            />
            <button
              type="button"
              tabindex="-1"
              class="absolute inset-y-0 right-0 flex items-center px-3"
              aria-label="Toggle password visibility"
              onclick="togglePasswordVisibility('password', this)"
            >              
              <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                  viewBox="0 0 24 24" stroke-width="1.5"
                  stroke="currentColor" class="h-5 w-5 text-gray-500">
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51
                    7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431
                    0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
              </svg>
            </button>
            {if !empty($errors.password)}
              <span class="text-red-500 text-sm mt-1">{$errors.password}</span>
            {/if}
          </div>

          <!-- Confirm Password -->
          <div class="relative flex flex-col">
            <input
              type="password"
              id="password_verify"
              name="password_verify"
              placeholder="Confirm Password *"
              required
              class="bg-gray-100 text-gray-600 placeholder-gray-400 focus:ring-sky-500 focus:border-sky-500 block w-full px-4 py-2 border-b border-gray-600 focus:outline-none focus:ring-0 focus:border-sky-600 pr-10"
              value="{$POST.password_verify|default:''}"
            />
            <button
              type="button"
              tabindex="-1"
              class="absolute inset-y-0 right-0 flex items-center px-3"
              aria-label="Toggle password visibility"
              onclick="togglePasswordVisibility('password_verify', this)"
            >              
              <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                  viewBox="0 0 24 24" stroke-width="1.5"
                  stroke="currentColor" class="h-5 w-5 text-gray-500">
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51
                    7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431
                    0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
              </svg>
            </button>
            {if !empty($errors.password_verify)}
              <span class="text-red-500 text-sm mt-1">{$errors.password_verify}</span>
            {/if}
          </div>
        </div>


        <!-- Terms and Submit -->
        <div class="flex items-center">
          <input type="checkbox" id="agree" name="agree" required
            class="h-5 w-5 accent-sky-600 text-sky-600 focus:ring-sky-700 border-gray-300 rounded">
          <label for="agree" class="ml-2 text-sm text-gray-500">
            By signing up, you agree to the
            <a href="https://eazybackup.com/terms/" target="_blank" class="text-sky-500 hover:underline">Terms of Service</a>
            and the <a href="https://eazybackup.com/privacy/" class="text-sky-500 hover:underline">Privacy Policy</a>.
          </label>
        </div>

        <div class="flex justify-center">
                    <div class="cf-turnstile" data-sitekey="{$TURNSTILE_SITE_KEY}" data-theme="light"></div>
                    </div>
                    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

        <!-- Submit Button -->
        <button type="submit"
          class="w-full bg-sky-600 text-white py-2 px-4 rounded hover:bg-sky-700 transition duration-200">
          Sign Up
        </button>

        <p class="text-center text-sm text-gray-500 mt-4">
            Already have an account? <a href="https://accounts.eazybackup.ca/index.php/login" class="text-gray-500 underline hover:underline"> Sign in here</a>
           
          </p>

          {literal}
            <script>
              document.addEventListener('DOMContentLoaded', function() {
                const form      = document.querySelector('form');
                const hpField   = document.getElementById('hp_field');
                const firstName = document.getElementById('first_name');
                const lastName  = document.getElementById('last_name');
                const nameRe    = /^[A-Za-z]+$/;
              
                form.addEventListener('submit', function(e) {
                  // 1) Honeypot must be empty
                  if (hpField.value.trim() !== '') {
                    // likely a bot
                    e.preventDefault();
                    return false;
                  }
              
                  // 2) First/Last name only letters
                  let clientErrors = [];
                  if (! nameRe.test(firstName.value.trim()) ) {
                    clientErrors.push('First Name may only contain letters.');
                  }
                  if (! nameRe.test(lastName.value.trim()) ) {
                    clientErrors.push('Last Name may only contain letters.');
                  }
              
                  if (clientErrors.length) {
                    e.preventDefault();
                    // display errors at top of form (or next to fields)
                    let msg = clientErrors.join('\n');
                    alert(msg);
                    return false;
                  }
              
                  // else let it submit
                });
              });
              </script>
            {/literal}

            {literal}
              <script>
              function togglePasswordVisibility(inputId, btn) {
                const input = document.getElementById(inputId);
                if (!input) return;
                // flip the type
                const isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
              
                // Optionally: change the icon’s style or swap it out
                const svg = btn.querySelector('svg');
                if (svg) {
                  svg.classList.toggle('text-gray-500');
                  svg.classList.toggle('text-sky-600');
                  // You could swap paths or rotate the icon if you like
                }
              }
              </script>
            {/literal}
                          
      </form>
    </div>
  </div>
</div>


  


