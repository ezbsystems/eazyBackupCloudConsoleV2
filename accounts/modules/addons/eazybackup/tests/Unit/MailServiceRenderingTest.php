<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Unit;

use EazyBackup\Tests\Support\UnitTestCase;
use PartnerHub\MailService;

/**
 * Coverage for MailService private rendering helpers — token replacement,
 * HTML sanitisation, and outer template wrapping.
 *
 * Source: lib/PartnerHub/MailService.php
 *
 * Risks this catches:
 *   - Stored-XSS via a tenant-supplied template body (script tags, on*
 *     handlers leaking into the rendered email).
 *   - Token leaks where {{ msp.brand.name }} renders as the literal placeholder
 *     because nested replacement is broken.
 *   - Markdown rendering producing broken HTML for legitimate inputs (bold,
 *     italic, links, multi-paragraph bodies).
 *
 * The targeted helpers are `private static`, so we reach in via Reflection.
 * That avoids changing visibility on production code purely for testability;
 * Reflection-on-private is acceptable for a tightly scoped sanitisation/render
 * surface that downstream code never calls directly.
 */
final class MailServiceRenderingTest extends UnitTestCase
{
    public function test_replace_tokens_substitutes_top_level_scalars(): void
    {
        $out = $this->invokePrivate('replaceTokens', [
            'Hello {{ name }}, your code is {{ code }}.',
            ['name' => 'Sam', 'code' => 'XYZ-42'],
        ]);
        self::assertSame('Hello Sam, your code is XYZ-42.', $out);
    }

    public function test_replace_tokens_substitutes_nested_customer_scope(): void
    {
        $out = $this->invokePrivate('replaceTokens', [
            'Hi {{ customer.name }}, your invoice {{ invoice.number }} is ready.',
            [
                'customer' => ['name' => 'Acme'],
                'invoice' => ['number' => 'EB-001'],
            ],
        ]);
        self::assertSame('Hi Acme, your invoice EB-001 is ready.', $out);
    }

    public function test_replace_tokens_supports_msp_brand_two_level_nesting(): void
    {
        $out = $this->invokePrivate('replaceTokens', [
            'From {{ msp.brand.name }} ({{ msp.support_email }})',
            [
                'msp' => [
                    'brand' => ['name' => 'Acme Backup'],
                    'support_email' => 'support@acme.test',
                ],
            ],
        ]);
        self::assertSame('From Acme Backup (support@acme.test)', $out);
    }

    public function test_replace_tokens_skips_array_values_at_top_level(): void
    {
        // Top-level array values should NOT be coerced to "Array" or stringified;
        // they're scoped namespaces handled by the customer/invoice/etc. branches.
        $out = $this->invokePrivate('replaceTokens', [
            'X = {{ x }}',
            ['x' => ['a' => 1]],
        ]);
        self::assertSame('X = {{ x }}', $out, 'Unresolved token must be left alone, not replaced with "Array".');
    }

    public function test_replace_tokens_leaves_unknown_tokens_intact(): void
    {
        $out = $this->invokePrivate('replaceTokens', [
            'Hi {{ customer.name }}, balance: {{ unknown }}',
            ['customer' => ['name' => 'Acme']],
        ]);
        self::assertSame('Hi Acme, balance: {{ unknown }}', $out);
    }

    public function test_sanitize_html_strips_script_tags(): void
    {
        $out = $this->invokePrivate('sanitizeHtml', [
            '<p>Hello</p><script>alert(1)</script><p>World</p>',
        ]);
        self::assertStringNotContainsString('<script', $out);
        self::assertStringNotContainsString('alert(1)', $out);
        self::assertStringContainsString('<p>Hello</p>', $out);
        self::assertStringContainsString('<p>World</p>', $out);
    }

    public function test_sanitize_html_strips_inline_event_handlers(): void
    {
        // Both quote styles, varied case, varied attribute names.
        $out = $this->invokePrivate('sanitizeHtml', [
            '<a href="https://eazybackup.ca" onclick="bad()" ONMOUSEOVER=\'oops()\' onload="x()">link</a>',
        ]);
        self::assertStringNotContainsString('onclick', $out);
        self::assertStringNotContainsString('onmouseover', strtolower($out));
        self::assertStringNotContainsString('onload', $out);
        self::assertStringContainsString('href="https://eazybackup.ca"', $out, 'Legitimate href must survive.');
    }

    public function test_sanitize_html_preserves_safe_markup(): void
    {
        $safe = '<p><strong>Hi</strong> <em>there</em>, click <a href="https://eazybackup.ca">here</a>.</p>';
        $out = $this->invokePrivate('sanitizeHtml', [$safe]);
        self::assertSame($safe, $out);
    }

    public function test_render_markdown_emphasis_and_link(): void
    {
        $out = $this->invokePrivate('renderMarkdownToHtml', [
            'Hello **bold** and *italic* and a [link](https://eazybackup.ca).',
        ]);
        self::assertStringContainsString('<strong>bold</strong>', $out);
        self::assertStringContainsString('<em>italic</em>', $out);
        self::assertStringContainsString('href="https://eazybackup.ca"', $out);
        self::assertStringContainsString('target="_blank"', $out);
        self::assertStringContainsString('rel="noopener noreferrer"', $out);
    }

    public function test_render_markdown_html_escapes_raw_html_input(): void
    {
        // The markdown renderer htmlspecialchars()-encodes the input before applying its
        // limited markdown rules. That's how it neutralises HTML injection attempts in
        // tenant-supplied template bodies.
        $out = $this->invokePrivate('renderMarkdownToHtml', [
            '<script>alert(1)</script>',
        ]);
        self::assertStringNotContainsString('<script>', $out);
        self::assertStringContainsString('&lt;script&gt;', $out);
    }

    public function test_render_template_body_includes_header_image_when_provided(): void
    {
        $out = $this->invokePrivate('renderTemplateBody', [
            'Hello',
            ['header_image' => 'https://cdn.example.test/logo.png', 'primary_color' => '#FF0000'],
        ]);
        self::assertStringContainsString('https://cdn.example.test/logo.png', $out);
        self::assertStringContainsString('#FF0000', $out);
        self::assertStringContainsString('Hello', $out);
    }

    public function test_render_template_body_omits_header_when_no_image(): void
    {
        $out = $this->invokePrivate('renderTemplateBody', [
            'Hello',
            ['primary_color' => '#1B2C50'],
        ]);
        self::assertStringNotContainsString('<img', $out);
        self::assertStringContainsString('#1B2C50', $out);
    }

    public function test_render_template_body_html_encodes_header_image_url(): void
    {
        // Defence: a header_image like `" onerror=...` must end up encoded inside the src attr.
        $out = $this->invokePrivate('renderTemplateBody', [
            'Body',
            ['header_image' => '"><script>alert(1)</script>', 'primary_color' => '#000'],
        ]);
        self::assertStringNotContainsString('<script>', $out);
        self::assertStringContainsString('&quot;', $out);
    }

    /**
     * Reach into MailService's `private static` helpers without changing their
     * visibility in production code. Limited to this test class.
     */
    private function invokePrivate(string $method, array $args)
    {
        $ref = new \ReflectionMethod(MailService::class, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs(null, $args);
    }
}
