<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Raised when Graph @odata.nextLink pagination appears stuck or loops.
 * See: https://github.com/microsoftgraph/msgraph-sdk-dotnet/issues/3070
 */
final class GraphPaginationException extends \RuntimeException
{
}
