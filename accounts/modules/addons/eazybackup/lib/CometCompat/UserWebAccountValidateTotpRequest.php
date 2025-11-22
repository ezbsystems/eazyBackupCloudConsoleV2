<?php

namespace eazyBackup\CometCompat;

class UserWebAccountValidateTotpRequest implements \Comet\NetworkRequest {

    /** @var string */
    protected $Username;
    /** @var string */
    protected $SessionKey;
    /** @var string */
    protected $ProfileHash;
    /** @var string */
    protected $TOTPCode;

    public function __construct(string $username, string $sessionKey, string $profileHash, string $totpCode) {
        $this->Username    = $username;
        $this->SessionKey  = $sessionKey;
        $this->ProfileHash = $profileHash;
        $this->TOTPCode    = $totpCode;
    }

    public function Endpoint(): string { return '/api/v1/user/web/account/validate-totp'; }
    public function Method(): string { return 'POST'; }
    public function ContentType(): string { return 'application/x-www-form-urlencoded'; }

    public function Parameters(): array {
        $ret = [];
        $ret['Username']    = $this->Username;
        $ret['AuthType']    = 'SessionKey';
        $ret['SessionKey']  = $this->SessionKey;
        $ret['ProfileHash'] = $this->ProfileHash;
        $ret['TOTPCode']    = $this->TOTPCode;
        return $ret;
    }

    /**
     * @return \Comet\APIResponseMessage
     * @throws \Exception
     */
    public static function ProcessResponse(int $responseCode, string $body): \Comet\APIResponseMessage {
        if ($responseCode !== 200) {
            throw new \Exception("Unexpected HTTP " . intval($responseCode) . " response", $responseCode);
        }
        $decoded = \json_decode($body);
        if (\json_last_error() !== \JSON_ERROR_NONE) {
            throw new \Exception("JSON decode failed: " . \json_last_error_msg(), \json_last_error());
        }
        // Validate standard Comet API response shape
        $isCARM = (($decoded instanceof \stdClass) && property_exists($decoded, 'Status') && property_exists($decoded, 'Message'));
        if (!$isCARM) {
            throw new \Exception("Unexpected response type for ValidateTotp");
        }
        $carm = \Comet\APIResponseMessage::createFromStdclass($decoded);
        if ($carm->Status >= 400) {
            throw new \Exception("Error " . $carm->Status . ": " . $carm->Message, $carm->Status);
        }
        return $carm;
    }
}
