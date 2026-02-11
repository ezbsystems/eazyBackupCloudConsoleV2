//go:build windows
// +build windows

package agent

import (
	"io"
	"syscall"
	"testing"
)

type flakyReadSeeker struct {
	data      []byte
	pos       int64
	failAfter int64
}

func (f *flakyReadSeeker) Read(p []byte) (int, error) {
	if f.pos >= int64(len(f.data)) {
		return 0, io.EOF
	}

	n := 0
	for n < len(p) && f.pos < int64(len(f.data)) {
		if f.failAfter >= 0 && f.pos >= f.failAfter {
			if n == 0 {
				return 0, syscall.Errno(27)
			}
			return n, syscall.Errno(27)
		}
		p[n] = f.data[f.pos]
		n++
		f.pos++
	}

	if n == len(p) {
		return n, nil
	}
	if f.pos >= int64(len(f.data)) {
		return n, io.EOF
	}
	return n, nil
}

func (f *flakyReadSeeker) Seek(offset int64, whence int) (int64, error) {
	var next int64
	switch whence {
	case io.SeekStart:
		next = offset
	case io.SeekCurrent:
		next = f.pos + offset
	case io.SeekEnd:
		next = int64(len(f.data)) + offset
	default:
		return f.pos, syscall.EINVAL
	}
	if next < 0 {
		return f.pos, syscall.EINVAL
	}
	f.pos = next
	return f.pos, nil
}

func TestReadAtSectorNotFound_NonStrictZeroFills(t *testing.T) {
	disabled := false
	reset := setStrictReadErrorsOverride(&disabled)
	defer reset()

	reader := &flakyReadSeeker{
		data:      []byte{1, 2, 3, 4, 5, 6, 7, 8, 9},
		failAfter: 4,
	}
	buf := make([]byte, 8)

	var warnings []diskReadWarning
	err := readAt(reader, `\\.\PhysicalDrive0`, "range-primary", 0, buf, 1024, true, func(details diskReadWarning) {
		warnings = append(warnings, details)
	})
	if err != nil {
		t.Fatalf("expected nil error in non-strict mode, got %v", err)
	}
	want := []byte{1, 2, 3, 4, 0, 0, 0, 0}
	for i := range want {
		if buf[i] != want[i] {
			t.Fatalf("unexpected buf[%d]=%d want=%d", i, buf[i], want[i])
		}
	}
	if len(warnings) != 1 {
		t.Fatalf("expected 1 warning, got %d", len(warnings))
	}
	if warnings[0].NearTail {
		t.Fatalf("expected non-tail warning")
	}
	if warnings[0].ZeroFilledBytes != 4 || warnings[0].ReadBytes != 4 {
		t.Fatalf("unexpected warning payload: %#v", warnings[0])
	}
}

func TestReadAtSectorNotFound_StrictFails(t *testing.T) {
	enabled := true
	reset := setStrictReadErrorsOverride(&enabled)
	defer reset()

	reader := &flakyReadSeeker{
		data:      []byte{1, 2, 3, 4, 5, 6},
		failAfter: 2,
	}
	buf := make([]byte, 6)
	err := readAt(reader, `\\.\PhysicalDrive0`, "range-primary", 0, buf, 1024, true, nil)
	if err == nil {
		t.Fatalf("expected strict mode to return error")
	}
	if !isWindowsSectorNotFound(err) {
		t.Fatalf("expected sector-not-found error, got %v", err)
	}
}

func TestReadAtSectorNotFound_NearTailReturnsEOF(t *testing.T) {
	disabled := false
	reset := setStrictReadErrorsOverride(&disabled)
	defer reset()

	reader := &flakyReadSeeker{
		data:      []byte{1, 2, 3, 4, 5, 6, 7, 8},
		failAfter: 8,
	}
	buf := make([]byte, 8)

	var warning diskReadWarning
	calls := 0
	err := readAt(reader, `\\.\PhysicalDrive0`, "range-primary", 8, buf, 16, true, func(details diskReadWarning) {
		calls++
		warning = details
	})
	if err != io.EOF {
		t.Fatalf("expected io.EOF near tail, got %v", err)
	}
	if calls != 1 {
		t.Fatalf("expected 1 warning callback, got %d", calls)
	}
	if !warning.NearTail {
		t.Fatalf("expected warning to mark near tail")
	}
}
