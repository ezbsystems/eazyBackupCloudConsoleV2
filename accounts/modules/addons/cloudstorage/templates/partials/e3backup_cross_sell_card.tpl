{*
    Reusable cross-sell footer card.
    Params: ebCrossSellTitle, ebCrossSellBody, ebCrossSellCtaLabel, ebCrossSellCtaHref
*}
{assign var=ebCrossSellTitle value=$ebCrossSellTitle|default:''}
{assign var=ebCrossSellBody value=$ebCrossSellBody|default:''}
{assign var=ebCrossSellCtaLabel value=$ebCrossSellCtaLabel|default:'Learn more'}
{assign var=ebCrossSellCtaHref value=$ebCrossSellCtaHref|default:'#'}

<div class="eb-card-raised mt-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h3 class="eb-type-h4">{$ebCrossSellTitle|escape}</h3>
            <p class="eb-type-body mt-2 text-[var(--eb-text-muted)]">{$ebCrossSellBody|escape}</p>
        </div>
        <a href="{$ebCrossSellCtaHref|escape:'html'}" class="eb-btn eb-btn-secondary eb-btn-md shrink-0">
            {$ebCrossSellCtaLabel|escape}
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
            </svg>
        </a>
    </div>
</div>
