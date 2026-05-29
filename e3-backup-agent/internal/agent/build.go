package agent

import "strings"

// buildVersion/buildCommit are populated at startup from the compiled-in
// values that the linker injects into package main via
// `-X main.version`/`-X main.commit` (see Makefile LDFLAGS). main is expected
// to forward them here with SetBuildInfo so the rest of the agent has a single
// authoritative version source for reporting to the server and for post-update
// verification.
var (
	buildVersion string
	buildCommit  string
)

// SetBuildInfo records the compiled-in version/commit. Empty or "dev" values
// are treated as "unknown" and ignored so a plain `go build` (no ldflags) does
// not advertise a misleading version.
func SetBuildInfo(version, commit string) {
	v := strings.TrimSpace(version)
	if v != "" && v != "dev" {
		buildVersion = v
	}
	c := strings.TrimSpace(commit)
	if c != "" && c != "unknown" {
		buildCommit = c
	}
}

// BuildVersion returns the compiled-in agent version, or "" if it was not set
// at link time.
func BuildVersion() string { return buildVersion }

// BuildCommit returns the compiled-in commit, or "" if it was not set at link
// time.
func BuildCommit() string { return buildCommit }
