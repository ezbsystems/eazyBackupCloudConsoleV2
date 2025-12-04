<div class="min-h-screen bg-gray-700 text-gray-100">
    <div class="container mx-auto px-4 pb-8">
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center px-2">
            <!-- Navigation Horizontal -->
            <div class="flex items-center">        
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>                   
                <h2 class="text-2xl font-semibold text-white">My Account</h2>
            </div>
        </div>
		{assign var="activeTab" value="security"}
        {include file="$template/includes/profile-nav.tpl" activeTab=$activeTab}
        <div class="flex flex-col flex-1 overflow-y-auto bg-gray-700">
            <!-- Main Content Container -->
			<div class="bg-slate-800 shadow rounded-b-xl p-4 mb-4">	       		

				{if $linkableProviders}
					<!-- Linked Accounts Card -->
					<div class="bg-white shadow rounded p-4 mb-6">
						<h3 class="text-xl font-semibold mb-3 text-gray-100">
							{lang key='remoteAuthn.titleLinkedAccounts'}
						</h3>

						{include file="$template/includes/linkedaccounts.tpl" linkContext="clientsecurity" }
						<br />
						{include file="$template/includes/linkedaccounts.tpl" linkContext="linktable" }
						<br />
					</div>
				{/if}


				{if $twoFactorAuthAvailable}
					<div class="border-b border-gray-900/10 pb-8">
						<div class="card-body">
							<h3 class="text-lg font-semibold text-gray-100 mb-4">{lang key='twofactorauth'}</h3>
				
						{if $twoFactorAuthEnabled}
							<p class="twofa-config-link text-gray-100">
								{lang key='twofacurrently'} <span class="inline-flex items-center rounded-md bg-green-600 px-2 py-1 text-sm font-semibold text-gray-100 ring-1 ring-green-600/20 ring-inset">{lang key='enabled'|strtolower}</span>
							</p>
						{else}
							<p class="twofa-config-link text-gray-100">
								{lang key='twofacurrently'} <span class="inline-flex items-center rounded-md bg-red-700 px-2 py-1 text-sm font-semibold text-gray-100 ring-1 ring-red-600/10 ring-inset">{lang key='disabled'|strtolower}</span>
							</p>
						{/if}
				
							{if $twoFactorAuthRequired}
								{include file="$template/includes/alert.tpl" type="warning" msg="{lang key="clientAreaSecurityTwoFactorAuthRequired"}"}                   
							{/if}
				
                            {if $twoFactorAuthEnabled}
                                <a href="{routePath('account-security-two-factor-disable')}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-700 open-modal twofa-config-link w-auto max-w-xs" data-modal-title="{lang key='twofadisable'}" data-modal-class="twofa-setup" data-btn-submit-label="{lang key='twofadisable'}" data-btn-submit-color="danger" data-btn-submit-id="btnDisable2FA">
                                    {lang key='twofadisableclickhere'}
                                </a>
                            {else}
                                <a href="{routePath('account-security-two-factor-enable')}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-700 open-modal twofa-config-link w-auto max-w-xs" data-modal-title="{lang key='twofaenable'}" data-modal-class="twofa-setup" data-btn-submit-id="btnEnable2FA">
                                    {lang key='twofaenableclickhere'}
                                </a>
                            {/if}
						</div>
					</div>
				{/if}



				<div id="modalAjax" class="modal system-modal fade fixed inset-0 z-50 hidden" tabindex="-1" role="dialog"
					aria-hidden="true">

					<div class="absolute inset-0 bg-black bg-opacity-50"></div>
					
					<div class="modal-dialog relative rounded max-w-lg w-full mx-auto mt-10 border-gray-800">        
						<div class="modal-content border-gray-800">
							<div class="modal-header bg-gray-800 flex items-center justify-between p-4">                
								<h5 class="modal-title text-md text-white font-semibold"></h5>               
								<button type="button" class="close text-gray-700" data-dismiss="modal">
									<span aria-hidden="true">&times;</span>
									<span class="sr-only">{lang key='close'}</span>
								</button>
							</div>
						
							<div class="modal-body bg-gray-800 p-4">
								{lang key='loading'}
							</div>

							<div class="modal-footer flex justify-end space-x-4 p-4 border-t bg-gray-800">                
							<button type="button" class="btn-close px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
									data-dismiss="modal">
									{lang key='close'}
								</button>
								<button type="button" class="btn-submit px-4 py-2 bg-sky-600 text-white rounded modal-submit">
									{lang key='submit'}
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
// Ensure the page refreshes after closing the 2FA modal so the status and buttons reflect the new state
jQuery(function($){
    var modalShownForTwoFA = false;

    // Mark when the 2FA modal is shown (this page uses only this modal for 2FA actions)
    $('#modalAjax').on('shown.bs.modal', function(){
        modalShownForTwoFA = true;
    });

    // When the modal is closed, refresh the page to update the UI
    $('#modalAjax').on('hidden.bs.modal', function(){
        if (modalShownForTwoFA) {
            window.location.reload();
        }
    });
});
</script>


