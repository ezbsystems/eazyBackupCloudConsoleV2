<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(($branding['company_name'] ?? $branding['name'] ?? 'Portal') . ' Portal') ?></title>
    <?php if (!empty($branding['favicon_url'])): ?>
    <link rel="icon" href="<?= htmlspecialchars($branding['favicon_url']) ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php
        $primaryColor = $branding['primary_color'] ?? '#FE5000';
        $accentColor  = $branding['accent_color'] ?? '#1B2C50';
    ?>
    <style>
        :root {
            --portal-primary: <?= htmlspecialchars($primaryColor) ?>;
            --portal-accent: <?= htmlspecialchars($accentColor) ?>;
        }
        .portal-nav-active { background-color: var(--portal-primary); color: #fff; }
        .portal-nav-active:hover { opacity: 0.9; }
        .portal-btn-primary { background-color: var(--portal-primary); }
        .portal-btn-primary:hover { opacity: 0.9; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col">
    <?php if (!empty($_SESSION['portal_impersonated_by'])): ?>
    <div class="bg-amber-600/90 text-white text-sm py-2.5 px-4 flex items-center justify-center gap-3 flex-wrap">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 shrink-0">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
        </svg>
        <span>You are viewing this portal as <strong><?= htmlspecialchars($session['name'] ?? $session['email'] ?? 'tenant') ?></strong>.</span>
        <a href="<?= htmlspecialchars($_SESSION['portal_impersonate_return'] ?? '/index.php?m=eazybackup&a=ph-tenants-manage') ?>" class="inline-flex items-center gap-1.5 rounded-md bg-white/20 px-3 py-1 text-xs font-semibold text-white hover:bg-white/30 transition">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
            </svg>
            Logout &amp; Return to Partner Hub
        </a>
    </div>
    <?php endif; ?>

    <header class="border-b border-slate-800 bg-slate-900/70">
        <?php $mspSuffix = !empty($_GET['msp']) ? '&msp=' . rawurlencode((string) $_GET['msp']) : ''; ?>
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <?php if (!empty($branding['logo_url'])): ?>
                <img src="<?= htmlspecialchars($branding['logo_url']) ?>" alt="" class="h-8 max-w-[160px] object-contain">
                <?php endif; ?>
                <span class="font-semibold text-white">
                    <?= htmlspecialchars($branding['company_name'] ?? $branding['name'] ?? 'Portal') ?>
                </span>
            </div>
            <nav class="flex items-center gap-1 text-sm flex-wrap">
                <a href="index.php?page=dashboard<?= $mspSuffix ?>" class="px-3 py-2 rounded <?= ($page ?? '') === 'dashboard' ? 'portal-nav-active' : 'text-slate-300 hover:text-white' ?>">Dashboard</a>
                <?php
                $portalPages = $branding['portal_pages'] ?? [];
                $navItems = [
                    'services'      => ['label' => 'Services',      'key' => 'show_services'],
                    'cloud_storage' => ['label' => 'Cloud Storage', 'key' => 'show_cloud_storage'],
                    'devices'       => ['label' => 'Devices',       'key' => 'show_devices'],
                    'jobs'          => ['label' => 'Jobs',          'key' => 'show_jobs'],
                    'restore'       => ['label' => 'Restore',       'key' => 'show_restore'],
                    'billing'       => ['label' => 'Billing',       'key' => 'show_billing'],
                ];
                foreach ($navItems as $slug => $meta):
                    $visible = !isset($portalPages[$meta['key']]) || $portalPages[$meta['key']];
                    if (!$visible) continue;
                ?>
                <a href="index.php?page=<?= $slug . $mspSuffix ?>" class="px-3 py-2 rounded <?= ($page ?? '') === $slug ? 'portal-nav-active' : 'text-slate-300 hover:text-white' ?>"><?= $meta['label'] ?></a>
                <?php endforeach; ?>
                <a href="index.php?page=settings<?= $mspSuffix ?>" class="px-3 py-2 rounded <?= ($page ?? '') === 'settings' ? 'portal-nav-active' : 'text-slate-300 hover:text-white' ?>">Settings</a>
            </nav>
        </div>
    </header>

    <main class="flex-1">
        <?php
        $templatePath = __DIR__ . '/' . basename((string) ($template ?? ''));
        if (is_file($templatePath)) {
            include $templatePath;
        }
        ?>
    </main>

    <footer class="border-t border-slate-800 py-4 text-center text-xs text-slate-500">
        <div class="container mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
            <div>
                <?php if (!empty($branding['footer_text'])): ?>
                    <?= htmlspecialchars($branding['footer_text']) ?>
                <?php else: ?>
                    &copy; <?= date('Y') ?> <?= htmlspecialchars($branding['company_name'] ?? 'Portal') ?>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-3">
                <?php if (!empty($branding['support_email'])): ?>
                <a href="mailto:<?= htmlspecialchars($branding['support_email']) ?>" class="text-slate-400 hover:text-white"><?= htmlspecialchars($branding['support_email']) ?></a>
                <?php endif; ?>
                <?php if (!empty($branding['support_url'])): ?>
                <a href="<?= htmlspecialchars($branding['support_url']) ?>" class="text-slate-400 hover:text-white" target="_blank" rel="noopener">Support</a>
                <?php endif; ?>
            </div>
        </div>
    </footer>
</body>
</html>
