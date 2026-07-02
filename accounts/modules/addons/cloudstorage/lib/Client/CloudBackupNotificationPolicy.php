<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

/**
 * Shared notification policy for cloud backup job report emails.
 */
final class CloudBackupNotificationPolicy
{
  private const MODULE = 'cloudstorage';

  private const HARDCODED_NOTIFY_ON_SUCCESS = 0;
  private const HARDCODED_NOTIFY_ON_WARNING = 1;
  private const HARDCODED_NOTIFY_ON_FAILURE = 1;

  /**
   * @param array<string, mixed> $job
   * @param object|null $client WHMCS client row with email property
   * @return array{
   *   send: bool,
   *   reason: string,
   *   recipients: list<string>,
   *   notify_on_success: int,
   *   notify_on_warning: int,
   *   notify_on_failure: int
   * }
   */
  public static function resolve(array $job, string $runStatus, $client = null): array
  {
    $clientId = (int) ($job['client_id'] ?? 0);
    $settings = self::loadClientSettings($clientId);
    $backupUser = self::loadBackupUser((int) ($job['backup_user_id'] ?? 0));

    if ($backupUser !== null && (int) ($backupUser->notifications_enabled ?? 1) === 0) {
      return self::skipDecision('notifications_disabled', [], 0, 0, 0);
    }

    $clientDefaults = self::clientOutcomeDefaults($settings);
    $outcome = self::resolveOutcomeToggles($job, $backupUser, $clientDefaults);
    $recipients = self::resolveRecipients($job, $backupUser, $settings, $client);

    if ($recipients === []) {
      return self::skipDecision('no_recipients', [], $outcome['success'], $outcome['warning'], $outcome['failure']);
    }

    $status = strtolower(trim($runStatus));
    if ($status === 'success' && !$outcome['success']) {
      return self::skipDecision('success_disabled', $recipients, $outcome['success'], $outcome['warning'], $outcome['failure']);
    }
    if (in_array($status, ['partial_success', 'warning'], true) && !$outcome['warning']) {
      return self::skipDecision('warning_disabled', $recipients, $outcome['success'], $outcome['warning'], $outcome['failure']);
    }
    if ($status === 'failed' && !$outcome['failure']) {
      return self::skipDecision('failure_disabled', $recipients, $outcome['success'], $outcome['warning'], $outcome['failure']);
    }
    if ($status === 'cancelled' && !$outcome['failure']) {
      return self::skipDecision('cancelled_disabled', $recipients, $outcome['success'], $outcome['warning'], $outcome['failure']);
    }

    return [
      'send' => true,
      'reason' => '',
      'recipients' => $recipients,
      'notify_on_success' => $outcome['success'],
      'notify_on_warning' => $outcome['warning'],
      'notify_on_failure' => $outcome['failure'],
    ];
  }

  /**
   * Parse comma, semicolon, or JSON array email list.
   *
   * @return list<string>
   */
  public static function parseEmailList($emailList): array
  {
    if ($emailList === null || $emailList === '') {
      return [];
    }

    if (is_array($emailList)) {
      return array_values(array_filter(array_map('trim', array_map('strval', $emailList))));
    }

    $emailList = (string) $emailList;
    $decoded = json_decode($emailList, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
      return array_values(array_filter(array_map('trim', array_map('strval', $decoded))));
    }

    $emails = preg_split('/[;,]+/', $emailList) ?: [];

    return array_values(array_filter(array_map('trim', $emails)));
  }

  /** @return object|null */
  private static function loadClientSettings(int $clientId)
  {
    if ($clientId <= 0) {
      return null;
    }

    try {
      return Capsule::table('s3_cloudbackup_settings')
        ->where('client_id', $clientId)
        ->first();
    } catch (\Throwable $e) {
      logModuleCall(self::MODULE, 'notification_policy_client_settings', ['client_id' => $clientId], $e->getMessage());

      return null;
    }
  }

  /** @return object|null */
  private static function loadBackupUser(int $backupUserId)
  {
    if ($backupUserId <= 0 || !Capsule::schema()->hasTable('s3_backup_users')) {
      return null;
    }

    $cols = ['id', 'email', 'notifications_enabled'];
    foreach (['notify_emails', 'notify_on_success', 'notify_on_warning', 'notify_on_failure'] as $col) {
      if (Capsule::schema()->hasColumn('s3_backup_users', $col)) {
        $cols[] = $col;
      }
    }

    try {
      return Capsule::table('s3_backup_users')
        ->where('id', $backupUserId)
        ->first($cols);
    } catch (\Throwable $e) {
      logModuleCall(self::MODULE, 'notification_policy_backup_user', ['backup_user_id' => $backupUserId], $e->getMessage());

      return null;
    }
  }

