{if $errorMessage}
    {include file="$template/includes/alert-darkmode.tpl" type="error" msg=$errorMessage textcenter=true}
{/if}

<p class="text-gray-300 text-sm mb-4">
    {lang key='pwresetsecurityquestionrequired'}
</p>

<form method="post" action="{routePath('password-reset-security-verify')}" class="space-y-6">
    <div>
        <label for="inputAnswer" class="block text-sm font-medium text-white mb-2">
            {$securityQuestion}
        </label>
        <input
            type="text"
            name="answer"
            id="inputAnswer"
            autofocus
            class="block w-full px-3 py-2.5 rounded-lg border border-slate-700 bg-slate-900/60 text-sm text-white placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
        >
    </div>

    <div class="flex justify-center">
        <button
            type="submit"
            class="inline-flex w-full items-center justify-center rounded-full px-4 py-2 text-sm font-semibold text-white shadow-sm bg-[#FE5000] hover:bg-[#ff6a26] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-slate-950 focus:ring-[#FE5000]"
        >
            {lang key='pwresetsubmit'}
        </button>
    </div>
</form>
