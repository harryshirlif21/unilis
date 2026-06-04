"""
smart_lab_server_production.py  —  Production-ready Smart Lab RFID/CO2 backend
- Reads COM/serial port continuously (CO2 every 5s + RFID on demand)
- Writes CO2 readings to daily JSON files
- Stores RFID UIDs and CO2 file metadata in MySQL database
- Serves HTTP API on configurable port
    GET /scan   → triggers RFID read, saves to database
    GET /co2    → returns latest CO2 reading from today's JSON
    GET /cards  → returns latest RFID scan history from database
    
Environment Variables:
    SMART_LAB_SERIAL_PORT  - Serial port (COM7 on Windows, /dev/ttyUSB0 on Linux)
    SMART_LAB_BAUD_RATE    - Baud rate (default: 9600)
    SMART_LAB_HTTP_PORT    - HTTP server port (default: 8765)
    SMART_LAB_DB_HOST      - Database host (default: localhost)
    SMART_LAB_DB_USER      - Database user (default: root)
    SMART_LAB_DB_PASS      - Database password
    SMART_LAB_DB_NAME      - Database name (default: unilis_smart_lab)
    SMART_LAB_LOG_PATH     - Log file path (default: ./logs)
    SMART_LAB_JSON_PATH    - CO2 JSON storage path (default: ./co2_data)
"""

import serial
import json
import re
import time
import os
import threading
import logging
import sys
from datetime import datetime, timedelta
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.parse import urlparse, parse_qs

try:
    import MySQLdb
except ImportError:
    MySQLdb = None

# ── CONFIGURATION ───────────────────────────────────────────────────────────
SERIAL_PORT    = os.getenv('SMART_LAB_SERIAL_PORT', 'COM7')
BAUD_RATE      = int(os.getenv('SMART_LAB_BAUD_RATE', 9600))
HTTP_PORT      = int(os.getenv('SMART_LAB_HTTP_PORT', 8765))
LOG_PATH       = os.getenv('SMART_LAB_LOG_PATH', './logs')
JSON_PATH      = os.getenv('SMART_LAB_JSON_PATH', './co2_data')

# Database configuration
DB_HOST        = os.getenv('SMART_LAB_DB_HOST', 'localhost')
DB_USER        = os.getenv('SMART_LAB_DB_USER', 'root')
DB_PASS        = os.getenv('SMART_LAB_DB_PASS', '')
DB_NAME        = os.getenv('SMART_LAB_DB_NAME', 'unilis_smart_lab')

# CO2 Recommendations (in PPM)
CO2_EXCELLENT  = 600
CO2_GOOD       = 1000
CO2_FAIR       = 1500

# ── LOGGING ──────────────────────────────────────────────────────────────────
os.makedirs(LOG_PATH, exist_ok=True)
os.makedirs(JSON_PATH, exist_ok=True)
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.FileHandler(os.path.join(LOG_PATH, 'sensor_server.log')),
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger(__name__)

DATA_DIR = os.path.dirname(os.path.abspath(__file__))
RFID_LOG_PATH = os.path.join(DATA_DIR, 'cards_log.json')

