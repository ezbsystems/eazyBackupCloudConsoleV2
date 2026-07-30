package kopia

import (
	"context"
	"fmt"
	"strings"
	"testing"

	kopiafs "github.com/kopia/kopia/fs"
)

func TestBrowseUnion32Manifests(t *testing.T) {
	ctx := context.Background()
	listsPath := "tenant/sites/site1/lists"
	roots := make(map[string]kopiafs.Directory)
	sources := make([]BrowseSource, 0, 32)

	for i := 0; i < 32; i++ {
		manifestID := fmt.Sprintf("manifest-%02d", i)
		childRunID := fmt.Sprintf("run-%02d", i)
		fileName := fmt.Sprintf("item-%02d.json", i)
		root := newMemDir("", map[string]kopiafs.Entry{
			"tenant": newMemDir("tenant", map[string]kopiafs.Entry{
				"sites": newMemDir("sites", map[string]kopiafs.Entry{
					"site1": newMemDir("site1", map[string]kopiafs.Entry{
						"lists": newMemDir("lists", map[string]kopiafs.Entry{
							"list1": newMemDir("list1", map[string]kopiafs.Entry{
								"items": newMemDir("items", map[string]kopiafs.Entry{
									fileName: newMemFile(fileName, []byte(`{"fields":{"Title":"Item `+fmt.Sprint(i)+`"}}`)),
								}),
							}),
						}),
					}),
				}),
			}),
		})
		roots[manifestID] = root
		sources = append(sources, BrowseSource{
			ChildRunID:     childRunID,
			ManifestID:     manifestID,
			CandidatePaths: []string{listsPath + "/list1/items"},
		})
	}

	result, err := browseMultiFromRoots(ctx, BrowseRequest{
		Path:    listsPath + "/list1/items",
		Sources: sources,
	}, roots)
	if err != nil {
		t.Fatalf("browse: %v", err)
	}
	if len(result.Entries) != 32 {
		t.Fatalf("expected 32 entries, got %d", len(result.Entries))
	}
	for _, entry := range result.Entries {
		if len(entry.SourceRefs) != 1 {
			t.Fatalf("entry %s: expected 1 source ref, got %d", entry.Name, len(entry.SourceRefs))
		}
	}
}

func TestBrowseMergeDuplicateFoldersRetainSourceRefs(t *testing.T) {
	ctx := context.Background()
	contentPath := "tenant/sites/site1/drives/drive1/content"
	roots := map[string]kopiafs.Directory{
		"m1": buildShardContentRoot("m1", "Shared"),
		"m2": buildShardContentRoot("m2", "Shared"),
	}
	sources := []BrowseSource{
		{ChildRunID: "run-1", ManifestID: "m1", CandidatePaths: []string{contentPath}},
		{ChildRunID: "run-2", ManifestID: "m2", CandidatePaths: []string{contentPath}},
	}

	result, err := browseMultiFromRoots(ctx, BrowseRequest{
		Path:    contentPath,
		Sources: sources,
	}, roots)
	if err != nil {
		t.Fatalf("browse: %v", err)
	}
	if len(result.Entries) != 1 {
		t.Fatalf("expected merged folder, got %d entries", len(result.Entries))
	}
	entry := result.Entries[0]
	if entry.Name != "Shared" || entry.Type != "folder" {
		t.Fatalf("unexpected entry: %+v", entry)
	}
	if len(entry.SourceRefs) != 2 {
		t.Fatalf("expected 2 source refs, got %d", len(entry.SourceRefs))
	}
}

