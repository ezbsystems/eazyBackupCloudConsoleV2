<div class="eb-page">
    <div class="eb-page-inner py-8">
        <div class="mx-auto max-w-6xl">
            <div class="eb-panel">
                <div class="eb-page-header">
                    <div>
                        <div class="eb-type-eyebrow">Cloud Storage</div>
                        <h1 class="eb-page-title mt-2">Create Your e3 Storage Account</h1>
                        <p class="eb-page-description">
                            Provision your S3-compatible e3 object storage account and unlock buckets, access keys, usage analytics, and billing controls in the client area.
                        </p>
                    </div>
                    <div class="eb-badge eb-badge--orange">Provisioning Required</div>
                </div>

                {if $status eq 'fail'}
                    <div class="eb-alert eb-alert--danger">
                        <div>
                            <div class="eb-alert-title">Cloud Storage is not ready yet</div>
                            <div>The previous setup attempt did not complete. Review the billing terms, then try provisioning the account again.</div>
                        </div>
                    </div>
                {/if}

                <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(22rem,0.8fr)]">
                    <section class="space-y-6">
                        <div class="eb-card-raised">
                            <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
                                <div class="min-w-0 flex-1">
                                    <img
                                        src="{$WEB_ROOT}/resources/images/eazybackup_e3_light.svg"
                                        class="h-auto w-full max-w-[16rem]"
                                        alt="eazyBackup e3"
                                    >
                                    <p class="eb-type-body mt-6 max-w-2xl">
                                        e3 is a high-performance S3-compatible object storage service. Bring your own backup software, media workflows, and automation to store, manage, and access data securely on scalable infrastructure.
                                    </p>
                                </div>                             
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="eb-stat-card">
                                <div class="eb-stat-label">Minimum Charge</div>
                                <div class="eb-stat-value">1 TiB</div>
                                <div class="eb-type-caption mt-2">$9 CAD per month for the first tebibyte of protected storage.</div>
                            </div>
                            <div class="eb-stat-card">
                                <div class="eb-stat-label">Additional Storage</div>
                                <div class="eb-stat-value">$0.00878</div>
                                <div class="eb-type-caption mt-2">Per GiB beyond the included first 1 TiB, equivalent to $9 per TiB.</div>
                            </div>
                            <div class="eb-stat-card">
                                <div class="eb-stat-label">Included Egress</div>
                                <div class="eb-stat-value">3x</div>
                                <div class="eb-type-caption mt-2">Monthly outbound transfer is included up to three times your maximum monthly stored data.</div>
                            </div>
                        </div>

                        {* <div class="grid gap-4 lg:grid-cols-2">
                            <div class="eb-card">
                                <div class="eb-card-header">
                                    <div>
                                        <h2 class="eb-card-title">What You Get</h2>
                                        <p class="eb-card-subtitle">Provisioning creates the storage identity used across the Cloud Storage workspace.</p>
                                    </div>
                                </div>
                                <div class="eb-richtext">
                                    <ul class="list-disc space-y-2 pl-5">
                                        <li>S3-compatible object storage backed by the e3 platform.</li>
                                        <li>Access to buckets, access keys, usage reporting, and billing views.</li>
                                        <li>Compatibility with standard S3 tooling, SDKs, and backup platforms.</li>
                                        <li>A billing profile aligned with your existing WHMCS client account.</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="eb-card">
                                <div class="eb-card-header">
                                    <div>
                                        <h2 class="eb-card-title">Before You Continue</h2>
                                        <p class="eb-card-subtitle">Provisioning is immediate and enables monthly billing for the storage account.</p>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <div class="flex items-start gap-3">
                                        <div class="eb-badge eb-badge--default">1</div>
                                        <div>
                                            <div class="text-sm font-semibold text-[var(--eb-text-primary)]">Confirm the billing terms</div>
                                            <p class="eb-type-caption mt-1">The account starts with a 1 TiB minimum monthly charge and scales with storage beyond that threshold.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <div class="eb-badge eb-badge--default">2</div>
                                        <div>
                                            <div class="text-sm font-semibold text-[var(--eb-text-primary)]">Review the generated username</div>
                                            <p class="eb-type-caption mt-1">Your storage username is derived from the primary email on your WHMCS account to keep provisioning consistent.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <div class="eb-badge eb-badge--default">3</div>
                                        <div>
                                            <div class="text-sm font-semibold text-[var(--eb-text-primary)]">Create the account</div>
                                            <p class="eb-type-caption mt-1">Once complete, this page will redirect into the Cloud Storage dashboard.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div> *}
                    </section>

                    <section class="eb-card-raised">
                        <div class="eb-card-header">
                            <div>
                                <h2 class="eb-card-title">Provision Storage Access</h2>                                
                            </div>
                        </div>

                        <div id="responseMessage" class="hidden eb-alert" aria-live="polite">
                            <div>
                                <div id="responseMessageTitle" class="eb-alert-title"></div>
                                <div id="responseMessageBody"></div>
                            </div>
                        </div>

                        <form id="s3StorageForm" class="space-y-6">
                            
                            <div class="eb-subpanel space-y-4">
                                <div class="flex items-start gap-3">
                                    <input
                                        id="agreeTerms"
                                        name="agreeTerms"
                                        type="checkbox"
                                        class="mt-1 h-4 w-4 rounded border-[var(--eb-border-default)] bg-[var(--eb-bg-input)] text-[var(--eb-brand-orange)]"
                                        required
                                    >
                                    <div class="space-y-2 text-sm text-[var(--eb-text-secondary)]">
                                        <label for="agreeTerms" class="block">
                                            I have read and agree to the billing terms for this storage account.
                                        </label>
                                        <button type="button" id="openModalButton" class="font-semibold text-[var(--eb-brand-orange)] transition-colors hover:text-[var(--eb-accent)]">
                                            Review billing terms
                                        </button>
                                    </div>
                                </div>
                                <p id="agreeError" class="hidden eb-field-error">You must agree to the billing terms before creating the account.</p>
                            </div>

                            <div class="flex flex-col gap-3 sm:flex-row">
                                <button
                                    type="submit"
                                    id="createS3StorageAccount"
                                    class="eb-btn eb-btn-primary eb-btn-md w-full justify-center sm:flex-1"
                                    disabled
                                >
                                    Create Account
                                </button>
                                <button
                                    type="button"
                                    id="openModalButtonSecondary"
                                    class="eb-btn eb-btn-secondary eb-btn-md w-full justify-center sm:w-auto"
                                >
                                    Review Billing Terms
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{$WEB_ROOT}/modules/addons/eazybackup/templates/assets/js/ui.js"></script>

