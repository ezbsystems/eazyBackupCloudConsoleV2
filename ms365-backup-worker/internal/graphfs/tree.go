package graphfs

import (
	"context"
	"path"
	"strings"
	"time"

	kopiafs "github.com/kopia/kopia/fs"
)

// BuildTree creates a nested directory tree from flat virtual paths.
func BuildTree(rootName string, files map[string][]byte) kopiafs.Entry {
	root := NewDirectory(rootName)
	for p, content := range files {
		parts := splitPath(p)
		if len(parts) == 0 {
			continue
		}
		cur := root
		for i := 0; i < len(parts)-1; i++ {
			name := parts[i]
			next, err := cur.Child(context.Background(), name)
			if err != nil {
				dir := NewDirectory(name)
				cur.AddEntry(dir)
				cur = dir
				continue
			}
			if md, ok := next.(*memoryDir); ok {
				cur = md
			}
		}
		fileName := parts[len(parts)-1]
		cur.AddEntry(newStaticFile(fileName, content, time.Now().UTC()))
	}
	return root
}

func splitPath(p string) []string {
	p = path.Clean("/" + strings.TrimSpace(p))
	p = strings.TrimPrefix(p, "/")
	if p == "." || p == "" {
		return nil
	}
	return strings.Split(p, "/")
}
