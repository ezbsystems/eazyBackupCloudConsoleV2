//go:build windows
// +build windows

package agent

import (
	"fmt"
	"log"
	"strings"
	"syscall"
	"unsafe"

	"golang.org/x/sys/windows/registry"
)

const (
	driveUnknown   = 0
	driveNoRootDir = 1
	driveRemovable = 2
	driveFixed     = 3
	driveRemote    = 4
	driveCDROM     = 5
	driveRAMDisk   = 6
)

var (
	kernel32                 = syscall.NewLazyDLL("kernel32.dll")
	mpr                      = syscall.NewLazyDLL("mpr.dll")
	procGetLogicalDrives     = kernel32.NewProc("GetLogicalDrives")
	procGetDriveTypeW        = kernel32.NewProc("GetDriveTypeW")
	procGetVolumeInformation = kernel32.NewProc("GetVolumeInformationW")
	procGetDiskFreeSpace     = kernel32.NewProc("GetDiskFreeSpaceW")
	procGetDiskFreeSpaceEx   = kernel32.NewProc("GetDiskFreeSpaceExW")
	procWNetGetConnectionW   = mpr.NewProc("WNetGetConnectionW")
)

func enumerateVolumes() ([]VolumeInfo, error) {
	mask, _, callErr := procGetLogicalDrives.Call()
	if mask == 0 {
		return nil, fmt.Errorf("GetLogicalDrives failed: %v", callErr)
	}

	seen := make(map[string]bool) // track drive letters already added
	var vols []VolumeInfo
	for i := 0; i < 26; i++ {
		if mask&(1<<uint(i)) == 0 {
			continue
		}
		drive := fmt.Sprintf("%c:\\", 'A'+i)
		driveLetter := fmt.Sprintf("%c:", 'A'+i)
		dType := getDriveType(drive)

		// Include fixed drives, RAM disks, and network (remote) drives
		if dType != driveFixed && dType != driveRAMDisk && dType != driveRemote {
			continue // skip removable/cdrom/unknown
		}

		label, fs := getVolumeInfo(drive)
		size := getTotalBytes(drive)

		vol := VolumeInfo{
			Path:       strings.TrimSuffix(drive, "\\"),
			Label:      label,
			FileSystem: fs,
			SizeBytes:  size,
		}

		if dType == driveRemote {
			vol.Type = "network"
			vol.IsNetwork = true
			if uncPath := getUNCPath(driveLetter); uncPath != "" {
				vol.UNCPath = uncPath
				if vol.Label == "" {
					vol.Label = uncPath
				}
			}
		} else {
			vol.Type = "fixed"
		}

		seen[string(rune('A'+i))] = true
		vols = append(vols, vol)
	}

	// Discover network drives mapped in interactive user sessions via registry.
	// The service runs in session 0 and cannot see user-session mappings via
	// GetLogicalDrives, but HKEY_USERS\<SID>\Network is accessible.
	for letter, uncPath := range enumerateUserNetworkDrives() {
		if seen[letter] {
			continue
		}
		vol := VolumeInfo{
			Path:      letter + ":",
			Type:      "network",
			IsNetwork: true,
			UNCPath:   uncPath,
		}
		if uncPath != "" {
			vol.Label = uncPath
		}
		vols = append(vols, vol)
	}

	return vols, nil
}

