<div class="min-h-screen bg-gray-700 text-gray-100">
    <div class="container mx-auto px-4 pb-8">
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center px-2">
            <!-- Navigation Horizontal -->
            <div class="flex items-center">        
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>                   
                <h2 class="text-2xl font-semibold text-white">My Account</h2>
            </div>
        </div>
		{assign var="activeTab" value="password"}
        {include file="$template/includes/profile-nav.tpl" activeTab=$activeTab}
        <div class="flex flex-col flex-1 overflow-y-auto bg-gray-700">
        <!-- Main Content Container -->
        <div class="bg-slate-800 shadow rounded-b-xl p-4 mb-4">	   

    {include file="$template/includes/flashmessage-darkmode.tpl"}

    <form class="space-y-6 using-password-strength" method="post" action="{routePath('user-password')}" role="form">
        <input type="hidden" name="submit" value="true" />
        <div class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-6">
            <!-- Existing Password -->
            <div class="sm:col-span-3">
                <label for="inputExistingPassword" class="block text-sm/6 font-medium text-gray-100">
                    {lang key='existingpassword'}
                </label>
                <div class="sm:col-span-2">
                    <input 
                        type="password" 
                        name="existingpw" 
                        id="inputExistingPassword" 
                        autocomplete="off" 
                        class="block w-full px-3 py-2 border border-gray-600 text-gray-100 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" 
                    />
                </div>
            </div>
        </div>
            
        <div class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-6">
            <!-- New Password -->
            <div id="newPassword1" class="sm:col-span-3">
                <label for="inputNewPassword1" class="block text-sm/6 font-medium text-gray-100">
                    {lang key='newpassword'}
                </label>
                <div class="sm:col-span-2 space-y-2">
                    <input 
                        type="password" 
                        name="newpw" 
                        id="inputNewPassword1" 
                        autocomplete="off" 
                        class="block w-full px-3 py-2 border border-gray-600 text-gray-100 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" 
                    />               
                </div>            
            </div>

            <!-- Confirm New Password -->
            <div id="newPassword2" class="sm:col-span-3">
                <label for="inputNewPassword2" class="block text-sm/6 font-medium text-gray-100">
                    {lang key='confirmnewpassword'}
                </label>
                <div class="sm:col-span-2">
                    <input 
                        type="password" 
                        name="confirmpw" 
                        id="inputNewPassword2" 
                        autocomplete="off" 
                        class="block w-full px-3 py-2 border border-gray-600 text-gray-100 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" 
                    />
                    <div id="inputNewPassword2Msg" class="mt-1 text-sm text-gray-600"></div>
                </div>
            </div>
        </div>
            <!-- Submit and Reset Buttons -->
            <div class="flex items-center justify-end space-x-4">
                <button 
                    type="reset" 
                    class="text-sm/6 font-semibold text-gray-100"
                >
                    {lang key='cancel'}
                </button>
                <button 
                    type="submit" 
                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700"
                >
                    {lang key='clientareasavechanges'}
                </button>
            </div>        
    </form>
    
</div>

<!-- JavaScript for Dynamic iframe Height -->
<script>
    function sendHeight() {
        var body = document.body,
            html = document.documentElement;

        var height = Math.max(
            body.scrollHeight, body.offsetHeight, 
            html.clientHeight, html.scrollHeight, html.offsetHeight
        );

        // Send the height to the parent
        window.parent.postMessage(height, '*'); // Replace '*' with your domain for security
    }

    document.addEventListener('DOMContentLoaded', function() {
        sendHeight();
    });

    // Example using MutationObserver to detect changes
    var observer = new MutationObserver(function(mutations) {
        sendHeight();
    });

    var targetNode = document.querySelector('.password-form-container');
    if (targetNode) {
        observer.observe(targetNode, { attributes: true, childList: true, subtree: true });
    }
</script>
