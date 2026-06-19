package graphsync

// CalendarGraphFields mirrors PHP Ms365Backup\CalendarGraphFields.
const (
	CalendarListSelect     = "id,createdDateTime,lastModifiedDateTime,changeKey,iCalUId,type,seriesMasterId,recurrence,start,end,originalStart,isCancelled,hasAttachments,attendees,organizer,body,locations,onlineMeeting"
	CalendarSeriesSelect   = "id,createdDateTime,lastModifiedDateTime,changeKey,iCalUId,type,recurrence,start,end,cancelledOccurrences,exceptionOccurrences"
	CalendarPreferImmutable = `IdType="ImmutableId"`
	CalendarNormalPageSize = "100"
	CalendarPartitionPageSize = "25"
	CalendarInventoryStart = "1990-01-01T00:00:00Z"
)

// CalendarImmutableHeaders returns Prefer header for stable event IDs.
func CalendarImmutableHeaders() map[string]string {
	return map[string]string{"Prefer": CalendarPreferImmutable}
}

// CreatedDateTimeFilter builds an OData filter with unquoted datetime literals.
func CreatedDateTimeFilter(start, end string) string {
	return "createdDateTime ge " + start + " and createdDateTime lt " + end
}

// LastModifiedWatermarkFilter builds incremental inventory filter.
func LastModifiedWatermarkFilter(watermark string) string {
	return "lastModifiedDateTime ge " + watermark
}
