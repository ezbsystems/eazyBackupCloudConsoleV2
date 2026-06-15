package graphrestore

import "testing"

func TestCalendarIDFromSnapshotPath(t *testing.T) {
	path := "cfb5450a-eb80-4c61-aecc-9dca87649cf6/users/1533e37a-2e8f-4f24-8155-11777c70997d/calendar/AAMkAGVjZGNkNjgyLWI0ZWUtNDRjMy1iNzc3LWM2MmUzYzZlOGJmYwBGAAAAAAB3V4t7mfolRqmlVW5Vax4UBwCZGheBG4SjR6g15N32C-o8AAAAAAEGAACZGheBG4SjR6g15N32C-o8AAAFzWxyAAA=/AAMkAGVjZGNkNjgyLWI0ZWUtNDRjMy1iNzc3LWM2MmUzYzZlOGJmYwBGAAAAAAB3V4t7mfolRqmlVW5Vax4UBwCZGheBG4SjR6g15N32C-o8AAAAAAENAACZGheBG4SjR6g15N32C-o8AAJBSMW9AAA=.json"
	want := "AAMkAGVjZGNkNjgyLWI0ZWUtNDRjMy1iNzc3LWM2MmUzYzZlOGJmYwBGAAAAAAB3V4t7mfolRqmlVW5Vax4UBwCZGheBG4SjR6g15N32C-o8AAAAAAEGAACZGheBG4SjR6g15N32C-o8AAAFzWxyAAA="
	if got := calendarIDFromSnapshotPath(path); got != want {
		t.Fatalf("calendarIDFromSnapshotPath() = %q, want %q", got, want)
	}
}

func TestSanitizeEventForCreateRemovesReadOnlyFields(t *testing.T) {
	event := map[string]any{
		"subject":           "Team sync",
		"id":                "abc",
		"createdDateTime":   "2026-01-01T00:00:00Z",
		"lastModifiedDateTime": "2026-01-02T00:00:00Z",
		"organizer":         map[string]any{"emailAddress": map[string]any{"address": "a@b.com"}},
		"recurrence":        map[string]any{"pattern": map[string]any{"type": "weekly"}},
	}
	sanitizeEventForCreate(event)
	for _, key := range []string{"id", "createdDateTime", "lastModifiedDateTime", "organizer"} {
		if _, ok := event[key]; ok {
			t.Fatalf("sanitizeEventForCreate left read-only key %q", key)
		}
	}
	if _, ok := event["recurrence"]; !ok {
		t.Fatal("sanitizeEventForCreate removed recurrence")
	}
	if event["subject"] != "Team sync" {
		t.Fatal("sanitizeEventForCreate removed subject")
	}
}
