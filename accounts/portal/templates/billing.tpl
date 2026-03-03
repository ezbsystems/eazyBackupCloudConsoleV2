<div class="container mx-auto px-4 py-8 space-y-6">
    <h1 class="text-2xl font-semibold text-white">Billing</h1>

    <section class="bg-slate-900/70 border border-slate-800 rounded-xl p-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-medium text-white">Invoices</h2>
            <button id="billing-refresh-invoices" class="rounded bg-orange-500 px-3 py-2 text-sm font-medium text-white hover:bg-orange-400">Refresh</button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-slate-200">
                <thead class="text-left text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="py-2 pr-4">Invoice</th>
                        <th class="py-2 pr-4">Amount</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Created</th>
                        <th class="py-2 pr-4">Actions</th>
                    </tr>
                </thead>
                <tbody id="billing-invoices-body">
                    <tr><td colspan="5" class="py-3 text-slate-400">Loading invoices...</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="bg-slate-900/70 border border-slate-800 rounded-xl p-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-medium text-white">Payment Activity</h2>
            <button id="billing-refresh-payment-methods" class="rounded bg-orange-500 px-3 py-2 text-sm font-medium text-white hover:bg-orange-400">Refresh</button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-slate-200">
                <thead class="text-left text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="py-2 pr-4">Payment Intent</th>
                        <th class="py-2 pr-4">Amount</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Created</th>
                    </tr>
                </thead>
                <tbody id="billing-payment-methods-body">
                    <tr><td colspan="4" class="py-3 text-slate-400">Loading payment activity...</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
