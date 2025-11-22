package rclone

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"time"

	"github.com/your-org/e3-cloudbackup-worker/internal/config"
	"github.com/your-org/e3-cloudbackup-worker/internal/db"
)

type Builder struct {
	cfg *config.Config
}

func NewBuilder(cfg *config.Config) *Builder {
	return &Builder{cfg: cfg}
}

// GenerateRcloneConfig writes a minimal rclone config file with:
// - source remote based on job.SourceType and decrypted source_config_enc
// - dest remote for e3 destination with decrypted credentials
// Returns the remote names to use ("source", "dest").
func (b *Builder) GenerateRcloneConfig(ctx context.Context, job *db.Job, configPath string, decryptedSourceJSON string, destAccessKey string, destSecretKey string, driveRefreshToken string, googleClientID string, googleClientSecret string) (string, string, error) {
	content := new(strings.Builder)
	// Source remote
	switch job.SourceType {
	case "s3_compatible":
		var src S3Source
		if err := json.Unmarshal([]byte(decryptedSourceJSON), &src); err != nil {
			return "", "", fmt.Errorf("parse decrypted source json: %w", err)
		}
		if src.Endpoint == "" || src.AccessKey == "" || src.SecretKey == "" || src.Region == "" {
			return "", "", errors.New("missing required s3 source fields")
		}
		fmt.Fprintf(content, "[source]\n")
		fmt.Fprintf(content, "type = s3\n")
		fmt.Fprintf(content, "provider = Other\n")
		fmt.Fprintf(content, "env_auth = false\n")
		fmt.Fprintf(content, "access_key_id = %s\n", src.AccessKey)
		fmt.Fprintf(content, "secret_access_key = %s\n", src.SecretKey)
		fmt.Fprintf(content, "endpoint = %s\n", src.Endpoint)
		fmt.Fprintf(content, "region = %s\n", src.Region)
		fmt.Fprintln(content)
	case "aws":
		var a AWSSource
		if err := json.Unmarshal([]byte(decryptedSourceJSON), &a); err != nil {
			return "", "", fmt.Errorf("parse decrypted aws source json: %w", err)
		}
		if a.AccessKey == "" || a.SecretKey == "" || a.Region == "" {
			return "", "", errors.New("missing required aws source fields")
		}
		fmt.Fprintf(content, "[source]\n")
		fmt.Fprintf(content, "type = s3\n")
		fmt.Fprintf(content, "provider = AWS\n")
		fmt.Fprintf(content, "env_auth = false\n")
		fmt.Fprintf(content, "access_key_id = %s\n", a.AccessKey)
		fmt.Fprintf(content, "secret_access_key = %s\n", a.SecretKey)
		fmt.Fprintf(content, "region = %s\n", a.Region)
		fmt.Fprintln(content)
	case "sftp":
		var s SFTPSource
		if err := json.Unmarshal([]byte(decryptedSourceJSON), &s); err != nil {
			return "", "", fmt.Errorf("parse decrypted source json: %w", err)
		}
		if s.Host == "" || s.User == "" {
			return "", "", errors.New("missing required sftp source fields")
		}
		fmt.Fprintf(content, "[source]\n")
		fmt.Fprintf(content, "type = sftp\n")
		fmt.Fprintf(content, "host = %s\n", s.Host)
		if s.Port > 0 {
			fmt.Fprintf(content, "port = %d\n", s.Port)
		}
		fmt.Fprintf(content, "user = %s\n", s.User)
		if s.Pass != "" {
			fmt.Fprintf(content, "pass = %s\n", s.Pass)
		}
		fmt.Fprintln(content)
	case "google_drive":
		var g GDriveSource
		if err := json.Unmarshal([]byte(decryptedSourceJSON), &g); err != nil {
			return "", "", fmt.Errorf("parse decrypted drive source json: %w", err)
		}
		fmt.Fprintf(content, "[source]\n")
		fmt.Fprintf(content, "type = drive\n")
		// Always set readonly scope for backups
		fmt.Fprintf(content, "scope = drive.readonly\n")
		// Prefer env-supplied credentials passed from caller; fallback to job JSON
		cid := googleClientID
		if cid == "" {
			cid = g.ClientID
		}
		csec := googleClientSecret
		if csec == "" {
			csec = g.ClientSecret
		}
		if cid != "" {
			fmt.Fprintf(content, "client_id = %s\n", cid)
		}
		if csec != "" {
			fmt.Fprintf(content, "client_secret = %s\n", csec)
		}
		// Token: if caller provided a refresh token, merge with any existing token JSON from decrypted source
		if driveRefreshToken != "" {
			var tok map[string]any
			if strings.TrimSpace(g.Token) != "" {
				_ = json.Unmarshal([]byte(g.Token), &tok) // best-effort
			}
			if tok == nil {
				tok = map[string]any{}
			}
			// Ensure required fields are present; prefer existing access_token/expiry if present
			tok["refresh_token"] = driveRefreshToken
			if _, ok := tok["token_type"]; !ok {
				tok["token_type"] = "Bearer"
			}
			if _, ok := tok["access_token"]; !ok {
				tok["access_token"] = ""
			}
			if _, ok := tok["expiry"]; !ok {
				tok["expiry"] = time.Unix(0, 0).UTC().Format(time.RFC3339)
			}
			if bts, err := json.Marshal(tok); err == nil {
				fmt.Fprintf(content, "token = %s\n", string(bts))
			} else if g.Token != "" {
				fmt.Fprintf(content, "token = %s\n", g.Token)
			}
		} else if g.Token != "" {
			fmt.Fprintf(content, "token = %s\n", g.Token)
		}
		if g.RootFolderID != "" {
			fmt.Fprintf(content, "root_folder_id = %s\n", g.RootFolderID)
		}
		if g.TeamDrive != "" {
			fmt.Fprintf(content, "team_drive = %s\n", g.TeamDrive)
		}
		fmt.Fprintln(content)
	case "dropbox":
		var d DropboxSource
		if err := json.Unmarshal([]byte(decryptedSourceJSON), &d); err != nil {
			return "", "", fmt.Errorf("parse decrypted dropbox source json: %w", err)
		}
		fmt.Fprintf(content, "[source]\n")
		fmt.Fprintf(content, "type = dropbox\n")
		if d.Token != "" {
			fmt.Fprintf(content, "token = %s\n", d.Token)
		}
		if d.Root != "" {
			fmt.Fprintf(content, "root_folder = %s\n", d.Root)
		}
		fmt.Fprintln(content)
	default:
		return "", "", fmt.Errorf("unsupported source type: %s", job.SourceType)
	}
	// Destination remote (e3 / S3-compatible)
	fmt.Fprintf(content, "[dest]\n")
	fmt.Fprintf(content, "type = s3\n")
	fmt.Fprintf(content, "provider = Other\n")
	fmt.Fprintf(content, "env_auth = false\n")
	fmt.Fprintf(content, "access_key_id = %s\n", destAccessKey)
	fmt.Fprintf(content, "secret_access_key = %s\n", destSecretKey)
	fmt.Fprintf(content, "endpoint = %s\n", b.cfg.Destination.Endpoint)
	if b.cfg.Destination.Region != "" {
		fmt.Fprintf(content, "region = %s\n", b.cfg.Destination.Region)
	}
	fmt.Fprintln(content)

	if err := os.MkdirAll(filepath.Dir(configPath), 0o755); err != nil {
		return "", "", fmt.Errorf("ensure config dir: %w", err)
	}
	if err := os.WriteFile(configPath, []byte(content.String()), 0o600); err != nil {
		return "", "", fmt.Errorf("write rclone config: %w", err)
	}
	return "source", "dest", nil
}

