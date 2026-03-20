{assign var=completeBrandName value=$completeBrandName|default:'eazyBackup'}
{assign var=completeDownloadHost value=$completeDownloadHost|default:'csw.eazybackup.ca'}
{assign var=completeSelfAddress value=$completeSelfAddress|default:'https://csw.eazybackup.ca/'}
{assign var=completeClientLabel value=$completeClientLabel|default:$completeBrandName}
{assign var=completeHeading value=$completeHeading|default:"Your new {$completeBrandName} account is ready"}
{assign var=completeIntro value=$completeIntro|default:"Download the {$completeClientLabel} client for your platform using the options below. We're here if you need any assistance."}
{assign var=completeAccentClass value=$completeAccentClass|default:'eb-btn-primary'}

<div x-data="ebCompleteDownloadPage({
        host: '{$completeDownloadHost|escape:'javascript'}',
        selfAddress: '{$completeSelfAddress|escape:'javascript'}'
    })"
    @keydown.escape.window="openModal = null"
    class="eb-page">
    <div class="eb-page-inner !max-w-6xl">
        <div class="eb-panel">
            <div class="mx-auto max-w-4xl text-center">
                <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-[radial-gradient(circle_at_top,_var(--eb-accent),_var(--eb-brand-orange))] text-white shadow-[var(--eb-shadow-lg)]">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-12 w-12">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>

                <h1 class="eb-page-title mt-6">{$completeHeading}</h1>
                <p class="eb-page-description mx-auto mt-3 max-w-2xl text-base">{$completeIntro}</p>

                <section class="eb-subpanel mt-8">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="text-left">
                            <div class="eb-badge eb-badge--success">Account Ready</div>
                            <h2 class="eb-app-card-title mt-3">Download client software</h2>
                            <p class="mt-2 text-sm text-[var(--eb-text-secondary)]">Choose a platform to view installers, command-line download options, and minimum requirements.</p>
                        </div>
                        <div class="flex flex-wrap justify-center gap-2 lg:justify-end">
                            <button type="button" @click="openModal = 'windows'" class="eb-btn eb-btn-md {$completeAccentClass}">Windows</button>
                            <button type="button" @click="openModal = 'linux'" class="eb-btn eb-btn-md {$completeAccentClass}">Linux</button>
                            <button type="button" @click="openModal = 'macos'" class="eb-btn eb-btn-md {$completeAccentClass}">macOS</button>
                            <button type="button" @click="openModal = 'synology'" class="eb-btn eb-btn-md {$completeAccentClass}">Synology</button>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <template x-if="openModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-[color:var(--eb-backdrop-modal)] p-4">
            <div @click.away="openModal = null" class="eb-subpanel relative w-full max-w-3xl shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-[var(--eb-border-default)] pb-4">
                    <div>
                        <div class="eb-badge eb-badge--orange" x-text="platformTitle(openModal)"></div>
                        <h2 class="eb-app-card-title mt-3">Download client software</h2>
                    </div>
                    <button type="button" @click="openModal = null" class="eb-btn eb-btn-ghost eb-btn-sm">Close</button>
                </div>

                <div class="mt-6 space-y-6">
                    <section x-show="openModal === 'windows'" x-cloak>
                        <div class="flex flex-wrap gap-2">
                            <span class="eb-badge eb-badge--neutral">Desktop app</span>
                            <span class="eb-badge eb-badge--neutral">Command-line client</span>
                            <span class="eb-badge eb-badge--neutral">Web-managed</span>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a class="eb-btn eb-btn-md {$completeAccentClass}" :href="downloadHref(1)">Any CPU</a>
                            <a class="eb-btn eb-btn-md eb-btn-secondary" :href="downloadHref(5)">x86_64 only</a>
                            <a class="eb-btn eb-btn-md eb-btn-secondary" :href="downloadHref(3)">x86_32 only</a>
                        </div>
                        <div class="mt-5">
                            <h3 class="eb-field-label">System Requirements</h3>
                            <ul class="space-y-2 text-sm text-[var(--eb-text-secondary)]">
                                <li>CPU: x86_64 or x86_32 (+SSE2)</li>
                                <li>Screen resolution: 1024x600</li>
                                <li>Operating system: Windows 7, Windows Server 2008 R2, or newer</li>
                            </ul>
                        </div>
                    </section>

                    <section x-show="openModal === 'linux'" x-cloak>
                        <div class="flex flex-wrap gap-2">
                            <span class="eb-badge eb-badge--neutral">Desktop app</span>
                            <span class="eb-badge eb-badge--neutral">Command-line client</span>
                            <span class="eb-badge eb-badge--neutral">Web-managed</span>
                        </div>

                        <div class="mt-4 grid gap-4">
                            <div class="rounded-lg border border-[var(--eb-border-default)] bg-[var(--eb-surface-elevated)] p-4">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                    <div>
                                        <h3 class="eb-app-card-title">.deb installer</h3>
                                        <p class="mt-1 text-sm text-[var(--eb-text-secondary)]">Download directly or copy a command for unattended installation.</p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <a class="eb-btn eb-btn-sm {$completeAccentClass}" :href="downloadHref(21)">Download</a>
                                        <button type="button" class="eb-btn eb-btn-sm eb-btn-secondary" @click="copyCommand('linux-deb-curl', curlCommand(21))" x-text="copied === 'linux-deb-curl' ? 'Copied cURL' : 'Copy as cURL'"></button>
                                        <button type="button" class="eb-btn eb-btn-sm eb-btn-secondary" @click="copyCommand('linux-deb-wget', wgetCommand(21))" x-text="copied === 'linux-deb-wget' ? 'Copied wget' : 'Copy as wget'"></button>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-lg border border-[var(--eb-border-default)] bg-[var(--eb-surface-elevated)] p-4">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                    <div>
                                        <h3 class="eb-app-card-title">.tar.gz installer</h3>
                                        <p class="mt-1 text-sm text-[var(--eb-text-secondary)]">Use this package on distributions where a tarball install is preferred.</p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <a class="eb-btn eb-btn-sm {$completeAccentClass}" :href="downloadHref(7)">Download</a>
                                        <button type="button" class="eb-btn eb-btn-sm eb-btn-secondary" @click="copyCommand('linux-tgz-curl', curlCommand(7))" x-text="copied === 'linux-tgz-curl' ? 'Copied cURL' : 'Copy as cURL'"></button>
                                        <button type="button" class="eb-btn eb-btn-sm eb-btn-secondary" @click="copyCommand('linux-tgz-wget', wgetCommand(7))" x-text="copied === 'linux-tgz-wget' ? 'Copied wget' : 'Copy as wget'"></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="eb-field-label">System Requirements</h3>
                            <ul class="space-y-2 text-sm text-[var(--eb-text-secondary)]">
                                <li>CPU: x86_64, x86_32 (+SSE2), ARM 32 (v6kl/v7l +vfp), or ARM 64</li>
                                <li>Operating system: Ubuntu 16.04+, Debian 9+, CentOS 7+, Fedora 30+</li>
                            </ul>
                        </div>
                    </section>

                    <section x-show="openModal === 'macos'" x-cloak>
                        <div class="flex flex-wrap gap-2">
                            <span class="eb-badge eb-badge--neutral">Desktop app</span>
                            <span class="eb-badge eb-badge--neutral">Command-line client</span>
                            <span class="eb-badge eb-badge--neutral">Web-managed</span>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a class="eb-btn eb-btn-md {$completeAccentClass}" :href="downloadHref(8)">x86_64</a>
                            <a class="eb-btn eb-btn-md eb-btn-secondary" :href="downloadHref(20)">Apple Silicon</a>
                        </div>
                        <div class="mt-5">
                            <h3 class="eb-field-label">System Requirements</h3>
                            <ul class="space-y-2 text-sm text-[var(--eb-text-secondary)]">
                                <li>CPU: Intel or Apple Silicon</li>
                                <li>Screen resolution: 1024x600</li>
                                <li>Operating system: macOS 10.12 or newer</li>
                            </ul>
                        </div>
                    </section>

                    <section x-show="openModal === 'synology'" x-cloak>
                        <div class="flex flex-wrap gap-2">
                            <span class="eb-badge eb-badge--neutral">Command-line client</span>
                            <span class="eb-badge eb-badge--neutral">Web-managed</span>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a class="eb-btn eb-btn-md {$completeAccentClass}" :href="downloadHref(18)">DSM 6</a>
                            <a class="eb-btn eb-btn-md eb-btn-secondary" :href="downloadHref(19)">DSM 7</a>
                        </div>
                        <div class="mt-5">
                            <h3 class="eb-field-label">System Requirements</h3>
                            <ul class="space-y-2 text-sm text-[var(--eb-text-secondary)]">
                                <li>Operating system: DSM 6 or DSM 7</li>
                                <li>CPU: x86_64, x86_32, ARMv7, or ARMv8</li>
                            </ul>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
