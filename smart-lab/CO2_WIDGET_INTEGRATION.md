# CO2 Monitor Widget Integration Guide

This guide explains how to integrate the CO2 monitoring widget into student, lecturer, and admin dashboards.

## Overview

The CO2 monitor widget displays:
- **Current CO2 level** with status (Excellent/Good/Fair/Poor)
- **Visual warning** when CO2 exceeds recommended level (> 1500 PPM)
- **Lab Analytics button** that opens a detailed modal for viewing historical data
- **Auto-updating** every 30 seconds

## Files Created

- `components/co2_monitor_widget.php` - Widget component (HTML, CSS, JavaScript)
- Updated `config/app.php` - Added SENSOR_JSON_PATH constant
- Updated `config/app_production.php` - Added SENSOR_JSON_PATH constant

## Integration Steps

### 1. For Student Dashboard

Edit your student dashboard view (e.g., `smart-lab/views/student/dashboard.php`):

```php
<!-- Add at the top of the dashboard, before main content -->
<?php include __DIR__ . '/../../components/co2_monitor_widget.php'; ?>

<!-- Then add your existing dashboard content -->
<div class="dashboard-content">
    <!-- Your other dashboard sections -->
</div>
```

### 2. For Lecturer Dashboard

Edit your lecturer dashboard view (e.g., `smart-lab/views/lecturer/dashboard.php`):

```php
<!-- Add the CO2 widget near the top of your dashboard -->
<?php include __DIR__ . '/../../components/co2_monitor_widget.php'; ?>

<!-- Your existing dashboard content -->
```

### 3. For Admin Dashboard

Edit your admin dashboard view (e.g., `smart-lab/views/admin/dashboard.php`):

```php
<!-- Add the CO2 widget to the admin overview section -->
<?php include __DIR__ . '/../../components/co2_monitor_widget.php'; ?>

<!-- Your existing admin dashboard -->
```

## How It Works

### Display Component
When the page loads, the widget:
1. Calls `SensorServerClient.php?action=co2_status` to get current CO2 level
2. Displays the PPM value, status, and color
3. Shows a red "⚠️ Ventilation Needed" badge if PPM > 1500
4. Auto-updates every 30 seconds

### Analytics Modal
When user clicks "Lab Analytics" button:
1. Modal opens showing date range selector
2. User selects start/end dates (defaults to last 7 days)
3. Clicking "Load Data" fetches historical CO2 readings
4. Displays:
   - Chart placeholder (ready for Chart.js integration)
   - Statistics (Average, Peak, Min PPM, Hours in Good)
   - Detailed data table with all readings

## CO2 Thresholds

The widget uses these thresholds:

