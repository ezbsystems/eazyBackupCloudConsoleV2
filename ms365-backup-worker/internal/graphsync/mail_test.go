package graphsync

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync"
	"testing"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

func TestSyncMailProgressReportsMessageCounts(t *testing.T) {
	const userID = "user-mail-progress"
	folderIDs := []string{"folder-inbox", "folder-sent"}
	messageIDs := [][]string{
		{"msg-1", "msg-2", "msg-3"},
		{"msg-4", "msg-5"},
	}

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		switch {
		case strings.HasSuffix(r.URL.Path, "/mailFolders") && !strings.Contains(r.URL.Path, "/messages/"):
			_, _ = w.Write([]byte(`{"value":[` +
				`{"id":"folder-inbox","displayName":"Inbox"},` +
				`{"id":"folder-sent","displayName":"Sent Items"}` +
				`]}`))
		case strings.Contains(r.URL.Path, "/messages/delta"):
			folderID := ""
			for _, id := range folderIDs {
				if strings.Contains(r.URL.Path, id) {
					folderID = id
					break
				}
			}
			idx := 0
			if folderID == folderIDs[1] {
				idx = 1
			}
			var value []map[string]any
			for _, msgID := range messageIDs[idx] {
				value = append(value, map[string]any{
					"id":               msgID,
					"subject":          "hello",
					"receivedDateTime": "2026-06-23T12:00:00Z",
					"body":             map[string]any{"contentType": "text", "content": "test"},
				})
			}
			payload, _ := json.Marshal(map[string]any{
				"value":      value,
				"@odata.deltaLink": "https://graph.test/delta-done",
			})
			_, _ = w.Write(payload)
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 0, MaxConcurrency: 4})
	overlay := graphfs.NewOverlayBuilder()

	var progressMu sync.Mutex
	var progress [][3]int
	res, err := SyncMail(context.Background(), client, MailSyncOptions{
		AzureTenantID: "tenant-1",
		UserID:        userID,
		Parallel:      2,
		FolderParallel: 2,
		Staging:       overlay,
		OnProgress: func(done, total int, _ int64) {
			progressMu.Lock()
			progress = append(progress, [3]int{done, total, total - done})
			progressMu.Unlock()
		},
	})
	if err != nil {
		t.Fatalf("SyncMail: %v", err)
	}

	if res.Stats.Messages != 5 {
		t.Fatalf("messages stored = %d, want 5", res.Stats.Messages)
	}
	if len(progress) == 0 {
		t.Fatal("expected progress callbacks")
	}

	last := progress[len(progress)-1]
	if last[0] != 5 {
		t.Fatalf("final progress done = %d, want 5 message count (not folder count 2)", last[0])
	}
	if last[1] < last[0] {
		t.Fatalf("final progress total %d < done %d", last[1], last[0])
	}
	if last[0] == 2 {
		t.Fatal("progress still reporting folder counts")
	}

	for i, snap := range progress {
		if snap[1] < snap[0] {
			t.Fatalf("progress[%d] total %d < done %d", i, snap[1], snap[0])
		}
	}
}
