package kopia

import (
	"context"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"net/url"
	"os"
	"path/filepath"
	"strings"

	"github.com/kopia/kopia/repo/blob"
	"github.com/kopia/kopia/repo/blob/s3"
	"github.com/kopia/kopia/repo/content"
)

type StorageOptions struct {
	Endpoint     string
	Region       string
	Bucket       string
	Prefix       string
	AccessKey    string
	SecretKey    string
	RepoPassword string
}

// RepoCacheSettings configures persistent per-repo Kopia config and on-disk caches.
type RepoCacheSettings struct {
	RepoConfigDir            string
	ContentCacheSizeMiB      int
	ContentCacheLimitMiB     int
	MetadataCacheSizeMiB     int
	MetadataCacheLimitMiB    int
	MinIndexSweepAgeSeconds  int
}

// CacheBreakdown reports on-disk cache usage by subdirectory.
type CacheBreakdown struct {
	ContentsMiB  int64
	MetadataMiB  int64
	IndexesMiB   int64
	OwnWritesMiB int64
}

// RepoIdentity returns a stable key for a tenant Kopia repository (bucket + prefix).
func (o StorageOptions) RepoIdentity() string {
	prefix := strings.Trim(strings.TrimSpace(o.Prefix), "/")
	if prefix == "" {
		return o.Bucket
	}
	return o.Bucket + ":" + prefix
}

func (o StorageOptions) repoHash() string {
	sum := sha256.Sum256([]byte(o.RepoIdentity() + "|" + strings.TrimSpace(o.Endpoint)))
	return hex.EncodeToString(sum[:16])
}

// PersistentRepoConfigPath returns a stable Kopia config file path for this repository.
func (o StorageOptions) PersistentRepoConfigPath(repoConfigDir string) string {
	return filepath.Join(repoConfigDir, "repos", o.repoHash()+".config")
}

func (s RepoCacheSettings) cachingOptions(storage StorageOptions) *content.CachingOptions {
	cacheDir := filepath.Join(s.RepoConfigDir, "cache", storage.repoHash())
	contentBytes := int64(s.ContentCacheSizeMiB) << 20
	if contentBytes <= 0 {
		contentBytes = 512 << 20
	}
	contentLimitMiB := s.ContentCacheLimitMiB
	if contentLimitMiB <= 0 {
		// Soft size alone is a sweep target; limit 0 is treated as unlimited by Kopia
		// and let concurrent SharePoint uploads grow content cache to tens of GiB.
		contentLimitMiB = s.ContentCacheSizeMiB * 4
		if contentLimitMiB < 2048 {
			contentLimitMiB = 2048
		}
		if contentLimitMiB > 4096 {
			contentLimitMiB = 4096
		}
	}
	contentLimitBytes := int64(contentLimitMiB) << 20
	if contentLimitBytes < contentBytes {
		contentLimitBytes = contentBytes
	}
	metadataBytes := int64(s.MetadataCacheSizeMiB) << 20
	if metadataBytes <= 0 {
		metadataBytes = contentBytes / 4
		if metadataBytes < 64<<20 {
			metadataBytes = 64 << 20
		}
	}
	metadataLimitMiB := s.MetadataCacheLimitMiB
	if metadataLimitMiB <= 0 {
		metadataLimitMiB = s.MetadataCacheSizeMiB * 4
		if metadataLimitMiB < 256 {
			metadataLimitMiB = 256
		}
		if metadataLimitMiB > 1024 {
			metadataLimitMiB = 1024
		}
	}
	metadataLimitBytes := int64(metadataLimitMiB) << 20
	if metadataLimitBytes < metadataBytes {
		metadataLimitBytes = metadataBytes
	}
	minIndexSweep := content.DurationSeconds(s.MinIndexSweepAgeSeconds)
	if minIndexSweep <= 0 {
		minIndexSweep = content.DurationSeconds(3600)
	}
	return &content.CachingOptions{
		CacheDirectory:              cacheDir,
		ContentCacheSizeBytes:       contentBytes,
		ContentCacheSizeLimitBytes:  contentLimitBytes,
		MetadataCacheSizeBytes:      metadataBytes,
		MetadataCacheSizeLimitBytes: metadataLimitBytes,
		MinIndexSweepAge:            minIndexSweep,
		MinContentSweepAge:          content.DurationSeconds(3600),
		MinMetadataSweepAge:         content.DurationSeconds(3600),
	}
}

func (o StorageOptions) Password() string {
	if strings.TrimSpace(o.RepoPassword) != "" {
		return o.RepoPassword
	}
	return fmt.Sprintf("%s:%s:%s", o.Bucket, o.AccessKey, o.SecretKey)
}

func (o StorageOptions) Storage(ctx context.Context) (blob.Storage, error) {
	endpointHost := o.Endpoint
	doNotUseTLS := false
	if o.Endpoint != "" {
		if u, err := url.Parse(o.Endpoint); err == nil && u.Host != "" {
			endpointHost = u.Host
			doNotUseTLS = u.Scheme == "http"
		}
	}
	prefix := strings.Trim(strings.TrimSpace(o.Prefix), "/")
	if prefix != "" {
		prefix += "/"
	}
	return s3.New(ctx, &s3.Options{
		BucketName:      o.Bucket,
		Prefix:          prefix,
		Endpoint:        endpointHost,
		AccessKeyID:     o.AccessKey,
		SecretAccessKey: o.SecretKey,
		Region:          o.Region,
		DoNotUseTLS:     doNotUseTLS,
		DoNotVerifyTLS:  true,
	}, false)
}

func EnsureRunDir(runDir string) error {
	return os.MkdirAll(filepath.Join(runDir, "kopia"), 0o755)
}

// DirSizeMiB returns the total size of a directory tree in MiB.
func DirSizeMiB(path string) int64 {
	var total int64
	_ = filepath.Walk(path, func(_ string, info os.FileInfo, err error) error {
		if err != nil || info == nil || info.IsDir() {
			return nil
		}
		total += info.Size()
		return nil
	})
	return total >> 20
}
