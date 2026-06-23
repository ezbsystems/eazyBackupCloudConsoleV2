package archive

import (
	"archive/zip"
	"context"
	"fmt"
	"io"
	"strings"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/kopia"
)

type ExportOptions struct {
	Pool             *kopia.Pool
	Storage          kopia.StorageOptions
	Items            []api.RestoreItem
	SourceManifestID string
	ManifestByPath   map[string]string
	ArchiveExport    api.ArchiveExport
	DestEndpoint     string
	DestRegion       string
	DestAccessKey    string
	DestSecretKey    string
	OnProgress       func(done, total int, message string, bytes int64)
}

type ExportResult struct {
	ObjectKey string
	Bytes     int64
	Files     int
}

type archiveStats struct {
	Mode      string `json:"mode"`
	ObjectKey string `json:"object_key"`
	Bytes     int64  `json:"bytes"`
	Files     int    `json:"files"`
}

func StatsJSON(result ExportResult) archiveStats {
	return archiveStats{
		Mode:      "archive",
		ObjectKey: result.ObjectKey,
		Bytes:     result.Bytes,
		Files:     result.Files,
	}
}

func Export(ctx context.Context, opts ExportOptions) (*ExportResult, error) {
	ae := opts.ArchiveExport
	objectKey := strings.TrimSpace(ae.ObjectKey)
	if objectKey == "" {
		return nil, fmt.Errorf("archive object_key required")
	}
	bucket := strings.TrimSpace(ae.Bucket)
	if bucket == "" {
		bucket = strings.TrimSpace(opts.Storage.Bucket)
	}
	if bucket == "" {
		return nil, fmt.Errorf("archive bucket required")
	}

	report := func(done, total int, message string, bytes int64) {
		if opts.OnProgress != nil {
			opts.OnProgress(done, total, message, bytes)
		}
	}

	report(0, len(opts.Items), "Collecting snapshot files", 0)
	files, err := collectSelectionFiles(ctx, opts.Pool, opts.Storage, opts.SourceManifestID, opts.ManifestByPath, opts.Items)
	if err != nil {
		return nil, err
	}
	if len(files) == 0 {
		return nil, fmt.Errorf("no exportable files found in selection")
	}

	client, err := newMinioClient(opts.DestEndpoint, opts.DestRegion, opts.DestAccessKey, opts.DestSecretKey)
	if err != nil {
		return nil, fmt.Errorf("s3 client: %w", err)
	}

	pr, pw := io.Pipe()
	uploadErrCh := make(chan error, 1)
	var uploadedBytes int64

	go func() {
		info, err := streamPutObject(ctx, client, bucket, objectKey, pr)
		if err != nil {
			uploadErrCh <- err
			return
		}
		uploadedBytes = info
		uploadErrCh <- nil
	}()

	zipMethod := zip.Store
	if strings.EqualFold(strings.TrimSpace(ae.Compression), "deflate") {
		zipMethod = zip.Deflate
	}

	var contentBytes int64
	buildErr := func() error {
		zw := zip.NewWriter(pw)
		defer zw.Close()

		for i, file := range files {
			if err := ctx.Err(); err != nil {
				return err
			}
			report(i, len(files), fmt.Sprintf("Archiving %s", shortName(file.Path)), contentBytes)

			reader, size, err := opts.Pool.ExtractReader(ctx, kopia.ExtractRequest{
				Storage:    opts.Storage,
				ManifestID: file.ManifestID,
				Path:       file.Path,
				SourcePath: "/ms365",
			})
			if err != nil {
				return fmt.Errorf("extract %s: %w", file.Path, err)
			}

			zipName := snapshotToZipPath(file.Path)
			header := &zip.FileHeader{
				Name:   zipName,
				Method: zipMethod,
			}
			if size > 0 {
				header.UncompressedSize64 = uint64(size)
			}
			header.SetModTime(time.Now().UTC())
			w, err := zw.CreateHeader(header)
			if err != nil {
				_ = reader.Close()
				return fmt.Errorf("zip header %s: %w", zipName, err)
			}
			n, err := io.Copy(w, reader)
			_ = reader.Close()
			if err != nil {
				return fmt.Errorf("zip write %s: %w", zipName, err)
			}
			contentBytes += n
		}
		report(len(files), len(files), "Finalizing archive", contentBytes)
		return zw.Close()
	}()

	if buildErr != nil {
		_ = pw.CloseWithError(buildErr)
		_ = <-uploadErrCh
		return nil, buildErr
	}
	if err := pw.Close(); err != nil {
		_ = <-uploadErrCh
		return nil, err
	}
	if err := <-uploadErrCh; err != nil {
		return nil, fmt.Errorf("upload: %w", err)
	}

	return &ExportResult{
		ObjectKey: objectKey,
		Bytes:     uploadedBytes,
		Files:     len(files),
	}, nil
}

func shortName(path string) string {
	path = strings.Trim(path, "/")
	if i := strings.LastIndex(path, "/"); i >= 0 {
		return path[i+1:]
	}
	return path
}
