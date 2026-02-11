//go:build windows

package agent

import (
	"time"
	"unsafe"

	"golang.org/x/sys/windows"
)

func setSystemTimeUTC(t time.Time) error {
	tt := t.UTC()
	st := &windows.Systemtime{
		Year:         uint16(tt.Year()),
		Month:        uint16(tt.Month()),
		DayOfWeek:    uint16(tt.Weekday()),
		Day:          uint16(tt.Day()),
		Hour:         uint16(tt.Hour()),
		Minute:       uint16(tt.Minute()),
		Second:       uint16(tt.Second()),
		Milliseconds: uint16(tt.Nanosecond() / int(time.Millisecond)),
	}
	return callSetSystemTime(st)
}

var (
	kernel32Time        = windows.NewLazySystemDLL("kernel32.dll")
	procSetSystemTime   = kernel32Time.NewProc("SetSystemTime")
)

func callSetSystemTime(st *windows.Systemtime) error {
	if st == nil {
		return windows.ERROR_INVALID_PARAMETER
	}
	ret, _, err := procSetSystemTime.Call(uintptr(unsafe.Pointer(st)))
	if ret == 0 {
		if err == nil || err == windows.ERROR_SUCCESS {
			return windows.GetLastError()
		}
		return err
	}
	return nil
}
