# Cloud Backup Worker VM Setup Guide

This guide provides step-by-step instructions for setting up the e3-cloudbackup-worker-01 VM from scratch. Follow this guide if the server needs to be rebuilt or for initial deployment.

## Prerequisites

- Proxmox VM access (or equivalent virtualization platform)
- Root/administrative access to the VM
- Network connectivity to WHMCS database server
- Network connectivity to Ceph RGW endpoint
- SSH access configured

## Step 1: VM Provisioning

### 1.1 Create VM in Proxmox

1. **VM Specifications**:
   - **Hostname**: `e3-cloudbackup-worker-01`
   - **OS**: Ubuntu 22.04 LTS (or Debian 12)
   - **CPU**: 2-4 cores (adjust based on expected load)
   - **RAM**: 4-8 GB (adjust based on concurrent jobs)
   - **Disk**: 50+ GB (for logs and temporary files)
   - **Network**: Same network as Ceph RGW for optimal performance

2. **Install Operating System**:
   - Use Ubuntu Server 22.04 LTS ISO
   - Minimal installation (no GUI)
   - Enable SSH during installation
   - Set root password or configure SSH keys

### 1.2 Initial System Configuration

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Set hostname
sudo hostnamectl set-hostname e3-cloudbackup-worker-01

# Add to /etc/hosts (if needed)
echo "127.0.0.1 e3-cloudbackup-worker-01" | sudo tee -a /etc/hosts

# Reboot to ensure hostname is set
sudo reboot
```

## Step 2: Install System Dependencies

### 2.1 Install Base Packages

```bash
sudo apt update
sudo apt install -y \
    curl \
    wget \
    git \
    build-essential \
    ca-certificates \
    gnupg \
    lsb-release \
    unzip \
    tar \
    gzip \
    net-tools \
    vim \
    htop \
    logrotate \
    supervisor \
    systemd
```

### 2.2 Install Go (for Worker Service)

```bash
# Download Go (check https://go.dev/dl/ for latest version)
cd /tmp
wget https://go.dev/dl/go1.21.5.linux-amd64.tar.gz

# Remove old Go installation if exists
sudo rm -rf /usr/local/go

# Extract Go
sudo tar -C /usr/local -xzf go1.21.5.linux-amd64.tar.gz

# Add Go to system-wide PATH (for all users)
echo 'export PATH=$PATH:/usr/local/go/bin' | sudo tee -a /etc/profile
echo 'export PATH=$PATH:/usr/local/go/bin' | sudo tee -a /etc/bash.bashrc

# Add Go to e3backup user's PATH (create .bashrc if it doesn't exist)
sudo -u e3backup mkdir -p /home/e3backup
sudo -u e3backup bash -c 'echo "export PATH=\$PATH:/usr/local/go/bin" >> /home/e3backup/.bashrc'

# Reload profile for current session
export PATH=$PATH:/usr/local/go/bin

# Verify installation (as root)
go version
# Should output: go version go1.21.5 linux/amd64

# Verify Go is accessible for e3backup user
sudo -u e3backup /usr/local/go/bin/go version
# Should output: go version go1.21.5 linux/amd64
```

**Alternative: Install via package manager**
```bash
# Add Go repository (if available)
# Or use snap:
sudo snap install go --classic
```

## Step 3: Install rclone

### 3.1 Download and Install rclone

```bash
# Download latest rclone
cd /tmp
curl https://rclone.org/install.sh | sudo bash

# Verify installation
rclone version
# Should show rclone version and build info
```

### 3.2 Verify rclone Installation

```bash
# Check rclone location
which rclone
# Should output: /usr/bin/rclone

# Test rclone
rclone --version
```

## Step 4: Create Service User and Directories

### 4.1 Create Service User

```bash
# Create dedicated user for worker service
sudo useradd -r -s /bin/false -d /opt/e3-cloudbackup-worker e3backup

# Create required directories
sudo mkdir -p /opt/e3-cloudbackup-worker/{bin,config,logs,runs}
sudo mkdir -p /var/lib/e3-cloudbackup/runs
sudo mkdir -p /var/log/e3-cloudbackup

