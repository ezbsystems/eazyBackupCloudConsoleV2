package archive

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"path"
	"strings"
	"sync"

	"github.com/eazybackup/ms365-backup-worker/internal/kopia"
)

// MetadataIndex holds prefetched sidecar JSON keyed by snapshot-relative path.
type MetadataIndex struct {
	mu   sync.RWMutex
	data map[string][]byte
}

func NewMetadataIndex() *MetadataIndex {
	return &MetadataIndex{data: map[string][]byte{}}
}

func (m *MetadataIndex) Get(snapshotPath string) []byte {
	m.mu.RLock()
	defer m.mu.RUnlock()
	return m.data[snapshotPath]
}

func (m *MetadataIndex) Put(snapshotPath string, data []byte) {
	m.mu.Lock()
	defer m.mu.Unlock()
	m.data[snapshotPath] = data
}

// PrefetchMetadata reads sidecar files needed to resolve human-readable zip paths.
func PrefetchMetadata(
	ctx context.Context,
	pool *kopia.Pool,
	storage kopia.StorageOptions,
	files []fileEntry,
) (*MetadataIndex, error) {
	idx := NewMetadataIndex()
	needed := map[string]string{} // path -> manifestID
	for _, f := range files {
		for _, metaPath := range inferMetadataPaths(f.Path) {
			if _, ok := needed[metaPath]; !ok {
				needed[metaPath] = f.ManifestID
			}
		}
	}
	if len(needed) == 0 {
		return idx, nil
	}

	const maxRead = 256 * 1024
	for metaPath, manifestID := range needed {
		if err := ctx.Err(); err != nil {
			return nil, err
		}
		reader, size, err := pool.ExtractReader(ctx, kopia.ExtractRequest{
			Storage:    storage,
			ManifestID: manifestID,
			Path:       metaPath,
			SourcePath: "/ms365",
		})
		if err != nil {
			continue
		}
		limit := size
		if limit <= 0 || limit > maxRead {
			limit = maxRead
		}
		data, err := io.ReadAll(io.LimitReader(reader, limit))
		reader.Close()
		if err != nil || len(data) == 0 {
			continue
		}
		idx.Put(metaPath, data)
	}
	return idx, nil
}

func inferMetadataPaths(snapshotPath string) []string {
	parts := stripTenantPrefix(strings.Split(strings.Trim(snapshotPath, "/"), "/"))
	if len(parts) == 0 {
		return nil
	}
	tenantPrefix := tenantPrefixFromPath(snapshotPath)
	var out []string
	add := func(rel string) {
		if rel != "" {
			out = append(out, joinSnapshotPath(tenantPrefix, rel))
		}
	}

	switch parts[0] {
	case "users":
		if len(parts) < 3 {
			return out
		}
		userID := parts[1]
		add("directory/users/" + safeSnapshotID(userID) + ".json")
		switch parts[2] {
		case "mail":
			if len(parts) >= 4 {
				add("users/" + userID + "/mail/" + parts[3] + "/_folder.json")
			}
		case "calendar", "calendars":
			if len(parts) >= 4 {
				add("users/" + userID + "/calendar/" + parts[3] + "/_calendar.json")
			}
		case "contacts":
			if len(parts) >= 4 {
				add("users/" + userID + "/contacts/" + parts[3] + "/_folder.json")
			}
		case "onenote":
			if len(parts) >= 4 {
				add("users/" + userID + "/onenote/" + parts[3] + "/_notebook.json")
			}
			if len(parts) >= 5 {
				add("users/" + userID + "/onenote/" + parts[3] + "/sections/" + parts[4] + "/_section.json")
			}
		}
	case "sites":
		if len(parts) >= 2 {
			siteID := parts[1]
			add("sites/" + siteID + "/_site.json")
			if len(parts) >= 4 && parts[2] == "lists" {
				add("sites/" + siteID + "/lists/lists.json")
			}
		}
	case "teams":
		if len(parts) >= 2 {
			add("teams/" + parts[1] + "/metadata.json")
		}
	case "groups":
		if len(parts) >= 2 {
			add("directory/groups/" + safeSnapshotID(parts[1]) + ".json")
		}
	case "planner":
		if len(parts) >= 2 {
			add("planner/" + parts[1] + "/_plan.json")
		}
		if len(parts) >= 4 && parts[2] == "buckets" {
			add("planner/" + parts[1] + "/buckets/" + parts[3] + "/_bucket.json")
		}
	case "onenote":
		if len(parts) >= 2 {
			add("onenote/" + parts[1] + "/_notebook.json")
		}
		if len(parts) >= 4 && parts[2] == "sections" {
			add("onenote/" + parts[1] + "/sections/" + parts[3] + "/_section.json")
		}
	}
	return out
}

