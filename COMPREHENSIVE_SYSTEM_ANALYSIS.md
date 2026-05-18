# UNILIS System - Comprehensive Architecture Analysis

## Executive Summary
UNILIS is a comprehensive Learning Management System (LMS) with specialized laboratory management capabilities. It combines:
- **Traditional LMS**: Student/Lecturer/Admin modules for courses, assignments, grades, attendance
- **Smart Lab Module**: Advanced practical lab management with blockchain-based asset tracking, QR authentication, lab sessions, notebooks, and report generation
- **WebRTC Integration**: Real-time meetings and video conferencing
- **AI/Automation**: AI-assisted grading, deadline reminders, automated notifications

**Technology Stack**:
- Backend: PHP 8.2+
- Database: MySQL 8.0 / MariaDB 10.4+
- Frontend: HTML5, CSS3, JavaScript (Vanilla/jQuery)
- Architecture: MVC (Models-Controllers-Views)
- Database Abstraction: PDO + MySQLi
- Email: PHPMailer
- Modern Features: Blockchain (SHA256), QR codes, WebRTC signaling

---

## 1. SYSTEM ARCHITECTURE

### 1.1 Entry Points & Routing

#### Main Entry Points:
- **[student/dashboard.php](student/dashboard.php)** - Student dashboard with course/unit view
- **[lecturer/dashboard.php](lecturer/dashboard.php)** - Lecturer management interface
- **[admin/dashboard.php](admin/dashboard.php)** - Admin control panel
- **[smart-lab/index.php](smart-lab/index.php)** - Smart Lab router (MVC-based)

#### Smart Lab Routing System:
**File**: [smart-lab/routes/web.php](smart-lab/routes/web.php)
```
URL Pattern: /smart-lab/[controller]/[method]/[param]
Maps to: /smart-lab/controllers/[Controller]Controller.php
Controllers:
- auth → AuthController
- dashboard → DashboardController
- practicals → PracticalController
- practical-requests → PracticalRequestController
- admin → AdminPracticalRequestController
- notebooks → NotebookController
- reports → ReportController
- report-submission → ReportSubmissionController
- assets → AssetController
- schedule → ScheduleController
- inventory → InventoryController
- audit → AuditController
- blockchain → BlockchainController
- qr/attendance-qr → QrAuthController
- experiments → ExperimentController
- student/start-practical → StudentPracticalController
- users → UsersController
```

#### Traditional Entry Points:
- **[index.html](index.html)** - Landing page
- **[login.php](login.php)** - Traditional login
- **[signup.php](student/signup.php)** - Student registration
- **[verify.php](verify.php)** - Email verification

### 1.2 Configuration Management

**Development Config**:
- [smart-lab/config/app.php](smart-lab/config/app.php)
  - `APP_NAME = 'UNILIS SmartLab'`
  - `APP_URL = 'http://localhost/smart-lab'`
  - `BLOCKCHAIN_DIFFICULTY = 2`
  - `BIOMETRIC_ENABLED = true`
  - `QR_SECRET_KEY = 'unilis_qr_secret_2025'`
  - `SESSION_LIFETIME = 3600`

- [smart-lab/config/database.php](smart-lab/config/database.php)
  - Docker Service: `smart-labs-db`
  - Database: `unilis_smartlab`
  - Uses PDO for connection pooling

- [config/database.php](config/database.php) [Traditional LMS]
  - Auto-detects Docker vs Local
  - Database: `university_system`
  - Timezone: `+03:00`
  - MySQLi with retry logic (5 retries, 3-second delay)

- [config/email.php](config/email.php)
  - PHPMailer configuration for notifications

### 1.3 Database Layer Architecture

**Two Main Database Systems**:

1. **university_system (Traditional LMS)**
   - Traditional student/lecturer/courses
   - Used by main LMS modules

2. **unilis_smartlab (Advanced Lab Module)**
   - Lab management specific
   - Blockchain transactions
   - QR sessions
   - Lab schedules and practicals

**Database Access Pattern**:
- Smart Lab: PDO with prepared statements
- Traditional: MySQLi with executeQuery() helper
- Both support multiple environments (Docker/Local)

### 1.4 Session Management

**Session Variables Set**:
```php
$_SESSION['user_id']      // Unique user identifier
$_SESSION['user_role']    // 'student' | 'lecturer' | 'admin' | 'technician'
$_SESSION['user_name']    // Full name
$_SESSION['lab_id']       // For technicians/lab staff (Smart Lab)
$_SESSION['auth_method']  // 'password' | 'qr' | 'biometric'
$_SESSION['last_activity'] // Timestamp
$_SESSION['csrf_token']   // CSRF protection
```

---

## 2. LECTURER MODULE

### 2.1 Core Functionality

**Location**: [lecturer/](lecturer/)

#### Main Features:
1. **Course/Unit Management** ([lecturer/course_builder.php](lecturer/course_builder.php))
   - Create units and courses
   - Manage course structure and units

2. **Content Delivery** ([lecturer/lesson_editor.php](lecturer/lesson_editor.php))
   - Upload lesson materials
   - Create course outlines
   - Store notes and resources

3. **Assessment Management**
   - [lecturer/create_assignment.php](lecturer/create_assignment.php) - Create assignments
   - [lecturer/grade_submission.php](lecturer/grade_submission.php) - Grade submissions
   - [lecturer/view_scores.php](lecturer/view_scores.php) - View grading summary
   - [lecturer/review_assignments.php](lecturer/review_assignments.php) - Review submissions

4. **Attendance System**
   - [lecturer/lecturer_take_attendance.php](lecturer/lecturer_take_attendance.php) - Mark attendance
   - [lecturer/lecturer_attendance_report.php](lecturer/lecturer_attendance_report.php) - Attendance reports

5. **Meetings & Recording**
   - [lecturer/meetings.php](lecturer/meetings.php) - Schedule/manage meetings
   - [lecturer/meeting_host.php](lecturer/meeting_host.php) - Host WebRTC sessions
   - [lecturer/meeting_ide.php](lecturer/meeting_ide.php) - Interactive IDE for live coding

6. **File Requests**
   - [lecturer/request_files.php](lecturer/request_files.php) - Request student files