# Set ownership
sudo chown -R e3backup:e3backup /opt/e3-cloudbackup-worker
sudo chown -R e3backup:e3backup /var/lib/e3-cloudbackup
sudo chown -R e3backup:e3backup /var/log/e3-cloudbackup

# Set permissions
sudo chmod 755 /opt/e3-cloudbackup-worker
sudo chmod 750 /opt/e3-cloudbackup-worker/{config,logs}
sudo chmod 755 /var/lib/e3-cloudbackup
sudo chmod 755 /var/log/e3-cloudbackup
```

## Step 5: Database Access Configuration

### 5.1 Create Database User (on WHMCS Database Server)

**On the WHMCS database server**, create a read-write user for the worker:

**Important**: In MySQL, `'username'@'hostname'` specifies WHERE THE CONNECTION COMES FROM (the client host), not where the database server is. Since the worker VM will connect TO the database server, use the worker VM's hostname/IP, NOT 'localhost'. Using 'localhost' would only allow connections from the database server itself.

**Option 1: Using Worker VM Hostname** (if DNS resolution works):
```sql
-- Connect to MySQL/MariaDB as root
mysql -u root -p

-- Create database user (replace PASSWORD with strong password)
-- Note: 'e3-cloudbackup-worker-01' is the worker VM hostname (where connections come FROM)
CREATE USER 'e3backup_worker'@'e3-cloudbackup-worker-01' IDENTIFIED BY 'STRONG_PASSWORD_HERE';

-- Grant necessary permissions
GRANT SELECT, INSERT, UPDATE ON eazyback_whmcs.s3_cloudbackup_jobs TO 'e3backup_worker'@'e3-cloudbackup-worker-01';
GRANT SELECT, INSERT, UPDATE ON eazyback_whmcs.s3_cloudbackup_runs TO 'e3backup_worker'@'e3-cloudbackup-worker-01';
GRANT SELECT ON eazyback_whmcs.s3_buckets TO 'e3backup_worker'@'e3-cloudbackup-worker-01';
GRANT SELECT ON eazyback_whmcs.s3_users TO 'e3backup_worker'@'e3-cloudbackup-worker-01';
GRANT SELECT ON eazyback_whmcs.s3_user_access_keys TO 'e3backup_worker'@'e3-cloudbackup-worker-01';
GRANT SELECT ON eazyback_whmcs.s3_cloudbackup_sources TO 'e3backup_worker'@'e3-cloudbackup-worker-01';
GRANT SELECT ON eazyback_whmcs.tbladdonmodules TO 'e3backup_worker'@'e3-cloudbackup-worker-01';

FLUSH PRIVILEGES;
EXIT;
```

**Option 2: Using Worker VM IP Address** (recommended if hostname resolution is unreliable):
```sql
-- Connect to MySQL/MariaDB as root
mysql -u root -p

-- Create database user using IP address (replace 10.0.0.50 with actual worker VM IP)
CREATE USER 'e3backup_worker'@'192.168.92.115' IDENTIFIED BY 'STRONG_PASSWORD_HERE';

-- Grant necessary permissions (using IP address)
GRANT SELECT, INSERT, UPDATE ON eazyback_whmcs.s3_cloudbackup_jobs TO 'e3backup_worker'@'10.0.0.50';
GRANT SELECT, INSERT, UPDATE ON eazyback_whmcs.s3_cloudbackup_runs TO 'e3backup_worker'@'10.0.0.50';
GRANT SELECT ON eazyback_whmcs.s3_buckets TO 'e3backup_worker'@'10.0.0.50';
GRANT SELECT ON eazyback_whmcs.s3_users TO 'e3backup_worker'@'10.0.0.50';
GRANT SELECT ON eazyback_whmcs.s3_user_access_keys TO 'e3backup_worker'@'10.0.0.50';
GRANT SELECT ON eazyback_whmcs.s3_cloudbackup_sources TO 'e3backup_worker'@'10.0.0.50';
GRANT SELECT ON eazyback_whmcs.tbladdonmodules TO 'e3backup_worker'@'10.0.0.50';

FLUSH PRIVILEGES;
EXIT;
```

**Option 3: Using Subnet Wildcard** (if worker VM IP may change):
```sql
-- Connect to MySQL/MariaDB as root
mysql -u root -p

