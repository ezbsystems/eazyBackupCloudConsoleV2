//go:build !windows

package agent

import (
	"fmt"
	"time"
)

func setSystemTimeUTC(_ time.Time) error {
	return fmt.Errorf("system clock sync is only implemented for windows recovery builds")
}