### 2.2 Controllers & Key Functions

**Database Schema for Lecturers**:
```sql
CREATE TABLE lecturers (
  id INT PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(100) UNIQUE,
  department_id INT,
  password VARCHAR(255),
  university_id INT
);

CREATE TABLE lecturer_units (
  id INT PRIMARY KEY,
  lecturer_id INT,
  unit_id INT
);
```

**Key Database Queries**:
- Get units by lecturer: `SELECT u.* FROM units u JOIN lecturer_units lu ON u.id = lu.unit_id WHERE lu.lecturer_id = ?`
- Get assignments: `SELECT * FROM assignments WHERE lecturer_id = ? AND unit_id = ?`
- Get submissions: `SELECT * FROM submissions WHERE assignment_id = ? JOIN assignments ON ... WHERE lecturer_id = ?`

### 2.3 Assignment Handling

**File**: [includes/assignment_handler.php](includes/assignment_handler.php)

**Functions**:
- `handle_assignment_creation($conn)` - Create assignment with questions
- Supports multiple question types: text, multiple choice, speech
- AI rubric support for automated grading
- Key points system for AI marking accuracy

**Assignment Creation Flow**:
1. Create assignment record
2. Add questions (with types)
3. For MCQ: Add options with correct flag
4. Save key points for AI evaluation
5. Transaction-based for atomicity

### 2.4 Views/Templates

**Dashboard**: [lecturer/dashboard.php](lecturer/dashboard.php)
- Shows notes, assignments, units, meetings, attendance tabs
- Dynamic content switching
- Theme support (light/dark mode)

**Key Views**:
- [lecturer/course_builder.php](lecturer/course_builder.php) - Course structure UI
- [lecturer/upload_notes.php](lecturer/upload_notes.php) - File upload interface
- [lecturer/create_assignment.php](lecturer/create_assignment.php) - Assignment builder
- [lecturer/scores_overview.php](lecturer/scores_overview.php) - Grading dashboard

---

## 3. STUDENT MODULE

### 3.1 Core Functionality

**Location**: [student/](student/)

#### Main Features:
1. **Dashboard & Enrollment**
   - [student/dashboard.php](student/dashboard.php) - Main student dashboard
   - View enrolled units/courses
   - Semester selector
   - Notification center

2. **Course Navigation**
   - [student/my_units.php](student/my_units.php) - List enrolled units
   - [student/course_view.php](student/course_view.php) - View unit details

3. **Content Access**
   - [student/lesson_view.php](student/lesson_view.php) - View lesson materials
   - [student/unit_notes.php](student/unit_notes.php) - Unit-specific notes
   - [student/notes.php](student/notes.php) - All notes
   - [student/viewnotes.php](student/viewnotes.php) - View specific note

4. **Assessments**
   - [student/take_assessment.php](student/take_assessment.php) - Take exams/quizzes
   - [student/take_assignment.php](student/take_assignment.php) - Submit assignment
   - [student/submit_assignment.php](student/submit_assignment.php) - File submission handler
   - [student/take_interactive_assignment.php](student/take_interactive_assignment.php) - Interactive submission

5. **Grading & Progress**
   - [student/grading_centre.php](student/grading_centre.php) - View grades
   - [student/my_progress.php](student/my_progress.php) - Progress tracking
   - [student/exam_reports.php](student/exam_reports.php) - Exam performance

6. **Attendance**
   - [student/attendance_submit.php](student/attendance_submit.php) - Mark attendance
   - [student/student_attendance.php](student/student_attendance.php) - Attendance view
   - [student/verify-attendance.php](student/verify-attendance.php) - QR verification

7. **Practical Labs (Smart Lab)**
   - [student/practical-page.php](student/practical-page.php) - Available practicals

8. **Notifications**
   - [student/notifications.php](student/notifications.php) - View all notifications

### 3.2 Database Schema

```sql
CREATE TABLE students (
  id INT PRIMARY KEY,
  reg_no VARCHAR(50) UNIQUE,
  name VARCHAR(100),
  email VARCHAR(100),
  university_id INT,
  department_id INT,
  course_id INT,
  year_of_study INT,
  year_joined INT,
  password VARCHAR(255)
);

CREATE TABLE student_units (
  id INT PRIMARY KEY,
  student_id INT,
  unit_id INT
);

CREATE TABLE submissions (
  id INT PRIMARY KEY,
  assignment_id INT,
  student_id INT,
  file_path VARCHAR(255),
  submitted_at TIMESTAMP,
  marks INT,
  is_graded TINYINT(1)
);

CREATE TABLE interactive_submissions (
  id INT PRIMARY KEY,
  student_id INT,
  assignment_id INT,
  submitted_at TIMESTAMP,
  score DECIMAL(5,2),
  graded TINYINT(1)
);

CREATE TABLE interactive_answers (
  id INT PRIMARY KEY,
  submission_id INT,
  question_id INT,
  option_id INT,
  answer_text TEXT,
  is_correct TINYINT(1),
  answer_audio VARCHAR(255)
);
```

### 3.3 Views

**Dashboard**: [student/dashboard.php](student/dashboard.php)
- Notification panel with latest 5 notifications
- Shows enrolled units by semester
- Color-coded status indicators
- Responsive modern design

**Key Views**:
- [student/my_units.php](student/my_units.php) - Unit list with semester filter
- [student/grading_centre.php](student/grading_centre.php) - Grade display with analytics
- [student/my_progress.php](student/my_progress.php) - Overall progress tracking

---

## 4. ADMIN MODULE

### 4.1 Core Functionality

**Location**: [admin/](admin/)

#### Main Features:
1. **System Management**
   - [admin/dashboard.php](admin/dashboard.php) - Admin dashboard
   - System statistics and monitoring

2. **User Management**
   - [admin/add_lecturer.php](admin/add_lecturer.php) - Add lecturers
   - [admin/add_university.php](admin/add_university.php) - Manage universities
   - [admin/add_department.php](admin/add_department.php) - Add departments
   - [admin/add_course.php](admin/add_course.php) - Create courses

3. **Requests Handling**
   - [admin/pendingreq.php](admin/pendingreq.php) - Pending requests (file requests, team invites)