-- Create database user for entire subnet (replace 10.0.0.% with your subnet)
-- Less secure but more flexible if worker VM IP changes
CREATE USER 'e3backup_worker'@'192.168.92.115'
  IDENTIFIED BY 'PASSWORD';

-- Grant necessary permissions
GRANT SELECT, INSERT, UPDATE ON eazyback_whmcs.s3_cloudbackup_jobs TO 'e3backup_worker'@'192.168.92.115';
GRANT SELECT, INSERT, UPDATE ON eazyback_whmcs.s3_cloudbackup_runs TO 'e3backup_worker'@'192.168.92.115';
GRANT SELECT ON eazyback_whmcs.s3_buckets TO 'e3backup_worker'@'192.168.92.115';
GRANT SELECT ON eazyback_whmcs.s3_users TO 'e3backup_worker'@'192.168.92.115';
GRANT SELECT ON eazyback_whmcs.s3_user_access_keys TO 'e3backup_worker'@'192.168.92.115';
GRANT SELECT ON eazyback_whmcs.s3_cloudbackup_sources TO 'e3backup_worker'@'192.168.92.115';
GRANT SELECT ON eazyback_whmcs.tbladdonmodules TO 'e3backup_worker'@'192.168.92.115';

FLUSH PRIVILEGES;
EXIT;
```

**Note**: 
- Replace `eazyback_whmcs` with your actual WHMCS database name (check `configuration.php` on your WHMCS server)
- Using `'localhost'` would only allow connections from the database server itself, which is NOT what we want
- Use the worker VM's hostname, IP address, or subnet wildcard depending on your network setup


### Open MySQL to the LAN (bind-address)
On Ubuntu, the MySQL or MariaDB configuration is usually:

```
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```
Look for a line like: 
bind-address            = 127.0.0.1
Change it to:
bind-address            = 192.168.92.79

```
sudo systemctl restart mysql
```
Confirm MySQL is listening on new IP
```
sudo ss -tlnp | grep 3306
```


### 5.2 Test Database Connection (from Worker VM)


```bash
# Install MySQL client for testing
sudo apt install -y mysql-client

# Test connection (replace with actual values)
mysql -h 192.168.92.79 -u e3backup_worker -p eazyback_whmcs

# If connection succeeds, run a test query:
SHOW TABLES LIKE 's3_cloudbackup%';
EXIT;
```

## Step 6: Worker Service Installation

**Important**: The worker service is a **separate application** that runs on the worker VM. It is NOT part of the WHMCS addon module codebase. The worker service needs to be developed separately (see Phase 4 in `CLOUD_BACKUP_TASKS.md`).

### 6.1 Deploy Worker Service Code

**Option A: Copy from Local Development Machine** (if you have the worker service code locally)
```bash
On your local development machine, create a tarball of the worker service:
cd /var/www/eazybackup.ca
tar -czf e3-cloudbackup-worker.tar.gz \
  --exclude='.git' \
  --exclude='node_modules' \
  e3-cloudbackup-worker

# Copy to worker VM using SCP (from your local machine):
scp e3-cloudbackup-worker.tar.gz root@e3-cloudbackup-worker-01:/tmp/

# On the worker VM, extract:
cd /opt/e3-cloudbackup-worker
sudo tar -xzf /tmp/e3-cloudbackup-worker.tar.gz --strip-components=1
sudo chown -R e3backup:e3backup /opt/e3-cloudbackup-worker
```

**Option B: Clone from Git Repository** (if you have the worker service on GitHub/GitLab)
```bash
cd /opt/e3-cloudbackup-worker
sudo -u e3backup git clone https://github.com/your-org/e3-cloudbackup-worker.git .
sudo chown -R e3backup:e3backup /opt/e3-cloudbackup-worker
```

**Option C: Deploy from Archive** (if you have a pre-built archive)
```bash
# Upload worker service archive to /tmp (via SCP, SFTP, etc.)
# Extract to /opt/e3-cloudbackup-worker
cd /tmp
sudo tar -xzf e3-cloudbackup-worker.tar.gz -C /opt/e3-cloudbackup-worker
sudo chown -R e3backup:e3backup /opt/e3-cloudbackup-worker
```

**Option D: Create Worker Service Structure** (if starting from scratch)
```bash
# Create basic directory structure
cd /opt/e3-cloudbackup-worker
sudo -u e3backup mkdir -p cmd/worker internal/{config,db,jobs,rclone,logs} bin config

