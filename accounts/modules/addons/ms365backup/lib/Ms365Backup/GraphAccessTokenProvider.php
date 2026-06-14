<?php
declare(strict_types=1);

namespace Ms365Backup;

interface GraphAccessTokenProvider
{
    public function getAccessToken(): string;
}
