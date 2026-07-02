{assign var=ebEnableProductChoice value=$ebEnableProductChoice|default:'e3backup'}
{assign var=ebEnablePageTitle value=$ebEnablePageTitle|default:'Enable backup product'}
{assign var=ebEnablePageDescription value=$ebEnablePageDescription|default:''}

<div class="eb-card-raised max-w-2xl"
     x-data="ebEnableProductForm('{$ebEnableProductChoice|escape:'javascript'}')"
     x-init="init()">
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
                <div class="eb-alert-title">Billing</div>
                <p class="eb-type-body">
                    A new backup product will be added to your account. Usage is metered monthly on your existing invoice.
                </p>
            </div>
        </div>

        <div x-show="generalError" x-cloak class="eb-alert eb-alert--danger" role="alert">
            <div x-text="generalError"></div>
        </div>

        <form @submit.prevent="submit()" class="space-y-5">
            <input type="hidden" name="product_choice" :value="productChoice">
            <input type="hidden" name="existing_client" value="1">

            <div>
                <label for="eb-enable-username" class="eb-field-label">Backup agent username</label>
                <input id="eb-enable-username"
                       type="text"
                       x-model.trim="username"
                       autocomplete="username"
                       class="eb-input w-full"
                       :class="errors.username && 'is-error'"
                       placeholder="Choose a username (a-z, 0-9, ., _, -)">
                <p class="eb-field-help">Minimum 6 characters. You will sign in from the backup agent using this username.</p>
                <p class="eb-field-error" x-show="errors.username" x-text="errors.username"></p>
            </div>

            <div>
                <label for="eb-enable-portal-password" class="eb-field-label">Confirm your portal password</label>
                <input id="eb-enable-portal-password"
                       type="password"
                       x-model="password"
                       autocomplete="current-password"
                       class="eb-input w-full"
                       :class="errors.new_password && 'is-error'"
                       placeholder="Your eazyBackup portal password">
                <p class="eb-field-help">Re-enter the password you use to sign in to this portal. Your backup agent will use the same password.</p>
                <p class="eb-field-error" x-show="errors.new_password" x-text="errors.new_password"></p>
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-2">
                <button type="submit" class="eb-btn eb-btn-orange eb-btn-md" :disabled="submitting">
                    <span x-show="!submitting">Enable backup</span>
                    <span x-show="submitting">Provisioning…</span>
                </button>
                <a href="index.php?m=cloudstorage&page=e3backup&view=dashboard" class="eb-btn eb-btn-secondary eb-btn-md">Cancel</a>
            </div>
        </form>
    </div>
</div>

{literal}
<script>
function ebEnableProductForm(productChoice) {
    return {
        productChoice: productChoice || 'e3backup',
        username: '',
        password: '',
        submitting: false,
        generalError: '',
        errors: {},
        init() {},
        async submit() {
            this.generalError = '';
            this.errors = {};
            this.submitting = true;
            try {
                const body = new URLSearchParams();
                body.set('product_choice', this.productChoice);
                body.set('existing_client', '1');
                body.set('username', this.username);
                body.set('new_password', this.password);
                const res = await fetch('modules/addons/cloudstorage/api/setpassword_and_provision.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString(),
                });
                const data = await res.json();
                if (data && data.status === 'success' && data.redirectUrl) {
                    window.location.href = data.redirectUrl;
                    return;
                }
                if (data && data.errors) {
                    this.errors = data.errors;
                    this.generalError = data.errors.general || '';
                } else {
                    this.generalError = (data && data.message) ? data.message : 'Provisioning failed. Please try again.';
                }
            } catch (_) {
                this.generalError = 'Provisioning failed. Please try again.';
            } finally {
                this.submitting = false;
            }
        },
    };
}
</script>
{/literal}
