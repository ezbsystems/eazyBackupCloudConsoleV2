package archive

import "testing"

func TestZipPathResolverMailFolder(t *testing.T) {
	meta := NewMetadataIndex()
	meta.Put("tenant/users/u1/mail/folder1/_folder.json", []byte(`{"displayName":"Inbox"}`))
	meta.Put("tenant/directory/users/u1.json", []byte(`{"userPrincipalName":"jane@contoso.com"}`))

	r := NewZipPathResolver(meta)
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

	r := NewZipPathResolver(meta)
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

	r := NewZipPathResolver(meta)
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
