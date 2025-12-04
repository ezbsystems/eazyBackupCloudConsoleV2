<div class="flex justify-center items-center min-h-screen bg-slate-950 text-gray-300">
    <!-- Global nebula background -->
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>

    <div class="relative max-w-md w-full">
        <!-- Local glow behind the reset card -->
        <div class="pointer-events-none absolute -top-24 -right-16 w-[26rem] h-[26rem] bg-[radial-gradient(circle_at_center,_#1f2937a6,_transparent_65%)] -z-10"></div>

        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6 w-full">
            {if $loggedin && $innerTemplate}
                {include file="$template/includes/alert-darkmode.tpl" type="error" msg="{lang key='noPasswordResetWhenLoggedIn'}" textcenter=true}
            {else}
                {if $successMessage}
                    {include file="$template/includes/alert-darkmode.tpl" type="success" msg=$successTitle textcenter=true}
                    <p class="text-gray-300 text-sm text-center">{$successMessage}</p>
                {else}
                    {if $innerTemplate}
                        {include file="$template/password-reset-$innerTemplate.tpl"}
                    {/if}
                {/if}
            {/if}
        </div>
    </div>
</div>
