package archive

import (
	"archive/zip"
	"context"
	"fmt"
	"io"
	"sync"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/kopia"
)

const (
	defaultParallelExtracts = 32
	maxParallelExtracts     = 64
	// streamExtractThreshold avoids buffering very large blobs in memory during prefetch.
	streamExtractThreshold = 16 << 20
)

func clampParallelExtracts(n int) int {
	if n <= 0 {
		return defaultParallelExtracts
	}
	if n > maxParallelExtracts {
		return maxParallelExtracts
	}
	return n
}

type preparedEntry struct {
	index   int
	zipName string
	method  uint16
	body    []byte
	size    int64
	file    fileEntry
	stream  bool
	err     error
}

func buildZipFromFiles(
	ctx context.Context,
	pool *kopia.Pool,
	storage kopia.StorageOptions,
	parallel int,
	zw *zip.Writer,
	files []fileEntry,
	zipMethod uint16,
	report func(done, total int, message string, bytes int64),
) (int64, error) {
	if len(files) == 0 {
		return 0, nil
	}
	parallel = clampParallelExtracts(parallel)
	if len(files) == 1 || parallel == 1 {
		return buildZipSequential(ctx, pool, storage, zw, files, zipMethod, report)
	}

	jobs := make(chan int, parallel*2)
	results := make(chan preparedEntry, parallel*2)

	var workers sync.WaitGroup
	worker := func() {
		defer workers.Done()
		for idx := range jobs {
			if err := ctx.Err(); err != nil {
				results <- preparedEntry{index: idx, err: err}
				continue
			}
			results <- prepareEntry(ctx, pool, storage, files[idx], idx, zipMethod)
		}
	}
	workers.Add(parallel)
	for i := 0; i < parallel; i++ {
		go worker()
	}

	go func() {
		for i := range files {
			jobs <- i
		}
		close(jobs)
		workers.Wait()
		close(results)
	}()

	pending := make(map[int]preparedEntry, parallel*2)
	next := 0
	var contentBytes int64

	for received := 0; received < len(files); {
		select {
		case <-ctx.Done():
			return contentBytes, ctx.Err()
		case pe, ok := <-results:
			if !ok {
				received = len(files)
				break
			}
			received++
			pending[pe.index] = pe
		}

		for {
			pe, ok := pending[next]
			if !ok {
				break
			}
			delete(pending, next)
			if pe.err != nil {
				return contentBytes, pe.err
			}
			n, err := writePreparedEntry(ctx, pool, storage, zw, pe)
			if err != nil {
				return contentBytes, err
			}
			contentBytes += n
			report(next+1, len(files), fmt.Sprintf("Archiving %s", shortName(pe.file.Path)), contentBytes)
			next++
		}
	}

	return contentBytes, nil
}

func buildZipSequential(
	ctx context.Context,
	pool *kopia.Pool,
	storage kopia.StorageOptions,
	zw *zip.Writer,
	files []fileEntry,
	zipMethod uint16,
	report func(done, total int, message string, bytes int64),
) (int64, error) {
	var contentBytes int64
	for i, file := range files {
		if err := ctx.Err(); err != nil {
			return contentBytes, err
		}
		pe := prepareEntry(ctx, pool, storage, file, i, zipMethod)
		if pe.err != nil {
			return contentBytes, pe.err
		}
		n, err := writePreparedEntry(ctx, pool, storage, zw, pe)
		if err != nil {
			return contentBytes, err
		}
		contentBytes += n
		report(i+1, len(files), fmt.Sprintf("Archiving %s", shortName(file.Path)), contentBytes)
	}
	return contentBytes, nil
}

func prepareEntry(
	ctx context.Context,
	pool *kopia.Pool,
	storage kopia.StorageOptions,
	file fileEntry,
	index int,
	zipMethod uint16,
) preparedEntry {
	pe := preparedEntry{
		index:   index,
		zipName: snapshotToZipPath(file.Path),
		method:  zipMethod,
		file:    file,
	}
	if file.Size > streamExtractThreshold {
		pe.stream = true
		pe.size = file.Size
		return pe
	}

	reader, size, err := pool.ExtractReader(ctx, kopia.ExtractRequest{
		Storage:    storage,
		ManifestID: file.ManifestID,
		Path:       file.Path,
		SourcePath: "/ms365",
	})
	if err != nil {
		pe.err = fmt.Errorf("extract %s: %w", file.Path, err)
		return pe
	}
	defer reader.Close()

	if size > streamExtractThreshold {
		pe.stream = true
		pe.size = size
		return pe
	}

	data, err := io.ReadAll(reader)
	if err != nil {
		pe.err = fmt.Errorf("read %s: %w", file.Path, err)
		return pe
	}
	pe.body = data
	pe.size = int64(len(data))
	return pe
}

func writePreparedEntry(
	ctx context.Context,
	pool *kopia.Pool,
	storage kopia.StorageOptions,
	zw *zip.Writer,
	pe preparedEntry,
) (int64, error) {
	header := &zip.FileHeader{
		Name:   pe.zipName,
		Method: pe.method,
	}
	if pe.size > 0 {
		header.UncompressedSize64 = uint64(pe.size)
	}
	header.SetModTime(time.Now().UTC())
	w, err := zw.CreateHeader(header)
	if err != nil {
		return 0, fmt.Errorf("zip header %s: %w", pe.zipName, err)
	}

	if pe.stream {
		reader, _, err := pool.ExtractReader(ctx, kopia.ExtractRequest{
			Storage:    storage,
			ManifestID: pe.file.ManifestID,
			Path:       pe.file.Path,
			SourcePath: "/ms365",
		})
		if err != nil {
			return 0, fmt.Errorf("extract %s: %w", pe.file.Path, err)
		}
		defer reader.Close()
		n, err := io.Copy(w, reader)
		if err != nil {
			return 0, fmt.Errorf("zip write %s: %w", pe.zipName, err)
		}
		return n, nil
	}

	n, err := w.Write(pe.body)
	if err != nil {
		return 0, fmt.Errorf("zip write %s: %w", pe.zipName, err)
	}
	return int64(n), nil
}
