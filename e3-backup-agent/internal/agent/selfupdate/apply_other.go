//go:build !windows && !linux

package selfupdate

import "fmt"

// apply is unsupported on platforms other than Windows and Linux.
func apply(artifactPath string, spec Spec) error {
	return fmt.Errorf("selfupdate: remote update is not supported on this platform")
}
