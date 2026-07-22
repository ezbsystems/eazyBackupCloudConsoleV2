<?php

/**
 * Shared request parsing for MS365 job plan / billing preview APIs.
 */

/**
 * @return list<string>
 */
function ms365PlanDecodeJsonStringArray(mixed $value): array
{
    if (is_array($value)) {
        return array_values(array_map('strval', $value));
    }
    if (!is_string($value)) {
        return [];
    }
    $raw = trim($value);
    if ($raw === '') {
        return [];
    }
    $candidates = [$raw, stripslashes($raw), html_entity_decode($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), trim($raw, "'\"")];
    foreach ($candidates as $cand) {
        $decoded = json_decode($cand, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_map('strval', $decoded));
        }
    }

    return [];
}

/**
 * @return array<string, mixed>
 */
function ms365PlanDecodeJsonObject(mixed $value): array
{
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value)) {
        return [];
    }
    $raw = trim($value);
    if ($raw === '') {
        return [];
    }
    $candidates = [$raw, stripslashes($raw), html_entity_decode($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), trim($raw, "'\"")];
    foreach ($candidates as $cand) {
        $decoded = json_decode($cand, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }

    return [];
}

/**
 * @return array<string, mixed>
 */
function ms365PlanReadRequestParams(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    return $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
}

/**
 * @return array<string, mixed>
 */
function ms365PlanEmptyBilling(): array
{
    return [
        'protected_users' => 0,
        'onedrive_overage_gib' => 0,
        'pricing' => [
            'protected_user_price_cad' => 0.0,
            'onedrive_overage_per_gib_cad' => 0.0,
            'estimated_monthly_cad' => 0.0,
        ],
        'trial_status' => null,
        'inventory_stale' => false,
        'member_resolution_pending' => false,
        'breakdown' => [],
        'personal_selected_count' => 0,
        'membership_source_count' => 0,
        'reconciliation' => [
            'direct_appearances' => 0,
            'membership_appearances' => 0,
            'duplicate_appearances_removed' => 0,
            'protected_objects' => 0,
        ],
    ];
}