# Create a basic Go module (if using Go)
sudo -u e3backup cat > go.mod << 'EOF'
module github.com/your-org/e3-cloudbackup-worker

go 1.21

require (
    // Add your dependencies here
)
EOF

# Note: You'll need to implement the worker service according to Phase 4 requirements
# See docs/CLOUD_BACKUP_TASKS.md for implementation details
```

### 6.2 Build Worker Service

**Important**: Ensure Go is accessible. If you get "command not found" when using `sudo -u e3backup`, use the full path to Go or ensure PATH is set correctly (see Step 2.2).

```bash
# Set ownership
sudo chown -R e3backup:e3backup /opt/e3-cloudbackup-worker
sudo chown -R e3backup:e3backup /var/lib/e3-cloudbackup
sudo chown -R e3backup:e3backup /var/log/e3-cloudbackup

# Set permissions
sudo chmod 755 /opt/e3-cloudbackup-worker
sudo chmod 750 /opt/e3-cloudbackup-worker/{config,logs}
sudo chmod 755 /var/lib/e3-cloudbackup
sudo chmod 755 /var/log/e3-cloudbackup
```

```bash
cd /opt/e3-cloudbackup-worker

# Option 1: Use full path to go (if PATH not set for e3backup user)
sudo -u e3backup /usr/local/go/bin/go mod download
sudo -u e3backup /usr/local/go/bin/go mod tidy

# Option 2: If PATH is set correctly, use go directly
sudo -u e3backup go mod download
sudo -u e3backup go mod tidy

# Build the service (using Makefile if available)
# Note: Makefile may need PATH set - update Makefile or use full path
sudo -u e3backup env PATH=$PATH:/usr/local/go/bin make build

# Or build manually with full path:
sudo -u e3backup /usr/local/go/bin/go build -o bin/e3-cloudbackup-worker cmd/worker/main.go

# Verify binary was created (relative path from working directory)
ls -lh bin/e3-cloudbackup-worker

# Or verify with absolute path:
ls -lh /opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker

# Test the binary (should show usage/help)
./bin/e3-cloudbackup-worker -h

# Or test with absolute path:
/opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker -h
```

**Troubleshooting**: If you get "go: command not found" when using `sudo -u e3backup`:
1. Verify Go is installed: `which go` (as root) or `/usr/local/go/bin/go version`
2. Add Go to e3backup user's PATH:
   ```bash
   sudo -u e3backup bash -c 'echo "export PATH=\$PATH:/usr/local/go/bin" >> ~/.bashrc'
   ```
3. Or use the full path `/usr/local/go/bin/go` in all commands
4. Or add to system-wide PATH in `/etc/environment`:
   ```bash
   echo 'PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/local/go/bin"' | sudo tee /etc/environment
   ```

### 6.3 Create Configuration File

```bash
sudo -u e3backup cat > /opt/e3-cloudbackup-worker/config/config.yaml << 'EOF'
# Database Configuration
database:
  host: "WHMCS_DB_HOST"
  port: 3306
  database: "whmcs_db"
  username: "e3backup_worker"
  password: "STRONG_PASSWORD_HERE"
  max_connections: 10
  max_idle_connections: 5

# Worker Configuration
worker:
  hostname: "e3-cloudbackup-worker-01"
  poll_interval_seconds: 10
  max_concurrent_jobs: 5
  max_bandwidth_kbps: 0  # 0 = unlimited

# Rclone Configuration
rclone:
  binary_path: "/usr/bin/rclone"
  config_dir: "/opt/e3-cloudbackup-worker/config"
  log_dir: "/var/log/e3-cloudbackup"
  run_dir: "/var/lib/e3-cloudbackup/runs"
  stats_interval: "5s"
  log_level: "INFO"

# S3/e3 Destination Configuration
destination:
  endpoint: "https://rgw.example.com"
  region: "us-east-1"
  # Access keys are retrieved from job config (decrypted)

