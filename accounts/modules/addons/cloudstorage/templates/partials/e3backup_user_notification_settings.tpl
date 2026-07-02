<div class="eb-subpanel" style="margin-bottom: 20px;">
    <div class="flex items-center justify-between" style="margin-bottom: 8px;">
        <h2 class="eb-section-title !mb-0">Email notifications</h2>
    </div>
    <p class="eb-type-caption" style="margin-bottom: 16px;">
        Backup job report emails for this user. Individual jobs can still override recipients via API.
    </p>

    <div x-show="notificationMessage" x-cloak class="eb-alert eb-alert--success" style="margin-bottom: 12px;" role="status">
        <div x-text="notificationMessage"></div>
    </div>
    <div x-show="notificationError" x-cloak class="eb-alert eb-alert--danger" style="margin-bottom: 12px;" role="alert">
        <div x-text="notificationError"></div>
    </div>

    <div class="space-y-4">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="eb-field-label !mb-1">Enable notifications</p>
                <p class="eb-type-caption">Send backup report emails for this user.</p>
            </div>
            <button type="button" class="eb-toggle shrink-0" @click="notificationForm.enabled = !notificationForm.enabled" :aria-pressed="notificationForm.enabled">
                <div class="eb-toggle-track" :class="notificationForm.enabled && 'is-on'">
                    <div class="eb-toggle-thumb"></div>
                </div>
                <span class="eb-toggle-label" x-text="notificationForm.enabled ? 'On' : 'Off'"></span>
            </button>
        </div>

        <div x-show="notificationForm.enabled" x-transition class="space-y-4" style="display: none;">
            <div>
                <label class="eb-field-label" for="e3-notify-email-input">Recipients</label>
                <p class="eb-type-caption" style="margin-bottom: 8px;">
                    If empty, reports go to this user&rsquo;s profile email, then the account owner email.
                </p>
                <div class="flex flex-wrap gap-2" style="margin-bottom: 8px;" x-show="notificationForm.emails.length">
                    <template x-for="(email, index) in notificationForm.emails" :key="email + '-' + index">
                        <span class="eb-badge eb-badge--neutral inline-flex items-center gap-1">
                            <span x-text="email"></span>
                            <button type="button"
                                    class="eb-btn eb-btn-ghost eb-btn-xs"
                                    style="min-width: auto; padding: 0 4px;"
                                    @click="removeNotificationEmail(index)"
                                    :aria-label="'Remove ' + email">&times;</button>
                        </span>
                    </template>
                </div>
                <div class="flex flex-wrap items-start gap-2">
                    <input id="e3-notify-email-input"
                           type="email"
                           x-model.trim="newNotifyEmail"
                           class="eb-input"
                           style="min-width: 220px; flex: 1 1 220px;"
                           placeholder="name@example.com"
                           :disabled="!notificationForm.enabled"
                           @keydown.enter.prevent="addNotificationEmail()">
                    <button type="button"
                            class="eb-btn eb-btn-secondary eb-btn-sm"
                            @click="addNotificationEmail()"
                            :disabled="!notificationForm.enabled">
                        Add
                    </button>
                </div>
                <p class="eb-field-error" x-show="notificationErrors.notify_emails" x-text="notificationErrors.notify_emails"></p>
            </div>

            <div>
                <p class="eb-field-label" style="margin-bottom: 8px;">Notify on</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-700/60 bg-slate-900/30 px-3 py-2">
                        <span class="text-sm">Success</span>
                        <button type="button"
                                class="eb-toggle shrink-0"
                                @click="notificationForm.notify_on_success = !notificationForm.notify_on_success"
                                :disabled="!notificationForm.enabled"
                                :aria-pressed="notificationForm.notify_on_success">
                            <div class="eb-toggle-track" :class="notificationForm.notify_on_success && 'is-on'">
                                <div class="eb-toggle-thumb"></div>
                            </div>
                        </button>
                    </div>
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-700/60 bg-slate-900/30 px-3 py-2">
                        <span class="text-sm">Warning</span>
                        <button type="button"
                                class="eb-toggle shrink-0"
                                @click="notificationForm.notify_on_warning = !notificationForm.notify_on_warning"
                                :disabled="!notificationForm.enabled"
                                :aria-pressed="notificationForm.notify_on_warning">
                            <div class="eb-toggle-track" :class="notificationForm.notify_on_warning && 'is-on'">
                                <div class="eb-toggle-thumb"></div>
                            </div>
                        </button>
                    </div>
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-700/60 bg-slate-900/30 px-3 py-2">
                        <span class="text-sm">Failure</span>
                        <button type="button"
                                class="eb-toggle shrink-0"
                                @click="notificationForm.notify_on_failure = !notificationForm.notify_on_failure"
                                :disabled="!notificationForm.enabled"
                                :aria-pressed="notificationForm.notify_on_failure">
                            <div class="eb-toggle-track" :class="notificationForm.notify_on_failure && 'is-on'">
                                <div class="eb-toggle-thumb"></div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <button type="button"
                    class="eb-btn eb-btn-primary"
                    @click="saveNotificationSettings()"
                    :disabled="savingNotifications">
                <span x-show="!savingNotifications">Save notifications</span>
                <span x-show="savingNotifications">Saving…</span>
            </button>
        </div>
    </div>
</div>
