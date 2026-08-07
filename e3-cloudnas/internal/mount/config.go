package mount

import (
	"strings"

	"github.com/ezbsystems/e3-cloudnas/internal/api"
	"github.com/rclone/rclone/fs/config/configmap"
	"github.com/rclone/rclone/vfs/vfscommon"
)

func remoteRoot(req api.MountRequest) string {
	root := strings.Trim(req.Bucket, "/")
	prefix := strings.Trim(req.Prefix, "/")
	if prefix != "" {
		root += "/" + prefix
	}
	return root
}

func s3Config(req api.MountRequest) configmap.Simple {
	return configmap.Simple{
		"provider":          "Ceph",
		"access_key_id":     req.AccessKey,
		"secret_access_key": req.SecretKey,
		"endpoint":          req.Endpoint,
		"region":            req.Region,
		"chunk_size":        "5Mi",
		"copy_cutoff":       "5Gi",
		"upload_cutoff":     "200Mi",
		"force_path_style":  "true",
		"disable_http2":     "true",
		"no_check_bucket":   "true",
		"list_chunk":        "1000",
	}
}

func vfsOptions(req api.MountRequest) vfscommon.Options {
	opt := vfscommon.DefaultOpt
	switch strings.ToLower(strings.TrimSpace(req.CacheMode)) {
	case "off":
		opt.CacheMode = vfscommon.CacheModeOff
	case "minimal":
		opt.CacheMode = vfscommon.CacheModeMinimal
	case "full":
		opt.CacheMode = vfscommon.CacheModeFull
	default:
		opt.CacheMode = vfscommon.CacheModeWrites
	}
	opt.ReadOnly = req.ReadOnly
	return opt
}
