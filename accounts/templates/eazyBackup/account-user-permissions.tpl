<div class="min-h-screen bg-gray-700 text-gray-300">
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
		{assign var="activeTab" value="userpermissions"}
        {include file="$template/includes/profile-nav.tpl" activeTab=$activeTab}
        <div class="flex flex-col flex-1 overflow-y-auto bg-gray-700">
            <!-- Main Content Container -->
			<div class="bg-gray-800 shadow rounded-b-md p-4 mb-4">	      	


                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-300 mb-4">{lang key='userManagement.managePermissions'}</h3>
                    <p class="text-sm text-gray-300 mb-4">{$user->email}</p>
                    <p class="text-base font-semibold text-gray-300 mb-4">{lang key="userManagement.permissions"}</p>

                    <form method="post" action="{routePath('account-users-permissions-save', $user->id)}" class="space-y-4">
                        <div class="col-span-full">
                            {foreach $permissions as $permission}
                                <label class="block">
                                    <input 
                                        type="checkbox" 
                                        class="rounded accent-sky-600 text-sky-600 focus:ring-sky-500" 
                                        name="perms[{$permission.key}]" 
                                        value="1"{if $userPermissions->hasPermission($permission.key)} checked{/if}>
                                    <span class="text-sm ml-2 text-gray-300">{$permission.title}</span>
                                    <small class="text-sm text-gray-400 block">{$permission.description}</small>
                                </label>                
                            {/foreach}
                        </div>

                        <div class="flex items-center justify-end space-x-4">
                            <a href="{routePath('account-users')}" 
                            class="text-sm/6 font-semibold text-gray-300">
                                {lang key="clientareacancel"}
                            </a>
                            <button type="submit" 
                                class="rounded-md bg-sky-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600">
                                {lang key="clientareasavechanges"}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
