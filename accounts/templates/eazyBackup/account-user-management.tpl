<div x-data="{ removeUserModal: false, cancelInviteModal: false, currentUserId: null, currentInviteId: null, invitePermissions: false }">
    {assign var="activeTab" value="usermanage"}

    {capture name=ebAccountNav}
        {include file="$template/includes/profile-nav.tpl" activeTab=$activeTab}
    {/capture}

    {capture name=ebAccountBreadcrumb}
        <div class="eb-breadcrumb">
            <a href="{$WEB_ROOT}/clientarea.php?action=details" class="eb-breadcrumb-link">Account</a>
            <span class="eb-breadcrumb-separator">/</span>
            <span class="eb-breadcrumb-current">Users</span>
        </div>
    {/capture}

    {capture name=ebAccountContent}
        {include file="$template/includes/ui/page-header.tpl"
            ebBreadcrumb=$smarty.capture.ebAccountBreadcrumb
            ebPageTitle={lang key="navUserManagement"}
            ebPageDescription="Manage account users, invitations, and permission assignments."
        }

        <div class="eb-subpanel">
            <div class="eb-section-intro">
                <h3 class="eb-section-title">{lang key="navUserManagement"}</h3>
                <p class="eb-section-description">{lang key="userManagement.usersFound" count=$users->count()}</p>
            </div>

            <div class="eb-table-shell">
                <table class="eb-table">
                    <thead>
                        <tr>
                            <th>{lang key="userManagement.emailAddress"} / {lang key="userManagement.lastLogin"}</th>
                            <th>{lang key="userManagement.actions"}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $users as $user}
                            <tr>
                                <td>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span>{$user->email}</span>
                                        {if $user->pivot->owner}
                                            <span class="eb-badge eb-badge--success">{lang key="clientOwner"}</span>
                                        {/if}
                                        {if $user->hasTwoFactorAuthEnabled()}
                                            <i class="fas fa-shield" style="color: var(--eb-success-icon);" title="{lang key='twoFactor.enabled'}"></i>
                                        {else}
                                            <i class="fas fa-shield eb-text-muted" title="{lang key='twoFactor.disabled'}"></i>
                                        {/if}
                                    </div>
                                    <p class="eb-choice-card-description mt-2">
                                        {lang key="userManagement.lastLogin"}:
                                        {if $user->pivot->hasLastLogin()}
                                            {$user->pivot->getLastLogin()->diffForHumans()}
                                        {else}
                                            {lang key='never'}
                                        {/if}
                                    </p>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{routePath('account-users-permissions', $user->id)}"
                                           class="eb-btn eb-btn-secondary eb-btn-xs{if $user->pivot->owner} pointer-events-none opacity-50{/if}">
                                            {lang key="userManagement.managePermissions"}
                                        </a>
                                        <button type="button"
                                                class="eb-btn eb-btn-danger eb-btn-xs{if $user->pivot->owner} pointer-events-none opacity-50{/if}"
                                                data-id="{$user->id}"
                                                @click="if (!$event.currentTarget.classList.contains('pointer-events-none')) { removeUserModal = true; currentUserId = $event.currentTarget.dataset.id; }">
                                            {lang key="userManagement.removeAccess"}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        {/foreach}

                        {if $invites->count() > 0}
                            <tr>
                                <td colspan="2" class="eb-table-primary">{lang key="userManagement.pendingInvites"}</td>
                            </tr>
                            {foreach $invites as $invite}
                                <tr>
                                    <td>
                                        <span>{$invite->email}</span>
                                        <p class="eb-choice-card-description mt-2">{lang key="userManagement.inviteSent"}: {$invite->created_at->diffForHumans()}</p>
                                    </td>
                                    <td>
                                        <form method="post" action="{routePath('account-users-invite-resend')}" class="flex flex-wrap gap-2">
                                            <input type="hidden" name="inviteid" value="{$invite->id}">
                                            <button type="submit" class="eb-btn eb-btn-secondary eb-btn-xs">{lang key="userManagement.resendInvite"}</button>
                                            <button type="button"
                                                    class="eb-btn eb-btn-secondary eb-btn-xs"
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
            </div>

            <p class="eb-choice-card-description mt-4">* {lang key="userManagement.accountOwnerPermissionsInfo"}</p>
        </div>

        <div class="mt-4 eb-subpanel">
            <h3 class="eb-section-title">{lang key="userManagement.inviteNewUser"}</h3>
            <p class="eb-section-description">{lang key="userManagement.inviteNewUserDescription"}</p>

            <form method="post" action="{routePath('account-users-invite')}" class="mt-5 space-y-5">
                <div>
                    <label for="inviteemail" class="eb-field-label">{lang key="userManagement.emailAddress"}</label>
                    <input type="email" id="inviteemail" name="inviteemail" placeholder="name@example.com" class="eb-input" value="{$formdata.inviteemail}">
                </div>

                <div class="space-y-3">
                    <label class="eb-inline-choice">
                        <input type="radio" class="eb-radio-input" name="permissions" value="all" checked="checked" @change="invitePermissions = false">
                        <span>{lang key="userManagement.allPermissions"}</span>
                    </label>
                    <label class="eb-inline-choice">
                        <input type="radio" class="eb-radio-input" name="permissions" value="choose" @change="invitePermissions = true">
                        <span>{lang key="userManagement.choosePermissions"}</span>
                    </label>
                </div>

                <div x-show="invitePermissions" x-cloak class="grid gap-3 sm:grid-cols-2">
                    {foreach $permissions as $permission}
                        <label class="eb-choice-card">
                            <div class="eb-choice-card-control">
                                <input type="checkbox" class="eb-check-input" name="perms[{$permission.key}]" value="1">
                            </div>
                            <div>
                                <span class="eb-choice-card-title">{$permission.title}</span>
                                <small class="eb-choice-card-description">{$permission.description}</small>
                            </div>
                        </label>
                    {/foreach}
                </div>

                <button type="submit" class="eb-btn eb-btn-primary">{lang key="userManagement.sendInvite"}</button>
            </form>
        </div>
    {/capture}

    {include file="$template/includes/ui/page-shell.tpl"
        ebPageNav=$smarty.capture.ebAccountNav
        ebPageContent=$smarty.capture.ebAccountContent
    }

    <div x-show="removeUserModal" x-cloak class="fixed inset-0 z-50">
        <div class="eb-modal-backdrop absolute inset-0" @click="removeUserModal = false"></div>
        <div class="relative flex min-h-full items-center justify-center px-4">
            <form method="post" action="{routePath('account-users-remove')}" class="eb-modal">
                <input type="hidden" name="userid" :value="currentUserId" id="inputRemoveUserId">
                <div class="eb-modal-header">
                    <h4 class="eb-modal-title">{lang key="userManagement.removeAccess"}</h4>
                </div>
                <div class="eb-modal-body space-y-2">
                    <p>{lang key="userManagement.removeAccessSure"}</p>
                    <p>{lang key="userManagement.removeAccessInfo"}</p>
                </div>
                <div class="eb-modal-footer">
                    <button type="button" class="eb-btn eb-btn-ghost" @click="removeUserModal = false">{lang key="cancel"}</button>
                    <button type="submit" class="eb-btn eb-btn-danger" id="btnRemoveUserConfirm">{lang key="confirm"}</button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="cancelInviteModal" x-cloak class="fixed inset-0 z-50">
        <div class="eb-modal-backdrop absolute inset-0" @click="cancelInviteModal = false"></div>
        <div class="relative flex min-h-full items-center justify-center px-4">
            <form method="post" action="{routePath('account-users-invite-cancel')}" class="eb-modal">
                <input type="hidden" name="inviteid" :value="currentInviteId" id="inputCancelInviteId">
                <div class="eb-modal-header">
                    <h4 class="eb-modal-title">{lang key="userManagement.cancelInvite"}</h4>
                </div>
                <div class="eb-modal-body space-y-2">
                    <p>{lang key="userManagement.cancelInviteSure"}</p>
                    <p>{lang key="userManagement.cancelInviteInfo"}</p>
                </div>
                <div class="eb-modal-footer">
                    <button type="button" class="eb-btn eb-btn-ghost" @click="cancelInviteModal = false">{lang key="cancel"}</button>
                    <button type="submit" class="eb-btn eb-btn-danger" id="btnCancelInviteConfirm">{lang key="confirm"}</button>
                </div>
            </form>
        </div>
    </div>
</div>
