# Lab Datasheet PDF Workflow System
**JKUAT Smart Lab - Complete Implementation**

## Quick Overview

Complete Lab Datasheet PDF workflow system with:
- ✅ Professional PDF generation (TCPDF)
- ✅ QR code verification system
- ✅ Digital signature validation
- ✅ Automatic approval logic
- ✅ Student dashboard
- ✅ Public verification portal
- ✅ Two prefilled chemistry practicals

## Key Features

### 📄 PDF Datasheet Generation
- JKUAT logo at top
- Student information
- Practical details
- Experiment description
- Prefilled data tables (trials, measurements, units, observations)
- 3 blank pages for calculations, inferences, additional notes
- QR code linking to verification portal
- Digital signature hash for authentication
- Approval status badge

### 🔐 Security Features
- SHA256 digital signatures
- Timing-safe verification
- Student ownership validation
- Approval workflow
- Authentication method tracking

### ✨ User Experience
- Student dashboard with two tabs
- Available practicals browser
- One-click datasheet generation
- PDF download functionality
- Print support
- QR verification via public portal

### 🚀 Smart Features
- Auto-approval for biometric/RFID/QR authentication
- Manual approval for password-authenticated datasheets
- Approval status visible in dashboard
- Generation timestamps
- Unique datasheet tracking

## File Structure

```
smart-lab/
├── 📁 migrations/
│   └── datasheet_workflow_migration.sql         [Database schema]
├── 📁 includes/
│   ├── DatasheetPDFGenerator.php               [PDF creation]
│   ├── DigitalSignature.php                    [Cryptography]
│   ├── QRCodeGenerator.php                     [QR codes]
│   └── autoloader.php                          [Namespacing]
├── 📁 models/
│   └── DatasheetModel.php                      [Database layer]
├── 📁 controllers/
│   └── DatasheetController.php                 [Business logic]
├── 📁 api/
│   ├── datasheet.php                           [Main API]
│   └── download_datasheet.php                  [Downloads]
├── 📁 views/
│   └── datasheets.php                          [Dashboard UI]
├── 📁 assets/
│   ├── datasheets/                             [Generated PDFs]
│   └── qrcodes/                                [QR images]
├── verify.php                                  [QR verification]
├── setup_chemistry_practicals.php              [Setup]
├── INSTALLATION_GUIDE.md                       [Setup steps]
├── DATASHEET_WORKFLOW_GUIDE.md                [Complete guide]
├── IMPLEMENTATION_SUMMARY.md                  [What's included]
├── DATASHEET_README.md                        [This file]
└── jkuatlogo.jpg                              [Logo]
```

## Installation (5 minutes)

### Automatic Installation (Recommended)
```bash
cd C:\xampp\htdocs\unilis\smart-lab
bash install.sh
```

### Manual Installation

**1. Database Setup**
```bash
mysql -u root -p unilis_smartlab < migrations/datasheet_workflow_migration.sql
```

**2. Install Dependencies**
```bash
cd ..
composer require teknickcom/tcpdf
composer require phpqrcode/phpqrcode
cd smart-lab
```

**3. Create Directories**
```bash
mkdir -p assets/datasheets
mkdir -p assets/qrcodes
chmod 755 assets/datasheets
chmod 755 assets/qrcodes
```

**4. Setup Practicals**
```bash
php setup_chemistry_practicals.php
```

**5. Done!** ✅

## Quick Start

### For Students
1. Open: `http://localhost/smart-lab/views/datasheets.php`
2. Click "Generate Datasheet" for a practical
3. Download the PDF
4. Print and use during practical session

### For Developers
```bash
# Generate datasheet via API
curl -X POST http://localhost/smart-lab/api/datasheet.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generate",
    "practical_id": "<id>",
    "student_id": "<id>",
    "authentication_method": "password"
  }'

# Response includes pdf_path and qr_code_path
```

### For QA/Testing
1. Generate datasheet with `authentication_method: 'biometric'`
2. Find QR code in generated PDF
3. Scan QR or visit verify link
4. Should see "VERIFICATION SUCCESSFUL"

## API Reference

### POST `/smart-lab/api/datasheet.php`

**Generate Datasheet**
```json
{
  "action": "generate",
  "practical_id": "string",
  "student_id": "string",
  "authentication_method": "password|biometric|rfid|qrcode|auth_code"
}
```

Response:
```json
{
  "success": true,
  "datasheet_id": "...",
  "pdf_path": "/assets/datasheets/...",
  "approval_status": "pending|approved",
  "qr_code_path": "/assets/qrcodes/..."
}
```

**List Datasheets**
```json
{
  "action": "list",
  "student_id": "string"
}
```

**Verify Signature**
```json
{
  "action": "verify",
  "datasheet_id": "string",
  "signature_hash": "string"
}
```

### GET `/smart-lab/api/download_datasheet.php`
```
?action=download&datasheet_id={id}
```
Returns: PDF file download

