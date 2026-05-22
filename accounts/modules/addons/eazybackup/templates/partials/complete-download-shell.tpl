{assign var=completeBrandName value=$completeBrandName|default:'eazyBackup'}
{assign var=completeClientLabel value=$completeClientLabel|default:$completeBrandName}
{assign var=completeHeading value=$completeHeading|default:"Your new {$completeBrandName} account is ready"}
{assign var=completeIntro value=$completeIntro|default:"Install the {$completeClientLabel} client on the device you want to back up. Use the Download button below to pick your platform — Windows, Linux, macOS, and Synology installers are all available."}
{assign var=completeAccentClass value=$completeAccentClass|default:'eb-btn-primary'}

<div class="eb-page">
    <div class="eb-page-inner !max-w-4xl">
        <div class="eb-panel">
            <div class="mx-auto max-w-3xl text-center">
                <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-[radial-gradient(circle_at_top,_var(--eb-accent),_var(--eb-brand-orange))] text-white shadow-[var(--eb-shadow-lg)]">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-12 w-12">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>

                <h1 class="eb-page-title mt-6">{$completeHeading}</h1>
                <p class="eb-page-description mx-auto mt-3 max-w-2xl text-base">{$completeIntro}</p>

                <section class="eb-subpanel mt-8 text-left">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div class="eb-badge eb-badge--success">Account Ready</div>
                            <h2 class="eb-app-card-title mt-3">Next step: install the backup client</h2>
                            <p class="mt-2 text-sm text-[var(--eb-text-secondary)]">
                                Click <strong>Download Backup Client</strong> to open the download menu. You'll find installers for every supported platform, along with copy-as-cURL and wget commands for unattended installs.
                            </p>
                        </div>
                        <div class="flex flex-wrap justify-center gap-2 lg:justify-end">
                            <button type="button"
                                    id="eb-complete-open-downloads"
                                    class="eb-btn eb-btn-md {$completeAccentClass}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="mr-2 inline-block h-5 w-5 align-text-bottom">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                                Download Backup Client
                            </button>
                            <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=knowledgebase"
                               class="eb-btn eb-btn-md eb-btn-secondary">Knowledge Base</a>
                            <a href="{$WEB_ROOT}/supporttickets.php"
                               class="eb-btn eb-btn-md eb-btn-ghost">Contact Support</a>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var trigger = document.getElementById('eb-complete-open-downloads');
    if (!trigger) {
        return;
    }
    trigger.addEventListener('click', function (event) {
        event.preventDefault();
        // Stop our click from bubbling to the document-level "outside click"
        // listener in header.tpl, which would otherwise immediately close the
        // flyout that we're about to open.
        event.stopPropagation();

        var flyoutButton = document.getElementById('download-backup-client-button');
        if (flyoutButton) {
            // Defer to the next tick so our original click event has fully
            // finished propagating before the synthetic click opens the flyout.
            window.setTimeout(function () {
                flyoutButton.click();
            }, 0);
            return;
        }
        // Fallback: if the canonical sidebar flyout isn't present on this page,
        // send the user to the dedicated download page so they're never stranded.
        window.location.href = 'index.php?m=eazybackup&a=download';
    });
})();
</script>
