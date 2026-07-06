package kopia

import (
	"bytes"
	"context"
	"io"
	"os"
	"strings"
	"testing"
	"time"

	kopiafs "github.com/kopia/kopia/fs"
)

func TestParseMailMetadataFromJSON(t *testing.T) {
	raw := []byte(`{
		"id":"msg1",
		"subject":"Team offsite agenda #7",
		"receivedDateTime":"2025-06-11T15:39:00Z",
		"isDraft":false,
		"from":{"emailAddress":{"name":"Contoso Admin","address":"admin@contoso.com"}},
		"body":{"contentType":"html","content":"<p>long body</p>"}
	}`)

	meta := parseMailMetadata(raw)
	if meta.Subject != "Team offsite agenda #7" {
		t.Fatalf("subject: got %q", meta.Subject)
	}
	if meta.FromName != "Contoso Admin" {
		t.Fatalf("from name: got %q", meta.FromName)
	}
	if meta.ReceivedAt != "2025-06-11T15:39:00Z" {
		t.Fatalf("received: got %q", meta.ReceivedAt)
	}
}

func TestParseMailMetadataDraftPrefix(t *testing.T) {
	raw := []byte(`{"subject":"Budget approval request","isDraft":true,"sentDateTime":"2025-06-11T14:00:00Z"}`)
	meta := parseMailMetadata(raw)
	if !meta.IsDraft {
		t.Fatal("expected draft")
	}
	labels := mailMessageLabelsFromMeta(meta)
	if labels.Label != "(Draft) Budget approval request" {
		t.Fatalf("label: got %q", labels.Label)
	}
}

func TestParseCalendarMetadata(t *testing.T) {
	raw := []byte(`{
		"subject":"Budget review 4",
		"type":"singleInstance",
		"isAllDay":false,
		"isCancelled":false,
		"start":{"dateTime":"2025-06-16T18:40:00Z","timeZone":"UTC"}
	}`)

	meta := parseCalendarMetadata(raw)
	if meta.Subject != "Budget review 4" {
		t.Fatalf("subject: got %q", meta.Subject)
	}
	if meta.StartAt != "2025-06-16T18:40:00Z" {
		t.Fatalf("start: got %q", meta.StartAt)
	}
	if meta.EventType != "singleInstance" {
		t.Fatalf("type: got %q", meta.EventType)
	}
}

func TestParseCalendarMetadataRecurring(t *testing.T) {
	raw := []byte(`{"subject":"Recurring Meeting #1","type":"seriesMaster","start":{"dateTime":"2025-06-16T00:00:00Z"}}`)
	meta := parseCalendarMetadata(raw)
	if meta.Subject != "Recurring Meeting #1" {
		t.Fatalf("subject: got %q", meta.Subject)
	}
	if meta.EventType != "seriesMaster" {
		t.Fatalf("type: got %q", meta.EventType)
	}
}

func TestOpaqueCalendarFolderFallback(t *testing.T) {
	got := opaqueCalendarFolderFallback("AAMkAGVjZGNkNjgyLWI0ZWUtNDRjMy1iNzc3LWM2MmUzYzZlOGJmYwBGAAAAAAB3V4t7mfolRqmlVW5Vax4UBwCZGheBG4SjR6g15N32C-o8AAAAAAEGAACZGheBG4SjR6g15N32C-o8AAJBSNl-AAA=")
	if !strings.Contains(got, "Calendar …") {
		t.Fatalf("fallback: got %q", got)
	}
}

func mailMessageLabelsFromMeta(meta mailMetadata) browseLabelResult {
	subject := meta.Subject
	if subject == "" {
		subject = "(No subject)"
	}
	if meta.IsDraft {
		subject = "(Draft) " + subject
	}
	sender := meta.FromName
	if sender == "" {
		sender = "Unknown sender"
	}
	when := formatMailDate(meta.ReceivedAt)
	if when == "" {
		when = formatMailDate(meta.SentAt)
	}
	subtitle := sender
	if when != "" {
		subtitle = sender + " · " + when
	}
	return browseLabelResult{Label: subject, Subtitle: subtitle}
}

type memDir struct {
	name     string
	children map[string]kopiafs.Entry
}

func newMemDir(name string, children map[string]kopiafs.Entry) *memDir {
	return &memDir{name: name, children: children}
}

