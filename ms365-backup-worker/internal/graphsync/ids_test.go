package graphsync

import "testing"

func TestSafeIDPreservesBangDriveID(t *testing.T) {
	raw := "b!Lo81U-FbgEaT7FbLkW0SvS9d295rdrxKrGY9L2qWF24Sz0cFE5BDR7H4es0msZTd"
	if got := safeID(raw); got != raw {
		t.Fatalf("safeID = %q, want %q", got, raw)
	}
	wantStorage := "b_Lo81U-FbgEaT7FbLkW0SvS9d295rdrxKrGY9L2qWF24Sz0cFE5BDR7H4es0msZTd"
	if got := storageSafeID(raw); got != wantStorage {
		t.Fatalf("storageSafeID = %q, want %q", got, wantStorage)
	}
}

func TestStorageSafeIDCommaSiteID(t *testing.T) {
	raw := "stchf.sharepoint.com,4258a7df-79cf-40d0-8f64-54b9c55a0af8,e7593a82-5d61-48a6-8b40-cd5f8b654dcf"
	want := "stchf.sharepoint.com_4258a7df-79cf-40d0-8f64-54b9c55a0af8_e7593a82-5d61-48a6-8b40-cd5f8b654dcf"
	if got := storageSafeID(raw); got != want {
		t.Fatalf("storageSafeID = %q, want %q", got, want)
	}
}

func TestSiteStoragePathCommaSiteID(t *testing.T) {
	tenant := "4728969e-5eff-4981-b0c6-46eadac79cfe"
	raw := "stchf.sharepoint.com,4258a7df-79cf-40d0-8f64-54b9c55a0af8,e7593a82-5d61-48a6-8b40-cd5f8b654dcf"
	got := siteStoragePath(tenant, raw)
	want := tenant + "/sites/stchf.sharepoint.com_4258a7df-79cf-40d0-8f64-54b9c55a0af8_e7593a82-5d61-48a6-8b40-cd5f8b654dcf"
	if got != want {
		t.Fatalf("siteStoragePath = %q, want %q", got, want)
	}
}