func TestBrowseSortFolderFirstAndPaginate(t *testing.T) {
	ctx := context.Background()
	contentPath := "tenant/sites/site1/drives/drive1/content"
	roots := map[string]kopiafs.Directory{
		"m1": newMemDir("", map[string]kopiafs.Entry{
			"tenant": newMemDir("tenant", map[string]kopiafs.Entry{
				"sites": newMemDir("sites", map[string]kopiafs.Entry{
					"site1": newMemDir("site1", map[string]kopiafs.Entry{
						"drives": newMemDir("drives", map[string]kopiafs.Entry{
							"drive1": newMemDir("drive1", map[string]kopiafs.Entry{
								"content": newMemDir("content", map[string]kopiafs.Entry{
									"zebra.txt":  newMemFile("zebra.txt", []byte("z")),
									"Alpha":      newMemDir("Alpha", map[string]kopiafs.Entry{}),
									"beta.txt":   newMemFile("beta.txt", []byte("b")),
									"middle.txt": newMemFile("middle.txt", []byte("m")),
								}),
							}),
						}),
					}),
				}),
			}),
		}),
	}

	result, err := browseMultiFromRoots(ctx, BrowseRequest{
		Path:    contentPath,
		Sources: []BrowseSource{{ManifestID: "m1", CandidatePaths: []string{contentPath}}},
		Limit:   2,
		Offset:  0,
	}, roots)
	if err != nil {
		t.Fatalf("browse: %v", err)
	}
	if result.TotalCount != 4 {
		t.Fatalf("total_count: got %d want 4", result.TotalCount)
	}
	if !result.HasMore || len(result.Entries) != 2 {
		t.Fatalf("page 1: has_more=%v len=%d", result.HasMore, len(result.Entries))
	}
	if result.Entries[0].Type != "folder" || result.Entries[0].Name != "Alpha" {
		t.Fatalf("first entry: %+v", result.Entries[0])
	}
	if result.Entries[1].Type != "file" || result.Entries[1].Name != "beta.txt" {
		t.Fatalf("second entry: %+v", result.Entries[1])
	}

	page2, err := browseMultiFromRoots(ctx, BrowseRequest{
		Path:    contentPath,
		Sources: []BrowseSource{{ManifestID: "m1", CandidatePaths: []string{contentPath}}},
		Limit:   2,
		Offset:  2,
	}, roots)
	if err != nil {
		t.Fatalf("browse page2: %v", err)
	}
	if page2.HasMore || len(page2.Entries) != 2 {
		t.Fatalf("page 2: has_more=%v len=%d", page2.HasMore, len(page2.Entries))
	}
	if page2.Entries[0].Name != "middle.txt" || page2.Entries[1].Name != "zebra.txt" {
		t.Fatalf("page2 entries: %s, %s", page2.Entries[0].Name, page2.Entries[1].Name)
	}
}

func TestBrowseDuplicatePathConflictWarning(t *testing.T) {
	ctx := context.Background()
	contentPath := "tenant/sites/site1/drives/drive1/content"
	roots := map[string]kopiafs.Directory{
		"m1": buildShardContentRootWithFile("m1", "report.txt", []byte("aaa")),
		"m2": buildShardContentRootWithFile("m2", "report.txt", []byte("bbbbbb")),
	}
	sources := []BrowseSource{
		{ChildRunID: "run-1", ManifestID: "m1", CandidatePaths: []string{contentPath}},
		{ChildRunID: "run-2", ManifestID: "m2", CandidatePaths: []string{contentPath}},
	}

	result, err := browseMultiFromRoots(ctx, BrowseRequest{
		Path:    contentPath,
		Sources: sources,
	}, roots)
	if err != nil {
		t.Fatalf("browse: %v", err)
	}
	if len(result.Entries) != 1 {
		t.Fatalf("expected one merged file, got %d", len(result.Entries))
	}
	if len(result.Entries[0].SourceRefs) != 2 {
		t.Fatalf("expected 2 source refs, got %d", len(result.Entries[0].SourceRefs))
	}
	foundConflict := false
	for _, warning := range result.Warnings {
		if strings.Contains(warning, "duplicate path conflict") {
			foundConflict = true
			break
		}
	}
	if !foundConflict {
		t.Fatalf("expected duplicate path conflict warning, got %v", result.Warnings)
	}
}

func TestBrowsePartialSourceFailureReturnsWarning(t *testing.T) {
	ctx := context.Background()
	contentPath := "tenant/sites/site1/drives/drive1/content"
	roots := map[string]kopiafs.Directory{
		"m1": buildShardContentRootWithFile("m1", "ok.txt", []byte("ok")),
	}
	sources := []BrowseSource{
		{ChildRunID: "run-good", ManifestID: "m1", CandidatePaths: []string{contentPath}},
		{ChildRunID: "run-bad", ManifestID: "missing", CandidatePaths: []string{contentPath}},
	}

	result, err := browseMultiFromRoots(ctx, BrowseRequest{
		Path:    contentPath,
		Sources: sources,
	}, roots)
	if err != nil {
		t.Fatalf("browse: %v", err)
	}
	if len(result.Entries) != 1 {
		t.Fatalf("expected partial union, got %d entries", len(result.Entries))
	}
	if len(result.Warnings) == 0 {
		t.Fatal("expected partial-source warning")
	}
}

func TestBrowseAllSourcesFailIsError(t *testing.T) {
	_, err := browseMultiFromRoots(context.Background(), BrowseRequest{
		Path: "tenant/sites/site1/drives/drive1/content",
		Sources: []BrowseSource{
			{ChildRunID: "run-1", ManifestID: "missing-1", CandidatePaths: []string{"nope"}},
			{ChildRunID: "run-2", ManifestID: "missing-2", CandidatePaths: []string{"nope"}},
		},
	}, map[string]kopiafs.Directory{})
	if err == nil || !strings.Contains(err.Error(), "all browse sources failed") {
		t.Fatalf("expected all-source failure, got %v", err)
	}
}

