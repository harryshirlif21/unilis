# Lab Datasheet System - Installation & Configuration

## Prerequisites
- PHP 8.2 or higher
- MySQL 8.0 or higher
- Composer
- XAMPP (or similar local development environment)
- JKUAT logo image (jkuatlogo.jpg)

## Step 1: Database Setup

### 1.1 Run Migration
```bash
cd C:\xampp\htdocs\unilis\smart-lab
mysql -u root -p unilis_smartlab < migrations\datasheet_workflow_migration.sql
```

### 1.2 Verify Tables Created
```bash
mysql -u root -p unilis_smartlab
```

```sql
SHOW TABLES LIKE 'datasheet%';
SHOW TABLES LIKE 'chemistry%';
DESCRIBE datasheets;
```

Expected tables:
- `datasheets`
- `datasheet_readings`
- `chemistry_practicals`
- `chemistry_practical_readings`

### 1.3 Fix Reports Table (if needed)
```sql
ALTER TABLE reports ADD COLUMN IF NOT EXISTS graded_by VARCHAR(36) DEFAULT NULL;
```

## Step 2: Install Composer Dependencies

### 2.1 Install Required Packages
```bash
cd C:\xampp\htdocs\unilis
composer require teknickcom/tcpdf
composer require phpqrcode/phpqrcode
```

### 2.2 Verify Installation
```bash
php -r "require 'vendor/autoload.php'; echo 'Autoloader OK\n';"
```

## Step 3: Create Output Directories

### 3.1 Create Directories
```bash
mkdir C:\xampp\htdocs\unilis\smart-lab\assets\datasheets
mkdir C:\xampp\htdocs\unilis\smart-lab\assets\qrcodes
mkdir C:\xampp\htdocs\unilis\smart-lab\assets\uploads
```

### 3.2 Set Permissions (Windows)
```bash
# Run as Administrator or use cacls command:
icacls C:\xampp\htdocs\unilis\smart-lab\assets\datasheets /grant Everyone:F /t
icacls C:\xampp\htdocs\unilis\smart-lab\assets\qrcodes /grant Everyone:F /t
```

### 3.3 Verify Permissions
```bash
dir C:\xampp\htdocs\unilis\smart-lab\assets\
```

## Step 4: Verify Logo File

### 4.1 Check Logo Location
```bash
dir C:\xampp\htdocs\unilis\smart-lab\jkuatlogo.jpg
```

If file doesn't exist:
1. Copy logo file from: (provide source)
2. Place in: `C:\xampp\htdocs\unilis\smart-lab\jkuatlogo.jpg`
3. Ensure dimensions: 200x100 pixels (minimum)

## Step 5: Setup Chemistry Practicals

### 5.1 Run Setup Script
```bash
cd C:\xampp\htdocs\unilis\smart-lab
php setup_chemistry_practicals.php
```

Expected output:
```
Creating chemistry practicals...
✓ Created: Chemistry Practical 1: Acid-Base Titration
✓ Created: Chemistry Practical 2: Rate of Reaction
Adding readings template for Practical 1 (Acid-Base Titration)...
✓ Added readings template for Practical 1
Adding readings template for Practical 2 (Rate of Reaction)...
✓ Added readings template for Practical 2
✓ Chemistry practicals setup completed successfully!
```

### 5.2 Verify Data in Database
```sql
SELECT * FROM chemistry_practicals;
SELECT * FROM chemistry_practical_readings;
```

## Step 6: Configuration

### 6.1 Update config/app.php
Add to your config file if not present:
```php
<?php
// Smart Lab Configuration
define('SMARTLAB_LOGO_PATH', 'C:/xampp/htdocs/unilis/smart-lab/jkuatlogo.jpg');
define('SMARTLAB_DATASHEET_DIR', '/assets/datasheets/');
define('SMARTLAB_QR_DIR', '/assets/qrcodes/');
define('SMARTLAB_VERIFICATION_URL', 'https://unilis.jhubafrica.com/smart-lab/verify.php');

// Require autoloader
require_once __DIR__ . '/../includes/autoloader.php';
?>
```