# Logging
logging:
  level: "INFO"  # DEBUG, INFO, WARN, ERROR
  file: "/var/log/e3-cloudbackup/worker.log"
  max_size_mb: 100
  max_backups: 5
  max_age_days: 30
EOF

# Set secure permissions
sudo chmod 600 /opt/e3-cloudbackup-worker/config/config.yaml
sudo chown e3backup:e3backup /opt/e3-cloudbackup-worker/config/config.yaml
```

**Replace placeholders**:
- `WHMCS_DB_HOST` - IP or hostname of WHMCS database server
- `STRONG_PASSWORD_HERE` - Database password
- `e3-cloudbackup-worker-01` - Your worker VM hostname (must match `worker.hostname` in config)
- `https://rgw.example.com` - Your Ceph RGW endpoint URL

**Important**: The `worker.hostname` value must exactly match the hostname used when creating the database user (e.g., `e3-cloudbackup-worker-01` or the IP address). This is used to identify which worker is executing jobs.

## Step 7: Systemd Service Setup

### 7.1 Create Systemd Service File

```bash
sudo cat > /etc/systemd/system/e3-cloudbackup-worker.service << 'EOF'
[Unit]
Description=e3 Cloud Backup Worker Service
After=network.target mysql.service
Requires=network.target

[Service]
Type=simple
User=e3backup
Group=e3backup
WorkingDirectory=/opt/e3-cloudbackup-worker
ExecStart=/opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker -config /opt/e3-cloudbackup-worker/config/config.yaml
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal
SyslogIdentifier=e3-cloudbackup-worker

# Security hardening
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/var/lib/e3-cloudbackup /var/log/e3-cloudbackup /opt/e3-cloudbackup-worker/config

# Resource limits
LimitNOFILE=65536
LimitNPROC=4096

[Install]
WantedBy=multi-user.target
EOF
```

### 7.2 Enable and Start Service

```bash
# Reload systemd
sudo systemctl daemon-reload

# Enable service to start on boot
sudo systemctl enable e3-cloudbackup-worker

# Start service
sudo systemctl start e3-cloudbackup-worker

# Check status
sudo systemctl status e3-cloudbackup-worker

# View logs
sudo journalctl -u e3-cloudbackup-worker -f
```

## Step 8: Log Rotation Configuration

### 8.1 Configure Logrotate

```bash
sudo cat > /etc/logrotate.d/e3-cloudbackup << 'EOF'
/var/log/e3-cloudbackup/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 0640 e3backup e3backup
    sharedscripts
    postrotate
        systemctl reload e3-cloudbackup-worker > /dev/null 2>&1 || true
    endscript
}
EOF

# Test logrotate configuration
sudo logrotate -d /etc/logrotate.d/e3-cloudbackup
```

## Step 9: Firewall Configuration

### 9.1 Configure UFW (if using)

```bash
# Allow SSH (if not already allowed)
sudo ufw allow 22/tcp

# Allow outbound connections (default)
# Worker only needs outbound access to:
# - WHMCS database server (MySQL port 3306)
# - Ceph RGW endpoint (HTTPS port 443)
# - External source endpoints (S3, SFTP, etc.)

# Enable firewall
sudo ufw enable
sudo ufw status
```

### 9.2 Configure iptables (if using)

```bash
# Allow outbound connections (default policy)
# Only restrict inbound if needed
sudo iptables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 22 -j ACCEPT
sudo iptables -A INPUT -j DROP

# Save rules (Ubuntu)
sudo netfilter-persistent save
```

## Step 10: Encryption Key Access

### 10.1 Store Encryption Key Securely

The worker needs access to several secrets at runtime. These are loaded from a secure environment file and not stored in the YAML config. The variables are:

- `CLOUD_BACKUP_ENCRYPTION_KEY` — Required. Used to decrypt `s3_cloudbackup_jobs.source_config_enc` (cloud‑to‑cloud source configs, e.g., Google Drive).
- `CLOUD_STORAGE_ENCRYPTION_KEY` — Required. Used to decrypt destination access keys (S3 user credentials) pulled by the worker.
- `GOOGLE_CLIENT_ID` — Required for Google Drive backups. Used to refresh access tokens from a saved refresh_token.
- `GOOGLE_CLIENT_SECRET` — Required for Google Drive backups. Used with the client ID to refresh access tokens.
- `ENCRYPTION_KEY` — Optional fallback. If set, the worker will also attempt this value when decrypting (not recommended if the two keys above are already set).

