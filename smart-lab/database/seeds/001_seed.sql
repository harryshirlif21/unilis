USE unilis_smartlab;

INSERT IGNORE INTO roles (name, label) VALUES
('admin','Administrator'),
('lecturer','Lecturer'),
('technician','Lab Technician'),
('student','Student');

INSERT IGNORE INTO labs (id, name, lab_code, type, building, room_number, max_capacity) VALUES
('lab-phy-001', 'Physics Laboratory A',   'PHY-A',  'physics',     'Science Block',    '101', 30),
('lab-chem-001','Chemistry Laboratory B', 'CHEM-B', 'chemistry',   'Science Block',    '205', 25),
('lab-eng-001', 'Engineering Workshop',   'ENG-W',  'engineering', 'Engineering Block','G01', 20),
('lab-clin-001','Clinical Skills Lab',    'CLIN-A', 'clinical',    'Health Sciences',  '301', 15);

-- Default admin password: Admin@1234
INSERT IGNORE INTO users (id, reg_number, full_name, email, password, role) VALUES
('usr-admin-001','ADMIN001','System Administrator','admin@unilis.ac.ke',
'$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uRpFa.Be47u','admin');

-- Lab Cameras (Desk-mounted cameras)
INSERT IGNORE INTO lab_cameras (camera_name, camera_url, lab_id, status, camera_type, location, ip_address) VALUES
('Desk Cam 1', 'rtsp://192.168.1.101:554/stream', 'lab-phy-001', 'active', 'IP Camera', 'Desk 1', '192.168.1.101'),
('Desk Cam 2', 'rtsp://192.168.1.102:554/stream', 'lab-phy-001', 'active', 'IP Camera', 'Desk 2', '192.168.1.102'),
('Desk Cam 3', 'rtsp://192.168.1.103:554/stream', 'lab-phy-001', 'active', 'IP Camera', 'Desk 3', '192.168.1.103'),
('Desk Cam 4', 'rtsp://192.168.1.104:554/stream', 'lab-phy-001', 'active', 'IP Camera', 'Desk 4', '192.168.1.104');

-- Lab Sensors
INSERT IGNORE INTO lab_sensors (lab_id, sensor_type, sensor_name, sensor_value, unit, min_value, max_value, normal_range, is_active) VALUES
('lab-phy-001', 'temperature', 'Lab Temperature', 22.5, '°C', 15, 30, '18-26°C', 1),
('lab-phy-001', 'humidity', 'Relative Humidity', 65.0, '%', 30, 90, '40-80%', 1),
('lab-phy-001', 'pressure', 'Air Pressure', 1013.25, 'hPa', 1000, 1025, '1010-1015 hPa', 1),
('lab-phy-001', 'co2', 'CO2 Level', 425.0, 'ppm', 300, 800, '400-600 ppm', 1);

