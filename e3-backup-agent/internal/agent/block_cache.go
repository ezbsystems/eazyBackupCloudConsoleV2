package agent

import (
	"encoding/json"
	"os"
	"path/filepath"
)

// BlockCache is a lightweight placeholder to allow future diff/CBT-style caching.
// Current implementation just persists a JSON envelope; phase 2 can extend.
type BlockCache struct {
	Path        string
	JobID       string
	BlockSize   int64
	BlockHashes map[int64][]byte // offset -> hash
}

func LoadBlockCache(baseDir string, jobID string, blockSize int64) *BlockCache {
	safeID := jobID
	if safeID == "" {
		safeID = "unknown"
	}
	path := filepath.Join(baseDir, "cache", "job_cache", "job_"+safeID+".blockcache")
	_ = os.MkdirAll(filepath.Dir(path), 0o755)
	data, err := os.ReadFile(path)
	if err != nil {
		return &BlockCache{Path: path, JobID: jobID, BlockSize: blockSize, BlockHashes: map[int64][]byte{}}
	}
	var bc BlockCache
	if jsonErr := json.Unmarshal(data, &bc); jsonErr != nil {
		return &BlockCache{Path: path, JobID: jobID, BlockSize: blockSize, BlockHashes: map[int64][]byte{}}
	}
	if bc.BlockHashes == nil {
		bc.BlockHashes = map[int64][]byte{}
	}
	if bc.BlockSize == 0 {
		bc.BlockSize = blockSize
	}
	bc.Path = path
	return &bc
}

func (b *BlockCache) Save() error {
	if b == nil || b.Path == "" {
		return nil
	}
	if b.BlockHashes == nil {
		b.BlockHashes = map[int64][]byte{}
	}
	payload, err := json.MarshalIndent(b, "", "  ")
	if err != nil {
		return err
	}
	return os.WriteFile(b.Path, payload, 0o600)
}

// intToString avoids fmt import here.
func intToString(v int64) string {
	return string([]byte(fmtInt(v)))
}

func fmtInt(v int64) []byte {
	if v == 0 {
		return []byte{'0'}
	}
	neg := v < 0
	if neg {
		v = -v
	}
	var buf [20]byte
	i := len(buf)
	for v > 0 {
		i--
		buf[i] = byte('0' + (v % 10))
		v /= 10
	}
	if neg {
		i--
		buf[i] = '-'
	}
	return buf[i:]
}
