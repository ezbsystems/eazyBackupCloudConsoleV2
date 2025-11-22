package jobs

// ErrorCode represents a stable identifier for categorizing run failures.
// These codes are recorded in s3_cloudbackup_runs.error_summary to aid triage.
type ErrorCode string

const (
	// General/config
	ErrNoEncryptionKey     ErrorCode = "ERR_NO_ENCRYPTION_KEY"
	ErrDecryptSourceConfig ErrorCode = "ERR_DECRYPT_SOURCE_CONFIG"
	ErrDecryptDestAccess   ErrorCode = "ERR_DECRYPT_DEST_ACCESS_KEY"
	ErrDecryptDestSecret   ErrorCode = "ERR_DECRYPT_DEST_SECRET_KEY"
	ErrWriteRcloneConfig   ErrorCode = "ERR_WRITE_RCLONE_CONFIG"
	ErrRcloneStart         ErrorCode = "ERR_RCLONE_START"

	// Google Drive specific
	ErrGDriveNoClientCreds   ErrorCode = "ERR_GDRIVE_NO_CLIENT_CREDENTIALS"
	ErrGDriveNoRefresh       ErrorCode = "ERR_GDRIVE_NO_REFRESH_TOKEN"
	ErrGDriveSourceLookup    ErrorCode = "ERR_GDRIVE_SOURCE_LOOKUP"
	ErrGDriveTokenEnforce    ErrorCode = "ERR_GDRIVE_ENFORCE_TOKEN"
	ErrGDriveRefreshPrecheck ErrorCode = "ERR_GDRIVE_REFRESH_PRECHECK"

	// S3 / S3-compatible source preflight
	ErrS3Auth          ErrorCode = "ERR_S3_AUTH"
	ErrS3BucketMissing ErrorCode = "ERR_S3_BUCKET_NOT_FOUND"
	ErrS3PreflightNet  ErrorCode = "ERR_S3_PREFLIGHT_NETWORK"
)
