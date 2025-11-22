<div id="trial-signup" class="relative min-h-screen flex items-center justify-center p-6" style="background-image: radial-gradient(circle at center center, rgba(33,33,33,0),rgb(33,33,33)),repeating-linear-gradient(135deg, rgb(33,33,33) 0px, rgb(33,33,33) 1px,transparent 1px, transparent 4px),repeating-linear-gradient(45deg, rgb(56,56,56) 0px, rgb(56,56,56) 5px,transparent 5px, transparent 6px),linear-gradient(90deg, rgb(33,33,33),rgb(33,33,33));">
    <!-- Overlay -->
    <div class="absolute inset-0 bg-gray-900 bg-opacity-70"></div>
    
    <!-- Main Content (z-index higher than overlay) -->
    <div class="relative z-10 shadow-lg rounded-lg overflow-hidden max-w-4xl w-full flex flex-col md:flex-row">
        
        <!-- Text Column -->
        <div class="md:w-1/2 p-8 bg-gray-800">
            <h1 class="text-gray-100 text-3xl font-bold mb-6">Try OBC for Free</h1>
            <p class="text-gray-100 text-lg mb-6">
            Engineered to meet strict compliance and security standards—including government-certified protection. With clear, transparent pricing and the assurance of Canadian data sovereignty, your secure storage strategy starts here.
            </p>
            <ul class="list-disc list-inside space-y-2">
                <li class="text-gray-100 text-md">Automatic Backups for Workstations and Servers</li>
                <li class="text-gray-100 text-md">Cloud backup for Microsoft 365 users</li>
                <li class="text-gray-100 text-md">Canadian data sovereignty</li>
                <li class="text-gray-100 text-md">Unlimited storage during your trial</li>
                <li class="text-gray-100 text-md">No credit card required</li>
            </ul>
        </div>
        
        <!-- Form Column -->
        <div class="bg-white md:w-1/2 p-8">
            
            {if !empty($errors["error"])}
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
                    {$errors["error"]}
                </div>
            {/if}
            
            <h1 class="text-gray-700 text-2xl font-semibold mb-4">Start your 14-day free trial</h1>
            
            <form id="signup" method="post" action="{$modulelink}&a=obc-signup" class="space-y-6">
                
                <!-- Username and Email (Two Columns) -->
                <div class="flex flex-row space-x-4">
                    <!-- Username -->
                    <div class="flex flex-col w-1/2">
                        <label for="username" class="mb-2 font-medium text-gray-700">Username</label>
                        <input type="text" id="username" name="username" 
                               class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-100 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 {if !empty($errors["username"])}border-red-500{/if}" 
                               {if !empty($POST["username"])}value="{$POST["username"]}"{/if}>
                        {if !empty($errors["username"])}
                            <span class="text-red-500 text-sm mt-1">{$errors["username"]}</span>
                        {/if}
                    </div>
                    
                    <!-- Email Address -->
                    <div class="flex flex-col w-1/2">
                        <label for="email" class="mb-2 font-medium text-gray-700">Email address</label>
                        <input type="email" id="email" name="email" 
                               class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-100 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 {if !empty($errors["email"])}border-red-500{/if}" 
                               {if !empty($POST["email"])}value="{$POST["email"]}"{/if}>
                        {if !empty($errors["email"])}
                            <span class="text-red-500 text-sm mt-1">{$errors["email"]}</span>
                        {/if}
                    </div>
                </div>
                
                <!-- Phone Number (Single Row) -->
                <div class="flex flex-col">
                    <label for="phonenumber" class="mb-2 font-medium text-gray-700">Phone number</label>
                    <input type="text" id="phonenumber" name="phonenumber" 
                           class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-100 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 {if !empty($errors["phonenumber"])}border-red-500{/if}" 
                           {if !empty($POST["phonenumber"])}value="{$POST["phonenumber"]}"{/if}>
                    {if !empty($errors["phonenumber"])}
                        <span class="text-red-500 text-sm mt-1">{$errors["phonenumber"]}</span>
                    {/if}
                </div>
                
                <!-- Password and Confirm Password (Two Columns) -->
                <div class="flex flex-row space-x-4">
                    <!-- Password -->
                    <div class="flex flex-col w-1/2">
                        <label for="password" class="mb-2 font-medium text-gray-700">Password</label>
                        <input type="password" id="password" name="password" 
                               class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-100 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 {if !empty($errors["password"])}border-red-500{/if}">
                        {if !empty($errors["password"])}
                            <span class="text-red-500 text-sm mt-1">{$errors["password"]}</span>
                        {/if}
                    </div>
                    <!-- Confirm Password -->
                    <div class="flex flex-col w-1/2">
                        <label for="confirmpassword" class="mb-2 font-medium text-gray-700">Confirm password</label>
                        <input type="password" id="confirmpassword" name="confirmpassword" 
                               class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-100 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 {if !empty($errors["confirmpassword"])}border-red-500{/if}">
                        {if !empty($errors["confirmpassword"])}
                            <span class="text-red-500 text-sm mt-1">{$errors["confirmpassword"]}</span>
                        {/if}
                    </div>
                </div>
                
                <!-- Terms Checkbox (Single Row) -->
                <div class="flex items-center">
                    <input type="checkbox" id="agree" name="agree" required 
                           class="h-5 w-5 text-indigo-600 focus:ring-indigo-700 border-gray-300 rounded">
                    <label for="agree" class="ml-2 text-sm">
                        By signing up, you agree to the 
                        <a href="https://eazybackup.com/terms/" class="text-indigo-600 hover:underline">Terms of Service</a> 
                        and 
                        <a href="https://eazybackup.com/privacy/" class="text-indigo-600 hover:underline">Privacy Policy</a>.
                    </label>
                </div>
                
                <!-- reCAPTCHA (Centered) -->
                <div class="flex justify-center">
                    <div class="g-recaptcha" data-sitekey="6LcrQtQqAAAAAIAjmJIXSU79tmibxSvV2IYpVa6Y"></div>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" 
                        class="w-full bg-indigo-600 text-white py-2 px-4 rounded hover:bg-indigo-700 transition duration-200">
                    Sign Up
                </button>
                
                <!-- reCAPTCHA Script -->
                <script src="https://www.google.com/recaptcha/api.js" async defer></script>
                
            </form>
        </div>
    </div>
</div>
