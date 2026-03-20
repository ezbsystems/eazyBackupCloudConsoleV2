<div class="eb-page">
    <div class="eb-page-inner !max-w-7xl">
        <div class="eb-panel !overflow-hidden !p-0">
            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,37fr)_minmax(0,63fr)]">
                <section class="relative overflow-hidden border-b border-[var(--eb-border-default)] bg-[linear-gradient(160deg,rgba(7,13,27,0.98)_0%,rgba(17,29,51,0.98)_55%,rgba(23,32,53,0.98)_100%)] px-6 py-10 text-[var(--eb-text-inverse)] sm:px-8 lg:border-b-0 lg:border-r lg:px-8 lg:py-12">
                    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(254,80,0,0.18),transparent_32%),radial-gradient(circle_at_20%_80%,rgba(59,130,246,0.12),transparent_30%)]"></div>

                    <div class="relative lg:max-w-md">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="eb-badge eb-badge--orange">Partner Program</div>
                            <div class="eb-badge eb-badge--neutral">No contracts</div>
                            <div class="eb-badge eb-badge--neutral">Canadian hosted</div>
                        </div>

                        <h1 class="mt-6 font-[var(--eb-font-display)] text-3xl font-semibold leading-tight text-white sm:text-4xl">
                            Sell backup. Start today.
                        </h1>
                        <p class="mt-5 text-base leading-relaxed text-slate-300">
                            Built for MSPs — Canadian-hosted, compliance-ready, and self-serve from day one.
                        </p>

                        <ul class="mt-8 space-y-5">
                            <li class="flex gap-3">
                                <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-[var(--eb-brand-orange)]" aria-hidden="true"></span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-white">No Lock-In</p>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-300">Start with one client. No storage floors or volume commitments.</p>
                                </div>
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-[var(--eb-brand-orange)]" aria-hidden="true"></span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-white">Canadian-Hosted</p>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-300">Data stays in Canada. Built for sovereignty and regulatory requirements.</p>
                                </div>
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-[var(--eb-brand-orange)]" aria-hidden="true"></span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-white">One Control Plane</p>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-300">Monitor backup health and manage all clients from a single dashboard.</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </section>

                <section class="bg-[var(--eb-bg-card)] px-6 py-10 sm:px-8">
                    <div class="mx-auto max-w-2xl">
                        <div class="eb-breadcrumb mb-3">
                            <span class="eb-breadcrumb-current text-xs font-semibold uppercase tracking-wide text-[var(--eb-text-muted)]">Partner Signup</span>
                        </div>
                        <h2 class="eb-page-title !text-[2.2rem]">Become an eazyBackup Partner</h2>
                        <p class="eb-page-description !mt-3 text-sm">
                            Create your account and start onboarding clients today.
                        </p>

                        {if !empty($errors.error)}
                            {include file="templates/eazyBackup/includes/ui/eb-alert.tpl"
                                ebAlertType="danger"
                                ebAlertTitle="Unable to create account"
                                ebAlertMessage=$errors.error
                                ebAlertClass="mt-6"
                            }
                        {/if}

                        <form id="reseller" method="post" action="{$modulelink}&a=reseller" class="mt-8 space-y-6">
                            <div class="eb-subpanel">
                                <div class="eb-section-intro">
                                    <h3 class="eb-section-title">Contact Details</h3>
                                    <p class="eb-section-description">Tell us about you and your company.</p>
                                </div>

                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <label for="firstname" class="eb-field-label">First Name</label>
                                        <input type="text" id="firstname" name="firstname" value="{$POST.firstname|default:''|escape}" class="eb-input{if !empty($errors.firstname)} is-error{/if}">
                                        {if !empty($errors.firstname)}
                                            <p class="mt-1 text-sm text-[var(--eb-danger-text)]">{$errors.firstname}</p>
                                        {/if}
                                    </div>
                                    <div>
                                        <label for="lastname" class="eb-field-label">Last Name</label>
                                        <input type="text" id="lastname" name="lastname" value="{$POST.lastname|default:''|escape}" class="eb-input{if !empty($errors.lastname)} is-error{/if}">
                                        {if !empty($errors.lastname)}
                                            <p class="mt-1 text-sm text-[var(--eb-danger-text)]">{$errors.lastname}</p>
                                        {/if}
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="companyname" class="eb-field-label">Company</label>
                                        <input type="text" id="companyname" name="companyname" value="{$POST.companyname|default:''|escape}" class="eb-input{if !empty($errors.companyname)} is-error{/if}">
                                        {if !empty($errors.companyname)}
                                            <p class="mt-1 text-sm text-[var(--eb-danger-text)]">{$errors.companyname}</p>
                                        {/if}
                                    </div>
                                    <div>
                                        <label for="email" class="eb-field-label">Email Address</label>
                                        <input type="email" id="email" name="email" value="{$POST.email|default:''|escape}" class="eb-input{if !empty($errors.email)} is-error{/if}">
                                        {if !empty($errors.email)}
                                            <p class="mt-1 text-sm text-[var(--eb-danger-text)]">{$errors.email}</p>
                                        {/if}
                                    </div>
                                    <div>
                                        <label for="phonenumber" class="eb-field-label">Phone Number</label>
                                        <input type="tel" id="phonenumber" name="phonenumber" placeholder="123-456-7890" value="{$POST.phonenumber|default:''|escape}" class="eb-input{if !empty($errors.phonenumber)} is-error{/if}">
                                        {if !empty($errors.phonenumber)}
                                            <p class="mt-1 text-sm text-[var(--eb-danger-text)]">{$errors.phonenumber}</p>
                                        {/if}
                                    </div>
                                </div>
                            </div>

                            <div class="eb-subpanel">
                                <div class="eb-section-intro">
                                    <h3 class="eb-section-title">Account Security</h3>
                                    <p class="eb-section-description">Choose a password for your partner account.</p>
                                </div>

                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <label for="password" class="eb-field-label">Password</label>
                                        <input type="password" id="password" name="password" class="eb-input{if !empty($errors.password)} is-error{/if}">
                                        {if !empty($errors.password)}
                                            <p class="mt-1 text-sm text-[var(--eb-danger-text)]">{$errors.password}</p>
                                        {/if}
                                    </div>
                                    <div>
                                        <label for="confirmpassword" class="eb-field-label">Confirm Password</label>
                                        <input type="password" id="confirmpassword" name="confirmpassword" class="eb-input{if !empty($errors.confirmpassword)} is-error{/if}">
                                        {if !empty($errors.confirmpassword)}
                                            <p class="mt-1 text-sm text-[var(--eb-danger-text)]">{$errors.confirmpassword}</p>
                                        {/if}
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-[var(--eb-border-default)] bg-[var(--eb-surface-elevated)] px-5 py-4 text-center text-sm leading-7 text-[var(--eb-text-secondary)]">
                                By signing up you agree to the
                                <a href="https://eazybackup.com/terms/" target="_top" class="font-medium text-[var(--eb-brand-orange)] underline underline-offset-2 hover:text-[var(--eb-primary-hover)]">Terms of Service</a>
                                and
                                <a href="https://eazybackup.com/privacy/" target="_top" class="font-medium text-[var(--eb-brand-orange)] underline underline-offset-2 hover:text-[var(--eb-primary-hover)]">Privacy Policy</a>.
                            </div>

                            <div class="flex flex-col gap-3">
                                <button type="submit" class="eb-btn eb-btn-primary eb-btn-lg w-full justify-center">Create Partner Account</button>                                
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var phoneInput = document.getElementById('phonenumber');
    if (!phoneInput) {
        return;
    }

    var formatPhone = function (value) {
        var digits = String(value || '').replace(/\D/g, '').slice(0, 10);
        var parts = [];
        if (digits.length > 0) {
            parts.push(digits.slice(0, 3));
        }
        if (digits.length > 3) {
            parts.push(digits.slice(3, 6));
        }
        if (digits.length > 6) {
            parts.push(digits.slice(6, 10));
        }
        return parts.join('-');
    };

    phoneInput.value = formatPhone(phoneInput.value);
    phoneInput.addEventListener('input', function () {
        phoneInput.value = formatPhone(phoneInput.value);
    });
});
</script>
