<!-- accounts\templates\eazyBackup\login.tpl -->
<div class="providerLinkingFeedback"></div>

<form method="post" action="{routePath('login-validate')}" class="flex justify-center items-center min-h-screen bg-slate-950 text-gray-300" role="form">
    <!-- Global nebula background -->
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>

    <div class="relative max-w-lg w-full">
        <!-- glow behind the login card -->
        <div class="pointer-events-none absolute -top-24 -right-16 w-[26rem] h-[26rem] bg-[radial-gradient(circle_at_center,_#1f2937a6,_transparent_65%)] -z-10"></div>

        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6 w-full">
        
            <div class="mb-6">
                <h6 class="text-2xl font-semibold text-white">{lang key='loginheading'}</h6>
                {* <p class="text-gray-600">{lang key='userLogin.signInToContinue'}</p> *}
            </div>
            {include file="$template/includes/flashmessage-darkmode.tpl"}

            <!-- Email Field -->
            <div class="">
                <label for="inputEmail" class="block text-sm font-medium text-white mb-2">{lang key='clientareaemail'}</label>
                <div class="relative mb-4">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-300">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>                  
                    </span>
                    <input 
                        type="email" 
                        name="username" 
                        id="inputEmail" 
                        placeholder="name@company.com" 
                        class="block w-full pl-10 pr-3 py-2.5 rounded-lg border border-slate-700 bg-slate-900/60 text-sm text-white placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                    >
                </div>
            </div>

            <!-- Password Field -->
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <label for="inputPassword" class="block text-sm font-medium text-white mb-2">{lang key='clientareapassword'}</label>
                    
                </div>
                <div class="relative mb-4">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-300">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                        </svg>                  
                    </span>
                    <input 
                        type="password" 
                        name="password" 
                        id="inputPassword" 
                        placeholder="{lang key='clientareapassword'}" 
                        class="block w-full pl-10 pr-3 py-2.5 rounded-lg border border-slate-700 bg-slate-900/60 text-sm text-white placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                    >
                    <button type="button" id="togglePassword" tabindex="-1" 
                        class="absolute inset-y-0 right-0 px-3 text-gray-300 hover:text-gray-200 focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                
                </div>
            </div>

            <!-- Captcha -->
            {if $captcha->isEnabled()}
                {include file="$template/includes/captcha.tpl"}
            {/if}

            <!-- Actions -->
            <div class="flex items-center justify-between mb-6">
                <label class="flex items-center space-x-2 text-sm text-gray-300 rounded-md">
                    <input type="checkbox" name="rememberme" class="h-4 w-4 accent-indigo-600 text-gray-300 rounded-md accent-sky-600 border-gray-300 rounded focus:ring-sky-500">
                    <span>{lang key='loginrememberme'}</span>
                    
                </label>
                <a href="{routePath('password-reset-begin')}" class="text-sm font-semibold text-sky-500 hover:underline">{lang key='forgotpw'}</a>
            </div>
            <div class="flex items-center justify-between mb-4">
                <button
                    id="login"
                    type="submit"
                    class="group relative inline-flex w-full items-center justify-center overflow-hidden rounded-full px-[2px] py-[2px] focus:outline-none"
                >
                    <!-- Outer glowing ring -->
                    <span
                        class="absolute inset-0 rounded-full bg-[conic-gradient(from_180deg_at_50%_50%,_rgba(254,80,0,0.15),_rgba(254,80,0,0.6),_rgba(254,80,0,0.15))] opacity-80 blur-[2px] transition duration-500 group-hover:blur-[6px] group-hover:opacity-100"
                        aria-hidden="true"
                    ></span>

                    <!-- Inner button surface -->
                    <span
                        class="relative inline-flex w-full items-center justify-center rounded-full bg-gradient-to-r from-[#FE5000] via-[#ff8a3a] to-[#FE5000] px-4 py-2 text-sm font-semibold text-white shadow-[0_0_25px_rgba(254,80,0,0.55)] transition duration-300 group-hover:shadow-[0_0_40px_rgba(254,80,0,0.85)] group-hover:translate-y-[1px]"
                    >
                        <!-- Lock icon -->
                        <span
                            class="mr-2 inline-flex h-6 w-6 items-center justify-center rounded-full bg-black/20 text-white/90 transition-transform duration-300 group-hover:translate-x-[1px]"
                            aria-hidden="true"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                class="h-3.5 w-3.5"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M16.5 10.5V8.25A4.25 4.25 0 0 0 8 8.25v2.25m8.5 0h-9a1.75 1.75 0 0 0-1.75 1.75v4.5A1.75 1.75 0 0 0 7.5 20.5h9a1.75 1.75 0 0 0 1.75-1.75v-4.5a1.75 1.75 0 0 0-1.75-1.75Z"
                                />
                            </svg>
                        </span>

                        <span class="relative text-md">
                            {lang key='loginbutton'}
                        </span>
                    </span>
                </button>
            </div>

        
        
        <!-- Footer -->        
        </div>
    </div>
</form>

{include file="$template/includes/linkedaccounts.tpl" linkContext="login" customFeedback=true}

<script>
  // Engage: Wait until the DOM is fully loaded.
  document.addEventListener('DOMContentLoaded', () => {
    // Locate our password input and toggle button.
    const passwordInput = document.getElementById('inputPassword');
    const togglePassword = document.getElementById('togglePassword');

    // Listen for a click event on the toggle button.
    togglePassword.addEventListener('click', () => {
      // Toggle the input type.
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
      } else {
        passwordInput.type = 'password';
      }
      
      // (Optional) Log the action to the ship's console for debugging.
      console.log('Password field toggled: ' + passwordInput.type);
    });
  });
</script>
