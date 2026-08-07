package api

import (
	"context"
	"fmt"
	"sync"
	"testing"
)

func TestRunLogParallelBatchChildrenNoRace(t *testing.T) {
	// Regression: lazy mutex/map init in RunLog raced when a batch started many
	// children at once (prod 9022: fatal error: concurrent map writes).
		c := NewClient("http://127.0.0.1", "tok", "node-1")
	ctx := context.Background()
	var wg sync.WaitGroup
	for i := 0; i < 64; i++ {
		wg.Add(1)
		go func(n int) {
			defer wg.Done()
			runID := fmt.Sprintf("run-%d", n)
			for j := 0; j < 20; j++ {
				c.RunLog(ctx, runID, "info", "starting run parallel stress")
			}
		}(i)
	}
	wg.Wait()
}
