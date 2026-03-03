<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(($branding['name'] ?? 'Portal') . ' Portal') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
    <header class="border-b border-slate-800 bg-slate-900/70">
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <div class="font-semibold text-white">
                <?= htmlspecialchars($branding['name'] ?? 'Portal') ?>
            </div>
            <nav class="flex items-center gap-2 text-sm">
                <a href="index.php?page=dashboard" class="px-3 py-2 rounded <?= ($page ?? '') === 'dashboard' ? 'bg-slate-700 text-white' : 'text-slate-300 hover:text-white' ?>">Dashboard</a>
                <a href="index.php?page=settings" class="px-3 py-2 rounded <?= ($page ?? '') === 'settings' ? 'bg-slate-700 text-white' : 'text-slate-300 hover:text-white' ?>">Settings</a>
                <a href="index.php?page=billing" class="px-3 py-2 rounded <?= ($page ?? '') === 'billing' ? 'bg-slate-700 text-white' : 'text-slate-300 hover:text-white' ?>">Billing</a>
            </nav>
        </div>
    </header>

    <main>
        <?php
        $templatePath = __DIR__ . '/' . basename((string) ($template ?? ''));
        if (is_file($templatePath)) {
            include $templatePath;
        }
        ?>
    </main>
</body>
</html>
