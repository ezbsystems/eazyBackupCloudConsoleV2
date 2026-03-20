<!-- accounts\templates\eazyBackup\login.tpl -->
<div class="providerLinkingFeedback"></div>

{capture name=ebLoginContent}
    <form method="post" action="{routePath('login-validate')}" role="form">
        <div class="mb-6">
            <h6 class="eb-auth-title">{lang key='loginheading'}</h6>
        </div>
        {include file="$template/includes/flashmessage-darkmode.tpl"}

        <div>
            <label for="inputEmail" class="eb-field-label">{lang key='clientareaemail'}</label>
            <div class="eb-input-wrap mb-4">
                <span class="eb-input-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                </span>
                <input
                    type="email"
                    name="username"
                    id="inputEmail"
                    placeholder="name@company.com"
                    class="eb-input eb-input-has-icon"
                >
            </div>
        </div>

        <div class="mb-6">
            <div class="flex items-center justify-between">
                <label for="inputPassword" class="eb-field-label">{lang key='clientareapassword'}</label>
            </div>
            <div class="eb-input-wrap mb-4">
                <span class="eb-input-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                    </svg>
                </span>
                <input
                    type="password"
                    name="password"
                    id="inputPassword"
                    placeholder="{lang key='clientareapassword'}"
                    class="eb-input eb-input-has-icon pr-11"
                >
                <button type="button" id="togglePassword" tabindex="-1"
                    class="absolute inset-y-0 right-0 px-3 eb-link-muted focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                </button>
            </div>
        </div>

        {if $captcha->isEnabled()}
            {include file="$template/includes/captcha.tpl"}
        {/if}

        <div class="mb-6 flex items-center justify-between">
            <label class="eb-inline-choice">
                <input type="checkbox" name="rememberme" class="eb-check-input">
                <span>{lang key='loginrememberme'}</span>
            </label>
            <a href="{routePath('password-reset-begin')}" class="eb-link text-sm font-semibold">{lang key='forgotpw'}</a>
        </div>

        <div class="mb-4">
            <button id="login" type="submit" class="eb-btn eb-btn-primary w-full rounded-full py-2.5">
                <span class="mr-2 inline-flex h-6 w-6 items-center justify-center rounded-full bg-black/20" aria-hidden="true">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        class="h-3.5 w-3.5"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M16.5 10.5V8.25A4.25 4.25 0 0 0 8 8.25v2.25m8.5 0h-9a1.75 1.75 0 0 0-1.75 1.75v4.5A1.75 1.75 0 0 0 7.5 20.5h9a1.75 1.75 0 0 0 1.75-1.75v-4.5a1.75 1.75 0 0 0-1.75-1.75Z"
                        />
                    </svg>
                </span>
                <span>{lang key='loginbutton'}</span>
            </button>
        </div>
    </form>
{/capture}

{include file="$template/includes/ui/auth-shell.tpl" ebAuthContent=$smarty.capture.ebLoginContent}

{include file="$template/includes/linkedaccounts.tpl" linkContext="login" customFeedback=true}

<script>
  // Engage: Wait until the DOM is fully loaded.
  document.addEventListener('DOMContentLoaded', () => {
    // Locate our password input and toggle button.
    const passwordInput = document.getElementById('inputPassword');
    const togglePassword = document.getElementById('togglePassword');

    // Listen for a click event on the toggle button.
    togglePassword.addEventListener('click', () => {
      // Toggle the input type.
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
      } else {
        passwordInput.type = 'password';
      }
      
      // (Optional) Log the action to the ship's console for debugging.
      console.log('Password field toggled: ' + passwordInput.type);
    });
  });
</script>
