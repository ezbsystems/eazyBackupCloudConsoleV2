<?php

    require_once __DIR__ . '/../../../../init.php';

    if (!defined("WHMCS")) {
        die("This file cannot be accessed directly");
    }

    use Symfony\Component\HttpFoundation\JsonResponse;
    use WHMCS\ClientArea;
    use WHMCS\User\Client;

    $ca = new ClientArea();
    if (!$ca->isLoggedIn()) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Session timeout.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    if (!isset($_POST['password']) || empty($_POST['password'])) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Please enter the password.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    $password = $_POST["password"];
    $client = Client::find($ca->getUserID());
    $email = $client->email;

    $command = "ValidateLogin";
    $postData = [
        'email' => $email,
        'password2' => $password
    ];

    $results = localAPI($command, $postData);

    if ($results['result'] === 'success' && $results['userid'] > 0) {
        $jsonData = [
            'status' => 'success',
            'message' => 'Password is correct.'
        ];
    } else {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Password is incorrect.'
        ];

    }

    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();