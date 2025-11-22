<style>
	.loading-dots {
		display: none;
		position: relative;
		width: 60px;
		margin-top: 10px;
	}

	.loading-dots .dot {
		position: absolute;
		width: 10px;
		height: 10px;
		border-radius: 50%;
		background-color: #00082D; /* Change to desired dot color */
		animation: dotFlashing 1s infinite linear alternate;
		animation-delay: 0s;
	}

	.loading-dots .dot:nth-child(2) {
		left: 20px;
		animation-delay: 0.2s;
	}

	.loading-dots .dot:nth-child(3) {
		left: 40px;
		animation-delay: 0.4s;
	}

	.loader {
		display: none;
		position: fixed;
		top: 48%;
		width: 100%;
		height: 100%;
		z-index: 9;
		left: 35%;
		right: 0;
		justify-content: center;
		align-items: center;
		flex-direction: column;
		transform: translate(-50%, -50%);
		border: 3px solid #4F657F;
		border-radius: 50%;
		border-top: 3px solid #C28247;
		width: 25px;
		height: 25px;
		animation: spin 2s linear infinite;
	}

	@keyframes spin {
		0% {
			transform: rotate(0deg);
		}

		100% {
			transform: rotate(360deg);
		}
	}
</style>

<!-- Sign-Up Form Container -->
<div class="flex justify-center items-center min-h-screen bg-gray-700">
	<div class="bg-gray-800 rounded-lg shadow p-8 max-w-xl w-full">
		{* Loading Overlay *}
		<div id="loading-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden">
            <div class="flex items-center">
                <div class="text-gray-300 text-lg">Loading...</div>
                <svg class="animate-spin h-8 w-8 text-gray-300 ml-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
            </div>
        </div>

		<div class="flex flex-col mb-6">
			<img src="{$WEB_ROOT}/resources/images/eazybackup_e3_light.svg" class="text-white w-64 mb-6" alt="eazyBackup e3">
			<p class="text-gray-300 mb-6">
				e3 is a high-performance S3-compatible object storage service, bring your own software to store, manage, and access your data securely with our scalable storage.
			</p>
			<h3 class="font-semibold text-xl text-gray-300 mb-2">Pricing</h3>
			<ul class="list-disc list-inside text-gray-300">
				<li>Minimum monthly charge: 1 TiB / $9 CAD</li>
				<li>Additional storage is billed at $0.00878 per GiB ($9/TiB) beyond the initial 1 TiB. </li>
				<li>Free egress up to 3x your monthly maxiumum storage.</li>
			</ul>
		</div>

		<div class="w-full">
			<form id="s3StorageForm" class="flex flex-col">
				<div class="mb-6">
					<div class="relative">
						<span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-300">
							<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
								<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
							</svg>
						</span>
						<label for="username" class="sr-only">Username:</label>
						<input
							type="text"
							id="username"
							name="username"
							placeholder="Username"
							required
							class="block w-full pl-10 pr-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-blue-600"
						>
					</div>

				</div>
				<!-- Agreement Checkbox -->
				<div class="flex items-start space-x-2 mb-6">
					<div class="flex items-center h-5">
						<input
							id="agreeTerms"
							name="agreeTerms"
							type="checkbox"
							required
							class="h-4 w-4 accent-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
						>
					</div>
					<div class="text-sm">
						<label for="agreeTerms" class="font-medium text-gray-300">
							I have read and agree to the
							<a href="#" id="openModalButton" class="text-sm font-semibold text-blue-500 hover:underline">billing terms</a>
						</label>
						<div id="agreeError" class="mt-4 text-center bg-red-700 hidden">
							<label class="text-gray-300"></label>
						</div>
					</div>
				</div>
				<div class="mb-6">
					<button
						type="submit"
						id="createS3StorageAccount"
						class="items-center w-full px-4 py-2 mb-4 border border-transparent shadow-sm text-md font-semisbold rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
						disabled
					>
						Create Account
					</button>
				</div>
			</form>
		</div>

		<div id="responseMessage" class="mt-4 text-center">
			<p class="text-gray-300"></p>
		</div>
	</div>
</div>