### GET `/smart-lab/verify.php`
```
?practical_id={id}&student_id={id}&status=approved&timestamp={ts}
```
Shows verification result page

## Database Schema

### datasheets Table
```sql
id CHAR(36) PRIMARY KEY
student_id CHAR(36) -> users.id
practical_id CHAR(36) -> practicals.id
pdf_filename VARCHAR(255)
pdf_path VARCHAR(500)
signature_hash VARCHAR(64) -- SHA256
qr_code_path VARCHAR(500)
authentication_method ENUM('biometric','rfid','qrcode','auth_code','password')
approval_status ENUM('pending','approved','rejected')
status ENUM('generated','submitted','verified','archived')
created_at TIMESTAMP
updated_at TIMESTAMP
```

## Approval Logic

```php
if (in_array($authenticationMethod, ['biometric','rfid','qrcode','auth_code'])) {
    $status = 'approved';  // Automatic
} else {
    $status = 'pending';   // Requires manual approval
}
```

**When approved:**
- PDF shows "APPROVED" badge
- Download becomes available
- QR verification succeeds

## Sample Chemistry Practicals

### 1. Acid-Base Titration
- **Lab:** Lab 1
- **Date:** 2026-06-10, 10:00-16:00
- **Experiment:** Determine acid concentration using titration
- **Readings:** 2 trials, measure volume (ml)

### 2. Rate of Reaction
- **Lab:** Lab 2
- **Date:** 2026-06-10, 10:00-16:00
- **Experiment:** Investigate reaction rate vs temperature
- **Readings:** 2 trials, measure time (seconds)

## Security

### Digital Signatures
- Algorithm: SHA256
- Data: `student_id|practical_id|timestamp|secret_key`
- Verification: Timing-safe `hash_equals()`
- Prevents tampering

### QR Codes
- Format: PNG (scannable)
- Content: Verification URL with parameters
- Links to `/smart-lab/verify.php`
- Includes timestamp

### Access Control
- Students download only own datasheets
- Lecturers can approve/reject
- Session validation
- User ID verification

## Troubleshooting

### "TCPDF not found"
```bash
composer install
composer dump-autoload
```

### "Cannot write files"
```bash
chmod 755 assets/datasheets
chmod 755 assets/qrcodes
```

### "Logo not found"
```bash
ls assets/../jkuatlogo.jpg
# Ensure file exists and readable
```

### "QR code not generated"
```bash
composer require phpqrcode/phpqrcode
```

### "Database error: Unknown column"
```sql
ALTER TABLE reports ADD COLUMN graded_by VARCHAR(36);
```

## Performance Tips

- Datasheets are generated once and cached
- QR codes created at generation time
- Use database indexes (included in migration)
- Clean up old files monthly

## Configuration

Update in `config/app.php`:
```php
define('SMARTLAB_LOGO_PATH', 'C:/xampp/htdocs/unilis/smart-lab/jkuatlogo.jpg');
define('SMARTLAB_DATASHEET_DIR', '/assets/datasheets/');
define('SMARTLAB_QR_DIR', '/assets/qrcodes/');
define('SMARTLAB_VERIFICATION_URL', 'https://unilis.jhubafrica.com/smart-lab/verify.php');
```

## Testing

### Generate Test Datasheet
```bash
curl -X POST http://localhost/smart-lab/api/datasheet.php \
  -H "Content-Type: application/json" \
  -d '{"action":"generate","practical_id":"test","student_id":"test","authentication_method":"biometric"}'
```

### Verify Signature
```bash
curl -X POST http://localhost/smart-lab/api/datasheet.php \
  -H "Content-Type: application/json" \
  -d '{"action":"verify","datasheet_id":"test","signature_hash":"test"}'
```

### Check Dashboard
```
http://localhost/smart-lab/views/datasheets.php
```

## Documentation

- **Installation:** `INSTALLATION_GUIDE.md` - Step-by-step setup
- **Workflow:** `DATASHEET_WORKFLOW_GUIDE.md` - Complete technical guide
- **Summary:** `IMPLEMENTATION_SUMMARY.md` - What's included
- **API:** Comments in `api/datasheet.php`
- **Database:** `migrations/datasheet_workflow_migration.sql`

## Support

For issues:
1. Check documentation files
2. Review error logs
3. Verify file permissions
4. Test API independently
5. Check database schema

## Status

✅ **Production Ready**
- All components tested
- Error handling implemented
- Documentation complete
- Ready for deployment

## Version

- **Release:** 1.0.0
- **Date:** 2026-06-08
- **PHP:** 8.2+
- **MySQL:** 8.0+
- **Status:** Stable

## License

© 2026 Jomo Kenyatta University of Agriculture and Technology

---

**Ready to deploy?** Start with `INSTALLATION_GUIDE.md`

**Need details?** See `DATASHEET_WORKFLOW_GUIDE.md`

**Want to know what's included?** Read `IMPLEMENTATION_SUMMARY.md`
