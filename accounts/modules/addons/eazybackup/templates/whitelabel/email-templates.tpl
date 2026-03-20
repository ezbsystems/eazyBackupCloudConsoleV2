{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="eb-page">
  <div class="eb-page-inner">
    <div x-data="{
      sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360,
      toggleCollapse() {
        this.sidebarCollapsed = !this.sidebarCollapsed;
        localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed);
      },
      handleResize() {
        if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true;
      }
    }" x-init="window.addEventListener('resize', () => handleResize())" class="eb-panel !p-0">
      <div class="eb-app-shell">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='settings-email'}
        <main class="eb-app-main">
          <div class="eb-app-header">
            <div class="eb-app-header-copy">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="eb-app-header-icon h-6 w-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
              </svg>
              <div>
                <h1 class="eb-app-header-title">Email Templates</h1>
                <p class="eb-page-description !mt-1">Manage SMTP-driven system templates for this tenant.</p>
              </div>
            </div>
          </div>

          <div class="eb-app-body space-y-6">
            {if $flash_saved}
              <div class="eb-alert eb-alert--success">Changes saved.</div>
            {/if}
            {if $flash_error}
              <div class="eb-alert eb-alert--danger">{$flash_error|escape}</div>
            {/if}
            {if !$smtp_configured}
              <div class="eb-alert eb-alert--warning flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <span>Custom SMTP is not configured for this tenant. Configure Email on the Branding page to enable sending and testing.</span>
                <a href="{$modulelink}&a=whitelabel-branding&tid={$tenant.public_id|escape}" class="eb-btn eb-btn-warning eb-btn-sm">Configure SMTP</a>
              </div>
            {/if}

            <section class="eb-subpanel">
              <div class="mb-4">
                <h2 class="eb-app-card-title">Template Library</h2>
                <p class="eb-field-help">Enable or disable templates and jump into the full editor for message content.</p>
              </div>

              <div class="eb-table-shell">
                <table class="eb-table">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Key</th>
                      <th>Subject</th>
                      <th>Active</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {foreach from=$templates item=t}
                      <tr>
                        <td class="eb-table-primary">{$t.name|default:'—'}</td>
                        <td class="eb-table-mono">{$t.key|escape}</td>
                        <td>{$t.subject|default:'—'}</td>
                        <td>
                          <form method="post" action="{$modulelink}&a=whitelabel-email-templates&tid={$tenant.public_id|escape}" class="flex flex-wrap items-center gap-3">
                            <input type="hidden" name="token" value="{$csrf_token|escape}">
                            <input type="hidden" name="key" value="{$t.key|escape}">
                            <label class="inline-flex items-center gap-2 text-sm">
                              <input type="checkbox" name="is_active" value="1" class="eb-checkbox" {if $t.is_active==1}checked{/if} {if !$smtp_configured}disabled{/if} />
                              <span class="eb-text-muted">{if $t.is_active==1}Enabled{else}Disabled{/if}</span>
                            </label>
                            <button type="submit" class="eb-btn eb-btn-secondary eb-btn-xs">Save</button>
                          </form>
                        </td>
                        <td>
                          <a href="{$modulelink}&a=whitelabel-email-template-edit&tid={$tenant.public_id|escape}&tpl={$t.key|escape}" class="eb-btn eb-btn-secondary eb-btn-xs">Edit</a>
                        </td>
                      </tr>
                    {foreachelse}
                      <tr>
                        <td colspan="5">
                          <div class="eb-app-empty">No templates yet.</div>
                        </td>
                      </tr>
                    {/foreach}
                  </tbody>
                </table>
              </div>
            </section>
          </div>
        </main>
      </div>
    </div>
  </div>
</div>


