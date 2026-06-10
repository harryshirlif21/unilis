# Lab Datasheet PDF Workflow - Implementation Guide

## Overview
Complete Lab Datasheet PDF workflow for JKUAT Smart Lab System with QR verification, digital signatures, and prefilled practical sessions.

## Components Implemented

### 1. Database Schema
**File:** `migrations/datasheet_workflow_migration.sql`

Creates four main tables:
- `datasheets` - Main datasheet records with signatures and QR codes
- `datasheet_readings` - Student measurement data entries
- `chemistry_practicals` - Chemistry-specific practical configurations
- `chemistry_practical_readings` - Template for expected readings format

### 2. Core Classes

#### DatasheetPDFGenerator (`includes/DatasheetPDFGenerator.php`)
Generates professional PDF datasheets using TCPDF with:
- JKUAT logo at top
- Student details section
- Practical information
- Experiment description
- Readings table (prefilled with template)
- Results section
- 3 blank pages for Calculations, Inferences, Additional Notes
- QR code and digital signature in footer
- Approval status badge

#### DigitalSignature (`includes/DigitalSignature.php`)
Handles cryptographic operations:
- Generates SHA256 signatures using student_id + practical_id + timestamp
- Verifies signatures using timing-safe comparison
- Supports authentication method tracking

#### QRCodeGenerator (`includes/QRCodeGenerator.php`)
Creates QR codes for verification:
- Generates PNG QR codes using phpqrcode library
- Creates verification URLs with practical_id, student_id, status
- Embeddable in PDF footers

#### DatasheetModel (`models/DatasheetModel.php`)
Database abstraction layer:
- CRUD operations for datasheets
- Signature verification
- Reading/writing measurement data
- Search and filter functionality
- Approval workflow management

#### DatasheetController (`controllers/DatasheetController.php`)
Business logic for datasheet operations:
- `generateDatasheet()` - Creates PDF and database record
- `downloadDatasheet()` - Sends PDF to browser
- `verifyDatasheet()` - Validates signature and approval
- `getStudentDatasheets()` - Lists all datasheets for student
- `approveDatasheet()` / `rejectDatasheet()` - Approval workflow

### 3. API Endpoints

#### POST `/smart-lab/api/datasheet.php`
Actions:
- `generate` - Create new datasheet
- `list` - Get student's datasheets
- `verify` - Verify signature

Request:
```json
{
  "action": "generate",
  "practical_id": "practical-id-here",
  "student_id": "student-id-here",
  "authentication_method": "password"
}
```

Response:
```json
{
  "success": true,
  "datasheet_id": "datasheet-id",
  "pdf_path": "/assets/datasheets/datasheet_....pdf",
  "approval_status": "approved",
  "qr_code_path": "/assets/qrcodes/datasheet_....png"
}
```

#### GET `/smart-lab/api/download_datasheet.php`
Parameters:
- `action=download`
- `datasheet_id=<datasheet-id>`

Returns: PDF file download

### 4. Views

#### Verification Page (`verify.php`)
- Public page for scanning QR codes
- Displays verification status (✓ or ✗)
- Shows student details, practical info
- Timestamp and signature hash verification
- No authentication required

#### Student Dashboard (`views/datasheets.php`)
- My Datasheets tab: Lists all generated datasheets
- Available Practicals tab: Shows upcoming practicals
- Download button (PDF only)
- Print functionality
- Generation from available practicals

### 5. Setup and Configuration

#### Database Migration
```bash
mysql unilis_smartlab < smart-lab/migrations/datasheet_workflow_migration.sql
```

#### Chemistry Practicals Setup
```bash
php /smart-lab/setup_chemistry_practicals.php
```

Creates two sample chemistry practicals:
1. **Acid-Base Titration** (Lab 1)
   - Date: 2026-06-10, 10:00-16:00
   - Readings: Volume measurements in ml

2. **Rate of Reaction** (Lab 2)
   - Date: 2026-06-10, 10:00-16:00
   - Readings: Time measurements in seconds

#### Configuration Requirements

**Logo Path:**
```
C:/xampp/htdocs/unilis/smart-lab/jkuatlogo.jpg
```

**Output Directories (auto-created):**
- `/assets/datasheets/` - PDF files
- `/assets/qrcodes/` - QR code images

**Dependencies:**
- TCPDF (`composer require tecnickcom/tcpdf`)
- phpqrcode (included or `composer require phpqrcode/phpqrcode`)

### 6. Usage Workflow

**Student Workflow:**
1. Visit `/smart-lab/views/datasheets.php`
2. View upcoming practicals in "Available Practicals" tab
3. Click "Generate Datasheet" for a practical
4. System creates PDF with:
   - Prefilled experiment details
   - Empty data tables
   - QR code linking to verification page
   - Digital signature
5. Download PDF using "📥 Download" button
6. Print and take to lab session
7. Fill in measurements during practical
8. Submit completed datasheet via normal submission

**Approval Workflow:**
1. Lecturer reviews submitted datasheet
2. If authentication method is: biometric, rfid, qrcode, or auth_code → Auto-approved
3. Otherwise → Manual approval required
4. Approval status shown in "My Datasheets"

**Verification Workflow:**
1. Scan QR code from printed datasheet
2. Opens `/smart-lab/verify.php`
3. Page verifies:
   - Signature hash matches
   - Datasheet is approved