function ebCompleteDownloadPage(config) {
    return {
        openModal: null,
        copied: null,
        platformTitle(platform) {
            if (platform === 'windows') return 'Windows';
            if (platform === 'linux') return 'Linux';
            if (platform === 'macos') return 'macOS';
            if (platform === 'synology') return 'Synology';
            return 'Downloads';
        },
        downloadHref(platformId) {
            return 'https://' + config.host + '/dl/' + platformId;
        },
        curlCommand(platformId) {
            return "curl -O -J -d 'SelfAddress=" + encodeURIComponent(config.selfAddress) + "&Platform=" + platformId + "' -X POST 'https://" + config.host + "/api/v1/admin/branding/generate-client/by-platform'";
        },
        wgetCommand(platformId) {
            return "wget --content-disposition --post-data 'SelfAddress=" + encodeURIComponent(config.selfAddress) + "&Platform=" + platformId + "' 'https://" + config.host + "/api/v1/admin/branding/generate-client/by-platform'";
        },
        copyCommand(key, text) {
            var done = () => {
                this.copied = key;
                window.setTimeout(() => {
                    if (this.copied === key) {
                        this.copied = null;
                    }
                }, 2000);
            };

            if (!navigator.clipboard) {
                var textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                    done();
                } catch (err) {
                    console.error('Fallback copy failed', err);
                }
                document.body.removeChild(textArea);
                return;
            }

            navigator.clipboard.writeText(text).then(done, function (err) {
                console.error('Clipboard copy failed', err);
            });
        }
    };
}
</script>