func tenantPrefixFromPath(snapshotPath string) string {
	p := strings.Trim(snapshotPath, "/")
	if i := strings.Index(p, "/"); i > 0 {
		head := p[:i]
		rest := p[i+1:]
		if isGuidLike(head) || isKnownRoot(strings.Split(rest, "/")[0]) {
			return head
		}
	}
	return ""
}

func isKnownRoot(seg string) bool {
	switch seg {
	case "users", "sites", "drives", "teams", "groups", "planner", "onenote", "directory":
		return true
	default:
		return false
	}
}

func joinSnapshotPath(tenantPrefix, rel string) string {
	rel = strings.Trim(rel, "/")
	if tenantPrefix == "" {
		return rel
	}
	return tenantPrefix + "/" + rel
}

func safeSnapshotID(id string) string {
	return strings.NewReplacer("/", "_", "\\", "_", ":", "_").Replace(id)
}

// ZipPathResolver maps snapshot paths to human-readable zip entry paths.
type ZipPathResolver struct {
	meta       *MetadataIndex
	collisions map[string]map[string]int
}

func NewZipPathResolver(meta *MetadataIndex) *ZipPathResolver {
	if meta == nil {
		meta = NewMetadataIndex()
	}
	return &ZipPathResolver{
		meta:       meta,
		collisions: map[string]map[string]int{},
	}
}

func (r *ZipPathResolver) ZipPath(snapshotPath string) string {
	parts := stripTenantPrefix(strings.Split(strings.Trim(snapshotPath, "/"), "/"))
	if len(parts) == 0 {
		return "unknown"
	}
	zipParts := r.mapWorkloadRoot(parts)
	if len(zipParts) == 0 {
		return snapshotToZipPath(snapshotPath)
	}
	return path.Join(zipParts...)
}

func (r *ZipPathResolver) mapWorkloadRoot(parts []string) []string {
	if len(parts) == 0 {
		return nil
	}
	switch parts[0] {
	case "users":
		if len(parts) < 3 {
			return append([]string{"users"}, parts[1:]...)
		}
		userLabel := r.userLabel(parts[1])
		workload := parts[2]
		rest := parts[3:]
		switch workload {
		case "onedrive":
			if len(rest) > 0 && rest[0] == "content" {
				rest = rest[1:]
			}
			return append([]string{"onedrive", userLabel}, rest...)
		case "mail":
			return append([]string{"mail", userLabel}, r.relabelMailSegments(parts, rest)...)
		case "calendar", "calendars":
			return append([]string{"calendar", userLabel}, r.relabelCalendarSegments(parts, rest)...)
		case "contacts":
			return append([]string{"contacts", userLabel}, r.relabelContactSegments(parts, rest)...)
		case "tasks":
			return append([]string{"tasks", userLabel}, rest...)
		case "onenote":
			return append([]string{"onenote", userLabel}, r.relabelOneNoteUserSegments(parts, rest)...)
		default:
			return append([]string{workload, userLabel}, rest...)
		}
	case "sites":
		siteLabel := r.siteLabel(parts[1])
		rest := parts[2:]
		if len(rest) > 0 && rest[0] == "lists" && len(rest) >= 2 {
			listLabel := r.spListLabel(parts[1], rest[1])
			return append([]string{"sharepoint", siteLabel, "lists", listLabel}, rest[2:]...)
		}
		return append([]string{"sharepoint", siteLabel}, rest...)
	case "drives":
		rest := parts[1:]
		for i, seg := range rest {
			if seg == "content" {
				rest = rest[i+1:]
				break
			}
		}
		return append([]string{"onedrive"}, rest...)
	case "teams":
		teamLabel := r.teamLabel(parts[1])
		rest := parts[2:]
		if len(rest) >= 2 && rest[0] == "channels" {
			chLabel := r.channelLabel(parts[1], rest[1])
			return append([]string{"teams", teamLabel, chLabel}, rest[2:]...)
		}
		return append([]string{"teams", teamLabel}, rest...)
	case "groups":
		groupLabel := r.groupLabel(parts[1])
		return append([]string{"groups", groupLabel}, parts[2:]...)
	case "planner":
		planLabel := r.plannerPlanLabel(parts[1])
		rest := parts[2:]
		if len(rest) >= 2 && rest[0] == "buckets" {
			bucketLabel := r.plannerBucketLabel(parts[1], rest[1])
			return append([]string{"planner", planLabel, bucketLabel}, rest[2:]...)
		}
		return append([]string{"planner", planLabel}, rest...)
	case "onenote":
		nbLabel := r.onenoteNotebookLabel(parts[1])
		rest := parts[2:]
		if len(rest) >= 2 && rest[0] == "sections" {
			secLabel := r.onenoteSectionLabel(parts[1], rest[1])
			return append([]string{"onenote", nbLabel, secLabel}, rest[2:]...)
		}
		return append([]string{"onenote", nbLabel}, rest...)
	case "directory":
		return parts
	default:
		return parts
	}
}

