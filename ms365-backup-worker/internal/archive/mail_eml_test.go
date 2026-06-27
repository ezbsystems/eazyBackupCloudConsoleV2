package archive

import (
	"bytes"
	"strings"
	"testing"
)

func TestBuildMailEMLHeadersAndBody(t *testing.T) {
	msg := map[string]any{
		"subject":             "Hello World",
		"internetMessageId":   "<abc@contoso.com>",
		"receivedDateTime":    "2026-06-26T10:00:00Z",
		"from": map[string]any{
			"emailAddress": map[string]any{"name": "Jane", "address": "jane@contoso.com"},
		},
		"toRecipients": []any{
			map[string]any{"emailAddress": map[string]any{"name": "Bob", "address": "bob@contoso.com"}},
		},
		"bodyPreview": "Plain preview",
		"body": map[string]any{
			"contentType": "html",
			"content":     "<p>HTML body</p>",
		},
	}
	eml, err := buildMailEML(msg, nil, nil)
	if err != nil {
		t.Fatalf("buildMailEML: %v", err)
	}
	s := string(eml)
	for _, want := range []string{"Subject: Hello World", "From:", "jane@contoso.com", "To:", "bob@contoso.com", "text/plain", "text/html"} {
		if !strings.Contains(s, want) {
			t.Fatalf("missing %q in EML:\n%s", want, s)
		}
	}
}

func TestMailEMLFilename(t *testing.T) {
	msg := map[string]any{
		"subject":          "RE: Project",
		"receivedDateTime": "2026-06-26T15:30:00Z",
	}
	name := mailEMLFilename(msg)
	if !strings.HasPrefix(name, "2026-06-26_") || !strings.HasSuffix(name, ".eml") {
		t.Fatalf("unexpected filename %q", name)
	}
}

func TestMailAttachmentParent(t *testing.T) {
	parent, file, ok := mailAttachmentParent("tenant/users/u1/mail/f1/msg/attachments/doc.pdf")
	if !ok {
		t.Fatal("expected attachment parent")
	}
	if parent != "tenant/users/u1/mail/f1/msg.json" {
		t.Fatalf("parent = %q", parent)
	}
	if file != "doc.pdf" {
		t.Fatalf("file = %q", file)
	}
}

func TestWriteMailEMLSpecialSubject(t *testing.T) {
	msg := map[string]any{"subject": `Say "hi"`, "bodyPreview": "x"}
	var buf bytes.Buffer
	if err := writeMailEML(&buf, msg, `Say "hi"`, nil, nil); err != nil {
		t.Fatalf("writeMailEML: %v", err)
	}
	if !strings.Contains(buf.String(), `Subject: Say "hi"`) {
		t.Fatalf("subject not preserved: %s", buf.String())
	}
}
