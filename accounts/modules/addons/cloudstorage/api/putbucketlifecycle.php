<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined('WHMCS')) {
	die('This file cannot be accessed directly');
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\BucketLifecycleService;
use WHMCS\Database\Capsule;

function cs_read_json_body(): array {
	$raw = file_get_contents('php://input');
	if (!$raw) {
		return [];
	}
	$dec = json_decode($raw, true);
	return is_array($dec) ? $dec : [];
}

$ca = new ClientArea();
$loggedInUserId = $ca->getUserID();
$packageId = ProductConfig::$E3_PRODUCT_ID;
$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || is_null($product->username)) {
	(new JsonResponse(['status' => 'fail', 'message' => 'Unable to save lifecycle rule. Please try again later.'], 200))->send();
	exit;
}

$username = $product->username;
$user = DBController::getUser($username);

$input = array_merge(cs_read_json_body(), $_POST ?? []);
$bucketName = $input['bucket_name'] ?? '';

if ($bucketName === '') {
	(new JsonResponse(['status' => 'fail', 'message' => 'Bucket name is required.'], 200))->send();
	exit;
}

// Validate bucket ownership (self or tenants)
$bucket = DBController::getRow('s3_buckets', [['name', '=', $bucketName]]);
if (is_null($bucket)) {
	(new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200))->send();
	exit;
}
if ((int)$bucket->user_id !== (int)$user->id) {
	$tenants = DBController::getTenants($user->id, ['id']);
	$tenantIds = $tenants ? $tenants->pluck('id')->toArray() : [];
	if (!in_array((int)$bucket->user_id, $tenantIds, true)) {
		(new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200))->send();
		exit;
	}
}

// Module settings
$moduleRows = DBController::getResult('tbladdonmodules', [['module', '=', 'cloudstorage']]);
if (count($moduleRows) == 0) {
	(new JsonResponse(['status' => 'fail', 'message' => 'Service unavailable. Please try again later.'], 200))->send();
	exit;
}
$endpoint = $moduleRows->where('setting', 's3_endpoint')->pluck('value')->first();
$s3Region = $moduleRows->where('setting', 's3_region')->pluck('value')->first() ?: 'us-east-1';
$encryptionKey = $moduleRows->where('setting', 'encryption_key')->pluck('value')->first();
// Optional allowed storage classes (CSV)
$allowedClassesCsv = (string)($moduleRows->where('setting', 'lifecycle_storage_classes')->pluck('value')->first() ?? '');
$allowedClasses = array_values(array_filter(array_map('trim', explode(',', $allowedClassesCsv))));

$service = new BucketLifecycleService($endpoint, $s3Region);
$owner = Capsule::table('s3_users')->where('id', $bucket->user_id)->first();

// Build final rules set: accept either single 'rule' or full 'rules' array
$incomingRule = isset($input['rule']) && is_array($input['rule']) ? $input['rule'] : null;
$incomingRules = (isset($input['rules']) && is_array($input['rules'])) ? $input['rules'] : null;

// Log a compact view of the incoming payload for diagnostics
try {
	logModuleCall('cloudstorage', 'lifecycle_put_request', [
		'bucket' => $bucketName,
		'mode' => $incomingRule ? 'single' : (is_array($incomingRules) ? 'bulk' : 'none'),
		'rule_id' => $incomingRule['ID'] ?? null,
		'rules_count' => is_array($incomingRules) ? count($incomingRules) : null,
	], 'Received lifecycle put request');
} catch (\Throwable $e) {
	// ignore logging failures
}