func (d *memDir) Name() string                      { return d.name }
func (d *memDir) Size() int64                       { return 0 }
func (d *memDir) Mode() os.FileMode                 { return os.ModeDir | 0o755 }
func (d *memDir) ModTime() time.Time                { return time.Time{} }
func (d *memDir) IsDir() bool                       { return true }
func (d *memDir) Owner() kopiafs.OwnerInfo          { return kopiafs.OwnerInfo{} }
func (d *memDir) Device() kopiafs.DeviceInfo        { return kopiafs.DeviceInfo{} }
func (d *memDir) LocalFilesystemPath() string       { return "" }
func (d *memDir) Sys() any                          { return nil }
func (d *memDir) Child(_ context.Context, name string) (kopiafs.Entry, error) {
	if e, ok := d.children[name]; ok {
		return e, nil
	}
	return nil, kopiafs.ErrEntryNotFound
}
func (d *memDir) Readdir(_ context.Context) (kopiafs.Entries, error) {
	out := make(kopiafs.Entries, 0, len(d.children))
	for _, e := range d.children {
		out = append(out, e)
	}
	return out, nil
}

type memFile struct {
	name string
	data []byte
}

func newMemFile(name string, data []byte) *memFile {
	return &memFile{name: name, data: data}
}

func (f *memFile) Name() string                { return f.name }
func (f *memFile) Size() int64                 { return int64(len(f.data)) }
func (f *memFile) Mode() os.FileMode           { return 0o644 }
func (f *memFile) ModTime() time.Time          { return time.Time{} }
func (f *memFile) IsDir() bool                 { return false }
func (f *memFile) Owner() kopiafs.OwnerInfo    { return kopiafs.OwnerInfo{} }
func (f *memFile) Device() kopiafs.DeviceInfo  { return kopiafs.DeviceInfo{} }
func (f *memFile) LocalFilesystemPath() string { return "" }
func (f *memFile) Sys() any                    { return nil }
func (f *memFile) Open(_ context.Context) (kopiafs.Reader, error) {
	return &memReader{file: f, r: bytes.NewReader(f.data)}, nil
}

type memReader struct {
	file *memFile
	r    *bytes.Reader
}

func (r *memReader) Read(p []byte) (int, error)  { return r.r.Read(p) }
func (r *memReader) Close() error                { return nil }
func (r *memReader) Seek(offset int64, whence int) (int64, error) {
	return r.r.Seek(offset, whence)
}
func (r *memReader) Entry() (kopiafs.Entry, error) { return r.file, nil }

func TestFolderDisplayNameDualPath(t *testing.T) {
	ctx := context.Background()
	folderID := "AAMkAGQy:opaqueFolderId"
	safeID := safeSnapshotID(folderID)
	root := newMemDir("", map[string]kopiafs.Entry{
		"users": newMemDir("users", map[string]kopiafs.Entry{
			"u1": newMemDir("u1", map[string]kopiafs.Entry{
				"mail": newMemDir("mail", map[string]kopiafs.Entry{
					safeID: newMemDir(safeID, map[string]kopiafs.Entry{
						"_folder.json": newMemFile("_folder.json", []byte(`{"displayName":"Inbox"}`)),
					}),
				}),
			}),
		}),
	})

	got := folderDisplayName(ctx, root, "users/u1/mail/"+folderID)
	if got != "Inbox" {
		t.Fatalf("folderDisplayName dual-path: got %q", got)
	}
}

func TestContactFolderDisplayName(t *testing.T) {
	ctx := context.Background()
	folderID := "AAMkAGQyOpaqueFolderId"
	root := newMemDir("", map[string]kopiafs.Entry{
		"users": newMemDir("users", map[string]kopiafs.Entry{
			"u1": newMemDir("u1", map[string]kopiafs.Entry{
				"contacts": newMemDir("contacts", map[string]kopiafs.Entry{
					folderID: newMemDir(folderID, map[string]kopiafs.Entry{
						"_folder.json": newMemFile("_folder.json", []byte(`{"displayName":"Organizational Contacts"}`)),
					}),
				}),
			}),
		}),
	})

	got := contactFolderDisplayName(ctx, root, "users/u1/contacts/"+folderID)
	if got != "Organizational Contacts" {
		t.Fatalf("contact folder label: got %q", got)
	}
}

