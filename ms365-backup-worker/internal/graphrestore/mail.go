package graphrestore

import (
	"context"
	"fmt"
	"net/url"
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

func restoreCalendarEvent(ctx context.Context, client *graph.Client, userID string, data []byte, policy string) (bool, error) {
	event, err := parseJSON(data)
	if err != nil {
		return false, err
	}
	// Calendar events captured from Graph do not carry their owning calendar id in
	// the body — it is only present in the snapshot path, which is lossy (safeID
	// rewrites '/', '\\' and ':' to '_') and cannot be turned back into a real
	// Graph calendar id. When the id is absent we restore into the user's default
	// calendar via /users/{id}/events. Using mapString avoids the previous bug
	// where fmt.Sprint(nil) produced the literal string "<nil>", which then
	// defeated the empty-string fallback and POSTed to /calendars/<nil>/events.
	calendarID := mapString(event, "calendarId")
	if calendarID == "" {
		calendarID = mapString(event, "parentCalendarId")
	}

	iCalUID := mapString(event, "iCalUId")
	if policy == "skip_duplicates" && iCalUID != "" {
		escaped := strings.ReplaceAll(iCalUID, "'", "''")
		filter := fmt.Sprintf("iCalUId eq '%s'", escaped)
		listPath := fmt.Sprintf("/users/%s/events", userID)
		if calendarID != "" {
			listPath = fmt.Sprintf("/users/%s/calendars/%s/events", userID, calendarID)
		}
		existing, err := client.Paginate(ctx, listPath, map[string]string{
			"$filter": filter,
			"$top":    "1",
			"$select": "id",
		})
		if err == nil && len(existing) > 0 {
			return true, nil
		}
	}

	delete(event, "@odata.context")
	delete(event, "id")
	delete(event, "@odata.etag")
	delete(event, "changeKey")
	delete(event, "calendarId")
	delete(event, "parentCalendarId")
	// Suppress meeting invitations — restore body without attendees first.
	delete(event, "attendees")
	delete(event, "responseStatus")

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

func restoreDriveFile(ctx context.Context, client *graph.Client, target Target, path string, data []byte, policy string) (bool, error) {
	driveID, itemPath := drivePathFromSnapshot(path, target)
	if driveID == "" {
		return false, fmt.Errorf("could not resolve drive from path")
	}
	if policy == "skip_duplicates" {
		encPath := url.PathEscape(strings.TrimPrefix(itemPath, "/"))
		existing, err := client.Get(ctx, fmt.Sprintf("/drives/%s/root:%s", driveID, encPath), nil)
		if err == nil && existing != nil {
			return true, nil
		}
	}
	encPath := url.PathEscape(strings.TrimPrefix(itemPath, "/"))
	_, err := client.PutBytes(ctx, fmt.Sprintf("/drives/%s/root:%s:/content", driveID, encPath), data)
	return false, err
}

func drivePathFromSnapshot(path string, target Target) (driveID, itemPath string) {
	parts := strings.Split(path, "/")
	for i, p := range parts {
		if p == "drives" && i+1 < len(parts) {
			driveID = parts[i+1]
			if i+2 < len(parts) {
				itemPath = "/" + strings.Join(parts[i+2:], "/")
			}
			return driveID, itemPath
		}
	}
	return target.GraphID, "/" + path
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
