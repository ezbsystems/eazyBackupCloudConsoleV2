        <!-- Header Section -->
        <div class="mb-8">
            <h6 class="text-2xl font-semibold text-white mb-4">{lang key='pwreset'}</h6>
            <p class="text-gray-300 text-sm">{lang key='pwresetenternewpw'}</p>
        </div>

        <!-- Error Message -->
        {if $errorMessage}
            {include file="$template/includes/alert-darkmode.tpl" type="error" msg=$errorMessage textcenter=true}
        {/if}

        <!-- Success Message -->
        {if $successMessage}
            {include file="$template/includes/alert-darkmode.tpl" type="success" msg=$successMessage textcenter=true}
        {/if}

        <!-- Form Section -->
        <form method="POST" action="{routePath('password-reset-change-perform')}" class="space-y-6">
            <input type="hidden" name="answer" id="answer" value="{$securityAnswer}" />

            <!-- New Password Input Field -->
            <div>
                <label for="inputNewPassword1" class="block text-sm font-medium text-white mb-2">{lang key='newpassword'}</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-300">                        
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                    </span>
                    <input 
                        type="password" 
                        name="newpw" 
                        id="inputNewPassword1" 
                        placeholder="{lang key='newpassword'}" 
                        required 
                        class="block w-full pl-10 pr-3 py-2.5 rounded-lg border border-slate-700 bg-slate-900/60 text-sm text-white placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                    />
                </div>
            </div>

            <!-- Confirm New Password Input Field -->
            <div>
                <label for="inputNewPassword2" class="block text-sm font-medium text-white mb-2">{lang key='confirmnewpassword'}</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-300">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                    </span>
                    <input 
                        type="password" 
                        name="confirmpw" 
                        id="inputNewPassword2" 
                        placeholder="{lang key='confirmnewpassword'}" 
                        required 
                        class="block w-full pl-10 pr-3 py-2.5 rounded-lg border border-slate-700 bg-slate-900/60 text-sm text-white placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                    />
                </div>
                <!-- Validation Message -->
                <div id="inputNewPassword2Msg" class="mt-1 text-sm text-red-500"></div>
            </div>

            <!-- Password Strength Indicator -->
            <div>
                <label class="block text-sm text-gray-300 mb-2">{lang key='passwordtips'}</label>
                
            </div>

            <!-- Submit Button -->
            <div>
                <div class="flex justify-center">
                    <button 
                        type="submit" 
                        class="inline-flex w-full items-center justify-center rounded-full px-4 py-2 text-sm font-semibold text-white shadow-sm bg-[#FE5000] hover:bg-[#ff6a26] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-slate-950 focus:ring-[#FE5000]"
                    >
                        {lang key='clientareasavechanges'}
                    </button>                    
                </div>
            </div>
        </form>

