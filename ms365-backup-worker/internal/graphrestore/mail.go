package graphrestore

import (
	"bytes"
	"context"
	"fmt"
	"io"
	"net/url"
	"path"
	"strings"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
)

func restoreMailMessage(ctx context.Context, client *graph.Client, userID string, data []byte, policy string) (bool, error) {
	msg, err := parseJSON(data)
	if err != nil {
		return false, err
	}
	folderID := mapString(msg, "parentFolderId")
	internetID, _ := msg["internetMessageId"].(string)
	if policy == "skip_duplicates" && internetID != "" {
		escaped := strings.ReplaceAll(internetID, "'", "''")
		filter := fmt.Sprintf("internetMessageId eq '%s'", escaped)
		if folderID == "" {
			folderID = "inbox"
		}
		existing, err := client.Paginate(ctx, fmt.Sprintf("/users/%s/mailFolders/%s/messages", userID, folderID), map[string]string{
			"$filter": filter,
			"$top":    "1",
			"$select": "id",
		})
		if err == nil && len(existing) > 0 {
			return true, nil
		}
	}

	if folderID == "" {
		folderID = "inbox"
	}
	delete(msg, "@odata.context")
	delete(msg, "id")
	delete(msg, "@odata.etag")
	delete(msg, "changeKey")
	_, err = client.PostJSON(ctx, fmt.Sprintf("/users/%s/mailFolders/%s/messages", userID, folderID), msg)
	return false, err
}

func restoreCalendarEvent(ctx context.Context, client *graph.Client, userID, snapshotPath string, data []byte, policy string) (bool, error) {
	event, err := parseJSON(data)
	if err != nil {
		return false, err
	}
	// Calendar events captured from Graph do not carry their owning calendar id in
	// the body — it is only present in the snapshot path. When the id is absent we
	// restore into the user's default calendar via /users/{id}/events.
	calendarID := mapString(event, "calendarId")
	if calendarID == "" {
		calendarID = mapString(event, "parentCalendarId")
	}
	if calendarID == "" {
		calendarID = calendarIDFromSnapshotPath(snapshotPath)
	}

	iCalUID := mapString(event, "iCalUId")
	if policy == "skip_duplicates" && iCalUID != "" {
		listPath := fmt.Sprintf("/users/%s/events", userID)
		if calendarID != "" {
			listPath = fmt.Sprintf("/users/%s/calendars/%s/events", userID, calendarID)
		}
		existing, err := client.FindEventsByICalUID(ctx, listPath, iCalUID)
		if err == nil && len(existing) > 0 {
			return true, nil
		}
	}

	sanitizeEventForCreate(event)

	postPath := fmt.Sprintf("/users/%s/events", userID)
	if calendarID != "" {
		postPath = fmt.Sprintf("/users/%s/calendars/%s/events", userID, calendarID)
	}
	_, err = client.PostJSON(ctx, postPath, event)
	if err != nil {
		if policy == "skip_duplicates" && isDuplicateGraphError(err) {
			return true, nil
		}
		return false, err
	}
	return false, nil
}

func restoreContact(ctx context.Context, client *graph.Client, userID string, data []byte, policy string) (bool, error) {
	contact, err := parseJSON(data)
	if err != nil {
		return false, err
	}
	email := primaryEmail(contact)
	if policy == "skip_duplicates" && email != "" {
		escaped := strings.ReplaceAll(email, "'", "''")
		filter := fmt.Sprintf("emailAddresses/any(a:a/address eq '%s')", escaped)
		existing, err := client.Paginate(ctx, fmt.Sprintf("/users/%s/contacts", userID), map[string]string{
			"$filter": filter,
			"$top":    "1",
			"$select": "id",
		})
		if err == nil && len(existing) > 0 {
			return true, nil
		}
	}
	delete(contact, "@odata.context")
	delete(contact, "id")
	_, err = client.PostJSON(ctx, fmt.Sprintf("/users/%s/contacts", userID), contact)
	return false, err
}

func primaryEmail(m map[string]any) string {
	raw, ok := m["emailAddresses"].([]any)
	if !ok {
		return ""
	}
	for _, item := range raw {
		em, ok := item.(map[string]any)
		if !ok {
			continue
		}
		if addr, _ := em["address"].(string); addr != "" {
			return addr
		}
	}
	return ""
}

