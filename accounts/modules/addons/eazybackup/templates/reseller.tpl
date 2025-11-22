<div id="reseller-signup" class="relative min-h-screen flex items-center justify-center p-6" style="background-image: linear-gradient(200deg, rgba(171, 171, 171,0.05) 0%, rgba(171, 171, 171,0.05) 23%,rgba(90, 90, 90,0.05) 23%, rgba(90, 90, 90,0.05) 48%,rgba(65, 65, 65,0.05) 48%, rgba(65, 65, 65,0.05) 61%,rgba(232, 232, 232,0.05) 61%, rgba(232, 232, 232,0.05) 100%),linear-gradient(126deg, rgba(194, 194, 194,0.05) 0%, rgba(194, 194, 194,0.05) 11%,rgba(127, 127, 127,0.05) 11%, rgba(127, 127, 127,0.05) 33%,rgba(117, 117, 117,0.05) 33%, rgba(117, 117, 117,0.05) 99%,rgba(248, 248, 248,0.05) 99%, rgba(248, 248, 248,0.05) 100%),linear-gradient(144deg, rgba(64, 64, 64,0.05) 0%, rgba(64, 64, 64,0.05) 33%,rgba(211, 211, 211,0.05) 33%, rgba(211, 211, 211,0.05) 50%,rgba(53, 53, 53,0.05) 50%, rgba(53, 53, 53,0.05) 75%,rgba(144, 144, 144,0.05) 75%, rgba(144, 144, 144,0.05) 100%),linear-gradient(329deg, hsl(148,0%,0%),hsl(148,0%,0%));">
<div class="absolute inset-0 bg-gray-900 bg-opacity-70"></div>
    <div class="relative z-10 shadow-lg rounded-lg overflow-hidden max-w-4xl w-full flex flex-col md:flex-row">
        
        <!-- Benefits Column -->
        <div class="md:w-1/2 p-8 bg-gray-800 text-gray-100">
            <h1 class="text-3xl font-bold mb-6">Try eazyBackup for Free</h1>
            <p class="text-gray-200 text-md mb-6">
                See firsthand how our secure platform enables you to remotely protect, monitor, and manage endpoints efficiently, helping you lower costs and enhance your service offerings.
            </p>
            <div class="space-y-6">
                <!-- Partner Benefits -->
                <div>
                    <h3 class="text-xl font-semibold mb-2 text-gray-100">Partner Benefits</h3>
                    <p class="text-sm text-gray-200">
                        Our program is designed for MSPs and IT service providers. No contracts or minimum commitments for storage or number of accounts.
                    </p>
                </div>
                <hr class="border-gray-400">
                <!-- White Label Branding -->
                <div>
                    <h3 class="text-xl font-semibold mb-2 text-gray-100">Canadian Owned & Certified</h3>
                    <p class="text-sm text-gray-200">
                        Proudly Canadian, with Government of Canada CGP certification ensuring rigid security and compliance and the assurance of Canadian data sovereignty.
                    </p>
                </div>
                <hr class="border-gray-400">
                <!-- Central Monitoring & Management -->
                <div>
                    <h3 class="text-xl font-semibold mb-2 text-gray-100">Central Monitoring & Management</h3>
                    <p class="text-sm text-gray-300">
                        Full remote management and central monitoring for all your customer accounts and backups, easily manage backups and monitor their status remotely.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Signup Form Column -->
        <div class="bg-white md:w-1/2 p-8">
            <h1 class="text-gray-700 text-2xl font-semibold mb-4">Become an eazyBackup Partner</h1>
            
            {if !empty($errors["error"])}
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
                    {$errors["error"]}
                </div>
            {/if}
            
            <form id="reseller" method="post" action="{$modulelink}&a=reseller" class="space-y-6">
                
                <!-- Name Fields in One Row -->
                <div class="flex flex-row space-x-4">
                    <!-- First Name -->
                    <div class="flex flex-col w-1/2">
                        <label for="firstname" class="mb-2 font-medium text-gray-700">First Name</label>
                        <input type="text" id="firstname" name="firstname" 
                               class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-100 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 {if !empty($errors["firstname"])}border-red-500{/if}" 
                               {if !empty($POST["firstname"])}value="{$POST["firstname"]}"{/if}>
                        {if !empty($errors["firstname"])}
                            <span class="text-red-500 text-sm mt-1">{$errors["firstname"]}</span>
                        {/if}
                    </div>
                    <!-- Last Name -->
                    <div class="flex flex-col w-1/2">
                        <label for="lastname" class="mb-2 font-medium text-gray-700">Last Name</label>
                        <input type="text" id="lastname" name="lastname" 
                               class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-100 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 {if !empty($errors["lastname"])}border-red-500{/if}" 
                               {if !empty($POST["lastname"])}value="{$POST["lastname"]}"{/if}>
                        {if !empty($errors["lastname"])}
                            <span class="text-red-500 text-sm mt-1">{$errors["lastname"]}</span>
                        {/if}
                    </div>
                </div>
                
                <!-- Company Name -->
                <div class="flex flex-col">
                    <label for="companyname" class="mb-2 font-medium text-gray-700">Company</label>
                    <input type="text" id="companyname" name="companyname" 
                           class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-100 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 {if !empty($errors["companyname"])}border-red-500{/if}" 
                           {if !empty($POST["companyname"])}value="{$POST["companyname"]}"{/if}>
                    {if !empty($errors["companyname"])}
                        <span class="text-red-500 text-sm mt-1">{$errors["companyname"]}</span>
                    {/if}
                </div>
                
                <!-- Email Address -->
                <div class="flex flex-col">
                    <label for="email" class="mb-2 font-medium text-gray-700">Email Address</label>
                    <input type="email" id="email" name="email" 
                           class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-100 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 {if !empty($errors["email"])}border-red-500{/if}" 
                           {if !empty($POST["email"])}value="{$POST["email"]}"{/if}>
                    {if !empty($errors["email"])}
                        <span class="text-red-500 text-sm mt-1">{$errors["email"]}</span>
                    {/if}
                </div>
                
                <!-- Phone Number -->
                <div class="flex flex-col">
                    <label for="phonenumber" class="mb-2 font-medium text-gray-700">Phone number</label>
                    <input 
                        type="tel" 
                        id="phonenumber" 
                        name="phonenumber"         
                        placeholder="123-456-7890"
                        required
                        class="block w-full rounded-md bg-white px-3 py-2.5 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm {if !empty($errors["phonenumber"])}border-red-500{/if}" 
                        {if !empty($POST["phonenumber"])}value="{$POST.phonenumber|escape}"{/if}
                    >
                    {if !empty($errors["phonenumber"])}
                        <span class="text-red-500 text-sm mt-1">{$errors.phonenumber}</span>
                    {/if}
                </div>
                
                <!-- Password Fields in One Row -->
                <div class="flex flex-row space-x-4">
                    <!-- Password -->
                    <div class="flex flex-col w-1/2">
                        <label for="password" class="mb-2 font-medium text-gray-700">Password</label>
                        <input type="password" id="password" name="password" 
                               class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-100 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 {if !empty($errors["password"])}border-red-500{/if}">
                        {if !empty($errors["password"])}
                            <span class="text-red-500 text-sm mt-1">{$errors["password"]}</span>
                        {/if}
                    </div>
                    <!-- Confirm Password -->
                    <div class="flex flex-col w-1/2">
                        <label for="confirmpassword" class="mb-2 font-medium text-gray-700">Confirm Password</label>
                        <input type="password" id="confirmpassword" name="confirmpassword" 
                               class="block w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-100 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 {if !empty($errors["confirmpassword"])}border-red-500{/if}">
                        {if !empty($errors["confirmpassword"])}
                            <span class="text-red-500 text-sm mt-1">{$errors["confirmpassword"]}</span>
                        {/if}
                    </div>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" 
                        class="w-full bg-indigo-600 text-white py-2 px-4 rounded hover:bg-indigo-700 transition duration-200 font-semibold">
                    Sign Up
                </button>
                
                <!-- Terms of Service -->
                <small class="terms text-center text-gray-600">
                    By signing up you agree to the 
                    <a href="https://eazybackup.com/terms/" target="_top" class="text-indigo-600 hover:underline">Terms of Service</a> 
                    and 
                    <a href="https://eazybackup.com/privacy/" target="_top" class="text-indigo-600 hover:underline">Privacy Policy</a>.
                </small>
            </form>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cleave.js/1.6.0/cleave.min.js"></script>
<script>
    new Cleave('#phonenumber', {
        delimiters: ['-', '-'],
        blocks: [3, 3, 4],
        numericOnly: true
    });
</script>
