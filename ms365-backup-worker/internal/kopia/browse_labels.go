package kopia

import (
	"context"
	"encoding/json"
	"fmt"
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
	reSPListFieldTitle  = regexp.MustCompile(`"(?:Title|FileLeafRef|LinkTitle|Name|LinkFilename|Description|Subject)"\s*:\s*"((?:\\.|[^"\\])*)"`)
)

func shouldHideBrowseName(name string) bool {
	lower := strings.ToLower(strings.TrimSpace(name))
	if lower == "" || lower == "folders.json" || lower == "delta_state.json" {
		return true
	}
	if lower == "lists.json" || lower == "drives.json" {
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
	if entryType == "folder" && strings.Contains(childPath, "/contacts/") {
		if label := contactFolderDisplayName(ctx, root, childPath); label != "" {
			return browseLabelResult{Label: label}
		}
		return browseLabelResult{Label: opaqueContactFolderFallback(name)}
	}
	if entryType == "folder" && strings.Contains(childPath, "/mail/") {
		if folderLabel := folderDisplayName(ctx, root, childPath); folderLabel != "" {
			return browseLabelResult{Label: folderLabel}
		}
		if msgLabel := mailAttachmentFolderLabels(ctx, root, childPath, name); msgLabel.Label != "" {
			return msgLabel
		}
		return browseLabelResult{Label: "Folder"}
	}
	if entryType == "folder" && isCalendarItemFolder(childPath) {
		if calLabel := calendarFolderDisplayName(ctx, root, childPath); calLabel != "" {
			return browseLabelResult{Label: calLabel}
		}
		return browseLabelResult{Label: opaqueCalendarFolderFallback(name)}
	}
	if entryType == "folder" && isSharePointListFolder(childPath) {
		if label := sharePointListFolderDisplayName(ctx, root, childPath, name); label != "" {
			return browseLabelResult{Label: label}
		}
		return browseLabelResult{Label: opaqueSharePointListFallback(name)}
	}
	if entryType == "folder" && isDriveContentBrowsePath(childPath) && strings.Contains(childPath, "/sites/") {
		if label := sharePointDriveFolderDisplayName(ctx, root, childPath); label != "" {
			return browseLabelResult{Label: label}
		}
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
			if msgLabel := mailAttachmentFolderLabels(ctx, root, childPath, name); msgLabel.Label != "" {
				return msgLabel
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
			return contactMessageLabels(ctx, root, childPath)
		}
		if strings.Contains(childPath, "/tasks/") {
			return browseLabelResult{Label: "Task"}
		}
		if isSharePointListItemPath(childPath) {
			return sharePointListItemLabels(ctx, root, childPath)
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

func contactMessageLabels(ctx context.Context, root kopiafs.Directory, filePath string) browseLabelResult {
	buf, err := readFilePrefix(ctx, root, filePath, browseMetaReadLimit)
	if err != nil || len(buf) == 0 {
		return browseLabelResult{Label: "Contact"}
	}

	meta := parseContactMetadata(buf)
	label := strings.TrimSpace(meta.DisplayName)
	if label == "" {
		label = strings.TrimSpace(strings.TrimSpace(meta.GivenName) + " " + strings.TrimSpace(meta.Surname))
	}
	if label == "" {
		label = strings.TrimSpace(meta.Email)
	}
	if label == "" {
		label = "Contact"
	}

	subtitle := strings.TrimSpace(meta.Email)
	if subtitle == "" {
		subtitle = strings.TrimSpace(strings.TrimSpace(meta.GivenName) + " " + strings.TrimSpace(meta.Surname))
	}

	return browseLabelResult{
		Label:    truncateLabel(label, 120),
		Subtitle: subtitle,
	}
}

type contactMetadata struct {
	DisplayName string
	GivenName   string
	Surname     string
	Email       string
}

func parseContactMetadata(buf []byte) contactMetadata {
	var meta contactMetadata

	var parsed map[string]any
	if err := json.Unmarshal(buf, &parsed); err == nil {
		meta.DisplayName, _ = parsed["displayName"].(string)
		meta.GivenName, _ = parsed["givenName"].(string)
		meta.Surname, _ = parsed["surname"].(string)
		if addrs, ok := parsed["emailAddresses"].([]any); ok {
			for _, a := range addrs {
				m, _ := a.(map[string]any)
				if m == nil {
					continue
				}
				if addr, _ := m["address"].(string); strings.TrimSpace(addr) != "" {
					meta.Email = addr
					break
				}
			}
		}
		if meta.DisplayName != "" || meta.GivenName != "" || meta.Email != "" {
			return meta
		}
	}

	return meta
}

func mailAttachmentFolderLabels(ctx context.Context, root kopiafs.Directory, folderPath, name string) browseLabelResult {
	if !isMailMessageAttachmentFolder(folderPath, name) {
		return browseLabelResult{}
	}
	msgJSONPath := strings.Trim(folderPath, "/") + ".json"
	labels := mailMessageLabels(ctx, root, msgJSONPath)
	if labels.Label == "" || labels.Label == "Email message" {
		return browseLabelResult{}
	}
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

func isMailMessageAttachmentFolder(folderPath, name string) bool {
	trimmed := strings.Trim(folderPath, "/")
	parts := strings.Split(trimmed, "/")
	for i, part := range parts {
		if part != "mail" {
			continue
		}
		if i+2 >= len(parts) {
			return false
		}
		return parts[i+2] == name && i+3 == len(parts)
	}
	return false
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
	for _, variant := range folderPathVariants(folderPath) {
		metaPath := strings.Trim(variant, "/") + "/_folder.json"
		buf, err := readFilePrefix(ctx, root, metaPath, browseMetaReadLimit)
		if err != nil || len(buf) == 0 {
			continue
		}
		var meta map[string]any
		if err := json.Unmarshal(buf, &meta); err != nil {
			continue
		}
		if dn, ok := meta["displayName"].(string); ok && strings.TrimSpace(dn) != "" {
			return dn
		}
	}
	return ""
}

func folderPathVariants(folderPath string) []string {
	trimmed := strings.Trim(folderPath, "/")
	if trimmed == "" {
		return []string{""}
	}
	parts := strings.Split(trimmed, "/")
	last := parts[len(parts)-1]
	safe := safeSnapshotID(last)
	if safe == last {
		return []string{trimmed}
	}
	safeParts := append(append([]string{}, parts[:len(parts)-1]...), safe)
	return []string{trimmed, strings.Join(safeParts, "/")}
}

func safeSnapshotID(id string) string {
	return strings.NewReplacer("/", "_", "\\", "_", ":", "_").Replace(id)
}

func contactFolderDisplayName(ctx context.Context, root kopiafs.Directory, folderPath string) string {
	return folderDisplayName(ctx, root, folderPath)
}

func opaqueContactFolderFallback(name string) string {
	name = strings.TrimSpace(name)
	if name == "" {
		return "Contacts"
	}
	if len(name) > 16 {
		return "Contacts …" + name[len(name)-8:]
	}
	return "Contacts"
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

func isSharePointListFolder(path string) bool {
	lower := strings.ToLower(strings.Trim(path, "/"))
	if !strings.Contains(lower, "/sites/") || !strings.Contains(lower, "/lists/") {
		return false
	}
	parts := strings.Split(lower, "/")
	for i, part := range parts {
		if part != "lists" || i+1 >= len(parts) {
			continue
		}
		segment := parts[i+1]
		return segment != "" && segment != "items"
	}
	return false
}

func isSharePointListItemPath(path string) bool {
	lower := strings.ToLower(path)
	return strings.Contains(lower, "/sites/") && strings.Contains(lower, "/lists/") &&
		strings.Contains(lower, "/items/") && strings.HasSuffix(lower, ".json") &&
		!strings.HasSuffix(lower, ".removed.json")
}

func sharePointListFolderDisplayName(ctx context.Context, root kopiafs.Directory, folderPath, name string) string {
	siteID, listID := sharePointSiteAndListIDs(folderPath, name)
	if siteID == "" || listID == "" {
		return ""
	}
	for _, catalogPath := range sharePointListsCatalogPaths(folderPath, siteID) {
		buf, err := readFilePrefix(ctx, root, catalogPath, browseMetaReadLimit)
		if err != nil || len(buf) == 0 {
			continue
		}
		if label := listDisplayNameFromCatalog(buf, listID); label != "" {
			return label
		}
	}
	return ""
}

func sharePointDriveFolderDisplayName(ctx context.Context, root kopiafs.Directory, folderPath string) string {
	driveID := sharePointDriveIDFromContentPath(folderPath)
	if driveID == "" {
		return ""
	}
	siteID := sharePointSiteIDFromPath(folderPath)
	if siteID == "" {
		return ""
	}
	for _, catalogPath := range sharePointDrivesCatalogPaths(folderPath, siteID) {
		buf, err := readFilePrefix(ctx, root, catalogPath, browseMetaReadLimit)
		if err != nil || len(buf) == 0 {
			continue
		}
		if label := driveDisplayNameFromCatalog(buf, driveID); label != "" {
			return label
		}
	}
	return ""
}

func sharePointListItemLabels(ctx context.Context, root kopiafs.Directory, filePath string) browseLabelResult {
	buf, err := readFilePrefix(ctx, root, filePath, browseMetaReadLimit)
	if err != nil || len(buf) == 0 {
		return browseLabelResult{Label: sharePointListItemFallbackLabel(filePath)}
	}
	title := parseSharePointListItemTitle(buf)
	subtitle := parseSharePointListItemSubtitle(buf)
	if title == "" {
		title = sharePointListItemFallbackLabel(filePath)
	}
	return browseLabelResult{Label: truncateLabel(title, 120), Subtitle: subtitle}
}

func sharePointListItemFallbackLabel(filePath string) string {
	name := strings.TrimSuffix(filePath, ".json")
	if idx := strings.LastIndex(name, "/"); idx >= 0 {
		name = name[idx+1:]
	}
	name = strings.TrimSpace(name)
	if name != "" {
		return "List item " + name
	}
	return "List item"
}

func parseSharePointListItemSubtitle(buf []byte) string {
	var parsed map[string]any
	if err := json.Unmarshal(buf, &parsed); err != nil {
		return ""
	}
	for _, key := range []string{"lastModifiedDateTime", "createdDateTime"} {
		if v := sharePointFieldString(parsed[key]); v != "" {
			return v
		}
	}
	return ""
}

func parseSharePointListItemTitle(buf []byte) string {
	var parsed map[string]any
	if err := json.Unmarshal(buf, &parsed); err == nil {
		if fields, ok := parsed["fields"].(map[string]any); ok {
			for _, key := range []string{"Title", "LinkTitle", "FileLeafRef", "Name", "LinkFilename", "Description", "Subject"} {
				if v := sharePointFieldString(fields[key]); v != "" {
					return v
				}
			}
			for k, v := range fields {
				if strings.HasPrefix(k, "@") || strings.HasPrefix(k, "_") {
					continue
				}
				if s := sharePointFieldString(v); s != "" {
					return s
				}
			}
		}
		for _, key := range []string{"Title", "name", "displayName"} {
			if v := sharePointFieldString(parsed[key]); v != "" {
				return v
			}
		}
	}
	if m := reSPListFieldTitle.FindSubmatch(buf); len(m) > 1 {
		return strings.TrimSpace(unescapeJSONString(string(m[1])))
	}
	return ""
}

func sharePointFieldString(v any) string {
	switch t := v.(type) {
	case string:
		return strings.TrimSpace(t)
	case float64:
		if t == float64(int64(t)) {
			return fmt.Sprintf("%d", int64(t))
		}
		return strings.TrimSpace(fmt.Sprintf("%v", t))
	case bool:
		if t {
			return "true"
		}
		return "false"
	case map[string]any:
		if s, ok := t["value"].(string); ok {
			return strings.TrimSpace(s)
		}
	}
	return ""
}

func listDisplayNameFromCatalog(data []byte, listID string) string {
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
		if id != listID && safeSnapshotID(id) != listID && safeSnapshotID(listID) != id {
			continue
		}
		if name, _ := item["displayName"].(string); strings.TrimSpace(name) != "" {
			return strings.TrimSpace(name)
		}
	}
	return ""
}

func driveDisplayNameFromCatalog(data []byte, driveID string) string {
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
		if id != driveID && safeSnapshotID(id) != driveID && safeSnapshotID(driveID) != id {
			continue
		}
		if name, _ := item["name"].(string); strings.TrimSpace(name) != "" {
			return strings.TrimSpace(name)
		}
	}
	return ""
}

func sharePointSiteIDFromPath(path string) string {
	parts := strings.Split(strings.Trim(path, "/"), "/")
	for i, part := range parts {
		if part == "sites" && i+1 < len(parts) {
			return parts[i+1]
		}
	}
	return ""
}

func sharePointSiteAndListIDs(folderPath, name string) (string, string) {
	parts := strings.Split(strings.Trim(folderPath, "/"), "/")
	for i, part := range parts {
		if part == "sites" && i+2 < len(parts) && parts[i+2] == "lists" {
			return parts[i+1], name
		}
	}
	return "", name
}

func sharePointDriveIDFromContentPath(path string) string {
	parts := strings.Split(strings.Trim(path, "/"), "/")
	for i, part := range parts {
		if part == "drives" && i+1 < len(parts) {
			return parts[i+1]
		}
	}
	return ""
}

func sharePointListsCatalogPaths(folderPath, siteID string) []string {
	prefix := sharePointSitePathPrefix(folderPath, siteID)
	if prefix == "" {
		return nil
	}
	return []string{prefix + "/lists/lists.json"}
}

func sharePointDrivesCatalogPaths(folderPath, siteID string) []string {
	prefix := sharePointSitePathPrefix(folderPath, siteID)
	if prefix == "" {
		return nil
	}
	return []string{prefix + "/drives.json"}
}

func sharePointSitePathPrefix(folderPath, siteID string) string {
	parts := strings.Split(strings.Trim(folderPath, "/"), "/")
	for i, part := range parts {
		if part == "sites" && i+1 < len(parts) {
			return strings.Join(parts[:i+2], "/")
		}
	}
	if siteID != "" {
		if idx := strings.Index(folderPath, "/sites/"); idx >= 0 {
			rest := folderPath[idx+len("/sites/"):]
			if slash := strings.Index(rest, "/"); slash >= 0 {
				return folderPath[:idx+len("/sites/")+slash]
			}
			return strings.Trim(folderPath, "/")
		}
	}
	return ""
}

func opaqueSharePointListFallback(name string) string {
	name = strings.TrimSpace(name)
	if name == "" {
		return "List"
	}
	if len(name) > 16 {
		return "List …" + name[len(name)-8:]
	}
	return "List"
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
