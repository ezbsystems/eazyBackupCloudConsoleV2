package kopia

import (
	"strings"
	"testing"
)

func TestParseMailMetadataFromJSON(t *testing.T) {
	raw := []byte(`{
		"id":"msg1",
		"subject":"Team offsite agenda #7",
		"receivedDateTime":"2025-06-11T15:39:00Z",
		"isDraft":false,
		"from":{"emailAddress":{"name":"Contoso Admin","address":"admin@contoso.com"}},
		"body":{"contentType":"html","content":"<p>long body</p>"}
	}`)

	meta := parseMailMetadata(raw)
	if meta.Subject != "Team offsite agenda #7" {
		t.Fatalf("subject: got %q", meta.Subject)
	}
	if meta.FromName != "Contoso Admin" {
		t.Fatalf("from name: got %q", meta.FromName)
	}
	if meta.ReceivedAt != "2025-06-11T15:39:00Z" {
		t.Fatalf("received: got %q", meta.ReceivedAt)
	}
}

func TestParseMailMetadataDraftPrefix(t *testing.T) {
	raw := []byte(`{"subject":"Budget approval request","isDraft":true,"sentDateTime":"2025-06-11T14:00:00Z"}`)
	meta := parseMailMetadata(raw)
	if !meta.IsDraft {
		t.Fatal("expected draft")
	}
	labels := mailMessageLabelsFromMeta(meta)
	if labels.Label != "(Draft) Budget approval request" {
		t.Fatalf("label: got %q", labels.Label)
	}
}

func TestParseCalendarMetadata(t *testing.T) {
	raw := []byte(`{
		"subject":"Budget review 4",
		"type":"singleInstance",
		"isAllDay":false,
		"isCancelled":false,
		"start":{"dateTime":"2025-06-16T18:40:00Z","timeZone":"UTC"}
	}`)

	meta := parseCalendarMetadata(raw)
	if meta.Subject != "Budget review 4" {
		t.Fatalf("subject: got %q", meta.Subject)
	}
	if meta.StartAt != "2025-06-16T18:40:00Z" {
		t.Fatalf("start: got %q", meta.StartAt)
	}
	if meta.EventType != "singleInstance" {
		t.Fatalf("type: got %q", meta.EventType)
	}
}

func TestParseCalendarMetadataRecurring(t *testing.T) {
	raw := []byte(`{"subject":"Recurring Meeting #1","type":"seriesMaster","start":{"dateTime":"2025-06-16T00:00:00Z"}}`)
	meta := parseCalendarMetadata(raw)
	if meta.Subject != "Recurring Meeting #1" {
		t.Fatalf("subject: got %q", meta.Subject)
	}
	if meta.EventType != "seriesMaster" {
		t.Fatalf("type: got %q", meta.EventType)
	}
}

func TestOpaqueCalendarFolderFallback(t *testing.T) {
	got := opaqueCalendarFolderFallback("AAMkAGVjZGNkNjgyLWI0ZWUtNDRjMy1iNzc3LWM2MmUzYzZlOGJmYwBGAAAAAAB3V4t7mfolRqmlVW5Vax4UBwCZGheBG4SjR6g15N32C-o8AAAAAAEGAACZGheBG4SjR6g15N32C-o8AAJBSNl-AAA=")
	if !strings.Contains(got, "Calendar …") {
		t.Fatalf("fallback: got %q", got)
	}
}

func mailMessageLabelsFromMeta(meta mailMetadata) browseLabelResult {
	subject := meta.Subject
	if subject == "" {
		subject = "(No subject)"
	}
	if meta.IsDraft {
		subject = "(Draft) " + subject
	}
	sender := meta.FromName
	if sender == "" {
		sender = "Unknown sender"
	}
	when := formatMailDate(meta.ReceivedAt)
	if when == "" {
		when = formatMailDate(meta.SentAt)
	}
	subtitle := sender
	if when != "" {
		subtitle = sender + " · " + when
	}
	return browseLabelResult{Label: subject, Subtitle: subtitle}
}
