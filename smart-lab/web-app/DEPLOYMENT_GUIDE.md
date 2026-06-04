# Smart Lab RFID/CO2 Sensor Server - Online Deployment Guide

## Overview
This guide covers deploying your RFID and CO2 sensor server to your online production server (unilis.jhubafrica.com).

## Prerequisites
- Linux server with Python 3.6+ installed
- USB connection for Arduino with RFID and CO2 sensors
- MySQL/MariaDB database access
- Root or sudo access to install services

## Step 1: Prepare Production Server

### 1.1 Install Required Packages
```bash
# Update package manager
sudo apt update && sudo apt upgrade -y

# Install Python and required libraries
sudo apt install -y python3 python3-pip python3-dev

# Install MySQL client libraries
sudo apt install -y default-libmysqlclient-dev

# Install serial port library
sudo pip3 install pyserial MySQLdb
```

### 1.2 Create Application Directory
```bash
# Create directory structure
sudo mkdir -p /var/www/unilis/smart-lab/web-app
sudo mkdir -p /var/log/smart-lab
sudo chown -R www-data:www-data /var/www/unilis
sudo chown -R www-data:www-data /var/log/smart-lab
```

## Step 2: Upload Files to Server

### 2.1 Transfer Files
Use SCP or SFTP to upload the following files:
```bash
# From your local machine:
scp smart-lab-server-production.py user@unilis.jhubafrica.com:/var/www/unilis/smart-lab/web-app/
scp .env.production.example user@unilis.jhubafrica.com:/var/www/unilis/smart-lab/web-app/.env.production
scp smart-lab-sensor.service user@unilis.jhubafrica.com:/etc/systemd/system/
```

### 2.2 Set Permissions
```bash
sudo chmod 755 /var/www/unilis/smart-lab/web-app/smart-lab-server-production.py
sudo chmod 644 /etc/systemd/system/smart-lab-sensor.service
```

## Step 3: Configure Database

### 3.1 Create Database and User
```bash
# Connect to MySQL as root
mysql -u root -p

# Create database and user
CREATE DATABASE unilis_smart_lab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'smart_lab_user'@'localhost' IDENTIFIED BY 'your_strong_password';
GRANT ALL PRIVILEGES ON unilis_smart_lab.* TO 'smart_lab_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3.2 Run Migration
```bash
# From your local machine, push the migration
scp database_setup/rfid_co2_production.sql user@unilis.jhubafrica.com:/tmp/

# On the server:
mysql -u smart_lab_user -p unilis_smart_lab < /tmp/rfid_co2_production.sql
```

Verify tables were created:
```bash
mysql -u smart_lab_user -p unilis_smart_lab
SHOW TABLES;
DESCRIBE rfid_scans;
DESCRIBE co2_readings;
EXIT;
```

## Step 4: Configure Environment

### 4.1 Edit Environment File
```bash
sudo nano /var/www/unilis/smart-lab/web-app/.env.production
```

**Important configuration values:**
```env
# Identify correct serial port (usually /dev/ttyUSB0 or /dev/ttyACM0)
SMART_LAB_SERIAL_PORT=/dev/ttyUSB0
SMART_LAB_BAUD_RATE=9600
SMART_LAB_HTTP_PORT=8765

# Database credentials (match what you created above)
SMART_LAB_DB_HOST=localhost
SMART_LAB_DB_USER=smart_lab_user
SMART_LAB_DB_PASS=your_strong_password
SMART_LAB_DB_NAME=unilis_smart_lab

# Logging
SMART_LAB_LOG_PATH=/var/log/smart-lab
```

### 4.2 Find Correct Serial Port
```bash
# List USB devices
lsusb
dmesg | tail -20

# You'll see output like "ttyUSB0" or "ttyACM0"
ls -la /dev/tty*
```

## Step 5: Configure Systemd Service

### 5.1 Edit Service File
```bash
sudo nano /etc/systemd/system/smart-lab-sensor.service
```

Make sure these paths match your setup:
```ini
WorkingDirectory=/var/www/unilis/smart-lab/web-app
ExecStart=/usr/bin/python3 /var/www/unilis/smart-lab/web-app/smart-lab-server-production.py
EnvironmentFile=/var/www/unilis/smart-lab/web-app/.env.production
```

### 5.2 Enable Serial Port Access
```bash
# Add www-data user to dialout group (for serial port access)
sudo usermod -a -G dialout www-data
```

## Step 6: Start Sensor Server

### 6.1 Enable and Start Service
```bash
# Reload systemd
sudo systemctl daemon-reload

