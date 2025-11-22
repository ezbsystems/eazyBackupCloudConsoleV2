<?php

namespace eazyBackup\CometCompat;

class UserWebAccountRegenerateTotpRequest implements \Comet\NetworkRequest {

    /** @var string */
    protected $Username;
    /** @var string */
    protected $SessionKey;
    /** @var string */
    protected $ProfileHash;

    public function __construct(string $username, string $sessionKey, string $profileHash) {
        $this->Username    = $username;
        $this->SessionKey  = $sessionKey;
        $this->ProfileHash = $profileHash;
    }

    public function Endpoint(): string { return '/api/v1/user/web/account/regenerate-totp'; }
    public function Method(): string { return 'POST'; }
    public function ContentType(): string { return 'application/x-www-form-urlencoded'; }

    public function Parameters(): array {
        $ret = [];
        $ret['Username']    = $this->Username;
        $ret['AuthType']    = 'SessionKey';
        $ret['SessionKey']  = $this->SessionKey;
        $ret['ProfileHash'] = $this->ProfileHash;
        return $ret;
    }

    /**
     * @return \Comet\TotpRegeneratedResponse
     * @throws \Exception
     */
    public static function ProcessResponse(int $responseCode, string $body): \Comet\TotpRegeneratedResponse {
        if ($responseCode !== 200) {
            throw new \Exception("Unexpected HTTP " . intval($responseCode) . " response", $responseCode);
        }
        $decoded = \json_decode($body);
        if (\json_last_error() !== \JSON_ERROR_NONE) {
            throw new \Exception("JSON decode failed: " . \json_last_error_msg(), \json_last_error());
        }
        $isCARM = (($decoded instanceof \stdClass) && property_exists($decoded, 'Status') && property_exists($decoded, 'Message'));
        if ($isCARM && $decoded->Status >= 400) {
            $carm = \Comet\APIResponseMessage::createFromStdclass($decoded);
            throw new \Exception("Error " . $carm->Status . ": " . $carm->Message, $carm->Status);
        }
        if (is_array($decoded) && count($decoded) === 0) {
            return \Comet\TotpRegeneratedResponse::createFromStdclass(new \stdClass());
        }
        return \Comet\TotpRegeneratedResponse::createFromStdclass($decoded);
    }
}
