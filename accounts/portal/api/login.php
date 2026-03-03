<?php

require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    portal_json(['status' => 'fail', 'message' => 'Invalid method'], 405);
}

if (!portal_validate_csrf()) {
    portal_json(['status' => 'fail', 'message' => 'CSRF failed'], 401);
}

$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    portal_json(['status' => 'fail', 'message' => 'Email and password are required'], 400);
}

$result = portal_login($email, $password);
if (($result['status'] ?? 'fail') !== 'success') {
    portal_json($result, 401);
}

portal_json(['status' => 'success']);
