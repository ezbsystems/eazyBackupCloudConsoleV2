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

func TestCachingOptionsDefaultHardLimitWhenUnset(t *testing.T) {
	// Prod: content_cache_size_mib=512 with no content_cache_limit_mib left
	// ContentCacheSizeLimitBytes=0 (unlimited). Concurrent SharePoint uploads grew
	// content cache to ~50GiB on 61GiB workers and triggered soft→hard cancel loops.
	settings := RepoCacheSettings{
		RepoConfigDir:       t.TempDir(),
		ContentCacheSizeMiB: 512,
		// ContentCacheLimitMiB intentionally 0
		MetadataCacheSizeMiB: 128,
	}
	storage := StorageOptions{Bucket: "test-bucket", Endpoint: "http://s3.example"}
	opts := settings.cachingOptions(storage)
	if opts.ContentCacheSizeLimitBytes < opts.ContentCacheSizeBytes {
		t.Fatalf("expected content hard limit >= soft size, soft=%d hard=%d",
			opts.ContentCacheSizeBytes, opts.ContentCacheSizeLimitBytes)
	}
	// Cap must stay well below a 61GiB worker rootfs so soft pressure has headroom.
	maxAllowed := int64(4096) << 20
	if opts.ContentCacheSizeLimitBytes > maxAllowed {
		t.Fatalf("content hard limit too large: %d > %d", opts.ContentCacheSizeLimitBytes, maxAllowed)
	}
	if opts.MetadataCacheSizeLimitBytes < opts.MetadataCacheSizeBytes {
		t.Fatalf("expected metadata hard limit >= soft size, soft=%d hard=%d",
			opts.MetadataCacheSizeBytes, opts.MetadataCacheSizeLimitBytes)
	}
}
