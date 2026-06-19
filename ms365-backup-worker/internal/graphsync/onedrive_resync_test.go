package graphsync

import (
	"testing"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

func TestShouldForceOneDriveFullResyncMisroutedLayout(t *testing.T) {
	tenant := "cfb5450a-eb80-4c61-aecc-9dca87649cf6"
	user := "1533e37a-2e8f-4f24-8155-11777c70997d"
	drive := "b!drive"

	overlay := graphfs.NewOverlayBuilder()
	overlay.PutJSON(tenant+"/users/"+user+"/onedrive/content/SeederData/seed.txt", []byte("x"), time.Now())
	overlay.PutJSON(tenant+"/users/"+user+"/onedrive/content/drives/"+drive+"/root:/placeholder", []byte("{}"), time.Now())

	opts := OneDriveSyncOptions{
		AzureTenantID: tenant,
		UserID:        user,
		DriveID:       drive,
		DeltaLink:     "https://graph.microsoft.com/delta",
		Overlay:       overlay,
	}
	res := &OneDriveSyncResult{Stats: map[string]int{"items": 0}}

	if !shouldForceOneDriveFullResync(opts, drive, res) {
		t.Fatal("expected full resync when misrouted content/drives subtree exists")
	}
}

func TestShouldForceOneDriveFullResyncHealthyIncremental(t *testing.T) {
	tenant := "cfb5450a-eb80-4c61-aecc-9dca87649cf6"
	user := "1533e37a-2e8f-4f24-8155-11777c70997d"
	drive := "b!drive"

	overlay := graphfs.NewOverlayBuilder()
	overlay.PutJSON(tenant+"/users/"+user+"/onedrive/content/SeederData/seed.txt", []byte("x"), time.Now())

	opts := OneDriveSyncOptions{
		AzureTenantID: tenant,
		UserID:        user,
		DriveID:       drive,
		DeltaLink:     "https://graph.microsoft.com/delta",
		Overlay:       overlay,
	}
	res := &OneDriveSyncResult{Stats: map[string]int{"items": 2}}

	if shouldForceOneDriveFullResync(opts, drive, res) {
		t.Fatal("expected no resync when incremental pass stored items and layout is healthy")
	}
}

func TestShouldForceOneDriveFullResyncNoDeltaLink(t *testing.T) {
	opts := OneDriveSyncOptions{
		AzureTenantID: "tenant",
		UserID:        "user",
		Overlay:       graphfs.NewOverlayBuilder(),
	}
	res := &OneDriveSyncResult{Stats: map[string]int{"items": 0}}
	if shouldForceOneDriveFullResync(opts, "drive", res) {
		t.Fatal("expected no resync without incoming delta link")
	}
}
