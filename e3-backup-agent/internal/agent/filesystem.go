package agent

import (
	"os"
	"path/filepath"
	"runtime"
	"sort"
	"strings"
	"time"
)

// FileEntry represents an item returned from a directory listing.
type FileEntry struct {
	Name       string    `json:"name"`
	Path       string    `json:"path"`
	IsDir      bool      `json:"is_dir"`
	Size       int64     `json:"size,omitempty"`
	ModifiedAt time.Time `json:"modified_at,omitempty"`
	Type       string    `json:"type,omitempty"` // drive, folder, file
	Icon       string    `json:"icon,omitempty"` // UI hint
}

// BrowseDirectoryRequest carries browse parameters.
type BrowseDirectoryRequest struct {
	Path     string `json:"path"`      // Empty = list root drives
	MaxItems int    `json:"max_items"` // Safety cap; defaults applied server-side
}

// BrowseDirectoryResponse is returned to the server/UI.
type BrowseDirectoryResponse struct {
	Path    string      `json:"path"`
	Parent  string      `json:"parent,omitempty"`
	Entries []FileEntry `json:"entries"`
	Error   string      `json:"error,omitempty"`
}

// BrowseDirectory lists drives (when path empty) or a directory's contents.
func BrowseDirectory(req BrowseDirectoryRequest) BrowseDirectoryResponse {
	maxItems := req.MaxItems
	if maxItems <= 0 {
		maxItems = 500
	}
	if maxItems > 1000 {
		maxItems = 1000
	}

	path := strings.TrimSpace(req.Path)
	if path == "" {
		return listRootDrives()
	}

	return listDirectoryContents(path, maxItems)
}

func listRootDrives() BrowseDirectoryResponse {
	vols, err := ListVolumes()
	if err != nil {
		return BrowseDirectoryResponse{Error: err.Error()}
	}

	var entries []FileEntry
	for _, v := range vols {
		label := v.Label
		if label == "" {
			label = "Local Disk"
		}
		entries = append(entries, FileEntry{
			Name:  v.Path,
			Path:  ensureTrailingSlash(v.Path),
			IsDir: true,
			Size:  int64(v.SizeBytes),
			Type:  "drive",
			Icon:  "drive",
		})
	}

	if runtime.GOOS == "linux" {
		for _, p := range []string{"/home", "/var", "/opt", "/etc"} {
			if info, err := os.Stat(p); err == nil && info.IsDir() {
				entries = append(entries, FileEntry{
					Name:  filepath.Base(p),
					Path:  p,
					IsDir: true,
					Type:  "folder",
					Icon:  "folder-root",
				})
			}
		}
	}

	return BrowseDirectoryResponse{Path: "", Entries: entries}
}

func listDirectoryContents(dirPath string, maxItems int) BrowseDirectoryResponse {
	cleanPath := filepath.Clean(dirPath)

	info, err := os.Stat(cleanPath)
	if err != nil {
		return BrowseDirectoryResponse{Path: cleanPath, Error: err.Error()}
	}
	if !info.IsDir() {
		return BrowseDirectoryResponse{Path: cleanPath, Error: "not a directory"}
	}

	dirEntries, err := os.ReadDir(cleanPath)
	if err != nil {
		return BrowseDirectoryResponse{Path: cleanPath, Error: err.Error()}
	}

	// Sort: directories first, then name
	sort.Slice(dirEntries, func(i, j int) bool {
		iDir := dirEntries[i].IsDir()
		jDir := dirEntries[j].IsDir()
		if iDir != jDir {
			return iDir
		}
		return strings.ToLower(dirEntries[i].Name()) < strings.ToLower(dirEntries[j].Name())
	})

	entries := make([]FileEntry, 0, len(dirEntries))
	for idx, entry := range dirEntries {
		if idx >= maxItems {
			break
		}
		name := entry.Name()
		fullPath := filepath.Join(cleanPath, name)
		e := FileEntry{
			Name:  name,
			Path:  fullPath,
			IsDir: entry.IsDir(),
		}

		if entry.IsDir() {
			e.Type = "folder"
			e.Icon = detectFolderIcon(name)
		} else {
			e.Type = "file"
			e.Icon = detectFileIcon(name)
			if statInfo, statErr := entry.Info(); statErr == nil {
				e.Size = statInfo.Size()
				e.ModifiedAt = statInfo.ModTime()
			}
		}
		entries = append(entries, e)
	}

	parent := filepath.Dir(cleanPath)
	if parent == cleanPath {
		parent = ""
	}

	return BrowseDirectoryResponse{
		Path:    cleanPath,
		Parent:  parent,
		Entries: entries,
	}
}

func detectFolderIcon(name string) string {
	l := strings.ToLower(strings.TrimSpace(name))
	switch l {
	case "documents", "my documents":
		return "folder-documents"
	case "pictures", "photos", "my pictures":
		return "folder-pictures"
	case "music", "my music":
		return "folder-music"
	case "videos", "movies", "my videos":
		return "folder-videos"
	case "downloads":
		return "folder-downloads"
	case "desktop":
		return "folder-desktop"
	case "program files", "program files (x86)", "applications":
		return "folder-apps"
	case "users", "home":
		return "folder-users"
	default:
		return "folder"
	}
}

func detectFileIcon(name string) string {
	ext := strings.ToLower(filepath.Ext(name))
	switch ext {
	case ".jpg", ".jpeg", ".png", ".gif", ".bmp", ".webp", ".svg":
		return "file-image"
	case ".mp4", ".avi", ".mkv", ".mov", ".wmv":
		return "file-video"
	case ".mp3", ".wav", ".flac", ".aac", ".ogg":
		return "file-audio"
	case ".pdf":
		return "file-pdf"
	case ".doc", ".docx":
		return "file-word"
	case ".xls", ".xlsx":
		return "file-excel"
	case ".ppt", ".pptx":
		return "file-powerpoint"
	case ".zip", ".rar", ".7z", ".tar", ".gz":
		return "file-archive"
	case ".exe", ".msi":
		return "file-executable"
	case ".txt", ".log", ".md":
		return "file-text"
	case ".js", ".ts", ".py", ".go", ".php", ".java", ".c", ".cpp", ".h":
		return "file-code"
	default:
		return "file"
	}
}

func ensureTrailingSlash(p string) string {
	if runtime.GOOS == "windows" {
		if !strings.HasSuffix(p, "\\") {
			return p + "\\"
		}
		return p
	}
	if !strings.HasSuffix(p, "/") {
		return p + "/"
	}
	return p
}
