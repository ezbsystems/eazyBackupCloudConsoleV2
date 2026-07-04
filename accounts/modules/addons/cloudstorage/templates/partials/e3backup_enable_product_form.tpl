{assign var=ebEnableProductChoice value=$ebEnableProductChoice|default:'e3backup'}
{assign var=ebEnablePageTitle value=$ebEnablePageTitle|default:'Enable backup product'}
{assign var=ebEnablePageDescription value=$ebEnablePageDescription|default:''}

<div class="eb-card-raised max-w-2xl">
    <div class="eb-card-header">
        <div>
            <h2 class="eb-card-title">{$ebEnablePageTitle|escape}</h2>
            {if $ebEnablePageDescription}
            <p class="eb-card-subtitle">{$ebEnablePageDescription|escape}</p>
            {/if}
        </div>
    </div>

    <div class="p-6 space-y-5">
        <div class="eb-alert eb-alert--info">
            <div>
                <div class="eb-alert-title">Product enablement has moved</div>
                <p class="eb-type-body">
                    Backup workloads are now provisioned per Backup User. Add a User from the Users page instead of enabling separate products.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <a href="index.php?m=cloudstorage&page=e3backup&view=users" class="eb-btn eb-btn-primary eb-btn-md">Go to Users</a>
            <a href="index.php?m=cloudstorage&page=e3backup&view=dashboard" class="eb-btn eb-btn-secondary eb-btn-md">Back to dashboard</a>
        </div>
    </div>
</div>