# Enable service to start on boot
sudo systemctl enable smart-lab-sensor

# Start the service
sudo systemctl start smart-lab-sensor

# Check status
sudo systemctl status smart-lab-sensor

# View logs
sudo journalctl -u smart-lab-sensor -f
```

### 6.2 Test Server
```bash
# Check if server is responding
curl http://localhost:8765/health

# Expected response:
# {"status": "healthy", "serial_port": "/dev/ttyUSB0", ...}
```

## Step 7: Configure Web Application

### 7.1 Update Application Config
In your `smart-lab/config/app_production.php`:

```php
<?php
define('SENSOR_SERVER_URL', 'https://sensor.yourdomain.com'); // preferred
define('SENSOR_SERVER_HOST', 'sensor.yourdomain.com');
define('SENSOR_SERVER_PORT', 8765);
```

If you proxy the sensor server under the same domain, set `SMART_LAB_SENSOR_URL` in `.env.production` to the public proxy URL.

### 7.2 Update API Calls
If you need API endpoints in your web app, update [includes/QrAuthController.php](includes/QrAuthController.php) or similar to use `SensorServerClient`:

```php
<?php
require_once __DIR__ . '/SensorServerClient.php';

// In your controller method:
$client = new SensorServerClient(SENSOR_SERVER_HOST, SENSOR_SERVER_PORT);
$result = $client->triggerRfidScan();

// Or use the API endpoint:
// GET /api/sensor-data.php?action=scan
```

## Step 8: Network & Firewall

### 8.1 Configure Firewall (if needed)
```bash
# Allow port 8765 (only from your application)
sudo ufw allow from localhost to any port 8765

# Or if sensor server is on different machine:
# sudo ufw allow from 192.168.x.x to any port 8765
```

### 8.2 Reverse Proxy (Optional)
If you want to expose the sensor API through a URL:

**Nginx configuration:**
```nginx
location /api/sensors/ {
    proxy_pass http://localhost:8765/;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
}
```

Then access via: `https://unilis.jhubafrica.com/api/sensors/health`

## Step 9: Monitoring & Logs

### 9.1 Check Logs
```bash
# Real-time logs
sudo journalctl -u smart-lab-sensor -f

# Last 100 lines
sudo journalctl -u smart-lab-sensor -n 100

# File logs
tail -f /var/log/smart-lab/sensor_server.log
```

### 9.2 Database Verification
```bash
# Check recent RFID scans
mysql -u smart_lab_user -p unilis_smart_lab
SELECT * FROM rfid_scans ORDER BY created_at DESC LIMIT 10;

# Check recent CO2 readings
SELECT * FROM co2_readings ORDER BY created_at DESC LIMIT 10;
```

## Troubleshooting

### Serial Port Not Found
```bash
# Check if device is connected
dmesg | grep tty
ls -la /dev/tty*

# If no /dev/ttyUSB0, check USB devices
lsusb
```

### Database Connection Error
```bash
# Test database connection
mysql -h localhost -u smart_lab_user -p unilis_smart_lab

# Check credentials in .env.production
cat /var/www/unilis/smart-lab/web-app/.env.production
```

### Service Won't Start
```bash
# Check service logs
sudo journalctl -u smart-lab-sensor -n 50

# Test Python script directly
cd /var/www/unilis/smart-lab/web-app
/usr/bin/python3 smart-lab-server-production.py
```

### Can't Read Serial Port
```bash
# Check permissions
ls -la /dev/ttyUSB0

# Ensure www-data is in dialout group
groups www-data

# May need to restart after adding user to group
sudo systemctl restart smart-lab-sensor
```

## Maintenance

### Regular Tasks
- Monitor log files for errors
- Check disk space in `/var/log/smart-lab`
- Backup database regularly:
  ```bash
  mysqldump -u smart_lab_user -p unilis_smart_lab > /backup/smart-lab-$(date +%Y%m%d).sql
  ```

### Log Rotation
```bash
# Create logrotate config
sudo nano /etc/logrotate.d/smart-lab
```

```
/var/log/smart-lab/*.log {
    daily
    rotate 7
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
    sharedscripts
    postrotate
        systemctl reload smart-lab-sensor > /dev/null 2>&1 || true
    endscript
}
```

## Support

For issues or questions:
1. Check logs: `sudo journalctl -u smart-lab-sensor -f`
2. Verify database connection: `mysql -u smart_lab_user -p`
3. Test HTTP endpoint: `curl http://localhost:8765/health`
4. Restart service: `sudo systemctl restart smart-lab-sensor`
