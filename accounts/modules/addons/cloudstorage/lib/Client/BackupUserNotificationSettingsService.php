<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

/**
 * CRUD + validation for per-backup-user email notification settings.
 */
final class BackupUserNotificationSettingsService
{
  private const MAX_EMAILS = 10;

  /** @var bool|null */
  private static $notifyColumnsAvailable;

  /**
   * @return array{
   *   notifications_enabled: bool,
   *   notify_emails: list<string>,
   *   notify_on_success: bool,
   *   notify_on_warning: bool,
   *   notify_on_failure: bool
   * }
   */
  public static function getForBackupUser(int $clientId, int $backupUserId): array
  {
    $row = self::loadOwnedRow($clientId, $backupUserId);
    if ($row === null) {
      return self::defaultDto();
    }

    return self::normalizeRow($row);
  }

  /**
   * @param array<string, mixed> $payload
   * @return array{
   *   ok: bool,
   *   settings?: array{
   *     notifications_enabled: bool,
   *     notify_emails: list<string>,
   *     notify_on_success: bool,
   *     notify_on_warning: bool,
   *     notify_on_failure: bool
   *   },
   *   message?: string,
   *   errors?: array<string, string>
   * }
   */
  public static function saveForBackupUser(int $clientId, int $backupUserId, array $payload): array
  {
    if (self::loadOwnedRow($clientId, $backupUserId) === null) {
      return [
        'ok' => false,
        'message' => 'User not found.',
      ];
    }

    $validation = self::validatePayload($payload);
    if (!empty($validation['errors'])) {
      return [
        'ok' => false,
        'message' => 'Please correct the highlighted fields.',
        'errors' => $validation['errors'],
      ];
    }

    $dto = $validation['dto'];
    $update = [
      'notifications_enabled' => $dto['notifications_enabled'] ? 1 : 0,
      'notify_emails' => $dto['notify_emails'] === [] ? null : json_encode(array_values($dto['notify_emails'])),
      'notify_on_success' => $dto['notify_on_success'] ? 1 : 0,
      'notify_on_warning' => $dto['notify_on_warning'] ? 1 : 0,
      'notify_on_failure' => $dto['notify_on_failure'] ? 1 : 0,
      'updated_at' => Capsule::raw('NOW()'),
    ];

    try {
      Capsule::table('s3_backup_users')
        ->where('id', $backupUserId)
        ->where('client_id', $clientId)
        ->update($update);
    } catch (\Throwable $e) {
      return [
        'ok' => false,
        'message' => 'Failed to save notification settings.',
      ];
    }

    return [
      'ok' => true,
      'settings' => $dto,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   * @return array{dto: array<string, mixed>, errors: array<string, string>}
   */
  public static function validatePayload(array $payload): array
  {
    $errors = [];

    $enabled = self::parseBool($payload['notifications_enabled'] ?? null, true);
    $notifyOnSuccess = self::parseBool($payload['notify_on_success'] ?? null, false);
    $notifyOnWarning = self::parseBool($payload['notify_on_warning'] ?? null, true);
    $notifyOnFailure = self::parseBool($payload['notify_on_failure'] ?? null, true);

    $emailsRaw = self::parseEmailsInput($payload['notify_emails'] ?? []);
    if (!is_array($emailsRaw)) {
      $errors['notify_emails'] = 'Recipients must be a list of email addresses.';
      $emailsRaw = [];
    }

    $normalizedEmails = [];
    $seen = [];
    foreach ($emailsRaw as $email) {
      $email = strtolower(trim((string) $email));
      if ($email === '') {
        continue;
      }
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['notify_emails'] = 'Please enter valid email addresses.';
        continue;
      }
      if (isset($seen[$email])) {
        $errors['notify_emails'] = 'Duplicate email addresses are not allowed.';
        continue;
      }
      $seen[$email] = true;
      $normalizedEmails[] = $email;
    }

    if (count($normalizedEmails) > self::MAX_EMAILS) {
      $errors['notify_emails'] = 'You can add at most ' . self::MAX_EMAILS . ' email addresses.';
    }

    return [
      'dto' => [
        'notifications_enabled' => $enabled,
        'notify_emails' => $normalizedEmails,
        'notify_on_success' => $notifyOnSuccess,
        'notify_on_warning' => $notifyOnWarning,
        'notify_on_failure' => $notifyOnFailure,
      ],
      'errors' => $errors,
    ];
  }

  /** @param mixed $raw @return list<string> */
  private static function parseEmailsInput($raw): array
  {
    if ($raw === null || $raw === '') {
      return [];
    }

    if (is_array($raw)) {
      $emails = [];
      foreach ($raw as $item) {
        foreach (self::parseEmailsInput($item) as $email) {
          $emails[] = $email;
        }
      }

      return $emails;
    }

    $text = html_entity_decode(trim((string) $raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($text === '') {
      return [];
    }

    if ($text[0] === '[') {
      $decoded = json_decode($text, true);
      if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return self::parseEmailsInput($decoded);
      }

      return [];
    }

    return array_values(array_filter(
      array_map('trim', preg_split('/[;,]+/', $text) ?: []),
      static fn(string $email): bool => $email !== ''
    ));
  }

  /** @return object|null */
  private static function loadOwnedRow(int $clientId, int $backupUserId)
  {
    if ($backupUserId <= 0 || !Capsule::schema()->hasTable('s3_backup_users')) {
      return null;
    }

    $cols = ['id', 'client_id'];
    if (self::notifyColumnsAvailable()) {
      $cols = array_merge($cols, [
        'notifications_enabled',
        'notify_emails',
        'notify_on_success',
        'notify_on_warning',
        'notify_on_failure',
      ]);
    }

    return Capsule::table('s3_backup_users')
      ->where('id', $backupUserId)
      ->where('client_id', $clientId)
      ->first($cols);
  }

  private static function notifyColumnsAvailable(): bool
  {
    if (self::$notifyColumnsAvailable === null) {
      self::$notifyColumnsAvailable = Capsule::schema()->hasColumn('s3_backup_users', 'notifications_enabled');
    }

    return self::$notifyColumnsAvailable;
  }

  /**
   * @return array{
   *   notifications_enabled: bool,
   *   notify_emails: list<string>,
   *   notify_on_success: bool,
   *   notify_on_warning: bool,
   *   notify_on_failure: bool
   * }
   */
  private static function defaultDto(): array
  {
    return [
      'notifications_enabled' => true,
      'notify_emails' => [],
      'notify_on_success' => false,
      'notify_on_warning' => true,
      'notify_on_failure' => true,
    ];
  }

  /** @param object $row */
  private static function normalizeRow($row): array
  {
    if (!self::notifyColumnsAvailable()) {
      return self::defaultDto();
    }

    return [
      'notifications_enabled' => (int) ($row->notifications_enabled ?? 1) === 1,
      'notify_emails' => CloudBackupNotificationPolicy::parseEmailList($row->notify_emails ?? ''),
      'notify_on_success' => (int) ($row->notify_on_success ?? 0) === 1,
      'notify_on_warning' => (int) ($row->notify_on_warning ?? 1) === 1,
      'notify_on_failure' => (int) ($row->notify_on_failure ?? 1) === 1,
    ];
  }

  /** @param mixed $value */
  private static function parseBool($value, bool $default = false): bool
  {
    if (is_bool($value)) {
      return $value;
    }
    if (is_int($value) || is_float($value)) {
      return (int) $value === 1;
    }
    $normalized = strtolower(trim((string) $value));
    if ($normalized === '') {
      return $default;
    }

    return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
  }
}
