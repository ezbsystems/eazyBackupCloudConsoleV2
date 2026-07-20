package graphsync

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync"
	"testing"
	"time"

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

func TestSyncMailWritesFoldersCatalogAndBrowseIndex(t *testing.T) {
	const userID = "user-mail-index"
	folderID := "folder-inbox"
	msgID := "msg-hello"

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		switch {
		case strings.HasSuffix(r.URL.Path, "/mailFolders") && !strings.Contains(r.URL.Path, "/messages/"):
			_, _ = w.Write([]byte(`{"value":[{"id":"` + folderID + `","displayName":"Inbox"}]}`))
		case strings.Contains(r.URL.Path, "/messages/delta"):
			payload, _ := json.Marshal(map[string]any{
				"value": []map[string]any{{
					"id":               msgID,
					"subject":          "Quarterly report",
					"receivedDateTime": "2026-06-23T12:00:00Z",
					"from":             map[string]any{"emailAddress": map[string]any{"name": "Finance", "address": "finance@contoso.com"}},
					"hasAttachments":   true,
					"body":             map[string]any{"contentType": "text", "content": "test"},
				}},
				"@odata.deltaLink": "https://graph.test/delta-done",
			})
			_, _ = w.Write(payload)
		case strings.Contains(r.URL.Path, "/attachments"):
			_, _ = w.Write([]byte(`{"value":[{"id":"att1","name":"report.pdf","size":42}]}`))
		case strings.Contains(r.URL.Path, "/attachments/") && strings.HasSuffix(r.URL.Path, "/$value"):
			_, _ = w.Write([]byte("pdf"))
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 0, MaxConcurrency: 4})
	overlay := graphfs.NewOverlayBuilder()

	_, err := SyncMail(context.Background(), client, MailSyncOptions{
		AzureTenantID:  "tenant-1",
		UserID:         userID,
		Parallel:       2,
		FolderParallel: 1,
		Staging:        overlay,
	})
	if err != nil {
		t.Fatalf("SyncMail: %v", err)
	}

	catalogPath := "tenant-1/users/" + userID + "/mail/folders.json"
	catalogRaw, ok := overlay.ReadFile(catalogPath)
	if !ok {
		t.Fatalf("missing %s", catalogPath)
	}
	var catalog map[string]any
	if err := json.Unmarshal(catalogRaw, &catalog); err != nil {
		t.Fatalf("folders catalog: %v", err)
	}
	values, _ := catalog["value"].([]any)
	if len(values) != 1 {
		t.Fatalf("folders catalog entries = %d, want 1", len(values))
	}

	browsePath := mailBrowseIndexPath("tenant-1", userID, folderID)
	var browse mailBrowseIndex
	if !overlay.ReadJSON(browsePath, &browse) {
		t.Fatalf("missing %s", browsePath)
	}
	if browse.Version != mailBrowseIndexVersion {
		t.Fatalf("browse version = %d", browse.Version)
	}
	entry, ok := browse.Messages[safeID(msgID)]
	if !ok {
		t.Fatalf("browse index missing message %s", safeID(msgID))
	}
	if entry.Subject != "Quarterly report" || !entry.HasAttachments {
		t.Fatalf("browse entry: %#v", entry)
	}
}