4. **Profile**
   - [admin/profile.php](admin/profile.php) - Admin profile management
   - [admin/update_password.php](admin/update_password.php) - Password management

### 4.2 Database Schema

```sql
CREATE TABLE admins (
  id INT PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(100) UNIQUE,
  password VARCHAR(255)
);

CREATE TABLE universities (
  id INT PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(100)
);

CREATE TABLE departments (
  id INT PRIMARY KEY,
  name VARCHAR(100),
  university_id INT
);

CREATE TABLE courses (
  id INT PRIMARY KEY,
  name VARCHAR(100),
  department_id INT,
  duration INT DEFAULT 4
);

CREATE TABLE units (
  id INT PRIMARY KEY,
  name VARCHAR(255),
  code VARCHAR(50),
  course_id INT,
  year INT,
  semester INT
);
```

### 4.3 Views

**Minimal Traditional LMS Admin Interface**:
- Basic CRUD operations
- Simple forms for data entry
- Limited analytics

---

## 5. SMART-LAB MODULE (Advanced Lab Management)

### 5.1 Core Architecture & Features

**Location**: [smart-lab/](smart-lab/)

The Smart Lab is a comprehensive laboratory management system with multiple advanced features.

#### Main Features:
1. **Authentication & Authorization**
   - Multi-method authentication (password, QR, biometric)
   - Role-based access (student, technician, lecturer, admin)
   - Lab session authentication

2. **Lab Management**
   - Lab registration and profiling
   - Lab capacity management
   - Lab schedules and occupancy tracking

3. **Practical Management** ([smart-lab/controllers/PracticalController.php](smart-lab/controllers/PracticalController.php))
   - Create practicals with detailed specs
   - Schedule practicals in labs
   - Manage max students per practical
   - Lab availability checking
   - Time slot conflict resolution

4. **Lab Sessions** ([smart-lab/controllers/StudentPracticalController.php](smart-lab/controllers/StudentPracticalController.php))
   - Session creation and management
   - Student enrollment in sessions
   - QR code generation for lab entry
   - Attendance tracking

5. **Notebooks** ([smart-lab/controllers/NotebookController.php](smart-lab/controllers/NotebookController.php))
   - Student lab notebooks with versioning
   - Content templates (objectives, materials, procedure, observations, conclusions)
   - Status workflow (draft → submitted → approved/rejected)
   - Revision tracking

6. **Reports** ([smart-lab/controllers/ReportController.php](smart-lab/controllers/ReportController.php))
   - Lab report generation from notebooks
   - Report submission and grading
   - PDF export capabilities

7. **Asset Management** ([smart-lab/controllers/AssetController.php](smart-lab/controllers/AssetController.php))
   - Equipment and chemical inventory
   - Asset tracking with blockchain
   - Issue/return workflows
   - Usage logging
   - Min quantity alerts

8. **QR Authentication** ([smart-lab/controllers/QrAuthController.php](smart-lab/controllers/QrAuthController.php))
   - Generate QR codes for lab entry
   - QR-based session tracking
   - Attendance verification via QR

9. **Blockchain Asset Tracking** ([smart-lab/blockchain/AssetTracker.php](smart-lab/blockchain/AssetTracker.php))
   - Immutable asset transaction ledger
   - SHA-256 block hashing
   - Chain validation
   - Asset history preservation

10. **Audit Logging** ([smart-lab/controllers/AuditController.php](smart-lab/controllers/AuditController.php))
    - User activity tracking
    - Module-specific action logging
    - IP address and user agent logging
    - Audit trail reports

### 5.2 Smart Lab Database Schema

**Key Tables** (database: `unilis_smartlab`):

