package graphsync

import (
	"hash/fnv"
	"strconv"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
)

// ShardFilter describes range-based content sharding for large drives/sites.
type ShardFilter struct {
	Index int
	Total int
}

func ShardFilterFromJob(shardKey string, shard *api.ShardInfo) ShardFilter {
	if shard != nil && shard.Total > 1 {
		return ShardFilter{Index: shard.Index, Total: shard.Total}
	}
	if shardKey != "" {
		if idx, err := strconv.Atoi(shardKey); err == nil {
			// physical_key suffix only; total unknown — treat as single shard.
			_ = idx
		}
	}
	if shard != nil && shard.Total <= 1 && shard.Index > 0 {
		return ShardFilter{Index: shard.Index, Total: 1}
	}
	return ShardFilter{}
}

func (f ShardFilter) Active() bool {
	return f.Total > 1
}

func (f ShardFilter) IncludesItem(itemID string) bool {
	if !f.Active() || itemID == "" {
		return true
	}
	h := fnv.New32a()
	_, _ = h.Write([]byte(itemID))
	return int(h.Sum32()%uint32(f.Total)) == f.Index
}
