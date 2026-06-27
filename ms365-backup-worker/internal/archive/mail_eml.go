package archive

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"mime"
	"mime/multipart"
	"net/mail"
	"net/textproto"
	"path"
	"path/filepath"
	"strings"
	"time"
)

type mailAttachmentRef struct {
	Path     string
	Filename string
	Size     int64
}

type mailAttachmentIndex struct {
	byMessage map[string][]mailAttachmentRef
}

func newMailAttachmentIndex(files []fileEntry) *mailAttachmentIndex {
	idx := &mailAttachmentIndex{byMessage: map[string][]mailAttachmentRef{}}
	for _, f := range files {
		msgPath, name, ok := mailAttachmentParent(f.Path)
		if !ok {
			continue
		}
		idx.byMessage[msgPath] = append(idx.byMessage[msgPath], mailAttachmentRef{
			Path:     f.Path,
			Filename: name,
			Size:     f.Size,
		})
	}
	return idx
}

func mailAttachmentParent(snapshotPath string) (messagePath, filename string, ok bool) {
	p := strings.Trim(snapshotPath, "/")
	i := strings.Index(p, "/attachments/")
	if i < 0 {
		return "", "", false
	}
	parent := p[:i]
	if !strings.Contains(parent, "/mail/") {
		return "", "", false
	}
	if !strings.HasSuffix(parent, ".json") {
		parent += ".json"
	}
	filename = p[i+len("/attachments/"):]
	if filename == "" {
		return "", "", false
	}
	return parent, filename, true
}

func isMailMessageJSON(snapshotPath string) bool {
	p := strings.ToLower(snapshotPath)
	if !strings.Contains(p, "/mail/") || !strings.HasSuffix(p, ".json") {
		return false
	}
	if strings.Contains(p, "/attachments/") {
		return false
	}
	base := path.Base(p)
	switch base {
	case "folders.json", "_folder.json", "delta_state.json":
		return false
	}
	if strings.HasSuffix(base, ".removed.json") {
		return false
	}
	return true
}

func shouldSkipAsEmbeddedAttachment(snapshotPath string, mailMessages map[string]struct{}) bool {
	msgPath, _, ok := mailAttachmentParent(snapshotPath)
	if !ok {
		return false
	}
	_, ok = mailMessages[msgPath]
	return ok
}

func mailMessagePathsSet(files []fileEntry) map[string]struct{} {
	out := map[string]struct{}{}
	for _, f := range files {
		if isMailMessageJSON(f.Path) {
			out[f.Path] = struct{}{}
		}
	}
	return out
}

func mailEMLFilename(msg map[string]any) string {
	subject, _ := msg["subject"].(string)
	subject = sanitizeZipSegment(subject)
	if subject == "" {
		subject = "No subject"
	}
	datePrefix := ""
	for _, key := range []string{"receivedDateTime", "sentDateTime"} {
		if s, ok := msg[key].(string); ok && s != "" {
			if t, err := time.Parse(time.RFC3339, s); err == nil {
				datePrefix = t.UTC().Format("2006-01-02") + "_"
				break
			}
		}
	}
	if id, _ := msg["id"].(string); subject == "No subject" && id != "" {
		subject = guidSuffix(id)
	}
	return datePrefix + subject + ".eml"
}

func buildMailEML(msg map[string]any, attachments []mailAttachmentRef, loadAttachment func(ref mailAttachmentRef) (io.ReadCloser, error)) ([]byte, error) {
	var buf bytes.Buffer
	subject, _ := msg["subject"].(string)
	if err := writeMailEML(&buf, msg, subject, attachments, loadAttachment); err != nil {
		return nil, err
	}
	return buf.Bytes(), nil
}

func writeMailEML(w io.Writer, msg map[string]any, subject string, attachments []mailAttachmentRef, loadAttachment func(ref mailAttachmentRef) (io.ReadCloser, error)) error {
	var bodyBuf bytes.Buffer
	if len(attachments) == 0 {
		alt := multipart.NewWriter(&bodyBuf)
		boundary := alt.Boundary()
		if err := writeMailAlternativeParts(alt, msg); err != nil {
			return err
		}
		if err := alt.Close(); err != nil {
			return err
		}
		return writeMailHeaders(w, msg, subject, bodyBuf.Bytes(), "multipart/alternative; boundary="+boundary)
	}

	mixed := multipart.NewWriter(&bodyBuf)
	var altInner bytes.Buffer
	alt := multipart.NewWriter(&altInner)
	if err := writeMailAlternativeParts(alt, msg); err != nil {
		return err
	}
	if err := alt.Close(); err != nil {
		return err
	}
	altHeader := textproto.MIMEHeader{
		"Content-Type": {"multipart/alternative; boundary=" + alt.Boundary()},
	}
	altPart, err := mixed.CreatePart(altHeader)
	if err != nil {
		return err
	}
	if _, err := altPart.Write(altInner.Bytes()); err != nil {
		return err
	}

	for _, att := range attachments {
		if loadAttachment == nil {
			continue
		}
		reader, err := loadAttachment(att)
		if err != nil {
			return err
		}
		ct := mime.TypeByExtension(filepath.Ext(att.Filename))
		if ct == "" {
			ct = "application/octet-stream"
		}
		partHeader := textproto.MIMEHeader{
			"Content-Type":        {ct},
			"Content-Disposition": {fmt.Sprintf(`attachment; filename="%s"`, mime.QEncoding.Encode("utf-8", att.Filename))},
		}
		part, err := mixed.CreatePart(partHeader)
		if err != nil {
			reader.Close()
			return err
		}
		if _, err := io.Copy(part, reader); err != nil {
			reader.Close()
			return err
		}
		reader.Close()
	}
	if err := mixed.Close(); err != nil {
		return err
	}
	return writeMailHeaders(w, msg, subject, bodyBuf.Bytes(), mixed.FormDataContentType())
}