func TestOpaqueContactFolderFallback(t *testing.T) {
	got := opaqueContactFolderFallback("AAMkAGVjZGNkNjgyLWI0ZWUtNDRjMy1iNzc3LWM2MmUzYzZlOGJmYwBGAAAAAAB3V4t7mfolRqmlVW5Vax4UBwCZGheBG4SjR6g15N32C-o8AAAAAAEGAACZGheBG4SjR6g15N32C-o8AAJBSNl-AAA=")
	if !strings.Contains(got, "Contacts …") {
		t.Fatalf("fallback: got %q", got)
	}
}

func TestParseContactMetadata(t *testing.T) {
	raw := []byte(`{
		"displayName":"Jane Q. Public",
		"givenName":"Jane",
		"surname":"Public",
		"emailAddresses":[{"address":"jane@contoso.com"}]
	}`)
	meta := parseContactMetadata(raw)
	if meta.DisplayName != "Jane Q. Public" {
		t.Fatalf("displayName: got %q", meta.DisplayName)
	}
	if meta.Email != "jane@contoso.com" {
		t.Fatalf("email: got %q", meta.Email)
	}
}

func TestContactMessageLabelsFromName(t *testing.T) {
	ctx := context.Background()
	root := newMemDir("", map[string]kopiafs.Entry{
		"users": newMemDir("users", map[string]kopiafs.Entry{
			"u1": newMemDir("u1", map[string]kopiafs.Entry{
				"contacts": newMemDir("contacts", map[string]kopiafs.Entry{
					"folder1": newMemDir("folder1", map[string]kopiafs.Entry{
						"c1.json": newMemFile("c1.json", []byte(`{"givenName":"Bob","surname":"Smith","emailAddresses":[{"address":"bob@contoso.com"}]}`)),
					}),
				}),
			}),
		}),
	})

	got := contactMessageLabels(ctx, root, "users/u1/contacts/folder1/c1.json")
	if got.Label != "Bob Smith" {
		t.Fatalf("label: got %q", got.Label)
	}
	if got.Subtitle != "bob@contoso.com" {
		t.Fatalf("subtitle: got %q", got.Subtitle)
	}
}

func TestMailAttachmentFolderLabels(t *testing.T) {
	ctx := context.Background()
	msgID := "AAMkAGMsgId123"
	root := newMemDir("", map[string]kopiafs.Entry{
		"users": newMemDir("users", map[string]kopiafs.Entry{
			"u1": newMemDir("u1", map[string]kopiafs.Entry{
				"mail": newMemDir("mail", map[string]kopiafs.Entry{
					"inbox": newMemDir("inbox", map[string]kopiafs.Entry{
						msgID+".json": newMemFile(msgID+".json", []byte(`{
							"subject":"Quarterly report",
							"receivedDateTime":"2025-06-11T15:39:00Z",
							"from":{"emailAddress":{"name":"Finance","address":"finance@contoso.com"}}
						}`)),
						msgID: newMemDir(msgID, map[string]kopiafs.Entry{
							"attachments": newMemDir("attachments", map[string]kopiafs.Entry{}),
						}),
					}),
				}),
			}),
		}),
	})

	folderPath := "users/u1/mail/inbox/" + msgID
	got := mailAttachmentFolderLabels(ctx, root, folderPath, msgID)
	if got.Label != "Quarterly report" {
		t.Fatalf("label: got %q", got.Label)
	}
	if !strings.Contains(got.Subtitle, "Attachments") {
		t.Fatalf("subtitle: got %q", got.Subtitle)
	}
}

func TestIsMailMessageAttachmentFolder(t *testing.T) {
	if !isMailMessageAttachmentFolder("users/u1/mail/inbox/msg1", "msg1") {
		t.Fatal("expected attachment folder")
	}
	if isMailMessageAttachmentFolder("users/u1/mail/inbox", "inbox") {
		t.Fatal("mail folder should not match")
	}
	if isMailMessageAttachmentFolder("users/u1/mail/inbox/msg1/attachments", "attachments") {
		t.Fatal("nested attachment path should not match")
	}
}

var _ io.Closer = (*memReader)(nil)
