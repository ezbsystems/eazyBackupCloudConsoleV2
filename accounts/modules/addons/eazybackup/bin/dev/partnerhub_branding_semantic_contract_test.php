<?php

declare(strict_types=1);

/**
 * Contract test: Partner Hub branding.tpl semantic migration markers.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_branding_semantic_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$path = $moduleRoot . '/templates/whitelabel/branding.tpl';

$source = @file_get_contents($path);
if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read branding.tpl\n");
    exit(1);
}

$failures = [];

// --- Surfaced section markers (stable data attributes on each logical panel) ---
$sectionMarkers = [
    'system branding section' => 'data-eb-ph-section="system-branding"',
    'backup agent branding section' => 'data-eb-ph-section="backup-agent-branding"',
    'email reporting section' => 'data-eb-ph-section="email-reporting"',
    'hostname section' => 'data-eb-ph-section="hostname"',
    'tenant status section' => 'data-eb-ph-section="tenant-status"',
];
foreach ($sectionMarkers as $label => $needle) {
    if (strpos($source, $needle) === false) {
        $failures[] = "FAIL: missing {$label}: {$needle}";
    }
}

// --- Stable DOM ids consumed by form submission, JS hooks, and toast rendering ---
$requiredIds = [
    'branding form' => 'id="brandingForm"',
    'toast container' => 'id="toast-container"',
    'custom domain host input' => 'id="eb-cd-host"',
    'custom domain check button' => 'id="eb-cd-check"',
    'custom domain attach button' => 'id="eb-cd-attach"',
    'custom domain loader' => 'id="eb-cd-loader"',
    'custom domain loader text' => 'id="eb-cd-loader-text"',
    'custom domain status' => 'id="eb-cd-status"',
    'header color input' => 'id="header_color"',
    'header color picker' => 'id="header_color_picker"',
    'accent color input' => 'id="accent_color"',
    'accent color picker' => 'id="accent_color_picker"',
    'tile background input' => 'id="tile_background"',
    'tile background picker' => 'id="tile_background_picker"',
];
foreach ($requiredIds as $label => $needle) {
    if (strpos($source, $needle) === false) {
        $failures[] = "FAIL: missing {$label}: {$needle}";
    }
}

// --- Core semantic field/control classes (Partner Hub form vocabulary) ---
$semanticClassMarkers = [
    'eb-field-label usage' => 'eb-field-label',
    'eb-input usage' => 'eb-input',
    'eb-select usage' => 'eb-select',
    'eb-textarea usage' => 'eb-textarea',
    'eb-file-field usage' => 'eb-file-field',
    'eb-file-field__control usage' => 'eb-file-field__control',
    'eb-file-field__input usage' => 'eb-file-field__input',
    'eb-file-field__main usage' => 'eb-file-field__main',
    'eb-file-field__button usage' => 'eb-file-field__button',
    'eb-file-field__name usage' => 'eb-file-field__name',
];
foreach ($semanticClassMarkers as $label => $classToken) {
    $pattern = '#\bclass\s*=\s*["\'][^"\']*\b' . preg_quote($classToken, '#') . '\b[^"\']*["\']#i';
    if (!preg_match($pattern, $source)) {
        $failures[] = "FAIL: missing {$label}";
    }
}

// --- File inputs: full semantic file-field pattern with preserved name + accept ---
$fileUploads = [
    ['name' => 'header_image_file', 'accept' => '.jpg,.jpeg,.gif,.png,.svg'],
    ['name' => 'favicon_file', 'accept' => '.ico'],
    ['name' => 'win_ico_file', 'accept' => '.ico,.jpg,.jpeg,.gif,.png'],
    ['name' => 'mac_icns_file', 'accept' => '.ico,.jpg,.jpeg,.gif,.png'],
    ['name' => 'mac_menubar_icns_file', 'accept' => '.ico,.jpg,.jpeg,.gif,.png'],
    ['name' => 'logo_file', 'accept' => '.jpg,.jpeg,.gif,.png,.svg'],
    ['name' => 'tile_image_file', 'accept' => '.jpg,.jpeg,.gif,.png,.svg'],
    ['name' => 'app_icon_file', 'accept' => '.jpg,.jpeg,.gif,.png,.svg'],
    ['name' => 'eula_file', 'accept' => '.rtf,.txt,.pdf'],
];
foreach ($fileUploads as $spec) {
    $name = preg_quote($spec['name'], '#');
    $accept = preg_quote($spec['accept'], '#');
    $pattern = '#<div[^>]*\bclass=["\'][^"\']*\beb-file-field\b[^"\']*["\'][^>]*>'
        . '[\s\S]{0,1200}?<div[^>]*\bclass=["\'][^"\']*\beb-file-field__control\b[^"\']*["\'][^>]*>'
        . '[\s\S]{0,800}?<[^>]+class=["\'][^"\']*\beb-file-field__main\b[^"\']*["\'][^>]*>'
        . '[\s\S]{0,400}?<[^>]+class=["\'][^"\']*\beb-file-field__button\b[^"\']*["\'][^>]*>'
        . '[\s\S]{0,400}?<[^>]+class=["\'][^"\']*\beb-file-field__name\b[^"\']*["\'][^>]*>'
        . '[\s\S]{0,800}?<input(?=[^>]*\btype=["\']file["\'])'
        . '(?=[^>]*\bname=["\']' . $name . '["\'])'
        . '(?=[^>]*\baccept=["\']' . $accept . '["\'])'
        . '(?=[^>]*\bclass=["\'][^"\']*\beb-file-field__input\b[^"\']*\beb-file-input\b[^"\']*["\'])'
        . '[^>]*>[\s\S]{0,1200}?</div>#i';
    if (!preg_match($pattern, $source)) {
        $failures[] = 'FAIL: file upload control not migrated (need eb-file-field > eb-file-field__control with eb-file-field__main/button/name plus the real input[type=file] carrying name="' . $spec['name'] . '", accept="' . $spec['accept'] . '", and eb-file-field__input eb-file-input)';
    }
}

// --- Toggle: parent mail server (must use semantic toggle, not raw checkbox styling) ---
if (strpos($source, 'name="use_parent_mail"') === false) {
    $failures[] = 'FAIL: missing name="use_parent_mail" (regression guard)';
} elseif (!preg_match(
    '#<button[^>]*\bclass="[^"]*\beb-toggle\b[^"]*"[^>]*>[\s\S]{0,800}?name="use_parent_mail"#i',
    $source
) && !preg_match(
    '#name="use_parent_mail"[\s\S]{0,800}?\beb-toggle-track\b#i',
    $source
)) {
    $failures[] = 'FAIL: use_parent_mail must be wired with eb-toggle / eb-toggle-track structure';
}

// --- Custom domain actions: semantic button markers (ids stay for JS; classes must include eb-btn) ---
if (strpos($source, 'id="eb-cd-check"') !== false
    && !preg_match('#<button(?=[^>]*\bid=["\']eb-cd-check["\'])(?=[^>]*\bclass=["\'][^"\']*\beb-btn\b[^"\']*["\'])[^>]*>#i', $source)) {
    $failures[] = 'FAIL: custom domain Check DNS button must include eb-btn semantic class';
}
if (strpos($source, 'id="eb-cd-attach"') !== false
    && !preg_match('#<button(?=[^>]*\bid=["\']eb-cd-attach["\'])(?=[^>]*\bclass=["\'][^"\']*\beb-btn\b[^"\']*["\'])[^>]*>#i', $source)) {
    $failures[] = 'FAIL: custom domain Attach button must include eb-btn semantic class';
}

// --- Denylist: legacy Tailwind skin stacks that should leave migrated form sections ---
$forbidden = [
    'legacy section card shell' => 'rounded-2xl border border-slate-800/80 bg-slate-900/70',
    'legacy slate input chrome bundle' => 'w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700',
    'legacy block label stack' => 'block text-slate-400 mb-1 text-sm',
    'legacy brittle h3 selector' => 'h3.text-lg.font-semibold.text-white.mb-3',
];
foreach ($forbidden as $label => $needle) {
    if (strpos($source, $needle) !== false) {
        $failures[] = "FAIL: still contains legacy stack ({$label})";
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "partnerhub-branding-semantic-contract-ok\n";
exit(0);
