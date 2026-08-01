<?php
declare(strict_types=1);

namespace Ms365Backup\Comet;

final class CometServiceMask
{
    public const CALENDAR = 1;
    public const CONTACT = 2;
    public const MAIL = 4;
    public const SHAREPOINT = 8;
    public const ONEDRIVE = 16;

    /** @return array{calendar: bool, contacts: bool, mail: bool, sharepoint: bool, onedrive: bool} */
    public static function decode(int $mask): array
    {
        return [
            'calendar' => ($mask & self::CALENDAR) !== 0,
            'contacts' => ($mask & self::CONTACT) !== 0,
            'mail' => ($mask & self::MAIL) !== 0,
            'sharepoint' => ($mask & self::SHAREPOINT) !== 0,
            'onedrive' => ($mask & self::ONEDRIVE) !== 0,
        ];
    }
}
