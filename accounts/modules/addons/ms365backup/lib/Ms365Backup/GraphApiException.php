<?php
declare(strict_types=1);

namespace Ms365Backup;

final class GraphApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $errorCode = '',
        public readonly string $innerErrorCode = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /** @param array<string, mixed>|null $body */
    public static function fromResponse(int $status, ?array $body): self
    {
        $errorCode = '';
        $innerErrorCode = '';
        $msg = (string) $status;
        if (is_array($body) && isset($body['error']) && is_array($body['error'])) {
            $err = $body['error'];
            $errorCode = (string) ($err['code'] ?? '');
            $msg = (string) ($err['message'] ?? json_encode($body));
            if (isset($err['innerError']) && is_array($err['innerError'])) {
                $innerErrorCode = (string) ($err['innerError']['code'] ?? '');
            }
        } elseif (is_array($body)) {
            $msg = json_encode($body) ?: (string) $status;
        }

        return new self(
            'Graph API error (' . $status . '): ' . $msg,
            $status,
            $errorCode,
            $innerErrorCode,
        );
    }
}
