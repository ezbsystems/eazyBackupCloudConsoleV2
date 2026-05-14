<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Unit;

use EazyBackup\Tests\Support\UnitTestCase;

/**
 * Coverage for `eb_signup_validate_basic_input` — the form validation guard
 * for the public signup form.
 *
 * Source: pages/whitelabel/PublicSignupController.php
 *
 * Risks this catches:
 *   - A new validation rule slipping in without a test, or an existing rule
 *     being relaxed (e.g. accepting blank password as "OK").
 *   - The error-code shape being changed (the template binds to specific
 *     codes — `name`, `email`, `username`, `password`, `agree`, `product`).
 *   - filter_var() not being used for email validation (regression to a
 *     looser check that lets junk through to localAPI).
 */
final class PublicSignupValidationTest extends UnitTestCase
{
    public function test_full_valid_payload_returns_no_errors(): void
    {
        $errs = eb_signup_validate_basic_input([
            'first_name' => 'Sam',
            'last_name' => 'Buyer',
            'email' => 'sam@acme.test',
            'username' => 'sam_buyer',
            'password' => 'super-secret',
            'confirm_password' => 'super-secret',
            'agree' => true,
            'product_pid' => 42,
        ]);
        self::assertSame([], $errs);
    }

    public function test_missing_first_or_last_name_emits_name(): void
    {
        $base = $this->validBase();
        self::assertContains('name', eb_signup_validate_basic_input(['first_name' => '', 'last_name' => 'X'] + $base));
        self::assertContains('name', eb_signup_validate_basic_input(['first_name' => 'X', 'last_name' => ''] + $base));
    }

    public function test_invalid_or_blank_email_emits_email(): void
    {
        $base = $this->validBase();
        self::assertContains('email', eb_signup_validate_basic_input(['email' => ''] + $base));
        self::assertContains('email', eb_signup_validate_basic_input(['email' => 'not-an-email'] + $base));
        self::assertContains('email', eb_signup_validate_basic_input(['email' => 'sam@'] + $base));
    }

    public function test_missing_username_emits_username(): void
    {
        $base = $this->validBase();
        self::assertContains('username', eb_signup_validate_basic_input(['username' => '   '] + $base));
    }

    public function test_password_mismatch_emits_password(): void
    {
        $base = $this->validBase();
        self::assertContains('password', eb_signup_validate_basic_input([
            'password' => 'a',
            'confirm_password' => 'b',
        ] + $base));
    }

    public function test_blank_password_emits_password(): void
    {
        $base = $this->validBase();
        self::assertContains('password', eb_signup_validate_basic_input([
            'password' => '',
            'confirm_password' => '',
        ] + $base));
    }

    public function test_missing_agree_emits_agree(): void
    {
        $base = $this->validBase();
        self::assertContains('agree', eb_signup_validate_basic_input(['agree' => false] + $base));
        self::assertContains('agree', eb_signup_validate_basic_input(['agree' => 0] + $base));
    }

    public function test_zero_or_missing_product_emits_product(): void
    {
        $base = $this->validBase();
        self::assertContains('product', eb_signup_validate_basic_input(['product_pid' => 0] + $base));
        unset($base['product_pid']);
        self::assertContains('product', eb_signup_validate_basic_input($base));
    }

    public function test_multiple_errors_reported_together(): void
    {
        $errs = eb_signup_validate_basic_input([
            'first_name' => '',
            'last_name' => '',
            'email' => 'x',
            'username' => '',
            'password' => '',
            'confirm_password' => '',
            'agree' => false,
            'product_pid' => 0,
        ]);
        self::assertSame(['name', 'email', 'username', 'password', 'agree', 'product'], $errs);
    }

    /**
     * Returns a known-good payload that test cases can selectively mutate.
     */
    private function validBase(): array
    {
        return [
            'first_name' => 'Sam',
            'last_name' => 'Buyer',
            'email' => 'sam@acme.test',
            'username' => 'sam_buyer',
            'password' => 'super-secret',
            'confirm_password' => 'super-secret',
            'agree' => true,
            'product_pid' => 42,
        ];
    }
}
