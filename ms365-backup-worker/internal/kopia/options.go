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
	RepoConfigDir       string
	ContentCacheSizeMiB int
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
	metadataBytes := contentBytes / 4
	if metadataBytes < 64<<20 {
		metadataBytes = 64 << 20
	}
	return &content.CachingOptions{
		CacheDirectory:            cacheDir,
		MaxCacheSizeBytes:         contentBytes,
		MaxMetadataCacheSizeBytes: metadataBytes,
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
	})
}

func EnsureRunDir(runDir string) error {
	return os.MkdirAll(filepath.Join(runDir, "kopia"), 0o755)
}
