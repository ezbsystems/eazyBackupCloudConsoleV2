package graph

import (
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"net/url"
	"strings"
)

const (
	DefaultMaxPages              = 500
	DefaultMaxEmptyPagesWithNext = 3
	graphDefectURL               = "https://github.com/microsoftgraph/msgraph-sdk-dotnet/issues/3070"
)

// PaginationCapMode controls behavior when max pages is exceeded.
type PaginationCapMode int

const (
	CapFail PaginationCapMode = iota
	CapWarnContinue
)

// DuplicatePageMode controls behavior when a page returns only duplicate item IDs.
type DuplicatePageMode int

const (
	DuplicatePageStrict DuplicatePageMode = iota
	DuplicatePageDetectOnly
)

// PageLogFunc receives pagination log lines (level, formatted message).
type PageLogFunc func(level, message string)

// PaginationMonitor controls logging and safety limits for Graph pagination.
type PaginationMonitor struct {
	Context               string
	MaxPages              int
	MaxEmptyPagesWithNext int
	DuplicatePageMode     DuplicatePageMode
	CapMode               PaginationCapMode
	Log                   PageLogFunc
}

// PaginationOutcome records how a paginate session ended.
type PaginationOutcome struct {
	CompletedNaturally     bool
	StoppedOnDuplicatePage bool
	CapReached             bool
	Pages                  int
	TotalItems             int
}

func NewPaginationMonitor(context string, mode DuplicatePageMode, log PageLogFunc) *PaginationMonitor {
	return &PaginationMonitor{
		Context:               context,
		MaxPages:              DefaultMaxPages,
		MaxEmptyPagesWithNext: DefaultMaxEmptyPagesWithNext,
		DuplicatePageMode:     mode,
		Log:                   log,
	}
}

func ForBackupPagination(context string, log PageLogFunc) *PaginationMonitor {
	return NewPaginationMonitor(context, DuplicatePageStrict, log)
}

func ForCalendarNormalScan(context string, log PageLogFunc) *PaginationMonitor {
	return NewPaginationMonitor(context, DuplicatePageDetectOnly, log)
}

func ForCalendarPartitionScan(context string, log PageLogFunc) *PaginationMonitor {
	return NewPaginationMonitor(context, DuplicatePageStrict, log)
}

func (m *PaginationMonitor) logf(level, format string, args ...any) {
	if m == nil || m.Log == nil {
		return
	}
	msg := fmt.Sprintf(format, args...)
	if m.Context != "" {
		msg = fmt.Sprintf("[%s] %s", m.Context, msg)
	}
	m.Log(level, msg)
}

func extractItemID(item map[string]any) string {
	if item == nil {
		return ""
	}
	if id, ok := item["id"].(string); ok {
		return id
	}
	return ""
}

func truncateLink(raw string) string {
	raw = strings.TrimSpace(raw)
	if len(raw) <= 120 {
		return raw
	}
	return raw[:60] + "…" + raw[len(raw)-40:]
}

func extractSkipToken(nextLink string) string {
	nextLink = strings.TrimSpace(nextLink)
	if nextLink == "" {
		return ""
	}
	u, err := url.Parse(nextLink)
	if err != nil {
		return ""
	}
	return u.Query().Get("$skiptoken")
}

func linkHash(raw string) string {
	sum := sha256.Sum256([]byte(raw))
	return hex.EncodeToString(sum[:])
}

// GraphPaginationError indicates a pagination loop, safety cap, or wedge.
type GraphPaginationError struct {
	Message string
	Context string
}

func (e *GraphPaginationError) Error() string {
	if e.Context != "" {
		return e.Message + " [" + e.Context + "]"
	}
	return e.Message
}

// DeltaResetError indicates the delta token expired and a full resync is required.
type DeltaResetError struct {
	Message    string
	StatusCode int
}

func (e *DeltaResetError) Error() string {
	return e.Message
}

// IsDeltaResetError reports whether err is a Graph delta token reset (410 / syncStateNotFound).
func IsDeltaResetError(err error) bool {
	if err == nil {
		return false
	}
	if _, ok := err.(*DeltaResetError); ok {
		return true
	}
	msg := strings.ToLower(err.Error())
	return strings.Contains(msg, "graph 410") ||
		strings.Contains(msg, "syncstatenotfound") ||
		strings.Contains(msg, "resyncrequired") ||
		strings.Contains(msg, "fullsyncrequired")
}

type pageFetchResult struct {
	items     []map[string]any
	nextLink  string
	deltaLink string
}

type paginationSession struct {
	monitor               *PaginationMonitor
	outcome               *PaginationOutcome
	seenNextLinks         map[string]bool
	seenItemIDs           map[string]bool
	emptyPagesWithNext    int
	page                  int
	totalItems            int
	stoppedOnDuplicate    bool
	trackDuplicateContent bool
}