  /**
   * @param object|null $settings
   * @return array{success: int, warning: int, failure: int}
   */
  private static function clientOutcomeDefaults($settings): array
  {
    $success = self::HARDCODED_NOTIFY_ON_SUCCESS;
    $warning = self::HARDCODED_NOTIFY_ON_WARNING;
    $failure = self::HARDCODED_NOTIFY_ON_FAILURE;

    if ($settings !== null) {
      if (isset($settings->default_notify_on_success)) {
        $success = (int) $settings->default_notify_on_success;
      }
      if (isset($settings->default_notify_on_warning)) {
        $warning = (int) $settings->default_notify_on_warning;
      }
      if (isset($settings->default_notify_on_failure)) {
        $failure = (int) $settings->default_notify_on_failure;
      }
    }

    return [
      'success' => $success,
      'warning' => $warning,
      'failure' => $failure,
    ];
  }

  /**
   * @param array<string, mixed> $job
   * @param object|null $backupUser
   * @param array{success: int, warning: int, failure: int} $clientDefaults
   * @return array{success: int, warning: int, failure: int}
   */
  private static function resolveOutcomeToggles(array $job, $backupUser, array $clientDefaults): array
  {
    $success = $clientDefaults['success'];
    $warning = $clientDefaults['warning'];
    $failure = $clientDefaults['failure'];

    if ($backupUser !== null) {
      if (isset($backupUser->notify_on_success)) {
        $success = (int) $backupUser->notify_on_success;
      }
      if (isset($backupUser->notify_on_warning)) {
        $warning = (int) $backupUser->notify_on_warning;
      }
      if (isset($backupUser->notify_on_failure)) {
        $failure = (int) $backupUser->notify_on_failure;
      }
    }

    if (self::jobNotifyTogglesAreExplicit($job)) {
      $jobNOS = isset($job['notify_on_success']) && $job['notify_on_success'] !== null
        ? (int) $job['notify_on_success'] : null;
      $jobNOW = isset($job['notify_on_warning']) && $job['notify_on_warning'] !== null
        ? (int) $job['notify_on_warning'] : null;
      $jobNOF = isset($job['notify_on_failure']) && $job['notify_on_failure'] !== null
        ? (int) $job['notify_on_failure'] : null;

      if ($jobNOS !== null) {
        $success = $jobNOS;
      }
      if ($jobNOW !== null) {
        $warning = $jobNOW;
      }
      if ($jobNOF !== null) {
        $failure = $jobNOF;
      }
    }

    return [
      'success' => $success,
      'warning' => $warning,
      'failure' => $failure,
    ];
  }

  /**
   * Job rows default to success=0, warning=1, failure=1. Treat that triplet (and all-zero
   * legacy rows) as "inherit from backup user / client" unless a job API sets other values.
   *
   * @param array<string, mixed> $job
   */
  private static function jobNotifyTogglesAreExplicit(array $job): bool
  {
    $jobNOS = isset($job['notify_on_success']) && $job['notify_on_success'] !== null
      ? (int) $job['notify_on_success'] : null;
    $jobNOW = isset($job['notify_on_warning']) && $job['notify_on_warning'] !== null
      ? (int) $job['notify_on_warning'] : null;
    $jobNOF = isset($job['notify_on_failure']) && $job['notify_on_failure'] !== null
      ? (int) $job['notify_on_failure'] : null;

    if ($jobNOS === null && $jobNOW === null && $jobNOF === null) {
      return false;
    }

    if ($jobNOS === 0 && $jobNOW === 0 && $jobNOF === 0) {
      return false;
    }

    if ($jobNOS === 0 && $jobNOW === 1 && $jobNOF === 1) {
      return false;
    }

    return true;
  }

  /**
   * @param array<string, mixed> $job
   * @param object|null $backupUser
   * @param object|null $settings
   * @return list<string>
   */
  private static function resolveRecipients(array $job, $backupUser, $settings, $client): array
  {
    if (!empty($job['notify_override_email'])) {
      $override = self::parseEmailList($job['notify_override_email']);
      if ($override !== []) {
        return $override;
      }
    }

    if ($backupUser !== null && !empty($backupUser->notify_emails)) {
      $userEmails = self::parseEmailList($backupUser->notify_emails);
      if ($userEmails !== []) {
        return $userEmails;
      }
    }

    if ($settings !== null && !empty($settings->default_notify_emails)) {
      $clientEmails = self::parseEmailList($settings->default_notify_emails);
      if ($clientEmails !== []) {
        return $clientEmails;
      }
    }

    if ($backupUser !== null && !empty($backupUser->email)) {
      $email = trim((string) $backupUser->email);
      if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [$email];
      }
    }

    if ($client !== null && !empty($client->email)) {
      $email = trim((string) $client->email);
      if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [$email];
      }
    }

    return [];
  }

  /**
   * @param list<string> $recipients
   * @return array{
   *   send: false,
   *   reason: string,
   *   recipients: list<string>,
   *   notify_on_success: int,
   *   notify_on_warning: int,
   *   notify_on_failure: int
   * }
   */
  private static function skipDecision(
    string $reason,
    array $recipients,
    int $notifyOnSuccess,
    int $notifyOnWarning,
    int $notifyOnFailure
  ): array {
    return [
      'send' => false,
      'reason' => $reason,
      'recipients' => $recipients,
      'notify_on_success' => $notifyOnSuccess,
      'notify_on_warning' => $notifyOnWarning,
      'notify_on_failure' => $notifyOnFailure,
    ];
  }
}
