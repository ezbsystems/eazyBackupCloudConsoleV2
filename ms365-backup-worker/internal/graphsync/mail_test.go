package graphsync

import (
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync"
	"testing"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

func TestSyncMailRetriesRepeatedFolderNextLinkWithSmallerPage(t *testing.T) {
	var serverURL string
	var folderPageSizes []string
	var currentTop string
	var deltaFolders []string
	var runLogMu sync.Mutex
	var runLog []string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		switch {
		case r.URL.Path == "/users/test-user/mailFolders":
			currentTop = r.URL.Query().Get("$top")
			folderPageSizes = append(folderPageSizes, currentTop)
			top := r.URL.Query().Get("$top")
			if top == "100" {
				_, _ = w.Write([]byte(`{"value":[{"id":"folder-1","displayName":"Inbox"}],"@odata.nextLink":"` + serverURL + `/mail-folders-page-2?top=100"}`))
				return
			}
			if top == "50" {
				_, _ = w.Write([]byte(`{"value":[{"id":"folder-1","displayName":"Inbox"},{"id":"folder-2","displayName":"Archive"}]}`))
				return
			}
			http.NotFound(w, r)
		case r.URL.Path == "/mail-folders-page-2":
			folderPageSizes = append(folderPageSizes, currentTop)
			_, _ = w.Write([]byte(`{"value":[{"id":"folder-2","displayName":"Archive"}],"@odata.nextLink":"` + serverURL + `/mail-folders-page-2?top=100"}`))
		case strings.Contains(r.URL.Path, "/messages/delta"):
			switch {
			case strings.Contains(r.URL.Path, "/mailFolders/folder-1/"):
				deltaFolders = append(deltaFolders, "folder-1")
			case strings.Contains(r.URL.Path, "/mailFolders/folder-2/"):
				deltaFolders = append(deltaFolders, "folder-2")
			}
			payload, _ := json.Marshal(map[string]any{
				"value":            []map[string]any{},
				"@odata.deltaLink": "https://graph.test/delta-done",
			})
			_, _ = w.Write(payload)
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()
	serverURL = srv.URL

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 0, MaxConcurrency: 1})
	res, err := SyncMail(context.Background(), client, MailSyncOptions{
		AzureTenantID:  "test-tenant",
		UserID:         "test-user",
		Parallel:       1,
		FolderParallel: 1,
		Staging:        graphfs.NewOverlayBuilder(),
		Log: func(level, message string) {
			runLogMu.Lock()
			runLog = append(runLog, level+": "+message)
			runLogMu.Unlock()
		},
	})
	if err != nil {
		t.Fatalf("SyncMail: %v", err)
	}
	if res.Stats.Folders != 2 {
		t.Fatalf("folders = %d, want 2", res.Stats.Folders)
	}
	if strings.Join(folderPageSizes, ",") != "100,100,50" {
		t.Fatalf("folder request page sizes = %v", folderPageSizes)
	}
	runLogMu.Lock()
	logText := strings.Join(runLog, "\n")
	runLogMu.Unlock()
	if !strings.Contains(logText, "retrying smaller page size") {
		t.Fatalf("run log missing intermediate wedge retry message: %q", logText)
	}
	if strings.Join(deltaFolders, ",") != "folder-1,folder-2" {
		t.Fatalf("message delta folders = %v, want [folder-1 folder-2]", deltaFolders)
	}
}

