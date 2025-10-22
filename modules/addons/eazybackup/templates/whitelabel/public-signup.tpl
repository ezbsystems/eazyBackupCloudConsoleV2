<div class="min-h-screen bg-gray-800 text-gray-200">
  <div class="container mx-auto max-w-2xl px-4 py-10">
    <h1 class="text-2xl font-semibold text-white mb-4">Start your trial</h1>
    <p class="text-sm text-gray-300 mb-6">Create your account to begin using our backup service.</p>
    <form method="post" class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm text-gray-300 mb-1">First name</label>
          <input name="first_name" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
        </div>
        <div>
          <label class="block text-sm text-gray-300 mb-1">Last name</label>
          <input name="last_name" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
        </div>
      </div>
      <div>
        <label class="block text-sm text-gray-300 mb-1">Company (optional)</label>
        <input name="company" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm text-gray-300 mb-1">Email</label>
          <input type="email" name="email" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
        </div>
        <div>
          <label class="block text-sm text-gray-300 mb-1">Phone (optional)</label>
          <input name="phone" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm text-gray-300 mb-1">Username</label>
          <input name="username" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
        </div>
        <div>
          <label class="block text-sm text-gray-300 mb-1">Password</label>
          <input type="password" name="password" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
        </div>
      </div>
      <div>
        <label class="block text-sm text-gray-300 mb-1">Confirm Password</label>
        <input type="password" name="confirm_password" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
      </div>
      <div>
        <label class="block text-sm text-gray-300 mb-1">Promo code (optional)</label>
        <input name="promo_code" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
      </div>
      <div class="flex items-center gap-2">
        <input type="checkbox" name="agree" value="1" class="rounded" />
        <span class="text-xs text-gray-300">I agree to the Terms of Service and Privacy Policy.</span>
      </div>
      <div class="flex justify-end">
        <button type="submit" class="rounded bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Create account</button>
      </div>
    </form>
    {if $turnstile_site_key}
      <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
      <div class="mt-4"><div class="cf-turnstile" data-sitekey="{$turnstile_site_key|escape}"></div></div>
    {/if}
  </div>
</div>


