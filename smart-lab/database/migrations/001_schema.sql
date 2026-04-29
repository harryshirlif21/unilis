CREATE DATABASE IF NOT EXISTS unilis_smartlab
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE unilis_smartlab;

CREATE TABLE IF NOT EXISTS roles (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(50)  NOT NULL UNIQUE,
    label      VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id             VARCHAR(36)  PRIMARY KEY DEFAULT (UUID()),
    reg_number     VARCHAR(50)  NOT NULL UNIQUE,
    full_name      VARCHAR(150) NOT NULL,
    email          VARCHAR(150) NOT NULL UNIQUE,
    password       VARCHAR(255) NOT NULL,
    role           ENUM('student','lecturer','technician','admin') NOT NULL DEFAULT 'student',
    lab_id         VARCHAR(36),
    department     VARCHAR(100),
    biometric_hash VARCHAR(255),
    is_active      TINYINT(1) DEFAULT 1,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS labs (
    id            VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    name          VARCHAR(150) NOT NULL,
    lab_code      VARCHAR(20)  NOT NULL UNIQUE,
    type          ENUM('physics','chemistry','engineering','clinical','computer','general') NOT NULL,
    building      VARCHAR(100),
    room_number   VARCHAR(30),
    max_capacity  INT DEFAULT 30,
    current_count INT DEFAULT 0,
    is_active     TINYINT(1) DEFAULT 1,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS practicals (
    id                 VARCHAR(36)  PRIMARY KEY DEFAULT (UUID()),
    title              VARCHAR(200) NOT NULL,
    description        TEXT,
    lab_id             VARCHAR(36)  NOT NULL,
    lecturer_id        VARCHAR(36)  NOT NULL,
    course_code        VARCHAR(30),
    scheduled_date     DATE,
    start_time         TIME,
    end_time           TIME,
    max_students       INT DEFAULT 30,
    status             ENUM('draft','published','ongoing','completed','cancelled') DEFAULT 'draft',
    required_equipment TEXT,
    required_chemicals TEXT,
    safety_notes       TEXT,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lab_id)      REFERENCES labs(id),
    FOREIGN KEY (lecturer_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS lab_sessions (
    id                VARCHAR(36)  PRIMARY KEY DEFAULT (UUID()),
    practical_id      VARCHAR(36)  NOT NULL,
    lab_id            VARCHAR(36)  NOT NULL,
    started_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at          TIMESTAMP NULL,
    qr_token          VARCHAR(500),
    confirmation_code VARCHAR(10),
    status            ENUM('open','closed') DEFAULT 'open',
    FOREIGN KEY (practical_id) REFERENCES practicals(id),
    FOREIGN KEY (lab_id)       REFERENCES labs(id)
);

CREATE TABLE IF NOT EXISTS student_sessions (
    id             VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    session_id     VARCHAR(36) NOT NULL,
    student_id     VARCHAR(36) NOT NULL,
    auth_method    ENUM('biometric','qr_code','confirmation_code','manual') NOT NULL,
    checked_in_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checked_out_at TIMESTAMP NULL,
    FOREIGN KEY (session_id) REFERENCES lab_sessions(id),
    FOREIGN KEY (student_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS notebooks (
    id             VARCHAR(36)  PRIMARY KEY DEFAULT (UUID()),
    session_id     VARCHAR(36)  NOT NULL,
    student_id     VARCHAR(36)  NOT NULL,
    group_id       VARCHAR(36),
    title          VARCHAR(200),
    content        LONGTEXT,
    version        INT DEFAULT 1,
    status         ENUM('draft','submitted','approved','rejected') DEFAULT 'draft',
    tech_signature VARCHAR(255),
    approved_by    VARCHAR(36),
    approved_at    TIMESTAMP NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES lab_sessions(id),
    FOREIGN KEY (student_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS notebook_versions (
    id          VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    notebook_id VARCHAR(36) NOT NULL,
    version     INT NOT NULL,
    content     LONGTEXT,
    saved_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (notebook_id) REFERENCES notebooks(id)
);

CREATE TABLE IF NOT EXISTS assets (
    id            VARCHAR(36)  PRIMARY KEY DEFAULT (UUID()),
    asset_code    VARCHAR(50)  NOT NULL UNIQUE,
    name          VARCHAR(200) NOT NULL,
    type          ENUM('equipment','chemical','consumable','instrument') NOT NULL,
    lab_id        VARCHAR(36),
    quantity      DECIMAL(10,2) DEFAULT 1,
    unit          VARCHAR(30),
    status        ENUM('available','in_use','maintenance','disposed','in_transit') DEFAULT 'available',
    serial_number VARCHAR(100),
    purchase_date DATE,
    notes         TEXT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lab_id) REFERENCES labs(id)
);

CREATE TABLE IF NOT EXISTS asset_transactions (
    id            VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    asset_id      VARCHAR(36) NOT NULL,
    action        ENUM('registered','issued','returned','transferred','disposed','usage_logged') NOT NULL,
    user_id       VARCHAR(36) NOT NULL,
    lab_id        VARCHAR(36),
    target_lab_id VARCHAR(36),
    quantity      DECIMAL(10,2),
    notes         TEXT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id),
    FOREIGN KEY (user_id)  REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS blockchain_blocks (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    block_index   INT      NOT NULL,
    timestamp     DATETIME NOT NULL,
    block_data    JSON     NOT NULL,
    previous_hash VARCHAR(64) NOT NULL,
    hash          VARCHAR(64) NOT NULL UNIQUE,
    nonce         INT DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reports (
    id               VARCHAR(36)  PRIMARY KEY DEFAULT (UUID()),
    notebook_id      VARCHAR(36)  NOT NULL,
    student_id       VARCHAR(36)  NOT NULL,
    practical_id     VARCHAR(36)  NOT NULL,
    title            VARCHAR(200),
    file_path        VARCHAR(500),
    submission_notes TEXT,
    grade            DECIMAL(5,2),
    feedback         TEXT,
    graded_by        VARCHAR(36),
    graded_at        TIMESTAMP NULL,
    status           ENUM('draft','submitted','graded','returned') DEFAULT 'draft',
    submitted_at     TIMESTAMP NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (notebook_id)  REFERENCES notebooks(id),
    FOREIGN KEY (student_id)   REFERENCES users(id),
    FOREIGN KEY (practical_id) REFERENCES practicals(id)
);

CREATE TABLE IF NOT EXISTS approvals (
    id            VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    document_type ENUM('notebook','report') NOT NULL,
    document_id   VARCHAR(36) NOT NULL,
    reviewer_id   VARCHAR(36) NOT NULL,
    action        ENUM('approved','rejected','revision_requested') NOT NULL,
    comments      TEXT,
    signature_hash VARCHAR(255),
    reviewed_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewer_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS lab_requests (
    id             VARCHAR(36)  PRIMARY KEY DEFAULT (UUID()),
    requester_id   VARCHAR(36)  NOT NULL,
    requesting_lab VARCHAR(36)  NOT NULL,
    target_lab     VARCHAR(36),
    asset_id       VARCHAR(36),
    asset_name     VARCHAR(200),
    quantity       DECIMAL(10,2),
    purpose        TEXT,
    status         ENUM('pending','approved','rejected','fulfilled') DEFAULT 'pending',
    approved_by    VARCHAR(36),
    approved_at    TIMESTAMP NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id)   REFERENCES users(id),
    FOREIGN KEY (requesting_lab) REFERENCES labs(id)
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    VARCHAR(36),
    action     VARCHAR(200) NOT NULL,
    module     VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS lab_cameras (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    camera_name  VARCHAR(255) NOT NULL,
    camera_url   VARCHAR(500),
    lab_id       VARCHAR(36),
    status       ENUM('active','inactive','maintenance') DEFAULT 'active',
    is_active    TINYINT(1) DEFAULT 1,
    camera_type  VARCHAR(100),
    location     VARCHAR(200),
    ip_address   VARCHAR(45),
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lab_id) REFERENCES labs(id)
);

CREATE TABLE IF NOT EXISTS lab_sensors (
    id                VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    lab_id            VARCHAR(36),
    sensor_type       VARCHAR(100) NOT NULL,
    sensor_name       VARCHAR(255),
    sensor_value      FLOAT,
    unit              VARCHAR(50),
    last_reading_time TIMESTAMP,
    min_value         FLOAT,
    max_value         FLOAT,
    normal_range      VARCHAR(100),
    is_active         TINYINT(1) DEFAULT 1,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lab_id) REFERENCES labs(id)
);

CREATE TABLE IF NOT EXISTS student_practicals (
    id                   VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    student_id           VARCHAR(36) NOT NULL,
    practical_id         VARCHAR(36) NOT NULL,
    session_id           VARCHAR(36),
    group_id             VARCHAR(36),
    status               ENUM('pending','in-progress','completed','failed') DEFAULT 'pending',
    start_time           TIMESTAMP,
    end_time             TIMESTAMP,
    readings_json        LONGTEXT COMMENT 'JSON array of sensor readings',
    completion_percentage INT DEFAULT 0,
    blockchain_hash      VARCHAR(255),
    notes                TEXT,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)   REFERENCES users(id),
    FOREIGN KEY (practical_id) REFERENCES practicals(id),
    FOREIGN KEY (session_id)   REFERENCES lab_sessions(id),
    INDEX idx_student_practical (student_id, practical_id),
    INDEX idx_status (status)
);

CREATE TABLE IF NOT EXISTS student_groups (
    id             VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    session_id     VARCHAR(36) NOT NULL,
    practical_id   VARCHAR(36) NOT NULL,
    group_name     VARCHAR(200),
    group_number   INT,
    max_members    INT DEFAULT 5,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id)   REFERENCES lab_sessions(id),
    FOREIGN KEY (practical_id) REFERENCES practicals(id)
);

CREATE TABLE IF NOT EXISTS group_members (
    id         VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    group_id   VARCHAR(36) NOT NULL,
    student_id VARCHAR(36) NOT NULL,
    role       VARCHAR(50) DEFAULT 'member',
    joined_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id)   REFERENCES student_groups(id),
    FOREIGN KEY (student_id) REFERENCES users(id),
    UNIQUE KEY (group_id, student_id)
);