<!-- Billing Terms Modal -->
<div
	id="billingModal"
	class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden lg:ml-64"
	aria-labelledby="modal-title"
	role="dialog"
	aria-modal="true"
>
	<div class="bg-gray-800 rounded-lg w-full max-w-3xl mx-4 relative">
		<div class="flex justify-between items-center bg-orange-600 text-white p-4 rounded-t-lg">
			<h5 class="text-lg font-semibold flex items-center">
				<i class="fas fa-file-contract text-gray-100 mr-2"></i> Billing Terms
			</h5>
			<button id="closeModalButton" class="text-white hover:text-gray-200 focus:outline-none">
				<svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
				</svg>
			</button>
		</div>
		<div class="p-6 overflow-y-auto max-h-96">
			<!-- Billing Terms Content -->
			<h2 class="text-2xl font-bold text-gray-300 mb-4">Billing Terms</h2>
			<p class="text-gray-300 mb-2">
				By proceeding with the registration and creating an account, you agree to the following billing terms:
			</p>
			<ul class="list-disc list-inside text-gray-300 mb-4">
				<li>You will be charged a minimum of 1 TiB / $9 CAD at the end of your first month.</li>
				<li>Additional storage used beyond the initial 1 TiB will be billed at $0.00878 per GiB.</li>
				<li>Free egress up to 3x your monthly maxiumum storage, with any additional egress priced at $0.01/GB</li>
			</ul>
			<p class="text-gray-300">
				If you have any questions about our service or pricing, please contact our support team for assistance.
			</p>
			<!-- Add more detailed billing terms as needed -->
		</div>
		<div class="flex justify-end p-4 bg-gray-800 rounded-b-lg">
			<button id="closeModalFooterButton" class="px-4 py-2 text-sm/6 font-semibold text-gray-300">
				Close
			</button>
		</div>
	</div>
</div>

<script>
	jQuery(document).ready(function() {
		jQuery('#openModalButton').click(function(e) {
			e.preventDefault();
			showBillingModal();
		});

		jQuery('#closeModalButton, #closeModalFooterButton').click(function() {
			hideBillingModal();
		});
		// Close modal when clicking outside the modal content
		jQuery('#billingModal').click(function(e) {
			if (e.target === this) {
				hideBillingModal();
			}
		});
		// Close modal on Escape key press
		jQuery(document).keydown(function(e) {
			if (e.key === 'Escape') {
				hideBillingModal();
			}
		});

		jQuery('#agreeTerms').change(function() {
			if (jQuery(this).is(':checked')) {
				jQuery('#createS3StorageAccount').prop('disabled', false);
				jQuery('#agreeError label').text('');
				jQuery('#agreeError').addClass('hidden');
			} else {
				jQuery('#createS3StorageAccount').prop('disabled', true);
			}
		});

		jQuery('#createS3StorageAccount').click(function(e) {
			e.preventDefault();
			if (jQuery('#agreeTerms').is(':checked')) {
				jQuery('#s3StorageForm').submit();
			} else {
				jQuery('#agreeError label').text('You must agree to the terms.');
				jQuery('#agreeError').removeClass('hidden');
			}
		});
		jQuery('#s3StorageForm').submit(function(e) {
			e.preventDefault();
			showLoader();
			var username = jQuery('#username').val();
			jQuery.ajax({
				type: 'POST',
				url: 'modules/addons/cloudstorage/api/createS3StorageAccount.php',
				data: { username: username },
				success: function(response) {
					hideLoader();
					if (response.status === 'fail') {
						jQuery('#responseMessage').addClass('bg-red-700').find('p').text(response.message);
						return;
					} else {
						jQuery('#responseMessage').removeClass('bg-red-700').addClass('bg-green-600').find('p').text(response.message);
						setTimeout(function() { location.reload(); }, 2000);
					}
				},
				error: function(xhr, status, error) {
					hideLoader();
					jQuery('#responseMessage').html('Error: ' + xhr.responseText);
				}
			});
		});

		function showBillingModal() {
			jQuery('#billingModal').removeClass('hidden');
			jQuery('body').addClass('overflow-hidden');
		}
		function hideBillingModal() {
			jQuery('#billingModal').addClass('hidden');
			jQuery('body').removeClass('overflow-hidden');
		}
	});
</script>