func TestBrowseMaxSourceCount(t *testing.T) {
	sources := make([]BrowseSource, BrowseMaxSourceCount+1)
	for i := range sources {
		sources[i] = BrowseSource{ManifestID: fmt.Sprintf("m%d", i)}
	}
	err := validateBrowseSources(sources)
	if err == nil || !strings.Contains(err.Error(), "exceed maximum") {
		t.Fatalf("expected max source error, got %v", err)
	}
}

func TestBrowseMaxSourceCountAtCeiling(t *testing.T) {
	sources := make([]BrowseSource, BrowseMaxSourceCount)
	for i := range sources {
		sources[i] = BrowseSource{ManifestID: fmt.Sprintf("m%d", i)}
	}
	if err := validateBrowseSources(sources); err != nil {
		t.Fatalf("ceiling should be valid: %v", err)
	}
}

func TestBrowseCandidatePathFallback(t *testing.T) {
	ctx := context.Background()
	contentPath := "tenant/sites/site1/drives/drive1/content"
	roots := map[string]kopiafs.Directory{
		"m1": buildShardContentRootWithFile("m1", "only.txt", []byte("x")),
	}
	result, err := browseMultiFromRoots(ctx, BrowseRequest{
		Path: contentPath,
		Sources: []BrowseSource{{
			ChildRunID:     "run-1",
			ManifestID:     "m1",
			CandidatePaths: []string{"missing/path", contentPath},
		}},
	}, roots)
	if err != nil {
		t.Fatalf("browse: %v", err)
	}
	if len(result.Entries) != 1 || result.Entries[0].Name != "only.txt" {
		t.Fatalf("unexpected entries: %+v", result.Entries)
	}
	if result.Entries[0].SourceRefs[0].SourcePath != contentPath+"/only.txt" {
		t.Fatalf("source path: %q", result.Entries[0].SourceRefs[0].SourcePath)
	}
}

func TestBrowseMergedFolderWarning(t *testing.T) {
	ctx := context.Background()
	contentPath := "tenant/sites/site1/drives/drive1/content"
	roots := map[string]kopiafs.Directory{
		"m1": buildShardContentRoot("m1", "Shared"),
		"m2": buildShardContentRoot("m2", "Shared"),
	}
	result, err := browseMultiFromRoots(ctx, BrowseRequest{
		Path: contentPath,
		Sources: []BrowseSource{
			{ChildRunID: "run-a", ManifestID: "m1", CandidatePaths: []string{contentPath}},
			{ChildRunID: "run-b", ManifestID: "m2", CandidatePaths: []string{contentPath}},
		},
	}, roots)
	if err != nil {
		t.Fatalf("browse: %v", err)
	}
	found := false
	for _, w := range result.Warnings {
		if strings.Contains(w, "merged folder") && strings.Contains(w, "2 shard sources") {
			found = true
			break
		}
	}
	if !found {
		t.Fatalf("expected merged-folder warning, got %v", result.Warnings)
	}
}

func buildShardContentRoot(manifestID, folderName string) kopiafs.Directory {
	return newMemDir("", map[string]kopiafs.Entry{
		"tenant": newMemDir("tenant", map[string]kopiafs.Entry{
			"sites": newMemDir("sites", map[string]kopiafs.Entry{
				"site1": newMemDir("site1", map[string]kopiafs.Entry{
					"drives": newMemDir("drives", map[string]kopiafs.Entry{
						"drive1": newMemDir("drive1", map[string]kopiafs.Entry{
							"content": newMemDir("content", map[string]kopiafs.Entry{
								folderName: newMemDir(folderName, map[string]kopiafs.Entry{}),
							}),
						}),
					}),
				}),
			}),
		}),
	})
}

func buildShardContentRootWithFile(manifestID, fileName string, data []byte) kopiafs.Directory {
	return newMemDir("", map[string]kopiafs.Entry{
		"tenant": newMemDir("tenant", map[string]kopiafs.Entry{
			"sites": newMemDir("sites", map[string]kopiafs.Entry{
				"site1": newMemDir("site1", map[string]kopiafs.Entry{
					"drives": newMemDir("drives", map[string]kopiafs.Entry{
						"drive1": newMemDir("drive1", map[string]kopiafs.Entry{
							"content": newMemDir("content", map[string]kopiafs.Entry{
								fileName: newMemFile(fileName, data),
							}),
						}),
					}),
				}),
			}),
		}),
	})
}
