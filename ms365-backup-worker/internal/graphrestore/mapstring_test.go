package graphrestore

import (
	"encoding/json"
	"testing"
)

func TestMapStringMissingOrNullIsEmpty(t *testing.T) {
	// A calendar event captured from Graph has no calendarId in its body, and
	// some payloads include an explicit JSON null. Both must resolve to "" so
	// the default-calendar fallback can trigger — never the literal "<nil>".
	raw := []byte(`{"subject":"Standup","calendarId":null,"iCalUId":"abc"}`)
	var m map[string]any
	if err := json.Unmarshal(raw, &m); err != nil {
		t.Fatalf("unmarshal: %v", err)
	}

	cases := map[string]string{
		"calendarId":       "",    // explicit null
		"parentCalendarId": "",    // missing key
		"iCalUId":          "abc", // present string (trimmed)
		"subject":          "Standup",
	}
	for key, want := range cases {
		if got := mapString(m, key); got != want {
			t.Errorf("mapString(%q) = %q, want %q", key, got, want)
		}
	}
}

func TestMapStringTrimsAndStringifies(t *testing.T) {
	m := map[string]any{
		"padded": "  trimmed  ",
		"number": float64(42),
	}
	if got := mapString(m, "padded"); got != "trimmed" {
		t.Errorf("mapString(padded) = %q, want %q", got, "trimmed")
	}
	if got := mapString(m, "number"); got != "42" {
		t.Errorf("mapString(number) = %q, want %q", got, "42")
	}
}
