<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-semibold text-white mb-2">Portal Dashboard</h1>
    <p class="text-slate-300">Welcome<?= !empty($session['name']) ? ', ' . htmlspecialchars((string) $session['name']) : '' ?>.</p>
    <p class="text-slate-400 mt-2">Use the navigation to view billing and account settings.</p>
</div>
