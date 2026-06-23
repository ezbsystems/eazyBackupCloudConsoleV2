package telemetry

import (
	"bufio"
	"bytes"
	"os"
	"runtime"
	"strconv"
	"strings"
	"sync"
	"syscall"
	"time"
)

const bytesPerMiB = 1024 * 1024

// Snapshot is a point-in-time resource sample for heartbeat reporting.
type Snapshot struct {
	CPUPct        float64
	CPUCoresUsed  float64
	MemUsedMiB    int64
	MemTotalMiB   int64
	DiskFreeMiB   int64
	DiskTotalMiB  int64
	RunDirFreeMiB int64
	Goroutines    int
	SampledAt     time.Time
}

// Collector samples CPU, memory, and disk usage between heartbeats.
type Collector struct {
	runDir      string
	maxCPUCores float64

	mu            sync.Mutex
	lastCPUTime   int64
	lastCPUSample time.Time
	cpuSource     string // "cgroup" or "proc"
}

func NewCollector(runDir string, maxCPUCores float64) *Collector {
	if maxCPUCores <= 0 {
		maxCPUCores = 1
	}
	return &Collector{
		runDir:      runDir,
		maxCPUCores: maxCPUCores,
	}
}

func (c *Collector) Sample() Snapshot {
	now := time.Now()
	cpuPct, cpuCores := c.sampleCPU(now)
	memUsed, memTotal := c.sampleMemory()
	rootFree, rootTotal := statfsMiB("/")
	runFree, _ := statfsMiB(c.runDir)

	return Snapshot{
		CPUPct:        cpuPct,
		CPUCoresUsed:  cpuCores,
		MemUsedMiB:    memUsed,
		MemTotalMiB:   memTotal,
		DiskFreeMiB:   rootFree,
		DiskTotalMiB:  rootTotal,
		RunDirFreeMiB: runFree,
		Goroutines:    runtime.NumGoroutine(),
		SampledAt:     now,
	}
}

func (c *Collector) sampleCPU(now time.Time) (pct float64, coresUsed float64) {
	usage, ok := readCgroupCPUUsage()
	source := "cgroup"
	if !ok {
		usage, ok = readProcCPUUsage()
		source = "proc"
	}
	if !ok {
		return 0, 0
	}

	c.mu.Lock()
	defer c.mu.Unlock()

	if c.cpuSource == "" {
		c.cpuSource = source
	}
	if c.lastCPUSample.IsZero() {
		c.lastCPUTime = usage
		c.lastCPUSample = now
		return 0, 0
	}

	elapsed := now.Sub(c.lastCPUSample)
	if elapsed <= 0 {
		return 0, 0
	}
	delta := usage - c.lastCPUTime
	if delta < 0 {
		delta = 0
	}
	c.lastCPUTime = usage
	c.lastCPUSample = now

	elapsedUSec := elapsed.Microseconds()
	if elapsedUSec <= 0 {
		return 0, 0
	}

	if c.cpuSource == "cgroup" {
		coresUsed = float64(delta) / float64(elapsedUSec)
	} else {
		// /proc/stat jiffies: scale by assumed 100 Hz tick.
		coresUsed = float64(delta) / (float64(elapsedUSec) / 10000.0)
	}
	pct = (coresUsed / c.maxCPUCores) * 100
	if pct < 0 {
		pct = 0
	}
	if pct > 100 {
		pct = 100
	}
	return pct, coresUsed
}

func readCgroupCPUUsage() (int64, bool) {
	data, err := os.ReadFile("/sys/fs/cgroup/cpu.stat")
	if err != nil {
		return 0, false
	}
	for _, line := range strings.Split(string(data), "\n") {
		fields := strings.Fields(line)
		if len(fields) == 2 && fields[0] == "usage_usec" {
			v, err := strconv.ParseInt(fields[1], 10, 64)
			if err != nil {
				return 0, false
			}
			return v, true
		}
	}
	return 0, false
}

func readProcCPUUsage() (int64, bool) {
	data, err := os.ReadFile("/proc/stat")
	if err != nil {
		return 0, false
	}
	scanner := bufio.NewScanner(bytes.NewReader(data))
	for scanner.Scan() {
		line := scanner.Text()
		if !strings.HasPrefix(line, "cpu ") {
			continue
		}
		fields := strings.Fields(line)
		if len(fields) < 5 {
			return 0, false
		}
		var total int64
		for i := 1; i < len(fields); i++ {
			v, err := strconv.ParseInt(fields[i], 10, 64)
			if err != nil {
				return 0, false
			}
			total += v
		}
		idle, err := strconv.ParseInt(fields[4], 10, 64)
		if err != nil {
			return 0, false
		}
		return total - idle, true
	}
	return 0, false
}

func (c *Collector) sampleMemory() (usedMiB, totalMiB int64) {
	if used, total, ok := readCgroupMemory(); ok {
		return used / bytesPerMiB, total / bytesPerMiB
	}
	return readProcMeminfo()
}

func readCgroupMemory() (used, total int64, ok bool) {
	usedData, err := os.ReadFile("/sys/fs/cgroup/memory.current")
	if err != nil {
		return 0, 0, false
	}
	used, err = strconv.ParseInt(strings.TrimSpace(string(usedData)), 10, 64)
	if err != nil {
		return 0, 0, false
	}

	maxData, err := os.ReadFile("/sys/fs/cgroup/memory.max")
	if err != nil {
		return 0, 0, false
	}
	maxStr := strings.TrimSpace(string(maxData))
	if maxStr == "max" {
		return 0, 0, false
	}
	total, err = strconv.ParseInt(maxStr, 10, 64)
	if err != nil || total <= 0 {
		return 0, 0, false
	}
	return used, total, true
}

func readProcMeminfo() (usedMiB, totalMiB int64) {
	data, err := os.ReadFile("/proc/meminfo")
	if err != nil {
		return 0, 0
	}
	var totalKB, availKB int64
	scanner := bufio.NewScanner(bytes.NewReader(data))
	for scanner.Scan() {
		line := scanner.Text()
		switch {
		case strings.HasPrefix(line, "MemTotal:"):
			totalKB = parseMeminfoKB(line)
		case strings.HasPrefix(line, "MemAvailable:"):
			availKB = parseMeminfoKB(line)
		}
	}
	if totalKB <= 0 {
		return 0, 0
	}
	totalMiB = totalKB / 1024
	if availKB > 0 {
		usedMiB = (totalKB - availKB) / 1024
	}
	return usedMiB, totalMiB
}

func parseMeminfoKB(line string) int64 {
	fields := strings.Fields(line)
	if len(fields) < 2 {
		return 0
	}
	v, err := strconv.ParseInt(fields[1], 10, 64)
	if err != nil {
		return 0
	}
	return v
}

func statfsMiB(path string) (freeMiB, totalMiB int64) {
	if path == "" {
		return 0, 0
	}
	var st syscall.Statfs_t
	if err := syscall.Statfs(path, &st); err != nil {
		return 0, 0
	}
	total := int64(st.Blocks) * int64(st.Bsize)
	free := int64(st.Bavail) * int64(st.Bsize)
	return free / bytesPerMiB, total / bytesPerMiB
}
