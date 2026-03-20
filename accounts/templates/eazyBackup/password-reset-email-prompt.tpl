        <div class="mb-8">
            <h6 class="eb-auth-title mb-4">{lang key='pwreset'}</h6>
            <p class="eb-auth-description">{lang key='pwresetemailneeded'}</p>
        </div>

        <!-- Error Message -->
        {if $errorMessage}
            {include file="$template/includes/alert-darkmode.tpl" type="error" msg=$errorMessage textcenter=true}
        {/if}

        <!-- Form Section -->
        <form method="post" action="{routePath('password-reset-validate-email')}" role="form" class="space-y-6">
            <input type="hidden" name="action" value="reset" />

            <!-- Email Input Field -->
            <div>
                <label for="inputEmail" class="eb-field-label">{lang key='loginemail'}</label>
                <div class="eb-input-wrap">
                    <span class="eb-input-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                  
                    </span>
                    <input 
                        type="email" 
                        name="email" 
                        id="inputEmail" 
                        placeholder="name@example.com" 
                        autofocus 
                        class="eb-input eb-input-has-icon"
                    />
                </div>
            </div>

            <!-- Captcha Section -->
            {if $captcha->isEnabled()}
                <div class="text-center">
                    {include file="$template/includes/captcha.tpl"}
                </div>
            {/if}

            <!-- Submit Button -->
            <div class="text-center">
                <button 
                    type="submit" 
                    class="eb-btn eb-btn-primary w-full rounded-full py-2.5"
                >
                    {lang key='pwresetsubmit'}
                </button>
            </div>
        </form>