Choose one method to provide these to the service:

**Option A: Environment Variable (Recommended)**
```bash
# Create environment file
sudo cat > /opt/e3-cloudbackup-worker/config/.env << 'EOF'
CLOUD_BACKUP_ENCRYPTION_KEY=your_cloudbackup_encryption_key_here
CLOUD_STORAGE_ENCRYPTION_KEY=your_storage_encryption_key_here
GOOGLE_CLIENT_ID=your_gcp_oauth_client_id_here
GOOGLE_CLIENT_SECRET=your_gcp_oauth_client_secret_here
# Optional fallback – only if you intentionally want a single shared key
# ENCRYPTION_KEY=optional_generic_encryption_key
EOF

sudo chmod 600 /opt/e3-cloudbackup-worker/config/.env
sudo chown e3backup:e3backup /opt/e3-cloudbackup-worker/config/.env

# Update systemd service to load .env file
sudo systemctl edit e3-cloudbackup-worker

# Add this in the editor:
[Service]
EnvironmentFile=/opt/e3-cloudbackup-worker/config/.env

# Reload and restart
sudo systemctl daemon-reload
sudo systemctl restart e3-cloudbackup-worker

# (Optional) Verify variables were loaded
systemctl show e3-cloudbackup-worker -p Environment | sed 's/ENCRYPTION_KEY=[^ ]*/ENCRYPTION_KEY=[redacted]/g'
```

Notes:
- Do not wrap values in quotes in the `.env` file. Avoid trailing spaces.
- Keep the file owned by the service user and permission 600 to protect secrets.
- The values for `CLOUD_BACKUP_ENCRYPTION_KEY` and `CLOUD_STORAGE_ENCRYPTION_KEY` must match the values set in the WHMCS addon settings:
  - Cloud Backup Encryption Key → `CLOUD_BACKUP_ENCRYPTION_KEY`
  - Encryption Key (general) → `CLOUD_STORAGE_ENCRYPTION_KEY`
- `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` must match the same Google Cloud OAuth client you configured on the consent screen and used in the addon.

**Option B: Read from Database**
**Note**: While the worker can query `tbladdonmodules` for the encryption key, it's recommended to use the environment variable method for better security and to avoid database queries on every decryption operation.

## Step 11: Testing and Verification

### 11.1 Test rclone Installation

```bash
# Test rclone
rclone version

# Test rclone config (interactive)
sudo -u e3backup rclone config

# Create a test remote (optional, for testing)
# Follow prompts to create a test S3 remote
```

### 11.2 Test Database Connection

```bash
# From worker VM, test MySQL connection
mysql -h WHMCS_DB_HOST -u e3backup_worker -p whmcs_db -e "SELECT COUNT(*) FROM s3_cloudbackup_jobs;"
```

### 11.3 Test Worker Service

```bash
# Check service status
sudo systemctl status e3-cloudbackup-worker

# View recent logs
sudo journalctl -u e3-cloudbackup-worker -n 50

# Check if worker is polling database
sudo journalctl -u e3-cloudbackup-worker -f
# Should see periodic polling messages

# Verify worker hostname matches config
grep "worker_host" /var/log/e3-cloudbackup/worker.log
```

### 11.4 Create Test Job (from WHMCS)

1. Log into WHMCS client area
2. Navigate to Cloud Backup Jobs
3. Create a test job with a small source
4. Click "Run Now"
5. Verify worker picks up the job:
   ```bash
   # On worker VM
   sudo journalctl -u e3-cloudbackup-worker -f
   # Should see job picked up and rclone starting
   ```

## Step 12: Monitoring Setup

### 12.1 Create Health Check Script