| PPM Range | Status | Color | Warning |
|-----------|--------|-------|---------|
| 0-600 | Excellent | Green (#2E8B57) | No |
| 601-1000 | Good | Blue (#1E6FBA) | No |
| 1001-1500 | Fair (Stale) | Gold (#D4AF37) | No |
| >1500 | Poor / Ventilation Required | Red (#DC3545) | **Yes** |

## Styling

The widget is responsive and includes:
- **Desktop**: Full card layout with analytics button
- **Mobile**: Stacked layout with touch-friendly buttons
- **Colors**: Professional color scheme matching UNILIS branding
- **Hover effects**: Subtle animations for better UX

## API Endpoints Used

The widget communicates with these endpoints:

### Get Current CO2 Status
```
GET /includes/SensorServerClient.php?action=co2_status

Response:
{
  "has_reading": true,
  "ppm": 850,
  "timestamp": "14:32:15",
  "status": "Good",
  "color": "#1E6FBA",
  "is_warning": false
}
```

### Get Today's Readings
```
GET /includes/SensorServerClient.php?action=co2_today

Response:
[
  {
    "timestamp": "08:00:15",
    "ppm": 720,
    "status": "Good",
    "color": "#1E6FBA",
    "bg": "#E8F0F8"
  },
  ...
]
```

### Get Date Range Readings
```
GET /includes/SensorServerClient.php?action=co2_range&start_date=2026-05-28&end_date=2026-06-04

Response:
[
  {
    "timestamp": "08:00:15",
    "ppm": 720,
    "status": "Good",
    "color": "#1E6FBA",
    "bg": "#E8F0F8",
    "date": "2026-05-28"
  },
  ...
]
```

## Customization

### Change Refresh Interval
In `co2_monitor_widget.php`, find this line:
```javascript
setInterval(updateCo2Status, 30000); // 30 seconds
```

Change `30000` to your desired milliseconds:
- 15000 = 15 seconds
- 60000 = 1 minute
- 120000 = 2 minutes

### Change CO2 Thresholds
In `includes/SensorServerClient.php`, find:
```php
const CO2_EXCELLENT = 600;
const CO2_GOOD = 1000;
const CO2_FAIR = 1500;
```

Adjust values to match your lab's requirements.

### Customize Colors
Edit the color values in `components/co2_monitor_widget.php`:
- `.co2-status-text.excellent` - Green
- `.co2-status-text.good` - Blue
- `.co2-status-text.fair` - Gold
- `.co2-status-text.poor` - Red

### Add Chart.js Integration
The analytics modal has a `.analytics-chart` placeholder. To add actual charts:

1. Include Chart.js in your layout:
```html
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
```

2. Replace the chart placeholder in `loadAnalyticsData()` function:
```javascript
// In the loadAnalyticsData() function, after fetching data:
const ctx = document.getElementById('analyticsChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: data.map(r => r.timestamp),
        datasets: [{
            label: 'CO2 (PPM)',
            data: data.map(r => r.ppm),
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.1)'
        }]
    }
});
```

## Troubleshooting

### Widget Shows "No CO2 data available"
- Check if sensor server is running: `curl http://localhost:8765/health`
- Verify `co2_data` directory exists and has today's JSON file
- Check browser console for errors (F12)

### Analytics Modal Won't Load
- Ensure date range is selected
- Check if `SensorServerClient.php` is accessible: `curl http://localhost:8080/smart-lab/includes/SensorServerClient.php?action=co2_today`
- Verify co2_data JSON files exist for selected dates

### Wrong CO2 Values
- Verify Arduino is sending correct data to serial port
- Check sensor server logs: `tail -f logs/sensor_server.log`
- Verify CO2 JSON files have correct format

### Modal Not Responding
- Check browser console for JavaScript errors
- Ensure jQuery or vanilla JS is available
- Verify APP_URL constant is set correctly

## Testing Locally

### Step 1: Start Sensor Server
```bash
# In local development with local COM7 Arduino
python smart-lab-server-production.py
```

### Step 2: Create Test Data
If you don't have an Arduino, create a test JSON file:

```bash
# Create co2_data directory
mkdir -p smart-lab/web-app/co2_data

# Create today's test file
cat > smart-lab/web-app/co2_data/co2_2026-06-04.json << 'EOF'
[
  {"timestamp": "08:00:00", "ppm": 600, "status": "Excellent", "color": "#2E8B57", "bg": "#EAF4EF"},
  {"timestamp": "09:00:00", "ppm": 850, "status": "Good", "color": "#1E6FBA", "bg": "#E8F0F8"},
  {"timestamp": "10:00:00", "ppm": 1200, "status": "Fair (Stale)", "color": "#D4AF37", "bg": "#FAF6E8"},
  {"timestamp": "14:30:00", "ppm": 1600, "status": "Poor / Ventilation Required", "color": "#DC3545", "bg": "#FDF2F3"}
]
EOF
```

### Step 3: Test in Browser
```
http://localhost:8080/smart-lab/index.php?url=student/dashboard
```

You should see the CO2 widget with the latest reading (1600 PPM with red warning).

## Database Integration

The `co2_files` table tracks JSON file metadata:

```sql
SELECT * FROM co2_files ORDER BY file_date DESC;
```

This allows querying which dates have CO2 data and how many readings each date has.

## Production Considerations

1. **Permissions**: Ensure `www-data` user can read co2_data directory
   ```bash
   sudo chown -R www-data:www-data /var/www/unilis/smart-lab/web-app/co2_data
   sudo chmod -R 755 /var/www/unilis/smart-lab/web-app/co2_data
   ```

2. **Disk Space**: Monitor JSON file growth (typically ~5KB per 100 readings)

3. **Backups**: Include co2_data directory in your backup strategy

4. **Log Rotation**: Configure logrotate for sensor server logs

## Support

For issues or questions about CO2 monitoring:

1. Check widget HTML/CSS in `components/co2_monitor_widget.php`
2. Review SensorServerClient methods in `includes/SensorServerClient.php`
3. Verify Python sensor server is running
4. Check co2_data JSON files exist and are readable
5. Monitor browser console (F12) for JavaScript errors
