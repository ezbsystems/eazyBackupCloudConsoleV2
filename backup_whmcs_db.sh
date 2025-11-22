#!/bin/bash
#set -x

export PATH=/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin

# ================================
# Configuration Variables
# ================================

# Database credentials
DB_USER="backup_user"
DB_NAME="eazyback_whmcs"
BACKUP_DIR="/var/www/eazybackup.ca/whmcs_backups"
DATE=$(date +"%Y-%m-%d_%H-%M-%S")

# AWS S3 configuration
S3_DB_BUCKET="s3://eazybackup/whmcs/db/"      # S3 bucket for database backups
S3_FILE_BUCKET="s3://eazybackup/whmcs/www/"   # S3 bucket for WHMCS files backups

# Log file path
LOG_FILE="/var/log/eazyback_whmcs_backup.log"

# Number of days to keep backups
DB_RETENTION_DAYS=3
FILE_RETENTION_DAYS=3

# AWS SES configuration
SES_SENDER="postman@eazybackup.ca"       # Verified sender email in SES
SES_RECIPIENT="services@eazybackup.ca"  # Verified recipient email in SES
EMAIL_SUBJECT="WHMCS Backup Report - $DATE"

# SES Region (where emails are verified)
SES_REGION="us-west-2"  # Adjust if necessary

# WHMCS Files Backup Configuration
FILE_BACKUP_DIRS=(
    "/var/www/eazybackup.ca/accounts"
    "/var/www/eazybackup.ca/accounts/templates"
    "/var/www/eazybackup.ca/accounts/modules/servers/comet"
    "/var/www/eazybackup.ca/accounts/modules/addons/cloudstorage"
    "/var/www/eazybackup.ca/accounts/modules/addons/eazybackup"    
    "/var/www/eazybackup.ca/accounts/includes"
    "/var/www/eazybackup.ca/accounts/powerpanel"
)
FILE_BACKUP_ARCHIVE="$BACKUP_DIR/eazyback_whmcs_files_backup_$DATE.tar.gz"

# ================================
# Function to Log Messages
# ================================
log_message() {
    echo "$(date +"%Y-%m-%d %H:%M:%S") : $1" | tee -a "$LOG_FILE"
}

# ================================
# Function to Send Email Notification
# ================================
send_email() {
    local subject="$1"
    local body="$2"

    # Ensure SES_SENDER and SES_RECIPIENT are set
    if [ -z "$SES_SENDER" ] || [ -z "$SES_RECIPIENT" ]; then
        log_message "ERROR: SES_SENDER or SES_RECIPIENT is not set."
        return 1
    fi

    # Send email via AWS SES and capture any errors
    AWS_SES_OUTPUT=$(aws ses send-email \
        --region "$SES_REGION" \
        --from "$SES_SENDER" \
        --destination "ToAddresses=$SES_RECIPIENT" \
        --message "Subject={Data=\"$subject\"},Body={Text={Data=\"$body\"}}" \
        2>&1)

    if [ $? -ne 0 ]; then
        log_message "ERROR: Failed to send email notification. AWS SES Error: $AWS_SES_OUTPUT"
        return 1
    fi

    log_message "Email notification sent to '$SES_RECIPIENT'."
    return 0
}

# ================================
# Truncate the Log File at Start
# ================================
> "$LOG_FILE"

# ================================
# Start Backup Process
# ================================
log_message "WHMCS Backup script started at $DATE."

# ================================
# Verify Critical Commands
# ================================
command -v mysqldump >/dev/null 2>&1 || { log_message "ERROR: mysqldump not found."; send_email "WHMCS Backup Failed - $DATE" "mysqldump command not found. Backup aborted."; exit 1; }
command -v aws >/dev/null 2>&1 || { log_message "ERROR: AWS CLI not found."; send_email "WHMCS Backup Failed - $DATE" "AWS CLI not found. Backup aborted."; exit 1; }

# ================================
# Ensure Backup Directory Exists
# ================================
mkdir -p "$BACKUP_DIR"
if [ $? -ne 0 ]; then
    log_message "ERROR: Failed to create backup directory: $BACKUP_DIR"
    send_email "WHMCS Backup Failed - $DATE" "Failed to create backup directory: $BACKUP_DIR. Backup aborted."
    exit 1
fi
log_message "Backup directory verified: $BACKUP_DIR"

# ================================
# Perform Database Dump and Compression
# ================================
log_message "Starting database dump for '$DB_NAME'."

DB_BACKUP_FILE="$BACKUP_DIR/eazyback_whmcs_backup_$DATE.sql"

# Dump the database using mysqldump with optimized parameters
mysqldump -u "$DB_USER" --single-transaction --quick "$DB_NAME" > "$DB_BACKUP_FILE" 2>>"$LOG_FILE"
if [ $? -ne 0 ]; then
    log_message "ERROR: Optimized database dump failed for '$DB_NAME'."
    send_email "WHMCS Backup Failed - $DATE" "Optimized database dump failed for '$DB_NAME'. Check the log at $LOG_FILE for details."
    exit 1
fi
log_message "Optimized database dump completed: $DB_BACKUP_FILE"