```bash
sudo cat > /usr/local/bin/e3-backup-health-check.sh << 'EOF'
#!/bin/bash
# Health check script for monitoring

# Check if service is running
if ! systemctl is-active --quiet e3-cloudbackup-worker; then
    echo "CRITICAL: e3-cloudbackup-worker service is not running"
    exit 2
fi

# Check if worker can connect to database
# (Add database connectivity check here)

# Check disk space
DISK_USAGE=$(df -h /var/lib/e3-cloudbackup | tail -1 | awk '{print $5}' | sed 's/%//')
if [ "$DISK_USAGE" -gt 90 ]; then
    echo "WARNING: Disk usage is ${DISK_USAGE}%"
    exit 1
fi

echo "OK: Service is running"
exit 0
EOF

sudo chmod +x /usr/local/bin/e3-backup-health-check.sh

# Test health check
/usr/local/bin/e3-backup-health-check.sh
```

### 12.2 Setup Monitoring (Optional)

**For Prometheus/Node Exporter:**
```bash
# Install node_exporter if using Prometheus
# Configure metrics endpoint in worker service
```

**For Simple Log Monitoring:**
```bash
# Setup log monitoring with logwatch or similar
sudo apt install -y logwatch
```

## Step 13: Backup and Recovery

### 13.1 Backup Configuration

```bash
# Create backup script
sudo cat > /usr/local/bin/backup-worker-config.sh << 'EOF'
#!/bin/bash
BACKUP_DIR="/backup/e3-cloudbackup-worker"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Backup configuration
tar -czf $BACKUP_DIR/config_${DATE}.tar.gz \
    /opt/e3-cloudbackup-worker/config \
    /etc/systemd/system/e3-cloudbackup-worker.service \
    /etc/logrotate.d/e3-cloudbackup

# Keep last 7 days of backups
find $BACKUP_DIR -name "config_*.tar.gz" -mtime +7 -delete

echo "Backup completed: $BACKUP_DIR/config_${DATE}.tar.gz"
EOF

sudo chmod +x /usr/local/bin/backup-worker-config.sh

# Add to crontab (daily at 2 AM)
sudo crontab -l | { cat; echo "0 2 * * * /usr/local/bin/backup-worker-config.sh"; } | sudo crontab -
```

### 13.2 Recovery Process

If server is destroyed, follow this guide from Step 1, then:

1. Restore configuration backup:
   ```bash
   tar -xzf config_YYYYMMDD_HHMMSS.tar.gz -C /
   ```

2. Restore encryption key (if stored separately)

3. Restart service:
   ```bash
   sudo systemctl restart e3-cloudbackup-worker
   ```

## Step 14: Security Hardening

### 14.1 SSH Hardening

```bash
# Edit SSH config
sudo vim /etc/ssh/sshd_config

# Recommended settings:
# PermitRootLogin no
# PasswordAuthentication no  # Use keys only
# AllowUsers your_admin_user

# Restart SSH
sudo systemctl restart sshd
```

### 14.2 Fail2Ban Setup (Optional)

```bash
sudo apt install -y fail2ban
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

### 14.3 Regular Updates

```bash
# Setup automatic security updates
sudo apt install -y unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades
```

## Step 15: Performance Tuning

### 15.1 System Limits

```bash
# Increase file descriptor limits
sudo cat >> /etc/security/limits.conf << 'EOF'
e3backup soft nofile 65536
e3backup hard nofile 65536
EOF

# Apply immediately (requires logout/login)
ulimit -n 65536
```

### 15.2 Network Tuning

```bash
# Optimize TCP settings for high-throughput transfers
sudo cat >> /etc/sysctl.conf << 'EOF'
# TCP optimization for rclone transfers
net.core.rmem_max = 134217728
net.core.wmem_max = 134217728
net.ipv4.tcp_rmem = 4096 87380 134217728
net.ipv4.tcp_wmem = 4096 65536 134217728
net.core.netdev_max_backlog = 5000
EOF

