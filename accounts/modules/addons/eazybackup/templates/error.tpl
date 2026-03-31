<div class="min-h-screen bg-[var(--eb-bg-app)] text-[var(--eb-text-primary)]">
  <div class="mx-auto flex min-h-screen max-w-3xl items-center px-4 py-10">
    <section class="eb-subpanel w-full">
      <div class="eb-section-intro">
        <span class="eb-eyebrow">eazyBackup</span>
        <h2 class="eb-section-title">Something went wrong</h2>
        <p class="eb-section-description">We couldn&apos;t finish loading this area. Review the details below, then return to the client area and try again.</p>
      </div>

      <div class="eb-alert eb-alert--danger">
        <div class="min-w-0 flex-1">
          {$error|default:'An unexpected error occurred.'}
        </div>
      </div>

      {if isset($errormessage) && $errormessage}
        <div class="eb-card mt-4">
          <p class="eb-field-label mb-2">Diagnostic details</p>
          <pre class="overflow-x-auto whitespace-pre-wrap text-xs text-[var(--eb-text-muted)]">{$errormessage}</pre>
        </div>
      {/if}

      <div class="mt-6 flex justify-end">
        <a href="index.php?m=eazybackup" class="eb-btn eb-btn-secondary">Back to eazyBackup</a>
      </div>
    </section>
  </div>
</div>


