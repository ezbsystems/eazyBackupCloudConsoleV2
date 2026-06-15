package graphsync

import (
	"strconv"
	"testing"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
)

func TestItemBelongsToShardPartition(t *testing.T) {
	counts := make([]int, 4)
	for i := 0; i < 1000; i++ {
		id := "item-" + strconv.Itoa(i)
		for shard := 0; shard < 4; shard++ {
			f := ShardFilter{Index: shard, Total: 4}
			if f.IncludesItem(id) {
				counts[shard]++
			}
		}
	}
	for _, c := range counts {
		if c == 0 {
			t.Fatalf("expected non-empty shard partition, got counts=%v", counts)
		}
	}
	f := ShardFilter{Index: 0, Total: 4}
	if !f.IncludesItem("stable-id") || !f.IncludesItem("stable-id") {
		t.Fatal("shard assignment must be stable")
	}
}

func TestShardFilterFromJob(t *testing.T) {
	f := ShardFilterFromJob("", &api.ShardInfo{Index: 2, Total: 8, Kind: "range"})
	if !f.Active() || f.Index != 2 || f.Total != 8 {
		t.Fatalf("unexpected filter: %+v", f)
	}
}