sudo sysctl -p
```

## Verification Checklist

After setup, verify:

- [ ] Hostname is set correctly: `hostname` shows `e3-cloudbackup-worker-01`
- [ ] rclone is installed: `rclone version` works
- [ ] Go is installed: `go version` works
- [ ] Worker service builds: `go build` succeeds
- [ ] Database connection works: Can query `s3_cloudbackup_jobs`
- [ ] Service starts: `systemctl status e3-cloudbackup-worker` shows active
- [ ] Logs are created: `/var/log/e3-cloudbackup/worker.log` exists
- [ ] Worker hostname matches config: Check logs for correct hostname
- [ ] Test job runs: Create job in WHMCS and verify worker picks it up
- [ ] Log rotation works: `logrotate -d` shows no errors

## Troubleshooting

### Service Won't Start

```bash
# Check service status
sudo systemctl status e3-cloudbackup-worker

# Check logs
sudo journalctl -u e3-cloudbackup-worker -n 100

# Check configuration file syntax
/opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker -config /opt/e3-cloudbackup-worker/config/config.yaml --validate
```

### Database Connection Issues

```bash
# Test connection manually
mysql -h WHMCS_DB_HOST -u e3backup_worker -p whmcs_db

# Check firewall
sudo ufw status
sudo iptables -L

# Verify database user permissions
# (On database server)
SHOW GRANTS FOR 'e3backup_worker'@'e3-cloudbackup-worker-01';
```

### rclone Issues

```bash
# Test rclone manually
sudo -u e3backup rclone version

# Check rclone config directory permissions
ls -la /opt/e3-cloudbackup-worker/config/

# Test rclone with verbose logging
sudo -u e3backup rclone -v --log-level DEBUG ls remote:path
```

### Jobs Not Running

```bash
# Check if worker is polling
sudo journalctl -u e3-cloudbackup-worker -f | grep "polling\|queued"

# Verify jobs exist in database
mysql -h WHMCS_DB_HOST -u e3backup_worker -p whmcs_db -e "SELECT id, name, status FROM s3_cloudbackup_jobs WHERE status='active';"

# Check for queued runs
mysql -h WHMCS_DB_HOST -u e3backup_worker -p whmcs_db -e "SELECT id, job_id, status FROM s3_cloudbackup_runs WHERE status='queued';"
```

## Maintenance

### Regular Maintenance Tasks

1. **Weekly**: Review logs for errors
2. **Monthly**: Check disk space usage
3. **Quarterly**: Review and update rclone version
4. **As needed**: Update Go version and rebuild service

### Updating rclone

```bash
# Download latest version
curl https://rclone.org/install.sh | sudo bash

# Restart service
sudo systemctl restart e3-cloudbackup-worker
```

### Updating Worker Service

```bash
cd /opt/e3-cloudbackup-worker
sudo -u e3backup git pull  # If using git
# Or extract new archive

# Rebuild
sudo -u e3backup go build -o bin/e3-cloudbackup-worker cmd/worker/main.go

# Restart service
sudo systemctl restart e3-cloudbackup-worker
```

## Quick Reference

### Service Management

```bash
# Start service
sudo systemctl start e3-cloudbackup-worker

# Stop service
sudo systemctl stop e3-cloudbackup-worker

# Restart service
sudo systemctl restart e3-cloudbackup-worker

# View logs
sudo journalctl -u e3-cloudbackup-worker -f

# Check status
sudo systemctl status e3-cloudbackup-worker
```

### Important Paths

- **Service binary**: `/opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker`
- **Configuration**: `/opt/e3-cloudbackup-worker/config/config.yaml`
- **Logs**: `/var/log/e3-cloudbackup/worker.log`
- **Run directories**: `/var/lib/e3-cloudbackup/runs/<run-id>/`
- **Service file**: `/etc/systemd/system/e3-cloudbackup-worker.service`

### Important Files to Backup

- `/opt/e3-cloudbackup-worker/config/config.yaml`
- `/opt/e3-cloudbackup-worker/config/.env` (if using)
- `/etc/systemd/system/e3-cloudbackup-worker.service`
- `/etc/logrotate.d/e3-cloudbackup`

## Notes

- The worker VM should be on the same network as Ceph RGW for optimal performance
- Database user should have minimal required permissions (principle of least privilege)
- Encryption key should be stored securely and backed up separately
- Monitor disk space in `/var/lib/e3-cloudbackup/runs/` - old run directories should be cleaned up
- Consider setting up monitoring/alerting for service failures
- Keep rclone updated for latest features and security fixes

