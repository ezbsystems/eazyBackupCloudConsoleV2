package archive

import (
	"strings"
	"testing"
)

func TestBuildCalendarICSSingleEvent(t *testing.T) {
	ev := map[string]any{
		"id":       "evt1",
		"iCalUId":  "uid-123",
		"subject":  "Team meeting",
		"type":     "singleInstance",
		"start":    map[string]any{"dateTime": "2026-06-26T10:00:00Z", "timeZone": "UTC"},
		"end":      map[string]any{"dateTime": "2026-06-26T11:00:00Z", "timeZone": "UTC"},
		"location": map[string]any{},
		"locations": []any{
			map[string]any{"displayName": "Room A"},
		},
		"organizer": map[string]any{
			"emailAddress": map[string]any{"name": "Jane", "address": "jane@contoso.com"},
		},
	}
	ics, err := buildCalendarICS(ev)
	if err != nil {
		t.Fatalf("buildCalendarICS: %v", err)
	}
	s := string(ics)
	for _, want := range []string{"BEGIN:VCALENDAR", "BEGIN:VEVENT", "SUMMARY:Team meeting", "UID:uid-123", "LOCATION:Room A"} {
		if !strings.Contains(s, want) {
			t.Fatalf("missing %q in ICS:\n%s", want, s)
		}
	}
}

func TestBuildCalendarICSAllDay(t *testing.T) {
	ev := map[string]any{
		"id":      "evt2",
		"subject": "Holiday",
		"start":   map[string]any{"date": "2026-07-01"},
		"end":     map[string]any{"date": "2026-07-02"},
	}
	ics, err := buildCalendarICS(ev)
	if err != nil {
		t.Fatalf("buildCalendarICS: %v", err)
	}
	if !strings.Contains(string(ics), "DTSTART;VALUE=DATE:20260701") {
		t.Fatalf("expected all-day DTSTART, got %s", string(ics))
	}
}

func TestBuildCalendarICSCancelled(t *testing.T) {
	ev := map[string]any{
		"id":          "evt3",
		"subject":     "Cancelled",
		"isCancelled": true,
		"start":       map[string]any{"dateTime": "2026-06-26T10:00:00Z"},
		"end":         map[string]any{"dateTime": "2026-06-26T11:00:00Z"},
	}
	ics, err := buildCalendarICS(ev)
	if err != nil {
		t.Fatalf("buildCalendarICS: %v", err)
	}
	if !strings.Contains(string(ics), "STATUS:CANCELLED") {
		t.Fatalf("expected cancelled status, got %s", string(ics))
	}
}

func TestBuildCalendarICSRecurring(t *testing.T) {
	ev := map[string]any{
		"id":      "series1",
		"subject": "Weekly sync",
		"type":    "seriesMaster",
		"start":   map[string]any{"dateTime": "2026-06-26T10:00:00Z"},
		"end":     map[string]any{"dateTime": "2026-06-26T11:00:00Z"},
		"recurrence": map[string]any{
			"pattern": map[string]any{"type": "weekly", "interval": float64(1), "daysOfWeek": []any{"monday"}},
			"range":   map[string]any{"type": "noEnd"},
		},
	}
	ics, err := buildCalendarICS(ev)
	if err != nil {
		t.Fatalf("buildCalendarICS: %v", err)
	}
	if !strings.Contains(string(ics), "FREQ=WEEKLY") {
		t.Fatalf("expected RRULE, got %s", string(ics))
	}
}

func TestCalendarICSFilename(t *testing.T) {
	ev := map[string]any{
		"subject": "Standup",
		"start":   map[string]any{"dateTime": "2026-06-26T09:00:00Z"},
	}
	name := calendarICSFilename(ev)
	if !strings.HasPrefix(name, "2026-06-26_") || !strings.HasSuffix(name, ".ics") {
		t.Fatalf("unexpected filename %q", name)
	}
}

func TestBuildCalendarICSSanitizesLineBreaks(t *testing.T) {
	ev := map[string]any{
		"id":      "evt-lf",
		"subject": "Line\r\nBreak\rSubject",
		"body": map[string]any{
			"contentType": "text",
			"content":     "Desc line one\nDesc line two",
		},
		"start": map[string]any{"dateTime": "2026-06-26T10:00:00Z"},
		"end":   map[string]any{"dateTime": "2026-06-26T11:00:00Z"},
		"organizer": map[string]any{
			"emailAddress": map[string]any{"name": "Org\nName", "address": "org@contoso.com"},
		},
	}
	ics, err := buildCalendarICS(ev)
	if err != nil {
		t.Fatalf("buildCalendarICS: %v", err)
	}
	s := string(ics)
	if strings.Contains(s, "\nBreak") || strings.Contains(s, "line one\nDesc") {
		t.Fatalf("expected line breaks sanitized in ICS:\n%s", s)
	}
	if !strings.Contains(s, "SUMMARY:Line Break Subject") {
		t.Fatalf("expected sanitized summary, got:\n%s", s)
	}
}
