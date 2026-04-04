package db

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"time"

	_ "github.com/go-sql-driver/mysql"
	"github.com/your-org/e3-cloudbackup-worker/internal/config"
)

type Database struct {
	sqlDB      *sql.DB
	workerHost string
}

func NewDatabase(cfg *config.Config) (*Database, error) {
	db, err := sql.Open("mysql", cfg.MySQLDSN())
	if err != nil {
		return nil, fmt.Errorf("open mysql: %w", err)
	}
	if cfg.Database.MaxConnections > 0 {
		db.SetMaxOpenConns(cfg.Database.MaxConnections)
	}
	if cfg.Database.MaxIdleConnections > 0 {
		db.SetMaxIdleConns(cfg.Database.MaxIdleConnections)
	}
	db.SetConnMaxLifetime(2 * time.Hour)
	return &Database{
		sqlDB:      db,
		workerHost: cfg.Worker.Hostname,
	}, nil
}

func (d *Database) Close() error {
	return d.sqlDB.Close()
}

type Run struct {
	ID    string
	JobID string
}

func (d *Database) GetRun(ctx context.Context, runID string) (*Run, error) {
	row := d.sqlDB.QueryRowContext(ctx,
		`SELECT BIN_TO_UUID(run_id), BIN_TO_UUID(job_id) FROM s3_cloudbackup_runs WHERE run_id=UUID_TO_BIN(?)`, runID)
	var r Run
	if err := row.Scan(&r.ID, &r.JobID); err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, fmt.Errorf("run %s not found", runID)
		}
		return nil, fmt.Errorf("scan run: %w", err)
	}
	return &r, nil
}

type Job struct {
	ID                 string
	ClientID           int64
	S3UserID           int64
	Name               string
	SourceType         string
	SourceDisplayName  string
	SourceConfigEnc    string
	SourceConnectionID sql.NullInt64
	SourcePath         string
	DestBucketID       int64
	DestBucketName     string
	DestPrefix         string
	BackupMode         string
	ValidationMode     string
	DestAccessKeyEnc   string
	DestSecretKeyEnc   string
}

type Progress struct {
	ProgressPct        float64
	BytesTotal         int64
	BytesTransferred   int64
	ObjectsTotal       int64
	ObjectsTransferred int64
	SpeedBytesPerSec   int64
	EtaSeconds         int64
	CurrentItem        string
}

type AddonSettings struct {
	GlobalMaxConcurrentJobs int
	GlobalMaxBandwidthKbps  int
	EventsMaxPerRun         int
	EventsProgressIntervalS int
}

func (d *Database) GetAddonConfigMap(ctx context.Context) (map[string]string, error) {
	rows, err := d.sqlDB.QueryContext(ctx, `
		SELECT setting, value
		FROM tbladdonmodules
		WHERE module='cloudstorage'`)
	if err != nil {
		return nil, fmt.Errorf("get addon config map: %w", err)
	}
	defer rows.Close()
	cfg := make(map[string]string)
	for rows.Next() {
		var k, v string
		if err := rows.Scan(&k, &v); err != nil {
			return nil, fmt.Errorf("scan addon setting: %w", err)
		}
		cfg[k] = v
	}
	return cfg, rows.Err()
}

func (d *Database) GetNextQueuedRuns(ctx context.Context, limit int) ([]Run, error) {
	rows, err := d.sqlDB.QueryContext(ctx, `
		SELECT BIN_TO_UUID(r.run_id), BIN_TO_UUID(r.job_id)
		FROM s3_cloudbackup_runs r
		INNER JOIN s3_cloudbackup_jobs j ON j.job_id = r.job_id
		WHERE r.status = 'queued'
		  AND j.status = 'active'
		  AND (j.agent_uuid IS NULL OR j.agent_uuid = '')
		  AND (j.source_type IS NULL OR j.source_type <> 'local_agent')
		  AND (j.engine IS NULL OR j.engine <> 'kopia')
		ORDER BY r.run_id ASC
		LIMIT ?`, limit)
	if err != nil {
		return nil, fmt.Errorf("query next queued runs: %w", err)
	}
	defer rows.Close()
	var res []Run
	for rows.Next() {
		var r Run
		if err := rows.Scan(&r.ID, &r.JobID); err != nil {
			return nil, fmt.Errorf("scan run: %w", err)
		}
		res = append(res, r)
	}
	return res, rows.Err()
}

func (d *Database) UpdateRunStatus(ctx context.Context, runID string, status string, startedAt, finishedAt *time.Time, errorSummary string) error {
	query := `UPDATE s3_cloudbackup_runs SET status=?, worker_host=?, error_summary=?`
	var args []any
	args = append(args, status, d.workerHost, nullify(errorSummary))
	if startedAt != nil {
		query += `, started_at=?`
		args = append(args, *startedAt)
	}
	if finishedAt != nil {
		query += `, finished_at=?`
		args = append(args, *finishedAt)
	}
	query += ` WHERE run_id=UUID_TO_BIN(?)`
	args = append(args, runID)

	_, err := d.sqlDB.ExecContext(ctx, query, args...)
	if err != nil {
		return fmt.Errorf("update run status: %w", err)
	}
	return nil
}

