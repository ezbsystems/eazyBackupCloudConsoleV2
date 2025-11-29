<div id="loading-overlay" style="display: none;" class="fixed inset-0 bg-black/75 flex items-center justify-center z-50">
    <div class="flex flex-col items-center">
        <svg class="animate-spin h-8 w-8 text-gray-300 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
        </svg>
        <div class="text-gray-300 text-lg">
            Provisioning your account, please wait...
        </div>
    </div>
</div>



<div id="trial-signup" class="relative min-h-screen flex items-center justify-center p-6">   
    <div class="absolute inset-0 bg-white"></div>
    
    <!-- Main Content -->
    <div class="relative z-10 shadow-lg rounded-lg overflow-hidden max-w-6xl w-full flex flex-col md:flex-row">
        
        <!-- Left Column -->
        <div class="w-full min-[850px]:w-2/5 min-[1400px]:w-3/5 p-8 bg-gray-100 text-gray-600">
            <h1 class="text-3xl font-bold mb-6">Try eazyBackup for Free</h1>
            <p class="text-lg mb-6">
            Engineered to meet strict compliance and security standards—including government-certified protection. With clear, transparent pricing and the assurance of Canadian data sovereignty, your secure storage strategy starts here.
            </p>
            <div class="space-y-6">
                <h2 class="flex items-center text-md font-semibold mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="h-6 w-6 text-sky-600 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                        d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    Automatic Backups for Workstations and Servers
                </h2>
                <h2 class="flex items-center text-md font-semibold mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="h-6 w-6 text-sky-600 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                        d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    Cloud backup for Microsoft 365 users
                </h2>                
                <h2 class="flex items-center text-md font-semibold mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="h-6 w-6 text-sky-600 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                        d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    Canadian data sovereignty
                </h2>    
                <h2 class="flex items-center text-md font-semibold mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="h-6 w-6 text-sky-600 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                        d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    Unlimited storage during your trial
                </h2>                    
                <h2 class="flex items-center text-md font-semibold mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="h-6 w-6 text-sky-600 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                        d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    No credit card required
                </h2>    
            </div>
        </div>
        
        <!-- Form Column -->
        <div class="w-full min-[850px]:w-3/5 min-[1400px]:w-2/5 bg-gray-100 p-8">
            
            {if !empty($errors["error"])}
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
                    {$errors["error"]}
                </div>
            {/if}
            
            <h1 class="text-gray-700 text-2xl font-semibold mb-4">Start your 14-day free trial</h1>
            
            {if !empty($emailSent)}
                <!-- Email verification confirmation state -->
                <div class="mt-6 space-y-4 text-sm">
                    <div class="rounded-xl border border-emerald-500/50 px-4 py-4 text-emerald-50">
                        <h2 class="text-md font-semibold tracking-tight text-gray-700">
                            Please check your email
                        </h2>
                        <p class="mt-1.5 text-sm text-gray-600">
                            We’ve sent a verification link to <span class="font-mono">{$email|escape}</span>.
                            Click the link in that email to verify your address and activate your eazyBackup trial.
                        </p>
                    </div>
                    <p class="text-[11px] text-slate-400">
                        If you don’t see the email in a few minutes, please check your spam or junk folder. You can safely close this page; the link will continue to work until it expires.
                    </p>
                </div>
            {else}
            <form id="signup" method="post" action="{$modulelink}&a=signup" class="space-y-6">
                
                <!-- Username and Email -->
                <div class="flex flex-row space-x-4">
                    <!-- Username -->
                    <div class="flex flex-col w-1/2">
                        <label for="username" class="mb-2 font-medium text-gray-700">Username</label>
                        <input type="text" id="username" name="username" 
                            class="block w-full rounded-md bg-slate-200 px-3 py-1.5 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-100 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:text-sm/6 {if !empty($errors["username"])}border-red-500{/if}" 
                            {if !empty($POST["username"])}value="{$POST["username"]}"{/if}>
                        {if !empty($errors["username"])}
                            <span class="text-red-500 text-sm mt-1">{$errors["username"]}</span>
                        {/if}
                    </div>
                    
                    <!-- Email Address -->
                    <div class="flex flex-col w-1/2">
                        <label for="email" class="mb-2 font-medium text-gray-700">Email address</label>
                        <input type="email" id="email" name="email" 
                            class="block w-full rounded-md bg-slate-200 px-3 py-1.5 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-100 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:text-sm/6 {if !empty($errors["email"])}border-red-500{/if}" 
                            {if !empty($POST["email"])}value="{$POST["email"]}"{/if}>
                        {if !empty($errors["email"])}
                            <span class="text-red-500 text-sm mt-1">{$errors["email"]}</span>
                        {/if}
                    </div>
                </div>
                
                <!-- Phone Number -->
                <div class="flex flex-col">
                    <label for="phonenumber" class="mb-2 font-medium text-gray-700">Phone number</label>
                    <input type="text"
                        id="contact_number"          
                        name="contact_number"       
                        class="block w-full rounded-md bg-slate-200 px-3 py-1.5 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 !placeholder:text-gray-100 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:text-sm/6 {if !empty($errors["phonenumber"])}border-red-500{/if}" 
                        {if !empty($POST["phonenumber"])}value="{$POST["phonenumber"]|escape:'html'}"{/if}
                        data-server-value="{$POST["phonenumber"]|escape:'html'}"  
                    >
                    <input type="hidden" id="phonenumber" name="phonenumber" value="{if !empty($POST["phonenumber"])}{$POST["phonenumber"]|escape:'html'}{/if}">
                    {if !empty($errors["phonenumber"])}
                        <span class="text-red-500 text-sm mt-1">{$errors["phonenumber"]}</span>
                    {/if}
                </div>
                
                

                <!-- Product Selection -->
                <div class="flex flex-col">
                <label for="product" class="mb-2 font-medium text-gray-700">Select Backup Plan</label>
                <select id="product" name="product" required class="block w-full rounded-md !bg-slate-200 px-3 py-1.5 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-100 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:text-sm/6">
                    <option value="" disabled selected>Select a product</option>
                    <option value="58" {if $POST.product == '58'}selected{/if}>eazyBackup</option>
                    <option value="52" {if $POST.product == '52'}selected{/if}>Microsoft 365 Backup</option>
                </select>
                {if !empty($errors["product"])}
                    <span class="text-red-500 text-sm mt-1">{$errors["product"]}</span>
                {/if}
                </div>

                
                <!-- Terms -->
                <div class="flex items-center">
                    <input type="checkbox" id="agree" name="agree" required
                    class="h-5 w-5 accent-sky-600 text-sky-600 focus:ring-sky-700 border-gray-300 rounded">
                    <label for="agree" class="ml-2 text-sm text-gray-500">
                    By signing up, you agree to the
                    <a href="https://eazybackup.com/terms/" target="_blank" class="text-sky-500 hover:underline">Terms of Service</a>
                    and the <a href="https://eazybackup.com/privacy/" class="text-sky-500 hover:underline">Privacy Policy</a>.
                    </label>
                </div>                
                
                <!-- Hidden field that will always carry the token -->
                <input type="hidden" name="cf-turnstile-response" id="cf-turnstile-response" />

                <!-- Dedicated container for Turnstile; we will render into this explicitly -->
                <div class="flex justify-center">
                <div id="cf-turnstile-container" class="cf-turnstile-slot"></div>
                    </div>

                <!-- Load the Turnstile library explicitly (prevents auto-render race) and enable debug -->
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=cfTurnstileReady&render=explicit&debug=1" async defer></script>
                <script>
                // Render function you can call on initial load AND on any post-back
                function renderTurnstile() {
                    if (window._cfRendered) { return; }
                    var slot = document.getElementById('cf-turnstile-container');
                    if (!slot) return;

                    // Clear any previous instance (post-back scenario)
                    slot.innerHTML = '';

                    // Render a fresh widget and wire token callbacks
                    window._cfWidgetId = turnstile.render(slot, {
                    sitekey: '{$TURNSTILE_SITE_KEY}',
                    theme: 'light',
                    callback: function (token) {
                        var el = document.getElementById('cf-turnstile-response');
                        if (el) el.value = token;
                        try { console.info('[Turnstile] token issued:', token ? (token.substring(0,8)+'…') : '(empty)'); } catch (e) {}
                    },
                    'expired-callback': function () {
                        var el = document.getElementById('cf-turnstile-response');
                        if (el) el.value = '';
                    },
                    'error-callback': function (err) {
                        var el = document.getElementById('cf-turnstile-response');
                        if (el) el.value = '';
                        try { console.error('[Turnstile] error', err); } catch (e) {}
                        // Retry once after a short delay to overcome transient flow hiccups
                        window._cfRenderAttempts = (window._cfRenderAttempts || 0) + 1;
                        if (window._cfRenderAttempts <= 1) {
                            try { if (window._cfWidgetId) turnstile.reset(window._cfWidgetId); } catch (e) {}
                            setTimeout(function(){ try { renderTurnstile(); } catch (e) {} }, 700);
                        }
                    }
                    });
                    window._cfRendered = true;
                }

                // Called by the script tag via ?onload=
                function cfTurnstileReady() {
                    renderTurnstile();
                }

                // Avoid double-render: rely on onload path; if needed elsewhere, call renderTurnstile() manually.

                // Gentle guard so the user cannot submit without a fresh token
                (function () {
                    var form = document.getElementById('signup');
                    if (!form) return;
                    form.addEventListener('submit', function (e) {
                    var token = document.getElementById('cf-turnstile-response')?.value || '';
                    if (!token) {
                        e.preventDefault();
                        // Try to reset and render again, then ask user to retry
                        if (window.turnstile && window._cfWidgetId) {
                        try { turnstile.reset(window._cfWidgetId); } catch (err) {}
                        } else {
                        renderTurnstile();
                        }
                        alert('Please complete the verification and submit again.');
                    }
                    });
                })();
                </script>
            
                <button type="submit" 
                        class="w-full bg-sky-600 text-white py-2 px-4 rounded hover:bg-sky-700 transition duration-200">
                    Sign Up
                </button>                
            </form>
            {/if}
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
            $("#signup").on("submit", function(e) {
            // If another listener (like the Turnstile guard) prevented submit, do nothing
            if (e.isDefaultPrevented && e.isDefaultPrevented()) return;

            // Hide the form and show the loader
            $(this).hide();
            $("#loading-overlay").css("display", "flex");
        });
    });
</script>

<script>
    (function () {
        var form = document.getElementById('signup');
        if (!form) return;

        // Keep the hidden "phonenumber" in sync on submit
        form.addEventListener('submit', function (e) {
        // If some other guard prevented submission, bail out early (so we don't hide the form)
        if (e.isDefaultPrevented && e.isDefaultPrevented()) return;

        var visible = document.getElementById('contact_number');
        var hidden  = document.getElementById('phonenumber');
        if (visible && hidden) {
            hidden.value = visible.value; // copy exactly what the user typed
        }
    });

        // Optional: after render, if any script mangled the visible value, force it back to what the server sent
        document.addEventListener('DOMContentLoaded', function () {
            var el = document.getElementById('contact_number');
            if (!el) return;
            var original = el.getAttribute('data-server-value');
            if (original && !el.value) {
                el.value = original;
            }
        });
    })();
</script>