func TestSyncMailBrowseIndexIncrementalMergeAndDeletion(t *testing.T) {
	const userID = "user-mail-merge"
	folderID := "folder-inbox"
	keptID := "msg-kept"
	removedID := "msg-removed"
	newID := "msg-new"

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		switch {
		case strings.HasSuffix(r.URL.Path, "/mailFolders"):
			_, _ = w.Write([]byte(`{"value":[{"id":"` + folderID + `","displayName":"Inbox"}]}`))
		case strings.Contains(r.URL.Path, "/messages/delta"):
			payload, _ := json.Marshal(map[string]any{
				"value": []map[string]any{
					{
						"id":               keptID,
						"subject":          "Updated subject",
						"receivedDateTime": "2026-06-24T12:00:00Z",
						"body":             map[string]any{"contentType": "text", "content": "kept"},
					},
					{
						"id":      removedID,
						"@removed": map[string]any{"reason": "deleted"},
					},
					{
						"id":               newID,
						"subject":          "Brand new",
						"receivedDateTime": "2026-06-25T12:00:00Z",
						"body":             map[string]any{"contentType": "text", "content": "new"},
					},
				},
				"@odata.deltaLink": "https://graph.test/delta-done",
			})
			_, _ = w.Write(payload)
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	overlay := graphfs.NewOverlayBuilder()
	prior := mailBrowseIndex{
		Version: mailBrowseIndexVersion,
		Messages: map[string]mailBrowseIndexEntry{
			safeID(keptID):    {ID: keptID, Subject: "Old subject"},
			safeID(removedID): {ID: removedID, Subject: "Gone"},
			safeID("msg-stale"): {ID: "msg-stale", Subject: "Should remain"},
		},
	}
	priorRaw, _ := json.Marshal(prior)
	overlay.PutJSON(mailBrowseIndexPath("tenant-1", userID, folderID), priorRaw, time.Now())

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 0, MaxConcurrency: 4})
	_, err := SyncMail(context.Background(), client, MailSyncOptions{
		AzureTenantID:  "tenant-1",
		UserID:         userID,
		Parallel:       2,
		FolderParallel: 1,
		Staging:        overlay,
	})
	if err != nil {
		t.Fatalf("SyncMail: %v", err)
	}

	var merged mailBrowseIndex
	if !overlay.ReadJSON(mailBrowseIndexPath("tenant-1", userID, folderID), &merged) {
		t.Fatal("missing merged browse index")
	}
	if _, ok := merged.Messages[safeID(removedID)]; ok {
		t.Fatal("removed message still in browse index")
	}
	if merged.Messages[safeID(keptID)].Subject != "Updated subject" {
		t.Fatalf("kept subject: %#v", merged.Messages[safeID(keptID)])
	}
	if merged.Messages[safeID(newID)].Subject != "Brand new" {
		t.Fatalf("new subject: %#v", merged.Messages[safeID(newID)])
	}
	if merged.Messages[safeID("msg-stale")].Subject != "Should remain" {
		t.Fatal("stale prior entry should remain when not in delta")
	}
}

func TestSyncMailMalformedPriorBrowseIndexRecovered(t *testing.T) {
	const userID = "user-mail-bad-index"
	folderID := "folder-inbox"
	msgID := "msg-only"

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		switch {
		case strings.HasSuffix(r.URL.Path, "/mailFolders"):
			_, _ = w.Write([]byte(`{"value":[{"id":"` + folderID + `","displayName":"Inbox"}]}`))
		case strings.Contains(r.URL.Path, "/messages/delta"):
			payload, _ := json.Marshal(map[string]any{
				"value": []map[string]any{{
					"id": msgID, "subject": "Fresh", "body": map[string]any{"contentType": "text", "content": "x"},
				}},
				"@odata.deltaLink": "https://graph.test/delta-done",
			})
			_, _ = w.Write(payload)
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	overlay := graphfs.NewOverlayBuilder()
	overlay.PutJSON(mailBrowseIndexPath("tenant-1", userID, folderID), []byte(`not-json`), time.Now())

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 0, MaxConcurrency: 4})
	_, err := SyncMail(context.Background(), client, MailSyncOptions{
		AzureTenantID:  "tenant-1",
		UserID:         userID,
		Parallel:       2,
		FolderParallel: 1,
		Staging:        overlay,
	})
	if err != nil {
		t.Fatalf("SyncMail: %v", err)
	}

	var browse mailBrowseIndex
	if !overlay.ReadJSON(mailBrowseIndexPath("tenant-1", userID, folderID), &browse) {
		t.Fatal("missing rebuilt browse index")
	}
	if browse.Messages[safeID(msgID)].Subject != "Fresh" {
		t.Fatalf("browse entry: %#v", browse.Messages[safeID(msgID)])
	}
}
