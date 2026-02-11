package agent

import "testing"

func TestShouldTreatSectorNotFoundAsEOF(t *testing.T) {
	const mb = int64(1024 * 1024)
	size := int64(100 * mb)

	if shouldTreatSectorNotFoundAsEOF(size, 91*mb) {
		t.Fatalf("expected false when requestedEnd is before tail tolerance")
	}
	if !shouldTreatSectorNotFoundAsEOF(size, 92*mb) {
		t.Fatalf("expected true at tail tolerance boundary")
	}
	if !shouldTreatSectorNotFoundAsEOF(size, size) {
		t.Fatalf("expected true at end of device")
	}
}

func TestStrictReadErrorsOverride(t *testing.T) {
	reset := setStrictReadErrorsOverride(nil)
	defer reset()

	t.Setenv("AGENT_DISK_IMAGE_STRICT_READ_ERRORS", "false")
	if isStrictReadErrors() {
		t.Fatalf("expected strict mode disabled by default")
	}

	t.Setenv("AGENT_DISK_IMAGE_STRICT_READ_ERRORS", "true")
	if !isStrictReadErrors() {
		t.Fatalf("expected strict mode enabled from env")
	}

	enabled := true
	resetEnabled := setStrictReadErrorsOverride(&enabled)
	if !isStrictReadErrors() {
		t.Fatalf("expected strict mode enabled by override")
	}

	disabled := false
	resetDisabled := setStrictReadErrorsOverride(&disabled)
	if isStrictReadErrors() {
		t.Fatalf("expected strict mode disabled by override")
	}

	resetDisabled()
	if !isStrictReadErrors() {
		t.Fatalf("expected strict mode restored after reset")
	}

	resetEnabled()
	if !isStrictReadErrors() {
		t.Fatalf("expected env strict mode after clearing overrides")
	}
}
