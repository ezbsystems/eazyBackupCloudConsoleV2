<?php

add_hook('ClientAreaHeadOutput', 1, function($vars)
{
    return <<<HTML
<meta name="robots" content="noindex">
HTML;
});
