package graphsync

import "testing"

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