func (r *ZipPathResolver) relabelMailSegments(snapshotParts, rest []string) []string {
	if len(rest) == 0 {
		return rest
	}
	folderLabel := r.mailFolderLabel(snapshotParts[1], rest[0])
	out := []string{folderLabel}
	if len(rest) > 1 {
		out = append(out, rest[1:]...)
	}
	return out
}

func (r *ZipPathResolver) relabelCalendarSegments(snapshotParts, rest []string) []string {
	if len(rest) == 0 {
		return rest
	}
	calLabel := r.calendarLabel(snapshotParts[1], rest[0])
	out := []string{calLabel}
	if len(rest) > 1 {
		out = append(out, rest[1:]...)
	}
	return out
}

func (r *ZipPathResolver) relabelContactSegments(snapshotParts, rest []string) []string {
	if len(rest) == 0 {
		return rest
	}
	folderLabel := r.contactFolderLabel(snapshotParts[1], rest[0])
	out := []string{folderLabel}
	if len(rest) > 1 {
		out = append(out, rest[1:]...)
	}
	return out
}

func (r *ZipPathResolver) relabelOneNoteUserSegments(snapshotParts, rest []string) []string {
	if len(rest) == 0 {
		return rest
	}
	nbLabel := r.onenoteNotebookLabel(rest[0])
	out := []string{nbLabel}
	if len(rest) > 1 && rest[1] == "sections" && len(rest) >= 3 {
		secLabel := r.onenoteSectionLabel(rest[0], rest[2])
		out = append(out, "sections", secLabel)
		if len(rest) > 3 {
			out = append(out, rest[3:]...)
		}
		return out
	}
	if len(rest) > 1 {
		out = append(out, rest[1:]...)
	}
	return out
}

func (r *ZipPathResolver) uniqueSegment(parent, label, originalID string) string {
	label = sanitizeZipSegment(label)
	if label == "" {
		if isGuidLike(originalID) {
			return guidSuffix(originalID)
		}
		return originalID
	}
	if parent == "" {
		parent = "_root"
	}
	if r.collisions[parent] == nil {
		r.collisions[parent] = map[string]int{}
	}
	count := r.collisions[parent][strings.ToLower(label)]
	if count == 0 {
		r.collisions[parent][strings.ToLower(label)] = 1
		return label
	}
	count++
	r.collisions[parent][strings.ToLower(label)] = count
	suffix := fmt.Sprintf("_%d", count)
	if isGuidLike(originalID) {
		suffix = "_" + guidSuffix(originalID)
	}
	candidate := label + suffix
	if len(candidate) > maxZipSegmentLen {
		candidate = label[:maxInt(1, maxZipSegmentLen-len(suffix))] + suffix
	}
	return candidate
}

func maxInt(a, b int) int {
	if a > b {
		return a
	}
	return b
}

func jsonStringField(data []byte, keys ...string) string {
	if len(data) == 0 {
		return ""
	}
	var obj map[string]any
	if err := json.Unmarshal(data, &obj); err != nil {
		return ""
	}
	for _, key := range keys {
		if v, ok := obj[key].(string); ok && strings.TrimSpace(v) != "" {
			return strings.TrimSpace(v)
		}
	}
	return ""
}