func TestSyncMailKeepsUniqueFoldersAfterFinalRepeatedNextLink(t *testing.T) {
	var serverURL string
	var runLogMu sync.Mutex
	var runLog []string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		switch {
		case r.URL.Path == "/users/test-user/mailFolders":
			_, _ = w.Write([]byte(`{"value":[{"id":"folder-1","displayName":"Inbox"}],"@odata.nextLink":"` + serverURL + `/mail-folders-page-2"}`))
		case r.URL.Path == "/mail-folders-page-2":
			_, _ = w.Write([]byte(`{"value":[{"id":"folder-1","displayName":"Inbox"},{"id":"folder-2","displayName":"Archive"}],"@odata.nextLink":"` + serverURL + `/mail-folders-page-2"}`))
		case strings.Contains(r.URL.Path, "/messages/delta"):
			payload, _ := json.Marshal(map[string]any{
				"value":            []map[string]any{},
				"@odata.deltaLink": "https://graph.test/delta-done",
			})
			_, _ = w.Write(payload)
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()
	serverURL = srv.URL

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 0, MaxConcurrency: 1})
	res, err := SyncMail(context.Background(), client, MailSyncOptions{
		AzureTenantID:  "test-tenant",
		UserID:         "test-user",
		Parallel:       1,
		FolderParallel: 1,
		Staging:        graphfs.NewOverlayBuilder(),
		Log: func(level, message string) {
			runLogMu.Lock()
			runLog = append(runLog, level+": "+message)
			runLogMu.Unlock()
		},
	})
	if err != nil {
		t.Fatalf("SyncMail: %v", err)
	}
	if res.Stats.Folders != 2 {
		t.Fatalf("folders = %d, want 2 unique folders (not 3 with repeated folder-1)", res.Stats.Folders)
	}
	runLogMu.Lock()
	logText := strings.Join(runLog, "\n")
	runLogMu.Unlock()
	if !strings.Contains(logText, "keeping unique folders returned") {
		t.Fatalf("run log missing final-wedge message: %q", logText)
	}
}

func TestSyncMailWellKnownShardSegmentMatchesFolder(t *testing.T) {
	var deltaFolders []string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		switch {
		case strings.HasSuffix(r.URL.Path, "/mailFolders") && !strings.Contains(r.URL.Path, "/messages/"):
			_, _ = w.Write([]byte(`{"value":[
				{"id":"AQMk-inbox-opaque","displayName":"Inbox","wellKnownName":"inbox"},
				{"id":"AQMk-sent-opaque","displayName":"Sent Items","wellKnownName":"sentitems"},
				{"id":"AQMk-custom","displayName":"Projects"}
			]}`))
		case strings.Contains(r.URL.Path, "/mailFolders/AQMk-inbox-opaque/messages/delta"):
			deltaFolders = append(deltaFolders, "inbox")
			_, _ = w.Write([]byte(`{"value":[{"id":"msg-1","subject":"Hello","body":{"content":"hi"}}],"@odata.deltaLink":"https://graph.test/inbox-done"}`))
		case strings.Contains(r.URL.Path, "/mailFolders/AQMk-sent-opaque/messages/delta"):
			deltaFolders = append(deltaFolders, "sent")
			_, _ = w.Write([]byte(`{"value":[],"@odata.deltaLink":"https://graph.test/sent-done"}`))
		case strings.Contains(r.URL.Path, "/mailFolders/AQMk-custom/messages/delta"):
			deltaFolders = append(deltaFolders, "custom")
			_, _ = w.Write([]byte(`{"value":[],"@odata.deltaLink":"https://graph.test/custom-done"}`))
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 0, MaxConcurrency: 2})
	result, err := SyncMail(context.Background(), client, MailSyncOptions{
		AzureTenantID:  "tenant-1",
		UserID:         "user-1",
		Parallel:       2,
		FolderParallel: 2,
		ShardKey:       "mail:inbox",
		Staging:        graphfs.NewOverlayBuilder(),
	})
	if err != nil {
		t.Fatalf("SyncMail well-known shard: %v", err)
	}
	if result.Stats.Folders != 3 {
		t.Fatalf("Folders = %d, want 3 catalogued", result.Stats.Folders)
	}
	if result.Stats.FoldersDelta != 1 {
		t.Fatalf("FoldersDelta = %d, want 1 (inbox shard only)", result.Stats.FoldersDelta)
	}
	if result.Stats.Messages != 1 {
		t.Fatalf("Messages = %d, want 1", result.Stats.Messages)
	}
	if strings.Join(deltaFolders, ",") != "inbox" {
		t.Fatalf("delta folders = %v, want [inbox] only (not opaque-id mismatch skip-all)", deltaFolders)
	}
}

func TestMailFolderMatchesShardWellKnownAndID(t *testing.T) {
	folder := map[string]any{
		"id":            "AQMk-opaque-id",
		"displayName":   "Inbox",
		"wellKnownName": "inbox",
	}
	if !mailFolderMatchesShard(folder, "inbox") {
		t.Fatal("expected wellKnownName inbox to match shard segment inbox")
	}
	if !mailFolderMatchesShard(folder, "AQMk-opaque-id") {
		t.Fatal("expected Graph folder id to match")
	}
	if mailFolderMatchesShard(folder, "sentitems") {
		t.Fatal("sentitems must not match inbox folder")
	}
}

