package archive

import (
	"bytes"
	"encoding/json"
	"fmt"
	"path"
	"strings"
	"time"

	"github.com/emersion/go-ical"
)

func isCalendarEventJSON(snapshotPath string) bool {
	p := strings.ToLower(snapshotPath)
	if !strings.Contains(p, "/calendar/") || !strings.HasSuffix(p, ".json") {
		return false
	}
	if strings.HasSuffix(p, "attachments.json") {
		return false
	}
	base := path.Base(p)
	switch base {
	case "_calendar.json", "folders.json", "delta_state.json":
		return false
	}
	if strings.HasSuffix(base, ".removed.json") {
		return false
	}
	return true
}

func isCalendarSeriesJSON(snapshotPath string) bool {
	return strings.Contains(strings.ToLower(snapshotPath), "/calendar/") &&
		strings.Contains(snapshotPath, "/series/") &&
		strings.HasSuffix(strings.ToLower(snapshotPath), ".json")
}

func shouldSkipCalendarExport(snapshotPath string) bool {
	return strings.HasSuffix(strings.ToLower(snapshotPath), "attachments.json")
}

func calendarICSFilename(ev map[string]any) string {
	subject, _ := ev["subject"].(string)
	subject = sanitizeZipSegment(subject)
	if subject == "" {
		if body, _ := ev["body"].(map[string]any); body != nil {
			if preview, _ := body["content"].(string); preview != "" {
				subject = sanitizeZipSegment(stripHTMLTags(preview))
			}
		}
	}
	if subject == "" {
		if id, _ := ev["id"].(string); id != "" {
			subject = guidSuffix(id)
		} else {
			subject = "Event"
		}
	}
	datePrefix := ""
	if start, ok := ev["start"].(map[string]any); ok {
		if s, _ := start["dateTime"].(string); s != "" {
			if t, err := time.Parse(time.RFC3339, s); err == nil {
				datePrefix = t.UTC().Format("2006-01-02") + "_"
			}
		} else if d, _ := start["date"].(string); d != "" {
			datePrefix = d + "_"
		}
	}
	return datePrefix + subject + ".ics"
}

func buildCalendarICS(ev map[string]any) ([]byte, error) {
	cal := ical.NewCalendar()
	cal.Props.SetText(ical.PropVersion, "2.0")
	cal.Props.SetText(ical.PropProductID, "-//EazyBackup//MS365 Archive//EN")

	event := ical.NewEvent()
	if uid, _ := ev["iCalUId"].(string); uid != "" {
		event.Props.SetText(ical.PropUID, uid)
	} else if id, _ := ev["id"].(string); id != "" {
		event.Props.SetText(ical.PropUID, id)
	}

	if subject, _ := ev["subject"].(string); subject != "" {
		event.Props.SetText(ical.PropSummary, subject)
	}

	if desc := calendarDescription(ev); desc != "" {
		event.Props.SetText(ical.PropDescription, desc)
	}

	if loc := calendarLocation(ev); loc != "" {
		event.Props.SetText(ical.PropLocation, loc)
	}

	if cancelled, _ := ev["isCancelled"].(bool); cancelled {
		event.Props.SetText(ical.PropStatus, "CANCELLED")
	}

	setCalendarDateProp(event, ical.PropDateTimeStart, ev["start"])
	setCalendarDateProp(event, ical.PropDateTimeEnd, ev["end"])

	if org := formatCalendarOrganizer(ev["organizer"]); org != "" {
		event.Props.SetText(ical.PropOrganizer, org)
	}
	for _, att := range calendarAttendees(ev["attendees"]) {
		event.Props.Add(&ical.Prop{
			Name:  ical.PropAttendee,
			Value: att,
		})
	}

	if rrule := graphRecurrenceToRRULE(ev["recurrence"]); rrule != "" {
		event.Props.SetText(ical.PropRecurrenceRule, rrule)
	}

	event.Props.SetDateTime(ical.PropDateTimeStamp, time.Now().UTC())

	cal.Children = append(cal.Children, event.Component)
	var buf bytes.Buffer
	if err := ical.NewEncoder(&buf).Encode(cal); err != nil {
		return nil, err
	}
	return buf.Bytes(), nil
}

func calendarDescription(ev map[string]any) string {
	if body, _ := ev["body"].(map[string]any); body != nil {
		if content, _ := body["content"].(string); strings.TrimSpace(content) != "" {
			ct, _ := body["contentType"].(string)
			if strings.EqualFold(ct, "html") {
				return stripHTMLTags(content)
			}
			return content
		}
	}
	return ""
}

func calendarLocation(ev map[string]any) string {
	if locs, ok := ev["locations"].([]any); ok && len(locs) > 0 {
		if m, _ := locs[0].(map[string]any); m != nil {
			if name, _ := m["displayName"].(string); name != "" {
				return name
			}
		}
	}
	return ""
}