func restoreTask(ctx context.Context, client *graph.Client, userID string, data []byte, policy string) (bool, error) {
	task, err := parseJSON(data)
	if err != nil {
		return false, err
	}
	listID := mapString(task, "listId")
	if listID == "" {
		lists, err := client.Paginate(ctx, fmt.Sprintf("/users/%s/todo/lists", userID), map[string]string{"$top": "1"})
		if err != nil || len(lists) == 0 {
			return false, fmt.Errorf("no todo list")
		}
		listID, _ = lists[0]["id"].(string)
	}
	title, _ := task["title"].(string)
	if policy == "skip_duplicates" && title != "" {
		escaped := strings.ReplaceAll(title, "'", "''")
		filter := fmt.Sprintf("title eq '%s'", escaped)
		existing, err := client.Paginate(ctx, fmt.Sprintf("/users/%s/todo/lists/%s/tasks", userID, listID), map[string]string{
			"$filter": filter,
			"$top":    "1",
			"$select": "id",
		})
		if err == nil && len(existing) > 0 {
			return true, nil
		}
	}
	delete(task, "@odata.context")
	delete(task, "id")
	delete(task, "listId")
	_, err = client.PostJSON(ctx, fmt.Sprintf("/users/%s/todo/lists/%s/tasks", userID, listID), task)
	return false, err
}

func restoreDriveFile(ctx context.Context, client *graph.Client, target Target, snapshotPath string, data []byte, policy string) (bool, error) {
	return restoreDriveFileStream(ctx, client, target, snapshotPath, int64(len(data)), bytes.NewReader(data), policy)
}

func restoreDriveFileStream(ctx context.Context, client *graph.Client, target Target, snapshotPath string, size int64, body io.Reader, policy string) (bool, error) {
	driveID, itemPath := drivePathFromSnapshot(snapshotPath, target)
	if useAlternateSharePointDrive(target, snapshotPath) {
		resolved, err := resolveSharePointDriveForTarget(ctx, client, target)
		if err != nil {
			return false, err
		}
		driveID = resolved
	} else if driveID == "" {
		driveID = strings.TrimSpace(target.DriveID)
	}
	if driveID == "" {
		return false, fmt.Errorf("could not resolve drive from path")
	}
	graphPath := graphDriveItemPath(itemPath)
	expectedName := path.Base(strings.TrimPrefix(itemPath, "/"))
	if policy == "skip_duplicates" {
		exists, err := driveItemExistsForSkip(ctx, client, driveID, graphPath, expectedName, size)
		if err != nil && !isGraphNotFound(err) {
			return false, fmt.Errorf("check existing %s: %w", expectedName, err)
		}
		if exists {
			return true, nil
		}
		if err := deleteZeroByteDriveStub(ctx, client, driveID, graphPath, expectedName); err != nil {
			return false, fmt.Errorf("remove incomplete %s: %w", expectedName, err)
		}
	}
	_, err := client.PutStream(ctx, fmt.Sprintf("/drives/%s/root:%s:/content", driveID, graphPath), size, body)
	return false, err
}

// graphDriveItemPath formats a drive item path for Graph "root:" addressing.
// Graph requires root:/folder/file — not root:folder/file.
func graphDriveItemPath(itemPath string) string {
	rel := strings.Trim(strings.TrimPrefix(itemPath, "/"), "/")
	if rel == "" {
		return "/"
	}
	parts := strings.Split(rel, "/")
	for i, p := range parts {
		parts[i] = url.PathEscape(p)
	}
	return "/" + strings.Join(parts, "/")
}

// driveItemExistsForSkip returns true when a matching file already exists at the
// destination path. Requires the Graph item name to match, the file facet to be
// present, and (when expectedSize > 0) the remote size to match — so zero-byte
// stubs or incomplete uploads are not treated as duplicates.
func driveItemExistsForSkip(ctx context.Context, client *graph.Client, driveID, graphPath, expectedName string, expectedSize int64) (bool, error) {
	expectedName = strings.TrimSpace(expectedName)
	if expectedName == "" {
		expectedName = path.Base(strings.Trim(strings.TrimPrefix(graphPath, "/"), "/"))
	}
	if expectedName == "" || expectedName == "/" {
		return false, nil
	}
	item, err := client.Get(ctx, fmt.Sprintf("/drives/%s/root:%s", driveID, graphPath), map[string]string{
		"$select": "id,name,file,folder,size",
	})
	if err != nil {
		return false, err
	}
	if itemMatchesDriveItemForSkip(item, expectedName, expectedSize) {
		return true, nil
	}
	return false, nil
}

func itemMatchesDriveItemForSkip(item map[string]any, expectedName string, expectedSize int64) bool {
	if item == nil {
		return false
	}
	name, _ := item["name"].(string)
	if name != expectedName {
		return false
	}
	if _, isFolder := item["folder"]; isFolder {
		return false
	}
	if _, isFile := item["file"]; !isFile {
		return false
	}
	if expectedSize > 0 {
		remoteSize := driveItemSize(item)
		if remoteSize != expectedSize {
			return false
		}
	} else if driveItemSize(item) == 0 {
		// Snapshot size unknown — still reject zero-byte stubs (failed upload placeholders).
		return false
	}
	return true
}