// Start from current rules if we need to merge by ID
if ($incomingRule && !isset($incomingRules)) {
	$cur = $service->get($bucketName, (int)$owner->id, $encryptionKey);
	if (($cur['status'] ?? 'fail') !== 'success') {
		logModuleCall('cloudstorage', 'lifecycle_merge_get_failed', ['bucket' => $bucketName], 'Failed fetching current rules during merge');
		(new JsonResponse(['status' => 'fail', 'message' => 'Unable to save lifecycle rule. Please try again later.'], 200))->send();
		exit;
	}
	$currentRules = $cur['data']['rules'] ?? [];
	$merged = [];
	$replaced = false;
	$incomingId = isset($incomingRule['ID']) ? (string)$incomingRule['ID'] : '';
	if ($incomingId === '') {
		(new JsonResponse(['status' => 'fail', 'message' => 'Please check your rule name and fields.'], 200))->send();
		exit;
	}
	foreach ($currentRules as $r) {
		$rid = isset($r['ID']) ? (string)$r['ID'] : '';
		if ($rid !== '' && $rid === $incomingId) {
			$merged[] = $incomingRule;
			$replaced = true;
		} else {
			$merged[] = $r;
		}
	}
	if (!$replaced) {
		$merged[] = $incomingRule;
	}
	$incomingRules = $merged;

	try {
		logModuleCall('cloudstorage', 'lifecycle_merge', [
			'bucket' => $bucketName,
			'merged_count' => count($incomingRules),
			'ids' => array_values(array_map(function($x){ return $x['ID'] ?? ''; }, $incomingRules))
		], 'Merged single rule into current set');
	} catch (\Throwable $e) {}
}

if (!is_array($incomingRules)) {
	(new JsonResponse(['status' => 'fail', 'message' => 'Please check your rule name and fields.'], 200))->send();
	exit;
}

$validate = function(array $rules, $bucketRow, array $allowedClasses) {
	// Always keep error details internal
	$logReasons = [];
	$ids = [];
	$state = strtolower((string)($bucketRow->versioning ?? ''));
	// Treat only explicit enabled/on/true/1 as ON; everything else (off, disabled, suspended, empty) is OFF
	$versioningOn = in_array($state, ['enabled', 'on', 'true', '1'], true);

	foreach ($rules as $idx => $r) {
		if (!is_array($r)) { $logReasons[] = "Rule $idx not an array"; continue; }
		$id = isset($r['ID']) ? (string)$r['ID'] : '';
		if ($id === '' || strlen($id) > 255) { $logReasons[] = "Invalid ID length for rule $idx"; }
		if ($id !== '') {
			if (isset($ids[$id])) { $logReasons[] = "Duplicate ID: $id"; }
			$ids[$id] = true;
		}
		$status = isset($r['Status']) ? (string)$r['Status'] : 'Enabled';
		if ($status !== 'Enabled' && $status !== 'Disabled') { $logReasons[] = "Invalid Status for $id"; }

		// Check transitions against allowed classes
		if (empty($allowedClasses)) {
			if (isset($r['Transition']) || isset($r['NoncurrentVersionTransition'])) {
				$logReasons[] = "Transitions present but no classes configured";
			}
		} else {
			if (isset($r['Transition']['StorageClass']) && !in_array($r['Transition']['StorageClass'], $allowedClasses, true)) {
				$logReasons[] = "Invalid StorageClass for current transition in $id";
			}
			if (isset($r['NoncurrentVersionTransition']['StorageClass']) && !in_array($r['NoncurrentVersionTransition']['StorageClass'], $allowedClasses, true)) {
				$logReasons[] = "Invalid StorageClass for noncurrent transition in $id";
			}
		}

		// Noncurrent* require versioning
		if (!$versioningOn) {
			if (isset($r['NoncurrentVersionTransition']) || isset($r['NoncurrentVersionExpiration'])) {
				$logReasons[] = "Noncurrent* specified while versioning is off for $id";
			}
		}

		// Size validators if present under Filter.And
		$flt = $r['Filter'] ?? null;
		if (is_array($flt)) {
			$and = $flt['And'] ?? $flt; // accept either
			if (is_array($and)) {
				if (isset($and['ObjectSizeGreaterThan']) && (!is_numeric($and['ObjectSizeGreaterThan']) || $and['ObjectSizeGreaterThan'] < 0)) {
					$logReasons[] = "Invalid ObjectSizeGreaterThan in $id";
				}
				if (isset($and['ObjectSizeLessThan']) && (!is_numeric($and['ObjectSizeLessThan']) || $and['ObjectSizeLessThan'] <= 0)) {
					$logReasons[] = "Invalid ObjectSizeLessThan in $id";
				}
			}
		}
	}

	if (!empty($logReasons)) {
		// Log specifics, return generic
		logModuleCall('cloudstorage', 'lifecycle_validate', ['bucket' => $bucketRow->name, 'count' => count($rules), 'versioning_state' => $state], implode('; ', $logReasons));
		return ['ok' => false];
	}
	return ['ok' => true];
};

