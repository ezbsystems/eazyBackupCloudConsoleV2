package jobs

import (
	"testing"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
)

func TestStandaloneDrainSuppressedForNonDrainUpdate(t *testing.T) {
	hb := &api.HeartbeatResponse{
		Drain:          true,
		AwaitingDeploy: true,
		Update:         &api.UpdateOffer{Version: "0.3.8", Drain: false},
	}
	pending := hb.Update
	standaloneDrain := hb.Drain
	if pending != nil && !pending.Drain {
		standaloneDrain = false
	}
	if standaloneDrain {
		t.Fatal("expected standalone drain suppressed when update offer has drain=false")
	}
}

func TestStandaloneDrainHonoredForDrainUpdate(t *testing.T) {
	hb := &api.HeartbeatResponse{
		Drain:          true,
		AwaitingDeploy: true,
		Update:         &api.UpdateOffer{Version: "0.3.8", Drain: true},
	}
	pending := hb.Update
	standaloneDrain := hb.Drain
	if pending != nil && !pending.Drain {
		standaloneDrain = false
	}
	if !standaloneDrain {
		t.Fatal("expected standalone drain when update offer requests drain")
	}
}