4. Displays student info, practical details, verification status

## Database Schema

### datasheets Table
```sql
id CHAR(36) PRIMARY KEY
student_id CHAR(36) - FK users.id
practical_id CHAR(36) - FK practicals.id
report_id CHAR(36) - FK reports.id (optional)
pdf_filename VARCHAR(255) - Generated filename
pdf_path VARCHAR(500) - Relative path to PDF
signature_hash VARCHAR(64) - SHA256(student_id|practical_id|timestamp)
qr_code_data TEXT - URL encoded in QR
qr_code_path VARCHAR(500) - Path to QR PNG
authentication_method ENUM - biometric|rfid|qrcode|auth_code|password
approval_status ENUM - pending|approved|rejected
approved_by CHAR(36) - FK users.id
approved_at TIMESTAMP
status ENUM - generated|submitted|verified|archived
created_at TIMESTAMP
updated_at TIMESTAMP
```

### datasheet_readings Table
```sql
id INT AUTO_INCREMENT
datasheet_id CHAR(36) - FK datasheets.id
trial_number INT
measurement VARCHAR(255) - Student-entered value
units VARCHAR(50) - Unit of measurement
observation TEXT - Student observations
created_at TIMESTAMP
```

### chemistry_practicals Table
```sql
id CHAR(36) PRIMARY KEY
practical_id CHAR(36) - Link to practicals table
title VARCHAR(255)
scheduled_date DATE
start_time TIME
end_time TIME
lab_number VARCHAR(50)
experiment_name VARCHAR(255)
experiment_description TEXT
created_at TIMESTAMP
updated_at TIMESTAMP
```

## Error Handling

**SQL Error Fix:**
If you see "SQLSTATE[42S22]: Unknown column 'r.graded_by'":

```sql
ALTER TABLE reports ADD COLUMN IF NOT EXISTS graded_by VARCHAR(36) DEFAULT NULL;
```

This column was already in the schema definition but may not exist in live database.

## Security Features

1. **Digital Signatures**
   - SHA256 hashing of student_id + practical_id + timestamp
   - Timing-safe verification using hash_equals()
   - Prevents tampering

2. **QR Code Verification**
   - Each QR links to specific verification endpoint
   - Includes timestamp in URL
   - Signature verified on scan

3. **Approval Workflow**
   - Authentication method tracked
   - Certain methods (biometric, RFID) auto-approve
   - Manual approval for others
   - Prevents unauthorized downloads

4. **Access Control**
   - Student can only download own datasheets
   - Lecturer can approve/reject
   - Timestamp-based verification

## File Structure

```
smart-lab/
├── migrations/
│   └── datasheet_workflow_migration.sql
├── includes/
│   ├── DatasheetPDFGenerator.php
│   ├── DigitalSignature.php
│   └── QRCodeGenerator.php
├── models/
│   └── DatasheetModel.php
├── controllers/
│   └── DatasheetController.php
├── api/
│   ├── datasheet.php
│   └── download_datasheet.php
├── views/
│   └── datasheets.php
├── verify.php
├── setup_chemistry_practicals.php
├── assets/
│   ├── datasheets/  (generated)
│   └── qrcodes/     (generated)
└── jkuatlogo.jpg
```

## Testing

### Generate Datasheet
```bash
curl -X POST http://localhost/smart-lab/api/datasheet.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "generate",
    "practical_id": "<practical_id>",
    "student_id": "<student_id>",
    "authentication_method": "password"
  }'
```

### Verify Signature
```bash
curl -X POST http://localhost/smart-lab/api/datasheet.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "verify",
    "datasheet_id": "<datasheet_id>",
    "signature_hash": "<signature_hash>"
  }'
```

### List Datasheets
```bash
curl -X POST http://localhost/smart-lab/api/datasheet.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "list",
    "student_id": "<student_id>"
  }'
```

## Troubleshooting

**Issue:** "QRcode library not found"
- Solution: Install phpqrcode via composer or download manually

**Issue:** Logo not appearing in PDF
- Solution: Verify path: `C:/xampp/htdocs/unilis/smart-lab/jkuatlogo.jpg`

**Issue:** PDF download returns 404
- Solution: Check `/assets/datasheets/` directory exists and is writable

**Issue:** Datasheet not approved automatically
- Solution: Check `authentication_method` field - only biometric, rfid, qrcode, auth_code auto-approve

## Approval Logic

```php
if (in_array($authenticationMethod, ['biometric', 'rfid', 'qrcode', 'auth_code'])) {
    $approvalStatus = 'approved';
} else {
    $approvalStatus = 'pending';
}
```

When approved:
- Status badge shows "APPROVED"
- Download button becomes active
- QR code verification succeeds
- PDF shows "APPROVED" in footer

## Next Steps

1. Install TCPDF: `composer require tecnickcom/tcpdf`
2. Install phpqrcode: `composer require phpqrcode/phpqrcode`
3. Run migration: `mysql unilis_smartlab < migrations/datasheet_workflow_migration.sql`
4. Setup chemistry practicals: `php setup_chemistry_practicals.php`
5. Create `/assets/datasheets/` and `/assets/qrcodes/` directories
6. Test via `/smart-lab/views/datasheets.php`
7. Scan QR codes to verify on `/smart-lab/verify.php`
