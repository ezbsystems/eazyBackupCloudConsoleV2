package jobs

import (
	"context"
	"errors"
	"testing"
)

func TestIsCooperativeCancel(t *testing.T) {
	ctx, cancel := context.WithCancel(context.Background())
	cancel()
	err := ctx.Err()
	if !isCooperativeCancel(err, ctx) {
		t.Fatal("expected cooperative cancel")
	}
	if isCooperativeCancel(errors.New("other"), ctx) {
		t.Fatal("unexpected match for unrelated error")
	}
	deadlineCtx, deadlineCancel := context.WithTimeout(context.Background(), 0)
	defer deadlineCancel()
	<-deadlineCtx.Done()
	if isCooperativeCancel(deadlineCtx.Err(), deadlineCtx) {
		t.Fatal("deadline exceeded should not count as cooperative cancel")
	}
}
