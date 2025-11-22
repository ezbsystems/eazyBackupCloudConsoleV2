{* Minimal shell for Stripe Connect embedded account management *}
<div class="p-6">
  <h2 class="text-xl font-semibold text-gray-100 mb-4">Manage Stripe Account</h2>
  <p class="text-gray-300 mb-4">Use the embedded management to update bank details, business profile, and ownership.</p>
  <div id="stripe-embedded-account" data-endpoint="{$modulelink|escape}&a=ph-stripe-account-session" data-connect-link="{$modulelink|escape}&a=ph-stripe-connect" data-manage-link="{$modulelink|escape}&a=ph-stripe-manage-redirect" class="rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 p-4"></div>
  <script src="modules/addons/eazybackup/assets/js/stripe-account-manage.js"></script>
</div>


