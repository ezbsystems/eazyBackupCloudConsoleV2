package kopia

import (
	"testing"
)

func TestCachingOptionsHardLimits(t *testing.T) {
	settings := RepoCacheSettings{
		RepoConfigDir:         t.TempDir(),
		ContentCacheSizeMiB:   64,
		ContentCacheLimitMiB:  128,
		MetadataCacheSizeMiB:  32,
		MetadataCacheLimitMiB: 64,
	}
	storage := StorageOptions{Bucket: "test-bucket", Endpoint: "http://s3.example"}
	opts := settings.cachingOptions(storage)
	if opts.ContentCacheSizeBytes != 64<<20 {
		t.Fatalf("content soft: got %d", opts.ContentCacheSizeBytes)
	}
	if opts.ContentCacheSizeLimitBytes != 128<<20 {
		t.Fatalf("content hard: got %d", opts.ContentCacheSizeLimitBytes)
	}
	if opts.MetadataCacheSizeBytes != 32<<20 {
		t.Fatalf("metadata soft: got %d", opts.MetadataCacheSizeBytes)
	}
	if opts.MetadataCacheSizeLimitBytes != 64<<20 {
		t.Fatalf("metadata hard: got %d", opts.MetadataCacheSizeLimitBytes)
	}
}