func (r *ZipPathResolver) userLabel(userID string) string {
	rel := "directory/users/" + safeSnapshotID(userID) + ".json"
	if label := jsonStringField(r.metaBySuffix(rel), "userPrincipalName", "mail", "displayName"); label != "" {
		return sanitizeZipSegment(label)
	}
	if isGuidLike(userID) {
		return guidSuffix(userID)
	}
	return sanitizeZipSegment(userID)
}

func (r *ZipPathResolver) mailFolderLabel(userID, folderID string) string {
	for _, suffix := range []string{
		"users/" + userID + "/mail/" + folderID + "/_folder.json",
		"users/" + userID + "/mail/" + safeSnapshotID(folderID) + "/_folder.json",
	} {
		if label := jsonStringField(r.metaBySuffix(suffix), "displayName"); label != "" {
			return r.uniqueSegment("mail/"+userID, label, folderID)
		}
	}
	return "Folder"
}

func (r *ZipPathResolver) calendarLabel(userID, calID string) string {
	for _, suffix := range []string{
		"users/" + userID + "/calendar/" + calID + "/_calendar.json",
		"users/" + userID + "/calendar/" + safeSnapshotID(calID) + "/_calendar.json",
	} {
		if label := jsonStringField(r.metaBySuffix(suffix), "name", "displayName"); label != "" {
			return r.uniqueSegment("calendar/"+userID, label, calID)
		}
	}
	return "Calendar"
}

func (r *ZipPathResolver) contactFolderLabel(userID, folderID string) string {
	for _, suffix := range []string{
		"users/" + userID + "/contacts/" + folderID + "/_folder.json",
		"users/" + userID + "/contacts/" + safeSnapshotID(folderID) + "/_folder.json",
	} {
		if label := jsonStringField(r.metaBySuffix(suffix), "displayName"); label != "" {
			return r.uniqueSegment("contacts/"+userID, label, folderID)
		}
	}
	return "Contacts"
}

func (r *ZipPathResolver) groupLabel(groupID string) string {
	rel := "directory/groups/" + safeSnapshotID(groupID) + ".json"
	if label := jsonStringField(r.metaBySuffix(rel), "displayName"); label != "" {
		return sanitizeZipSegment(label)
	}
	if isGuidLike(groupID) {
		return guidSuffix(groupID)
	}
	return sanitizeZipSegment(groupID)
}

func (r *ZipPathResolver) siteLabel(siteID string) string {
	for _, suffix := range []string{
		"sites/" + siteID + "/_site.json",
		"sites/" + safeSnapshotID(siteID) + "/_site.json",
	} {
		if label := jsonStringField(r.metaBySuffix(suffix), "displayName"); label != "" {
			return sanitizeZipSegment(label)
		}
	}
	return sanitizeZipSegment(siteID)
}

func (r *ZipPathResolver) spListLabel(siteID, listID string) string {
	for _, suffix := range []string{
		"sites/" + siteID + "/lists/lists.json",
		"sites/" + safeSnapshotID(siteID) + "/lists/lists.json",
	} {
		if label := listDisplayNameFromCatalog(r.metaBySuffix(suffix), listID); label != "" {
			return sanitizeZipSegment(label)
		}
	}
	if isGuidLike(listID) {
		return guidSuffix(listID)
	}
	return sanitizeZipSegment(listID)
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
		if id != listID && safeSnapshotID(id) != listID {
			continue
		}
		if name, _ := item["displayName"].(string); name != "" {
			return name
		}
	}
	return ""
}

type teamMetadata struct {
	DisplayName string
	Channels    map[string]string
}

func (r *ZipPathResolver) teamMeta(teamID string) teamMetadata {
	out := teamMetadata{Channels: map[string]string{}}
	for _, suffix := range []string{
		"teams/" + teamID + "/metadata.json",
		"teams/" + safeSnapshotID(teamID) + "/metadata.json",
	} {
		data := r.metaBySuffix(suffix)
		if len(data) == 0 {
			continue
		}
		var obj map[string]any
		if err := json.Unmarshal(data, &obj); err != nil {
			continue
		}
		out.DisplayName = stringFromJSON(obj["displayName"])
		if chans, ok := obj["channels"].([]any); ok {
			for _, c := range chans {
				m, _ := c.(map[string]any)
				if m == nil {
					continue
				}
				id := stringFromJSON(m["id"])
				name := stringFromJSON(m["displayName"])
				if id != "" && name != "" {
					out.Channels[id] = name
					out.Channels[safeSnapshotID(id)] = name
				}
			}
		}
		break
	}
	return out
}