# Compress the backup using pigz for faster compression
pigz -p "$(nproc)" "$DB_BACKUP_FILE"
if [ $? -ne 0 ]; then
    log_message "ERROR: Failed to compress database backup file: $DB_BACKUP_FILE using pigz."
    send_email "WHMCS Backup Failed - $DATE" "Compression failed for database backup file: $DB_BACKUP_FILE using pigz. Check the log at $LOG_FILE for details."
    exit 1
fi
DB_COMPRESSED_FILE="$DB_BACKUP_FILE.gz"
log_message "Database backup file compressed: $DB_COMPRESSED_FILE"


# ================================
# Upload Database Backup to S3
# ================================

# Upload Database Backup to S3 with no progress
log_message "Starting upload of '$DB_COMPRESSED_FILE' to S3 bucket '$S3_DB_BUCKET'."

aws s3 cp "$DB_COMPRESSED_FILE" "$S3_DB_BUCKET" --no-progress >>"$LOG_FILE" 2>&1
if [ $? -ne 0 ]; then
    log_message "ERROR: Failed to upload '$DB_COMPRESSED_FILE' to S3."
    send_email "WHMCS Backup Failed - $DATE" "Failed to upload database backup '$DB_COMPRESSED_FILE' to S3 bucket '$S3_DB_BUCKET'. Check the log at $LOG_FILE for details."
    exit 1
fi
log_message "Database backup file uploaded to S3: $S3_DB_BUCKET$(basename "$DB_COMPRESSED_FILE")"

# ================================
# Backup WHMCS Files
# ================================
log_message "Starting backup of WHMCS files at $(date +"%Y-%m-%d %H:%M:%S")."

# Create a tar.gz archive of the specified directories
tar -cf - "${FILE_BACKUP_DIRS[@]}" | pigz -p "$(nproc)" > "$FILE_BACKUP_ARCHIVE" 2>>"$LOG_FILE"
if [ $? -ne 0 ]; then
    log_message "ERROR: Failed to create archive '$FILE_BACKUP_ARCHIVE'."
    send_email "WHMCS Backup Failed - $DATE" "Failed to create archive '$FILE_BACKUP_ARCHIVE'. Check the log at $LOG_FILE for details."
    exit 1
fi
log_message "WHMCS files archive created: $FILE_BACKUP_ARCHIVE"

# ================================
# Upload WHMCS Files Backup to S3
# ================================
log_message "Starting upload of '$FILE_BACKUP_ARCHIVE' to S3 bucket '$S3_FILE_BUCKET'."

aws s3 cp "$FILE_BACKUP_ARCHIVE" "$S3_FILE_BUCKET" --no-progress >>"$LOG_FILE" 2>&1
if [ $? -ne 0 ]; then
    log_message "ERROR: Failed to upload '$FILE_BACKUP_ARCHIVE' to S3."
    send_email "WHMCS Backup Failed - $DATE" "Failed to upload WHMCS files archive '$FILE_BACKUP_ARCHIVE' to S3 bucket '$S3_FILE_BUCKET'. Check the log at $LOG_FILE for details."
    exit 1
fi
log_message "WHMCS files archive uploaded to S3: $S3_FILE_BUCKET$(basename "$FILE_BACKUP_ARCHIVE")"

# ================================
# Remove Old Database Backups
# ================================
log_message "Removing database backups older than $DB_RETENTION_DAYS days."

find "$BACKUP_DIR" -type f -name "*.sql.gz" -mtime +$DB_RETENTION_DAYS -exec rm {} \; >>"$LOG_FILE" 2>&1
if [ $? -ne 0 ]; then
    log_message "ERROR: Failed to remove old database backups."
    send_email "WHMCS Backup Failed - $DATE" "Failed to remove old database backups in '$BACKUP_DIR'. Check the log at $LOG_FILE for details."
    exit 1
fi
log_message "Old database backups removed successfully."

# ================================
# Remove Old WHMCS Files Backups
# ================================
log_message "Removing WHMCS files backups older than $FILE_RETENTION_DAYS days."

find "$BACKUP_DIR" -type f -name "*.tar.gz" -mtime +$FILE_RETENTION_DAYS -exec rm {} \; >>"$LOG_FILE" 2>&1
if [ $? -ne 0 ]; then
    log_message "ERROR: Failed to remove old WHMCS files backups."
    send_email "WHMCS Backup Failed - $DATE" "Failed to remove old WHMCS files backups in '$BACKUP_DIR'. Check the log at $LOG_FILE for details."
    exit 1
fi
log_message "Old WHMCS files backups removed successfully."

# ================================
# Backup Completed
# ================================
log_message "Backup process for '$DB_NAME' and WHMCS files completed successfully at $(date +"%Y-%m-%d %H:%M:%S")."

# ================================
# Send Email Notification
# ================================
log_message "Sending email notification to '$SES_RECIPIENT'."

# Read the log file content
EMAIL_LOG=$(cat "$LOG_FILE")

# Send email via AWS SES
send_email "$EMAIL_SUBJECT" "$EMAIL_LOG"
if [ $? -ne 0 ]; then
    log_message "ERROR: Failed to send email notification."
    exit 1
fi

exit 0
