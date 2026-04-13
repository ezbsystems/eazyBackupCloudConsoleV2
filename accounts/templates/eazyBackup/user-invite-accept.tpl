<style>
.eb-invite-page {
    padding-block: 2rem;
}

.eb-invite-panel {
    max-width: 72rem;
    margin-inline: auto;
}

.eb-invite-panel.is-compact {
    max-width: 48rem;
}

.eb-invite-shell {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.eb-invite-hero {
    text-align: center;
}

.eb-invite-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 3.5rem;
    height: 3.5rem;
    margin: 0 auto 1rem;
    border-radius: 1rem;
    border: 1px solid var(--eb-border-muted);
    background: color-mix(in srgb, var(--eb-info-weak) 74%, transparent 26%);
    color: var(--eb-info-text);
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.03);
}

.eb-invite-icon.is-danger {
    background: color-mix(in srgb, var(--eb-danger-weak) 74%, transparent 26%);
    color: var(--eb-danger-text);
}

.eb-invite-icon i {
    font-size: 1.5rem;
}

.eb-invite-title {
    margin: 0;
    color: var(--eb-text-primary);
    font-family: var(--eb-type-heading-family);
    font-size: clamp(1.9rem, 3vw, 2.4rem);
    font-weight: 600;
    line-height: 1.05;
    letter-spacing: -0.03em;
}

.eb-invite-copy {
    margin: 0.9rem auto 0;
    max-width: 44rem;
    color: var(--eb-text-secondary);
    font-size: 0.98rem;
    line-height: 1.65;
}

.eb-invite-copy strong,
.eb-invite-copy b {
    color: var(--eb-text-primary);
}

.eb-invite-copy.is-muted {
    margin-top: 0.55rem;
    color: var(--eb-text-muted);
    font-size: 0.84rem;
}

.eb-invite-grid {
    display: grid;
    gap: 1.5rem;
}

.eb-invite-form-card {
    height: 100%;
}

.eb-invite-form-card .eb-card-header {
    margin-bottom: 0;
}

.eb-invite-form-body {
    padding-top: 1.25rem;
}

.eb-invite-field-grid {
    display: grid;
    gap: 1rem;
}

.eb-invite-captcha {
    padding-top: 0.25rem;
}

.eb-invite-action {
    padding-top: 0.25rem;
}

.eb-invite-action.is-right {
    display: flex;
    justify-content: flex-end;
}

.eb-invite-login-cta {
    text-align: center;
}

.eb-invite-login-cta p {
    margin: 0 0 1.25rem;
    color: var(--eb-text-secondary);
}

.eb-invite-tos {
    padding: 0.95rem 1rem;
    border: 1px solid var(--eb-border-subtle);
    border-radius: var(--eb-radius-lg);
    background: color-mix(in srgb, var(--eb-bg-card) 78%, black 22%);
}

.eb-invite-tos a {
    color: var(--eb-info-text);
    font-weight: 600;
}

.eb-invite-invalid {
    max-width: 42rem;
    margin: 0 auto;
}

.eb-invite-invalid .eb-alert {
    justify-content: center;
    text-align: center;
}

.eb-invite-invalid .eb-alert-body {
    max-width: 32rem;
}