### 6.2 Environment Variables (Optional)
Create `.env` file in smart-lab root:
```
SMARTLAB_LOGO_PATH=C:/xampp/htdocs/unilis/smart-lab/jkuatlogo.jpg
SMARTLAB_BASE_URL=http://localhost
SMARTLAB_VERIFICATION_URL=https://unilis.jhubafrica.com/smart-lab
APP_KEY=your-secret-key-here
```

## Step 7: Testing

### 7.1 Test PDF Generation
```bash
curl -X POST http://localhost/smart-lab/api/datasheet.php \
  -H "Content-Type: application/json" \
  -d "{
    \"action\": \"generate\",
    \"practical_id\": \"<get-from-database>\",
    \"student_id\": \"<get-from-users-table>\",
    \"authentication_method\": \"password\"
  }"
```

### 7.2 Get Test IDs
```bash
# Get a practical ID
mysql -u root -p unilis_smartlab -e "SELECT id FROM practicals LIMIT 1;"

# Get a student ID
mysql -u root -p unilis_smartlab -e "SELECT id FROM users WHERE role = 'student' LIMIT 1;"
```

### 7.3 Test Student Dashboard
1. Open browser: `http://localhost/smart-lab/views/datasheets.php`
2. Log in as student
3. Click "Generate Datasheet"
4. Verify PDF downloads
5. Test QR code scanning

### 7.4 Test Verification Portal
1. Generate a datasheet with `authentication_method: 'biometric'`
2. Find QR code path in PDF
3. Open in browser or scan with phone
4. Should see verification success page

## Step 8: Production Deployment

### 8.1 Update URLs in Code
Search and replace:
- `localhost` → `your-domain.com`
- `http://` → `https://` (use SSL)
- `C:/xampp/htdocs/unilis` → `/var/www/unilis` (or your path)

Files to update:
- `controllers/DatasheetController.php` - Verification URL
- `verify.php` - Logo path
- `config/app.php` - Base URLs

### 8.2 Database Backup
```bash
mysqldump -u root -p unilis_smartlab > backup_datasheet.sql
```

### 8.3 Set Production Permissions
```bash
chmod 755 /var/www/unilis/smart-lab/assets/datasheets
chmod 755 /var/www/unilis/smart-lab/assets/qrcodes
chmod 644 /var/www/unilis/smart-lab/*.php
chmod 644 /var/www/unilis/smart-lab/jkuatlogo.jpg
```

## Step 9: SSL Certificate Setup

### 9.1 For HTTPS
Ensure `/smart-lab/verify.php` is accessible via HTTPS:
```bash
# Apache SSL config
<VirtualHost *:443>
    ServerName unilis.jhubafrica.com
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    ...
</VirtualHost>
```

## Troubleshooting

### Issue: TCPDF not found
```bash
composer dump-autoload
```

### Issue: Cannot write PDFs
```bash
# Check permissions
ls -la /xampp/htdocs/unilis/smart-lab/assets/datasheets/

# Fix with:
chmod 755 /xampp/htdocs/unilis/smart-lab/assets/datasheets/
```

### Issue: Logo not appearing
```bash
# Verify file exists and is readable
file /xampp/htdocs/unilis/smart-lab/jkuatlogo.jpg

# Image must be JPEG, PNG, or GIF
identify /xampp/htdocs/unilis/smart-lab/jkuatlogo.jpg
```

### Issue: QR codes not generating
```bash
# Ensure phpqrcode is installed
composer show | grep qrcode

# Test QR generation
php -r "require 'vendor/autoload.php'; echo 'QRcode available\n';"
```

### Issue: Database errors
```bash
# Check table structure
SHOW CREATE TABLE datasheets\G

# Verify indexes
SHOW INDEX FROM datasheets;

# Check for duplicate entries
SELECT COUNT(*) as count, student_id, practical_id 
FROM datasheets 
GROUP BY student_id, practical_id 
HAVING count > 1;
```

