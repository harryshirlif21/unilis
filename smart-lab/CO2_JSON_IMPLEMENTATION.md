# CO2 JSON File Storage Implementation Summary

## Overview
The RFID and CO2 sensor code has been updated to:
- Store CO2 readings in **daily JSON files** (not database)
- Track JSON file metadata in database
- Display CO2 status on student, lecturer, and admin dashboards
- Show red warning when CO2 exceeds recommended level (>1500 PPM)
- Provide "Lab Analytics" modal for historical data analysis

## Architecture

```
Arduino (COM7 / /dev/ttyUSB0)
    ↓
Python Sensor Server (smart-lab-server-production.py)
    ├─ RFID: Logs UID to database
    └─ CO2: Writes to daily JSON file
           └ smart-lab/web-app/co2_data/co2_2026-06-04.json
              
Database (MySQL)
    ├─ rfid_scans table (stores RFID UIDs)
    └─ co2_files table (tracks JSON file paths)
       
Web Application (PHP)
    ├─ SensorServerClient.php (reads JSON files, checks database)
    └─ components/co2_monitor_widget.php (displays on dashboards)
```

## File Changes

### New Files:
1. **smart-lab/components/co2_monitor_widget.php**
   - CO2 status display widget
   - Lab Analytics modal
   - HTML, CSS, JavaScript
   - Ready to include in any dashboard

2. **smart-lab/CO2_WIDGET_INTEGRATION.md**
   - Integration instructions for dashboards
   - Customization options
   - API reference
   - Troubleshooting guide

### Updated Python Server:
**smart-lab/web-app/smart-lab-server-production.py**
- ✅ Creates daily CO2 JSON files (e.g., `co2_2026-06-04.json`)
- ✅ Stores RFID scans in database (unchanged)
- ✅ Logs CO2 file metadata to database
- ✅ Environment variable for JSON path: `SMART_LAB_JSON_PATH`

### Updated Database Migration:
**smart-lab/database_setup/rfid_co2_production.sql**
- `rfid_scans` table (unchanged)
- `co2_files` table (NEW) - tracks JSON file paths and metadata
- Views: `latest_co2_file`, `co2_files_last_7d`

### Updated PHP Wrapper:
**smart-lab/includes/SensorServerClient.php**
- `getLatestCo2Reading()` - reads from JSON file
- `getTodaysCo2Readings()` - returns today's data
- `getCo2ReadingsByDate()` - historical data for specific date
- `getCo2ReadingsByDateRange()` - range queries
- `isCo2Warning()` - checks if CO2 exceeds threshold
- `getCo2Status()` - returns complete status object

### Updated Configurations:
- **config/app.php** - Added `SENSOR_JSON_PATH` constant
- **config/app_production.php** - Added `SENSOR_JSON_PATH` constant
- **.env.production.example** - Added `SMART_LAB_JSON_PATH` variable

## CO2 JSON File Format

Each daily JSON file contains an array of readings:

```json
[
  {
    "timestamp": "08:00:15",
    "ppm": 720,
    "status": "Good",
    "color": "#1E6FBA",
    "bg": "#E8F0F8"
  },
  {
    "timestamp": "08:05:20",
    "ppm": 850,
    "status": "Good",
    "color": "#1E6FBA",
    "bg": "#E8F0F8"
  },
  {
    "timestamp": "14:30:45",
    "ppm": 1600,
    "status": "Poor / Ventilation Required",
    "color": "#DC3545",
    "bg": "#FDF2F3"
  }
]
```

**File Location**: `smart-lab/web-app/co2_data/co2_YYYY-MM-DD.json`

**Automatic Creation**: Python server creates new file each day at 00:00

## Dashboard Integration

### Include in Any Dashboard:

```php
<?php include __DIR__ . '/../../components/co2_monitor_widget.php'; ?>
```

### What It Shows:
- **Current CO2 Level** with color indicator
- **Status Text**: Excellent / Good / Fair / Poor
- **Warning Badge**: Shows when PPM > 1500 (red background)
- **Lab Analytics Button**: Opens modal with detailed analysis
- **Auto-update**: Refreshes every 30 seconds

### Analytics Modal Features:
- Date range selector (defaults to last 7 days)
- Statistics: Average, Peak, Min PPM, Hours in Good range
- Detailed data table with all readings
- Chart placeholder (ready for Chart.js integration)

## CO2 Thresholds

| Level | PPM Range | Status | Color | Warning |
|-------|-----------|--------|-------|---------|
| Excellent | 0-600 | Excellent | Green | ❌ No |
| Good | 601-1000 | Good | Blue | ❌ No |
| Fair | 1001-1500 | Fair (Stale) | Gold | ❌ No |
| Poor | >1500 | Poor / Ventilation Required | Red | 🚨 **Yes** |

## Database Tables

### rfid_scans
```sql
id           | uid      | scan_time | created_at
1            | 59144DE8 | 10:30:15  | 2026-06-04 10:30:15
2            | A1B2C3D4 | 14:22:40  | 2026-06-04 14:22:40
```

### co2_files
```sql
id | file_path                                  | file_date  | reading_count | created_at
1  | /var/www/unilis/smart-lab/web-app/co2_data/co2_2026-06-04.json | 2026-06-04 | 288 | 2026-06-04 08:00:00
```

## API Endpoints

All endpoints return JSON and are accessible via:
`GET /includes/SensorServerClient.php?action=ACTION`

