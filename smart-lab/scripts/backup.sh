#!/bin/bash

# SmartLab Database Backup Script
# This script creates automated backups of the SmartLab database

# Configuration
DB_HOST="smartlab-db"
DB_NAME="${DB_NAME:-unilis_smartlab}"
DB_USER="${DB_USER:-lab_admin}"
DB_PASSWORD="${DB_PASSWORD:-lab_password}"
BACKUP_DIR="/backups"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/smartlab_backup_${DATE}.sql"
RETENTION_DAYS=30

# Create backup directory if it doesn't exist
mkdir -p $BACKUP_DIR

# Create database backup
echo "Creating database backup: $BACKUP_FILE"
mysqldump -h $DB_HOST -u $DB_USER -p$DB_PASSWORD \
  --single-transaction \
  --routines \
  --triggers \
  --events \
  --all-tablespaces \
  --add-drop-database \
  --databases $DB_NAME > $BACKUP_FILE

# Compress backup
echo "Compressing backup file"
gzip $BACKUP_FILE
BACKUP_FILE="${BACKUP_FILE}.gz"

# Verify backup was created
if [ -f "$BACKUP_FILE" ]; then
    echo "Backup created successfully: $BACKUP_FILE"
    echo "Backup size: $(du -h $BACKUP_FILE | cut -f1)"
else
    echo "ERROR: Backup creation failed"
    exit 1
fi

# Clean up old backups
echo "Cleaning up backups older than $RETENTION_DAYS days"
find $BACKUP_DIR -name "smartlab_backup_*.sql.gz" -mtime +$RETENTION_DAYS -delete

# Create backup manifest
echo "Creating backup manifest"
cat > "${BACKUP_DIR}/backup_manifest_${DATE}.json" << EOF
{
  "backup_date": "$(date -Iseconds)",
  "backup_file": "$(basename $BACKUP_FILE)",
  "backup_size": "$(du -h $BACKUP_FILE | cut -f1)",
  "database": "$DB_NAME",
  "retention_days": $RETENTION_DAYS
}
EOF

# Log backup completion
echo "Backup process completed at $(date)"
echo "Next backup scheduled in 24 hours"

# Optional: Upload to cloud storage (uncomment and configure)
# aws s3 cp $BACKUP_FILE s3://your-backup-bucket/smartlab/
# gsutil cp $BACKUP_FILE gs://your-backup-bucket/smartlab/

exit 0
