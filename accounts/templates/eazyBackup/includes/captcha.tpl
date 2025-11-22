{if $captcha->isEnabled() && $captcha->isEnabledForForm($captchaForm)}
<div class="text-center{if $containerClass}{$containerClass}{else} flex flex-row justify-center{/if}">
    {if $templatefile == 'homepage'}
        <div class="domainchecker-homepage-captcha">
    {/if}

    {if $captcha->recaptcha->isEnabled() && !$captcha->recaptcha->isInvisible()}
        <div id="google-recaptcha-domainchecker" class="recaptcha-container mx-auto" data-action="{$captchaForm}"></div>
    {elseif !$captcha->recaptcha->isEnabled()}
        <div class="w-full md:w-2/3 mx-auto mb-3 sm:mb-0">
            <div id="default-captcha-domainchecker" class="{if $filename == 'domainchecker'}flex items-center {/if}text-center flex flex-row pb-3">
                <p>{lang key="captchaverify"}</p>

                <div class="w-1/2 captchaimage">
                    <img id="inputCaptchaImage" data-src="{$systemurl}includes/verifyimage.php" src="{$systemurl}includes/verifyimage.php" alt="Captcha Image" />
                </div>

                <div class="w-1/2">
                    <input 
                        id="inputCaptcha" 
                        type="text" 
                        name="code" 
                        maxlength="6" 
                        class="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-600 {if $filename == 'register'}float-left{/if}"
                        data-toggle="tooltip" 
                        data-placement="right" 
                        data-trigger="manual" 
                        title="{lang key='orderForm.required'}"
                        placeholder="{lang key='enterCaptcha'}"
                    />
                </div>
            </div>
        </div>
    {/if}

    {if $templatefile == 'homepage'}
        </div>
    {/if}
</div>
{/if}
