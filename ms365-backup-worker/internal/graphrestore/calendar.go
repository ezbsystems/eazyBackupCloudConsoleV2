package graphrestore

import "strings"

// calendarIDFromSnapshotPath reads the owning calendar id from a snapshot path such as
// {tenant}/users/{userId}/calendar/{calendarId}/{eventId}.json. Backup stores the id
// in the path because Graph event bodies do not include calendarId.
func calendarIDFromSnapshotPath(path string) string {
	parts := strings.Split(strings.Trim(path, "/"), "/")
	for i, part := range parts {
		if part == "calendar" && i+1 < len(parts) {
			return strings.TrimSpace(parts[i+1])
		}
	}
	return ""
}

// eventCreateReadOnlyFields are Graph properties that must not be sent on event POST.
var eventCreateReadOnlyFields = []string{
	"@odata.context",
	"@odata.etag",
	"id",
	"changeKey",
	"createdDateTime",
	"lastModifiedDateTime",
	"webLink",
	"isOrganizer",
	"bodyPreview",
	"seriesMasterId",
	"occurrenceId",
	"onlineMeetingUrl",
	"onlineMeeting",
	"transactionId",
	"hasAttachments",
	"uid",
	"originalStart",
	"calendarId",
	"parentCalendarId",
	"organizer",
	"responseStatus",
	"attendees",
	"exceptionOccurrences",
	"extensions",
	"multiValueExtendedProperties",
	"singleValueExtendedProperties",
}

func sanitizeEventForCreate(event map[string]any) {
	for _, key := range eventCreateReadOnlyFields {
		delete(event, key)
	}
}