func writeMailAlternativeParts(mp *multipart.Writer, msg map[string]any) error {
	plain := bodyPreviewPlain(msg)
	html := bodyHTML(msg)
	if plain != "" {
		part, err := mp.CreatePart(textproto.MIMEHeader{"Content-Type": {"text/plain; charset=utf-8"}})
		if err != nil {
			return err
		}
		if _, err := part.Write([]byte(plain)); err != nil {
			return err
		}
	}
	if html != "" {
		part, err := mp.CreatePart(textproto.MIMEHeader{"Content-Type": {"text/html; charset=utf-8"}})
		if err != nil {
			return err
		}
		if _, err := part.Write([]byte(html)); err != nil {
			return err
		}
	}
	if plain == "" && html == "" {
		part, err := mp.CreatePart(textproto.MIMEHeader{"Content-Type": {"text/plain; charset=utf-8"}})
		if err != nil {
			return err
		}
		if _, err := part.Write([]byte("")); err != nil {
			return err
		}
	}
	return nil
}

func writeMailHeaders(w io.Writer, msg map[string]any, subject string, body []byte, contentType string) error {
	headers := textproto.MIMEHeader{}
	if from := formatMailAddress(msg["from"]); from != "" {
		headers.Set("From", from)
	}
	if to := formatRecipients(msg["toRecipients"]); to != "" {
		headers.Set("To", to)
	}
	if cc := formatRecipients(msg["ccRecipients"]); cc != "" {
		headers.Set("Cc", cc)
	}
	if bcc := formatRecipients(msg["bccRecipients"]); bcc != "" {
		headers.Set("Bcc", bcc)
	}
	if subject == "" {
		subject = "(No subject)"
	}
	headers.Set("Subject", subject)
	if msgID, _ := msg["internetMessageId"].(string); msgID != "" {
		headers.Set("Message-ID", msgID)
	}
	for _, key := range []string{"receivedDateTime", "sentDateTime"} {
		if s, ok := msg[key].(string); ok && s != "" {
			if t, err := time.Parse(time.RFC3339, s); err == nil {
				headers.Set("Date", t.Format(time.RFC1123Z))
				break
			}
		}
	}
	headers.Set("MIME-Version", "1.0")
	headers.Set("Content-Type", contentType)

	var out bytes.Buffer
	for key, vals := range headers {
		for _, v := range vals {
			fmt.Fprintf(&out, "%s: %s\r\n", key, v)
		}
	}
	out.WriteString("\r\n")
	if _, err := out.Write(body); err != nil {
		return err
	}
	_, err := w.Write(out.Bytes())
	return err
}

func bodyPreviewPlain(msg map[string]any) string {
	if preview, _ := msg["bodyPreview"].(string); strings.TrimSpace(preview) != "" {
		return preview
	}
	html := bodyHTML(msg)
	if html == "" {
		return ""
	}
	return stripHTMLTags(html)
}

func bodyHTML(msg map[string]any) string {
	body, _ := msg["body"].(map[string]any)
	if body == nil {
		return ""
	}
	content, _ := body["content"].(string)
	contentType, _ := body["contentType"].(string)
	if strings.EqualFold(contentType, "html") {
		return content
	}
	return ""
}

func stripHTMLTags(s string) string {
	var b strings.Builder
	inTag := false
	for _, r := range s {
		switch {
		case r == '<':
			inTag = true
		case r == '>':
			inTag = false
		case !inTag:
			b.WriteRune(r)
		}
	}
	return strings.TrimSpace(b.String())
}

func formatMailAddress(v any) string {
	m, _ := v.(map[string]any)
	if m == nil {
		return ""
	}
	email, _ := m["emailAddress"].(map[string]any)
	if email == nil {
		return ""
	}
	name, _ := email["name"].(string)
	addr, _ := email["address"].(string)
	if addr == "" {
		return ""
	}
	if name != "" {
		return (&mail.Address{Name: name, Address: addr}).String()
	}
	return addr
}

func formatRecipients(v any) string {
	items, _ := v.([]any)
	if len(items) == 0 {
		return ""
	}
	var parts []string
	for _, item := range items {
		if s := formatMailAddress(item); s != "" {
			parts = append(parts, s)
		}
	}
	return strings.Join(parts, ", ")
}

func parseMailMessageJSON(data []byte) (map[string]any, error) {
	var msg map[string]any
	if err := json.Unmarshal(data, &msg); err != nil {
		return nil, err
	}
	return msg, nil
}