$val = $validate($incomingRules, $bucket, $allowedClasses);
if (!($val['ok'] ?? false)) {
	(new JsonResponse(['status' => 'fail', 'message' => 'Unable to save lifecycle rule. Please try again later.'], 200))->send();
	exit;
}

// Normalize rules for broader RGW compatibility:
// - If Filter.And contains only Prefix, rewrite to Filter.Prefix
// - Coerce integer fields to int
foreach ($incomingRules as &$r) {
	// Status fallback
	if (!isset($r['Status'])) { $r['Status'] = 'Enabled'; }
	// Filter normalization
	if (isset($r['Filter']) && is_array($r['Filter'])) {
		$f = $r['Filter'];
		if (isset($f['And']) && is_array($f['And'])) {
			$and = $f['And'];
			$andKeys = array_keys($and);
			if (count($andKeys) === 1 && isset($and['Prefix'])) {
				$r['Filter'] = ['Prefix' => (string)$and['Prefix']];
			} else {
				// keep as-is
				$r['Filter'] = ['And' => $and];
			}
		}
	}
	// Integer coercions (best-effort)
	if (isset($r['Expiration']['Days'])) {
		$r['Expiration']['Days'] = (int)$r['Expiration']['Days'];
	}
	if (isset($r['NoncurrentVersionExpiration']['NoncurrentDays'])) {
		$r['NoncurrentVersionExpiration']['NoncurrentDays'] = (int)$r['NoncurrentVersionExpiration']['NoncurrentDays'];
	}
	if (isset($r['AbortIncompleteMultipartUpload']['DaysAfterInitiation'])) {
		$r['AbortIncompleteMultipartUpload']['DaysAfterInitiation'] = (int)$r['AbortIncompleteMultipartUpload']['DaysAfterInitiation'];
	}
	if (isset($r['Transition']['Days'])) {
		$r['Transition']['Days'] = (int)$r['Transition']['Days'];
	}
	if (isset($r['NoncurrentVersionTransition']['NoncurrentDays'])) {
		$r['NoncurrentVersionTransition']['NoncurrentDays'] = (int)$r['NoncurrentVersionTransition']['NoncurrentDays'];
	}
	// Filter size coercions if And used
	if (isset($r['Filter']['And'])) {
		$and = $r['Filter']['And'];
		if (isset($and['ObjectSizeGreaterThan'])) {
			$r['Filter']['And']['ObjectSizeGreaterThan'] = (int)$and['ObjectSizeGreaterThan'];
		}
		if (isset($and['ObjectSizeLessThan'])) {
			$r['Filter']['And']['ObjectSizeLessThan'] = (int)$and['ObjectSizeLessThan'];
		}
	}
}
unset($r);

// Log normalization summary
try {
	logModuleCall('cloudstorage', 'lifecycle_normalized', [
		'bucket' => $bucketName,
		'rules_count' => count($incomingRules),
		'ids' => array_values(array_map(function($x){ return $x['ID'] ?? ''; }, $incomingRules))
	], 'Rules normalized prior to put');
} catch (\Throwable $e) {}

$res = $service->put($bucketName, $incomingRules, (int)$owner->id, $encryptionKey);
if (($res['status'] ?? 'fail') !== 'success') {
	(new JsonResponse(['status' => 'fail', 'message' => $res['message'] ?? 'Unable to save lifecycle rule. Please try again later.'], 200))->send();
	exit;
}

(new JsonResponse(['status' => 'success', 'message' => $res['message'] ?? 'Saved.']))->send();
exit;


