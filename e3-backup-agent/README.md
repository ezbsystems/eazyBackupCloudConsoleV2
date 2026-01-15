# e3 Cloud Backup Worker

A Go-based background service that executes cloud-to-cloud backup jobs using rclone. The worker polls the WHMCS database for queued backup jobs, decrypts job configurations, executes rclone sync operations, and tracks progress in real-time.

## Table of Contents

- [Overview](#overview)
- [Tech Stack](#tech-stack)
- [Architecture](#architecture)
- [Prerequisites](#prerequisites)
- [Building](#building)
- [Configuration](#configuration)
- [Running](#running)
- [Development](#development)
- [Troubleshooting](#troubleshooting)

## Overview

The e3 Cloud Backup Worker is a critical component of the Cloud-to-Cloud Backup system. It operates as a separate service running on a dedicated VM, completely isolated from the WHMCS application server. This design provides:

- **Separation of Concerns**: Backup operations don't impact WHMCS performance
- **Scalability**: Multiple worker VMs can run concurrently
- **Security**: Sensitive credentials are decrypted only on the worker VM
- **Reliability**: Worker failures don't affect the WHMCS application

### How It Works

1. **Polling**: Worker polls the database every 10 seconds (configurable) for jobs with `status='queued'`
2. **Job Selection**: Selects jobs respecting `max_concurrent_jobs` limit
3. **Decryption**: Decrypts source credentials and destination credentials using AES-256-CBC
4. **Config Generation**: Creates rclone configuration files with source and destination remotes
5. **Execution**: Spawns rclone sync process with JSON logging
6. **Progress Tracking**: Tails rclone JSON logs and updates database every 5 seconds
7. **Completion**: Marks job as success/failed/warning/cancelled based on outcome

## Tech Stack

### Core Technologies

- **Go 1.21+**: Primary programming language
- **rclone**: Cloud storage synchronization tool (external binary)
- **MySQL/MariaDB**: Database for job queue and progress tracking
- **YAML**: Configuration file format

### Go Dependencies

- `github.com/go-sql-driver/mysql v1.7.1`: MySQL database driver
- `github.com/hpcloud/tail v1.0.0`: File tailing for log monitoring
- `gopkg.in/yaml.v3 v3.0.1`: YAML configuration parsing

### Standard Library Packages

- `crypto/aes`, `crypto/cipher`: AES-256-CBC decryption
- `database/sql`: Database operations
- `os/exec`: Process execution (rclone)
- `context`: Graceful shutdown and cancellation
- `encoding/json`: JSON parsing for rclone logs
- `encoding/base64`: Base64 decoding for encrypted data

## Architecture

### Project Structure

```
e3-cloudbackup-worker/
├── cmd/
│   └── worker/
│       └── main.go              # Entry point, service initialization
├── internal/
│   ├── config/
│   │   └── config.go            # Configuration loading and validation
│   ├── crypto/
│   │   └── crypto.go            # AES-256-CBC decryption (matches PHP)
│   ├── db/
│   │   └── db.go                # Database operations and queries
│   ├── jobs/
│   │   ├── scheduler.go         # Job polling and concurrency control
│   │   └── runner.go            # Job execution and rclone management
│   ├── logs/
│   │   └── tail.go              # Rclone JSON log tailing and parsing
│   └── rclone/
│       └── rclone.go            # Rclone config file generation
├── config/
│   └── config.yaml.example     # Example configuration file
├── Makefile                     # Build automation
├── e3-cloudbackup-worker.service  # Systemd service file
├── go.mod                       # Go module dependencies
└── README.md                    # This file
```

### Component Responsibilities

#### `cmd/worker/main.go`
- Service entry point
- Loads configuration
- Initializes database connection
- Sets up graceful shutdown (SIGINT/SIGTERM)
- Starts scheduler

#### `internal/config/config.go`
- YAML configuration parsing
- Default value application
- Configuration validation
- MySQL DSN generation
- Encryption key retrieval from environment

#### `internal/crypto/crypto.go`
- AES-256-CBC decryption implementation
- Matches PHP `HelperController::decryptKey()` format
- Handles base64(IV + ciphertext) format
- PKCS7 padding removal

#### `internal/db/db.go`
- Database connection management
- Job and run queries
- Progress updates
- Status transitions
- Cancellation flag checking

#### `internal/jobs/scheduler.go`
- Polls database for queued jobs
- Manages concurrency (respects `max_concurrent_jobs`)
- Tracks running jobs
- Distributes jobs to runners

#### `internal/jobs/runner.go`
- Executes individual backup jobs
- Decrypts source and destination credentials
- Generates rclone config files
- Spawns and monitors rclone processes
- Tails JSON logs for progress
- Updates database with progress
- Handles cancellation requests
- Manages status transitions

#### `internal/logs/tail.go`
- Tails rclone JSON log files
- Parses rclone stats JSON format
- Normalizes progress updates
- Handles log file rotation

#### `internal/rclone/rclone.go`
- Generates rclone configuration files
- Supports S3-compatible and SFTP sources
- Configures destination S3 remotes
- Builds rclone sync command arguments

## Prerequisites

### System Requirements

- **OS**: Linux (Ubuntu 20.04+, Debian 11+, or similar)
- **CPU**: 2+ cores recommended
- **RAM**: 2GB+ recommended
- **Disk**: 10GB+ for logs and temporary files
- **Network**: Outbound access to source storage and destination S3 endpoint

### Software Dependencies

1. **Go 1.21+**: [Install Go](https://golang.org/doc/install)
2. **rclone**: [Install rclone](https://rclone.org/install/)
   ```bash
   curl https://rclone.org/install.sh | sudo bash
   ```
3. **MySQL Client**: For testing database connectivity
   ```bash
   sudo apt-get install mysql-client
   ```

### Database Access

The worker needs read/write access to the WHMCS database. See `WORKER_VM_SETUP.md` for detailed database user setup instructions.

Required permissions:
- `SELECT, INSERT, UPDATE` on `s3_cloudbackup_jobs`
- `SELECT, INSERT, UPDATE` on `s3_cloudbackup_runs`
- `SELECT` on `s3_buckets`, `s3_users`, `s3_user_access_keys`, `tbladdonmodules`

## Building

### Quick Build

```bash
# Clone or navigate to the project directory
cd e3-cloudbackup-worker

# Download dependencies
go mod download
go mod tidy

# Build binary
make build

# Binary will be created at: bin/e3-cloudbackup-worker
```

### Manual Build

```bash
# Download dependencies
go mod download

# Build
go build -o bin/e3-cloudbackup-worker cmd/worker/main.go

# Build optimized binary (smaller, faster)
go build -ldflags="-s -w" -o bin/e3-cloudbackup-worker cmd/worker/main.go
```

### Build Targets

The `Makefile` provides several targets:

- `make build` - Build the binary
- `make install` - Build and install to `/usr/local/bin/`
- `make test` - Run tests (when tests are added)
- `make clean` - Remove build artifacts
- `make deps` - Download and tidy dependencies
- `make lint` - Run linter (if golangci-lint is installed)

### Cross-Compilation

To build for a different platform:

```bash
# Build for Linux (from macOS/Windows)
GOOS=linux GOARCH=amd64 go build -o bin/e3-cloudbackup-worker-linux cmd/worker/main.go

# Build for ARM64
GOOS=linux GOARCH=arm64 go build -o bin/e3-cloudbackup-worker-arm64 cmd/worker/main.go
```

## Configuration

### Configuration File

1. Copy the example configuration:
```bash
cp config/config.yaml.example config/config.yaml
```

2. Edit `config/config.yaml` with your settings:

```yaml
# Database Configuration
database:
  host: "192.168.1.100"          # WHMCS database server IP/hostname
  port: 3306
  database: "whmcs_db"            # Your WHMCS database name
  username: "e3backup_worker"     # Database user created for worker
  password: "STRONG_PASSWORD"     # Database password
  max_connections: 10
  max_idle_connections: 5

# Worker Configuration
worker:
  hostname: "e3-cloudbackup-worker-01"  # Must match DB user hostname
  poll_interval_seconds: 10             # How often to check for jobs
  max_concurrent_jobs: 5                 # Max parallel jobs
  max_bandwidth_kbps: 0                  # 0 = unlimited, or set limit

# Rclone Configuration
rclone:
  binary_path: "/usr/bin/rclone"
  config_dir: "/opt/e3-cloudbackup-worker/config"
  log_dir: "/var/log/e3-cloudbackup"
  run_dir: "/var/lib/e3-cloudbackup/runs"
  stats_interval: "5s"
  log_level: "INFO"

# Destination Configuration
destination:
  endpoint: "https://rgw.example.com"   # Your Ceph RGW endpoint
  region: "us-east-1"                    # S3 region

# Logging Configuration
logging:
  level: "INFO"
  file: "/var/log/e3-cloudbackup/worker.log"
  max_size_mb: 100
  max_backups: 5
  max_age_days: 30
```

3. Set secure permissions:
```bash
chmod 600 config/config.yaml
chown e3backup:e3backup config/config.yaml
```

### Environment Variables

**Required:**
- `CLOUD_BACKUP_ENCRYPTION_KEY`: Encryption key matching WHMCS module config

Set via:
```bash
export CLOUD_BACKUP_ENCRYPTION_KEY="your-encryption-key-here"
```

Or in systemd service file (see Running section).

### Configuration Validation

The worker validates configuration on startup:
- Required fields must be present
- Database port must be 1-65535
- Worker hostname must be set
- Destination endpoint must be set

Invalid configuration will cause the service to exit with an error.

## Running

### Manual Execution

```bash
# Set encryption key
export CLOUD_BACKUP_ENCRYPTION_KEY="your-key-here"

# Run worker
./bin/e3-cloudbackup-worker -config /path/to/config.yaml

# Or with custom config path
./bin/e3-cloudbackup-worker -config /opt/e3-cloudbackup-worker/config/config.yaml
```

### Systemd Service (Recommended)

1. **Copy service file:**
```bash
sudo cp e3-cloudbackup-worker.service /etc/systemd/system/
```

2. **Edit service file to set encryption key:**
```bash
sudo nano /etc/systemd/system/e3-cloudbackup-worker.service
```

Update the `Environment` line:
```ini
Environment="CLOUD_BACKUP_ENCRYPTION_KEY=your-actual-encryption-key"
```

3. **Create required directories:**
```bash
sudo mkdir -p /var/log/e3-cloudbackup
sudo mkdir -p /var/lib/e3-cloudbackup/runs
sudo chown -R e3backup:e3backup /var/log/e3-cloudbackup
sudo chown -R e3backup:e3backup /var/lib/e3-cloudbackup
```

4. **Install binary:**
```bash
sudo make install
# Or manually:
sudo cp bin/e3-cloudbackup-worker /usr/local/bin/
sudo chown root:root /usr/local/bin/e3-cloudbackup-worker
sudo chmod 755 /usr/local/bin/e3-cloudbackup-worker
```

5. **Enable and start service:**
```bash
sudo systemctl daemon-reload
sudo systemctl enable e3-cloudbackup-worker
sudo systemctl start e3-cloudbackup-worker
```

6. **Check status:**
```bash
sudo systemctl status e3-cloudbackup-worker
```

7. **View logs:**
```bash
# Recent logs
sudo journalctl -u e3-cloudbackup-worker -n 50

# Follow logs
sudo journalctl -u e3-cloudbackup-worker -f

# Logs since boot
sudo journalctl -u e3-cloudbackup-worker --since boot
```

### Service Management

```bash
# Start service
sudo systemctl start e3-cloudbackup-worker

# Stop service
sudo systemctl stop e3-cloudbackup-worker

# Restart service
sudo systemctl restart e3-cloudbackup-worker

# Reload configuration (if config file changed)
sudo systemctl reload e3-cloudbackup-worker

# Check status
sudo systemctl status e3-cloudbackup-worker

# Enable auto-start on boot
sudo systemctl enable e3-cloudbackup-worker

# Disable auto-start
sudo systemctl disable e3-cloudbackup-worker
```

## Development

### Development Setup

1. **Clone repository:**
```bash
git clone <repository-url>
cd e3-cloudbackup-worker
```

2. **Install Go dependencies:**
```bash
go mod download
go mod tidy
```

3. **Set up development environment:**
```bash
# Create local config
cp config/config.yaml.example config/config.yaml
# Edit config.yaml with test database settings

# Set encryption key
export CLOUD_BACKUP_ENCRYPTION_KEY="test-key"
```

### Code Structure

The codebase follows standard Go project layout:

- **`cmd/`**: Application entry points
- **`internal/`**: Private application code (not importable)
- **`config/`**: Configuration files
- **`go.mod`**: Go module definition

### Key Design Decisions

1. **Database Polling**: Uses polling instead of database triggers/events for simplicity and portability
2. **Concurrency Control**: In-memory map tracks running jobs (simple, effective for single-worker setup)
3. **Log Tailing**: Uses `hpcloud/tail` library for efficient log file monitoring
4. **Status Transitions**: Explicit status updates ensure database consistency
5. **Graceful Shutdown**: Context-based cancellation allows clean process termination

### Adding New Features

#### Adding a New Source Type

1. Update `internal/rclone/rclone.go`:
   - Add struct for source config (e.g., `GoogleDriveSource`)
   - Add case in `GenerateRcloneConfig()` switch statement
   - Generate appropriate rclone remote config

2. Update `internal/db/db.go`:
   - Ensure `GetJobConfig()` handles new source type

3. Update documentation

#### Adding Job Validation

1. Create `internal/validation/validation.go`
2. Add validation method to `internal/jobs/runner.go`
3. Call validation after rclone completion
4. Update run status based on validation results

### Testing

#### Unit Tests

```bash
# Run all tests
go test ./...

# Run tests with coverage
go test -cover ./...

# Run tests for specific package
go test ./internal/crypto
```

#### Integration Testing

1. Set up test database with sample jobs
2. Configure worker with test database
3. Create test job via WHMCS API
4. Monitor worker execution
5. Verify database updates

#### Manual Testing Checklist

- [ ] Worker connects to database successfully
- [ ] Worker polls for queued jobs
- [ ] Encryption/decryption works correctly
- [ ] Rclone config generation is correct
- [ ] Rclone process starts and executes
- [ ] Progress updates are written to database
- [ ] Job cancellation works
- [ ] Status transitions are correct
- [ ] Graceful shutdown works (SIGTERM)

### Debugging

#### Enable Debug Logging

Set log level to DEBUG in `config.yaml`:
```yaml
logging:
  level: "DEBUG"
```

#### Common Debug Commands

```bash
# Check if worker is running
ps aux | grep e3-cloudbackup-worker

# Check database connection
mysql -h DB_HOST -u DB_USER -p DB_NAME -e "SELECT COUNT(*) FROM s3_cloudbackup_runs WHERE status='queued';"

# Check rclone binary
which rclone
rclone version

# Test encryption manually
# Create a test Go program to decrypt sample data

# View rclone logs
tail -f /var/lib/e3-cloudbackup/runs/{run_id}/rclone.json

# Check worker logs
journalctl -u e3-cloudbackup-worker -f
```

## Troubleshooting

### Worker Not Picking Up Jobs

**Symptoms**: Jobs remain in `queued` status

**Possible Causes**:
1. Worker not running
2. Database connection issues
3. Worker hostname mismatch
4. All job slots full

**Solutions**:
```bash
# Check service status
sudo systemctl status e3-cloudbackup-worker

# Check database connection
mysql -h DB_HOST -u DB_USER -p -e "SELECT 1;"

# Verify hostname matches
grep hostname config/config.yaml
# Should match database user hostname

# Check running jobs
mysql -h DB_HOST -u DB_USER -p -e "SELECT COUNT(*) FROM s3_cloudbackup_runs WHERE status='running';"
```

### Decryption Errors

**Symptoms**: Jobs fail with "decrypt failed" error

**Possible Causes**:
1. Encryption key not set or incorrect
2. Encrypted data format mismatch
3. Key length issues

**Solutions**:
```bash
# Verify encryption key is set
echo $CLOUD_BACKUP_ENCRYPTION_KEY

# Check encryption key matches WHMCS
# In WHMCS: Addons → Cloud Storage → Configure
# Compare with worker environment variable

# Test decryption manually (create test program)
```

### Rclone Execution Failures

**Symptoms**: Jobs fail with "rclone failed" error

**Possible Causes**:
1. Rclone binary not found
2. Invalid rclone config
3. Network connectivity issues
4. Invalid credentials

**Solutions**:
```bash
# Verify rclone exists
which rclone
ls -la /usr/bin/rclone

# Test rclone manually
rclone version

# Check rclone config file
cat /var/lib/e3-cloudbackup/runs/{run_id}/rclone.conf

# Test network connectivity
ping rgw.example.com
curl -I https://rgw.example.com

# Check rclone logs
cat /var/lib/e3-cloudbackup/runs/{run_id}/rclone.json
```

### Database Connection Errors

**Symptoms**: Worker fails to start or can't query database

**Possible Causes**:
1. Incorrect database credentials
2. Network connectivity issues
3. Database user permissions
4. Firewall blocking connection

**Solutions**:
```bash
# Test connection manually
mysql -h DB_HOST -u DB_USER -p DB_NAME

# Check firewall
sudo ufw status
# Allow MySQL port if needed

# Verify user permissions
mysql -h DB_HOST -u root -p -e "SHOW GRANTS FOR 'e3backup_worker'@'worker-hostname';"
```

### High Memory Usage

**Symptoms**: Worker consumes excessive memory

**Possible Causes**:
1. Too many concurrent jobs
2. Large log files being buffered
3. Memory leaks

**Solutions**:
```bash
# Reduce max_concurrent_jobs in config.yaml
worker:
  max_concurrent_jobs: 2  # Reduce from 5

# Check memory usage
ps aux | grep e3-cloudbackup-worker

# Monitor memory over time
watch -n 1 'ps aux | grep e3-cloudbackup-worker'
```

## Security Considerations

1. **Encryption Key**: Store securely, never commit to version control
2. **Database Credentials**: Use strong passwords, limit user permissions
3. **File Permissions**: Config files should be 600, owned by service user
4. **Network**: Use firewall to restrict database access
5. **Logs**: May contain sensitive information, secure log files
6. **Service User**: Run as non-root user (`e3backup`)

## Performance Tuning

1. **Poll Interval**: Lower = faster job pickup, higher = less database load
2. **Concurrent Jobs**: Balance between throughput and resource usage
3. **Bandwidth Limit**: Prevent worker from saturating network
4. **Database Connections**: Adjust `max_connections` based on load
5. **Log Rotation**: Configure to prevent disk space issues

## License

[Your License Here]

## Support

For issues and questions:
- Check `WORKER_VM_SETUP.md` for deployment instructions
- Review logs: `journalctl -u e3-cloudbackup-worker`
- Check WHMCS module logs for related errors
