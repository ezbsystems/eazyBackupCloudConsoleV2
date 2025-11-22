<style>
  /* Override Bootstrap alert styles to match Tailwind's text-gray-300, text-sm, and mb-4 */
  .alert {
    color: #d1d5db !important;         /* text-gray-300 */
    font-size: 0.875rem !important;      /* text-sm (14px) */
    margin-bottom: 1rem !important;      /* mb-4 */
    text-align: center !important;       /* Centered text */
    background-color: transparent !important; /* Remove default alert background */
    border: none !important;             /* Remove borders */
    padding: 0.5rem 1rem !important;      /* Adjust padding if needed */
  }
  
  /* Optional: further refine the 'alert-warning' look, if desired */
  .alert.alert-warning {
    /* If you need to adjust any warning-specific styling, do so here */
  }

  input[type="text"][name="key"] {
    display: block;                /* block */
    width: 100%;                   /* w-full */
    padding: 0.5rem 0.75rem;         /* py-2 (0.5rem) and px-3 (0.75rem) */
    border: 1px solid #4b5563;      /* border and border-gray-600 */
    color: #d1d5db;                /* text-gray-300 */
    background-color: #374151;     /* bg-gray-700 */
    border-radius: 0.375rem;       /* rounded */
    outline: none;                 /* focus:outline-none */
    box-shadow: none;              /* focus:ring-0 */
  }

  /* Focus state styling */
  input[type="text"][name="key"]:focus {
    border-color: #2563eb;         /* focus:border-blue-600 */
    box-shadow: none;
  }

  input#btnLogin {
    display: block;                      /* Makes the button a block element */
    width: 100%;                         /* Full width similar to w-full */
    padding: 0.5rem 1rem;                /* Vertical and horizontal padding */
    border: 1px solid transparent;       /* Transparent border */
    background-color: #2563eb;           /* Tailwind blue-600 */
    color: #ffffff;                      /* White text */
    font-size: 1rem;                     /* Text size, approximates text-md */
    font-weight: 600;                    /* Semi-bold font (font-semisbold) */
    border-radius: 0.375rem;             /* Rounded corners (rounded-md) */
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); /* Small shadow (shadow-sm) */
    cursor: pointer;
    transition: background-color 0.2s ease-in-out;
  }

  /* Hover state: darken the background to mimic hover:bg-blue-700 */
  input#btnLogin:hover {
    background-color: #1d4ed8;           /* Tailwind blue-700 */
  }

  /* Focus state: remove any outlines or extra borders */
  input#btnLogin:focus {
    outline: none;
    border-color: transparent;
    box-shadow: none;
  }
</style>


<!-- templates/eazyBackup/two-factor-challenge.tpl -->
<div class="flex justify-center items-center min-h-screen bg-gray-700 p-4">
  <div class="bg-gray-800 rounded-lg shadow p-8 max-w-lg w-full">
    
    <!-- Header -->
    <h3 class="text-2xl font-semibold text-gray-300 text-center mb-6">{lang key='twofactorauth'}</h3>
    
    {* Flash messages can be included with dark mode styling *}
    {include file="$template/includes/flashmessage-darkmode.tpl"}
    
    {if $newbackupcode}
      {include file="$template/includes/alert.tpl" type="success" msg="{lang key='twofabackupcodereset'}" textcenter=true}
    {elseif $incorrect}
      {include file="$template/includes/alert.tpl" type="error" msg="{lang key='twofa2ndfactorincorrect'}" textcenter=true}
    {elseif $error}
      {include file="$template/includes/alert.tpl" type="error" msg=$error textcenter=true}
    {else}
      {include file="$template/includes/alert.tpl" type="warning" msg="{lang key='twofa2ndfactorreq'}" textcenter=true}
    {/if}

    <!-- Primary Two-Factor Challenge Form -->
    <form method="post" action="{routePath('login-two-factor-challenge-verify')}" id="frmTwoFactorChallenge" {if $usingBackup} class="hidden" {/if}>
      <div class="mb-4">
        <!-- The challenge markup/content goes here -->
        {$challenge}
      </div>
    </form>

    <!-- Backup Code Form -->
    <form method="post" action="{routePath('login-two-factor-challenge-backup-verify')}" id="frmTwoFactorBackup" {if !$usingBackup} class="hidden" {/if}>
      <div class="mb-4">
        <input type="text" name="twofabackupcode" placeholder="{lang key='twofabackupcodelogin'}"
               class="block w-full px-3 py-2 border border-gray-600 bg-gray-700 text-gray-300 rounded focus:outline-none focus:ring-0 focus:border-blue-600">
      </div>
      <div class="mb-4">
        <button type="submit" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded">
          {lang key='loginbutton'}
        </button>
      </div>
      <p class="text-center">
        <a href="#" class="block mt-2 text-sm font-medium text-blue-500 hover:underline" id="backupCodeCancel">
          {lang key='cancel'}
        </a>
      </p>
    </form>

    <!-- Footer with Toggle for Backup Code Form -->
    <div id="frmTwoFactorChallengeFooter" class="mt-4 text-center text-gray-300 text-sm">
      <small>
        {lang key='twofacantaccess2ndfactor'}
        <a href="#" id="loginWithBackupCode" class="text-blue-500 text-sm hover:underline">
          {lang key='twofaloginusingbackupcode'}
        </a>
      </small>
    </div>

  </div>
</div>

<script>
  // Engage! Switching between the two-factor forms.
  jQuery(document).ready(function() {
    jQuery('#loginWithBackupCode').click(function(e) {
      e.preventDefault();
      jQuery('#frmTwoFactorChallenge').hide();
      jQuery('#frmTwoFactorChallengeFooter').hide();
      jQuery('#frmTwoFactorBackup').show();
    });
    jQuery('#backupCodeCancel').click(function(e) {
      e.preventDefault();
      jQuery('#frmTwoFactorChallenge').show();
      jQuery('#frmTwoFactorChallengeFooter').show();
      jQuery('#frmTwoFactorBackup').hide();
    });
  });
</script>