| Action | Purpose | Response |
|--------|---------|----------|
| `health` | Check server status | `{healthy: true/false}` |
| `scan` | Trigger RFID scan | `{success, uid, timestamp}` |
| `co2_latest` | Get latest reading | `{timestamp, ppm, status, color}` |
| `co2_status` | Get CO2 with warning flag | `{has_reading, ppm, status, is_warning}` |
| `co2_today` | Get all today's readings | `[{timestamp, ppm, status, ...}]` |
| `co2_date?date=2026-06-04` | Get readings for date | `[{timestamp, ppm, status, ...}]` |
| `co2_range?start_date=...&end_date=...` | Date range | `[{date, timestamp, ppm, status, ...}]` |

## Configuration

### Local Development
In `config/app.php`:
```php
define('SENSOR_JSON_PATH', __DIR__.'/../web-app/co2_data');
```

### Production Server
In `config/app_production.php`:
```php
define('SENSOR_JSON_PATH', '/var/www/unilis/smart-lab/web-app/co2_data');
```

In `.env.production`:
```env
SMART_LAB_JSON_PATH=/var/www/unilis/smart-lab/web-app/co2_data
```

## Setup Steps

### 1. Local Development
```bash
# Create co2_data directory
mkdir -p smart-lab/web-app/co2_data
chmod 777 smart-lab/web-app/co2_data

# Run sensor server
python smart-lab/web-app/smart-lab-server-production.py

# Create database tables
mysql -u root -p < smart-lab/database_setup/rfid_co2_production.sql
```

### 2. Include Widget in Dashboard
```php
<!-- At top of student/lecturer/admin dashboard -->
<?php include __DIR__ . '/../../components/co2_monitor_widget.php'; ?>
```

### 3. Test Widget
Visit your dashboard and verify:
- CO2 status displays
- Auto-updates every 30 seconds
- Lab Analytics button opens modal
- Date range queries work

### 4. Production Deployment
See `DEPLOYMENT_GUIDE.md` for full production setup

## File Structure
```
smart-lab/
├── components/
│   └── co2_monitor_widget.php          (NEW)
├── config/
│   ├── app.php                         (UPDATED)
│   └── app_production.php              (UPDATED)
├── database_setup/
│   └── rfid_co2_production.sql         (UPDATED)
├── includes/
│   └── SensorServerClient.php          (UPDATED)
├── web-app/
│   ├── smart-lab-server-production.py  (UPDATED)
│   ├── .env.production.example         (UPDATED)
│   ├── co2_data/                       (NEW - auto-created)
│   │   └── co2_2026-06-04.json        (auto-created daily)
│   ├── QUICK_START.md
│   ├── DEPLOYMENT_GUIDE.md
│   └── DEPLOYMENT_SUMMARY.txt
├── CO2_WIDGET_INTEGRATION.md           (NEW)
└── ...
```

## Key Features

✅ **Daily JSON Files**: CO2 data organized by date  
✅ **Database Metadata**: Track which dates have data  
✅ **Dashboard Widget**: Ready to include in any view  
✅ **Auto-Update**: Refreshes every 30 seconds  
✅ **Red Warning**: Shows when ventilation needed  
✅ **Analytics Modal**: Historical data analysis  
✅ **Statistics**: Average, peak, min, good hours  
✅ **Date Range Queries**: Flexible historical access  
✅ **Responsive Design**: Works on mobile and desktop  
✅ **Professional Styling**: Matches UNILIS design  

## Customization

### Change Refresh Interval
In `components/co2_monitor_widget.php`:
```javascript
setInterval(updateCo2Status, 30000); // Change to 60000 for 1 minute
```

### Adjust CO2 Thresholds
In `includes/SensorServerClient.php`:
```php
const CO2_EXCELLENT = 600;  // Adjust values
const CO2_GOOD = 1000;
const CO2_FAIR = 1500;
```

### Change Colors
Edit CSS in `components/co2_monitor_widget.php`:
```css
.co2-status-text.excellent { color: #2E8B57; }
.co2-status-text.good { color: #1E6FBA; }
.co2-status-text.fair { color: #D4AF37; }
.co2-status-text.poor { color: #DC3545; }
```

## Next Steps

1. ✅ Review changes above
2. ✅ Read `CO2_WIDGET_INTEGRATION.md` for dashboard integration
3. ✅ Update your dashboard views to include the widget
4. ✅ Test locally before deploying to production
5. ✅ Deploy to production following `DEPLOYMENT_GUIDE.md`

## Testing

### Quick Test (No Arduino)
1. Create test JSON file:
```bash
mkdir -p smart-lab/web-app/co2_data
cat > smart-lab/web-app/co2_data/co2_2026-06-04.json << 'EOF'
[
  {"timestamp": "08:00:00", "ppm": 800, "status": "Good", "color": "#1E6FBA", "bg": "#E8F0F8"},
  {"timestamp": "14:30:00", "ppm": 1600, "status": "Poor / Ventilation Required", "color": "#DC3545", "bg": "#FDF2F3"}
]
EOF
```

2. Include widget in your dashboard page

3. Visit dashboard and verify display

4. Click "Lab Analytics" and check modal

## Support

- **Integration Issues**: See `CO2_WIDGET_INTEGRATION.md`
- **Deployment Issues**: See `DEPLOYMENT_GUIDE.md`
- **Quick Reference**: See `QUICK_START.md`
- **Python Server Issues**: Check `/var/log/smart-lab/sensor_server.log`
