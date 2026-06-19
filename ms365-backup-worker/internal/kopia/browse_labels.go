package kopia

import (
	"context"
	"encoding/json"
	"io"
	"regexp"
	"strings"
	"time"
	"unicode/utf8"

	kopiafs "github.com/kopia/kopia/fs"
	"github.com/kopia/kopia/repo"
	"github.com/kopia/kopia/snapshot"
)

const browseMetaReadLimit = 64 * 1024

var (
	reMailSubject      = regexp.MustCompile(`"subject"\s*:\s*"((?:\\.|[^"\\])*)"`)
	reReceivedTime     = regexp.MustCompile(`"receivedDateTime"\s*:\s*"([^"]+)"`)
	reSentTime         = regexp.MustCompile(`"sentDateTime"\s*:\s*"([^"]+)"`)
	reIsDraft          = regexp.MustCompile(`"isDraft"\s*:\s*(true|false)`)
	reFromName         = regexp.MustCompile(`"from"\s*:\s*\{[^{}]*"name"\s*:\s*"((?:\\.|[^"\\])*)"`)
	reFromAddress      = regexp.MustCompile(`"from"\s*:\s*\{[^{}]*"address"\s*:\s*"((?:\\.|[^"\\])*)"`)
	reCalendarSubject  = regexp.MustCompile(`"subject"\s*:\s*"((?:\\.|[^"\\])*)"`)
	reCalendarStart    = regexp.MustCompile(`"start"\s*:\s*\{[^{}]*"(?:dateTime|date)"\s*:\s*"([^"]+)"`)
	reCalendarIsAllDay = regexp.MustCompile(`"isAllDay"\s*:\s*(true|false)`)
	reCalendarType     = regexp.MustCompile(`"type"\s*:\s*"([^"]+)"`)
	reCalendarCancelled = regexp.MustCompile(`"isCancelled"\s*:\s*(true|false)`)
)

func shouldHideBrowseName(name string) bool {
	lower := strings.ToLower(strings.TrimSpace(name))
	if lower == "" || lower == "folders.json" || lower == "delta_state.json" {
		return true
	}
	if lower == "_folder.json" || lower == "_calendar.json" || strings.HasSuffix(lower, ".removed.json") {
		return true
	}
	return false
}

type browseLabelResult struct {
	Label    string
	Subtitle string
	SortKey  string
}

func browseLabel(
	ctx context.Context,
	rep repo.Repository,
	man *snapshot.Manifest,
	root kopiafs.Directory,
	childPath string,
	name string,
	entryType string,
) browseLabelResult {
	lower := strings.ToLower(name)
	if label := segmentLabel(lower); label != "" {
		return browseLabelResult{Label: label}
	}
	if entryType == "folder" && strings.Contains(childPath, "/mail/") {
		if folderLabel := folderDisplayName(ctx, root, childPath); folderLabel != "" {
			return browseLabelResult{Label: folderLabel}
		}
		return browseLabelResult{Label: "Folder"}
	}
	if entryType == "folder" && isCalendarItemFolder(childPath) {
		if calLabel := calendarFolderDisplayName(ctx, root, childPath); calLabel != "" {
			return browseLabelResult{Label: calLabel}
		}
		return browseLabelResult{Label: opaqueCalendarFolderFallback(name)}
	}
	if isGuidLike(name) {
		if isDriveContentBrowsePath(childPath) {
			if entryType == "folder" {
				return browseLabelResult{Label: opaqueDriveFolderFallback(name)}
			}
			return browseLabelResult{Label: name}
		}
		if entryType == "folder" && strings.Contains(childPath, "/mail/") {
			if folderLabel := folderDisplayName(ctx, root, childPath); folderLabel != "" {
				return browseLabelResult{Label: folderLabel}
			}
			return browseLabelResult{Label: "Folder"}
		}
		return browseLabelResult{}
	}
	if entryType == "file" && strings.HasSuffix(lower, ".json") {
		if strings.Contains(childPath, "/mail/") {
			return mailMessageLabels(ctx, root, childPath)
		}
		if isCalendarItemPath(childPath) {
			return calendarEventLabels(ctx, root, childPath)
		}
		if strings.Contains(childPath, "/contacts/") {
			return browseLabelResult{Label: "Contact"}
		}
		if strings.Contains(childPath, "/tasks/") {
			return browseLabelResult{Label: "Task"}
		}
		return browseLabelResult{Label: "Item"}
	}
	if entryType == "folder" {
		return browseLabelResult{Label: name}
	}
	return browseLabelResult{Label: name}
}

