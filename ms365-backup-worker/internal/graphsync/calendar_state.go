package graphsync

import (
	"encoding/json"
	"fmt"
	"strings"
)

// CalendarInventoryState is persisted per calendar in ms365_delta_state (workload=calendar).
type CalendarInventoryState struct {
	Complete              bool   `json:"complete"`
	ScanMode              string `json:"scanMode,omitempty"`
	LastSuccessfulTier    int    `json:"lastSuccessfulTier,omitempty"`
	LastModifiedWatermark string `json:"lastModifiedWatermark,omitempty"`
	WinningPageSize       string `json:"winningPageSize,omitempty"`
}

func calendarStateKey(calendarID string) string {
	return "cal:" + calendarID + ":inventory"
}

func parseCalendarInventoryState(raw string) CalendarInventoryState {
	raw = strings.TrimSpace(raw)
	if raw == "" {
		return CalendarInventoryState{}
	}
	var st CalendarInventoryState
	_ = json.Unmarshal([]byte(raw), &st)
	return st
}

func encodeCalendarInventoryState(st CalendarInventoryState) string {
	b, _ := json.Marshal(st)
	return string(b)
}

func maxLastModified(current string, events []map[string]any) string {
	max := current
	for _, ev := range events {
		lm, _ := ev["lastModifiedDateTime"].(string)
		if lm == "" {
			continue
		}
		if max == "" || lm > max {
			max = lm
		}
	}
	return max
}

func calendarInventoryStateFromMap(states map[string]string, calendarID string) CalendarInventoryState {
	if states == nil {
		return CalendarInventoryState{}
	}
	return parseCalendarInventoryState(states[calendarStateKey(calendarID)])
}

func mergeCalendarStates(out map[string]string, calendarID string, st CalendarInventoryState) {
	if out == nil {
		return
	}
	out[calendarStateKey(calendarID)] = encodeCalendarInventoryState(st)
}

// CalendarInventoryIncompleteError indicates a calendar could not complete inventory.
type CalendarInventoryIncompleteError struct {
	CalendarID string
	Reason     string
}

func (e *CalendarInventoryIncompleteError) Error() string {
	return fmt.Sprintf("calendar inventory incomplete for %s: %s", e.CalendarID, e.Reason)
}
