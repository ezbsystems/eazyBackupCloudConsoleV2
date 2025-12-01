{*
  eazyBackup - First-login password onboarding panel
  Shown after TOS acceptance for clients flagged as must_set_password.
*}

<div class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center px-4 py-10">
  <div class="w-full max-w-md">
    <div class="relative rounded-2xl border border-slate-800 bg-slate-900/80 shadow-2xl shadow-black/60 backdrop-blur-sm">
      <div class="pointer-events-none absolute inset-x-0 -top-px h-px bg-gradient-to-r from-emerald-400/0 via-emerald-400/70 to-sky-400/0"></div>

      <div class="px-6 py-6 sm:px-7 sm:py-7">
        <h1 class="text-xl font-semibold tracking-tight text-slate-50">
          Set your account password
        </h1>
        <p class="mt-2 text-xs text-slate-400">
          You’re logged in with a one-time session. Before you continue, please choose a password
          for ongoing access to your e3 Cloud Storage account.
        </p>

        {if isset($errors.general)}
          <div class="mt-4 rounded-md border border-rose-500/60 bg-rose-500/10 px-3 py-2 text-xs text-rose-100">
            {$errors.general}
          </div>
        {/if}

        <form
          method="post"
          action="index.php?m=eazybackup&amp;a=password-onboarding&amp;return_to={$return_to|escape:'url'}"
          class="mt-5 space-y-4 text-sm"
        >
          <div class="space-y-1.5">
            <label for="new_password" class="block text-xs font-medium text-slate-200">
              New password
            </label>
            <input
              id="new_password"
              name="new_password"
              type="password"
              autocomplete="new-password"
              class="block w-full rounded-lg border {if isset($errors.new_password)}border-rose-500{else}border-slate-700{/if} bg-slate-900/60 px-3 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
              placeholder="Choose a strong password"
              required
            />
            {if isset($errors.new_password)}
              <p class="text-[11px] text-rose-400 mt-1">{$errors.new_password}</p>
            {else}
              <p class="text-[11px] text-slate-500 mt-1">
                At least 10 characters. Use a mix of letters, numbers, and symbols.
              </p>
            {/if}
          </div>

          <div class="space-y-1.5">
            <label for="new_password_confirm" class="block text-xs font-medium text-slate-200">
              Confirm password
            </label>
            <input
              id="new_password_confirm"
              name="new_password_confirm"
              type="password"
              autocomplete="new-password"
              class="block w-full rounded-lg border {if isset($errors.new_password_confirm)}border-rose-500{else}border-slate-700{/if} bg-slate-900/60 px-3 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
              placeholder="Re-enter your password"
              required
            />
            {if isset($errors.new_password_confirm)}
              <p class="text-[11px] text-rose-400 mt-1">{$errors.new_password_confirm}</p>
            {/if}
          </div>

          <div class="pt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <button
              type="submit"
              class="inline-flex items-center justify-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold shadow-sm ring-1 ring-emerald-500/40 bg-gradient-to-r from-emerald-500 via-emerald-400 to-sky-400 text-slate-950 transition transform hover:-translate-y-px hover:shadow-lg active:translate-y-0 active:shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 focus:ring-offset-slate-900"
            >
              Save password and continue
            </button>

            <div class="text-[11px] text-slate-500 sm:text-right">
              <p>Trouble setting a password?</p>
              <p>
                <a href="submitticket.php" class="underline underline-offset-2 hover:text-emerald-300">
                  Contact support
                </a>
                for help.
              </p>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>