```sql
CREATE TABLE users (
  id VARCHAR(36) PRIMARY KEY,
  reg_number VARCHAR(50),
  full_name VARCHAR(200),
  email VARCHAR(100),
  password VARCHAR(255),
  role ENUM('student', 'technician', 'lecturer', 'admin'),
  is_active TINYINT(1),
  lab_id VARCHAR(36),
  biometric_hash VARCHAR(255),
  created_at TIMESTAMP
);

CREATE TABLE labs (
  id VARCHAR(36) PRIMARY KEY,
  name VARCHAR(100),
  lab_code VARCHAR(50),
  department_id VARCHAR(36),
  location VARCHAR(100),
  max_capacity INT,
  description TEXT,
  features JSON,
  created_at TIMESTAMP
);

CREATE TABLE practicals (
  id VARCHAR(36) PRIMARY KEY,
  title VARCHAR(255),
  description TEXT,
  objective TEXT,
  theory TEXT,
  lab_id VARCHAR(36),
  lecturer_id VARCHAR(36),
  course_code VARCHAR(50),
  scheduled_date DATE,
  start_time TIME,
  end_time TIME,
  duration_hours INT,
  max_students INT,
  required_equipment TEXT,
  required_chemicals TEXT,
  procedure_json JSON,
  observations_table_structure JSON,
  safety_notes TEXT,
  results_template TEXT,
  calculations_template TEXT,
  status ENUM('draft', 'scheduled', 'active', 'completed'),
  created_at TIMESTAMP
);

CREATE TABLE lab_sessions (
  id VARCHAR(36) PRIMARY KEY,
  lab_id VARCHAR(36),
  practical_id VARCHAR(36),
  scheduled_date DATETIME,
  start_time TIME,
  end_time TIME,
  status ENUM('open', 'active', 'closed'),
  confirmation_code VARCHAR(100),
  max_participants INT,
  started_at DATETIME,
  ended_at DATETIME,
  created_at TIMESTAMP
);

CREATE TABLE notebooks (
  id VARCHAR(36) PRIMARY KEY,
  session_id VARCHAR(36),
  student_id VARCHAR(36),
  group_id VARCHAR(36),
  title VARCHAR(255),
  content LONGTEXT,
  version INT,
  status ENUM('draft', 'submitted', 'approved', 'rejected'),
  created_by VARCHAR(36),
  creator_role ENUM('student', 'technician'),
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);

CREATE TABLE notebook_versions (
  id VARCHAR(36) PRIMARY KEY,
  notebook_id VARCHAR(36),
  version INT,
  content LONGTEXT,
  saved_at TIMESTAMP
);

CREATE TABLE reports (
  id VARCHAR(36) PRIMARY KEY,
  notebook_id VARCHAR(36),
  student_id VARCHAR(36),
  title VARCHAR(255),
  content LONGTEXT,
  status ENUM('draft', 'submitted', 'graded'),
  grade DECIMAL(5,2),
  feedback TEXT,
  created_at TIMESTAMP,
  submitted_at TIMESTAMP,
  graded_at TIMESTAMP
);

CREATE TABLE approvals (
  id VARCHAR(36) PRIMARY KEY,
  document_type ENUM('notebook', 'report'),
  document_id VARCHAR(36),
  reviewer_id VARCHAR(36),
  action ENUM('approved', 'rejected', 'revision_requested'),
  comments TEXT,
  signature_hash VARCHAR(255),
  reviewed_at TIMESTAMP
);

CREATE TABLE assets (
  id VARCHAR(36) PRIMARY KEY,
  asset_code VARCHAR(50) UNIQUE,
  name VARCHAR(200),
  type ENUM('equipment', 'chemical', 'consumable', 'instrument'),
  lab_id VARCHAR(36),
  quantity DECIMAL(10,2),
  unit VARCHAR(30),
  status ENUM('available', 'in_use', 'maintenance', 'disposed', 'in_transit'),
  serial_number VARCHAR(100),
  purchase_date DATE,
  warranty_expiry DATE,
  min_quantity INT,
  location VARCHAR(100),
  unit_price DECIMAL(10,2),
  safety_notes TEXT,
  description TEXT,
  notes TEXT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);

CREATE TABLE asset_transactions (
  id VARCHAR(36) PRIMARY KEY,
  asset_id VARCHAR(36),
  action ENUM('registered', 'issued', 'returned', 'transferred', 'disposed', 'usage_logged'),
  user_id VARCHAR(36),
  lab_id VARCHAR(36),
  target_lab_id VARCHAR(36),
  quantity DECIMAL(10,2),
  notes TEXT,
  created_at TIMESTAMP
);

CREATE TABLE blockchain_blocks (
  id INT PRIMARY KEY,
  block_index INT,
  timestamp DATETIME,
  block_data JSON,
  previous_hash VARCHAR(64),
  hash VARCHAR(64),
  nonce INT,
  created_at TIMESTAMP
);

CREATE TABLE qr_sessions (
  id VARCHAR(36) PRIMARY KEY,
  token VARCHAR(255),
  status ENUM('open', 'claimed', 'expired'),
  user_id VARCHAR(36),
  expires_at DATETIME,
  created_at TIMESTAMP
);

CREATE TABLE lab_attendance (
  id VARCHAR(36) PRIMARY KEY,
  schedule_id VARCHAR(36),
  user_id VARCHAR(36),
  attendance_time DATETIME,
  login_method ENUM('password', 'qr', 'biometric'),
  confirmed TINYINT(1),
  created_at TIMESTAMP
);

CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id VARCHAR(36),
  action VARCHAR(200),
  module VARCHAR(100),
  ip_address VARCHAR(45),
  user_agent VARCHAR(500),
  created_at TIMESTAMP
);
```

### 5.3 Smart Lab Controllers

**[smart-lab/controllers/](smart-lab/controllers/)**

| Controller | Key Methods | Responsibilities |
|-----------|------------|-----------------|
| **AuthController** | `login()`, `logout()`, `loginByQR()`, `loginBiometric()` | Multi-method authentication |
| **DashboardController** | `index()`, `apiStats()`, `apiActivity()`, `apiLabOccupancy()` | Role-specific dashboards, stats |
| **PracticalController** | `index()`, `create()`, `checkAvailability()`, `updateStatus()` | Lab practical management |
| **StudentPracticalController** | `dashboard()`, `start()`, `execute()`, `submit()` | Student lab participation |
| **NotebookController** | `index()`, `create()`, `updateContent()`, `submit()` | Lab notebook management |
| **ReportController** | `index()`, `create()`, `grade()` | Lab report handling |
| **ReportSubmissionController** | `submit()`, `getByStudent()` | Report submission workflow |
| **AssetController** | `index()`, `create()`, `issue()`, `returnAsset()`, `track()` | Equipment/chemical inventory |
| **ScheduleController** | `index()`, `create()`, `getAvailable()` | Lab schedule management |
| **InventoryController** | `index()`, `checkStatus()`, `lowStock()` | Real-time inventory view |
| **BlockchainController** | `viewChain()`, `verifyBlock()`, `validateAsset()` | Blockchain operations |
| **QrAuthController** | `generate()`, `poll()`, `scan()`, `confirm()` | QR code auth flow |
| **AuditController** | `index()`, `search()`, `export()` | Activity logging/reporting |
| **AdminPracticalRequestController** | `index()`, `approve()`, `reject()` | Admin practical approvals |
| **ExperimentController** | `index()`, `create()` | Experiment/procedure management |
| **UsersController** | `index()`, `create()`, `updateRole()` | User management |

### 5.4 Smart Lab Models

**[smart-lab/models/](smart-lab/models/)**

| Model | Key Methods | Data Management |
|-------|------------|-----------------|
| **PracticalModel** | `create()`, `getAll()`, `getById()`, `checkLabAvailability()` | Practical CRUD & availability |
| **NotebookModel** | `create()`, `getByStudent()`, `update()`, `updateStatus()` | Notebook versioning |
| **ReportModel** | `create()`, `getByStudent()`, `grade()`, `search()` | Report management |
| **AssetModel** | `create()`, `getAll()`, `updateQuantity()`, `checkMinQty()` | Asset inventory |
| **AuthModel** | `getUserByID()`, `updatePassword()`, `getBiometric()` | User authentication |
| **BlockchainModel** | `addBlock()`, `getChain()`, `validate()` | Blockchain operations |
| **DashboardModel** | `getStats()`, `getLabOccupancy()`, `getActivity()` | Analytics/stats |
| **ScheduleModel** | `getByLab()`, `checkConflicts()`, `getAvailable()` | Schedule management |

### 5.5 Smart Lab Authentication

**File**: [smart-lab/auth/Auth.php](smart-lab/auth/Auth.php)

