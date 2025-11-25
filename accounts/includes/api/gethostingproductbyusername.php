<?php

use WHMCS\Database\Capsule;

function gethostingproductbyusername(array $params) 
{
    // This is the username passed from the API request
    $username = $params['username'];

    // Query WHMCS database to get product details
    $product = Capsule::table('tblhosting')
        ->where('username', $username)
        ->first();

    if ($product) {
        // If product found, query WHMCS database to get configurable options
        $configOptions = Capsule::table('tblhostingconfigoptions')
            ->where('relid', $product->id)
            ->get();

        $configOptionsArray = [];
        foreach ($configOptions as $option) {
            $configOptionsArray[] = (array)$option;
        }

        // Return response
        return [
            'result' => 'success',
            'product' => (array)$product,
            'configoptions' => $configOptionsArray
        ];
    } else {
        // If product not found, return error response
        return [
            'result' => 'error',
            'message' => 'Product not found for the given username'
        ];
    }
}

$server = [
    'gethostingproductbyusername' => [
        'function' => 'gethostingproductbyusername',
        'signature' => ['username' => 'string'],
        'description' => 'Get hosting product details by username',
    ],
];

return $server;
