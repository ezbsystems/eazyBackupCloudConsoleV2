<div class="eb-page">
    <div class="eb-page-inner !max-w-5xl">
        <div class="eb-panel">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] lg:items-start">
                <section>
                    <div class="eb-badge eb-badge--success mb-4">Microsoft 365 backup account created</div>

                    <div class="flex items-start gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-[radial-gradient(circle_at_top,_var(--eb-accent),_var(--eb-brand-orange))] text-white shadow-[var(--eb-shadow-lg)]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-9 w-9">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>

                        <div>
                            <h1 class="eb-page-title">{$ms365Title|default:'Your Microsoft 365 backup account is ready'}</h1>
                            <p class="eb-page-description">
                                {$ms365Description|default:'Follow these quick steps to sign in and protect your Microsoft 365 data.'}
                            </p>
                        </div>
                    </div>

                    <div class="mt-8 space-y-6">
                        <div class="eb-subpanel">
                            <h2 class="eb-app-card-title">Step 1. Sign in to the Control Panel</h2>
                            <p class="mt-3 text-sm text-[var(--eb-text-secondary)]">
                                Go to
                                <a class="font-medium text-[var(--eb-info-text)] underline underline-offset-2 hover:text-[var(--eb-text-primary)]" href="{$ms365PanelUrl|escape:'html'}" target="_blank" rel="noopener noreferrer">
                                    {$ms365PanelUrl|escape}
                                </a>
                                and sign in with your new account.
                            </p>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-lg border border-[var(--eb-border-default)] bg-[var(--eb-surface-elevated)] px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-[0.12em] text-[var(--eb-text-muted)]">Username</p>
                                    <p class="mt-1 font-medium text-[var(--eb-text-primary)]">{$username|escape}</p>
                                </div>
                                <div class="rounded-lg border border-[var(--eb-border-default)] bg-[var(--eb-surface-elevated)] px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-[0.12em] text-[var(--eb-text-muted)]">Password</p>
                                    <p class="mt-1 text-sm text-[var(--eb-text-secondary)]">Use the password you selected during sign-up.</p>
                                </div>
                            </div>
                        </div>

                        <div class="eb-subpanel">
                            <h2 class="eb-app-card-title">Step 2. Configure Your Backup</h2>
                            <p class="mt-3 text-sm text-[var(--eb-text-secondary)]">
                                Select <span class="font-medium text-[var(--eb-text-primary)]">Protected Items</span> and then
                                <span class="font-medium text-[var(--eb-text-primary)]">Add new Protected Item</span>
                                to set up and customize your Microsoft 365 backup.
                            </p>
                            <p class="mt-3 text-sm text-[var(--eb-text-secondary)]">
                                Watch the step-by-step configuration video for a guided walkthrough. If you need help, the support team can take it from there.
                            </p>
                        </div>
                    </div>
                </section>

                <aside class="eb-subpanel">
                    <h2 class="eb-app-card-title">Microsoft 365 Backup Video Guide</h2>
                    <p class="mt-2 text-sm text-[var(--eb-text-secondary)]">
                        This walkthrough covers tenant connection, initial setup, and the first protected items to configure.
                    </p>

                    <div class="mt-4 overflow-hidden rounded-lg border border-[var(--eb-border-default)] bg-black">
                        <video class="aspect-video w-full" controls>
                            <source src="https://eazybackup.com/wp-content/uploads/2024/05/MS365_Backup_Getting_Started.mp4" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var loginForm = document.getElementById("whmcsLoginForm");
    if (loginForm) {
        loginForm.addEventListener("submit", function() {
            var redirectTo = '/clientarea.php?action=details';
            var hiddenInput = document.createElement("input");
            hiddenInput.type = "hidden";
            hiddenInput.name = "goto";
            hiddenInput.value = redirectTo;
            loginForm.appendChild(hiddenInput);
        });
    }
});
</script>