**Static Methods**:
```php
// Password-based login
public static function login(string $regNo, string $password): bool
public static function loginByEmail(string $email, string $password): bool

// Biometric login
public static function loginBiometric(string $biometricHash): bool

// QR-based login
public static function loginByQR(string $qrToken, string $sessionId): bool

// Confirmation code login
public static function loginByCode(string $confirmationCode): array

// Session helpers
public static function check(): bool
public static function guard(string $requiredRole = null): void
public static function id(): string
public static function role(): string
public static function name(): string

// MFA support
public static function requireMultiFactor(): bool
public static function initiateMultiFactor(string $userId): string
public static function verifyMultiFactor(string $code): bool
```

### 5.6 Views/Templates

**[smart-lab/views/](smart-lab/views/)**

Directory structure:
```
views/
  ├── layouts/
  │   ├── header.php
  │   └── footer.php
  ├── auth/
  │   ├── login.php
  │   ├── register.php
  │   └── mfa.php
  ├── dashboard/
  │   └── index.php
  ├── practicals/
  │   ├── index.php
  │   ├── create.php
  │   ├── view.php
  │   └── schedule.php
  ├── practicals-requests/
  │   └── manage.php
  ├── notebooks/
  │   ├── student_index.php
  │   ├── manage.php
  │   ├── create.php
  │   └── view.php
  ├── reports/
  │   ├── index.php
  │   ├── create.php
  │   └── view.php
  ├── assets/
  │   ├── index.php
  │   ├── create.php
  │   ├── view.php
  │   └── issue.php
  ├── inventory/
  │   └── index.php
  ├── schedule/
  │   ├── index.php
  │   └── create.php
  ├── audit/
  │   └── index.php
  ├── blockchain/
  │   └── index.php
  ├── student/
  │   └── dashboard.php
  └── users/
      └── manage.php
```

### 5.7 Blockchain Integration

**File**: [smart-lab/blockchain/AssetTracker.php](smart-lab/blockchain/AssetTracker.php)

**Purpose**: Immutable tracking of asset transactions

**Key Methods**:
```php
public function register(array $asset, string $techId, string $labId): Block
public function issue(string $assetId, string $userId, string $labId, float $qty, array $context): Block
public function returned(string $assetId, string $userId, string $labId, float $qty): Block
public function transfer(string $assetId, string $fromLab, string $toLab, float $qty): Block
public function dispose(string $assetId, string $labId, float $qty): Block
public function history(string $assetId): array
public function validateChain(): bool
public function getChainStats(): array
```

**Block Structure**:
```json
{
  "block_index": 1,
  "timestamp": "2026-04-15 10:30:00",
  "block_data": {
    "event": "asset_issued",
    "asset_id": "...",
    "asset_name": "...",
    "action": "issued",
    "quantity": 2,
    "user_id": "...",
    "lab_id": "..."
  },
  "previous_hash": "...",
  "hash": "...",
  "nonce": 0
}
```

### 5.8 QR Authentication Flow

**Process**:
1. Frontend calls [smart-lab/controllers/QrAuthController.php::generate()](smart-lab/controllers/QrAuthController.php) via AJAX
2. System generates QR token, creates `qr_sessions` record
3. Mobile camera scans QR code
4. Frontend polls `QrAuthController::poll()` for status
5. Upon mobile confirmation, user logs in with claimed session
6. Session marked as 'expired'

**QR Session Fields**:
- `id`: Session identifier
- `token`: QR-encoded token
- `status`: open/claimed/expired
- `user_id`: Claimed by user (if claimed)
- `expires_at`: 5-minute timeout

---

## 6. API ENDPOINTS & AJAX HANDLERS

### 6.1 Core API Endpoints

**Location**: [api/](api/)

| Endpoint | Method | Purpose | Returns |
|----------|--------|---------|---------|
| **get_units.php** | GET | Fetch units by course | JSON units array |
| **get_courses.php** | GET | Fetch courses by department | JSON courses array |
| **signaling.php** | POST/GET | WebRTC signaling (offer/answer/candidate) | Signal queue data |
| **meeting_state.php** | GET/POST | Meeting state management | Meeting status |
| **recording_upload.php** | POST | Handle meeting recordings | Upload confirmation |

### 6.2 Smart Lab API Endpoints

**[smart-lab/api/](smart-lab/api/)**

Most Smart Lab endpoints are controller-based via router.

**Key API Methods** (called via AJAX):

#### PracticalController:
- `checkAvailability()` - Verify lab availability for time slot
- `getAvailableSlots()` - Get free lab slots
- `getDailyPracticalCount()` - Check daily practical limit

#### NotebookController:
- `getSessions()` - Get available lab sessions
- `updateContent()` - Save notebook draft
- `submitNotebook()` - Submit for approval

#### ReportController:
- `generatePDF()` - Export report as PDF
- `getGradingStats()` - Grading statistics

#### AssetController:
- `getAssetStatus()` - Real-time asset status
- `checkQuantity()` - Verify quantity available
- `getHistory()` - Get asset transaction history

#### AuditController:
- `getActivityLog()` - Fetch audit logs
- `exportLogs()` - Export audit trail

### 6.3 Traditional LMS API Handlers

**[lecturer/ajax/](lecturer/ajax/)**, **[student/ajax/](student/ajax/)**

These handle dynamic interactions (notifications, grade updates, attendance, etc.)

---

## 7. CRITICAL DEPENDENCIES & UTILITIES

### 7.1 Core Utilities

**[smart-lab/utils/helpers.php](smart-lab/utils/helpers.php)**

Key functions:
```php
function redirect(string $path): void
function renderView(string $template, array $data = []): void
function sanitize(string $v): string
function sanitizeHTML(string $html): string
function jsonResponse(array $data, int $code = 200): void
function generateSignature(string $techId, string $docId): string
function logActivity(string $userId, string $action, string $module): void
```

**[smart-lab/utils/Validator.php](smart-lab/utils/Validator.php)**

Validation framework for forms and API inputs

### 7.2 Shared Includes (Traditional LMS)

**[includes/auth.php](includes/auth.php)** [EMPTY - Authentication handled separately]

**[includes/notifications.php](includes/notifications.php)**