func stringFromJSON(v any) string {
	s, _ := v.(string)
	return strings.TrimSpace(s)
}

func (r *ZipPathResolver) teamLabel(teamID string) string {
	meta := r.teamMeta(teamID)
	if meta.DisplayName != "" {
		return sanitizeZipSegment(meta.DisplayName)
	}
	return "Team"
}

func (r *ZipPathResolver) channelLabel(teamID, channelID string) string {
	meta := r.teamMeta(teamID)
	if name := meta.Channels[channelID]; name != "" {
		return sanitizeZipSegment(name)
	}
	if name := meta.Channels[safeSnapshotID(channelID)]; name != "" {
		return sanitizeZipSegment(name)
	}
	return "Channel"
}

func (r *ZipPathResolver) plannerPlanLabel(planID string) string {
	for _, suffix := range []string{
		"planner/" + planID + "/_plan.json",
		"planner/" + safeSnapshotID(planID) + "/_plan.json",
	} {
		if label := jsonStringField(r.metaBySuffix(suffix), "title"); label != "" {
			return sanitizeZipSegment(label)
		}
	}
	if isGuidLike(planID) {
		return guidSuffix(planID)
	}
	return sanitizeZipSegment(planID)
}

func (r *ZipPathResolver) plannerBucketLabel(planID, bucketID string) string {
	for _, suffix := range []string{
		"planner/" + planID + "/buckets/" + bucketID + "/_bucket.json",
		"planner/" + safeSnapshotID(planID) + "/buckets/" + safeSnapshotID(bucketID) + "/_bucket.json",
	} {
		if label := jsonStringField(r.metaBySuffix(suffix), "name"); label != "" {
			return sanitizeZipSegment(label)
		}
	}
	if isGuidLike(bucketID) {
		return guidSuffix(bucketID)
	}
	return sanitizeZipSegment(bucketID)
}

func (r *ZipPathResolver) onenoteNotebookLabel(notebookID string) string {
	for _, suffix := range []string{
		"onenote/" + notebookID + "/_notebook.json",
		"onenote/" + safeSnapshotID(notebookID) + "/_notebook.json",
	} {
		if label := jsonStringField(r.metaBySuffix(suffix), "displayName"); label != "" {
			return sanitizeZipSegment(label)
		}
	}
	if isGuidLike(notebookID) {
		return guidSuffix(notebookID)
	}
	return sanitizeZipSegment(notebookID)
}

func (r *ZipPathResolver) onenoteSectionLabel(notebookID, sectionID string) string {
	for _, suffix := range []string{
		"onenote/" + notebookID + "/sections/" + sectionID + "/_section.json",
		"onenote/" + safeSnapshotID(notebookID) + "/sections/" + safeSnapshotID(sectionID) + "/_section.json",
	} {
		if label := jsonStringField(r.metaBySuffix(suffix), "displayName"); label != "" {
			return sanitizeZipSegment(label)
		}
	}
	if isGuidLike(sectionID) {
		return guidSuffix(sectionID)
	}
	return sanitizeZipSegment(sectionID)
}

func (r *ZipPathResolver) metaBySuffix(suffix string) []byte {
	if data := r.meta.Get(suffix); len(data) > 0 {
		return data
	}
	suffix = strings.TrimPrefix(suffix, "/")
	r.meta.mu.RLock()
	defer r.meta.mu.RUnlock()
	for k, v := range r.meta.data {
		if strings.HasSuffix(k, suffix) {
			return v
		}
	}
	return nil
}

// replaceZipBaseName swaps the final path segment while preserving directories.
func replaceZipBaseName(zipPath, newBase string) string {
	dir := path.Dir(zipPath)
	if dir == "." || dir == "/" {
		return newBase
	}
	return path.Join(dir, newBase)
}