// BuildSyncArgs builds arguments for `rclone sync` using prepared remotes.
// It returns the args slice that should be passed to exec.Command(rclone, args...).
func (b *Builder) BuildSyncArgs(job *db.Job, sourceRemote string, destRemote string, configPath string, logPath string) []string {
	sourcePath := job.SourcePath
	if strings.HasPrefix(sourcePath, "/") {
		sourcePath = strings.TrimPrefix(sourcePath, "/")
	}
	dest := fmt.Sprintf("%s:%s/%s", destRemote, job.DestBucketName, strings.TrimPrefix(job.DestPrefix, "/"))
	source := fmt.Sprintf("%s:%s", sourceRemote, sourcePath)

	args := []string{
		"sync",
		source,
		dest,
		"--config", configPath,
		"--use-json-log",
		"--log-file", logPath,
		"--stats", b.cfg.Rclone.StatsInterval,
		"--stats-one-line=false",
	}
	// Global bandwidth limit
	if b.cfg.Worker.MaxBandwidthKbps > 0 {
		args = append(args, "--bwlimit", fmt.Sprintf("%dk", b.cfg.Worker.MaxBandwidthKbps))
	}
	// Log level mapping
	level := strings.ToLower(b.cfg.Rclone.LogLevel)
	if level == "" {
		level = "INFO"
	}
	args = append(args, "--log-level", level)

	return args
}

