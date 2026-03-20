{assign var=ebAuthShellClass value=$ebAuthShellClass|default:''}
{assign var=ebAuthWrapClass value=$ebAuthWrapClass|default:''}
{assign var=ebAuthCardClass value=$ebAuthCardClass|default:''}

<div class="eb-auth-shell {$ebAuthShellClass}">
    <div class="eb-auth-bg"></div>

    <div class="eb-auth-wrap {$ebAuthWrapClass}">
        <div class="eb-auth-glow"></div>
        <div class="eb-auth-card {$ebAuthCardClass}">
            {$ebAuthContent nofilter}
        </div>
    </div>
</div>
