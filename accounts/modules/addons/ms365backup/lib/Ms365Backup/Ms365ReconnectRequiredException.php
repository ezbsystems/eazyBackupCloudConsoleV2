<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Thrown when Microsoft 365 consent/token is invalid and the customer must reconnect.
 */
final class Ms365ReconnectRequiredException extends \RuntimeException
{
}
