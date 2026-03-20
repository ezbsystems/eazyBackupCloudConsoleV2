{if $errorMessage}
    {include file="$template/includes/alert-darkmode.tpl" type="error" msg=$errorMessage textcenter=true}
{/if}

<p class="text-gray-300 text-sm mb-4">
    {lang key='pwresetsecurityquestionrequired'}
</p>

<form method="post" action="{routePath('password-reset-security-verify')}" class="space-y-6">
    <div>
        <label for="inputAnswer" class="eb-field-label">
            {$securityQuestion}
        </label>
        <input
            type="text"
            name="answer"
            id="inputAnswer"
            autofocus
            class="eb-input rounded-xl"
        >
    </div>

    <div class="flex justify-center">
        <button
            type="submit"
            class="eb-btn eb-btn-primary w-full rounded-full py-2.5"
        >
            {lang key='pwresetsubmit'}
        </button>
    </div>
</form>
