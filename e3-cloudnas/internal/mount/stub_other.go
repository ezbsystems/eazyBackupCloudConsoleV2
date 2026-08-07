//go:build !windows || !cgo

package mount

import "fmt"

func NewWinFspMounter(_ string) (Mounter, error) {
	return nil, fmt.Errorf("WinFsp mount only supported on windows with cgo")
}

func WinFspAvailable() bool {
	return false
}
