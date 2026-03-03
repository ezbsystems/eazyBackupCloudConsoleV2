<div class="container mx-auto px-4 py-8 space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-white">Services</h1>
            <p class="text-slate-400 text-sm">View active services and request cancellation at period end.</p>
        </div>
        <a href="index.php?page=cloud_storage<?= !empty($_GET['msp']) ? '&msp=' . rawurlencode((string) $_GET['msp']) : '' ?>" class="rounded bg-sky-600 px-3 py-2 text-sm font-medium text-white hover:bg-sky-500">
            Open Cloud Storage
        </a>
    </div>

    <section class="bg-slate-900/70 border border-slate-800 rounded-xl p-4">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-slate-200">
                <thead class="text-left text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="py-2 pr-4">Service</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Billing</th>
                        <th class="py-2 pr-4">Started</th>
                        <th class="py-2 pr-4">Actions</th>
                    </tr>
                </thead>
                <tbody id="services-table-body">
                    <tr><td colspan="5" class="py-3 text-slate-400">Loading services...</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
(() => {
    const csrfToken = <?= json_encode($csrf ?? '') ?>;
    const mspSlug = (new URLSearchParams(window.location.search)).get('msp');
    const apiUrl = (name) => mspSlug ? `index.php?api=${encodeURIComponent(name)}&msp=${encodeURIComponent(mspSlug)}` : `index.php?api=${encodeURIComponent(name)}`;
    const tableBody = document.getElementById('services-table-body');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatPrice(service) {
        const amount = Number(service.unit_amount || 0) / 100;
        const currency = String(service.currency || 'USD').toUpperCase();
        if (!service.interval) {
            return `${amount.toFixed(2)} ${currency}`;
        }
        const count = Number(service.interval_count || 1);
        const suffix = count > 1 ? `${count} ${service.interval}s` : service.interval;
        return `${amount.toFixed(2)} ${currency} / ${suffix}`;
    }

    async function loadServices() {
        const response = await fetch(apiUrl('services'), { credentials: 'same-origin' });
        const payload = await response.json();
        if (!response.ok || !payload || payload.status !== 'success') {
            throw new Error((payload && payload.message) ? payload.message : 'Failed loading services');
        }
        const services = (((payload || {}).data || {}).services || []);
        if (!Array.isArray(services) || services.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5" class="py-3 text-slate-400">No services found.</td></tr>';
            return;
        }

        tableBody.innerHTML = services.map((service) => {
            const status = escapeHtml(service.status || '-');
            const canCancel = (service.status || '').toLowerCase() === 'active' && Number(service.cancel_at_period_end || 0) !== 1;
            const cancelBtn = canCancel
                ? `<button data-subscription-id="${Number(service.id || 0)}" class="service-cancel-btn rounded bg-rose-600 px-2 py-1 text-xs font-medium text-white hover:bg-rose-500">Request Cancel</button>`
                : '<span class="text-slate-500 text-xs">No action</span>';
            return `
                <tr class="border-b border-slate-800">
                    <td class="py-2 pr-4">
                        <div class="font-medium text-slate-100">${escapeHtml(service.name || 'Service')}</div>
                        <div class="text-xs text-slate-500">${escapeHtml(service.stripe_subscription_id || '')}</div>
                    </td>
                    <td class="py-2 pr-4">${status}</td>
                    <td class="py-2 pr-4">${escapeHtml(formatPrice(service))}</td>
                    <td class="py-2 pr-4">${escapeHtml(service.started_at || '-')}</td>
                    <td class="py-2 pr-4">${cancelBtn}</td>
                </tr>
            `;
        }).join('');

        for (const button of document.querySelectorAll('.service-cancel-btn')) {
            button.addEventListener('click', async () => {
                const subscriptionId = Number(button.getAttribute('data-subscription-id') || 0);
                if (!subscriptionId) return;
                button.disabled = true;
                try {
                    const response = await fetch(apiUrl('services'), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-Token': csrfToken,
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                        },
                        body: new URLSearchParams({
                            action: 'cancel_request',
                            subscription_id: String(subscriptionId)
                        })
                    });
                    const payload = await response.json();
                    if (!response.ok || !payload || payload.status !== 'success') {
                        throw new Error((payload && payload.message) ? payload.message : 'Cancel request failed');
                    }
                    await loadServices();
                } catch (err) {
                    button.disabled = false;
                    alert(err.message || 'Cancel request failed');
                }
            });
        }
    }

    loadServices().catch((err) => {
        tableBody.innerHTML = `<tr><td colspan="5" class="py-3 text-rose-300">${escapeHtml(err.message || 'Failed loading services')}</td></tr>`;
    });
})();
</script>