Key functions:
```php
function notify_student_assignment_submitted($conn, $student_id, $assignment_id, ...)
function notify_lecturer_assignment_submitted($conn, $lecturer_id, $student_name, ...)
function get_latest_notifications($conn, $limit, $user_id, $user_role)
function get_unread_notification_count($conn, $user_id, $user_role)
function mark_notification_as_read($conn, $notification_id)
```

**[includes/email_system.php](includes/email_system.php)**

Key functions:
```php
function send_notification_email($email, $user_name, $subject, $title, $message, ...)
function send_deadline_reminder_email($email, $student_name, $assignment_title, ...)
function getConfiguredMailer(): PHPMailer
function get_email_template($type, $title, $message, $link, $user_name)
```

**[includes/assignment_handler.php](includes/assignment_handler.php)**

Key functions:
```php
function handle_assignment_creation($conn)
function save_assignment_settings($conn, $assignment_id, $settings)
function process_questions($conn, $assignment_id, $questions)
```

**[includes/deadline_reminders.php](includes/deadline_reminders.php)**

Automated deadline reminder system

**[includes/student_attendance.php](includes/student_attendance.php)**

Attendance tracking and verification

**[includes/ai_grading.php](includes/ai_grading.php)**

AI-powered answer evaluation and grading

**[includes/validation.php](includes/validation.php)**

Form validation helpers

### 7.3 External Dependencies

**composer.json**:
```json
{
  "require": {
    "php": ">=7.4",
    "phpmailer/phpmailer": "^6.8",
    "dompdf/dompdf": "^2.0"
  }
}
```

**Installed Packages**:
- PHPMailer - Email sending
- DOMPDF - PDF generation (reports)

### 7.4 Third-Party APIs

- WebRTC - Browser native (Vanilla JS)
- QR Code - Client-side generation (via JS library)
- File handling - Vanilla upload

---

## 8. INTEGRATION POINTS

### 8.1 Module Communication

**Smart Lab ↔ Traditional LMS**:
1. Users table synchronization
   - Smart Lab creates UUID-based users
   - Traditional LMS uses INT-based students/lecturers
   - No direct FK relationships

2. Course/Unit mapping
   - Smart Lab practicals → Traditional LMS units
   - Via `course_code` and manual mapping

3. Notifications
   - Both systems can send notifications
   - Separate notification tables (may need consolidation)

**Cross-Module Data Flows**:
- Student enrolls in unit (Traditional LMS) → Can take practicals in Smart Lab
- Lecturer creates assignment (Traditional) → Also available in Smart Lab via API
- Practical completed (Smart Lab) → Can trigger grade updates in Traditional LMS

### 8.2 Authentication Integration

**Two Separate Systems**:
1. **Traditional LMS**: MySQLi session-based (id, user_role)
2. **Smart Lab**: PDO session-based (id, user_role, lab_id)

Both support multiple auth methods:
- Password
- QR code
- Biometric
- Email verification
- MFA

### 8.3 Notification System Integration

**Traditional LMS**:
- Uses [includes/notifications.php](includes/notifications.php)
- Sends emails via PHPMailer
- Stores in `notifications` table

**Smart Lab**:
- Uses in-app notifications
- Audit logs track all activity
- Can trigger email alerts

### 8.4 WebRTC Meeting Integration

**[api/signaling.php](api/signaling.php)** - Central signaling server
- Handles offer/answer/candidate exchange
- Supports screen sharing via chunk transfer
- Connection: meeting_id, user_id based

**Meeting Tables**:
```sql
CREATE TABLE meetings (
  id INT PRIMARY KEY,
  lecturer_id INT,
  ... [meeting details]
);

CREATE TABLE signal_queue (
  id INT PRIMARY KEY,
  meeting_id INT,
  from_user_id INT,
  to_user_id INT,
  signal_type ENUM('offer', 'answer', 'candidate'),
  signal_data JSON,
  created_at TIMESTAMP
);
```

---

## 9. KEY TABLES & RELATIONSHIPS

### 9.1 Core Entity Relationships

**Traditional LMS Database** (`university_system`):

```
universities (1) ──← departments (many)
                        ↓ (many)
                      courses (many)
                        ↓ (many)
                        units
                        ↗    ↖
            lecturer_units    student_units
                  ↗              ↖
            lecturers          students


           units (1) ──← lecturer_units (many) ──→ lecturers
           units (1) ──← student_units (many) ──→ students
           units (1) ──← assignments (many) ──→ lecturers
         units (1) ──← meetings (many) ──→ lecturers
           units (1) ──← notes (many)
        assignments (1) ──← questions (many)
        assignments (1) ──← submissions (many) ──→ students
       submissions (1) ──← student_answers (many)
```

**Smart Lab Database** (`unilis_smartlab`):

```
       labs (1) ──← practicals (many) ──→ lecturers
       labs (1) ──← lab_sessions (many) ──→ practicals
  lab_sessions (1) ──← notebooks (many) ──→ students
   notebooks (1) ──← notebook_versions (many)
    notebooks (1) ──→ reports (many)

        labs (1) ──← assets (many)
       assets (1) ──← asset_transactions (many) ──→ users
        assets (1) ──← blockchain_blocks (many)

   lab_sessions (1) ──← lab_attendance (many) ──→ users
```

### 9.2 Critical Tables

**User Management**:
- `users` (Smart Lab) - UUID-based
- `students` (Traditional) - INT-based
- `lecturers` (Traditional) - INT-based
- `admins` (Traditional) - INT-based

**Content Management**:
- `units` - Core course units
- `assignments` - Assignment definitions
- `questions` - Quiz questions
- `submissions` - Student work submissions

**Lab Operations**:
- `practicals` - Lab practical sessions
- `lab_sessions` - Individual session instances
- `notebooks` - Student lab notebooks
- `reports` - Lab reports

**Inventory & Assets**:
- `assets` - Equipment/chemical inventory
- `asset_transactions` - Asset movements
- `blockchain_blocks` - Immutable transaction log

**Tracking & Logging**:
- `notifications` - User notifications
- `audit_logs` - Activity logging
- `qr_sessions` - QR authentication sessions
- `lab_attendance` - Attendance records

---

## 10. CURRENT STATE & KNOWN ISSUES

### 10.1 Completed Features

