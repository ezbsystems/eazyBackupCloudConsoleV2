

        <!-- Header Section -->
        <div class="mb-8">
            <h6 class="text-2xl font-semibold text-white mb-4">{lang key='pwreset'}</h6>
            <p class="text-gray-300 text-sm">{lang key='pwresetemailneeded'}</p>
        </div>

        <!-- Error Message -->
        {if $errorMessage}
            {include file="$template/includes/alert-darkmode.tpl" type="error" msg=$errorMessage textcenter=true}
        {/if}

        <!-- Form Section -->
        <form method="post" action="{routePath('password-reset-validate-email')}" role="form" class="space-y-6">
            <input type="hidden" name="action" value="reset" />

            <!-- Email Input Field -->
            <div>
                <label for="inputEmail" class="block text-sm font-medium text-white mb-2">{lang key='loginemail'}</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-300">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                  
                    </span>
                    <input 
                        type="email" 
                        name="email" 
                        id="inputEmail" 
                        placeholder="name@example.com" 
                        autofocus 
                        class="block w-full pl-10 pr-3 py-2.5 rounded-lg border border-slate-700 bg-slate-900/60 text-sm text-white placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                    />
                </div>
            </div>

            <!-- Captcha Section -->
            {if $captcha->isEnabled()}
                <div class="text-center">
                    {include file="$template/includes/captcha.tpl"}
                </div>
            {/if}

            <!-- Submit Button -->
            <div class="text-center">
                <button 
                    type="submit" 
                    class="inline-flex w-full items-center justify-center rounded-full px-4 py-2 text-sm font-semibold text-white shadow-sm bg-[#FE5000] hover:bg-[#ff6a26] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-slate-950 focus:ring-[#FE5000]"
                >
                    {lang key='pwresetsubmit'}
                </button>
            </div>
        </form>