func TestSyncMailRetriesQuotaExceededFolderWithSmallerPage(t *testing.T) {
	var deltaPageSizes []string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		switch {
		case strings.HasSuffix(r.URL.Path, "/mailFolders") && !strings.Contains(r.URL.Path, "/messages/"):
			_, _ = w.Write([]byte(`{"value":[{"id":"folder-junk","displayName":"Junk Email"}]}`))
		case strings.Contains(r.URL.Path, "/mailFolders/folder-junk/messages/delta"):
			top := r.URL.Query().Get("$top")
			deltaPageSizes = append(deltaPageSizes, top)
			if top == "100" {
				w.WriteHeader(http.StatusForbidden)
				_, _ = w.Write([]byte(`{"error":{"code":"ErrorQuotaExceeded","message":"The process failed to get the correct properties."}}`))
				return
			}
			if top != "25" {
				t.Fatalf("unexpected quota fallback page size %q", top)
			}
			_, _ = w.Write([]byte(`{"value":[],"@odata.deltaLink":"https://graph.test/delta-done"}`))
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 1, RetryBaseDelayMs: 1, MaxConcurrency: 2})
	result, err := SyncMail(context.Background(), client, MailSyncOptions{
		AzureTenantID:  "tenant-1",
		UserID:         "user-1",
		Parallel:       2,
		FolderParallel: 2,
		Staging:        graphfs.NewOverlayBuilder(),
	})
	if err != nil {
		t.Fatalf("quota fallback should preserve the folder backup: %v", err)
	}
	if strings.Join(deltaPageSizes, ",") != "100,25" {
		t.Fatalf("delta page sizes = %v, want [100 25]", deltaPageSizes)
	}
	if result.Stats.FoldersDelta != 1 {
		t.Fatalf("delta folders = %d, want 1", result.Stats.FoldersDelta)
	}
}

func TestSyncMailSkipsFolderWhenQuotaRetryAlsoFails(t *testing.T) {
	var warnings []string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		switch {
		case strings.HasSuffix(r.URL.Path, "/mailFolders") && !strings.Contains(r.URL.Path, "/messages/"):
			_, _ = w.Write([]byte(`{"value":[
				{"id":"folder-archive","displayName":"Archive"},
				{"id":"folder-inbox","displayName":"Inbox"}
			]}`))
		case strings.Contains(r.URL.Path, "/mailFolders/folder-archive/messages/delta"):
			w.WriteHeader(http.StatusForbidden)
			_, _ = w.Write([]byte(`{"error":{"code":"ErrorQuotaExceeded","message":"The process failed to get the correct properties."}}`))
		case strings.Contains(r.URL.Path, "/mailFolders/folder-inbox/messages/delta"):
			_, _ = w.Write([]byte(`{"value":[],"@odata.deltaLink":"https://graph.test/inbox-done"}`))
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 0, MaxConcurrency: 2})
	result, err := SyncMail(context.Background(), client, MailSyncOptions{
		AzureTenantID:  "tenant-1",
		UserID:         "user-1",
		Parallel:       2,
		FolderParallel: 2,
		Staging:        graphfs.NewOverlayBuilder(),
		Log: func(level, message string) {
			if level == "warning" {
				warnings = append(warnings, message)
			}
		},
	})
	if err != nil {
		t.Fatalf("persistent quota on one folder must not fail the mailbox: %v", err)
	}
	if result.Stats.FoldersQuotaSkipped != 1 {
		t.Fatalf("FoldersQuotaSkipped = %d, want 1", result.Stats.FoldersQuotaSkipped)
	}
	if result.Stats.FoldersDelta != 1 {
		t.Fatalf("FoldersDelta = %d, want 1 successful inbox", result.Stats.FoldersDelta)
	}
	joined := strings.Join(warnings, "\n")
	if !strings.Contains(joined, "skipping folder") || !strings.Contains(joined, "Archive") {
		t.Fatalf("expected skip warning for Archive, got %q", joined)
	}
}

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
				"value":            value,
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
		AzureTenantID:  "tenant-1",
		UserID:         userID,
		Parallel:       2,
		FolderParallel: 2,
		Staging:        overlay,
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
						"id":       removedID,
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
			safeID(keptID):      {ID: keptID, Subject: "Old subject"},
			safeID(removedID):   {ID: removedID, Subject: "Gone"},
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