func segmentLabel(segment string) string {
	switch segment {
	case "users":
		return "Users"
	case "mail":
		return "Mail"
	case "calendars", "calendar":
		return "Calendar"
	case "contacts":
		return "Contacts"
	case "tasks":
		return "Tasks"
	case "drives":
		return "OneDrive & drives"
	case "sites":
		return "SharePoint"
	case "teams":
		return "Teams"
	case "groups":
		return "Groups"
	case "planner":
		return "Planner"
	case "onenote":
		return "OneNote"
	case "onedrive":
		return "OneDrive"
	case "content":
		return "Files"
	case "lists":
		return "Lists"
	case "messages":
		return "Messages"
	default:
		return ""
	}
}

func isGuidLike(value string) bool {
	if len(value) == 36 && strings.Count(value, "-") == 4 {
		return true
	}
	if len(value) == 32 {
		for _, c := range value {
			if (c < '0' || c > '9') && (c < 'a' || c > 'f') && (c < 'A' || c > 'F') {
				return false
			}
		}
		return true
	}
	return false
}

func mailMessageLabels(ctx context.Context, root kopiafs.Directory, filePath string) browseLabelResult {
	buf, err := readFilePrefix(ctx, root, filePath, browseMetaReadLimit)
	if err != nil || len(buf) == 0 {
		return browseLabelResult{Label: "Email message"}
	}

	meta := parseMailMetadata(buf)
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

type mailMetadata struct {
	Subject      string
	FromName     string
	FromAddress  string
	ReceivedAt   string
	SentAt       string
	IsDraft      bool
}

func parseMailMetadata(buf []byte) mailMetadata {
	var meta mailMetadata

	var parsed map[string]any
	if err := json.Unmarshal(buf, &parsed); err == nil {
		meta.Subject, _ = parsed["subject"].(string)
		if rd, _ := parsed["receivedDateTime"].(string); rd != "" {
			meta.ReceivedAt = rd
		}
		if sd, _ := parsed["sentDateTime"].(string); sd != "" {
			meta.SentAt = sd
		}
		if draft, ok := parsed["isDraft"].(bool); ok {
			meta.IsDraft = draft
		}
		if from, ok := parsed["from"].(map[string]any); ok {
			if ea, ok := from["emailAddress"].(map[string]any); ok {
				meta.FromName, _ = ea["name"].(string)
				meta.FromAddress, _ = ea["address"].(string)
			}
		}
		if meta.Subject != "" || meta.ReceivedAt != "" || meta.FromName != "" {
			return meta
		}
	}

	if m := reMailSubject.FindSubmatch(buf); len(m) > 1 {
		meta.Subject = unescapeJSONString(string(m[1]))
	}
	if m := reReceivedTime.FindSubmatch(buf); len(m) > 1 {
		meta.ReceivedAt = string(m[1])
	}
	if m := reSentTime.FindSubmatch(buf); len(m) > 1 {
		meta.SentAt = string(m[1])
	}
	if m := reIsDraft.FindSubmatch(buf); len(m) > 1 {
		meta.IsDraft = string(m[1]) == "true"
	}
	if m := reFromName.FindSubmatch(buf); len(m) > 1 {
		meta.FromName = unescapeJSONString(string(m[1]))
	}
	if m := reFromAddress.FindSubmatch(buf); len(m) > 1 {
		meta.FromAddress = unescapeJSONString(string(m[1]))
	}

	return meta
}

func folderDisplayName(ctx context.Context, root kopiafs.Directory, folderPath string) string {
	metaPath := strings.Trim(folderPath, "/") + "/_folder.json"
	buf, err := readFilePrefix(ctx, root, metaPath, browseMetaReadLimit)
	if err != nil || len(buf) == 0 {
		return ""
	}
	var meta map[string]any
	if err := json.Unmarshal(buf, &meta); err != nil {
		return ""
	}
	if dn, ok := meta["displayName"].(string); ok && strings.TrimSpace(dn) != "" {
		return dn
	}
	return ""
}

func isCalendarItemPath(path string) bool {
	lower := strings.ToLower(path)
	if !strings.Contains(lower, "/calendars/") && !strings.Contains(lower, "/calendar/") {
		return false
	}
	return strings.HasSuffix(lower, ".json") && !strings.HasSuffix(lower, "_calendar.json")
}

func isCalendarItemFolder(path string) bool {
	lower := strings.ToLower(strings.Trim(path, "/"))
	if !strings.Contains(lower, "/calendars/") && !strings.Contains(lower, "/calendar/") {
		return false
	}
	parts := strings.Split(lower, "/")
	for i, part := range parts {
		if part != "calendar" && part != "calendars" {
			continue
		}
		return i+1 < len(parts)
	}
	return false
}

func calendarFolderDisplayName(ctx context.Context, root kopiafs.Directory, folderPath string) string {
	metaPath := strings.Trim(folderPath, "/") + "/_calendar.json"
	buf, err := readFilePrefix(ctx, root, metaPath, browseMetaReadLimit)
	if err != nil || len(buf) == 0 {
		return ""
	}
	var meta map[string]any
	if err := json.Unmarshal(buf, &meta); err != nil {
		return ""
	}
	if name, ok := meta["name"].(string); ok && strings.TrimSpace(name) != "" {
		return name
	}
	if dn, ok := meta["displayName"].(string); ok && strings.TrimSpace(dn) != "" {
		return dn
	}
	return ""
}

func opaqueCalendarFolderFallback(name string) string {
	name = strings.TrimSpace(name)
	if name == "" {
		return "Calendar"
	}
	if len(name) > 16 {
		return "Calendar …" + name[len(name)-8:]
	}
	return "Calendar"
}

func isDriveContentBrowsePath(path string) bool {
	lower := strings.ToLower(path)
	return strings.Contains(lower, "/content") &&
		(strings.Contains(lower, "/drives/") || strings.Contains(lower, "/sites/") || strings.Contains(lower, "/onedrive/"))
}

func opaqueDriveFolderFallback(name string) string {
	name = strings.TrimSpace(name)
	if name == "" {
		return "Folder"
	}
	if len(name) > 20 {
		return "Folder …" + name[len(name)-8:]
	}
	return name
}

func calendarEventLabels(ctx context.Context, root kopiafs.Directory, filePath string) browseLabelResult {
	buf, err := readFilePrefix(ctx, root, filePath, browseMetaReadLimit)
	if err != nil || len(buf) == 0 {
		return browseLabelResult{Label: "Calendar event"}
	}

	meta := parseCalendarMetadata(buf)
	subject := strings.TrimSpace(meta.Subject)
	if subject == "" {
		subject = "(No title)"
	}
	if meta.IsCancelled {
		subject = "(Cancelled) " + subject
	} else if meta.EventType == "seriesMaster" {
		subject = "(Recurring) " + subject
	}

	when := formatMailDate(meta.StartAt)
	subtitle := when
	if meta.IsAllDay && when != "" {
		subtitle = "All day · " + when
	} else if meta.IsAllDay {
		subtitle = "All day"
	}

	return browseLabelResult{
		Label:    truncateLabel(subject, 120),
		Subtitle: subtitle,
		SortKey:  meta.StartAt,
	}
}

type calendarMetadata struct {
	Subject     string
	StartAt     string
	IsAllDay    bool
	IsCancelled bool
	EventType   string
}

func parseCalendarMetadata(buf []byte) calendarMetadata {
	var meta calendarMetadata

	var parsed map[string]any
	if err := json.Unmarshal(buf, &parsed); err == nil {
		meta.Subject, _ = parsed["subject"].(string)
		if start, ok := parsed["start"].(map[string]any); ok {
			if dt, _ := start["dateTime"].(string); dt != "" {
				meta.StartAt = dt
			} else if d, _ := start["date"].(string); d != "" {
				meta.StartAt = d
			}
		}
		if allDay, ok := parsed["isAllDay"].(bool); ok {
			meta.IsAllDay = allDay
		}
		if cancelled, ok := parsed["isCancelled"].(bool); ok {
			meta.IsCancelled = cancelled
		}
		if eventType, ok := parsed["type"].(string); ok {
			meta.EventType = eventType
		}
		if meta.Subject != "" || meta.StartAt != "" {
			return meta
		}
	}

	if m := reCalendarSubject.FindSubmatch(buf); len(m) > 1 {
		meta.Subject = unescapeJSONString(string(m[1]))
	}
	if m := reCalendarStart.FindSubmatch(buf); len(m) > 1 {
		meta.StartAt = string(m[1])
	}
	if m := reCalendarIsAllDay.FindSubmatch(buf); len(m) > 1 {
		meta.IsAllDay = string(m[1]) == "true"
	}
	if m := reCalendarCancelled.FindSubmatch(buf); len(m) > 1 {
		meta.IsCancelled = string(m[1]) == "true"
	}
	if m := reCalendarType.FindSubmatch(buf); len(m) > 1 {
		meta.EventType = string(m[1])
	}

	return meta
}

func readFilePrefix(ctx context.Context, root kopiafs.Directory, path string, limit int64) ([]byte, error) {
	entry, err := walkPath(ctx, root, path)
	if err != nil {
		return nil, err
	}
	file, ok := entry.(kopiafs.File)
	if !ok {
		return nil, io.EOF
	}
	reader, err := file.Open(ctx)
	if err != nil {
		return nil, err
	}
	defer reader.Close()

	if limit <= 0 {
		limit = browseMetaReadLimit
	}
	if size := file.Size(); size > 0 && size < limit {
		limit = size
	}

	buf := make([]byte, 0, limit)
	tmp := make([]byte, 4096)
	for int64(len(buf)) < limit {
		n, readErr := reader.Read(tmp)
		if n > 0 {
			remaining := limit - int64(len(buf))
			if int64(n) > remaining {
				n = int(remaining)
			}
			buf = append(buf, tmp[:n]...)
		}
		if readErr != nil {
			break
		}
	}
	return buf, nil
}

func formatMailDate(iso string) string {
	iso = strings.TrimSpace(iso)
	if iso == "" {
		return ""
	}
	t, err := time.Parse(time.RFC3339Nano, iso)
	if err != nil {
		t, err = time.Parse(time.RFC3339, iso)
		if err != nil {
			return ""
		}
	}
	local := t.Local()
	now := time.Now()
	if local.Year() == now.Year() && local.YearDay() == now.YearDay() {
		return local.Format("3:04 PM")
	}
	if local.After(now.AddDate(0, 0, -7)) {
		return local.Format("Mon 3:04 PM")
	}
	if local.Year() == now.Year() {
		return local.Format("Jan 2")
	}
	return local.Format("2006-01-02")
}

func truncateLabel(value string, max int) string {
	value = strings.TrimSpace(value)
	if max <= 0 || utf8.RuneCountInString(value) <= max {
		return value
	}
	runes := []rune(value)
	if len(runes) <= max {
		return value
	}
	return string(runes[:max-1]) + "…"
}

func unescapeJSONString(value string) string {
	if value == "" {
		return ""
	}
	var decoded string
	if err := json.Unmarshal([]byte(`"`+value+`"`), &decoded); err == nil {
		return decoded
	}
	return value
}