// enumerateUserNetworkDrives scans HKEY_USERS\<SID>\Network to discover
// persistent network drive mappings from all loaded user profiles. This lets
// the service detect mapped drives (NAS, file server, etc.) that are only
// visible in the interactive user's session.
func enumerateUserNetworkDrives() map[string]string {
	result := make(map[string]string)

	usersKey, err := registry.OpenKey(registry.USERS, "", registry.ENUMERATE_SUB_KEYS)
	if err != nil {
		log.Printf("agent: cannot open HKEY_USERS for network drive scan: %v", err)
		return result
	}
	defer usersKey.Close()

	sids, err := usersKey.ReadSubKeyNames(-1)
	if err != nil {
		return result
	}

	for _, sid := range sids {
		if strings.HasSuffix(sid, "_Classes") || sid == ".DEFAULT" {
			continue
		}

		networkKey, err := registry.OpenKey(registry.USERS, sid+`\Network`, registry.ENUMERATE_SUB_KEYS)
		if err != nil {
			continue
		}

		letters, err := networkKey.ReadSubKeyNames(-1)
		networkKey.Close()
		if err != nil {
			continue
		}

		for _, letter := range letters {
			upper := strings.ToUpper(letter)
			if len(upper) != 1 || upper[0] < 'A' || upper[0] > 'Z' {
				continue
			}
			if _, exists := result[upper]; exists {
				continue
			}

			letterKey, err := registry.OpenKey(registry.USERS, sid+`\Network\`+letter, registry.QUERY_VALUE)
			if err != nil {
				result[upper] = ""
				continue
			}
			remotePath, _, _ := letterKey.GetStringValue("RemotePath")
			letterKey.Close()
			result[upper] = remotePath
		}
	}

	return result
}

func getDriveType(path string) uint32 {
	ptr, _ := syscall.UTF16PtrFromString(path)
	r, _, _ := procGetDriveTypeW.Call(uintptr(unsafe.Pointer(ptr)))
	return uint32(r)
}

func getVolumeInfo(path string) (string, string) {
	volName := make([]uint16, 256)
	fsName := make([]uint16, 256)
	var serial, maxCompLen, flags uint32

	ptr, _ := syscall.UTF16PtrFromString(path)
	r, _, _ := procGetVolumeInformation.Call(
		uintptr(unsafe.Pointer(ptr)),
		uintptr(unsafe.Pointer(&volName[0])),
		uintptr(len(volName)),
		uintptr(unsafe.Pointer(&serial)),
		uintptr(unsafe.Pointer(&maxCompLen)),
		uintptr(unsafe.Pointer(&flags)),
		uintptr(unsafe.Pointer(&fsName[0])),
		uintptr(len(fsName)),
	)
	if r == 0 {
		return "", ""
	}
	return syscall.UTF16ToString(volName), syscall.UTF16ToString(fsName)
}

func getTotalBytes(path string) uint64 {
	ptr, _ := syscall.UTF16PtrFromString(path)
	var (
		freeBytesAvailable uint64
		totalBytes         uint64
		totalFreeBytes     uint64
	)
	r, _, _ := procGetDiskFreeSpaceEx.Call(
		uintptr(unsafe.Pointer(ptr)),
		uintptr(unsafe.Pointer(&freeBytesAvailable)),
		uintptr(unsafe.Pointer(&totalBytes)),
		uintptr(unsafe.Pointer(&totalFreeBytes)),
	)
	if r == 0 {
		return 0
	}
	return totalBytes
}

// getUNCPath resolves a drive letter (e.g., "Z:") to its UNC path (e.g., "\\server\share").
// Returns empty string if not a network drive or resolution fails.
func getUNCPath(driveLetter string) string {
	// Ensure format is "X:" not "X:\"
	driveLetter = strings.TrimSuffix(driveLetter, "\\")
	if len(driveLetter) != 2 || driveLetter[1] != ':' {
		return ""
	}

	localName, err := syscall.UTF16PtrFromString(driveLetter)
	if err != nil {
		return ""
	}

	// Start with a reasonable buffer size
	bufSize := uint32(512)
	remoteName := make([]uint16, bufSize)

	// WNetGetConnectionW returns 0 on success
	// ERROR_MORE_DATA (234) means buffer too small
	for {
		ret, _, _ := procWNetGetConnectionW.Call(
			uintptr(unsafe.Pointer(localName)),
			uintptr(unsafe.Pointer(&remoteName[0])),
			uintptr(unsafe.Pointer(&bufSize)),
		)

		if ret == 0 {
			// Success
			return syscall.UTF16ToString(remoteName)
		}

		if ret == 234 { // ERROR_MORE_DATA
			// Buffer too small, resize and retry
			remoteName = make([]uint16, bufSize)
			continue
		}

		// Other error (not connected, etc.)
		return ""
	}
}

// IsUNCPath returns true if the path is a UNC path (starts with \\).
func IsUNCPath(path string) bool {
	return strings.HasPrefix(path, "\\\\")
}

// ExtractShareRoot extracts the server\share portion from a UNC path.
// e.g., "\\server\share\folder\file" -> "\\server\share"
func ExtractShareRoot(uncPath string) string {
	if !IsUNCPath(uncPath) {
		return ""
	}

	// Remove leading \\
	path := strings.TrimPrefix(uncPath, "\\\\")
	parts := strings.SplitN(path, "\\", 3)
	if len(parts) < 2 {
		return ""
	}

	return "\\\\" + parts[0] + "\\" + parts[1]
}