func (d *Database) UpdateRunProgress(ctx context.Context, runID string, p Progress) error {
	_, err := d.sqlDB.ExecContext(ctx, `
		UPDATE s3_cloudbackup_runs
		SET progress_pct=?,
		    bytes_total=?,
		    bytes_transferred=?,
		    objects_total=?,
		    objects_transferred=?,
		    speed_bytes_per_sec=?,
		    eta_seconds=?,
		    current_item=?
		WHERE run_id=UUID_TO_BIN(?)`,
		p.ProgressPct,
		p.BytesTotal,
		p.BytesTransferred,
		p.ObjectsTotal,
		p.ObjectsTransferred,
		p.SpeedBytesPerSec,
		p.EtaSeconds,
		p.CurrentItem,
		runID,
	)
	if err != nil {
		return fmt.Errorf("update run progress: %w", err)
	}
	return nil
}

func (d *Database) MarkRunCancelled(ctx context.Context, runID string, reason string) error {
	now := time.Now().UTC()
	return d.UpdateRunStatus(ctx, runID, "cancelled", nil, &now, reason)
}

func (d *Database) GetJobConfig(ctx context.Context, jobID string) (*Job, error) {
	row := d.sqlDB.QueryRowContext(ctx, `
		SELECT BIN_TO_UUID(j.job_id), j.client_id, j.s3_user_id, j.name, j.source_type, j.source_display_name,
		       j.source_config_enc, j.source_connection_id, j.source_path, j.dest_bucket_id, b.name AS dest_bucket_name,
		       j.dest_prefix, j.backup_mode,
		       COALESCE(r.validation_mode, 'none') AS validation_mode,
		       ak.access_key AS dest_access_key_enc,
		       ak.secret_key AS dest_secret_key_enc
		FROM s3_cloudbackup_jobs j
		INNER JOIN s3_buckets b ON b.id = j.dest_bucket_id
		INNER JOIN s3_user_access_keys ak ON ak.user_id = j.s3_user_id
		LEFT JOIN (
			SELECT job_id, MAX(validation_mode) AS validation_mode
			FROM s3_cloudbackup_runs
			WHERE job_id = UUID_TO_BIN(?)
			GROUP BY job_id
		) r ON r.job_id = j.job_id
		WHERE j.job_id = UUID_TO_BIN(?)`,
		jobID, jobID,
	)
	var j Job
	err := row.Scan(
		&j.ID, &j.ClientID, &j.S3UserID, &j.Name, &j.SourceType, &j.SourceDisplayName,
		&j.SourceConfigEnc, &j.SourceConnectionID, &j.SourcePath, &j.DestBucketID, &j.DestBucketName,
		&j.DestPrefix, &j.BackupMode, &j.ValidationMode,
		&j.DestAccessKeyEnc, &j.DestSecretKeyEnc,
	)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, fmt.Errorf("job %s not found", jobID)
		}
		return nil, fmt.Errorf("scan job: %w", err)
	}
	return &j, nil
}

type SourceConnection struct {
	ID              int64
	ClientID        int64
	Provider        string
	RefreshTokenEnc string
	Status          string
	AccountEmail    sql.NullString
	Scopes          sql.NullString
	Meta            sql.NullString
}

func (d *Database) GetSourceConnection(ctx context.Context, id int64, clientID int64) (*SourceConnection, error) {
	row := d.sqlDB.QueryRowContext(ctx, `
		SELECT id, client_id, provider, refresh_token_enc, status, account_email, scopes, meta
		FROM s3_cloudbackup_sources
		WHERE id=? AND client_id=?`, id, clientID)
	var s SourceConnection
	if err := row.Scan(&s.ID, &s.ClientID, &s.Provider, &s.RefreshTokenEnc, &s.Status, &s.AccountEmail, &s.Scopes, &s.Meta); err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, fmt.Errorf("source connection %d not found", id)
		}
		return nil, fmt.Errorf("scan source connection: %w", err)
	}
	return &s, nil
}

func (d *Database) GetLatestActiveGoogleDriveSource(ctx context.Context, clientID int64) (*SourceConnection, error) {
	row := d.sqlDB.QueryRowContext(ctx, `
		SELECT id, client_id, provider, refresh_token_enc, status, account_email, scopes, meta
		FROM s3_cloudbackup_sources
		WHERE client_id=? AND provider='google_drive' AND status='active'
		ORDER BY updated_at DESC, id DESC
		LIMIT 1`, clientID)
	var s SourceConnection
	if err := row.Scan(&s.ID, &s.ClientID, &s.Provider, &s.RefreshTokenEnc, &s.Status, &s.AccountEmail, &s.Scopes, &s.Meta); err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, fmt.Errorf("no active google_drive source found for client %d", clientID)
		}
		return nil, fmt.Errorf("scan source connection: %w", err)
	}
	return &s, nil
}