// BuildSyncArgsWithBwLimit allows overriding bandwidth limit at runtime (kbps).
// If bwLimitKbps <= 0, falls back to default from config.
func (b *Builder) BuildSyncArgsWithBwLimit(job *db.Job, sourceRemote string, destRemote string, configPath string, logPath string, bwLimitKbps int) []string {
	args := b.BuildSyncArgs(job, sourceRemote, destRemote, configPath, logPath)
	if bwLimitKbps > 0 {
		// Replace or append --bwlimit
		has := false
		for i := range args {
			if args[i] == "--bwlimit" && i+1 < len(args) {
				args[i+1] = fmt.Sprintf("%dk", bwLimitKbps)
				has = true
				break
			}
		}
		if !has {
			args = append(args, "--bwlimit", fmt.Sprintf("%dk", bwLimitKbps))
		}
	}
	return args
}

// BuildCheckArgs builds arguments for `rclone check` to validate source vs dest.
func (b *Builder) BuildCheckArgs(job *db.Job, sourceRemote string, destRemote string, configPath string, logPath string) []string {
	sourcePath := job.SourcePath
	if strings.HasPrefix(sourcePath, "/") {
		sourcePath = strings.TrimPrefix(sourcePath, "/")
	}
	dest := fmt.Sprintf("%s:%s/%s", destRemote, job.DestBucketName, strings.TrimPrefix(job.DestPrefix, "/"))
	source := fmt.Sprintf("%s:%s", sourceRemote, sourcePath)

	args := []string{
		"check",
		source,
		dest,
		"--config", configPath,
		"--use-json-log",
		"--log-file", logPath,
		"--stats", b.cfg.Rclone.StatsInterval,
		"--stats-one-line=false",
	}
	level := strings.ToLower(b.cfg.Rclone.LogLevel)
	if level == "" {
		level = "INFO"
	}
	args = append(args, "--log-level", level)
	return args
}

type S3Source struct {
	Endpoint  string `json:"endpoint"`
	AccessKey string `json:"access_key"`
	SecretKey string `json:"secret_key"`
	Bucket    string `json:"bucket"`
	Region    string `json:"region"`
}

type AWSSource struct {
	AccessKey string `json:"access_key"`
	SecretKey string `json:"secret_key"`
	Bucket    string `json:"bucket"`
	Region    string `json:"region"`
}

type SFTPSource struct {
	Host string `json:"host"`
	Port int    `json:"port"`
	User string `json:"user"`
	Pass string `json:"pass"`
}

type GDriveSource struct {
	ClientID     string `json:"client_id"`
	ClientSecret string `json:"client_secret"`
	Token        string `json:"token"`
	RootFolderID string `json:"root_folder_id"`
	TeamDrive    string `json:"team_drive"`
}

type DropboxSource struct {
	Token string `json:"token"`
	Root  string `json:"root"`
}
