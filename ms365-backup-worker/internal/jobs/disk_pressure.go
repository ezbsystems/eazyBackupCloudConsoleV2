package jobs

const defaultDiskHysteresisMiB = 512

// diskHeadroomInput captures the inputs for a single headroom evaluation.
type diskHeadroomInput struct {
	freeMiB            int64
	watermarkMiB       int64
	flushMarkMiB       int64
	reservedDiskMiB    int64
	updateReserveMiB   int64
	candidateDiskMiB   int64
	hysteresisMiB      int64
	cachePressureMiB   int64
}

func (in diskHeadroomInput) softThresholdMiB() int64 {
	calculated := in.watermarkMiB + in.reservedDiskMiB + in.updateReserveMiB + in.cachePressureMiB
	if calculated > in.flushMarkMiB {
		return calculated
	}
	return in.flushMarkMiB
}

func (in diskHeadroomInput) requiredMiB() int64 {
	return in.watermarkMiB + in.reservedDiskMiB + in.updateReserveMiB + in.candidateDiskMiB
}

func (in diskHeadroomInput) hasHeadroom() bool {
	if in.freeMiB >= 1<<29 {
		return true
	}
	return in.freeMiB >= in.requiredMiB()
}

func (in diskHeadroomInput) softPressure() bool {
	if in.freeMiB >= 1<<29 {
		return false
	}
	return in.freeMiB < in.softThresholdMiB()
}

func (in diskHeadroomInput) hardPressure() bool {
	if in.freeMiB >= 1<<29 {
		return false
	}
	return in.freeMiB < in.watermarkMiB
}

func (in diskHeadroomInput) canResumeFromPressure() bool {
	if in.freeMiB >= 1<<29 {
		return true
	}
	if in.reservedDiskMiB > 0 {
		return false
	}
	h := in.hysteresisMiB
	if h <= 0 {
		h = defaultDiskHysteresisMiB
	}
	return in.freeMiB >= in.softThresholdMiB()+h
}

// drainStep records one step in an ordered cooperative drain for tests.
type drainStep string

const (
	drainStepCheckpoint    drainStep = "checkpoint"
	drainStepCancel        drainStep = "cancel"
	drainStepWaitRunners   drainStep = "wait_runners"
	drainStepEvictCaches   drainStep = "evict_caches"
	drainStepReleaseClaims drainStep = "release_claims"
)

func validDrainOrder(steps []drainStep) bool {
	expected := []drainStep{
		drainStepCheckpoint,
		drainStepCancel,
		drainStepWaitRunners,
		drainStepEvictCaches,
		drainStepReleaseClaims,
	}
	if len(steps) != len(expected) {
		return false
	}
	for i := range expected {
		if steps[i] != expected[i] {
			return false
		}
	}
	return true
}