@media (min-width: 1200px) {
    .eb-invite-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (min-width: 640px) {
    .eb-invite-field-grid.is-split {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>

<div class="eb-page eb-invite-page">
    <div class="eb-page-inner">
        <div class="eb-panel eb-invite-panel {if $loggedin || !$invite}is-compact{/if}">
            <div class="eb-invite-shell">
                {if $invite}
                    <div class="eb-invite-hero">
                        <div class="eb-invite-icon">
                            <i class="fas fa-user-check" aria-hidden="true"></i>
                        </div>
                        <h1 class="eb-invite-title">
                            {lang key="accountInvite.youHaveBeenInvited" clientName=$invite->getClientName()}
                        </h1>
                        <p class="eb-invite-copy">
                            {lang key="accountInvite.givenAccess" senderName=$invite->getSenderName() clientName=$invite->getClientName() ot="<strong>" ct="</strong>"}
                        </p>
                        <p class="eb-invite-copy is-muted">
                            {if $loggedin}
                                {lang key="accountInvite.inviteAcceptLoggedIn"}
                            {else}
                                {lang key="accountInvite.inviteAcceptLoggedOut"}
                            {/if}
                        </p>
                    </div>

                    <div>
                        {include file="$template/includes/flashmessage.tpl"}
                    </div>

                    {if $loggedin}
                        <div class="eb-card-raised">
                            <div class="eb-card-header">
                                <div>
                                    <div class="eb-card-title">{lang key="accountInvite.accept"}</div>
                                    <p class="eb-card-subtitle">{lang key="accountInvite.inviteAcceptLoggedIn"}</p>
                                </div>
                            </div>
                            <div class="eb-invite-login-cta">
                                <p>{lang key="accountInvite.inviteAcceptLoggedIn"}</p>
                                <form method="post" action="{routePath('invite-validate', $invite->token)}">
                                    <button type="submit" class="eb-btn eb-btn-primary eb-btn-md">
                                        <i class="fas fa-check" aria-hidden="true"></i>
                                        <span>{lang key="accountInvite.accept"}</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    {else}
                        <div class="eb-invite-grid">
                            <div class="eb-card-raised eb-invite-form-card">
                                <div class="eb-card-header eb-card-header--divided">
                                    <div>
                                        <div class="eb-card-title">{lang key="login"}</div>
                                        <p class="eb-card-subtitle">{lang key="accountInvite.accept"}</p>
                                    </div>
                                </div>
                                <div class="eb-invite-form-body">
                                    <form method="post" action="{routePath('login-validate')}" class="space-y-4">
                                        <div>
                                            <label for="inputLoginEmail" class="eb-field-label">{lang key="loginemail"}</label>
                                            <input type="email" name="username" id="inputLoginEmail" placeholder="{lang key='loginemail'}" value="{$formdata.email}" class="eb-input">
                                        </div>
                                        <div>
                                            <label for="inputLoginPassword" class="eb-field-label">{lang key="loginpassword"}</label>
                                            <input type="password" name="password" id="inputLoginPassword" placeholder="{lang key='loginpassword'}" class="eb-input">
                                        </div>

                                        <div class="eb-invite-captcha">
                                            {include file="$template/includes/captcha.tpl" captchaForm=$captchaForm containerClass="flex flex-wrap gap-4" nocache}
                                        </div>

                                        <div class="eb-invite-action">
                                            <button type="submit" class="eb-btn eb-btn-primary eb-btn-md w-full justify-center{$captcha->getButtonClass($captchaForm)}">
                                                {lang key="login"}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="eb-card-raised eb-invite-form-card">
                                <div class="eb-card-header eb-card-header--divided">
                                    <div>
                                        <div class="eb-card-title">{lang key="register"}</div>
                                        <p class="eb-card-subtitle">{lang key="accountInvite.accept"}</p>
                                    </div>
                                </div>
                                <div class="eb-invite-form-body">
                                    <form method="post" action="{routePath('invite-validate', $invite->token)}" class="space-y-4">
                                        <div class="eb-invite-field-grid is-split">
                                            <div>
                                                <label for="inputFirstName" class="eb-field-label">{lang key="clientareafirstname"}</label>
                                                <input type="text" name="firstname" id="inputFirstName" placeholder="{lang key='clientareafirstname'}" value="{$formdata.firstname}" class="eb-input">
                                            </div>
                                            <div>
                                                <label for="inputLastName" class="eb-field-label">{lang key="clientarealastname"}</label>
                                                <input type="text" name="lastname" id="inputLastName" placeholder="{lang key='clientarealastname'}" value="{$formdata.lastname}" class="eb-input">
                                            </div>
                                        </div>

                                        <div>
                                            <label for="inputEmail" class="eb-field-label">{lang key="loginemail"}</label>
                                            <input type="email" name="email" id="inputEmail" placeholder="{lang key='loginemail'}" value="{$formdata.email}" class="eb-input">
                                        </div>

                                        <div>
                                            <label for="inputPassword" class="eb-field-label">{lang key="loginpassword"}</label>
                                            <input type="password" name="password" id="inputPassword" placeholder="{lang key='loginpassword'}" autocomplete="off" class="eb-input">
                                        </div>

                                        {if $accept_tos}
                                            <div class="eb-invite-tos">
                                                <label class="eb-inline-choice">
                                                    <input type="checkbox" name="accept" id="accept" class="eb-check-input">
                                                    <span>{lang key='ordertosagreement'} <a href="{$tos_url}" target="_blank">{lang key='ordertos'}</a></span>
                                                </label>
                                            </div>
                                        {/if}

                                        <div class="eb-invite-captcha">
                                            {include file="$template/includes/captcha.tpl" captchaForm=$captchaFormRegister containerClass="flex flex-wrap gap-4" nocache}
                                        </div>

                                        <div class="eb-invite-action is-right">
                                            <button type="submit" class="eb-btn eb-btn-primary eb-btn-md{$captcha->getButtonClass($captchaFormRegister)}">
                                                {lang key="register"}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    {/if}
                {else}
                    <div class="eb-invite-invalid">
                        <div class="eb-alert eb-alert--danger">
                            <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86l-7.5 13A1 1 0 0 0 3.67 18h16.66a1 1 0 0 0 .87-1.5l-7.5-13a1 1 0 0 0-1.74 0z"/>
                            </svg>
                            <div class="eb-alert-body">
                                <div class="eb-alert-title">{lang key="accountInvite.notFound"}</div>
                                <p>{lang key="accountInvite.contactAdministrator"}</p>
                            </div>
                        </div>
                    </div>
                {/if}
            </div>
        </div>
    </div>
</div>