func setCalendarDateProp(event *ical.Event, propName string, v any) {
	m, _ := v.(map[string]any)
	if m == nil {
		return
	}
	if date, _ := m["date"].(string); date != "" {
		event.Props.SetDate(propName, parseICalDate(date))
		return
	}
	if dateTime, _ := m["dateTime"].(string); dateTime != "" {
		if t, err := time.Parse(time.RFC3339, dateTime); err == nil {
			tz, _ := m["timeZone"].(string)
			if tz != "" {
				if loc, err := time.LoadLocation(tz); err == nil {
					t = t.In(loc)
				}
			}
			event.Props.SetDateTime(propName, t)
		}
	}
}

func parseICalDate(s string) time.Time {
	t, err := time.Parse("2006-01-02", s)
	if err != nil {
		return time.Time{}
	}
	return t
}

func formatCalendarOrganizer(v any) string {
	m, _ := v.(map[string]any)
	if m == nil {
		return ""
	}
	email, _ := m["emailAddress"].(map[string]any)
	if email == nil {
		return ""
	}
	addr, _ := email["address"].(string)
	name, _ := email["name"].(string)
	if addr == "" {
		return ""
	}
	if name != "" {
		return fmt.Sprintf("CN=%s:mailto:%s", name, addr)
	}
	return "mailto:" + addr
}

func calendarAttendees(v any) []string {
	items, _ := v.([]any)
	var out []string
	for _, item := range items {
		m, _ := item.(map[string]any)
		if m == nil {
			continue
		}
		email, _ := m["emailAddress"].(map[string]any)
		if email == nil {
			continue
		}
		addr, _ := email["address"].(string)
		if addr == "" {
			continue
		}
		name, _ := email["name"].(string)
		partstat := "NEEDS-ACTION"
		if status, _ := m["status"].(map[string]any); status != nil {
			if resp, _ := status["response"].(string); resp != "" {
				partstat = strings.ToUpper(strings.ReplaceAll(resp, " ", "-"))
			}
		}
		line := fmt.Sprintf("CN=%s;PARTSTAT=%s:mailto:%s", name, partstat, addr)
		if name == "" {
			line = fmt.Sprintf("PARTSTAT=%s:mailto:%s", partstat, addr)
		}
		out = append(out, line)
	}
	return out
}

func graphRecurrenceToRRULE(v any) string {
	pattern, ok := v.(map[string]any)
	if !ok || pattern == nil {
		return ""
	}
	recur, _ := pattern["pattern"].(map[string]any)
	if recur == nil {
		return ""
	}
	var parts []string
	if typ, _ := recur["type"].(string); typ != "" {
		freq := strings.ToUpper(typ)
		switch typ {
		case "daily":
			freq = "DAILY"
		case "weekly":
			freq = "WEEKLY"
		case "absoluteMonthly", "relativeMonthly":
			freq = "MONTHLY"
		case "absoluteYearly", "relativeYearly":
			freq = "YEARLY"
		default:
			freq = strings.ToUpper(typ)
		}
		parts = append(parts, "FREQ="+freq)
	}
	if interval, ok := recur["interval"].(float64); ok && interval > 1 {
		parts = append(parts, fmt.Sprintf("INTERVAL=%d", int(interval)))
	}
	if days, ok := recur["daysOfWeek"].([]any); ok && len(days) > 0 {
		var dow []string
		for _, d := range days {
			if s, _ := d.(string); s != "" {
				dow = append(dow, strings.ToUpper(s[:minInt(2, len(s))]))
			}
		}
		if len(dow) > 0 {
			parts = append(parts, "BYDAY="+strings.Join(dow, ","))
		}
	}
	if day, ok := recur["dayOfMonth"].(float64); ok {
		parts = append(parts, fmt.Sprintf("BYMONTHDAY=%d", int(day)))
	}
	if month, ok := recur["month"].(float64); ok {
		parts = append(parts, fmt.Sprintf("BYMONTH=%d", int(month)))
	}
	if rangeObj, ok := pattern["range"].(map[string]any); ok {
		if endDate, _ := rangeObj["endDate"].(string); endDate != "" {
			t := parseICalDate(endDate)
			if !t.IsZero() {
				parts = append(parts, "UNTIL="+t.Format("20060102"))
			}
		}
		if count, ok := rangeObj["numberOfOccurrences"].(float64); ok && count > 0 {
			parts = append(parts, fmt.Sprintf("COUNT=%d", int(count)))
		}
	}
	if len(parts) == 0 {
		return ""
	}
	return strings.Join(parts, ";")
}

func minInt(a, b int) int {
	if a < b {
		return a
	}
	return b
}

func parseCalendarEventJSON(data []byte) (map[string]any, error) {
	var ev map[string]any
	if err := json.Unmarshal(data, &ev); err != nil {
		return nil, err
	}
	if evType, _ := ev["type"].(string); evType == "occurrence" {
		return nil, fmt.Errorf("skip occurrence event")
	}
	return ev, nil
}
