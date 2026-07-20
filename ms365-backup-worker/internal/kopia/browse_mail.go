package kopia

import (
	"context"
	"encoding/json"
	"strings"

	kopiafs "github.com/kopia/kopia/fs"
)

const mailBrowseIndexVersion = 1

type mailBrowseIndex struct {
	Version  int                            `json:"version"`
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

type mailBrowseResolver struct {
	index mailBrowseIndex
}

func (r *mailBrowseResolver) hasIndex() bool {
	return r != nil && r.index.Version == mailBrowseIndexVersion && r.index.Messages != nil
}

func loadMailBrowseResolver(ctx context.Context, root kopiafs.Directory, dirPath string) *mailBrowseResolver {
	dirPath = strings.Trim(dirPath, "/")
	if dirPath == "" || !strings.Contains(dirPath, "/mail/") {
		return nil
	}
	browsePath := dirPath + "/_browse.json"
	buf, err := readFilePrefix(ctx, root, browsePath, browseMetaReadLimit)
	if err != nil || len(buf) == 0 {
		return &mailBrowseResolver{}
	}
	var idx mailBrowseIndex
	if err := json.Unmarshal(buf, &idx); err != nil || idx.Version != mailBrowseIndexVersion || idx.Messages == nil {
		return &mailBrowseResolver{}
	}
	return &mailBrowseResolver{index: idx}
}

func needsFullMailLabel(childPath, entryType string) bool {
	if !strings.Contains(childPath, "/mail/") {
		return false
	}
	lowerName := strings.ToLower(browsePathBaseName(childPath))
	if segmentLabel(lowerName) != "" {
		return false
	}
	if entryType == "file" && strings.HasSuffix(strings.ToLower(childPath), ".json") {
		return true
	}
	if entryType == "folder" {
		return true
	}
	return false
}

func labelBrowseChild(
	ctx context.Context,
	root kopiafs.Directory,
	childPath, name, entryType string,
	useFastLabels bool,
	mailResolver *mailBrowseResolver,
) browseLabelResult {
	if mailResolver != nil && mailResolver.hasIndex() {
		if result, ok := mailResolver.labelChild(ctx, root, childPath, name, entryType); ok {
			return result
		}
	}
	if useFastLabels && !needsFullSharePointListLabel(childPath, entryType) && !needsFullMailLabel(childPath, entryType) {
		return fastBrowseLabel(childPath, name, entryType)
	}
	return browseLabel(ctx, nil, nil, root, childPath, name, entryType)
}

func (r *mailBrowseResolver) labelChild(ctx context.Context, root kopiafs.Directory, childPath, name, entryType string) (browseLabelResult, bool) {
	lowerName := strings.ToLower(name)
	if entryType == "folder" && lowerName == "attachments" && strings.Contains(childPath, "/mail/") {
		return browseLabelResult{Label: "Attachments"}, true
	}
	if label := segmentLabel(lowerName); label != "" {
		return browseLabelResult{Label: label}, true
	}
	if entryType == "file" && strings.HasSuffix(lowerName, ".json") && strings.Contains(childPath, "/mail/") {
		msgKey := strings.TrimSuffix(name, ".json")
		if entry, ok := r.lookupMessage(msgKey); ok {
			return formatMailMessageLabels(mailMetadataFromBrowseEntry(entry)), true
		}
		return browseLabelResult{Label: "Email message"}, true
	}
	if entryType == "folder" && strings.Contains(childPath, "/mail/") {
		if folderLabel := folderDisplayName(ctx, root, childPath); folderLabel != "" {
			return browseLabelResult{Label: folderLabel}, true
		}
		if msgLabel := mailAttachmentFolderLabelsWithResolver(ctx, root, childPath, name, r); msgLabel.Label != "" {
			return msgLabel, true
		}
		return browseLabelResult{Label: opaqueMailFolderFallback(name)}, true
	}
	return browseLabelResult{}, false
}

func (r *mailBrowseResolver) lookupMessage(key string) (mailBrowseIndexEntry, bool) {
	if entry, ok := r.index.Messages[key]; ok {
		return entry, true
	}
	safe := safeSnapshotID(key)
	if entry, ok := r.index.Messages[safe]; ok {
		return entry, true
	}
	for _, entry := range r.index.Messages {
		if entry.ID == key || safeSnapshotID(entry.ID) == key || entry.ID == safe {
			return entry, true
		}
	}
	return mailBrowseIndexEntry{}, false
}

func mailMetadataFromBrowseEntry(entry mailBrowseIndexEntry) mailMetadata {
	return mailMetadata{
		Subject:     entry.Subject,
		FromName:    entry.FromName,
		FromAddress: entry.FromAddress,
		ReceivedAt:  entry.ReceivedDateTime,
		SentAt:      entry.SentDateTime,
		IsDraft:     entry.IsDraft,
	}
}

func formatMailMessageLabels(meta mailMetadata) browseLabelResult {
	subject := strings.TrimSpace(meta.Subject)
	if subject == "" {
		subject = "(No subject)"
	}
	if meta.IsDraft {
		subject = "(Draft) " + subject
	}

	sender := strings.TrimSpace(meta.FromName)
	if sender == "" {
		sender = strings.TrimSpace(meta.FromAddress)
	}
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

	sortKey := meta.ReceivedAt
	if sortKey == "" {
		sortKey = meta.SentAt
	}

	return browseLabelResult{
		Label:    truncateLabel(subject, 120),
		Subtitle: subtitle,
		SortKey:  sortKey,
	}
}

func mailAttachmentFolderLabelsWithResolver(ctx context.Context, root kopiafs.Directory, folderPath, name string, resolver *mailBrowseResolver) browseLabelResult {
	if !isMailMessageAttachmentFolder(folderPath, name) {
		return browseLabelResult{}
	}
	if resolver != nil && resolver.hasIndex() {
		if entry, ok := resolver.lookupMessage(name); ok {
			labels := formatMailMessageLabels(mailMetadataFromBrowseEntry(entry))
			subtitle := "Attachments"
			if labels.Subtitle != "" {
				subtitle = labels.Subtitle + " · Attachments"
			}
			return browseLabelResult{
				Label:    labels.Label,
				Subtitle: subtitle,
				SortKey:  labels.SortKey,
			}
		}
	}
	return mailAttachmentFolderLabels(ctx, root, folderPath, name)
}

func mailFolderDisplayNameFromCatalog(ctx context.Context, root kopiafs.Directory, folderPath, name string) string {
	folderID := browsePathBaseName(folderPath)
	if folderID == "" {
		folderID = name
	}
	for _, catalogPath := range mailFoldersCatalogPaths(folderPath) {
		buf, err := readFilePrefix(ctx, root, catalogPath, browseMetaReadLimit)
		if err != nil || len(buf) == 0 {
			continue
		}
		if label := folderDisplayNameFromCatalog(buf, folderID); label != "" {
			return label
		}
	}
	return ""
}

func mailFoldersCatalogPaths(folderPath string) []string {
	parts := strings.Split(strings.Trim(folderPath, "/"), "/")
	for i, part := range parts {
		if part == "mail" && i >= 1 {
			return []string{strings.Join(parts[:i+1], "/") + "/folders.json"}
		}
	}
	return nil
}

func folderDisplayNameFromCatalog(data []byte, folderID string) string {
	if len(data) == 0 {
		return ""
	}
	var catalog map[string]any
	if err := json.Unmarshal(data, &catalog); err != nil {
		return ""
	}
	values, _ := catalog["value"].([]any)
	for _, v := range values {
		item, _ := v.(map[string]any)
		if item == nil {
			continue
		}
		id, _ := item["id"].(string)
		if id != folderID && safeSnapshotID(id) != folderID && safeSnapshotID(folderID) != id {
			continue
		}
		if name, _ := item["displayName"].(string); strings.TrimSpace(name) != "" {
			return strings.TrimSpace(name)
		}
	}
	return ""
}

func opaqueMailFolderFallback(name string) string {
	name = strings.TrimSpace(name)
	if name == "" || isGuidLike(name) || looksLikeOpaqueMailID(name) {
		return "Mail folder"
	}
	if len(name) > 16 {
		return "Mail folder …" + name[len(name)-8:]
	}
	return "Mail folder"
}

func looksLikeOpaqueMailID(name string) bool {
	if strings.HasPrefix(name, "AAMk") || strings.HasPrefix(name, "AQMk") {
		return len(name) > 20
	}
	return false
}

func browsePathBaseName(path string) string {
	path = strings.Trim(path, "/")
	if path == "" {
		return ""
	}
	if idx := strings.LastIndex(path, "/"); idx >= 0 {
		return path[idx+1:]
	}
	return path
}
