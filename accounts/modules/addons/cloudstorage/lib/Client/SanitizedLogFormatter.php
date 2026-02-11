<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

class SanitizedLogFormatter
{
	/**
	 * Sanitize and structure rclone log excerpt for client display.
	 * Keeps bucket names visible. Redacts credentials, RequestID/HostID, and low-level op names.
	 *
	 * @param string|null $rawLogJson
	 * @param string|null $runStatus e.g. success|failed|running|cancelled
	 * @param \DateTimeZone|string|null $timezone
	 * @return array{entries: array<int, array{time: string|null, level: string, message: string}>, formatted_log: string, hash: string|null, sanitized: bool}
	 */
	public static function sanitizeAndStructure($rawLogJson, $runStatus = null, $timezone = null)
	{
		$entries = [];
		$hash = $rawLogJson ? md5($rawLogJson) : null;
		$tzObj = null;
		if ($timezone instanceof \DateTimeZone) {
			$tzObj = $timezone;
		} elseif (is_string($timezone) && trim($timezone) !== '') {
			try {
				$tzObj = new \DateTimeZone(trim($timezone));
			} catch (\Throwable $e) {
				$tzObj = null;
			}
		}

		if (empty($rawLogJson)) {
			return [
				'entries' => [],
				'formatted_log' => 'No log data available for this backup run.',
				'hash' => $hash,
				'sanitized' => true,
			];
		}

		$logLines = json_decode($rawLogJson, true);

		// Handle array of JSON strings (double-encoded) or non-array
		if (is_array($logLines) && !empty($logLines) && is_string(reset($logLines))) {
			$decoded = [];
			foreach ($logLines as $line) {
				if (is_string($line)) {
					$dec = json_decode($line, true);
					if (is_array($dec)) $decoded[] = $dec;
				} elseif (is_array($line)) {
					$decoded[] = $line;
				}
			}
			$logLines = $decoded;
		} elseif (!is_array($logLines)) {
			$logLines = [];
			$lines = explode("\n", trim($rawLogJson));
			foreach ($lines as $line) {
				$line = trim($line);
				if ($line === '') continue;
				$dec = json_decode($line, true);
				if (is_array($dec)) $logLines[] = $dec;
				else $logLines[] = ['msg' => $line, 'level' => 'info'];
			}
		}

		if (empty($logLines)) {
			return [
				'entries' => [],
				'formatted_log' => 'Log data is empty or could not be parsed.',
				'hash' => $hash,
				'sanitized' => true,
			];
		}

		$hasErrors = false;
		foreach ($logLines as $entry) {
			if (!is_array($entry)) continue;
			$origLevel = strtolower($entry['level'] ?? 'info');
			$msg = (string)($entry['msg'] ?? '');
			$time = isset($entry['time']) ? self::formatTime($entry['time'], $tzObj) : null;

			// Build sanitized message (no emojis/icons, redactions applied)
			$sanitizedMsg = self::sanitizeMessage($msg);

			// Classify severity
			$level = self::classifyLevel($origLevel, $sanitizedMsg);
			if ($level === 'error') $hasErrors = true;

			// Skip misleading "success/no changes" lines if run not successful
			if ($runStatus !== null && $runStatus !== 'success') {
				if (self::looksLikeNoChangeSuccess($sanitizedMsg) || self::looksLikeCompletedSuccess($sanitizedMsg)) {
					continue;
				}
			}

			$entries[] = [
				'time' => $time,
				'level' => $level,
				'message' => $sanitizedMsg,
			];
		}

		// If we found errors, also filter any residual misleading success lines
		if ($hasErrors) {
			$entries = array_values(array_filter($entries, function ($e) {
				return !self::looksLikeNoChangeSuccess($e['message']) && !self::looksLikeCompletedSuccess($e['message']);
			}));
		}

		// Compose a compact formatted string for fallbacks
		$lines = [];
		foreach ($entries as $e) {
			$badge = strtoupper(self::badgeFor($e['level']));
			$prefix = ($e['time'] ? '[' . $e['time'] . '] ' : '');
			$lines[] = $prefix . '[' . $badge . '] ' . $e['message'];
		}
		$formatted = empty($lines) ? 'No sanitized log lines available for display.' : implode("\n", $lines);

		return [
			'entries' => $entries,
			'formatted_log' => $formatted,
			'hash' => $hash,
			'sanitized' => true,
		];
	}

	private static function formatTime($timestamp, $tzObj = null)
	{
		try {
			if ($tzObj instanceof \DateTimeZone) {
				$converted = TimezoneHelper::formatTimeOnly($timestamp, $tzObj);
				if ($converted !== null) {
					return $converted;
				}
			}
			$dt = new \DateTime($timestamp);
			return $dt->format('H:i:s');
		} catch (\Exception $e) {
			return null;
		}
	}

	private static function badgeFor($level)
	{
		switch ($level) {
			case 'error': return 'ERROR';
			case 'warn': return 'WARN';
			case 'ok': return 'OK';
			default: return 'INFO';
		}
	}

	private static function classifyLevel($origLevel, $msg)
	{
		$l = strtolower($origLevel);
		$m = strtolower($msg);
		if ($l === 'error') return 'error';
		if (strpos($m, 'invalidaccesskeyid') !== false) return 'error';
		if (preg_match('/\\b(5\\d\\d|4\\d\\d)\\b/', $m)) return 'error';
		if (strpos($m, 'failed') !== false) return 'error';
		if ($l === 'warning' || strpos($m, 'warning') !== false) return 'warn';
		if (self::looksLikeCompletedSuccess($m) || self::looksLikeNoChangeSuccess($m)) return 'ok';
		return 'info';
	}

	private static function looksLikeNoChangeSuccess($msg)
	{
		$m = strtolower($msg);
		return (strpos($m, 'no files to transfer') !== false) ||
			(strpos($m, 'already synchronized') !== false) ||
			(strpos($m, 'nothing to transfer') !== false);
	}

	private static function looksLikeCompletedSuccess($msg)
	{
		$m = strtolower($msg);
		return (strpos($m, 'completed successfully') !== false);
	}

	private static function sanitizeMessage($msg)
	{
		// Replace vendor name
		$msg = preg_replace('/rclone/i', 'eazyBackup', $msg);

		// Remove known emojis/icons used in prior formatting
		$icons = ['✅','❌','⚠️','📤','📊','🗑️','🔄','🔍','📦','📋','🚀'];
		$msg = str_replace($icons, '', $msg);

		// General emoji removal (basic)
		$msg = preg_replace('/[\x{1F300}-\x{1FAFF}]/u', '', $msg);

		// Redact credentials-like tokens
		$msg = preg_replace('/(?i)(access\\s*key\\s*id|secret\\s*access\\s*key|access_key|secret_key|authorization|token|bearer)\\s*[:=]\\s*[^\\s,]+/i', '$1: [redacted]', $msg);

		// Redact RequestID / HostID
		$msg = preg_replace('/RequestID:\\s*[^,\\s]+/i', 'RequestID: [redacted]', $msg);
		$msg = preg_replace('/HostID:\\s*[^,\\s]+/i', 'HostID: [redacted]', $msg);

		// Redact explicit S3 operation names
		$msg = preg_replace('/\\bS3:\\s*[A-Za-z0-9]+/i', 'cloud storage operation', $msg);

		// Normalize whitespace
		$msg = preg_replace('/\\s+/', ' ', trim($msg));

		return $msg;
	}
}


