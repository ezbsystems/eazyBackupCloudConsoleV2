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

    public function isAuthenticationFailure(): bool
    {
        if ($this->statusCode === 401) {
            return true;
        }

        $code = strtolower($this->errorCode);
        if (in_array($code, [
            'invalidauthenticationtoken',
            'authentication_failed',
            'authorization_identity_not_found',
        ], true)) {
            return true;
        }

        return stripos($this->getMessage(), 'identity of the calling application could not be established') !== false;
    }

    /** SharePoint / sites APIs are unavailable without SharePoint Online licensing. */
    public static function isSharePointUnavailable(\Throwable $e): bool
    {
        $msg = $e->getMessage();
        if (stripos($msg, 'SPO license') !== false
            || stripos($msg, 'does not have a SPO') !== false
            || (stripos($msg, 'sharepoint') !== false && stripos($msg, 'license') !== false)) {
            return true;
        }

        if (!$e instanceof self) {
            return false;
        }

        $code = strtolower($e->errorCode);
        if ($code !== '' && (str_contains($code, 'sharepoint') || str_contains($code, 'spo'))) {
            return true;
        }

        return $e->statusCode === 400 && stripos($msg, 'site') !== false;
    }
}
