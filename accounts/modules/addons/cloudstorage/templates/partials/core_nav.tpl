{assign var=cloudstorageActivePage value=$cloudstorageActivePage|default:'dashboard'}

<nav class="flex flex-wrap items-center gap-2" aria-label="Cloud Storage Navigation">
    <a href="index.php?m=cloudstorage&page=dashboard" class="eb-tab {if $cloudstorageActivePage eq 'dashboard'}is-active{/if}">
        Dashboard
    </a>
    <a href="index.php?m=cloudstorage&page=buckets" class="eb-tab {if $cloudstorageActivePage eq 'buckets'}is-active{/if}">
        Buckets
    </a>
    <a href="index.php?m=cloudstorage&page=access_keys" class="eb-tab {if $cloudstorageActivePage eq 'access_keys'}is-active{/if}">
        Access Keys
    </a>
    <a href="index.php?m=cloudstorage&page=users" class="eb-tab {if $cloudstorageActivePage eq 'users'}is-active{/if}">
        Users
    </a>
    <a href="index.php?m=cloudstorage&page=billing" class="eb-tab {if $cloudstorageActivePage eq 'billing'}is-active{/if}">
        Billing
    </a>
    <a href="index.php?m=cloudstorage&page=history" class="eb-tab {if $cloudstorageActivePage eq 'history'}is-active{/if}">
        Historical Stats
    </a>
</nav>
