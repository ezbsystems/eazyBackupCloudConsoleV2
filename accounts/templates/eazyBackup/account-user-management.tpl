<div x-data="{ 
        removeUserModal: false, 
        cancelInviteModal: false, 
        currentUserId: null, 
        currentInviteId: null 
    }" 
     class="min-h-screen bg-gray-700 text-gray-100">

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

        {assign var="activeTab" value="usermanage"}
        {include file="$template/includes/profile-nav.tpl" activeTab=$activeTab}

        <div class="flex flex-col flex-1 overflow-y-auto bg-gray-700">
            <!-- Main Content Container -->
            <div class="bg-slate-800 shadow rounded-b-xl p-4 mb-4">
                
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">{lang key="navUserManagement"}</h3>

                    <p class="text-sm text-gray-100 mb-1">{lang key="userManagement.usersFound" count=$users->count()}</p>

                    <table class="w-full border-collapse text-sm text-gray-100">
                        <thead>
                            <tr class="bg-gray-800">
                                <th class="border-b border-gray-600 px-4 py-2 text-left">{lang key="userManagement.emailAddress"} / {lang key="userManagement.lastLogin"}</th>
                                <th class="border-b border-gray-600 px-4 py-2 text-left" width="300">{lang key="userManagement.actions"}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $users as $user}
                                <tr class="hover:bg-gray-700">
                                    <td class="px-4 py-2">
                                        <span>{$user->email}</span>
                                        {if $user->pivot->owner}
                                            <span class="inline-block bg-sky-300 text-sky-800 text-xs font-medium px-2 py-1 rounded ml-2">{lang key="clientOwner"}</span>
                                        {/if}
                                        {if $user->hasTwoFactorAuthEnabled()}
                                            <i class="fas fa-shield text-green-500 ml-2" title="{lang key='twoFactor.enabled'}"></i>
                                        {else}
                                            <i class="fas fa-shield text-gray-400 ml-2" title="{lang key='twoFactor.disabled'}"></i>
                                        {/if}
                                        <br>
                                        <small>
                                            {lang key="userManagement.lastLogin"}:
                                            {if $user->pivot->hasLastLogin()}
                                                {$user->pivot->getLastLogin()->diffForHumans()}
                                            {else}
                                                {lang key='never'}
                                            {/if}
                                        </small>
                                    </td>
                                    <td class="px-4 py-2">
                                        <a href="{routePath('account-users-permissions', $user->id)}" 
                                           class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 {if $user->pivot->owner} opacity-50 cursor-not-allowed{/if}">
                                            {lang key="userManagement.managePermissions"}
                                        </a>
                                        <button type="button" 
                                                class="btn-remove-user inline-flex items-center px-3 py-1.5 border border-red-700 shadow-sm text-sm font-medium rounded-md text-white bg-red-700 hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 {if $user->pivot->owner} opacity-50 cursor-not-allowed{/if}" 
                                                data-id="{$user->id}"
                                                @click="removeUserModal = true; currentUserId = $event.currentTarget.dataset.id">
                                            {lang key="userManagement.removeAccess"}
                                        </button>
                                    </td>
                                </tr>
                            {/foreach}

                            {if $invites->count() > 0}
                                <tr class="bg-gray-800 border-b border-gray-600">
                                    <td colspan="3" class="px-4 py-2">
                                        <strong>{lang key="userManagement.pendingInvites"}</strong>
                                    </td>
                                </tr>
                                {foreach $invites as $invite}
                                    <tr class="hover:bg-gray-700">
                                        <td class="px-4 py-2">
                                            {$invite->email}
                                            <br>
                                            <small>
                                                {lang key="userManagement.inviteSent"}:
                                                {$invite->created_at->diffForHumans()}
                                            </small>
                                        </td>
                                        <td class="px-4 py-2">
                                            <form method="post" action="{routePath('account-users-invite-resend')}">
                                                <input type="hidden" name="inviteid" value="{$invite->id}">
                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 ">
                                                    {lang key="userManagement.resendInvite"}
                                                </button>
                                                <button type="button" 
                                                        class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 " 
                                                        data-id="{$invite->id}"
                                                        @click="cancelInviteModal = true; currentInviteId = $event.currentTarget.dataset.id">
                                                    {lang key="userManagement.cancelInvite"}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                {/foreach}
                            {/if}
                        </tbody>
                    </table>

                    <p class="text-sm text-gray-400 mt-4">* {lang key="userManagement.accountOwnerPermissionsInfo"}</p>
                </div>

                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">{lang key="userManagement.inviteNewUser"}</h3>

                    <p class="text-sm text-gray-100 mb-4">{lang key="userManagement.inviteNewUserDescription"}</p>

                    <form method="post" action="{routePath('account-users-invite')}" class="space-y-4">
                        <div>
                            <input 
                                type="email" 
                                name="inviteemail" 
                                placeholder="name@example.com" 
                                class="block w-full px-3 py-2 border border-gray-600 text-gray-100 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" 
                                value="{$formdata.inviteemail}" />
                        </div>
                        <div class="col-span-full">
                            <label class="block text-sm/6 font-medium text-gray-100">
                                <input 
                                    type="radio" 
                                    class="accent-sky-600 rounded text-sky-600 focus:ring-sky-500" 
                                    name="permissions" 
                                    value="all" 
                                    checked="checked" />
                                <span class="ml-2">{lang key="userManagement.allPermissions"}</span>
                            </label>
                            <label class="block text-sm/6 font-medium text-gray-100">
                                <input 
                                    type="radio" 
                                    class="accent-sky-600 rounded text-sky-600 focus:ring-sky-500" 
                                    name="permissions" 
                                    value="choose" />
                                <span class="ml-2">{lang key="userManagement.choosePermissions"}</span>
                            </label>
                        </div>
                        <div class="hidden" id="invitePermissions">
                            {foreach $permissions as $permission}
                                <label class="block">
                                    <input 
                                        type="checkbox" 
                                        class="rounded accent-sky-600 text-sky-600 focus:ring-sky-500" 
                                        name="perms[{$permission.key}]" 
                                        value="1" />
                                    <span class="text-sm ml-2 text-gray-100">{$permission.title}</span>
                                    <small class="text-sm text-gray-500 block text-gray-400">{$permission.description}</small>
                                </label>
                            {/foreach}
                        </div>
                        <button type="submit" 
                                class="rounded-md bg-sky-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600">
                            {lang key="userManagement.sendInvite"}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Remove User Modal -->
        <div id="modalRemoveUser" x-show="removeUserModal" x-cloak class="fixed inset-0 bg-black/70 flex items-center justify-center z-50">
            <form method="post" action="{routePath('account-users-remove')}" class="bg-gray-800 rounded-lg shadow-lg w-full max-w-lg p-6">
                <input type="hidden" name="userid" :value="currentUserId" id="inputRemoveUserId">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="text-lg font-semibold text-white">
                        {lang key="userManagement.removeAccess"}
                    </h4>
                    <button type="button" class="text-gray-100 hover:text-white" @click="removeUserModal = false">
                        &times;
                    </button>
                </div>
                <div class="mb-4 text-gray-200">
                    <p>{lang key="userManagement.removeAccessSure"}</p>
                    <p>{lang key="userManagement.removeAccessInfo"}</p>
                </div>
                <div class="flex justify-end">
                    <button type="button" class="mr-2 px-4 py-2 bg-gray-600 text-gray-200 rounded" @click="removeUserModal = false">
                        {lang key="cancel"}
                    </button>
                    <button type="submit" class="px-4 py-2 bg-sky-600 text-white rounded" id="btnRemoveUserConfirm">
                        {lang key="confirm"}
                    </button>
                </div>
            </form>
        </div>

        <!-- Cancel Invite Modal -->
        <div id="modalCancelInvite" x-show="cancelInviteModal" x-cloak class="fixed inset-0 bg-black/70 flex items-center justify-center z-50">
            <form method="post" action="{routePath('account-users-invite-cancel')}" class="bg-gray-800 rounded-lg shadow-lg w-full max-w-lg p-6">
                <input type="hidden" name="inviteid" :value="currentInviteId" id="inputCancelInviteId">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="text-lg font-semibold text-white">
                        {lang key="userManagement.cancelInvite"}
                    </h4>
                    <button type="button" class="text-gray-100 hover:text-white" @click="cancelInviteModal = false">
                        &times;
                    </button>
                </div>
                <div class="mb-4 text-gray-200">
                    <p>{lang key="userManagement.cancelInviteSure"}</p>
                    <p>{lang key="userManagement.cancelInviteInfo"}</p>
                </div>
                <div class="flex justify-end">
                    <button type="button" class="mr-2 px-4 py-2 bg-gray-600 text-gray-200 rounded" @click="cancelInviteModal = false">
                        {lang key="cancel"}
                    </button>
                    <button type="submit" class="px-4 py-2 bg-sky-600 text-white rounded" id="btnCancelInviteConfirm">
                        {lang key="confirm"}
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
    jQuery(document).ready(function() {
        jQuery('input:radio[name=permissions]').change(function () {
            if (this.value === 'choose') {
                jQuery('#invitePermissions').slideDown();
            } else {
                jQuery('#invitePermissions').slideUp();
            }
        });
        jQuery('.btn-manage-permissions').click(function(e) {
            if (jQuery(this).attr('disabled')) {
                e.preventDefault();
            }
        });
        jQuery('.btn-remove-user').click(function(e) {
            e.preventDefault();
            if (jQuery(this).attr('disabled')) {
                return;
            }
            jQuery('#inputRemoveUserId').val(jQuery(this).data('id'));
            jQuery('#modalRemoveUser').modal('show');
        });
        jQuery('.btn-cancel-invite').click(function(e) {
            e.preventDefault();
            jQuery('#inputCancelInviteId').val(jQuery(this).data('id'));
            jQuery('#modalCancelInvite').modal('show');
        });
    });
</script>
