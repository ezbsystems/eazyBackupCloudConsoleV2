<?php

declare(strict_types=1);

$target = __DIR__ . '/../terms/index.php';
if (is_file($target)) {
    return require $target;
}

return '<div class="alert alert-danger">Terms controller body missing.</div>';