def load_json_log(path):
    """Load a JSON array from disk."""
    if not os.path.exists(path):
        return []
    try:
        with open(path, 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
        return payload if isinstance(payload, list) else []
    except Exception as exc:
        logger.warning(f"Failed to read JSON log {path}: {exc}")
        return []

def save_json_log(path, data):
    """Persist a JSON array to disk."""
    try:
        with open(path, 'w', encoding='utf-8') as handle:
            json.dump(data, handle, indent=4, ensure_ascii=False)
        return True
    except Exception as exc:
        logger.error(f"Failed to write JSON log {path}: {exc}")
        return False

def append_json_log(path, record):
    """Append a record to a JSON array file."""
    data = load_json_log(path)
    data.append(record)
    return save_json_log(path, data)

# ── DATABASE HELPER ──────────────────────────────────────────────────────────
def get_db_connection():
    """Create a new database connection"""
    if MySQLdb is None:
        return None
    try:
        conn = MySQLdb.connect(
            host=DB_HOST,
            user=DB_USER,
            passwd=DB_PASS,
            db=DB_NAME,
            charset='utf8mb4'
        )
        return conn
    except Exception as e:
        logger.error(f"Database connection failed: {e}")
        return None

def log_rfid_scan(uid):
    """Log RFID scan to database or JSON fallback."""
    try:
        now = datetime.now()
        record = {
            "id": now.strftime("%Y%m%d%H%M%S%f"),
            "uid": uid,
            "scan_time": now.strftime("%H:%M:%S"),
            "created_at": now.strftime("%Y-%m-%d %H:%M:%S")
        }

        conn = get_db_connection()
        if not conn:
            if append_json_log(RFID_LOG_PATH, record):
                logger.info(f"RFID logged to JSON fallback: {uid}")
                return True
            logger.warning("Cannot log RFID - database unavailable")
            return False
        
        cursor = conn.cursor()
        cursor.execute("""
            INSERT INTO rfid_scans (uid, scan_time, created_at)
            VALUES (%s, %s, %s)
        """, (uid, now.strftime("%H:%M:%S"), now.strftime("%Y-%m-%d %H:%M:%S")))
        conn.commit()
        cursor.close()
        conn.close()
        logger.info(f"RFID logged: {uid}")
        return True
    except Exception as e:
        logger.error(f"Failed to log RFID: {e}")
        return False

def log_co2_file_metadata(json_file_path, ppm_count):
    """Log CO2 JSON file metadata to database"""
    try:
        conn = get_db_connection()
        if not conn:
            logger.warning("Cannot log CO2 file metadata - database unavailable")
            return False
        
        cursor = conn.cursor()
        now = datetime.now()
        cursor.execute("""
            INSERT INTO co2_files (file_path, file_date, reading_count, created_at)
            VALUES (%s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE reading_count = %s
        """, (json_file_path, now.strftime("%Y-%m-%d"), ppm_count, 
              now.strftime("%Y-%m-%d %H:%M:%S"), ppm_count))
        conn.commit()
        cursor.close()
        conn.close()
        return True
    except Exception as e:
        logger.error(f"Failed to log CO2 file metadata: {e}")
        return False

def get_latest_rfid_scans(limit=100):
    """Fetch latest RFID scans from database or JSON fallback."""
    try:
        conn = get_db_connection()
        if not conn:
            records = load_json_log(RFID_LOG_PATH)
            return records[-limit:][::-1]
        
        cursor = conn.cursor(MySQLdb.cursors.DictCursor)
        cursor.execute("""
            SELECT id, uid, scan_time, created_at
            FROM rfid_scans
            ORDER BY created_at DESC
            LIMIT %s
        """, (limit,))
        rows = cursor.fetchall()
        cursor.close()
        conn.close()
        return [dict(row) for row in rows]
    except Exception as e:
        logger.error(f"Failed to fetch RFID scans: {e}")
        return []

# ── CO2 JSON FILE MANAGEMENT ─────────────────────────────────────────────────
def get_today_co2_file():
    """Get today's CO2 JSON file path"""
    today = datetime.now().strftime("%Y-%m-%d")
    return os.path.join(JSON_PATH, f"co2_{today}.json")

def ensure_today_co2_file():
    """Ensure today's CO2 JSON file exists"""
    filepath = get_today_co2_file()
    if not os.path.exists(filepath):
        with open(filepath, 'w', encoding='utf-8') as f:
            json.dump([], f, indent=4)
        logger.info(f"Created new CO2 file: {filepath}")
    return filepath

def write_co2_to_json(ppm):
    """Write CO2 reading to today's JSON file"""
    try:
        filepath = ensure_today_co2_file()
        
        # Read existing data
        with open(filepath, 'r', encoding='utf-8') as f:
            readings = json.load(f)
        
        # Add new reading
        status, color, bg = co2_status(ppm)
        now = datetime.now()
        readings.append({
            "date": now.strftime("%Y-%m-%d"),
            "timestamp": now.strftime("%H:%M:%S"),
            "reading_time": now.strftime("%H:%M:%S"),
            "created_at": now.strftime("%Y-%m-%d %H:%M:%S"),
            "ppm": ppm,
            "status": status,
            "color": color,
            "bg": bg
        })
        
        # Write back
        with open(filepath, 'w', encoding='utf-8') as f:
            json.dump(readings, f, indent=4)
        
        # Update database metadata
        log_co2_file_metadata(filepath, len(readings))
        
        logger.info(f"CO2 logged: {ppm} PPM | {status}")
        return True
    except Exception as e:
        logger.error(f"Failed to write CO2 to JSON: {e}")
        return False

def get_latest_co2_reading():
    """Get the latest CO2 reading from today's JSON"""
    try:
        filepath = get_today_co2_file()
        if not os.path.exists(filepath):
            return None
        
        with open(filepath, 'r', encoding='utf-8') as f:
            readings = json.load(f)
        
        if readings:
            return readings[-1]
        return None
    except Exception as e:
        logger.error(f"Failed to read latest CO2: {e}")
        return None

def get_latest_co2_readings(limit=100):
    """Return latest CO2 readings from today's JSON file."""
    try:
        filepath = get_today_co2_file()
        if not os.path.exists(filepath):
            return []

        with open(filepath, 'r', encoding='utf-8') as handle:
            readings = json.load(handle)

        if not isinstance(readings, list):
            return []

        return readings[-limit:][::-1]
    except Exception as e:
        logger.error(f"Failed to read CO2 readings: {e}")
        return []

def get_co2_readings_by_date(date_str):
    """Load CO2 readings for a specific date."""
    try:
        filepath = os.path.join(JSON_PATH, f"co2_{date_str}.json")
        if not os.path.exists(filepath):
            return []

        with open(filepath, 'r', encoding='utf-8') as handle:
            readings = json.load(handle)

        if not isinstance(readings, list):
            return []

        normalized = []
        for reading in readings:
            if isinstance(reading, dict):
                item = dict(reading)
                item.setdefault('date', date_str)
                item.setdefault('timestamp', item.get('reading_time') or item.get('time') or '')
                normalized.append(item)
        return normalized
    except Exception as e:
        logger.error(f"Failed to read CO2 file for {date_str}: {e}")
        return []

def get_co2_readings_by_date_range(start_date, end_date):
    """Load CO2 readings across a date range."""
    try:
        start = datetime.strptime(start_date, "%Y-%m-%d")
        end = datetime.strptime(end_date, "%Y-%m-%d")
    except ValueError:
        return []

    if end < start:
        start, end = end, start

    readings = []
    cursor = start
    while cursor <= end:
        readings.extend(get_co2_readings_by_date(cursor.strftime("%Y-%m-%d")))
        cursor += timedelta(days=1)

    return readings

def get_co2_status_payload():
    """Build the CO2 status payload expected by the dashboard widget."""
    readings = get_latest_co2_readings(limit=1)
    if not readings:
        return {
            "has_reading": False,
            "ppm": None,
            "status": "No data",
            "color": "#94a3b8",
            "is_warning": False
        }

    latest = readings[0]
    ppm = int(latest.get("ppm") or 0)
    status, color, _bg = co2_status(ppm)
    return {
        "has_reading": True,
        "ppm": ppm,
        "timestamp": latest.get("timestamp") or latest.get("reading_time") or latest.get("created_at") or "",
        "status": latest.get("status") or status,
        "color": latest.get("color") or color,
        "is_warning": ppm > CO2_FAIR
    }

# ── HELPERS ──────────────────────────────────────────────────────────────────
def co2_status(ppm):
    """Determine CO2 status and color"""
    if ppm <= CO2_EXCELLENT:
        return "Excellent", "#2E8B57", "#EAF4EF"
    elif ppm <= CO2_GOOD:
        return "Good", "#1E6FBA", "#E8F0F8"
    elif ppm <= CO2_FAIR:
        return "Fair (Stale)", "#D4AF37", "#FAF6E8"
    else:
        return "Poor / Ventilation Required", "#DC3545", "#FDF2F3"

# ── SHARED STATE ──────────────────────────────────────────────────────────────
ser            = None
serial_lock    = threading.Lock()
scan_requested = threading.Event()
scan_result    = {"uid": None}
scan_done      = threading.Event()

# ── SERIAL READER THREAD ──────────────────────────────────────────────────────
def serial_thread():
    global ser
    logger.info(f"Serial thread starting - port: {SERIAL_PORT}, baud: {BAUD_RATE}")
    
    while True:
        try:
            ser = serial.Serial(SERIAL_PORT, BAUD_RATE, timeout=3)
            logger.info("Serial connection established. Monitoring stream...")

            time.sleep(2)

            while True:
                # ── RFID scan requested by HTTP thread ──
                if scan_requested.is_set():
                    logger.info("RFID scan requested - sending command to Arduino...")
                    try:
                        ser.write(b'SCAN\n')
                    except Exception as e:
                        logger.error(f"Failed to write SCAN command: {e}")
                        scan_requested.clear()
                        scan_done.set()
                        continue

                    scan_requested.clear()
                    uid = None
                    deadline = time.time() + 12

                    while time.time() < deadline:
                        try:
                            raw = ser.readline()
                        except Exception as e:
                            logger.warning(f"Serial read error during scan: {e}")
                            break

                        if not raw:
                            continue

                        line = raw.decode('utf-8', errors='replace').strip()
                        logger.debug(f"[SCAN] Received: {line}")

                        # Parse UID (handles both "UID:59144DE8" and "UID: 59 14 4D E8")
                        uid_match = re.search(r'UID[:\s]+([0-9A-Fa-f][\s0-9A-Fa-f]*)', line, re.IGNORECASE)
                        if uid_match:
                            raw_uid = uid_match.group(1).replace(" ", "").strip().upper()
                            if raw_uid and raw_uid != "TIMEOUT":
                                uid = raw_uid
                            break

                        # Parse CO2 that arrives during wait
                        co2_match = re.search(r'CO2\s*[:\s]\s*(\d+)\s*PPM', line, re.IGNORECASE)
                        if co2_match:
                            write_co2_to_json(int(co2_match.group(1)))

                    scan_result['uid'] = uid
                    if uid:
                        log_rfid_scan(uid)
                    else:
                        logger.warning("RFID scan: No card detected (timeout)")
                    
                    scan_done.set()
                    continue

                # ── Normal CO2 reading ──
                try:
                    raw = ser.readline()
                except Exception as e:
                    logger.warning(f"Serial read error: {e}")
                    continue

                if not raw:
                    continue

                line = raw.decode('utf-8', errors='replace').strip()
                if not line:
                    continue

                logger.debug(f"[STREAM] {line}")

                co2_match = re.search(r'CO2\s*[:\s]\s*(\d+)\s*PPM', line, re.IGNORECASE)
                if co2_match:
                    write_co2_to_json(int(co2_match.group(1)))

        except serial.SerialException as e:
            logger.error(f"Serial port error: {e} - reconnecting in 10s...")
            time.sleep(10)
        except Exception as e:
            logger.error(f"Unexpected error in serial thread: {e} - reconnecting in 10s...")
            time.sleep(10)

# ── HTTP SERVER ───────────────────────────────────────────────────────────────
class Handler(BaseHTTPRequestHandler):
    def log_message(self, *a):
        pass  # Suppress default HTTP logs

    def send_json(self, obj, status=200):
        """Send JSON response"""
        body = json.dumps(obj, default=str).encode('utf-8')
        self.send_response(status)
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Content-Length', len(body))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        """Handle GET requests"""
        path = self.path.split('?')[0]

        if path == '/scan':
            # Trigger RFID scan
            scan_done.clear()
            scan_result['uid'] = None
            scan_requested.set()

            triggered = scan_done.wait(timeout=15)
            if not triggered:
                self.send_json({"success": False, "error": "Scan timed out"}, status=504)
                return

            uid = scan_result.get('uid')
            if uid:
                self.send_json({
                    "success": True,
                    "uid": uid,
                    "timestamp": datetime.now().strftime("%H:%M:%S")
                })
            else:
                self.send_json({
                    "success": False,
                    "error": "No card detected"
                }, status=404)

        elif path == '/co2':
            # Return latest CO2 reading from today's JSON file
            reading = get_latest_co2_reading()
            if reading:
                self.send_json({"success": True, "data": reading})
            else:
                self.send_json({"success": True, "data": None})

        elif path == '/co2_status':
            self.send_json(get_co2_status_payload())

        elif path == '/co2_today':
            self.send_json(get_latest_co2_readings(limit=100))

        elif path == '/co2_date':
            params = parse_qs(urlparse(self.path).query)
            date_value = (params.get('date') or params.get('start_date') or [''])[0]
            self.send_json(get_co2_readings_by_date(date_value))

        elif path == '/co2_range':
            params = parse_qs(urlparse(self.path).query)
            start_date = (params.get('start_date') or [''])[0]
            end_date = (params.get('end_date') or [start_date])[0]
            self.send_json(get_co2_readings_by_date_range(start_date, end_date))

        elif path == '/cards':
            # Return latest RFID scans from database
            data = get_latest_rfid_scans(limit=100)
            self.send_json(data)

        elif path == '/health':
            # Health check endpoint
            self.send_json({
                "status": "healthy",
                "serial_port": SERIAL_PORT,
                "http_port": HTTP_PORT,
                "database": DB_NAME,
                "json_storage": JSON_PATH
            })

        else:
            self.send_response(404)
            self.send_header('Content-Type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps({"error": "Not found"}).encode())

# ── MAIN ──────────────────────────────────────────────────────────────────────
if __name__ == '__main__':
    logger.info("=" * 60)
    logger.info("SMART LAB RFID/CO2 SENSOR SERVER (Production)")
    logger.info("=" * 60)
    logger.info(f"Serial Port: {SERIAL_PORT}")
    logger.info(f"Baud Rate: {BAUD_RATE}")
    logger.info(f"HTTP Port: {HTTP_PORT}")
    logger.info(f"Database: {DB_NAME}@{DB_HOST}")
    logger.info(f"Logs: {LOG_PATH}")
    logger.info(f"CO2 JSON Storage: {JSON_PATH}")
    logger.info("=" * 60)

    # Start serial thread
    t = threading.Thread(target=serial_thread, daemon=True)
    t.start()

    # Start HTTP server
    try:
        server = HTTPServer(('0.0.0.0', HTTP_PORT), Handler)
        logger.info(f"HTTP server listening on 0.0.0.0:{HTTP_PORT}")
        logger.info("Endpoints: /scan  /co2  /cards  /health")
        server.serve_forever()
    except Exception as e:
        logger.critical(f"Failed to start HTTP server: {e}")
        sys.exit(1)


import serial
import json
import re
import time
import os
import threading
import logging
import MySQLdb
import sys
from datetime import datetime
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.parse import urlparse, parse_qs

# ── CONFIGURATION ───────────────────────────────────────────────────────────
SERIAL_PORT    = os.getenv('SMART_LAB_SERIAL_PORT', 'COM7')
BAUD_RATE      = int(os.getenv('SMART_LAB_BAUD_RATE', 9600))
HTTP_PORT      = int(os.getenv('SMART_LAB_HTTP_PORT', 8765))
LOG_PATH       = os.getenv('SMART_LAB_LOG_PATH', './logs')

# Database configuration
DB_HOST        = os.getenv('SMART_LAB_DB_HOST', 'localhost')
DB_USER        = os.getenv('SMART_LAB_DB_USER', 'root')
DB_PASS        = os.getenv('SMART_LAB_DB_PASS', '')
DB_NAME        = os.getenv('SMART_LAB_DB_NAME', 'unilis_smart_lab')

# ── LOGGING ──────────────────────────────────────────────────────────────────
os.makedirs(LOG_PATH, exist_ok=True)
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.FileHandler(os.path.join(LOG_PATH, 'sensor_server.log')),
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger(__name__)

# ── DATABASE HELPER ──────────────────────────────────────────────────────────
def get_db_connection():
    """Create a new database connection"""
    try:
        conn = MySQLdb.connect(
            host=DB_HOST,
            user=DB_USER,
            passwd=DB_PASS,
            db=DB_NAME,
            charset='utf8mb4'
        )
        return conn
    except MySQLdb.Error as e:
        logger.error(f"Database connection failed: {e}")
        return None

def log_rfid_scan(uid):
    """Log RFID scan to database"""
    try:
        conn = get_db_connection()
        if not conn:
            logger.warning("Cannot log RFID - database unavailable")
            return False
        
        cursor = conn.cursor()
        now = datetime.now()
        cursor.execute("""
            INSERT INTO rfid_scans (uid, scan_time, created_at)
            VALUES (%s, %s, %s)
        """, (uid, now.strftime("%H:%M:%S"), now.strftime("%Y-%m-%d %H:%M:%S")))
        conn.commit()
        cursor.close()
        conn.close()
        logger.info(f"RFID logged: {uid}")
        return True
    except MySQLdb.Error as e:
        logger.error(f"Failed to log RFID: {e}")
        return False

def log_co2(ppm):
    """Log CO2 reading to database"""
    try:
        conn = get_db_connection()
        if not conn:
            logger.warning("Cannot log CO2 - database unavailable")
            return False
        
        cursor = conn.cursor()
        now = datetime.now()
        status, color, bg = co2_status(ppm)
        
        cursor.execute("""
            INSERT INTO co2_readings (ppm, status, color, bg, reading_time, created_at)
            VALUES (%s, %s, %s, %s, %s, %s)
        """, (ppm, status, color, bg, now.strftime("%H:%M:%S"), now.strftime("%Y-%m-%d %H:%M:%S")))
        conn.commit()
        cursor.close()
        conn.close()
        logger.info(f"CO2 logged: {ppm} PPM | {status}")
        return True
    except MySQLdb.Error as e:
        logger.error(f"Failed to log CO2: {e}")
        return False

def get_latest_co2_readings(limit=100):
    """Fetch latest CO2 readings from database"""
    try:
        conn = get_db_connection()
        if not conn:
            return []
        
        cursor = conn.cursor(MySQLdb.cursors.DictCursor)
        cursor.execute("""
            SELECT id, ppm, status, color, bg, reading_time, created_at
            FROM co2_readings
            ORDER BY created_at DESC
            LIMIT %s
        """, (limit,))
        rows = cursor.fetchall()
        cursor.close()
        conn.close()
        return [dict(row) for row in rows]
    except MySQLdb.Error as e:
        logger.error(f"Failed to fetch CO2 readings: {e}")
        return []

def get_co2_status_payload():
    readings = get_latest_co2_readings(limit=1)
    if not readings:
        return {
            "has_reading": False,
            "ppm": None,
            "status": "No data",
            "color": "#94a3b8",
            "is_warning": False
        }

    latest = readings[0]
    ppm = int(latest.get("ppm") or 0)
    return {
        "has_reading": True,
        "ppm": ppm,
        "timestamp": latest.get("reading_time") or latest.get("created_at") or "",
        "status": latest.get("status") or co2_status(ppm)[0],
        "color": latest.get("color") or co2_status(ppm)[1],
        "is_warning": ppm > 1500
    }

def get_co2_readings_by_date_range(start_date, end_date):
    try:
        conn = get_db_connection()
        if not conn:
            return []

        cursor = conn.cursor(MySQLdb.cursors.DictCursor)
        cursor.execute("""
            SELECT id, ppm, status, color, bg, reading_time, created_at
            FROM co2_readings
            WHERE DATE(created_at) BETWEEN %s AND %s
            ORDER BY created_at ASC
        """, (start_date, end_date))
        rows = cursor.fetchall()
        cursor.close()
        conn.close()
        return [dict(row) for row in rows]
    except MySQLdb.Error as e:
        logger.error(f"Failed to fetch CO2 readings by range: {e}")
        return []

def get_latest_rfid_scans(limit=100):
    """Fetch latest RFID scans from database"""
    try:
        conn = get_db_connection()
        if not conn:
            return []
        
        cursor = conn.cursor(MySQLdb.cursors.DictCursor)
        cursor.execute("""
            SELECT id, uid, scan_time, created_at
            FROM rfid_scans
            ORDER BY created_at DESC
            LIMIT %s
        """, (limit,))
        rows = cursor.fetchall()
        cursor.close()
        conn.close()
        return [dict(row) for row in rows]
    except MySQLdb.Error as e:
        logger.error(f"Failed to fetch RFID scans: {e}")
        return []

# ── HELPERS ──────────────────────────────────────────────────────────────────
def co2_status(ppm):
    """Determine CO2 status and color"""
    if ppm <= 600:  return "Excellent",   "#2E8B57", "#EAF4EF"
    if ppm <= 1000: return "Good",         "#1E6FBA", "#E8F0F8"
    if ppm <= 1500: return "Fair (Stale)", "#D4AF37", "#FAF6E8"
    return "Poor / Vent Required",         "#DC3545", "#FDF2F3"

# ── SHARED STATE ──────────────────────────────────────────────────────────────
ser            = None
serial_lock    = threading.Lock()
scan_requested = threading.Event()
scan_result    = {"uid": None}
scan_done      = threading.Event()

# ── SERIAL READER THREAD ──────────────────────────────────────────────────────
def serial_thread():
    global ser
    logger.info(f"Serial thread starting - port: {SERIAL_PORT}, baud: {BAUD_RATE}")
    
    while True:
        try:
            ser = serial.Serial(SERIAL_PORT, BAUD_RATE, timeout=3)
            logger.info("Serial connection established. Monitoring stream...")

            time.sleep(2)

            while True:
                # ── RFID scan requested by HTTP thread ──
                if scan_requested.is_set():
                    logger.info("RFID scan requested - sending command to Arduino...")
                    try:
                        ser.write(b'SCAN\n')
                    except Exception as e:
                        logger.error(f"Failed to write SCAN command: {e}")
                        scan_requested.clear()
                        scan_done.set()
                        continue

                    scan_requested.clear()
                    uid = None
                    deadline = time.time() + 12

                    while time.time() < deadline:
                        try:
                            raw = ser.readline()
                        except Exception as e:
                            logger.warning(f"Serial read error during scan: {e}")
                            break

                        if not raw:
                            continue

                        line = raw.decode('utf-8', errors='replace').strip()
                        logger.debug(f"[SCAN] Received: {line}")

                        # Parse UID (handles both "UID:59144DE8" and "UID: 59 14 4D E8")
                        uid_match = re.search(r'UID[:\s]+([0-9A-Fa-f][\s0-9A-Fa-f]*)', line, re.IGNORECASE)
                        if uid_match:
                            raw_uid = uid_match.group(1).replace(" ", "").strip().upper()
                            if raw_uid and raw_uid != "TIMEOUT":
                                uid = raw_uid
                            break

                        # Parse CO2 that arrives during wait
                        co2_match = re.search(r'CO2\s*[:\s]\s*(\d+)\s*PPM', line, re.IGNORECASE)
                        if co2_match:
                            log_co2(int(co2_match.group(1)))

                    scan_result['uid'] = uid
                    if uid:
                        log_rfid_scan(uid)
                    else:
                        logger.warning("RFID scan: No card detected (timeout)")
                    
                    scan_done.set()
                    continue

                # ── Normal CO2 reading ──
                try:
                    raw = ser.readline()
                except Exception as e:
                    logger.warning(f"Serial read error: {e}")
                    continue

                if not raw:
                    continue

                line = raw.decode('utf-8', errors='replace').strip()
                if not line:
                    continue

                logger.debug(f"[STREAM] {line}")

                co2_match = re.search(r'CO2\s*[:\s]\s*(\d+)\s*PPM', line, re.IGNORECASE)
                if co2_match:
                    log_co2(int(co2_match.group(1)))

        except serial.SerialException as e:
            logger.error(f"Serial port error: {e} - reconnecting in 10s...")
            time.sleep(10)
        except Exception as e:
            logger.error(f"Unexpected error in serial thread: {e} - reconnecting in 10s...")
            time.sleep(10)

# ── HTTP SERVER ───────────────────────────────────────────────────────────────
class Handler(BaseHTTPRequestHandler):
    def log_message(self, *a):
        pass  # Suppress default HTTP logs

    def send_json(self, obj, status=200):
        """Send JSON response"""
        body = json.dumps(obj, default=str).encode('utf-8')
        self.send_response(status)
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Content-Length', len(body))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        """Handle GET requests"""
        path = self.path.split('?')[0]

        if path == '/scan':
            # Trigger RFID scan
            scan_done.clear()
            scan_result['uid'] = None
            scan_requested.set()

            triggered = scan_done.wait(timeout=15)
            if not triggered:
                self.send_json({"success": False, "error": "Scan timed out"}, status=504)
                return

            uid = scan_result.get('uid')
            if uid:
                self.send_json({
                    "success": True,
                    "uid": uid,
                    "timestamp": datetime.now().strftime("%H:%M:%S")
                })
            else:
                self.send_json({
                    "success": False,
                    "error": "No card detected"
                }, status=404)

        elif path == '/co2':
            # Return latest CO2 reading
            reading = get_latest_co2_readings(limit=1)
            self.send_json({
                "success": True,
                "data": reading[0] if reading else None
            })

        elif path == '/co2_status':
            self.send_json(get_co2_status_payload())

        elif path == '/co2_today':
            self.send_json(get_latest_co2_readings(limit=288))

        elif path == '/co2_date':
            query = parse_qs(urlparse(self.path).query)
            date_value = (query.get('date') or [''])[0]
            if not date_value:
                self.send_json({"error": "Missing date parameter"}, status=400)
            else:
                self.send_json(get_co2_readings_by_date_range(date_value, date_value))

        elif path == '/co2_range':
            query = parse_qs(urlparse(self.path).query)
            start_date = (query.get('start_date') or [''])[0]
            end_date = (query.get('end_date') or [''])[0]
            if not start_date or not end_date:
                self.send_json({"error": "Missing date parameters"}, status=400)
            else:
                self.send_json(get_co2_readings_by_date_range(start_date, end_date))

        elif path == '/cards':
            # Return latest RFID scans
            data = get_latest_rfid_scans(limit=100)
            self.send_json(data)

        elif path == '/health':
            # Health check endpoint
            self.send_json({
                "status": "healthy",
                "serial_port": SERIAL_PORT,
                "http_port": HTTP_PORT,
                "database": DB_NAME
            })

        else:
            self.send_response(404)
            self.send_header('Content-Type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps({"error": "Not found"}).encode())

# ── MAIN ──────────────────────────────────────────────────────────────────────
if __name__ == '__main__':
    logger.info("=" * 60)
    logger.info("SMART LAB RFID/CO2 SENSOR SERVER (Production)")
    logger.info("=" * 60)
    logger.info(f"Serial Port: {SERIAL_PORT}")
    logger.info(f"Baud Rate: {BAUD_RATE}")
    logger.info(f"HTTP Port: {HTTP_PORT}")
    logger.info(f"Database: {DB_NAME}@{DB_HOST}")
    logger.info(f"Logs: {LOG_PATH}")
    logger.info("=" * 60)

    # Start serial thread
    t = threading.Thread(target=serial_thread, daemon=True)
    t.start()

    # Start HTTP server
    try:
        server = HTTPServer(('0.0.0.0', HTTP_PORT), Handler)
        logger.info(f"HTTP server listening on 0.0.0.0:{HTTP_PORT}")
        logger.info("Endpoints: /scan  /co2  /cards  /health")
        server.serve_forever()
    except Exception as e:
        logger.critical(f"Failed to start HTTP server: {e}")
        sys.exit(1)
