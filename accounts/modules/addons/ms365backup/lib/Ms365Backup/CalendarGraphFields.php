<?php
declare(strict_types=1);

namespace Ms365Backup;

final class CalendarGraphFields
{
    public const LIST_SELECT = 'id,createdDateTime,lastModifiedDateTime,changeKey,iCalUId,type,seriesMasterId,recurrence,start,end,originalStart,isCancelled,hasAttachments,attendees,organizer,body,locations,onlineMeeting';

    public const SERIES_GET_SELECT = 'id,createdDateTime,lastModifiedDateTime,changeKey,iCalUId,type,recurrence,start,end,cancelledOccurrences,exceptionOccurrences';

    /** @var array<string, string> */
    public const PREFER_IMMUTABLE = ['Prefer' => 'IdType="ImmutableId"'];

    public const NORMAL_PAGE_SIZE = '100';

    public const PARTITION_PAGE_SIZE = '25';

    public const INVENTORY_START = '1990-01-01T00:00:00Z';

    /** OData $filter for Edm.DateTimeOffset — literals must not be quoted (Graph 400 otherwise). */
    public static function createdDateTimeFilter(\DateTimeImmutable $start, \DateTimeImmutable $end): string
    {
        return sprintf(
            'createdDateTime ge %s and createdDateTime lt %s',
            $start->format('Y-m-d\TH:i:s\Z'),
            $end->format('Y-m-d\TH:i:s\Z'),
        );
    }
}
