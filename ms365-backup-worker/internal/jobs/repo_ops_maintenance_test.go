package jobs

import (
	"testing"

	"github.com/eazybackup/ms365-backup-worker/internal/kopia"
)

func TestMaintenanceOutcomeMap(t *testing.T) {
	result := maintenanceOutcomeMap(kopia.MaintenanceOutcome{
		RequestedMode:    "quick",
		EffectiveMode:    "full",
		Escalated:        true,
		Skipped:          false,
		IndexBlobsBefore: 6000,
		IndexBlobsAfter:  1200,
	})
	if result["requested_mode"] != "quick" || result["effective_mode"] != "full" {
		t.Fatalf("unexpected modes: %+v", result)
	}
	if result["escalated"] != true {
		t.Fatalf("expected escalated flag: %+v", result)
	}
	if result["index_blobs_before"] != 6000 || result["index_blobs_after"] != 1200 {
		t.Fatalf("unexpected index counts: %+v", result)
	}
}