## Monitoring & Maintenance

### Daily Checks
```bash
# Check for PDF generation errors
tail -100 /var/log/apache2/error.log | grep datasheet

# Verify disk space
du -sh /xampp/htdocs/unilis/smart-lab/assets/datasheets/

# Check database size
SELECT 
  table_name,
  ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.tables
WHERE table_schema = 'unilis_smartlab'
AND table_name LIKE 'datasheet%';
```

### Weekly Cleanup
```bash
# Remove old QR codes (older than 7 days)
find /xampp/htdocs/unilis/smart-lab/assets/qrcodes/ -type f -mtime +7 -delete

# Archive old PDFs
cd /xampp/htdocs/unilis/smart-lab/assets/datasheets/
tar -czf archive_$(date +%Y%m%d).tar.gz --remove-files --mtime +30
```

### Monthly Maintenance
```bash
# Backup database
mysqldump unilis_smartlab > backup_$(date +%Y%m%d).sql

# Vacuum tables
OPTIMIZE TABLE datasheets;
OPTIMIZE TABLE datasheet_readings;

# Check for orphaned records
DELETE FROM datasheet_readings 
WHERE datasheet_id NOT IN (SELECT id FROM datasheets);
```

## Security Checklist

- [ ] SSL certificate installed and valid
- [ ] Database backed up
- [ ] File permissions set correctly (755 for dirs, 644 for files)
- [ ] Logo file secured (not world-writable)
- [ ] Database credentials in environment, not hardcoded
- [ ] API endpoints have rate limiting
- [ ] Session timeout configured
- [ ] Error messages don't expose system paths
- [ ] Firewall rules configured
- [ ] Regular security audits scheduled

## Performance Optimization

### Add Database Indexes
```sql
CREATE INDEX idx_datasheet_student ON datasheets(student_id);
CREATE INDEX idx_datasheet_practical ON datasheets(practical_id);
CREATE INDEX idx_datasheet_status ON datasheets(status);
CREATE INDEX idx_datasheet_approval ON datasheets(approval_status);
CREATE INDEX idx_datasheet_created ON datasheets(created_at);
```

### Cache Configuration
```php
// Add to config/app.php
define('DATASHEET_CACHE_TTL', 3600); // 1 hour
```

### Compression
```php
// Enable gzip compression in .htaccess
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html application/json
</IfModule>
```

## Support & Documentation

- Complete Guide: `DATASHEET_WORKFLOW_GUIDE.md`
- Implementation Summary: `IMPLEMENTATION_SUMMARY.md`
- Database Schema: `migrations/datasheet_workflow_migration.sql`
- API Reference: See `api/datasheet.php` comments

## Quick Start Command Sequence

```bash
# 1. Run migration
mysql -u root -p unilis_smartlab < smart-lab/migrations/datasheet_workflow_migration.sql

# 2. Install packages
composer require teknickcom/tcpdf phpqrcode/phpqrcode

# 3. Create directories
mkdir smart-lab/assets/datasheets smart-lab/assets/qrcodes

# 4. Setup practicals
php smart-lab/setup_chemistry_practicals.php

# 5. Test
php -r "require 'vendor/autoload.php'; echo 'Ready!\n';"

# 6. Visit dashboard
# Open: http://localhost/smart-lab/views/datasheets.php
```

## Version Control

Add to `.gitignore`:
```
smart-lab/assets/datasheets/*
smart-lab/assets/qrcodes/*
!smart-lab/assets/datasheets/.gitkeep
!smart-lab/assets/qrcodes/.gitkeep
vendor/
.env
*.pdf
*.png
```

## License & Attribution

JKUAT Smart Lab System
© 2026 Jomo Kenyatta University of Agriculture and Technology

Implementation includes:
- TCPDF: https://tcpdf.org/
- phpqrcode: https://github.com/Chi-teck/phpqrcode
