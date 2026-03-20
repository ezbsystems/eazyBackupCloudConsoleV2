{assign var=activeTab value=$activeTab|default:''}

<nav class="flex flex-wrap items-center gap-1" aria-label="Account Navigation">
    <a href="{$WEB_ROOT}/clientarea.php?action=details" class="eb-tab{if $activeTab eq 'details'} is-active{/if}"{if $activeTab eq 'details'} aria-current="page"{/if}>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
        </svg>
        <span>Profile</span>
    </a>
    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=notify-settings" class="eb-tab{if $smarty.get.m eq 'eazybackup' && $smarty.get.a eq 'notify-settings'} is-active{/if}"{if $smarty.get.m eq 'eazybackup' && $smarty.get.a eq 'notify-settings'} aria-current="page"{/if}>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>
        <span>Notifications</span>
    </a>
    <a href="{$WEB_ROOT}/index.php/account/paymentmethods" class="eb-tab{if $activeTab eq 'paymethods' || $activeTab eq 'paymethodsmanage'} is-active{/if}"{if $activeTab eq 'paymethods' || $activeTab eq 'paymethodsmanage'} aria-current="page"{/if}>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
        </svg>
        <span>Payment Details</span>
    </a>
    <a href="{$WEB_ROOT}/index.php?rp=/user/security" class="eb-tab{if $activeTab eq 'security'} is-active{/if}"{if $activeTab eq 'security'} aria-current="page"{/if}>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
        </svg>
        <span>Security</span>
    </a>
    <a href="{$WEB_ROOT}/index.php?rp=/user/password" class="eb-tab{if $activeTab eq 'password'} is-active{/if}"{if $activeTab eq 'password'} aria-current="page"{/if}>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
        </svg>
        <span>Change Password</span>
    </a>
    <a href="{$WEB_ROOT}/index.php/account/contacts?contactid=" class="eb-tab{if $activeTab eq 'contactsmanage' || $activeTab eq 'contactsnew'} is-active{/if}"{if $activeTab eq 'contactsmanage' || $activeTab eq 'contactsnew'} aria-current="page"{/if}>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
        </svg>
        <span>Contacts</span>
    </a>
    <a href="{$WEB_ROOT}/index.php/account/users" class="eb-tab{if $activeTab eq 'usermanage' || $activeTab eq 'userpermissions'} is-active{/if}"{if $activeTab eq 'usermanage' || $activeTab eq 'userpermissions'} aria-current="page"{/if}>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
        </svg>
        <span>Users</span>
    </a>
    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=terms" class="eb-tab{if $smarty.get.m eq 'eazybackup' && $smarty.get.a eq 'terms'} is-active{/if}"{if $smarty.get.m eq 'eazybackup' && $smarty.get.a eq 'terms'} aria-current="page"{/if}>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5.25h12M3 9.75h12m-6 4.5h6M3.75 3h10.5A2.25 2.25 0 0 1 16.5 5.25v13.5A2.25 2.25 0 0 1 14.25 21H7.06a2.25 2.25 0 0 1-1.59-.66L3.66 18.54A2.25 2.25 0 0 1 3 16.95V5.25A2.25 2.25 0 0 1 5.25 3Z" />
        </svg>
        <span>Terms</span>
    </a>
</nav>
