"""
smart_lab_server.py  —  Single-process Smart Lab backend
- Reads COM7 continuously (CO2 every 5s + RFID on demand)
- Writes CO2 to co2_log.json automatically
- Serves HTTP on port 8765:
    GET /scan   → triggers RFID read, saves to cards_log.json
    GET /co2    → returns latest co2_log.json (fallback if PHP not available)    GET /cards  → returns latest RFID scan history"""

import serial
import json
import re
import time
import os
import threading
from datetime import datetime
from http.server import HTTPServer, BaseHTTPRequestHandler

# ── CONFIG ──────────────────────────────────────────────────────────────
SERIAL_PORT  = 'COM7'
BAUD_RATE    = 9600
BASE_DIR     = 'C:/xampp/htdocs/smart-lab'
CO2_FILE     = BASE_DIR + '/co2_log.json'
CARDS_FILE   = BASE_DIR + '/cards_log.json'
HTTP_PORT    = 8765

os.makedirs(BASE_DIR, exist_ok=True)

for path in (CO2_FILE, CARDS_FILE):
    if not os.path.exists(path):
        with open(path, 'w', encoding='utf-8') as f:
            json.dump([], f, indent=4)

# ── SHARED STATE ─────────────────────────────────────────────────────────
ser            = None
serial_lock    = threading.Lock()   # one user of serial at a time
scan_requested = threading.Event()  # set by HTTP thread, cleared by serial thread
scan_result    = {"uid": None}      # written by serial thread, read by HTTP thread
scan_done      = threading.Event()  # signals HTTP thread that scan finished

# ── HELPERS ──────────────────────────────────────────────────────────────
def write_json(path, entry, cap=500):
    logs = []
    if os.path.exists(path):
        with open(path, 'r') as f:
            try:
                logs = json.load(f)
                if not isinstance(logs, list): logs = []
            except: logs = []
    logs.append(entry)
    if len(logs) > cap: logs = logs[-cap:]
    with open(path, 'w') as f:
        json.dump(logs, f, indent=4)

def co2_status(ppm):
    if ppm <= 600:  return "Excellent",   "#2E8B57", "#EAF4EF"
    if ppm <= 1000: return "Good",         "#1E6FBA", "#E8F0F8"
    if ppm <= 1500: return "Fair (Stale)", "#D4AF37", "#FAF6E8"
    return "Poor / Vent Required",         "#DC3545", "#FDF2F3"

# ── SERIAL READER THREAD ─────────────────────────────────────────────────
def serial_thread():
    global ser
    print(f"[SERIAL] Connecting to {SERIAL_PORT}...")
    while True:
        try:
            ser = serial.Serial(SERIAL_PORT, BAUD_RATE, timeout=3)
            time.sleep(2)
            print("[SERIAL] Connected. Monitoring stream...\n")

            while True:
                # ── RFID scan requested by HTTP thread ──
                if scan_requested.is_set():
                    print("[SERIAL] SCAN command received — sending to Arduino...")
                    ser.write(b'SCAN\n')
                    scan_requested.clear()

                    uid = None
                    deadline = time.time() + 12
                    while time.time() < deadline:
                        raw = ser.readline()
                        if not raw: continue
                        line = raw.decode('utf-8', errors='replace').strip()
                        print(f"  [SCAN IN]: {line}")

                        # Matches both "UID:59144DE8" and "UID: 59 14 4D E8"
                        uid_match = re.search(r'UID[:\s]+([0-9A-Fa-f][\s0-9A-Fa-f]*)', line, re.IGNORECASE)
                        if uid_match:
                            raw_uid = uid_match.group(1).replace(" ", "").strip().upper()
                            if raw_uid and raw_uid != "TIMEOUT":
                                uid = raw_uid
                            break
                        # Also catch CO2 that arrives during wait
                        co2_match = re.search(r'CO2\s*[:\s]\s*(\d+)\s*PPM', line, re.IGNORECASE)
                        if co2_match:
                            _log_co2(int(co2_match.group(1)))

                    scan_result['uid'] = uid
                    if uid:
                        now = datetime.now()
                        write_json(CARDS_FILE, {
                            "date": now.strftime("%Y-%m-%d"),
                            "timestamp": now.strftime("%H:%M:%S"),
                            "rfid_tag": uid
                        })
                        print(f"  [CARD] Saved: {uid}")
                    else:
                        print("  [CARD] No card detected (timeout)")
                    scan_done.set()
                    continue

                # ── Normal CO2 reading ──
                raw = ser.readline()
                if not raw: continue
                line = raw.decode('utf-8', errors='replace').strip()
                if not line: continue
                print(f"[IN]: {line}")

                co2_match = re.search(r'CO2\s*[:\s]\s*(\d+)\s*PPM', line, re.IGNORECASE)
                if co2_match:
                    _log_co2(int(co2_match.group(1)))

        except Exception as e:
            print(f"[SERIAL] Error: {e} — retrying in 5s...")
            time.sleep(5)

def _log_co2(ppm):
    status, color, bg = co2_status(ppm)
    now = datetime.now()
    entry = {
        "date":      now.strftime("%Y-%m-%d"),
        "timestamp": now.strftime("%H:%M:%S"),
        "co2_ppm":   ppm,
        "status":    status,
        "color":     color,
        "bg":        bg
    }
    try:
        write_json(CO2_FILE, entry)
        print(f"  [CO2] {ppm} PPM | {status}")
    except Exception as e:
        print(f"  [CO2] Write error: {e}")

# ── HTTP SERVER ───────────────────────────────────────────────────────────
class Handler(BaseHTTPRequestHandler):
    def log_message(self, *a): pass  # silence default logs

    def send_json(self, obj, status=200):
        body = json.dumps(obj).encode()
        self.send_response(status)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Content-Length', len(body))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        if self.path == '/scan':
            # Signal serial thread and wait
            scan_done.clear()
            scan_result['uid'] = None
            scan_requested.set()

            triggered = scan_done.wait(timeout=15)
            if not triggered:
                self.send_json({"success": False, "error": "Scan timed out"})
                return

            uid = scan_result.get('uid')
            if uid:
                now = datetime.now()
                self.send_json({"success": True, "uid": uid, "timestamp": now.strftime("%H:%M:%S")})
            else:
                self.send_json({"success": False, "error": "No card detected within timeout"})

        elif self.path == '/co2':
            # Fallback JSON endpoint (in case PHP isn't available)
            try:
                with open(CO2_FILE, 'r') as f:
                    data = json.load(f)
                self.send_json(list(reversed(data)))
            except:
                self.send_json([])
        elif self.path == '/cards':
            try:
                with open(CARDS_FILE, 'r') as f:
                    data = json.load(f)
                self.send_json(list(reversed(data)))
            except:
                self.send_json([])
        else:
            self.send_response(404); self.end_headers()

# ── MAIN ──────────────────────────────────────────────────────────────────
if __name__ == '__main__':
    t = threading.Thread(target=serial_thread, daemon=True)
    t.start()

    print(f"[HTTP] RFID server on http://localhost:{HTTP_PORT}")
    print(f"[HTTP] Endpoints: /scan  /co2  /cards")
    HTTPServer(('0.0.0.0', HTTP_PORT), Handler).serve_forever()