func (d *Database) CheckCancelRequested(ctx context.Context, runID string) (bool, error) {
	var flag bool
	err := d.sqlDB.QueryRowContext(ctx, `
		SELECT COALESCE(cancel_requested, 0) FROM s3_cloudbackup_runs WHERE run_id=UUID_TO_BIN(?)`, runID).
		Scan(&flag)
	if err != nil {
		return false, fmt.Errorf("query cancel flag: %w", err)
	}
	return flag, nil
}

func (d *Database) UpdateRunLogPathAndExcerpt(ctx context.Context, runID string, logPath, logExcerpt string) error {
	_, err := d.sqlDB.ExecContext(ctx, `
		UPDATE s3_cloudbackup_runs
		SET log_path=?, log_excerpt=?
		WHERE run_id=UUID_TO_BIN(?)`,
		logPath, logExcerpt, runID,
	)
	if err != nil {
		return fmt.Errorf("update run log info: %w", err)
	}
	return nil
}

func (d *Database) UpdateRunValidationStatus(ctx context.Context, runID string, status string) error {
	_, err := d.sqlDB.ExecContext(ctx, `
		UPDATE s3_cloudbackup_runs
		SET validation_status=?
		WHERE run_id=UUID_TO_BIN(?)`,
		status, runID,
	)
	if err != nil {
		return fmt.Errorf("update validation status: %w", err)
	}
	return nil
}

func (d *Database) UpdateRunValidationLogExcerpt(ctx context.Context, runID string, excerpt string) error {
	_, err := d.sqlDB.ExecContext(ctx, `
		UPDATE s3_cloudbackup_runs
		SET validation_log_excerpt=?
		WHERE run_id=UUID_TO_BIN(?)`,
		excerpt, runID,
	)
	if err != nil {
		return fmt.Errorf("update validation log excerpt: %w", err)
	}
	return nil
}

func (d *Database) GetSystemURL(ctx context.Context) (string, error) {
	row := d.sqlDB.QueryRowContext(ctx, `
		SELECT value FROM tblconfiguration WHERE setting='SystemURL' LIMIT 1`)
	var v string
	if err := row.Scan(&v); err != nil {
		return "", fmt.Errorf("get SystemURL: %w", err)
	}
	return v, nil
}

func (d *Database) GetAddonSettings(ctx context.Context) (*AddonSettings, error) {
	rows, err := d.sqlDB.QueryContext(ctx, `
		SELECT setting, value
		FROM tbladdonmodules
		WHERE module='cloudstorage'`)
	if err != nil {
		return nil, fmt.Errorf("get addon settings: %w", err)
	}
	defer rows.Close()
	settings := &AddonSettings{}
	for rows.Next() {
		var k, v string
		if err := rows.Scan(&k, &v); err != nil {
			return nil, fmt.Errorf("scan addon setting: %w", err)
		}
		switch k {
		case "cloudbackup_global_max_concurrent_jobs":
			var n int
			_, _ = fmt.Sscanf(v, "%d", &n)
			settings.GlobalMaxConcurrentJobs = n
		case "cloudbackup_global_max_bandwidth_kbps":
			var n int
			_, _ = fmt.Sscanf(v, "%d", &n)
			settings.GlobalMaxBandwidthKbps = n
		case "cloudbackup_event_max_per_run":
			var n int
			_, _ = fmt.Sscanf(v, "%d", &n)
			if n <= 0 {
				n = 5000
			}
			settings.EventsMaxPerRun = n
		case "cloudbackup_event_progress_interval_seconds":
			var n int
			_, _ = fmt.Sscanf(v, "%d", &n)
			if n <= 0 {
				n = 2
			}
			settings.EventsProgressIntervalS = n
		}
	}
	return settings, rows.Err()
}

func (d *Database) CountGlobalRunningRuns(ctx context.Context) (int, error) {
	row := d.sqlDB.QueryRowContext(ctx, `
		SELECT COUNT(*) FROM s3_cloudbackup_runs WHERE status IN ('starting','running')`)
	var n int
	if err := row.Scan(&n); err != nil {
		return 0, fmt.Errorf("count running runs: %w", err)
	}
	return n, nil
}

func (d *Database) CountRunEvents(ctx context.Context, runID string) (int, error) {
	row := d.sqlDB.QueryRowContext(ctx,
		`SELECT COUNT(*) FROM s3_cloudbackup_run_events WHERE run_id=UUID_TO_BIN(?)`, runID)
	var n int
	if err := row.Scan(&n); err != nil {
		return 0, fmt.Errorf("count run events: %w", err)
	}
	return n, nil
}

func (d *Database) InsertRunEvent(ctx context.Context, runID string, ts time.Time, typ, level, code, messageID string, paramsJSON string) error {
	_, err := d.sqlDB.ExecContext(ctx, `
		INSERT INTO s3_cloudbackup_run_events 
			(run_id, ts, type, level, code, message_id, params_json)
		VALUES (UUID_TO_BIN(?), ?, ?, ?, ?, ?, ?)`,
		runID, ts.UTC(), typ, level, code, messageID, paramsJSON,
	)
	if err != nil {
		return fmt.Errorf("insert run event: %w", err)
	}
	return nil
}

func nullify(s string) any {
	if s == "" {
		return nil
	}
	return s
}