✅ **Smart Lab Module**:
- Core practical management system
- Lab session scheduling
- QR-based authentication
- Lab notebook system
- Asset tracking with blockchain
- Audit logging
- Report generation
- Inventory management

✅ **Traditional LMS**:
- Student/Lecturer dashboards
- Assignment creation and submission
- Grading system
- Attendance tracking
- Notifications
- Unit/course management
- WebRTC meeting support

✅ **Authentication**:
- Multi-method auth (password, QR, biometric)
- Session management
- CSRF protection
- Role-based access control

### 10.2 Known Issues & Limitations

⚠️ **Database Separation**:
- Two separate databases (`university_system` vs `unilis_smartlab`)
- No direct foreign key relationships between systems
- User data duplication potential

⚠️ **Authentication Dual Systems**:
- Separate auth implementations for traditional LMS and Smart Lab
- Session management could be unified
- No SSO between modules

⚠️ **Incomplete Smart Lab Features**:
- `[smart-lab/controllers/ExperimentController.php](smart-lab/controllers/ExperimentController.php)` - Partial implementation
- `[smart-lab/controllers/ScheduleController.php](smart-lab/controllers/ScheduleController.php)` - Basic functionality
- Some views missing/incomplete

⚠️ **Legacy Code**:
- Multiple `dashboard_backup.php`, `profile_old.php` files
- Inconsistent naming conventions
- Mixed procedural and OOP code

⚠️ **API Limitations**:
- Limited API documentation
- No GraphQL endpoint
- RESTful endpoints scattered across files
- CORS headers may be overly permissive

⚠️ **File Organization**:
- Uploaded files mixed in assets/
- No clear backup strategy
- Large SQL dumps in repo root

⚠️ **Testing**:
- No automated test suite visible
- Multiple test files in root (`test_*.php`)
- No CI/CD pipeline visible

### 10.3 Potential Improvements

📋 **Recommended**:
1. Consolidate user systems (merge `users` with `students`/`lecturers`)
2. Unify authentication layer
3. Create shared service layer for notifications
4. Implement proper API gateway
5. Add comprehensive test suite
6. Clean up legacy files and backups
7. Implement proper state management for practicals
8. Add multi-tenant support (multiple universities)
9. Improve database indexing
10. Add API rate limiting and request validation

---

## 11. FILE STRUCTURE QUICK REFERENCE

```
unilis/
├── index.php                          # Traditional LMS landing page
├── index.html                         # Static landing page
├── login.php                          # Traditional login
├── logout.php                         # Session termination
├── verify.php                         # Email verification
├── 
├── config/
│   ├── database.php                   # Traditional LMS DB config
│   ├── db.php                         # DB abstraction layer
│   └── email.php                      # PHPMailer configuration
├── 
├── includes/
│   ├── auth.php                       # [EMPTY]
│   ├── notifications.php              # Notification system
│   ├── email_system.php               # Email utilities
│   ├── assignment_handler.php         # Assignment creation logic
│   ├── deadline_reminders.php         # Automated reminders
│   ├── ai_grading.php                 # AI grading system
│   ├── student_attendance.php         # Attendance tracking
│   ├── validation.php                 # Form validation
│   ├── header.php                     # Page header template
│   └── footer.php                     # Page footer template
├── 
├── student/
│   ├── dashboard.php                  # Student dashboard [MAIN]
│   ├── my_units.php                   # Enrolled units list
│   ├── course_view.php                # Unit details
│   ├── lesson_view.php                # Lesson materials
│   ├── notes.php                      # View notes
│   ├── take_assignment.php            # Assignment submission
│   ├── take_assessment.php            # Quiz/exam interface
│   ├── grading_centre.php             # Grade viewing
│   ├── my_progress.php                # Progress tracking
│   ├── exam_reports.php               # Exam performance
│   ├── attendance_submit.php          # Mark attendance
│   ├── practical-page.php             # Available practicals
│   ├── notifications.php              # Notification center
│   └── ajax/                          # AJAX handlers
├── 
├── lecturer/
│   ├── dashboard.php                  # Lecturer dashboard [MAIN]
│   ├── course_builder.php             # Create courses/units
│   ├── lesson_editor.php              # Create lessons
│   ├── create_assignment.php          # Assignment creator
│   ├── grade_submission.php           # Grading interface
│   ├── view_scores.php                # Grade overview
│   ├── meetings.php                   # Schedule meetings
│   ├── meeting_host.php               # Host WebRTC session
│   ├── lecturer_take_attendance.php   # Mark attendance
│   ├── request_files.php              # File requests
│   └── ajax/                          # AJAX handlers
├── 
├── admin/
│   ├── dashboard.php                  # Admin dashboard [MAIN]
│   ├── add_lecturer.php               # Add lecturer form
│   ├── add_course.php                 # Add course form
│   ├── add_department.php             # Add department form
│   ├── add_university.php             # Add university form
│   ├── pendingreq.php                 # Pending requests
│   └── profile.php                    # Admin profile
├── 
├── smart-lab/
│   ├── index.php                      # [ROUTER - MAIN ENTRY]
│   ├── landing.html                   # Smart Lab landing page
│   ├── 
│   ├── config/
│   │   ├── app.php                    # App configuration
│   │   ├── database.php               # PDO database config
│   │   └── roles.php                  # Role definitions
│   ├── 
│   ├── auth/
│   │   └── Auth.php                   # Authentication class
│   ├── 
│   ├── controllers/
│   │   ├── AuthController.php         # Login/logout
│   │   ├── DashboardController.php    # Dashboard stats
│   │   ├── PracticalController.php    # Practical management
│   │   ├── StudentPracticalController.php  # Student practical flow
│   │   ├── NotebookController.php     # Lab notebooks
│   │   ├── ReportController.php       # Lab reports
│   │   ├── AssetController.php        # Asset inventory
│   │   ├── AuditController.php        # Audit logs
│   │   ├── BlockchainController.php   # Blockchain interface
│   │   ├── QrAuthController.php       # QR authentication
│   │   └── ... [other controllers]
│   ├── 
│   ├── models/
│   │   ├── PracticalModel.php
│   │   ├── NotebookModel.php
│   │   ├── AssetModel.php
│   │   ├── AuthModel.php
│   │   └── ... [other models]
│   ├── 
│   ├── blockchain/
│   │   └── AssetTracker.php           # Blockchain operations
│   ├── 
│   ├── utils/
│   │   ├── helpers.php                # Utility functions
│   │   └── Validator.php              # Validation framework
│   ├── 
│   ├── routes/
│   │   └── web.php                    # Route mapping
│   ├── 
│   └── views/
│       ├── layouts/
│       ├── auth/
│       ├── dashboard/
│       ├── practicals/
│       ├── notebooks/
│       ├── reports/
│       ├── assets/
│       └── ... [other views]
├── 
├── api/
│   ├── signaling.php                  # WebRTC signaling
│   ├── get_units.php                  # Units API
│   ├── get_courses.php                # Courses API
│   ├── meeting_state.php              # Meeting state
│   └── recording_upload.php           # Recording upload
├── 
├── assets/
│   ├── css/                           # Stylesheets
│   ├── js/                            # JavaScript
│   ├── uploads/                       # User uploads
│   ├── assignments/
│   ├── meetings/
│   └── requested_files/
├── 
├── teams/                             # Team collaboration module
├── migrations/                        # Database migrations
├── vendor/                            # Composer dependencies
├── 
├── docker-compose.yml
├── Dockerfile
├── composer.json
└── DATABASE FILES:
    ├── docker/unilis.sql              # Traditional LMS schema
    ├── smart-lab/unilis_smartlab.sql  # Smart Lab schema
    └── [other SQL dumps]
```

