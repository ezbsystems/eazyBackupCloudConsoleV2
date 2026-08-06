package archive

import (
	"strings"
	"testing"
)

func TestZipPathResolverMailFolder(t *testing.T) {
	meta := NewMetadataIndex()
	meta.Put("tenant/users/u1/mail/folder1/_folder.json", []byte(`{"displayName":"Inbox"}`))
	meta.Put("tenant/directory/users/u1.json", []byte(`{"userPrincipalName":"jane@contoso.com"}`))

	r := NewZipPathResolver(meta, nil)
	got := r.ZipPath("tenant/users/u1/mail/folder1/msg.json")
	want := "mail/jane@contoso.com/Inbox/msg.json"
	if got != want {
		t.Fatalf("ZipPath = %q, want %q", got, want)
	}
}

func TestZipPathResolverTeamsChannel(t *testing.T) {
	meta := NewMetadataIndex()
	meta.Put("tenant/teams/t1/metadata.json", []byte(`{
		"displayName":"Engineering",
		"channels":[{"id":"c1","displayName":"General"}]
	}`))

	r := NewZipPathResolver(meta, nil)
	got := r.ZipPath("tenant/teams/t1/channels/c1/messages/m1.json")
	want := "teams/Engineering/General/messages/m1.json"
	if got != want {
		t.Fatalf("ZipPath = %q, want %q", got, want)
	}
}

func TestZipPathResolverCollision(t *testing.T) {
	meta := NewMetadataIndex()
	meta.Put("tenant/directory/users/u1.json", []byte(`{"mail":"user@test.com"}`))
	meta.Put("tenant/users/u1/mail/f1/_folder.json", []byte(`{"displayName":"Reports"}`))
	meta.Put("tenant/users/u1/mail/f2/_folder.json", []byte(`{"displayName":"Reports"}`))

	r := NewZipPathResolver(meta, nil)
	p1 := r.ZipPath("tenant/users/u1/mail/f1/a.json")
	p2 := r.ZipPath("tenant/users/u1/mail/f2/b.json")
	if p1 == p2 {
		t.Fatalf("expected distinct paths for duplicate folder names, got %q and %q", p1, p2)
	}
}

func TestInferMetadataPaths(t *testing.T) {
	paths := inferMetadataPaths("tenant/users/u1/mail/f1/msg.json")
	if len(paths) < 2 {
		t.Fatalf("expected metadata paths, got %v", paths)
	}
}

func TestZipPathResolverClaimUserLabel(t *testing.T) {
	userID := "5fbc7fbb-1234-5678-9abc-def012345678"
	labels := map[string]string{
		userID: "jane@contoso.com",
	}
	r := NewZipPathResolver(NewMetadataIndex(), labels)
	got := r.ZipPath("tenant/users/" + userID + "/mail/folder1/msg.json")
	want := "mail/jane@contoso.com/Folder/msg.json"
	if got != want {
		t.Fatalf("ZipPath = %q, want %q", got, want)
	}
	gotOD := r.ZipPath("tenant/users/" + userID + "/onedrive/content/Documents/file.pdf")
	wantOD := "onedrive/jane@contoso.com/Documents/file.pdf"
	if gotOD != wantOD {
		t.Fatalf("onedrive ZipPath = %q, want %q", gotOD, wantOD)
	}
}

func TestZipPathResolverClaimUserLabelCollision(t *testing.T) {
	userA := "aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"
	userB := "bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb"
	labels := map[string]string{
		userA: "user@test.com",
		userB: "user@test.com",
	}
	r := NewZipPathResolver(NewMetadataIndex(), labels)
	p1 := r.ZipPath("tenant/users/" + userA + "/mail/f1/a.json")
	p2 := r.ZipPath("tenant/users/" + userB + "/mail/f1/b.json")
	if p1 == p2 {
		t.Fatalf("expected distinct paths for duplicate UPNs, got %q and %q", p1, p2)
	}
	if !strings.Contains(p1, "user@test.com") || !strings.Contains(p2, "user@test.com") {
		t.Fatalf("expected UPN in paths, got %q and %q", p1, p2)
	}
}

func TestZipPathResolverMissingClaimLabelFallsBackToGuidSuffix(t *testing.T) {
	userID := "5fbc7fbb-1234-5678-9abc-def012345678"
	r := NewZipPathResolver(NewMetadataIndex(), nil)
	got := r.ZipPath("tenant/users/" + userID + "/mail/folder1/msg.json")
	want := "mail/5fbc7fbb/Folder/msg.json"
	if got != want {
		t.Fatalf("ZipPath = %q, want %q", got, want)
	}
}
