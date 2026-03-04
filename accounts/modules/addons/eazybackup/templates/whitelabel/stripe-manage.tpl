{* Minimal shell for Stripe Connect embedded account management *}
<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div class="mb-6">
      <h2 class="text-2xl font-semibold text-white">Manage Stripe Account</h2>
      <p class="text-xs text-slate-400 mt-1">Use the embedded management to update bank details, business profile, and ownership.</p>
    </div>
    <div id="stripe-embedded-account" data-endpoint="{$modulelink|escape}&a=ph-stripe-account-session" data-connect-link="{$modulelink|escape}&a=ph-stripe-connect" data-manage-link="{$modulelink|escape}&a=ph-stripe-manage-redirect" class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-4"></div>
    <script src="modules/addons/eazybackup/assets/js/stripe-account-manage.js"></script>
  </div>
</div>
