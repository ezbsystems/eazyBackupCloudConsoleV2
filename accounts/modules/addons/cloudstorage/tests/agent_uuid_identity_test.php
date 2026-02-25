<?php
require_once __DIR__ . '/../lib/Client/AgentIdentity.php';

use WHMCS\Module\Addon\CloudStorage\Client\AgentIdentity;

assert(AgentIdentity::isValidUuid('6f78c615-3d2f-4b7f-8f5b-56dc0a3da781') === true);
assert(AgentIdentity::isValidUuid('123') === false);
echo "ok\n";
