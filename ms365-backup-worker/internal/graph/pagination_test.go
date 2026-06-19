package graph

import "testing"

func TestPaginationSessionCapWarnContinue(t *testing.T) {
	monitor := ForBackupPagination("test", nil)
	monitor.MaxPages = 2
	monitor.CapMode = CapWarnContinue
	outcome := &PaginationOutcome{}
	session := newPaginationSession(monitor, outcome, false)

	_, err := session.processPage([]map[string]any{{"id": "a"}}, "https://graph.microsoft.com/next")
	if err != nil {
		t.Fatal(err)
	}
	_, err = session.processPage([]map[string]any{{"id": "b"}}, "https://graph.microsoft.com/next2")
	if err != nil {
		t.Fatal(err)
	}
	_, err = session.processPage([]map[string]any{{"id": "c"}}, "https://graph.microsoft.com/next3")
	if err != nil {
		t.Fatalf("warn_continue should not error on cap: %v", err)
	}
	if !outcome.CapReached {
		t.Fatal("expected CapReached")
	}
}

func TestExtractSkipToken(t *testing.T) {
	link := "https://graph.microsoft.com/v1.0/users/x/events?$skiptoken=abc123&$top=100"
	got := extractSkipToken(link)
	if got != "abc123" {
		t.Fatalf("skip token = %q", got)
	}
}

func TestLinkHashStable(t *testing.T) {
	a := linkHash("https://example.com?$skiptoken=1")
	b := linkHash("https://example.com?$skiptoken=1")
	if a != b {
		t.Fatal("hash not stable")
	}
	if a == linkHash("https://example.com?$skiptoken=2") {
		t.Fatal("different tokens should differ")
	}
}

func TestIsDeltaResetError(t *testing.T) {
	if !IsDeltaResetError(&DeltaResetError{Message: "gone"}) {
		t.Fatal("expected delta reset")
	}
	if IsDeltaResetError(&GraphPaginationError{Message: "loop"}) {
		t.Fatal("pagination error is not delta reset")
	}
}

func TestPaginationSessionDuplicateDetectOnly(t *testing.T) {
	monitor := ForCalendarNormalScan("test", nil)
	outcome := &PaginationOutcome{}
	session := newPaginationSession(monitor, outcome, true)
	session.seenItemIDs["a"] = true

	items := []map[string]any{{"id": "a"}}
	yielded, err := session.processPage(items, "https://graph.microsoft.com/next")
	if err != nil {
		t.Fatal(err)
	}
	if len(yielded) != 0 {
		t.Fatalf("expected no new items, got %d", len(yielded))
	}
	if !session.stopped() {
		t.Fatal("expected stopped on duplicate page")
	}
	session.finish(false)
	if !outcome.StoppedOnDuplicatePage {
		t.Fatal("outcome should record duplicate stop")
	}
}