func newPaginationSession(monitor *PaginationMonitor, outcome *PaginationOutcome, trackDuplicates bool) *paginationSession {
	s := &paginationSession{
		monitor:               monitor,
		outcome:               outcome,
		seenNextLinks:         map[string]bool{},
		seenItemIDs:           map[string]bool{},
		trackDuplicateContent: trackDuplicates,
	}
	if monitor != nil {
		monitor.logf("info", "Graph pagination started (max_pages=%d)", monitor.MaxPages)
	}
	return s
}

func (s *paginationSession) processPage(items []map[string]any, nextLink string) ([]map[string]any, error) {
	s.page++
	maxPages := DefaultMaxPages
	dupMode := DuplicatePageStrict
	if s.monitor != nil {
		if s.monitor.MaxPages > 0 {
			maxPages = s.monitor.MaxPages
		}
		dupMode = s.monitor.DuplicatePageMode
	}
	if s.page > maxPages {
		if s.monitor != nil && s.monitor.CapMode == CapWarnContinue {
			s.log("warning", "Graph pagination safety cap reached (page=%d max=%d total_items=%d); continuing with partial sync",
				s.page, maxPages, s.totalItems)
			if s.outcome != nil {
				s.outcome.CapReached = true
			}
			return nil, nil
		}
		s.log("error", "Graph pagination safety cap reached (page=%d max=%d total_items=%d); see %s",
			s.page, maxPages, s.totalItems, graphDefectURL)
		return nil, &GraphPaginationError{
			Message: fmt.Sprintf("Graph pagination safety cap reached (%d pages); see %s", maxPages, graphDefectURL),
			Context: s.context(),
		}
	}

	itemCount := len(items)
	newOnPage := 0
	var yielded []map[string]any
	for _, item := range items {
		id := extractItemID(item)
		if id != "" && s.seenItemIDs[id] {
			continue
		}
		if id != "" {
			s.seenItemIDs[id] = true
		}
		newOnPage++
		s.totalItems++
		yielded = append(yielded, item)
	}

	s.log("info", "Graph pagination page=%d items_on_page=%d new_items_on_page=%d total_items=%d has_next_link=%v skip_token=%q next_link=%q",
		s.page, itemCount, newOnPage, s.totalItems, nextLink != "", extractSkipToken(nextLink), truncateLink(nextLink))

	if s.trackDuplicateContent && itemCount > 0 && newOnPage == 0 && nextLink != "" {
		if dupMode == DuplicatePageDetectOnly {
			s.stoppedOnDuplicate = true
			s.log("warning", "Graph pagination stopped: duplicate-only page (known Graph defect) page=%d total_items=%d",
				s.page, s.totalItems)
			return yielded, nil
		}
		s.log("error", "Graph pagination loop: duplicate-only page page=%d next_link=%q", s.page, truncateLink(nextLink))
		return nil, &GraphPaginationError{
			Message: "Graph pagination loop detected: page contained only previously seen items",
			Context: s.context(),
		}
	}

	if itemCount == 0 && nextLink != "" {
		s.emptyPagesWithNext++
		maxEmpty := DefaultMaxEmptyPagesWithNext
		if s.monitor != nil && s.monitor.MaxEmptyPagesWithNext > 0 {
			maxEmpty = s.monitor.MaxEmptyPagesWithNext
		}
		if s.emptyPagesWithNext >= maxEmpty {
			s.log("error", "Graph pagination loop: %d consecutive empty pages with nextLink", s.emptyPagesWithNext)
			return nil, &GraphPaginationError{
				Message: fmt.Sprintf("Graph pagination loop suspected: %d consecutive empty page(s) still have @odata.nextLink", s.emptyPagesWithNext),
				Context: s.context(),
			}
		}
	} else {
		s.emptyPagesWithNext = 0
	}

	return yielded, nil
}

func (s *paginationSession) checkNextLink(nextURL string, isFirst bool) error {
	if isFirst || nextURL == "" {
		return nil
	}
	key := linkHash(nextURL)
	if s.seenNextLinks[key] {
		s.log("error", "Graph pagination loop: identical @odata.nextLink URL repeated")
		return &GraphPaginationError{
			Message: "Graph pagination loop detected: identical @odata.nextLink URL repeated; see " + graphDefectURL,
			Context: s.context(),
		}
	}
	s.seenNextLinks[key] = true
	return nil
}

func (s *paginationSession) finish(naturalEOF bool) {
	if s.monitor != nil {
		s.monitor.logf("info", "[%s] Graph pagination completed pages=%d total_items=%d stopped_on_duplicate=%v",
			s.context(), s.page, s.totalItems, s.stoppedOnDuplicate)
	}
	if s.outcome != nil {
		s.outcome.Pages = s.page
		s.outcome.TotalItems = s.totalItems
		s.outcome.StoppedOnDuplicatePage = s.stoppedOnDuplicate
		s.outcome.CompletedNaturally = !s.stoppedOnDuplicate && naturalEOF
	}
}

func (s *paginationSession) log(level, format string, args ...any) {
	if s.monitor != nil {
		s.monitor.logf(level, format, args...)
	}
}

func (s *paginationSession) context() string {
	if s.monitor != nil {
		return s.monitor.Context
	}
	return ""
}

func (s *paginationSession) stopped() bool {
	return s.stoppedOnDuplicate
}
