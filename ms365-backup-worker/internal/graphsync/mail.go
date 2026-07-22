package graphsync

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"
	"sync"
	"sync/atomic"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
	"golang.org/x/sync/errgroup"
)

const mailBrowseIndexVersion = 1

type mailBrowseIndex struct {
	Version  int                             `json:"version"`
	Messages map[string]mailBrowseIndexEntry `json:"messages"`
}

type mailBrowseIndexEntry struct {
	ID               string `json:"id,omitempty"`
	Subject          string `json:"subject,omitempty"`
	FromName         string `json:"fromName,omitempty"`
	FromAddress      string `json:"fromAddress,omitempty"`
	ReceivedDateTime string `json:"receivedDateTime,omitempty"`
	SentDateTime     string `json:"sentDateTime,omitempty"`
	IsDraft          bool   `json:"isDraft,omitempty"`
	HasAttachments   bool   `json:"hasAttachments,omitempty"`
}

type MailSyncOptions struct {
	AzureTenantID    string
	UserID           string
	Parallel         int
	FolderParallel   int
	DeltaStates      map[string]string
	Staging          *graphfs.OverlayBuilder
	UseBatchFallback bool
	ShardKey         string
	OnProgress       func(itemsDone, itemsTotal int, bytesEstimate int64)
	Log              RunLogger
}

type MailSyncResult struct {
	Stats       MailStats
	DeltaStates map[string]string
	FileCount   int
	ItemsDone   int
	BytesTotal  int64
}

type MailStats struct {
	Folders       int
	Messages      int
	Created       int
	Updated       int
	Removed       int
	FoldersDelta  int
	FoldersFull   int
	BatchFallback int
	Graph429Hits  int64
}

var mailFolderPageSizes = []string{"100", "50", "25"}

func paginateMailFolders(ctx context.Context, client *graph.Client, opts MailSyncOptions) ([]map[string]any, error) {
	var last []map[string]any
	for i, top := range mailFolderPageSizes {
		outcome := &graph.PaginationOutcome{}
		monitor := graph.NewPaginationMonitor("mail:folders", graph.DuplicatePageDetectOnly, nil)
		monitor.SoftStopRepeatedNextLink = true
		folders, err := client.PaginateOpts(ctx, fmt.Sprintf("/users/%s/mailFolders", opts.UserID), map[string]string{"$top": top}, &graph.PaginateOptions{
			Monitor:     monitor,
			Outcome:     outcome,
			TrackDupIDs: true,
		})
		if err != nil {
			return nil, err
		}
		if outcome.CompletedNaturally {
			return folders, nil
		}
		last = folders
		if opts.Log != nil {
			opts.Log("warning", fmt.Sprintf("Mail folder pagination wedged at page size %s; %s", top, map[bool]string{true: "keeping unique folders returned", false: "retrying smaller page size"}[i == len(mailFolderPageSizes)-1]))
		}
	}
	return last, nil
}

