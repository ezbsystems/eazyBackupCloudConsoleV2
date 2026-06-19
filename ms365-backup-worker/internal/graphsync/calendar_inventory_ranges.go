package graphsync

import (
	"time"
)

// InventoryRangeEnd is now+1 day UTC midnight (matches PHP CalendarInventoryRanges::rangeEnd).
func InventoryRangeEnd() time.Time {
	now := time.Now().UTC()
	return time.Date(now.Year(), now.Month(), now.Day(), 0, 0, 0, 0, time.UTC).Add(24 * time.Hour)
}

func inventoryRangeStart() time.Time {
	t, _ := time.Parse(time.RFC3339, CalendarInventoryStart)
	return t.UTC()
}

type timeRange struct {
	Start time.Time
	End   time.Time
}

func yearPartitions() []timeRange {
	return splitByYear(inventoryRangeStart(), InventoryRangeEnd())
}

func splitByYear(start, end time.Time) []timeRange {
	var ranges []timeRange
	cursor := start
	for cursor.Before(end) {
		next := cursor.AddDate(1, 0, 0)
		if next.After(end) {
			next = end
		}
		ranges = append(ranges, timeRange{Start: cursor, End: next})
		cursor = next
	}
	return ranges
}

func splitByMonth(start, end time.Time) []timeRange {
	var ranges []timeRange
	cursor := start
	for cursor.Before(end) {
		next := cursor.AddDate(0, 1, 0)
		if next.After(end) {
			next = end
		}
		ranges = append(ranges, timeRange{Start: cursor, End: next})
		cursor = next
	}
	return ranges
}

func splitByDay(start, end time.Time) []timeRange {
	var ranges []timeRange
	cursor := start
	for cursor.Before(end) {
		next := cursor.AddDate(0, 0, 1)
		if next.After(end) {
			next = end
		}
		ranges = append(ranges, timeRange{Start: cursor, End: next})
		cursor = next
	}
	return ranges
}

func splitByHour(start, end time.Time) []timeRange {
	var ranges []timeRange
	cursor := start
	for cursor.Before(end) {
		next := cursor.Add(time.Hour)
		if next.After(end) {
			next = end
		}
		ranges = append(ranges, timeRange{Start: cursor, End: next})
		cursor = next
	}
	return ranges
}

func formatGraphDateTime(t time.Time) string {
	return t.UTC().Format("2006-01-02T15:04:05Z")
}
