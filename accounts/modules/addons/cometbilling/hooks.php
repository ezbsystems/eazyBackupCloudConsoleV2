<?php
if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

add_hook('DailyCronJob', 1, function() {
    if (function_exists('cometbilling_cron')) {
        cometbilling_cron([]);
    }
});


