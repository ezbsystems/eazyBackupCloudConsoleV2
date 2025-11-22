<script src="{$BASE_PATH_JS}/PasswordStrength.js"></script>
<script>
    window.langPasswordStrength = "{lang key="pwstrength"}";
    window.langPasswordWeak = "{lang key="pwstrengthweak"}";
    window.langPasswordModerate = "{lang key="pwstrengthmoderate"}";
    window.langPasswordStrong = "{lang key="pwstrengthstrong"}";
    jQuery(document).ready(function() {
        jQuery("#inputPassword").keyup(registerFormPasswordStrengthFeedback);
    });
</script>

<div class="mx-auto {if $loggedin || !$invite} max-w-3xl {/if} bg-gray-700 rounded shadow my-4">
    <div class="px-5 py-5 text-center text-gray-300 bg-gray-800">
        {if $invite}
            <h2 class="mb-4">
                <i class="fas fa-info fa-2x text-blue-400 pb-4"></i>
                <br>
                {lang key="accountInvite.youHaveBeenInvited" clientName=$invite->getClientName()}
            </h2>

            {include file="$template/includes/flashmessage.tpl"}

            <p class="mb-4">
                {lang key="accountInvite.givenAccess" senderName=$invite->getSenderName() clientName=$invite->getClientName() ot="<strong>" ct="</strong>"}
            </p>

            {if $loggedin}
                <p class="mb-4">{lang key="accountInvite.inviteAcceptLoggedIn"}</p>
            {else}
                <p class="mb-4">{lang key="accountInvite.inviteAcceptLoggedOut"}</p>
            {/if}

            {if $loggedin}
                <form method="post" action="{routePath('invite-validate', $invite->token)}">
                    <p>
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                            {lang key="accountInvite.accept"}
                        </button>
                    </p>
                </form>
            {else}
                <div class="flex flex-col lg:flex-row gap-4">
                    <div class="w-full lg:w-1/2">
                        <div class="bg-gray-800 p-6 rounded shadow">
                            <h2 class="text-xl mb-4">{lang key="login"}</h2>
                            <form method="post" action="{routePath('login-validate')}" class="text-left">
                                <div class="mb-4">
                                    <label for="inputLoginEmail" class="block mb-1">{lang key="loginemail"}</label>
                                    <input type="email" name="username" id="inputLoginEmail" placeholder="{lang key="loginemail"}" value="{$formdata.email}" class="w-full p-2 rounded border border-gray-600 bg-gray-700 text-gray-300 focus:outline-none focus:border-blue-500">
                                </div>
                                <div class="mb-4">
                                    <label for="inputLoginPassword" class="block mb-1">{lang key="loginpassword"}</label>
                                    <input type="password" name="password" id="inputLoginPassword" placeholder="{lang key="loginpassword"}" class="w-full p-2 rounded border border-gray-600 bg-gray-700 text-gray-300 focus:outline-none focus:border-blue-500">
                                </div>
                                {include file="$template/includes/captcha.tpl" captchaForm=$captchaForm containerClass="flex flex-wrap gap-4" nocache}
                                <div class="text-center">
                                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded{$captcha->getButtonClass($captchaForm)}">
                                        {lang key="login"}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="w-full lg:w-1/2">
                        <div class="bg-gray-800 p-6 rounded shadow">
                            <h2 class="text-xl mb-4">{lang key="register"}</h2>
                            <form method="post" action="{routePath('invite-validate', $invite->token)}" class="text-left">
                                <div class="mb-4">
                                    <label for="inputFirstName" class="block mb-1">{lang key="clientareafirstname"}</label>
                                    <input type="text" name="firstname" id="inputFirstName" placeholder="{lang key="clientareafirstname"}" value="{$formdata.firstname}" class="w-full p-2 rounded border border-gray-600 bg-gray-700 text-gray-300 focus:outline-none focus:border-blue-500">
                                </div>
                                <div class="mb-4">
                                    <label for="inputLastName" class="block mb-1">{lang key="clientarealastname"}</label>
                                    <input type="text" name="lastname" id="inputLastName" placeholder="{lang key="clientarealastname"}" value="{$formdata.lastname}" class="w-full p-2 rounded border border-gray-600 bg-gray-700 text-gray-300 focus:outline-none focus:border-blue-500">
                                </div>
                                <div class="mb-4">
                                    <label for="inputEmail" class="block mb-1">{lang key="loginemail"}</label>
                                    <input type="email" name="email" id="inputEmail" placeholder="{lang key="loginemail"}" value="{$formdata.email}" class="w-full p-2 rounded border border-gray-600 bg-gray-700 text-gray-300 focus:outline-none focus:border-blue-500">
                                </div>
                                <div class="mb-4">
                                    <label for="inputPassword" class="block mb-1">{lang key="loginpassword"}</label>
                                    <div class="flex">
                                        <input type="password" name="password" id="inputPassword" data-error-threshold="{$pwStrengthErrorThreshold}" data-warning-threshold="{$pwStrengthWarningThreshold}" placeholder="{lang key="loginpassword"}" autocomplete="off" class="flex-grow p-2 rounded-l border-t border-l border-b border-gray-600 bg-gray-700 text-gray-300 focus:outline-none focus:border-blue-500" />
                                        <button type="button" class="bg-blue-500 hover:bg-blue-600 text-white font-bold px-4 rounded-r generate-password" data-targetfields="inputPassword">
                                            {lang key="generatePassword.btnShort"}
                                        </button>
                                    </div>

                                    <div class="mt-3">
                                        <div class="w-full bg-gray-600 rounded h-2">
                                            <div class="bg-green-500 rounded h-2 bg-striped" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" id="passwordStrengthMeterBar" style="width: 0%;"></div>
                                        </div>
                                        <p class="text-center text-sm text-gray-400 mt-2" id="passwordStrengthTextLabel">
                                            {lang key="pwstrength"}: {lang key="pwstrengthenter"}
                                        </p>
                                    </div>
                                </div>
                                {if $accept_tos}
                                    <div class="mb-4 text-center">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="accept" id="accept" class="form-checkbox h-4 w-4 text-blue-500">
                                            <span class="ml-2">{lang key='ordertosagreement'} <a href="{$tos_url}" target="_blank" class="text-blue-400 hover:underline">{lang key='ordertos'}</a></span>
                                        </label>
                                    </div>
                                {/if}
                                {include file="$template/includes/captcha.tpl" captchaForm=$captchaFormRegister containerClass="flex flex-wrap gap-4" nocache}
                                <div class="text-center">
                                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded{$captcha->getButtonClass($captchaFormRegister)}">
                                        {lang key="register"}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            {/if}
        {else}
            <h2 class="mb-4">
                <i class="fas fa-times fa-2x text-red-500 pb-4"></i><br>
                {lang key="accountInvite.notFound"}
            </h2>

            <p class="pt-4">{lang key="accountInvite.contactAdministrator"}</p>
        {/if}
    </div>
</div>

<br><br>