func SyncMail(ctx context.Context, client *graph.Client, opts MailSyncOptions) (*MailSyncResult, error) {
	if opts.Parallel <= 0 {
		opts.Parallel = 16
	}
	if opts.FolderParallel <= 0 {
		opts.FolderParallel = minInt(opts.Parallel, 4)
	}
	if opts.DeltaStates == nil {
		opts.DeltaStates = map[string]string{}
	}
	if opts.Staging == nil {
		return nil, fmt.Errorf("mail sync requires overlay builder")
	}

	folders, err := paginateMailFolders(ctx, client, opts)
	if err != nil {
		return nil, err
	}

	foldersCatalog, _ := json.Marshal(map[string]any{
		"fetched_at": time.Now().UTC().Format(time.RFC3339),
		"value":      folders,
	})
	opts.Staging.PutJSON(fmt.Sprintf("%s/users/%s/mail/folders.json", opts.AzureTenantID, opts.UserID), foldersCatalog, time.Now().UTC())

	incremental := len(opts.DeltaStates) > 0
	if opts.Log != nil {
		opts.Log("info", fmt.Sprintf("Starting mail backup folders=%d incremental=%v", len(folders), incremental))
	}

	stats := MailStats{Folders: len(folders)}
	newDelta := map[string]string{}
	var mu sync.Mutex
	var bytesTotal int64
	var messagesEnumerated atomic.Int32
	emitProgress := func() {
		if opts.OnProgress == nil {
			return
		}
		mu.Lock()
		done := stats.Messages
		bytes := bytesTotal
		mu.Unlock()
		total := int(messagesEnumerated.Load())
		if total < done {
			total = done
		}
		opts.OnProgress(done, total, bytes)
	}
	emitProgress()

	g, gctx := errgroup.WithContext(ctx)
	g.SetLimit(opts.FolderParallel)

	for _, folder := range folders {
		folder := folder
		g.Go(func() error {
			folderID, _ := folder["id"].(string)
			if folderID == "" {
				return nil
			}
			folderName, _ := folder["displayName"].(string)
			folderMeta, _ := json.Marshal(folder)
			metaPath := fmt.Sprintf("%s/users/%s/mail/%s/_folder.json", opts.AzureTenantID, opts.UserID, safeID(folderID))
			opts.Staging.PutJSON(metaPath, folderMeta, graphfsModTime(folder["lastModifiedDateTime"]))

			deltaKey := DeltaKeyForShard(opts.ShardKey)
			if opts.ShardKey != "" && stringsHasPrefix(opts.ShardKey, "mail:") {
				expectedFolder := strings.TrimPrefix(opts.ShardKey, "mail:")
				if folderID != expectedFolder {
					return nil
				}
			}

			deltaPath := fmt.Sprintf("/users/%s/mailFolders/%s/messages/delta", opts.UserID, folderID)
			priorDelta := opts.DeltaStates[folderID]
			if priorDelta == "" && deltaKey != "root" {
				priorDelta = opts.DeltaStates[deltaKey]
			}
			folderMonitor := graph.ForBackupPagination("mail:"+folderName, graphLog(opts.Log))
			items, deltaLink, err := paginateDeltaResilient(gctx, client, deltaPath, priorDelta, graph.MailMessageSelect, 100, nil, &graph.DeltaPaginateOptions{Monitor: folderMonitor})
			if err != nil {
				return fmt.Errorf("folder %s: %w", folderName, err)
			}
			messagesEnumerated.Add(int32(len(items)))

			browsePath := mailBrowseIndexPath(opts.AzureTenantID, opts.UserID, folderID)
			browseIndex := loadMailBrowseIndex(opts.Staging, browsePath)
			updateBrowseIndex := func(item map[string]any) {
				msgID, _ := item["id"].(string)
				if msgID == "" {
					return
				}
				if browseIndex.Messages == nil {
					browseIndex.Messages = map[string]mailBrowseIndexEntry{}
				}
				browseIndex.Messages[safeID(msgID)] = mailBrowseEntryFromGraph(item)
			}
			removeBrowseIndex := func(msgID string) {
				if msgID == "" || browseIndex.Messages == nil {
					return
				}
				delete(browseIndex.Messages, safeID(msgID))
			}
			writeBrowseIndex := func() {
				if browseIndex.Messages == nil {
					browseIndex.Messages = map[string]mailBrowseIndexEntry{}
				}
				browseIndex.Version = mailBrowseIndexVersion
				body, _ := json.Marshal(browseIndex)
				opts.Staging.PutJSON(browsePath, body, time.Now().UTC())
			}

			mu.Lock()
			if deltaLink != "" {
				newDelta[folderID] = deltaLink
				stats.FoldersDelta++
			} else {
				stats.FoldersFull++
			}
			mu.Unlock()

			var needBatch []string
			for _, item := range items {
				if removed, _ := item["@removed"].(map[string]any); removed != nil {
					msgID, _ := item["id"].(string)
					if msgID == "" {
						continue
					}
					tombPath := fmt.Sprintf("%s/users/%s/mail/%s/%s.removed.json", opts.AzureTenantID, opts.UserID, safeID(folderID), safeID(msgID))
					body, _ := json.Marshal(item)
					opts.Staging.PutJSON(tombPath, body, graphfsModTime(item["lastModifiedDateTime"]))
					livePath := fmt.Sprintf("%s/users/%s/mail/%s/%s.json", opts.AzureTenantID, opts.UserID, safeID(folderID), safeID(msgID))
					opts.Staging.Remove(livePath)
					removeBrowseIndex(msgID)
					mu.Lock()
					stats.Removed++
					mu.Unlock()
					continue
				}
				msgID, _ := item["id"].(string)
				if msgID == "" {
					continue
				}
				msgPath := fmt.Sprintf("%s/users/%s/mail/%s/%s.json", opts.AzureTenantID, opts.UserID, safeID(folderID), safeID(msgID))
				if messageBodyComplete(item) {
					body, _ := json.Marshal(item)
					opts.Staging.PutJSON(msgPath, body, graphfsModTime(item["receivedDateTime"]))
					mu.Lock()
					stats.Messages++
					stats.Updated++
					bytesTotal += int64(len(body))
					mu.Unlock()
					emitProgress()
					if err := syncMailAttachments(gctx, client, opts, folderID, msgID, item, &mu, &stats, &bytesTotal, emitProgress); err != nil {
						return err
					}
					updateBrowseIndex(item)
					continue
				}
				needBatch = append(needBatch, msgID)
			}

			writeMessage := func(msgID string, body []byte, item map[string]any) error {
				msgPath := fmt.Sprintf("%s/users/%s/mail/%s/%s.json", opts.AzureTenantID, opts.UserID, safeID(folderID), safeID(msgID))
				opts.Staging.PutJSON(msgPath, body, graphfsModTime(item["receivedDateTime"]))
				mu.Lock()
				stats.Messages++
				stats.Updated++
				bytesTotal += int64(len(body))
				mu.Unlock()
				emitProgress()
				updateBrowseIndex(item)
				return syncMailAttachments(gctx, client, opts, folderID, msgID, item, &mu, &stats, &bytesTotal, emitProgress)
			}

			if len(needBatch) == 0 {
				writeBrowseIndex()
				return nil
			}

			if opts.UseBatchFallback {
				bodies, err := client.BatchGetMessages(gctx, opts.UserID, needBatch)
				if err != nil {
					return err
				}
				for _, msgID := range needBatch {
					body, ok := bodies[msgID]
					var item map[string]any
					if !ok {
						body, err = client.GetMessageJSON(gctx, opts.UserID, msgID)
						if err != nil {
							return err
						}
					}
					_ = json.Unmarshal(body, &item)
					if err := writeMessage(msgID, body, item); err != nil {
						return err
					}
					mu.Lock()
					stats.BatchFallback++
					mu.Unlock()
				}
				writeBrowseIndex()
				return nil
			}

			itemG, itemCtx := errgroup.WithContext(gctx)
			itemG.SetLimit(opts.Parallel)
			for _, msgID := range needBatch {
				msgID := msgID
				itemG.Go(func() error {
					body, err := client.GetMessageJSON(itemCtx, opts.UserID, msgID)
					if err != nil {
						return err
					}
					var item map[string]any
					_ = json.Unmarshal(body, &item)
					return writeMessage(msgID, body, item)
				})
			}
			if err := itemG.Wait(); err != nil {
				return err
			}
			writeBrowseIndex()
			return nil
		})
	}

	if err := g.Wait(); err != nil {
		return nil, err
	}
	emitProgress()

	stats.Graph429Hits = client.ThrottleHits()
	return &MailSyncResult{
		Stats:       stats,
		DeltaStates: newDelta,
		FileCount:   opts.Staging.EntryCount(),
		ItemsDone:   stats.Messages,
		BytesTotal:  bytesTotal,
	}, nil
}

