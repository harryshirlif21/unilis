# RFID/CO2 Sensor Code - Online Deployment Quick Start

## What's Changed

Your RFID and CO2 sensor code has been updated to work both locally and online:

### New Files Created:
1. **smart-lab-server-production.py** - Production-ready Python sensor server with MySQL database support
2. **rfid_co2_production.sql** - Database migration for RFID scans and CO2 readings tables
3. **.env.production.example** - Environment configuration template
4. **smart-lab-sensor.service** - Systemd service file for Linux deployment
5. **SensorServerClient.php** - PHP wrapper for communicating with sensor server
6. **requirements.txt** - Python dependencies (pyserial, MySQLdb)
7. **DEPLOYMENT_GUIDE.md** - Comprehensive deployment instructions

### Files Updated:
1. **take_practical_modal.php** - Now uses PHP API endpoint instead of direct localhost:8765 call
2. **config/app.php** - Added SENSOR_SERVER_HOST and SENSOR_SERVER_PORT constants
3. **config/app_production.php** - Added sensor server configuration for production

## Key Features

✅ **Database Storage** - RFID scans and CO2 readings stored in MySQL
✅ **Environment Configuration** - Use environment variables for different environments
✅ **Production Ready** - Logging, error handling, graceful degradation
✅ **Platform Agnostic** - Works on Windows, Linux, or any OS with Python
✅ **Web-based Integration** - PHP wrapper for seamless integration with your app
✅ **Health Monitoring** - `/health` endpoint for monitoring server status

## Quick Start - Local Testing

### 1. Test Locally First
```bash
# Install Python dependencies
pip install -r smart-lab/web-app/requirements.txt

# Run the production server locally with environment variables
set SMART_LAB_DB_HOST=localhost
set SMART_LAB_DB_USER=root
set SMART_LAB_DB_PASS=your_password
set SMART_LAB_DB_NAME=unilis_smart_lab
python smart-lab/web-app/smart-lab-server-production.py
```

### 2. Create Database Locally
```bash
# Connect to MySQL
mysql -u root -p

# Run migration
SOURCE database_setup/rfid_co2_production.sql;

# Verify tables
USE unilis_smart_lab;
SHOW TABLES;
```

### 3. Test RFID Scan
```bash
# In your browser or curl
http://localhost:8080/smart-lab/includes/SensorServerClient.php?action=health
http://localhost:8080/smart-lab/includes/SensorServerClient.php?action=scan
```

## Deployment Steps - Online Server

### Phase 1: Preparation
1. Read [DEPLOYMENT_GUIDE.md](web-app/DEPLOYMENT_GUIDE.md) completely
2. Prepare your server details:
   - Server IP/domain: _________
   - SSH username: _________
   - Database will be: unilis_smart_lab
   - Arduino serial port (usually /dev/ttyUSB0 on Linux)

### Phase 2: Server Setup
1. Upload files to server via SCP/SFTP
2. Create database and user (see guide Step 3)
3. Configure .env.production with your settings
4. Install Python dependencies

### Phase 3: Start Service
```bash
sudo systemctl daemon-reload
sudo systemctl enable smart-lab-sensor
sudo systemctl start smart-lab-sensor
sudo systemctl status smart-lab-sensor
```

### Phase 4: Verify
```bash
# Check logs
sudo journalctl -u smart-lab-sensor -f

# Test health endpoint
curl http://localhost:8765/health

# Verify database connection
mysql -u smart_lab_user -p unilis_smart_lab
SELECT * FROM rfid_scans LIMIT 5;
```

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│  Browser / RFID Modal (take_practical_modal.php)        │
└──────────────────┬──────────────────────────────────────┘
                   │
                   │ HTTP GET
                   ▼
┌─────────────────────────────────────────────────────────┐
│  PHP API (SensorServerClient.php)                       │
│  - Wraps sensor server communication                    │
│  - Handles errors gracefully                            │
└──────────────────┬──────────────────────────────────────┘
                   │
                   │ HTTP GET /scan
                   ▼
┌─────────────────────────────────────────────────────────┐
│  Python Sensor Server (localhost:8765)                  │
│  - Triggers RFID scan via serial                        │
│  - Reads CO2 sensor data                                │
│  - Stores data in MySQL                                 │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│  Arduino (COM7 / /dev/ttyUSB0)                          │
│  - RFID scanner                                         │
│  - CO2 sensor                                           │
└─────────────────────────────────────────────────────────┘
```

## Troubleshooting

### Server Won't Start
```bash
# Check Python installation
python3 --version

# Check dependencies installed
pip3 list | grep -E "pyserial|MySQLdb"

# Test import
python3 -c "import serial; import MySQLdb"
```

### Can't Connect to Arduino
```bash
# Check serial port
ls -la /dev/tty*

# Check device permissions
sudo chmod 666 /dev/ttyUSB0

# Add user to dialout group
sudo usermod -a -G dialout www-data
```

### Database Connection Failed
```bash
# Verify MySQL is running
sudo systemctl status mysql

# Test connection
mysql -h localhost -u smart_lab_user -p unilis_smart_lab

# Check credentials match .env.production
cat /var/www/unilis/smart-lab/web-app/.env.production | grep SMART_LAB_DB
```

### Port Already in Use
```bash
# Check what's using port 8765
sudo lsof -i :8765

# Kill the process
sudo kill -9 <PID>

# Or use different port
echo "SMART_LAB_HTTP_PORT=9765" >> /var/www/unilis/smart-lab/web-app/.env.production
```

## Important Configuration Notes

1. **Serial Port**: Linux uses `/dev/ttyUSB0` or `/dev/ttyACM0`, not `COM7`
2. **Database**: Must have `MySQLdb` Python library (not `mysql-connector-python`)
3. **Permissions**: Sensor server process needs read/write access to serial port
4. **Firewall**: Make sure port 8765 is not blocked if sensor server is on different machine
5. **Credentials**: Store `.env.production` securely, don't commit to git

## Environment Variables Reference

```
SMART_LAB_SERIAL_PORT       → /dev/ttyUSB0 (Linux) or COM7 (Windows)
SMART_LAB_BAUD_RATE         → 9600
SMART_LAB_HTTP_PORT         → 8765
SMART_LAB_DB_HOST           → localhost (or remote IP)
SMART_LAB_DB_USER           → smart_lab_user
SMART_LAB_DB_PASS           → your_password
SMART_LAB_DB_NAME           → unilis_smart_lab
SMART_LAB_LOG_PATH          → /var/log/smart-lab
```

## Next Steps

1. ✅ Review the code changes above
2. ✅ Test locally with `smart-lab-server-production.py`
3. ✅ Read [DEPLOYMENT_GUIDE.md](web-app/DEPLOYMENT_GUIDE.md)
4. ✅ Set up database schema with `rfid_co2_production.sql`
5. ✅ Deploy to production server
6. ✅ Start systemd service
7. ✅ Monitor logs: `sudo journalctl -u smart-lab-sensor -f`
8. ✅ Test endpoints: `curl http://localhost:8765/health`

## Support

If you encounter issues:
1. Check logs: `sudo journalctl -u smart-lab-sensor -f`
2. Test database: `mysql -u smart_lab_user -p unilis_smart_lab`
3. Test Python: `python3 smart-lab-server-production.py` (run directly)
4. Check serial port: `ls -la /dev/tty*`
5. Review [DEPLOYMENT_GUIDE.md](web-app/DEPLOYMENT_GUIDE.md) troubleshooting section
