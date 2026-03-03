<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(($branding['company_name'] ?? $branding['name'] ?? 'Portal') . ' Portal') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
    <header class="border-b border-slate-800 bg-slate-900/70">
        <?php $mspSuffix = !empty($_GET['msp']) ? '&msp=' . rawurlencode((string) $_GET['msp']) : ''; ?>
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <div class="font-semibold text-white">
                <?= htmlspecialchars($branding['company_name'] ?? $branding['name'] ?? 'Portal') ?>
            </div>
            <nav class="flex items-center gap-2 text-sm">
                <a href="index.php?page=dashboard<?= $mspSuffix ?>" class="px-3 py-2 rounded <?= ($page ?? '') === 'dashboard' ? 'bg-slate-700 text-white' : 'text-slate-300 hover:text-white' ?>">Dashboard</a>
                <a href="index.php?page=services<?= $mspSuffix ?>" class="px-3 py-2 rounded <?= ($page ?? '') === 'services' ? 'bg-slate-700 text-white' : 'text-slate-300 hover:text-white' ?>">Services</a>
                <a href="index.php?page=cloud_storage<?= $mspSuffix ?>" class="px-3 py-2 rounded <?= ($page ?? '') === 'cloud_storage' ? 'bg-slate-700 text-white' : 'text-slate-300 hover:text-white' ?>">Cloud Storage</a>
                <a href="index.php?page=settings<?= $mspSuffix ?>" class="px-3 py-2 rounded <?= ($page ?? '') === 'settings' ? 'bg-slate-700 text-white' : 'text-slate-300 hover:text-white' ?>">Settings</a>
                <a href="index.php?page=billing<?= $mspSuffix ?>" class="px-3 py-2 rounded <?= ($page ?? '') === 'billing' ? 'bg-slate-700 text-white' : 'text-slate-300 hover:text-white' ?>">Billing</a>
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