(() => {
    const mspSlug = (new URLSearchParams(window.location.search)).get('msp');
    const apiUrl = (name) => mspSlug ? `index.php?api=${encodeURIComponent(name)}&msp=${encodeURIComponent(mspSlug)}` : `index.php?api=${encodeURIComponent(name)}`;
    const invoicesBody = document.getElementById('billing-invoices-body');
    const paymentMethodsBody = document.getElementById('billing-payment-methods-body');
    const refreshInvoices = document.getElementById('billing-refresh-invoices');
    const refreshPaymentMethods = document.getElementById('billing-refresh-payment-methods');

    function formatMoney(cents, currency) {
        const amount = Number(cents || 0) / 100;
        return `${amount.toFixed(2)} ${String(currency || 'USD').toUpperCase()}`;
    }

    function formatDate(epoch) {
        if (!epoch) return '-';
        const d = new Date(Number(epoch) * 1000);
        return Number.isNaN(d.getTime()) ? '-' : d.toLocaleString();
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function safeUrl(value) {
        if (!value) return '';
        try {
            const url = new URL(String(value), window.location.origin);
            if (url.protocol === 'https:' || url.protocol === 'http:') {
                return url.href;
            }
        } catch (error) {}
        return '';
    }

    async function loadInvoices() {
        invoicesBody.innerHTML = '<tr><td colspan="5" class="py-3 text-slate-400">Loading invoices...</td></tr>';
        const response = await fetch(apiUrl('invoices'), { credentials: 'same-origin' });
        const payload = await response.json();
        const invoices = (((payload || {}).data || {}).invoices || []);

        if (!response.ok || (payload || {}).status !== 'success') {
            const message = (payload && payload.message) ? payload.message : 'Failed loading invoices';
            throw new Error(message);
        }

        if (!Array.isArray(invoices) || invoices.length === 0) {
            invoicesBody.innerHTML = '<tr><td colspan="5" class="py-3 text-slate-400">No invoices found.</td></tr>';
            return;
        }

        invoicesBody.innerHTML = invoices.map((invoice) => {
            const hosted = safeUrl(invoice.hosted_invoice_url || '');
            const sendUrl = safeUrl(invoice.send_url || invoice.hosted_invoice_url || '');
            const downloadUrl = safeUrl(invoice.download_url || invoice.hosted_invoice_url || '');
            const sendLink = sendUrl ? `<a class="text-sky-300 hover:underline mr-3" href="${sendUrl}" target="_blank" rel="noopener">Send invoice</a>` : '<span class="text-slate-500 mr-3">Send invoice</span>';
            const downloadLink = downloadUrl ? `<a class="text-sky-300 hover:underline" href="${downloadUrl}" target="_blank" rel="noopener">Download</a>` : '<span class="text-slate-500">Download</span>';
            const invoiceId = escapeHtml(invoice.stripe_invoice_id || '-');
            const invoiceStatus = escapeHtml(invoice.status || '-');
            const invoiceLabel = hosted ? `<a class="text-sky-300 hover:underline" href="${hosted}" target="_blank" rel="noopener">${invoiceId}</a>` : invoiceId;
            return `
                <tr class="border-b border-slate-800">
                    <td class="py-2 pr-4">${invoiceLabel}</td>
                    <td class="py-2 pr-4">${escapeHtml(formatMoney(invoice.amount_total, invoice.currency))}</td>
                    <td class="py-2 pr-4">${invoiceStatus}</td>
                    <td class="py-2 pr-4">${escapeHtml(formatDate(invoice.created))}</td>
                    <td class="py-2 pr-4">${sendLink}${downloadLink}</td>
                </tr>
            `;
        }).join('');
    }

    async function loadPaymentMethods() {
        paymentMethodsBody.innerHTML = '<tr><td colspan="4" class="py-3 text-slate-400">Loading payment activity...</td></tr>';
        const response = await fetch(apiUrl('payment_methods'), { credentials: 'same-origin' });
        const payload = await response.json();
        const paymentMethods = (((payload || {}).data || {}).payment_methods || []);

        if (!response.ok || (payload || {}).status !== 'success') {
            const message = (payload && payload.message) ? payload.message : 'Failed loading payment activity';
            throw new Error(message);
        }

        if (!Array.isArray(paymentMethods) || paymentMethods.length === 0) {
            paymentMethodsBody.innerHTML = '<tr><td colspan="4" class="py-3 text-slate-400">No payment activity found.</td></tr>';
            return;
        }

        paymentMethodsBody.innerHTML = paymentMethods.map((method) => `
            <tr class="border-b border-slate-800">
                <td class="py-2 pr-4">${escapeHtml(method.stripe_payment_intent_id || '-')}</td>
                <td class="py-2 pr-4">${escapeHtml(formatMoney(method.amount, method.currency))}</td>
                <td class="py-2 pr-4">${escapeHtml(method.status || '-')}</td>
                <td class="py-2 pr-4">${escapeHtml(formatDate(method.created))}</td>
            </tr>
        `).join('');
    }

    async function refreshAll() {
        try {
            await Promise.all([loadInvoices(), loadPaymentMethods()]);
        } catch (error) {
            const message = (error && error.message) ? error.message : 'Request failed';
            const safeMessage = escapeHtml(message);
            invoicesBody.innerHTML = `<tr><td colspan="5" class="py-3 text-rose-300">${safeMessage}</td></tr>`;
            paymentMethodsBody.innerHTML = `<tr><td colspan="4" class="py-3 text-rose-300">${safeMessage}</td></tr>`;
        }
    }

    refreshInvoices.addEventListener('click', () => {
        loadInvoices().catch((error) => {
            const message = (error && error.message) ? error.message : 'Request failed';
            invoicesBody.innerHTML = `<tr><td colspan="5" class="py-3 text-rose-300">${escapeHtml(message)}</td></tr>`;
        });
    });

    refreshPaymentMethods.addEventListener('click', () => {
        loadPaymentMethods().catch((error) => {
            const message = (error && error.message) ? error.message : 'Request failed';
            paymentMethodsBody.innerHTML = `<tr><td colspan="4" class="py-3 text-rose-300">${escapeHtml(message)}</td></tr>`;
        });
    });

    refreshAll();
})();
</script>
