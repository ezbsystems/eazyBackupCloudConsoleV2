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
                        class="block w-full pl-10 pr-3 py-2 border border-gray-600 bg-gray-700 text-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-sky-600"
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
                        class="block w-full pl-10 pr-3 py-2 border border-gray-600 bg-gray-700 text-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-sky-600"
                    />
                </div>
                <!-- Validation Message -->
                <div id="inputNewPassword2Msg" class="mt-1 text-sm text-red-500"></div>
            </div>

            <!-- Password Strength Indicator -->
            <div>
                <label class="block text-sm text-gray-300 mb-2">{lang key='passwordtips'}</label>
                
            </div>

            <!-- Submit and Reset Buttons -->
            <div>
                <div class="flex justify-center space-x-4">
                    <button 
                        type="submit" 
                        class="items-center w-full px-4 py-2 border border-transparent shadow-sm text-md font-semisbold rounded-md text-white bg-sky-600 hover:bg-sky-700 border-transparent focus:border-transparent focus:ring-0"
                    >
                        {lang key='clientareasavechanges'}
                    </button>                    
                </div>
            </div>
        </form>

