<?php
declare(strict_types=1);

namespace Ms365Backup\Seeder;

final class SeederProfileCatalog
{
    /** @return array<string, array<string, int>> */
    public static function profiles(): array
    {
        return [
            'light' => [
                'mail_per_user' => 10,
                'events_per_user' => 5,
                'contacts_per_user' => 10,
                'tasks_per_user' => 5,
                'onedrive_files_per_user' => 10,
                'sharepoint_files_per_site' => 5,
                'teams_messages_per_channel' => 3,
            ],
            'standard' => [
                'mail_per_user' => 50,
                'events_per_user' => 20,
                'contacts_per_user' => 30,
                'tasks_per_user' => 15,
                'onedrive_files_per_user' => 50,
                'sharepoint_files_per_site' => 20,
                'teams_messages_per_channel' => 10,
            ],
            'heavy' => [
                'mail_per_user' => 200,
                'events_per_user' => 80,
                'contacts_per_user' => 100,
                'tasks_per_user' => 50,
                'onedrive_files_per_user' => 200,
                'sharepoint_files_per_site' => 100,
                'teams_messages_per_channel' => 30,
            ],
        ];
    }

    /** @return array<string, int> */
    public static function resolve(string $profile): array
    {
        $profiles = self::profiles();
        if (!isset($profiles[$profile])) {
            throw new \RuntimeException('Unknown seeder profile: ' . $profile);
        }

        return $profiles[$profile];
    }

    /** @return list<string> */
    public static function profileKeys(): array
    {
        return array_keys(self::profiles());
    }
}
