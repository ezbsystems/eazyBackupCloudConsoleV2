<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Unit;

use EazyBackup\Tests\Support\DatabaseTestCase;
use PartnerHub\SettingsService;
use WHMCS\Database\Capsule;

/**
 * Verifies the SMTP password encryption + idempotency contract.
 *
 * Source: lib/PartnerHub/SettingsService.php (saveEmailSettings — encrypt branch)
 *
 * Risks this catches:
 *   - Plaintext SMTP passwords leaking into the DB.
 *   - Double-encryption corrupting passwords on every save (the user types
 *     a new value -> save -> encrypted; user opens form again with stored
 *     value pre-filled -> save -> encrypted-again -> decrypt() returns
 *     ciphertext garbage).
 */
final class SettingsServiceEmailSmtpEncryptionTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('encrypt') || !function_exists('decrypt')) {
            self::markTestSkipped('WHMCS encrypt()/decrypt() helpers not loaded — skipping SMTP password test.');
        }
    }

    public function test_plaintext_password_is_encrypted_on_first_save(): void
    {
        $plaintext = 'p4ss-' . bin2hex(random_bytes(8));

        SettingsService::saveEmailSettings($this->testMspId, [
            'smtp' => [
                'mode' => 'smtp',
                'host' => 'smtp.example.test',
                'port' => 587,
                'username' => 'user@example.test',
                'password_enc' => $plaintext,
            ],
        ]);

        $stored = $this->readStoredSmtpPasswordEnc($this->testMspId);

        self::assertNotSame('', $stored, 'password_enc should be persisted.');
        self::assertNotSame($plaintext, $stored, 'password_enc must not be stored as plaintext.');
        self::assertSame($plaintext, decrypt($stored), 'decrypt() must round-trip back to the original plaintext.');
    }

    public function test_already_encrypted_password_is_not_double_encrypted(): void
    {
        $plaintext = 'rotation-' . bin2hex(random_bytes(8));

        // First save: SettingsService receives plaintext, encrypts.
        SettingsService::saveEmailSettings($this->testMspId, [
            'smtp' => [
                'mode' => 'smtp',
                'host' => 'smtp.example.test',
                'port' => 587,
                'username' => 'user@example.test',
                'password_enc' => $plaintext,
            ],
        ]);
        $afterFirstSave = $this->readStoredSmtpPasswordEnc($this->testMspId);

        // Second save: simulate the UI re-submitting the already-encrypted value
        // (which is what happens when the user opens the form and clicks Save
        // without touching the password field).
        SettingsService::saveEmailSettings($this->testMspId, [
            'smtp' => [
                'mode' => 'smtp',
                'host' => 'smtp.example.test',
                'port' => 587,
                'username' => 'user@example.test',
                'password_enc' => $afterFirstSave,
            ],
        ]);
        $afterSecondSave = $this->readStoredSmtpPasswordEnc($this->testMspId);

        self::assertSame(
            $plaintext,
            decrypt($afterSecondSave),
            'Second save must still round-trip back to the original plaintext (no double-encryption).'
        );
    }

    public function test_empty_password_is_left_empty(): void
    {
        SettingsService::saveEmailSettings($this->testMspId, [
            'smtp' => [
                'mode' => 'smtp',
                'host' => 'smtp.example.test',
                'port' => 587,
                'username' => '',
                'password_enc' => '',
            ],
        ]);

        $stored = $this->readStoredSmtpPasswordEnc($this->testMspId);
        self::assertSame('', $stored, 'Empty SMTP password should not be transformed.');
    }

    public function test_default_email_templates_returned_for_unknown_msp(): void
    {
        $settings = SettingsService::getEmailSettings($this->testMspId);

        // The MailService consumer requires these template keys to exist by default.
        $required = ['welcome', 'trial_ending', 'payment_failed', 'card_expiring',
                     'subscription_changed', 'new_invoice', 'pay_link'];
        foreach ($required as $key) {
            self::assertArrayHasKey($key, $settings['templates'], "Default template '{$key}' must exist.");
            self::assertArrayHasKey('subject', $settings['templates'][$key]);
            self::assertArrayHasKey('body_md', $settings['templates'][$key]);
        }

        // SMTP defaults to the built-in/no-op mode so we never accidentally relay
        // through nothing during a fresh tenant onboarding.
        self::assertSame('builtin', $settings['smtp']['mode']);
    }

    private function readStoredSmtpPasswordEnc(int $mspId): string
    {
        $row = Capsule::table('eb_msp_settings')
            ->where('msp_id', $mspId)
            ->first(['email_json']);

        if (!$row || !isset($row->email_json)) {
            return '';
        }

        $decoded = json_decode((string) $row->email_json, true);
        if (!is_array($decoded)) {
            return '';
        }

        return (string) ($decoded['smtp']['password_enc'] ?? '');
    }
}