func syncMailAttachments(ctx context.Context, client *graph.Client, opts MailSyncOptions, folderID, msgID string, item map[string]any, mu *sync.Mutex, stats *MailStats, bytesTotal *int64, onStored func()) error {
	hasAttachments, _ := item["hasAttachments"].(bool)
	if !hasAttachments {
		return nil
	}
	attachments, err := client.Paginate(ctx, fmt.Sprintf("/users/%s/messages/%s/attachments", opts.UserID, msgID), map[string]string{"$top": "50"})
	if err != nil {
		return err
	}
	for _, att := range attachments {
		attID, _ := att["id"].(string)
		if attID == "" {
			continue
		}
		name, _ := att["name"].(string)
		if name == "" {
			name = attID
		}
		size := int64(0)
		if v, ok := att["size"].(float64); ok {
			size = int64(v)
		}
		path := fmt.Sprintf("%s/users/%s/mail/%s/%s/attachments/%s", opts.AzureTenantID, opts.UserID, safeID(folderID), safeID(msgID), safePathSegment(name))
		contentPath := fmt.Sprintf("/users/%s/messages/%s/attachments/%s/$value", opts.UserID, msgID, attID)
		gf := graphfs.NewGraphFile(client, safePathSegment(name), contentPath, size, graphfsModTime(att["lastModifiedDateTime"]))
		opts.Staging.Put(path, gf)
		mu.Lock()
		stats.Messages++
		*bytesTotal += size
		mu.Unlock()
		if onStored != nil {
			onStored()
		}
	}
	return nil
}

func stringsHasPrefix(s, prefix string) bool {
	return len(s) >= len(prefix) && s[:len(prefix)] == prefix
}

func messageBodyComplete(item map[string]any) bool {
	if _, ok := item["body"]; ok {
		return true
	}
	if _, ok := item["bodyPreview"]; ok {
		return true
	}
	return false
}

func minInt(a, b int) int {
	if a < b {
		return a
	}
	return b
}

func mailBrowseIndexPath(tenantID, userID, folderID string) string {
	return fmt.Sprintf("%s/users/%s/mail/%s/_browse.json", tenantID, userID, safeID(folderID))
}

func loadMailBrowseIndex(staging *graphfs.OverlayBuilder, path string) mailBrowseIndex {
	var idx mailBrowseIndex
	if staging.ReadJSON(path, &idx) && idx.Version == mailBrowseIndexVersion && idx.Messages != nil {
		return idx
	}
	return mailBrowseIndex{
		Version:  mailBrowseIndexVersion,
		Messages: map[string]mailBrowseIndexEntry{},
	}
}

func mailBrowseEntryFromGraph(item map[string]any) mailBrowseIndexEntry {
	msgID, _ := item["id"].(string)
	entry := mailBrowseIndexEntry{ID: msgID}
	entry.Subject, _ = item["subject"].(string)
	entry.ReceivedDateTime, _ = item["receivedDateTime"].(string)
	entry.SentDateTime, _ = item["sentDateTime"].(string)
	if draft, ok := item["isDraft"].(bool); ok {
		entry.IsDraft = draft
	}
	if hasAtt, ok := item["hasAttachments"].(bool); ok {
		entry.HasAttachments = hasAtt
	}
	if from, ok := item["from"].(map[string]any); ok {
		if ea, ok := from["emailAddress"].(map[string]any); ok {
			entry.FromName, _ = ea["name"].(string)
			entry.FromAddress, _ = ea["address"].(string)
		}
	}
	return entry
}
