{assign var="activeTab" value="inbox"}

{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{literal}
<style>
    [x-cloak] { display: none !important; }
    .eb-inbox-row { transition: background-color 120ms ease, opacity 200ms ease; }
    .eb-inbox-row:hover { background: var(--eb-surface-hover, rgba(148,163,184,0.08)); cursor: pointer; }
    .eb-inbox-row.is-removing { opacity: 0; transform: translateX(8px); }
    .eb-unread-dot { display:inline-block; width: 8px; height: 8px; border-radius: 999px; background: var(--eb-accent, #f97316); margin-right: 8px; vertical-align: middle; }
    .eb-inbox-snippet { color: var(--eb-text-muted); font-size: 13px; max-width: 100%; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; white-space: normal; }
</style>
{/literal}

{capture name=ebAccountNav}
    {include file="$template/includes/profile-nav.tpl" activeTab=$activeTab}
{/capture}

{capture name=ebInboxBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="{$WEB_ROOT}/clientarea.php?action=details" class="eb-breadcrumb-link">Account</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Inbox</span>
    </div>
{/capture}

{capture name=ebInboxActions}
    <span class="eb-badge eb-badge--neutral" x-show="messages.length > 0" x-cloak>
        <span x-text="unreadCount"></span>&nbsp;unread
    </span>
{/capture}

{capture name=ebInboxContent}
    <script id="ebInboxData" type="application/json">{ldelim}"messages": {$inboxMessages|@json_encode}, "dismiss": "{$inboxDismissEndpoint}", "delete": "{$inboxDeleteEndpoint}", "csrf": "{$inboxCsrf}"{rdelim}</script>

    <div x-data="ebInbox()">
        {include
            file="templates/eazyBackup/includes/ui/page-header.tpl"
            ebBreadcrumb=$smarty.capture.ebInboxBreadcrumb
            ebPageTitle="Inbox"
            ebPageDescription="Personal messages from our team. Open a message to read; delete to remove from your inbox."
            ebPageActions=$smarty.capture.ebInboxActions
        }

        <div class="eb-subpanel">
            <template x-if="messages.length === 0">
                <div class="eb-card p-6 text-center" style="color: var(--eb-text-muted);">
                    <i class="fas fa-inbox" style="font-size: 28px; margin-bottom: 8px;"></i>
                    <div class="eb-card-title">Your inbox is empty</div>
                    <p class="mt-1">Personal messages from our team will appear here.</p>
                </div>
            </template>

            <template x-if="messages.length > 0">
                <div class="eb-card">
                    <ul class="divide-y" style="border-color: var(--eb-border);">
                        <template x-for="m in messages" :key="m.id">
                            <li class="eb-inbox-row p-4 flex items-start gap-3"
                                :class="{ 'is-removing': removing[m.id] }"
                                @click="open(m)">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="eb-unread-dot" x-show="!m.is_read"></span>
                                        <div class="eb-card-title" x-text="m.title"></div>
                                        <template x-if="m.is_expired">
                                            <span class="eb-badge eb-badge--warning" style="font-size:10px;padding:1px 5px;">Expired</span>
                                        </template>
                                    </div>
                                    <p class="eb-inbox-snippet mt-1" x-text="snippet(m.body)"></p>
                                    <div class="mt-1" style="font-size:12px;color:var(--eb-text-muted);">
                                        <span x-text="m.created_at"></span>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-1">
                                    <button type="button"
                                        class="eb-btn eb-btn-secondary eb-btn-xs"
                                        @click.stop="del(m)"
                                        :disabled="!!busy[m.id]">
                                        <span x-show="!busy[m.id]">Delete</span>
                                        <span x-show="busy[m.id]">&hellip;</span>
                                    </button>
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>
        </div>

        <div x-show="viewing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
            <div class="eb-modal-backdrop fixed inset-0" @click="close()"></div>
            <div class="eb-modal relative z-10" style="max-width:560px;width:100%;">
                <div class="eb-modal-header">
                    <div>
                        <h2 class="eb-modal-title" x-text="viewing && viewing.title"></h2>
                        <p class="eb-modal-subtitle" x-text="viewing && viewing.created_at"></p>
                    </div>
                    <button type="button" class="eb-modal-close" @click="close()" aria-label="Close">&times;</button>
                </div>
                <div class="eb-modal-body" style="max-height:60vh;overflow-y:auto;">
                    <div class="eb-type-body" style="color:var(--eb-text-secondary);" x-html="viewing && viewing.body_html"></div>
                </div>
                <div class="eb-modal-footer">
                    <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="close()">Close</button>
                    <button type="button" class="eb-btn eb-btn-danger-solid eb-btn-sm" @click="if (viewing) { del(viewing); close(); }">Delete</button>
                </div>
            </div>
        </div>
    </div>
{/capture}

{include
    file="templates/eazyBackup/includes/ui/page-shell.tpl"
    ebPageNav=$smarty.capture.ebAccountNav
    ebPageContent=$smarty.capture.ebInboxContent
}

{literal}
<script>
(function(){
    function init() {
        if (typeof window.Alpine === 'undefined') { setTimeout(init, 50); return; }
        if (window.__ebInboxReg) return;
        window.__ebInboxReg = true;
        window.Alpine.data('ebInbox', function(){
            var cfg = { messages: [], dismiss: '', 'delete': '', csrf: '' };
            try {
                var el = document.getElementById('ebInboxData');
                if (el) cfg = Object.assign(cfg, JSON.parse(el.textContent || '{}'));
            } catch (e) { /* ignore */ }
            return {
                messages: (cfg.messages || []).slice(),
                dismissEp: cfg.dismiss,
                deleteEp: cfg['delete'],
                token: cfg.csrf,
                viewing: null,
                busy: {},
                removing: {},
                get unreadCount() {
                    return this.messages.filter(function(m){ return !m.is_read; }).length;
                },
                snippet(body) {
                    body = String(body || '').replace(/\s+/g,' ').trim();
                    return body.length > 160 ? body.slice(0, 160) + '\u2026' : body;
                },
                async open(m) {
                    this.viewing = m;
                    if (m.is_read) return;
                    try {
                        const body = new URLSearchParams();
                        body.set('message_id', String(m.id));
                        body.set('token', this.token);
                        const r = await fetch(this.dismissEp, {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                            body: body.toString()
                        });
                        const j = await r.json().catch(function(){ return {ok:false}; });
                        if (j && j.ok) m.is_read = true;
                    } catch (e) { /* silent */ }
                },
                close() { this.viewing = null; },
                async del(m) {
                    if (this.busy[m.id]) return false;
                    this.busy[m.id] = true;
                    try {
                        const body = new URLSearchParams();
                        body.set('message_id', String(m.id));
                        body.set('token', this.token);
                        const r = await fetch(this.deleteEp, {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                            body: body.toString()
                        });
                        const j = await r.json().catch(function(){ return {ok:false}; });
                        if (j && j.ok) {
                            this.removing[m.id] = true;
                            setTimeout(() => {
                                this.messages = this.messages.filter(function(x){ return x.id !== m.id; });
                                if (this.viewing && this.viewing.id === m.id) this.viewing = null;
                            }, 220);
                        }
                    } catch (e) { /* silent */ }
                    finally { this.busy[m.id] = false; }
                    return true;
                }
            };
        });
    }
    document.addEventListener('alpine:init', init);
    init();
})();
</script>
{/literal}