// deleteZeroByteDriveStub removes failed-upload placeholders Graph still resolves by
// path but that do not appear in folder listings or the OneDrive UI.
func deleteZeroByteDriveStub(ctx context.Context, client *graph.Client, driveID, graphPath, expectedName string) error {
	item, err := client.Get(ctx, fmt.Sprintf("/drives/%s/root:%s", driveID, graphPath), map[string]string{
		"$select": "id,name,size,file",
	})
	if err != nil {
		if isGraphNotFound(err) {
			return nil
		}
		return err
	}
	name, _ := item["name"].(string)
	if name != expectedName || driveItemSize(item) != 0 {
		return nil
	}
	if _, isFile := item["file"]; !isFile {
		return nil
	}
	itemID, _ := item["id"].(string)
	if itemID == "" {
		return nil
	}
	return client.Delete(ctx, fmt.Sprintf("/drives/%s/items/%s", driveID, itemID))
}

func driveItemSize(item map[string]any) int64 {
	switch v := item["size"].(type) {
	case float64:
		return int64(v)
	case int64:
		return v
	case int:
		return int64(v)
	default:
		return 0
	}
}

func drivePathFromSnapshot(path string, target Target) (driveID, itemPath string) {
	parts := strings.Split(path, "/")
	for i, p := range parts {
		if p == "onedrive" && i+1 < len(parts) && parts[i+1] == "content" {
			if i+2 < len(parts) {
				itemPath = "/" + strings.Join(parts[i+2:], "/")
			}
			return "", itemPath
		}
		if p == "drives" && i+1 < len(parts) {
			driveID = parts[i+1]
			startIdx := i + 2
			if i+2 < len(parts) && parts[i+2] == "content" {
				startIdx = i + 3
			}
			if startIdx < len(parts) {
				itemPath = "/" + strings.Join(parts[startIdx:], "/")
			}
			return driveID, itemPath
		}
	}
	return "", ""
}

func useAlternateSharePointDrive(target Target, snapshotPath string) bool {
	if !strings.EqualFold(strings.TrimSpace(target.DestinationMode), "alternate") {
		return false
	}
	lower := strings.ToLower(snapshotPath)
	return strings.Contains(lower, "/sites/") && strings.Contains(lower, "/drives/")
}

func resolveSharePointDriveForTarget(ctx context.Context, client *graph.Client, target Target) (string, error) {
	siteID := strings.TrimSpace(target.GraphID)
	if siteID == "" {
		return "", fmt.Errorf("target SharePoint site id missing")
	}
	item, err := client.Get(ctx, "/sites/"+siteID+"/drive", map[string]string{"$select": "id"})
	if err != nil {
		return "", fmt.Errorf("resolve target site drive: %w", err)
	}
	id, _ := item["id"].(string)
	if strings.TrimSpace(id) == "" {
		return "", fmt.Errorf("target site has no default document library drive")
	}
	return id, nil
}

func restoreTeamsMessage(ctx context.Context, client *graph.Client, target Target, path string, data []byte, policy string) (bool, error) {
	msg, err := parseJSON(data)
	if err != nil {
		return false, err
	}
	teamID, channelID := teamChannelFromPath(path)
	if teamID == "" || channelID == "" {
		return false, fmt.Errorf("team/channel not in path")
	}
	body, _ := msg["body"].(map[string]any)
	payload := map[string]any{"body": body}
	if payload["body"] == nil {
		payload = msg
	}
	delete(payload, "@odata.context")
	delete(payload, "id")
	_, err = client.PostJSON(ctx, fmt.Sprintf("/teams/%s/channels/%s/messages", teamID, channelID), payload)
	return false, err
}

func teamChannelFromPath(path string) (teamID, channelID string) {
	parts := strings.Split(path, "/")
	for i, p := range parts {
		if p == "teams" && i+1 < len(parts) {
			teamID = parts[i+1]
		}
		if p == "channels" && i+1 < len(parts) {
			channelID = parts[i+1]
		}
	}
	return teamID, channelID
}

func restorePlannerItem(ctx context.Context, client *graph.Client, target Target, data []byte, policy string) (bool, error) {
	item, err := parseJSON(data)
	if err != nil {
		return false, err
	}
	delete(item, "@odata.context")
	delete(item, "id")
	planID := mapString(item, "planId")
	if planID == "" {
		return false, fmt.Errorf("planner planId missing")
	}
	delete(item, "planId")
	_, err = client.PostJSON(ctx, fmt.Sprintf("/planner/plans/%s/tasks", planID), item)
	return false, err
}

func restoreOneNoteItem(ctx context.Context, client *graph.Client, target Target, data []byte, policy string) (bool, error) {
	_, err := parseJSON(data)
	if err != nil {
		return false, err
	}
	return false, fmt.Errorf("onenote granular restore not yet supported for path item")
}
