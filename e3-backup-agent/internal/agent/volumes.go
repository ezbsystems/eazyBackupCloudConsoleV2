package agent

// VolumeInfo represents a volume or block device that can be imaged.
// Fields are intentionally lightweight and serializable for API transport.
type VolumeInfo struct {
	Path       string `json:"path"`                 // e.g. C: or /dev/sda1
	Label      string `json:"label,omitempty"`      // optional human-friendly label
	SizeBytes  uint64 `json:"size_bytes,omitempty"` // total bytes when available
	FileSystem string `json:"filesystem,omitempty"` // e.g. NTFS, ext4
	Type       string `json:"type,omitempty"`       // e.g. fixed, disk, part, lvm, network
	UNCPath    string `json:"unc_path,omitempty"`   // For network drives: \\server\share
	IsNetwork  bool   `json:"is_network,omitempty"` // True if this is a network/mapped drive
}

// NetworkCredentials holds authentication info for network shares.
type NetworkCredentials struct {
	Username string `json:"username,omitempty"`
	Password string `json:"password,omitempty"`
	Domain   string `json:"domain,omitempty"`
}

// ListVolumes returns the list of volumes/devices available on the host.
// It delegates to the OS-specific implementation in *_windows.go or *_linux.go.
func ListVolumes() ([]VolumeInfo, error) {
	return enumerateVolumes()
}