<style>
.btn {
    display: inline-block;
    font-weight: 400;
    color: #212529;
    text-align: center;
    vertical-align: middle;
    -webkit-user-select: none;
    -moz-user-select: none;
    user-select: none;
    background-color: transparent;
    border: 1px solid transparent;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    line-height: 1.5;
    border-radius: 0.25rem;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.btn:not(:disabled):not(.disabled) {
    cursor: pointer;
}

.btn-success {
    color: #fff;
    background-color: #16a34a;
    border-color: #16a34a;
}
.btn-success:hover {
    color: #fff;
    background-color: #15803d;
    border-color: #15803d;
}
.btn-success.focus,
.btn-success:focus {
    color: #fff;
    background-color: #15803d;
    border-color: #15803d;
    box-shadow: 0 0 0 0.2rem rgba(72, 180, 97, 0.5);
}
.btn-success.disabled,
.btn-success:disabled {
    color: #fff;
    background-color: #28a745;
    border-color: #28a745;
}
.btn-success:not(:disabled):not(.disabled).active,
.btn-success:not(:disabled):not(.disabled):active,
.show > .btn-success.dropdown-toggle {
    color: #fff;
    background-color: #1e7e34;
    border-color: #1c7430;
}
.btn-success:not(:disabled):not(.disabled).active:focus,
.btn-success:not(:disabled):not(.disabled):active:focus,
.show > .btn-success.dropdown-toggle:focus {
    box-shadow: 0 0 0 0.2rem rgba(72, 180, 97, 0.5);
}
.modal-content {
	position: relative;
	display: flex;
	flex-direction: column;
	width: 100%;
	pointer-events: auto;	
	background-clip: padding-box;	
	border-radius: 0.3rem;
	outline: 0;
}
.modal-title {
	margin-bottom: 0;
	line-height: 1.5;
}
.font-semibold {
	font-weight: 600;
}
.text-xl {
	font-size: 1.25rem;
	line-height: 1.75rem;
}
.btn:hover {  
    text-decoration: none;
}
h1, h2, h3, h4, h5, h6 {
	margin-top: 0;
	margin-bottom: 0.5rem;
}
.btn {
    overflow: hidden;
}
.p-4 {
	padding: 1.5rem !important;
}
*, ::after, ::before {
	box-sizing: border-box;
}
:focus-visible {
  outline: 1px solid #0284c7;

}
.modal-content {
	pointer-events: auto;
}
.modal-header > .close {
	color: inherit;
}
.modal-header .close {
	padding: 1rem 1rem;
	margin: -1rem -1rem -1rem auto;
}
.modal-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	padding: 1rem 1rem;
	border-bottom: 1px solid #4b5563;
	border-top-left-radius: calc(0.3rem - 1px);
	border-top-right-radius: calc(0.3rem - 1px);
}    
button.close {
	padding: 0;
	background-color: transparent;
	border: 0;
}
.close {
	float: right;
	font-size: 1.5rem;
	font-weight: 700;
	line-height: 1;
	color: #000;
	text-shadow: 0 1px 0 #fff;
	opacity: 0.5;
}
.close:not(:disabled):not(.disabled):focus, .close:not(:disabled):not(.disabled):hover {
	opacity: 0.75;
}
.modal-header > .close {
	color: inherit;
}    
.modal-header .close {
	padding: 1rem 1rem;
	margin: -1rem -1rem -1rem auto;
}
.close:hover {
	color: #d1d5db;
	text-decoration: none;
}
.close {
	float: right;
	font-size: 1.5rem;
	font-weight: 700;
	line-height: 1;
	color: #000;
	text-shadow: 0 1px 0 #fff;
	opacity: 0.5;
}    
.tex1t-gray-700 {
	--tw-text-opacity: 1;
	color: rgb(55 65 81 / var(--tw-text-opacity, 1));
}
button, input {
	overflow: visible;
}
button {
	border-radius: 0;
}
.twofa-module.active {
	border-color: #337ab7;
}
.twofa-module {
	margin: 10px 0;
	padding: 14px 20px;
	border: 1px solid #ccc;
	border-radius: 4px;
	cursor: pointer;
}
.twofa-module .col-radio {
	float: left;
	width: 35px;
	margin-top: 12px;
}
.twofa-module .col-logo {
	float: left;
	width: 80px;
	line-height: 40px;
	text-align: center;
}
.twofa-module .col-description {
	margin-left: 136px;
}
b, strong {
	font-weight: bolder;
}
p {
	margin-top: 0;
	margin-bottom: 1rem;
}
.btn:not(:disabled):not(.disabled) {
	cursor: pointer;
}
.w-hidden {
	display: none;
}
.btn {
	overflow: hidden;
}
.btn-primary {
	color: #fff;
	background-color: #0284c7;
	border-color: #0284c7;
}
[type="button"], [type="reset"], [type="submit"], button {
	-webkit-appearance: button;
}
.modal-footer {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: flex-end;
	padding: 0.75rem;
	border-top: 1px solid #4b5563;
	border-bottom-right-radius: calc(0.3rem - 1px);
	border-bottom-left-radius: calc(0.3rem - 1px);
}
.modal-backdrop.show {
	opacity: 0.5;
}
.modal-backdrop {
	position: fixed;
	top: 0;
	left: 0;
	z-index: 1040;
	width: 100vw;
	height: 100vh;
	background-color: #000;
}
.modal-open .modal {
	overflow-x: hidden;
	overflow-y: auto;
}
.modal {
	position: fixed;
	top: 0;
	left: 0;
	z-index: 1050;
	display: none;
	width: 100%;
	height: 100%;
	overflow: hidden;
	outline: 0;
}
.fade {
	transition: opacity 0.15s linear;
}
.modal.show .modal-dialog {
	transform: none;
}
.modal.fade .modal-dialog {
	transition: transform 0.3s ease-out;
	transform: translate(0, -50px);
    margin-top: 100px
}
.mt-10 {
	margin-top: 2.5rem;
}    
.modal .modal-dialog {
	max-width: 700px;
}
.ml-auto, .mx-auto {
	margin-left: auto !important;
}
.mr-auto, .mx-auto {
	margin-right: auto !important;
}
.shadow-lg {
	box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175) !important;
}
.rounded {
	border-radius: 0.25rem !important;
}
.bg-white {
	background-color: #fff !important;
}
.modal-dialog {
	max-width: 500px;
	margin: 1.75rem auto;
}
.modal-dialog {
	position: relative;
	width: auto;
	margin: 0.5rem;
	pointer-events: none;
}
.shadow-lg {
	--tw-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
	--tw-shadow-colored: 0 10px 15px -3px var(--tw-shadow-color), 0 4px 6px -4px var(--tw-shadow-color);
	box-shadow: var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow);
}
.form-control-lg {
	height: calc(1.5em + 1rem + 2px);
	padding: 0.5rem 1rem;
	font-size: 1.25rem;
	line-height: 1.5;
	border-radius: 0.3rem;
}
.form-control {
	display: block;
	width: 100%;
	height: calc(1.5em + 0.75rem + 2px);
	padding: 0.375rem 0.75rem;
	font-size: 1rem;
	font-weight: 400;
	line-height: 1.5;
	color: #d1d5db;
	background-color: #11182759;
	background-clip: padding-box;
	border: 1px solid #4b5563;
	border-radius: 0.25rem;
	transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
.col-sm-4 {
	flex: 0 0 33.3333333333%;
	max-width: 33.3333333333%;
}
.col, .col-1, .col-10, .col-11, .col-12, .col-2, .col-3, .col-4, .col-5, .col-6, .col-7, .col-8, .col-9, .col-auto, .col-lg, .col-lg-1, .col-lg-10, .col-lg-11, .col-lg-12, .col-lg-2, .col-lg-3, .col-lg-4, .col-lg-5, .col-lg-6, .col-lg-7, .col-lg-8, .col-lg-9, .col-lg-auto, .col-md, .col-md-1, .col-md-10, .col-md-11, .col-md-12, .col-md-2, .col-md-3, .col-md-4, .col-md-5, .col-md-6, .col-md-7, .col-md-8, .col-md-9, .col-md-auto, .col-sm, .col-sm-1, .col-sm-10, .col-sm-11, .col-sm-12, .col-sm-2, .col-sm-3, .col-sm-4, .col-sm-5, .col-sm-6, .col-sm-7, .col-sm-8, .col-sm-9, .col-sm-auto, .col-xl, .col-xl-1, .col-xl-10, .col-xl-11, .col-xl-12, .col-xl-2, .col-xl-3, .col-xl-4, .col-xl-5, .col-xl-6, .col-xl-7, .col-xl-8, .col-xl-9, .col-xl-auto {
	position: relative;
	width: 100%;
	padding-right: 15px;
	padding-left: 15px;
}
.col-sm-8 {
	flex: 0 0 66.6666666667%;
	max-width: 66.6666666667%;
}
label {
	display: inline-block;
	margin-bottom: .5rem;
}
.col-sm-6 {
	flex: 0 0 50%;
	max-width: 50%;
}    
.row {
	display: flex;
	flex-wrap: wrap;
	margin-right: -15px;
	margin-left: -15px;
}
.h3, h3 {
	font-size: 1.5rem;
}
.h1, .h2, .h3, .h4, .h5, .h6, h1, h2, h3, h4, h5, h6 {
	margin-bottom: 0.5rem;
	font-weight: 500;
	line-height: 1.2;
}
.twofa-module img {
	max-width: 100%;
	max-height: 40px;
}
img {
	vertical-align: middle;
	border-style: none;
}
img, video {
	max-width: 100%;
	height: auto;
}
input[type="button"].btn-block, input[type="reset"].btn-block, input[type="submit"].btn-block {
width: 100%;
}
.btn-primary:hover {
	color: #fff;
	background-color: #0369a1;
	border-color: #0369a1;
}

.btn-group-lg > .btn, .btn-lg {
	padding: 0.5rem 1rem;	
	line-height: 1.5;
	border-radius: 0.3rem;
}
.col-sm-4 {
	flex: 0 0 33.3333333333%;
	max-width: 33.3333333333%;
}
    .text-center {
	text-align: center !important;
}
    .alert-success {
	color: #155724;
	background-color: #d4edda;
	border-color: #c3e6cb;
}
    .alert {
	position: relative;
	padding: 0.75rem 1.25rem;
	margin-bottom: 1rem;
	border: 1px solid transparent;
	border-radius: 0.25rem;
}
    .twofa-setup .backup-code {
	margin: 20px auto;
	padding: 10px;
	background-color: #efefef;
	color: #444;
	text-align: center;
}
    element {
	display: block;
	font-family: monospace;
	font-size: 1.6em;
}
.btn-danger:hover {
	color: #fff;
	background-color: #b91c1c;
	border-color: #b91c1c;
}
.btn-danger {
	color: #fff;
	background-color: #dc2626;
	border-color: #dc2626;
	margin-left: 10px;
}
.alert-warning {
	color: #856404;
	background-color: #fff3cd;
	border-color: #ffeeba;
}
.alert {
	position: relative;
	padding: .75rem 1.25rem;
	margin-bottom: 1rem;
	border: 1px solid transparent;
	border-radius: .25rem;
}
.alert-danger {
	color: #721c24;
	background-color: #f8d7da;
	border-color: #f5c6cb;
}
.alert {
	position: relative;
	padding: .75rem 1.25rem;
	margin-bottom: 1rem;
	border: 1px solid transparent;
	border-radius: .25rem;
}
</style>