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

func TestPaginationSessionEmptyPagesAdvancingSkipToken(t *testing.T) {
	monitor := ForBackupPagination("directory:users", nil)
	session := newPaginationSession(monitor, nil, false)

	_, err := session.processPage(mkItems("u1", "u2"), "https://graph.microsoft.com/v1.0/users/delta?$skiptoken=token-a")
	if err != nil {
		t.Fatal(err)
	}
	for i, token := range []string{"token-b", "token-c", "token-d", "token-e"} {
		link := "https://graph.microsoft.com/v1.0/users/delta?$skiptoken=" + token
		_, err := session.processPage(nil, link)
		if err != nil {
			t.Fatalf("empty page %d with advancing skip token should not error: %v", i+2, err)
		}
	}
}

func TestPaginationSessionEmptyPagesSameSkipTokenWedge(t *testing.T) {
	monitor := ForBackupPagination("test", nil)
	session := newPaginationSession(monitor, nil, false)

	link := "https://graph.microsoft.com/v1.0/items/delta?$skiptoken=stuck"
	_, err := session.processPage(mkItems("a"), link)
	if err != nil {
		t.Fatal(err)
	}
	for i := 0; i < 3; i++ {
		_, err = session.processPage(nil, link)
		if err != nil {
			if _, ok := err.(*GraphPaginationError); !ok {
				t.Fatalf("page %d: expected GraphPaginationError, got %T: %v", i+2, err, err)
			}
			return
		}
	}
	t.Fatal("expected wedge error after repeated empty pages with same skip token")
}

func mkItems(ids ...string) []map[string]any {
	out := make([]map[string]any, 0, len(ids))
	for _, id := range ids {
		out = append(out, map[string]any{"id": id})
	}
	return out
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
