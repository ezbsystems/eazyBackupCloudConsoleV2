<?php

namespace WHMCS\Module\Addon\CloudStorage\Request;

use Symfony\Component\HttpFoundation\JsonResponse;

class TenantRequest {

    /**
     * Validate tenant delete request
     */
    public static function validateDelete($request)
    {
        if (empty($request['username'])) {
            $jsonData = [
                'status' => 'fail',
                'message' => 'Missing required parameter: username.'
            ];

            $response = new JsonResponse($jsonData, 400);
            $response->send();
            exit();
        }
    }

    /**
     * Validate decrypt key request
     */
    public static function validateDecryptKey($request)
    {
        if (empty($request['id']) || empty($request['username'])) {
            $jsonData = [
                'status' => 'fail',
                'message' => 'Missing required parameter: username and id.'
            ];

            $response = new JsonResponse($jsonData, 400);
            $response->send();
            exit();
        }
    }

    /**
     * Validate add tenant request
     */
    public static function validateTenant($request)
    {
        if (empty($request['name']) || empty($request['username'])) {
            $jsonData = [
                'status' => 'fail',
                'message' => 'Missing required parameter: username and name.'
            ];

            $response = new JsonResponse($jsonData, 400);
            $response->send();
            exit();
        }
    }

    /**
     * Validate add key request
     */
    public static function validateKey($request)
    {
        if (empty($request['type']) || empty($request['username']) ||  !in_array($request['type'], ['primary', 'subuser'])) {
            $jsonData = [
                'status' => 'fail',
                'message' => 'Missing required parameter: type and username.'
            ];

            $response = new JsonResponse($jsonData, 400);
            $response->send();
            exit();
        }

        if (
            $request['type'] == 'subuser' &&
            (
                empty($request['access']) ||
                empty($request['subusername']) ||
                !in_array($request['access'], ['read', 'write', 'readwrite', 'full'])
            )
        ) {
            $jsonData = [
                'status' => 'fail',
                'message' => 'Missing required parameter: access and subusername.'
            ];

            $response = new JsonResponse($jsonData, 400);
            $response->send();
            exit();
        }

    }

    /**
     * Validate delete key request
     */
    public static function validateDeleteKey($request)
    {
        if (
            empty($request['type']) ||
            empty($request['id']) ||
            empty($request['username']) ||
            !in_array($request['type'], ['primary', 'subuser'])
        ) {
            $jsonData = [
                'status' => 'fail',
                'message' => 'Missing required parameter: type, id and username.'
            ];

            $response = new JsonResponse($jsonData, 400);
            $response->send();
            exit();
        }
    }
}