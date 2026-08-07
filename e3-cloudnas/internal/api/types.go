package api

const TokenHeader = "X-E3-CloudNAS-Token"

type MountRequest struct {
	MountID     int64  `json:"mount_id"`
	DriveLetter string `json:"drive_letter"`
	Bucket      string `json:"bucket"`
	Prefix      string `json:"prefix"`
	Endpoint    string `json:"endpoint"`
	Region      string `json:"region"`
	AccessKey   string `json:"access_key"`
	SecretKey   string `json:"secret_key"`
	CacheMode   string `json:"cache_mode"`
	ReadOnly    bool   `json:"read_only"`
	VolumeLabel string `json:"volume_label"`
}

type MountStatus struct {
	MountID     int64  `json:"mount_id"`
	DriveLetter string `json:"drive_letter"`
	State       string `json:"state"`
	Error       string `json:"error,omitempty"`
}

type UnmountRequest struct {
	DriveLetter string `json:"drive_letter"`
	MountID     int64  `json:"mount_id"`
}

type HealthResponse struct {
	OK      bool   `json:"ok"`
	Version string `json:"version"`
	WinFsp  bool   `json:"winfsp"`
}

type StatusResponse struct {
	Mounts []MountStatus `json:"mounts"`
}

type OKResponse struct {
	OK bool `json:"ok"`
}

type ErrorResponse struct {
	Error string `json:"error"`
}
