<div class="container mx-auto px-4 py-8 space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-white">Cloud Storage</h1>
        <p class="text-slate-400 text-sm">Manage storage access context for linked storage users.</p>
    </div>

    <section class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <a href="index.php?m=cloudstorage&page=access_keys" target="_blank" rel="noopener" class="bg-slate-900/70 border border-slate-800 rounded-xl p-4 hover:border-sky-500/50 transition">
            <div class="text-lg font-medium text-white">Access Keys</div>
            <div class="text-sm text-slate-400 mt-1">Open access keys manager in Cloud Storage.</div>
        </a>
        <a href="index.php?m=cloudstorage&page=buckets" target="_blank" rel="noopener" class="bg-slate-900/70 border border-slate-800 rounded-xl p-4 hover:border-sky-500/50 transition">
            <div class="text-lg font-medium text-white">Buckets</div>
            <div class="text-sm text-slate-400 mt-1">Open buckets page for object storage management.</div>
        </a>
    </section>

    <section class="bg-slate-900/70 border border-slate-800 rounded-xl p-4">
        <h2 class="text-lg font-medium text-white mb-3">Linked Storage Users</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-slate-200">
                <thead class="text-left text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="py-2 pr-4">Username</th>
                        <th class="py-2 pr-4">Email</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Actions</th>
                    </tr>
                </thead>
                <tbody id="cloud-storage-users-body">
                    <tr><td colspan="4" class="py-3 text-slate-400">Loading linked storage users...</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
(() => {
    const mspSlug = (new URLSearchParams(window.location.search)).get('msp');
    const apiUrl = (name) => mspSlug ? `index.php?api=${encodeURIComponent(name)}&msp=${encodeURIComponent(mspSlug)}` : `index.php?api=${encodeURIComponent(name)}`;
    const usersBody = document.getElementById('cloud-storage-users-body');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function safeHttps(url) {
        try {
            const parsed = new URL(String(url || ''), window.location.origin);
            if (parsed.origin === window.location.origin) {
                return parsed.href;
            }
        } catch (e) {}
        return '';
    }

    async function loadStorageUsers() {
        const response = await fetch(apiUrl('services'), { credentials: 'same-origin' });
        const payload = await response.json();
        if (!response.ok || !payload || payload.status !== 'success') {
            throw new Error((payload && payload.message) ? payload.message : 'Failed loading storage users');
        }

        const users = (((payload || {}).data || {}).storage_users || []);
        if (!Array.isArray(users) || users.length === 0) {
            usersBody.innerHTML = '<tr><td colspan="4" class="py-3 text-slate-400">No linked storage users.</td></tr>';
            return;
        }

        usersBody.innerHTML = users.map((user) => {
            const keysUrl = safeHttps(user.access_keys_url || 'index.php?m=cloudstorage&page=access_keys');
            const bucketsUrl = safeHttps(user.buckets_url || 'index.php?m=cloudstorage&page=buckets');
            const keysLink = keysUrl
                ? `<a href="${keysUrl}" target="_blank" rel="noopener" class="text-sky-300 hover:underline mr-3">Access Keys</a>`
                : '<span class="text-slate-500 mr-3">Access Keys</span>';
            const bucketsLink = bucketsUrl
                ? `<a href="${bucketsUrl}" target="_blank" rel="noopener" class="text-sky-300 hover:underline">Buckets</a>`
                : '<span class="text-slate-500">Buckets</span>';
            return `
                <tr class="border-b border-slate-800">
                    <td class="py-2 pr-4">${escapeHtml(user.username || '-')}</td>
                    <td class="py-2 pr-4">${escapeHtml(user.email || '-')}</td>
                    <td class="py-2 pr-4">${escapeHtml(user.status || '-')}</td>
                    <td class="py-2 pr-4">${keysLink}${bucketsLink}</td>
                </tr>
            `;
        }).join('');
    }

    loadStorageUsers().catch((err) => {
        usersBody.innerHTML = `<tr><td colspan="4" class="py-3 text-rose-300">${escapeHtml(err.message || 'Failed loading storage users')}</td></tr>`;
    });
})();
</script>
