<div class="container mx-auto px-4 py-12 max-w-lg">
    <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-8 shadow-xl">
        <h1 class="text-2xl font-semibold text-white mb-2">Sign in</h1>
        <p class="text-sm text-slate-400 mb-6">Access your backup portal.</p>
        <form id="portal-login-form" class="space-y-4">
            <div>
                <label class="block text-sm text-slate-300 mb-1">Email</label>
                <input id="portal-login-email" type="email" required class="w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-sm text-slate-300 mb-1">Password</label>
                <input id="portal-login-password" type="password" required class="w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div id="portal-login-error" class="text-sm text-rose-300 hidden"></div>
            <button type="submit" class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white py-2 font-semibold">Sign in</button>
        </form>
    </div>
</div>

<script>
(() => {
    const form = document.getElementById('portal-login-form');
    const email = document.getElementById('portal-login-email');
    const password = document.getElementById('portal-login-password');
    const error = document.getElementById('portal-login-error');
    const csrfToken = <?= json_encode($csrf ?? '') ?>;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        error.classList.add('hidden');
        error.textContent = '';

        try {
            const body = new URLSearchParams({
                email: email.value.trim(),
                password: password.value
            });
            const response = await fetch('index.php?api=login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-CSRF-Token': csrfToken
                },
                body
            });
            const payload = await response.json();
            if (!response.ok || !payload || payload.status !== 'success') {
                throw new Error((payload && payload.message) ? payload.message : 'Login failed');
            }
            window.location.href = 'index.php?page=dashboard';
        } catch (e) {
            error.textContent = e.message || 'Login failed';
            error.classList.remove('hidden');
        }
    });
})();
</script>
