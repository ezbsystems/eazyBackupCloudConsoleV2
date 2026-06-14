package kopia

import (
	"context"
	"fmt"
	"net/url"
	"os"
	"path/filepath"
	"strings"

	"github.com/kopia/kopia/repo/blob"
	"github.com/kopia/kopia/repo/blob/s3"
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

func (o StorageOptions) RepoConfigPath(runDir, runID string) string {
	return filepath.Join(runDir, "kopia", fmt.Sprintf("run_%s.config", runID))
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
