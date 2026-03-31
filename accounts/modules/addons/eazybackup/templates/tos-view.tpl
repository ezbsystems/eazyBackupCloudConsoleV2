<style>
  .eb-legal-content {
    color: var(--eb-text-secondary);
  }
  .eb-legal-content p { margin: 0.75rem 0; }
  .eb-legal-content strong { color: var(--eb-text-primary); font-weight: 700; }
  .eb-legal-content em { font-style: italic; }
  .eb-legal-content a { color: var(--eb-accent); text-decoration: underline; }
  .eb-legal-content ul,
  .eb-legal-content ol { margin: 0.75rem 0; padding-left: 1.25rem; }
  .eb-legal-content ul { list-style: disc; }
  .eb-legal-content ol { list-style: decimal; }
  .eb-legal-content li { margin: 0.25rem 0; }
  .eb-legal-content ol ol { list-style: lower-alpha; }
  .eb-legal-content h1,
  .eb-legal-content h2,
  .eb-legal-content h3,
  .eb-legal-content h4 {
    margin: 1.25rem 0 0.75rem;
    color: var(--eb-text-primary);
    font-weight: 700;
  }
  .eb-legal-content hr {
    margin: 1.5rem 0;
    border: 0;
    border-top: 1px solid var(--eb-border-subtle);
  }
</style>

<div class="eb-page">
  <div class="eb-page-inner !max-w-5xl">
    <div class="eb-panel">
      <div class="eb-page-header">
        <div>
          <div class="eb-breadcrumb">
            <a href="index.php?m=eazybackup&a=terms" class="eb-breadcrumb-link">Legal Agreements</a>
            <span class="eb-breadcrumb-separator">/</span>
            <span class="eb-breadcrumb-current">Terms of Service</span>
          </div>
          <h1 class="eb-page-title">{$tos->title|default:'Terms of Service'|escape}</h1>
          <p class="eb-page-description">Review the exact Terms of Service version associated with this acceptance record.</p>
        </div>
      </div>

      <div class="eb-subpanel">
        <div class="eb-section-intro">
          <h3 class="eb-section-title">Agreement Snapshot</h3>
          <p class="eb-section-description">This view shows the stored title, version, and legal HTML for the selected Terms of Service revision.</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
          <div class="eb-card-raised">
            <p class="eb-field-label !mb-1">Document</p>
            <p class="text-sm font-medium text-[var(--eb-text-primary)]">{$tos->title|default:'Terms of Service'|escape}</p>
          </div>
          <div class="eb-card-raised">
            <p class="eb-field-label !mb-1">Version</p>
            <p class="text-sm font-medium text-[var(--eb-text-primary)]">{if $tos->version}{$tos->version|escape}{else}&mdash;{/if}</p>
          </div>
        </div>

        <div class="mt-6 eb-card-raised">
          {if $tos->content_html}
            <div class="eb-legal-content max-w-none text-sm leading-7">
              {$tos->content_html|unescape:'html' nofilter}
            </div>
          {else}
            <p class="text-sm text-[var(--eb-text-secondary)]">No content available for this version.</p>
          {/if}
        </div>

        <div class="mt-6">
          <a href="index.php?m=eazybackup&a=terms" class="eb-btn eb-btn-secondary eb-btn-sm">Back to Legal Agreements</a>
        </div>
      </div>
    </div>
  </div>
</div>