---

## 12. EXECUTION FLOW EXAMPLES

### 12.1 Student Assignment Submission Flow

```
1. Student logs in via login.php
   ↓
2. Session set: user_id, user_role='student'
   ↓
3. Navigate to student/take_assignment.php
   ↓
4. Display assignment details + submission form
   ↓
5. Form POST to student/submit_assignment.php
   ↓
6. Handle file upload to assets/uploads/
   ↓
7. Insert record into submissions table
   ↓
8. Call notify_student_assignment_submitted()
   ↓
9. Send confirmation email via PHPMailer
   ↓
10. Notify lecturer via notify_lecturer_assignment_submitted()
    ↓
11. Grade available in lecturer/grade_submission.php
```

### 12.2 Smart Lab Student Practical Participation Flow

```
1. Student logs in via smart-lab/auth
   ↓
2. Route to smart-lab/student/dashboard
   ↓
3. Display available lab sessions
   ↓
4. Click "Start Practical" → StudentPracticalController::start()
   ↓
5. Check lab_attendance record (must exist)
   ↓
6. Create student_submissions record
   ↓
7. Redirect to execution interface
   ↓
8. Student completes lab work
   ↓
9. Save notebook via NotebookController::create()
   ↓
10. Submit notebook for approval
    ↓
11. Technician reviews in NotebookController::index()
    ↓
12. Approve/reject via approvals table
    ↓
13. Student can then create lab report (ReportController)
    ↓
14. Report submitted → Lecturer grades in ReportController::grade()
```

### 12.3 Lab Asset Tracking Flow (with Blockchain)

```
1. Admin creates asset via AssetController::create()
   ↓
2. Asset registered in assets table
   ↓
3. AssetTracker::register() called
   ↓
4. New blockchain block created for asset registration
   ↓
5. Technician issues asset via AssetController::issue()
   ↓
6. AssetTracker::issue() creates new block
   ↓
7. Asset transaction recorded in asset_transactions
   ↓
8. Quantity updated in assets table
   ↓
9. Student returns asset
   ↓
10. AssetTracker::returned() creates return block
    ↓
11. Complete immutable history in blockchain_blocks
    ↓
12. Can be viewed in AssetController::view() blockchain history
```

---

## 13. DEPLOYMENT CONSIDERATIONS

### 13.1 Environment Detection

Both systems auto-detect environment:
```php
$is_production = (strpos($_SERVER['HTTP_HOST'], 'unilis.jhubafrica.com') !== false);
```

- **Production**: Loads `app_production.php`, `database_production.php`
- **Development**: Uses local settings

### 13.2 Docker Setup

**[docker-compose.yml](docker-compose.yml)** defines:
- PHP 8.2 web container
- MySQL 8.0 database container
- Network for service communication

**Database service**: `smart-labs-db`
- Used in [smart-lab/config/database.php](smart-lab/config/database.php)
- Connection string: `mysql:host=smart-labs-db;dbname=unilis_smartlab`

### 13.3 File Permissions

**Upload directories need write permissions**:
- `assets/uploads/`
- `assets/assignments/`
- `assets/meetings/`
- `assets/requested_files/`

### 13.4 Email Configuration

Requires SMTP credentials in [config/email.php](config/email.php):
- SMTP host, port, username, password
- From address and name

---

## 14. SECURITY ARCHITECTURE

### 14.1 Authentication Security

- Password hashing: `password_hash()` / `password_verify()` (bcrypt)
- Biometric: SHA-256 hash comparison
- QR: Token-based with expiration
- Session: PHP sessions with timeout

### 14.2 Data Protection

- SQL injection: Prepared statements (PDO/MySQLi)
- XSS: `sanitize()` and `sanitizeHTML()` functions
- CSRF: `$_SESSION['csrf_token']` generation/validation
- File upload: Server-side validation (type, size)

### 14.3 Access Control

**Smart Lab Role-based**:
- `Auth::guard($requiredRole)` - Protect endpoints
- Check `$_SESSION['user_role']` for authorization
- Lab-specific checks via `$_SESSION['lab_id']`

---

## 15. PERFORMANCE OPTIMIZATION NOTES

**Query Optimization Opportunities**:
- Add indexes on foreign keys
- Cache frequently accessed data (units, courses)
- Optimize notebook version queries
- Archive old audit logs

**Caching Candidates**:
- Lab occupancy data
- User role/permission lookups
- Course structure (rarely changes)

---

This comprehensive analysis covers the entire UNILIS system architecture, including both the traditional Learning Management System and the advanced Smart Lab module. The system is production-ready with Docker support and multiple authentication methods, though it would benefit from further consolidation of dual systems and addition of comprehensive test coverage.