<div
    id="billingModal"
    class="hidden fixed inset-0 z-50 flex items-center justify-center px-4 eb-modal-backdrop"
    aria-labelledby="billing-modal-title"
    role="dialog"
    aria-modal="true"
>
    <div class="eb-modal max-w-3xl">
        <div class="eb-modal-header">
            <div>
                <h2 id="billing-modal-title" class="eb-modal-title">Billing Terms</h2>
                <p class="eb-modal-subtitle">Review the pricing model for the e3 object storage account before provisioning.</p>
            </div>
            <button id="closeModalButton" type="button" class="eb-modal-close" aria-label="Close">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="eb-modal-body">
            <div class="eb-richtext space-y-4">
                <p>
                    By proceeding with the registration and creating an account, you agree to the following billing terms:
                </p>
                <ul class="list-disc space-y-2 pl-5">
                    <li>You will be charged a minimum of 1 TiB / $9 CAD at the end of your first month.</li>
                    <li>Additional storage used beyond the initial 1 TiB will be billed at $0.00878 per GiB.</li>
                    <li>Free egress is included up to 3x your monthly maximum storage, with any additional egress priced at $0.01 per GB.</li>
                </ul>
                <p>
                    If you have questions about the service or pricing model, contact support before creating the account.
                </p>
            </div>
        </div>
        <div class="eb-modal-footer">
            <button id="closeModalFooterButton" type="button" class="eb-btn eb-btn-secondary eb-btn-sm">
                Close
            </button>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function() {
        var alertStateClasses = 'eb-alert--success eb-alert--danger eb-alert--warning eb-alert--info';
        var createButton = jQuery('#createS3StorageAccount');
        var createButtonLabel = jQuery.trim(createButton.text());

        function clearAlert() {
            jQuery('#responseMessage')
                .addClass('hidden')
                .removeClass(alertStateClasses)
                .attr('aria-hidden', 'true');
            jQuery('#responseMessageTitle').text('');
            jQuery('#responseMessageBody').text('');
        }

        function showAlert(type, title, message) {
            var stateClass = 'eb-alert--info';

            if (type === 'success') {
                stateClass = 'eb-alert--success';
            } else if (type === 'danger') {
                stateClass = 'eb-alert--danger';
            } else if (type === 'warning') {
                stateClass = 'eb-alert--warning';
            }

            jQuery('#responseMessage')
                .removeClass('hidden ' + alertStateClasses)
                .addClass(stateClass)
                .attr('aria-hidden', 'false');
            jQuery('#responseMessageTitle').text(title);
            jQuery('#responseMessageBody').text(message);
        }

        function setSubmissionState(isBusy) {
            createButton.prop('disabled', isBusy || !jQuery('#agreeTerms').is(':checked'));
            createButton.text(isBusy ? 'Creating Account...' : createButtonLabel);
        }

        function showBillingModal() {
            jQuery('#billingModal').removeClass('hidden');
            jQuery('body').addClass('overflow-hidden');
        }

        function hideBillingModal() {
            jQuery('#billingModal').addClass('hidden');
            jQuery('body').removeClass('overflow-hidden');
        }

        jQuery('#openModalButton, #openModalButtonSecondary').click(function(e) {
            e.preventDefault();
            showBillingModal();
        });

        jQuery('#closeModalButton, #closeModalFooterButton').click(function() {
            hideBillingModal();
        });

        jQuery('#billingModal').click(function(e) {
            if (e.target === this) {
                hideBillingModal();
            }
        });

        jQuery(document).keydown(function(e) {
            if (e.key === 'Escape' && !jQuery('#billingModal').hasClass('hidden')) {
                hideBillingModal();
            }
        });

        jQuery('#agreeTerms').change(function() {
            jQuery('#agreeError').addClass('hidden');
            setSubmissionState(false);
        });

        jQuery('#s3StorageForm').submit(function(e) {
            e.preventDefault();
            clearAlert();

            if (!jQuery('#agreeTerms').is(':checked')) {
                jQuery('#agreeError').removeClass('hidden');
                return;
            }

            setSubmissionState(true);

            try {
                if (window.ebShowLoader) {
                    window.ebShowLoader(document.body, 'Creating account...');
                }
            } catch (_) {}

            jQuery.ajax({
                type: 'POST',
                url: 'modules/addons/cloudstorage/api/createS3StorageAccount.php',
                data: { username: jQuery('#username').val() },
                success: function(response) {
                    try {
                        if (window.ebHideLoader) {
                            window.ebHideLoader(document.body);
                        }
                    } catch (_) {}

                    if (response.status === 'fail') {
                        showAlert('danger', 'Provisioning failed', response.message);
                        setSubmissionState(false);
                        return;
                    }

                    showAlert('success', 'Storage account created', response.message);
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                },
                error: function(xhr) {
                    var errorMessage = 'An unexpected error occurred while creating the storage account.';

                    try {
                        if (window.ebHideLoader) {
                            window.ebHideLoader(document.body);
                        }
                    } catch (_) {}

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        errorMessage = xhr.responseText;
                    }

                    showAlert('danger', 'Provisioning failed', errorMessage);
                    setSubmissionState(false);
                }
            });
        });

        setSubmissionState(false);
        clearAlert();
    });
</script>
