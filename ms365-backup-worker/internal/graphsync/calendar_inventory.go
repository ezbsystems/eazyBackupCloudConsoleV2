package graphsync

import (
	"context"
	"encoding/json"
	"fmt"
	"strconv"
	"strings"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

var calendarPageSizeLadder = []string{"1000", "500", "250", "100", "50", "25"}

type calendarScanner struct {
	client        *graph.Client
	userID        string
	calendarID    string
	tenantID      string
	staging       *graphfs.OverlayBuilder
	log           RunLogger
	seenEventIDs  map[string]bool
	priorState    CalendarInventoryState
	inventoryPath string
	enrichQueue   []calendarEventMeta
	totalStored   int
}

type calendarEventMeta struct {
	ID             string
	Type           string
	HasAttachments bool
	LastModified   string
}

type calendarScanResult struct {
	state   CalendarInventoryState
	events  int
	removed int
}

func newCalendarScanner(client *graph.Client, opts CalendarSyncOptions, calID string, prior CalendarInventoryState) *calendarScanner {
	seen := map[string]bool{}
	if opts.GlobalSeen != nil {
		for k, v := range opts.GlobalSeen {
			seen[k] = v
		}
	}
	return &calendarScanner{
		client:        client,
		userID:        opts.UserID,
		calendarID:    calID,
		tenantID:      opts.AzureTenantID,
		staging:       opts.Staging,
		log:           opts.Log,
		seenEventIDs:  seen,
		priorState:    prior,
		inventoryPath: fmt.Sprintf("/users/%s/calendars/%s/events", opts.UserID, calID),
	}
}

func (s *calendarScanner) run(ctx context.Context) (*calendarScanResult, error) {
	s.logf("info", "Starting calendar backup calendar=%s", truncateID(s.calendarID))

	res := &calendarScanResult{state: s.priorState}

	// Tier 1: incremental watermark after proven-complete baseline
	if s.priorState.Complete && s.priorState.LastModifiedWatermark != "" {
		s.logf("info", "Calendar tier 1 incremental watermark=%s calendar=%s", s.priorState.LastModifiedWatermark, truncateID(s.calendarID))
		outcome := &graph.PaginationOutcome{}
		monitor := graph.ForCalendarNormalScan("calendar-incremental:"+truncateID(s.calendarID), s.graphLog())
		query := map[string]string{
			"$filter": LastModifiedWatermarkFilter(s.priorState.LastModifiedWatermark),
			"$top":    CalendarNormalPageSize,
			"$select": CalendarListSelect,
		}
		events, err := s.client.PaginateOpts(ctx, s.inventoryPath, query, &graph.PaginateOptions{
			Monitor:     monitor,
			Outcome:     outcome,
			Headers:     CalendarImmutableHeaders(),
			TrackDupIDs: true,
		})
		if err != nil {
			return nil, err
		}
		n, rm := s.storeEvents(events)
		res.events += n
		res.removed += rm
		if outcome.CompletedNaturally {
			res.state.Complete = true
			res.state.ScanMode = "incremental"
			res.state.LastSuccessfulTier = 1
			res.state.LastModifiedWatermark = maxLastModified(s.priorState.LastModifiedWatermark, events)
			s.logf("info", "Calendar tier 1 complete events=%d calendar=%s", res.events, truncateID(s.calendarID))
			return res, nil
		}
		s.logf("warning", "Calendar tier 1 wedge; escalating to tier 2 calendar=%s", truncateID(s.calendarID))
	}

	// Tier 2: page-size retry ladder on unfiltered list
	for _, pageSize := range calendarPageSizeLadder {
		s.logf("info", "Calendar tier 2 normal pass top=%s calendar=%s", pageSize, truncateID(s.calendarID))
		outcome := &graph.PaginationOutcome{}
		monitor := graph.ForCalendarNormalScan("calendar-normal:"+truncateID(s.calendarID), s.graphLog())
		query := map[string]string{
			"$top":    pageSize,
			"$select": CalendarListSelect,
		}
		events, err := s.client.PaginateOpts(ctx, s.inventoryPath, query, &graph.PaginateOptions{
			Monitor:     monitor,
			Outcome:     outcome,
			Headers:     CalendarImmutableHeaders(),
			TrackDupIDs: true,
		})
		if err != nil {
			return nil, err
		}
		n, rm := s.storeEvents(events)
		res.events += n
		res.removed += rm
		if outcome.CompletedNaturally {
			res.state.Complete = true
			if pageSize == calendarPageSizeLadder[0] {
				res.state.ScanMode = "normal"
			} else {
				res.state.ScanMode = "page_size_retry"
			}
			res.state.LastSuccessfulTier = 2
			res.state.WinningPageSize = pageSize
			res.state.LastModifiedWatermark = maxLastModified(res.state.LastModifiedWatermark, events)
			s.logf("info", "Calendar tier 2 complete top=%s events=%d calendar=%s", pageSize, res.events, truncateID(s.calendarID))
			return res, nil
		}
		if outcome.StoppedOnDuplicatePage {
			s.logf("warning", "Calendar tier 2 wedge at top=%s; trying smaller page size calendar=%s", pageSize, truncateID(s.calendarID))
			continue
		}
	}

	// Tier 3: createdDateTime partition fallback
	s.logf("info", "Calendar tier 3 partition fallback calendar=%s", truncateID(s.calendarID))
	if err := s.scanPartitions(ctx, yearPartitions()); err != nil {
		return nil, err
	}
	res.events = s.totalStored
	res.state.Complete = true
	res.state.ScanMode = "partition"
	res.state.LastSuccessfulTier = 3
	for _, m := range s.enrichQueue {
		res.state.LastModifiedWatermark = maxLastModified(res.state.LastModifiedWatermark, []map[string]any{
			{"lastModifiedDateTime": m.LastModified},
		})
	}
	s.logf("info", "Calendar tier 3 complete events=%d calendar=%s", res.events, truncateID(s.calendarID))
	return res, nil
}

func (s *calendarScanner) scanPartitions(ctx context.Context, ranges []timeRange) error {
	for _, r := range ranges {
		if err := s.scanPartitionWindow(ctx, r); err != nil {
			if _, ok := err.(*graph.GraphPaginationError); ok {
				sub := s.subdivide(r)
				if len(sub) == 0 {
					return &CalendarInventoryIncompleteError{CalendarID: s.calendarID, Reason: "hour partition still wedged"}
				}
				if err := s.scanPartitions(ctx, sub); err != nil {
					return err
				}
				continue
			}
			return err
		}
	}
	return nil
}

func (s *calendarScanner) subdivide(r timeRange) []timeRange {
	span := r.End.Sub(r.Start)
	switch {
	case span > 365*24*time.Hour:
		return splitByMonth(r.Start, r.End)
	case span > 31*24*time.Hour:
		return splitByDay(r.Start, r.End)
	case span > 24*time.Hour:
		return splitByHour(r.Start, r.End)
	default:
		return nil
	}
}

func (s *calendarScanner) scanPartitionWindow(ctx context.Context, r timeRange) error {
	outcome := &graph.PaginationOutcome{}
	monitor := graph.ForCalendarPartitionScan(
		fmt.Sprintf("calendar-partition:%s:%s", truncateID(s.calendarID), formatGraphDateTime(r.Start)),
		s.graphLog(),
	)
	query := map[string]string{
		"$filter":  CreatedDateTimeFilter(formatGraphDateTime(r.Start), formatGraphDateTime(r.End)),
		"$orderby": "createdDateTime",
		"$top":     CalendarPartitionPageSize,
		"$select":  CalendarListSelect,
	}
	events, err := s.client.PaginateOpts(ctx, s.inventoryPath, query, &graph.PaginateOptions{
		Monitor:     monitor,
		Outcome:     outcome,
		Headers:     CalendarImmutableHeaders(),
		TrackDupIDs: true,
	})
	if err != nil {
		return err
	}
	s.storeEvents(events)
	if !outcome.CompletedNaturally {
		return &graph.GraphPaginationError{Message: "partition scan did not complete naturally", Context: monitor.Context}
	}
	return nil
}

func (s *calendarScanner) storeEvents(events []map[string]any) (stored, removed int) {
	for _, ev := range events {
		if removedObj, _ := ev["@removed"].(map[string]any); removedObj != nil {
			id, _ := ev["id"].(string)
			if id != "" {
				path := s.eventPath(id)
				s.staging.Remove(path)
				removed++
			}
			continue
		}
		evType, _ := ev["type"].(string)
		if evType == "occurrence" {
			continue
		}
		id, _ := ev["id"].(string)
		if id == "" {
			continue
		}
		if s.seenEventIDs[id] {
			continue
		}
		s.seenEventIDs[id] = true
		body, _ := json.Marshal(ev)
		path := s.eventPath(id)
		s.staging.PutJSON(path, body, graphfsModTime(ev["lastModifiedDateTime"]))
		hasAtt, _ := ev["hasAttachments"].(bool)
		s.enrichQueue = append(s.enrichQueue, calendarEventMeta{
			ID: id, Type: evType, HasAttachments: hasAtt,
			LastModified: stringFromAny(ev["lastModifiedDateTime"]),
		})
		stored++
	}
	s.totalStored += stored
	return stored, removed
}

func (s *calendarScanner) eventPath(eventID string) string {
	return fmt.Sprintf("%s/users/%s/calendar/%s/%s.json", s.tenantID, s.userID, safeID(s.calendarID), safeID(eventID))
}

func (s *calendarScanner) logf(level, format string, args ...any) {
	if s.log != nil {
		s.log(level, fmt.Sprintf(format, args...))
	}
}

func (s *calendarScanner) graphLog() graph.PageLogFunc {
	if s.log == nil {
		return nil
	}
	return func(level, message string) {
		s.log(level, message)
	}
}

func truncateID(id string) string {
	id = strings.TrimSpace(id)
	if len(id) <= 12 {
		return id
	}
	return id[:8] + "…"
}

// enrichCalendarEvents fetches series masters and attachments after inventory.
func enrichCalendarEvents(ctx context.Context, client *graph.Client, opts CalendarSyncOptions, calID string, queue []calendarEventMeta) error {
	if client == nil || opts.Staging == nil || len(queue) == 0 {
		return nil
	}
	base := fmt.Sprintf("%s/users/%s/calendar/%s", opts.AzureTenantID, opts.UserID, safeID(calID))
	for _, meta := range queue {
		if meta.Type == "seriesMaster" {
			seriesPath := fmt.Sprintf("/users/%s/events/%s", opts.UserID, meta.ID)
			series, err := client.GetJSONWithHeaders(ctx, seriesPath, map[string]string{
				"$select": CalendarSeriesSelect,
				"$expand": "exceptionOccurrences",
			}, CalendarImmutableHeaders())
			if err == nil {
				body, _ := json.Marshal(series)
				seriesFile := fmt.Sprintf("%s/series/%s.json", base, safeID(meta.ID))
				opts.Staging.PutJSON(seriesFile, body, graphfsModTime(series["lastModifiedDateTime"]))
			}
		}
		if meta.HasAttachments {
			atts, err := client.Paginate(ctx, fmt.Sprintf("/users/%s/events/%s/attachments", opts.UserID, meta.ID), map[string]string{"$top": "50"})
			if err == nil && len(atts) > 0 {
				body, _ := json.Marshal(atts)
				attPath := fmt.Sprintf("%s/%s/attachments.json", base, safeID(meta.ID))
				opts.Staging.PutJSON(attPath, body, graphfsModTime(meta.LastModified))
			}
		}
	}
	return nil
}

func stringFromAny(v any) string {
	s, _ := v.(string)
	return s
}

func parsePageSize(s string) int {
	n, _ := strconv.Atoi(s)
	if n <= 0 {
		return 100
	}
	return n
}
