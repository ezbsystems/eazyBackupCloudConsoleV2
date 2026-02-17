<style>
    .eb-invite-input {
        width: 100%;
        border-radius: 0.75rem;
        border: 1px solid rgba(51, 65, 85, 0.9);
        background: rgba(15, 23, 42, 0.7);
        color: rgb(226, 232, 240);
        padding: 0.625rem 0.875rem;
        transition: border-color .2s ease, box-shadow .2s ease;
    }
    .eb-invite-input:focus {
        outline: none;
        border-color: rgb(56, 189, 248);
        box-shadow: 0 0 0 1px rgba(56, 189, 248, 0.5);
    }
    .eb-invite-label {
        display: block;
        margin-bottom: 0.45rem;
        font-size: 0.8125rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        color: rgb(148, 163, 184);
    }
    .eb-invite-btn-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        border-radius: 0.75rem;
        border: 1px solid rgba(56, 189, 248, 0.5);
        background: linear-gradient(to right, rgb(14 165 233), rgb(59 130 246));
        color: white;
        font-weight: 700;
        padding: 0.625rem 1.15rem;
        transition: transform .15s ease, box-shadow .2s ease;
    }
    .eb-invite-btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 26px rgba(56, 189, 248, 0.2);
    }
</style>

<div class="mx-auto {if $loggedin || !$invite}max-w-3xl{else}max-w-6xl{/if} my-8 px-4">
    <div class="relative overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/85 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(56,189,248,0.10),_transparent_60%)]"></div>
        <div class="relative px-6 py-8 sm:px-10 sm:py-10 text-slate-200">
            {if $invite}
                <div class="mb-6 text-center">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl border border-sky-500/30 bg-sky-500/10 text-sky-300">
                        <i class="fas fa-user-check text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-semibold text-white">
                        {lang key="accountInvite.youHaveBeenInvited" clientName=$invite->getClientName()}
                    </h2>
                    <p class="mt-3 text-base text-slate-300">
                        {lang key="accountInvite.givenAccess" senderName=$invite->getSenderName() clientName=$invite->getClientName() ot="<strong class='text-white'>" ct="</strong>"}
                    </p>
                    <p class="mt-2 text-sm text-slate-400">
                        {if $loggedin}
                            {lang key="accountInvite.inviteAcceptLoggedIn"}
                        {else}
                            {lang key="accountInvite.inviteAcceptLoggedOut"}
                        {/if}
                    </p>
                </div>

                <div class="mb-6">
                    {include file="$template/includes/flashmessage.tpl"}
                </div>

                {if $loggedin}
                    <div class="rounded-2xl border border-slate-800 bg-slate-900/65 p-8 text-center">
                        <p class="mb-5 text-slate-300">{lang key="accountInvite.inviteAcceptLoggedIn"}</p>
                        <form method="post" action="{routePath('invite-validate', $invite->token)}">
                            <button type="submit" class="eb-invite-btn-primary">
                                <i class="fas fa-check"></i>
                                {lang key="accountInvite.accept"}
                            </button>
                        </form>
                    </div>
                {else}
                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                        <div class="rounded-2xl border border-slate-800 bg-slate-900/65 p-6 sm:p-7">
                            <h3 class="text-lg font-semibold text-white mb-1">{lang key="login"}</h3>
                            <p class="text-xs uppercase tracking-wide text-slate-400 mb-6">{lang key="accountInvite.accept"}</p>
                            <form method="post" action="{routePath('login-validate')}" class="text-left space-y-4">
                                <div>
                                    <label for="inputLoginEmail" class="eb-invite-label">{lang key="loginemail"}</label>
                                    <input type="email" name="username" id="inputLoginEmail" placeholder="{lang key='loginemail'}" value="{$formdata.email}" class="eb-invite-input">
                                </div>
                                <div>
                                    <label for="inputLoginPassword" class="eb-invite-label">{lang key="loginpassword"}</label>
                                    <input type="password" name="password" id="inputLoginPassword" placeholder="{lang key='loginpassword'}" class="eb-invite-input">
                                </div>

                                <div class="pt-2">
                                    {include file="$template/includes/captcha.tpl" captchaForm=$captchaForm containerClass="flex flex-wrap gap-4" nocache}
                                </div>

                                <div class="pt-2">
                                    <button
                                        type="submit"
                                        class="group relative inline-flex w-full items-center justify-center overflow-hidden rounded-full px-[2px] py-[2px] focus:outline-none{$captcha->getButtonClass($captchaForm)}"
                                    >
                                        <span
                                            class="relative inline-flex w-full items-center justify-center rounded-full bg-gradient-to-r from-[#FE5000] via-[#ff8a3a] to-[#FE5000] px-4 py-2 text-sm font-semibold text-white shadow-[0_0_25px_rgba(254,80,0,0.55)] transition duration-300 group-hover:shadow-[0_0_40px_rgba(254,80,0,0.85)] group-hover:translate-y-[1px]"
                                        >
                                            {lang key="login"}
                                        </span>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="rounded-2xl border border-slate-800 bg-slate-900/65 p-6 sm:p-7">
                            <h3 class="text-lg font-semibold text-white mb-1">{lang key="register"}</h3>
                            <p class="text-xs uppercase tracking-wide text-slate-400 mb-6">{lang key="accountInvite.accept"}</p>
                            <form method="post" action="{routePath('invite-validate', $invite->token)}" class="text-left space-y-4">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="inputFirstName" class="eb-invite-label">{lang key="clientareafirstname"}</label>
                                        <input type="text" name="firstname" id="inputFirstName" placeholder="{lang key='clientareafirstname'}" value="{$formdata.firstname}" class="eb-invite-input">
                                    </div>
                                    <div>
                                        <label for="inputLastName" class="eb-invite-label">{lang key="clientarealastname"}</label>
                                        <input type="text" name="lastname" id="inputLastName" placeholder="{lang key='clientarealastname'}" value="{$formdata.lastname}" class="eb-invite-input">
                                    </div>
                                </div>

                                <div>
                                    <label for="inputEmail" class="eb-invite-label">{lang key="loginemail"}</label>
                                    <input type="email" name="email" id="inputEmail" placeholder="{lang key='loginemail'}" value="{$formdata.email}" class="eb-invite-input">
                                </div>

                                <div>
                                    <label for="inputPassword" class="eb-invite-label">{lang key="loginpassword"}</label>
                                    <input type="password" name="password" id="inputPassword" placeholder="{lang key='loginpassword'}" autocomplete="off" class="eb-invite-input" />
                                </div>

                                {if $accept_tos}
                                    <div class="rounded-xl border border-slate-800 bg-slate-900/50 px-4 py-3">
                                        <label class="inline-flex items-start gap-3 text-sm text-slate-300">
                                            <input type="checkbox" name="accept" id="accept" class="mt-0.5 h-4 w-4 rounded border-slate-600 bg-slate-800 text-sky-500">
                                            <span>{lang key='ordertosagreement'} <a href="{$tos_url}" target="_blank" class="font-semibold text-sky-300 hover:text-sky-200 hover:underline">{lang key='ordertos'}</a></span>
                                        </label>
                                    </div>
                                {/if}

                                <div class="pt-2">
                                    {include file="$template/includes/captcha.tpl" captchaForm=$captchaFormRegister containerClass="flex flex-wrap gap-4" nocache}
                                </div>

                                <div class="pt-2 text-right">
                                    <button type="submit" class="btn-accent{$captcha->getButtonClass($captchaFormRegister)}">
                                        {lang key="register"}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                {/if}
            {else}
                <div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-6 py-8 text-center">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl border border-rose-500/30 bg-rose-500/10 text-rose-300">
                        <i class="fas fa-times text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-semibold text-white">{lang key="accountInvite.notFound"}</h2>
                    <p class="mt-3 text-slate-300">{lang key="accountInvite.contactAdministrator"}</p>
                </div>
            {/if}
        </div>
    </div>
</div>