func TestSyncMailRemovedMessageDeletesAttachmentSubtree(t *testing.T) {
	const userID = "user-mail-attach-remove"
	folderID := "folder-inbox"
	removedID := "msg-removed"
	keptID := "msg-kept"

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		switch {
		case strings.HasSuffix(r.URL.Path, "/mailFolders"):
			_, _ = w.Write([]byte(`{"value":[{"id":"` + folderID + `","displayName":"Inbox"}]}`))
		case strings.Contains(r.URL.Path, "/messages/delta"):
			payload, _ := json.Marshal(map[string]any{
				"value": []map[string]any{
					{
						"id":       removedID,
						"@removed": map[string]any{"reason": "deleted"},
					},
					{
						"id":               keptID,
						"subject":          "Still here",
						"receivedDateTime": "2026-06-24T12:00:00Z",
						"body":             map[string]any{"contentType": "text", "content": "kept"},
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
	removedAttach := mailMessageAttachmentPrefix("tenant-1", userID, folderID, removedID) + "attachments/invoice.pdf"
	untouchedAttach := mailMessageAttachmentPrefix("tenant-1", userID, folderID, "msg-untouched") + "attachments/report.pdf"
	overlay.PutJSON(removedAttach, []byte("old-pdf"), time.Now())
	overlay.PutJSON(untouchedAttach, []byte("untouched-pdf"), time.Now())
	overlay.PutJSON(
		fmt.Sprintf("%s/users/%s/mail/%s/%s.json", "tenant-1", userID, safeID(folderID), safeID(removedID)),
		[]byte(`{"id":"`+removedID+`"}`),
		time.Now(),
	)

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

	if overlay.HasPath(removedAttach) {
		t.Fatalf("removed message attachment still present at %s", removedAttach)
	}
	if overlay.HasPathPrefix(mailMessageAttachmentPrefix("tenant-1", userID, folderID, removedID)) {
		t.Fatal("removed message attachment prefix still has live entries")
	}
	if !overlay.HasPath(untouchedAttach) {
		t.Fatalf("untouched message attachment should remain at %s", untouchedAttach)
	}
}

func TestSyncMailUpdatedMessageClearsStaleAttachments(t *testing.T) {
	const userID = "user-mail-attach-update"
	folderID := "folder-inbox"
	msgID := "msg-update"

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		switch {
		case strings.HasSuffix(r.URL.Path, "/mailFolders"):
			_, _ = w.Write([]byte(`{"value":[{"id":"` + folderID + `","displayName":"Inbox"}]}`))
		case strings.Contains(r.URL.Path, "/messages/delta"):
			payload, _ := json.Marshal(map[string]any{
				"value": []map[string]any{{
					"id":               msgID,
					"subject":          "Updated attachments",
					"receivedDateTime": "2026-06-24T12:00:00Z",
					"hasAttachments":   true,
					"body":             map[string]any{"contentType": "text", "content": "updated"},
				}},
				"@odata.deltaLink": "https://graph.test/delta-done",
			})
			_, _ = w.Write(payload)
		case strings.Contains(r.URL.Path, "/attachments") && !strings.HasSuffix(r.URL.Path, "/$value"):
			_, _ = w.Write([]byte(`{"value":[{"id":"att-new","name":"new-file.pdf","size":11}]}`))
		case strings.Contains(r.URL.Path, "/attachments/") && strings.HasSuffix(r.URL.Path, "/$value"):
			_, _ = w.Write([]byte("new-pdf-bytes"))
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	overlay := graphfs.NewOverlayBuilder()
	staleAttach := mailMessageAttachmentPrefix("tenant-1", userID, folderID, msgID) + "attachments/old-file.pdf"
	overlay.PutJSON(staleAttach, []byte("stale-pdf"), time.Now())

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

	if overlay.HasPath(staleAttach) {
		t.Fatalf("stale attachment still present at %s", staleAttach)
	}
	newAttach := mailMessageAttachmentPrefix("tenant-1", userID, folderID, msgID) + "attachments/new-file.pdf"
	if !overlay.HasPath(newAttach) {
		t.Fatalf("expected new attachment at %s", newAttach)
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
