<?php

namespace WHMCS\Module\Addon\Eazybackup;

class Helper {

    /**
     * @param int $bytes
     * @param int $decimals
     * @return string
     */
    public static function humanFileSize($bytes, $decimals = 0)
    {
        $size = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = (int) floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
    }
}