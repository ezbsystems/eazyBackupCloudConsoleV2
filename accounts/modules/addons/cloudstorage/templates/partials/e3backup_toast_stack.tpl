{* Global e3 backup toast stack — semantic eb-toast variants per theme reference. *}
<div id="e3backup-toast-stack"
     x-data="e3backupToastStack()"
     x-init="init()"
     x-cloak
     class="pointer-events-none fixed top-4 right-4 z-[9999] flex flex-col gap-2"
     style="max-width: min(380px, calc(100vw - 2rem));"
     aria-live="polite"
     aria-relevant="additions">
    <template x-for="toast in toasts" :key="toast.id">
        <div :class="'eb-toast pointer-events-auto eb-toast--' + toast.variant"
             x-transition.opacity.duration.200ms
             role="status"
             @click="dismiss(toast.id)"
             style="cursor: pointer;">
            <svg class="w-4 h-4 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path x-show="toast.variant === 'success'" stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                <path x-show="toast.variant === 'danger'" stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                <path x-show="toast.variant === 'warning'" stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                <path x-show="toast.variant === 'info'" stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
            </svg>
            <span class="min-w-0 flex-1" x-text="toast.message"></span>
        </div>
    </template>
</div>

{literal}
<script>
if (typeof window.e3backupToastStack !== 'function') {
    window.e3backupToastStack = function e3backupToastStack() {
        return {
            toasts: [],
            _seq: 0,
            init() {
                const show = (type, message) => {
                    const msg = String(message || '').trim();
                    if (!msg) return;
                    const variant = (type === 'error' || type === 'danger') ? 'danger'
                        : (type === 'warning') ? 'warning'
                        : (type === 'info') ? 'info'
                        : 'success';
                    const id = ++this._seq;
                    this.toasts.push({ id: id, variant: variant, message: msg });
                    const ttl = variant === 'danger' ? 7000 : 4500;
                    setTimeout(() => this.dismiss(id), ttl);
                };
                window.e3backupShowToast = show;
                window.toast = {
                    success: (m) => show('success', m),
                    error: (m) => show('error', m),
                    info: (m) => show('info', m),
                    warning: (m) => show('warning', m),
                };
                const queued = window._e3backupToastQueue;
                if (Array.isArray(queued) && queued.length) {
                    window._e3backupToastQueue = [];
                    queued.forEach((item) => {
                        if (item && item.type) show(item.type, item.message);
                    });
                }
            },
            dismiss(id) {
                this.toasts = this.toasts.filter((t) => t.id !== id);
            },
        };
    };
}
</script>
{/literal}
