package graphsync

import (
	"context"
	"fmt"
	"strings"

	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
	kopiafs "github.com/kopia/kopia/fs"
)

// VerifyOneDriveOverlayTree checks that root-level OneDrive files in the overlay appear in the built tree.
func VerifyOneDriveOverlayTree(ctx context.Context, overlay *graphfs.OverlayBuilder, root kopiafs.Entry, tenantID, userID string) error {
	if overlay == nil || root == nil || strings.TrimSpace(userID) == "" {
		return nil
	}
	prefix := fmt.Sprintf("%s/users/%s/onedrive/content/", tenantID, userID)
	if !overlayHasOneDriveRootFiles(overlay, prefix) {
		return nil
	}
	contentDir, err := walkOneDriveContentDir(ctx, root, tenantID, userID)
	if err != nil {
		return fmt.Errorf("onedrive tree: %w", err)
	}
	children, err := contentDir.Readdir(ctx)
	if err != nil {
		return fmt.Errorf("onedrive tree readdir: %w", err)
	}
	present := map[string]struct{}{}
	for _, ch := range children {
		if _, isDir := ch.(kopiafs.Directory); isDir {
			continue
		}
		present[ch.Name()] = struct{}{}
	}

	var missing []string
	for _, path := range overlay.Paths() {
		if !strings.HasPrefix(path, prefix) {
			continue
		}
		rel := strings.TrimPrefix(path, prefix)
		if strings.Contains(rel, "/") {
			continue
		}
		if _, ok := present[rel]; !ok {
			missing = append(missing, rel)
		}
	}
	if len(missing) > 0 {
		return fmt.Errorf("onedrive tree missing overlay root files: %s", strings.Join(missing, ", "))
	}
	return nil
}

func overlayHasOneDriveRootFiles(overlay *graphfs.OverlayBuilder, prefix string) bool {
	for _, path := range overlay.Paths() {
		if !strings.HasPrefix(path, prefix) {
			continue
		}
		rel := strings.TrimPrefix(path, prefix)
		if rel != "" && !strings.Contains(rel, "/") {
			return true
		}
	}
	return false
}

func walkOneDriveContentDir(ctx context.Context, root kopiafs.Entry, tenantID, userID string) (kopiafs.Directory, error) {
	parts := []string{tenantID, "users", userID, "onedrive", "content"}
	cur := root
	for _, part := range parts {
		dir, ok := cur.(kopiafs.Directory)
		if !ok {
			return nil, fmt.Errorf("path segment %q is not a directory", part)
		}
		next, err := dir.Child(ctx, part)
		if err != nil {
			return nil, err
		}
		cur = next
	}
	dir, ok := cur.(kopiafs.Directory)
	if !ok {
		return nil, fmt.Errorf("onedrive content is not a directory")
	}
	return dir, nil
}
