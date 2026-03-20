<?php
declare(strict_types=1);

/**
 * Contract test: trial signup Turnstile render config.
 *
 * Run: php accounts/modules/addons/cloudstorage/bin/dev/turnstile_signup_render_contract_test.php
 */

$templatePath = dirname(__DIR__, 2) . '/templates/signup.tpl';
$source = @file_get_contents($templatePath);

if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read templates/signup.tpl\n");
    exit(1);
}

$failures = [];

if (strpos($source, "size: useInvisible ? 'invisible' : 'flexible'") === false) {
    $failures[] = "missing responsive visible Turnstile size marker";
}

if (strpos($source, "size: useInvisible ? 'invisible' : 'flex'") !== false) {
    $failures[] = "still contains invalid Turnstile size marker";
}

if (strpos($source, '<span class="text-xs font-medium text-[var(--eb-text-muted)]">Security verification</span>') !== false) {
    $failures[] = "still contains visible Turnstile helper label";
}

if (strpos($source, 'class="pointer-events-none fixed left-0 top-0 -z-10 h-0 w-0 overflow-hidden opacity-0"') === false) {
    $failures[] = "missing zero-height hidden Turnstile container marker";
}

if (strpos($source, 'class="pointer-events-none fixed left-0 top-0 -z-10 h-px w-px overflow-hidden opacity-0"') !== false) {
    $failures[] = "still contains non-zero hidden Turnstile container marker";
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "turnstile-signup-render-contract-ok\n";
exit(0);
