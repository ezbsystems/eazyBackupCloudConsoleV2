<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-semibold text-white mb-4">Settings</h1>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-4 space-y-4">
            <h2 class="text-lg font-medium text-white">Profile</h2>
            <form id="profile-form" class="space-y-3">
                <div>
                    <label for="profile-name" class="block text-sm text-slate-300 mb-1">Name</label>
                    <input id="profile-name" name="name" type="text" class="w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100" value="<?= htmlspecialchars($session['name'] ?? '') ?>" required>
                </div>
                <div>
                    <label for="profile-email" class="block text-sm text-slate-300 mb-1">Email</label>
                    <input id="profile-email" name="email" type="email" class="w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100" value="<?= htmlspecialchars($session['email'] ?? '') ?>" required>
                </div>
                <div class="text-xs text-slate-400">
                    <span class="text-slate-500">Role:</span> <?= htmlspecialchars($session['role'] ?? '') ?>
                    <span class="mx-2 text-slate-700">|</span>
                    <span class="text-slate-500">Tenant:</span> <?= htmlspecialchars($session['tenant_name'] ?? '') ?>
                </div>
                <button type="submit" class="rounded bg-orange-500 px-3 py-2 text-sm font-medium text-white hover:bg-orange-400">Save profile</button>
                <div id="profile-status" class="text-sm text-slate-300"></div>
            </form>
        </div>

        <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-4 space-y-4">
            <h2 class="text-lg font-medium text-white">Security</h2>
            <form id="password-form" class="space-y-3">
                <div>
                    <label for="current-password" class="block text-sm text-slate-300 mb-1">Current password</label>
                    <input id="current-password" name="current_password" type="password" class="w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100" required>
                </div>
                <div>
                    <label for="new-password" class="block text-sm text-slate-300 mb-1">New password</label>
                    <input id="new-password" name="new_password" type="password" class="w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100" minlength="8" required>
                </div>
                <div>
                    <label for="confirm-password" class="block text-sm text-slate-300 mb-1">Confirm new password</label>
                    <input id="confirm-password" name="confirm_password" type="password" class="w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100" minlength="8" required>
                </div>
                <button type="submit" class="rounded bg-orange-500 px-3 py-2 text-sm font-medium text-white hover:bg-orange-400">Change password</button>
                <div id="password-status" class="text-sm text-slate-300"></div>
            </form>
        </div>
    </div>
</div>

<script>
(() => {
    const csrfToken = <?= json_encode($csrf ?? '') ?>;
    const profileForm = document.getElementById('profile-form');
    const passwordForm = document.getElementById('password-form');
    const profileStatus = document.getElementById('profile-status');
    const passwordStatus = document.getElementById('password-status');

    function setStatus(el, message, ok) {
        el.textContent = message;
        el.className = ok ? 'text-sm text-emerald-300' : 'text-sm text-rose-300';
    }

    async function submitForm(url, form) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken,
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: new URLSearchParams(new FormData(form))
        });

        let payload = null;
        try {
            payload = await response.json();
        } catch (e) {
            payload = null;
        }

        if (!response.ok || !payload || payload.status !== 'success') {
            const message = payload && payload.message ? payload.message : 'Request failed';
            throw new Error(message);
        }

        return payload;
    }

    profileForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        setStatus(profileStatus, 'Saving profile...', true);

        try {
            await submitForm('index.php?api=profile_update', profileForm);
            setStatus(profileStatus, 'Profile updated.', true);
        } catch (error) {
            setStatus(profileStatus, error.message || 'Profile update failed.', false);
        }
    });

    passwordForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        setStatus(passwordStatus, 'Changing password...', true);

        try {
            await submitForm('index.php?api=change_password', passwordForm);
            passwordForm.reset();
            setStatus(passwordStatus, 'Password changed.', true);
        } catch (error) {
            setStatus(passwordStatus, error.message || 'Password change failed.', false);
        }
    });
})();
</script>
