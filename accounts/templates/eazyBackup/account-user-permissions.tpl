{assign var="activeTab" value="userpermissions"}

{capture name=ebAccountNav}
    {include file="$template/includes/profile-nav.tpl" activeTab=$activeTab}
{/capture}

{capture name=ebAccountBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/clientarea.php?action=details" class="eb-breadcrumb-link">Account</a>
        <span class="eb-breadcrumb-separator">/</span>
        <a href="{routePath('account-users')}" class="eb-breadcrumb-link">Users</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Permissions</span>
    </div>
{/capture}

{capture name=ebAccountContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebAccountBreadcrumb
        ebPageTitle={lang key='userManagement.managePermissions'}
        ebPageDescription=$user->email
    }

    <div class="eb-subpanel">
        <form method="post" action="{routePath('account-users-permissions-save', $user->id)}" class="space-y-5">
            <div>
                <h3 class="eb-section-title">{lang key="userManagement.permissions"}</h3>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    {foreach $permissions as $permission}
                        <label class="eb-choice-card{if $userPermissions->hasPermission($permission.key)} is-selected{/if}">
                            <div class="eb-choice-card-control">
                                <input type="checkbox" class="eb-check-input" name="perms[{$permission.key}]" value="1"{if $userPermissions->hasPermission($permission.key)} checked{/if}>
                            </div>
                            <div>
                                <span class="eb-choice-card-title">{$permission.title}</span>
                                <small class="eb-choice-card-description">{$permission.description}</small>
                            </div>
                        </label>
                    {/foreach}
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{routePath('account-users')}" class="eb-btn eb-btn-ghost">{lang key="clientareacancel"}</a>
                <button type="submit" class="eb-btn eb-btn-primary">{lang key="clientareasavechanges"}</button>
            </div>
        </form>
    </div>
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPageNav=$smarty.capture.ebAccountNav
    ebPageContent=$smarty.capture.ebAccountContent
}
