{assign var="activeTab" value="notifications"}

{capture name=ebAccountNav}
    {include file="$template/includes/profile-nav.tpl" activeTab=$activeTab}
{/capture}

{capture name=ebNotifyBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/clientarea.php?action=details" class="eb-breadcrumb-link">Account</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Notifications</span>
    </div>
{/capture}

{capture name=ebNotifyContent}
    {include
        file="templates/eazyBackup/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebNotifyBreadcrumb
        ebPageTitle="Notifications"
        ebPageDescription="Manage billing notification categories, recipient routing, and dashboard visibility settings."
    }
    <div class="eb-subpanel">
        {if $successful}
            <div class="eb-alert eb-alert--success mb-6">
                <div class="eb-alert-title">{lang key='changessavedsuccessfully'}</div>
            </div>
        {/if}
        {if $errormessage}
            <div class="eb-alert eb-alert--danger mb-6">{$errormessage}</div>
        {/if}

        <form method="post" action="{$modulelink}&a=notify-settings" class="space-y-8">
            <section>
                <div class="eb-section-intro">
                    <h3 class="eb-section-title">Email Categories</h3>
                    <p class="eb-section-description">Choose which billing notifications you receive for cloud backup services.</p>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <label class="flex items-center gap-3">
                        <button type="button" class="eb-toggle" data-toggle data-target="notify_storage">
                            <span class="eb-toggle-track{if $prefs.notify_storage} is-on{/if}">
                                <span class="eb-toggle-thumb"></span>
                            </span>
                        </button>
                        <span class="eb-toggle-label">Storage</span>
                        <input type="hidden" name="notify_storage" id="notify_storage" value="{if $prefs.notify_storage}1{else}0{/if}">
                    </label>
                    <label class="flex items-center gap-3">
                        <button type="button" class="eb-toggle" data-toggle data-target="notify_devices">
                            <span class="eb-toggle-track{if $prefs.notify_devices} is-on{/if}">
                                <span class="eb-toggle-thumb"></span>
                            </span>
                        </button>
                        <span class="eb-toggle-label">Devices</span>
                        <input type="hidden" name="notify_devices" id="notify_devices" value="{if $prefs.notify_devices}1{else}0{/if}">
                    </label>
                    <label class="flex items-center gap-3">
                        <button type="button" class="eb-toggle" data-toggle data-target="notify_addons">
                            <span class="eb-toggle-track{if $prefs.notify_addons} is-on{/if}">
                                <span class="eb-toggle-thumb"></span>
                            </span>
                        </button>
                        <span class="eb-toggle-label">Add-ons</span>
                        <input type="hidden" name="notify_addons" id="notify_addons" value="{if $prefs.notify_addons}1{else}0{/if}">
                    </label>
                </div>
            </section>

            <section>
                <div class="eb-section-intro">
                    <h3 class="eb-section-title">Recipient Routing</h3>
                    <p class="eb-section-description">Choose where notification emails should be sent.</p>
                </div>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="routing_policy" class="eb-field-label">Default Recipient</label>
                        <select id="routing_policy" name="routing_policy" class="eb-select">
                            <option value="primary" {if $prefs.routing_policy=='primary'}selected{/if}>Primary</option>
                            <option value="billing" {if $prefs.routing_policy=='billing'}selected{/if}>Billing</option>
                            <option value="technical" {if $prefs.routing_policy=='technical'}selected{/if}>Technical</option>
                            <option value="custom" {if $prefs.routing_policy=='custom'}selected{/if}>Custom</option>
                        </select>
                    </div>
                    <div>
                        <label for="custom_recipients" class="eb-field-label">Custom Recipient(s)</label>
                        <input
                            type="text"
                            name="custom_recipients"
                            id="custom_recipients"
                            value="{$prefs.custom_recipients|escape}"
                            placeholder="user@example.com, billing@example.com"
                            class="eb-input"
                        />
                        <p class="eb-field-help">Only used when Default Recipient is set to Custom. Separate with commas or semicolons.</p>
                    </div>
                </div>
            </section>

            <section>
                <div class="eb-section-intro">
                    <h3 class="eb-section-title">Dashboard</h3>
                    <p class="eb-section-description">Control whether the upcoming charges panel is visible on your dashboard.</p>
                </div>
                <label class="flex items-center gap-3">
                    <button type="button" class="eb-toggle" data-toggle data-target="show_upcoming_charges">
                        <span class="eb-toggle-track{if $prefs.show_upcoming_charges} is-on{/if}">
                            <span class="eb-toggle-thumb"></span>
                        </span>
                    </button>
                    <span class="eb-toggle-label">Show Upcoming Charges</span>
                    <input type="hidden" name="show_upcoming_charges" id="show_upcoming_charges" value="{if $prefs.show_upcoming_charges}1{else}0{/if}">
                </label>
            </section>

            <div class="flex items-center justify-end gap-3">
                <button type="reset" class="eb-btn eb-btn-ghost">{lang key='cancel'}</button>
                <button type="submit" name="save" value="save" class="eb-btn eb-btn-primary">{lang key='clientareasavechanges'}</button>
            </div>
        </form>
    </div>
{/capture}

{include
    file="templates/eazyBackup/includes/ui/page-shell.tpl"
    ebPageNav=$smarty.capture.ebAccountNav
    ebPageContent=$smarty.capture.ebNotifyContent
}

{literal}
<script>
document.addEventListener('DOMContentLoaded', function () {
  var toggles = document.querySelectorAll('[data-toggle]');
  toggles.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var targetId = btn.getAttribute('data-target');
      if (!targetId) {
        return;
      }
      var input = document.getElementById(targetId);
      if (!input) {
        return;
      }
      var next = String(parseInt(input.value || '0', 10) === 1 ? 0 : 1);
      input.value = next;
      var track = btn.querySelector('.eb-toggle-track');
      if (track) {
        track.classList.toggle('is-on', next === '1');
      }
    });
  });
});
</script>
{/literal}
