package mount

import (
	"testing"

	"github.com/ezbsystems/e3-cloudnas/internal/api"
	"github.com/rclone/rclone/vfs/vfscommon"
)

func TestS3ConfigMatchesAgentCephSettings(t *testing.T) {
	req := api.MountRequest{
		Endpoint:  "https://s3.example.com",
		Region:    "ca-central-1",
		AccessKey: "access",
		SecretKey: "secret",
	}

	got := s3Config(req)
	want := map[string]string{
		"provider":          "Ceph",
		"access_key_id":     "access",
		"secret_access_key": "secret",
		"endpoint":          "https://s3.example.com",
		"region":            "ca-central-1",
		"chunk_size":        "5Mi",
		"copy_cutoff":       "5Gi",
		"upload_cutoff":     "200Mi",
		"force_path_style":  "true",
		"disable_http2":     "true",
		"no_check_bucket":   "true",
		"list_chunk":        "1000",
	}
	for key, value := range want {
		if got[key] != value {
			t.Errorf("config[%q] = %q, want %q", key, got[key], value)
		}
	}
}

func TestRemoteRootMatchesAgentPrefixJoin(t *testing.T) {
	req := api.MountRequest{Bucket: "bucket", Prefix: "/folder/subfolder/"}
	if got, want := remoteRoot(req), "bucket/folder/subfolder/"; got != want {
		t.Fatalf("remoteRoot() = %q, want %q", got, want)
	}
	if got, want := remoteRoot(api.MountRequest{Bucket: "bucket"}), "bucket"; got != want {
		t.Fatalf("remoteRoot() without prefix = %q, want %q", got, want)
	}
}

func TestVFSOptions(t *testing.T) {
	tests := []struct {
		cacheMode string
		want      vfscommon.CacheMode
	}{
		{cacheMode: "off", want: vfscommon.CacheModeOff},
		{cacheMode: "minimal", want: vfscommon.CacheModeMinimal},
		{cacheMode: "writes", want: vfscommon.CacheModeWrites},
		{cacheMode: "full", want: vfscommon.CacheModeFull},
		{cacheMode: "unknown", want: vfscommon.CacheModeWrites},
	}

	for _, test := range tests {
		t.Run(test.cacheMode, func(t *testing.T) {
			got := vfsOptions(api.MountRequest{CacheMode: test.cacheMode, ReadOnly: true})
			if got.CacheMode != test.want {
				t.Errorf("cache mode = %v, want %v", got.CacheMode, test.want)
			}
			if !got.ReadOnly {
				t.Error("read only = false, want true")
			}
		})
	}
}
