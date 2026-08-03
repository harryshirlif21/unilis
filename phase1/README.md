# UNILIS Phase 1 - Academic Foundation Expansion

## Overview
Phase 1 extends UNILIS with Department Administration, Technician Management, Academic Assignments, Dynamic Permissions, and System Upgrade Manager - all without affecting existing functionality.

## What Was Built

### 1. Database Expansion (Non-Destructive)
- **Upgraded existing tables**: Added columns to `courses`, `admins`, `lecturers`, `students`, `notifications`, `assignments` without removing any existing data
- **Created 7 new tables**: `department_admins`, `technicians`, `system_versions`, `system_migrations`, `system_upgrade_logs`, `technician_pools`, `pool_technicians`
- **Upgraded existing `assignments` table**: Added `assignment_type`, `user_id`, `user_role`, `reference_type`, `reference_id`, `academic_year`, `is_active`, `assigned_by`, `expires_at` columns for academic assignment support
- **Set admin@unilis.com as Super Admin** with `is_super_admin = 1`

### 2. New Roles
| Role | Description | Login Source |
|------|-------------|-------------|
| **Global Admin** (existing) | Full system access, can add other admins | `admins` table |
| **Department Admin** (new) | Department-level management | `admins` + `department_admins` |
| **Technician** (new) | Lab support (prepared for SmartLab) | `technicians` table |
| **Lecturer** (existing) | Teaching & assessment | `lecturers` table |
| **Student** (existing) | Learning & submissions | `students` table |

### 3. Files Created
```
phase1/
├── ARCHITECTURE.md              # Architecture design document
├── README.md                    # This file
├── config/
│   └── phase1_config.php        # Phase 1 configuration
├── includes/
│   ├── auth_extended.php        # Extended authentication & permissions
│   └── login_handler.php        # New role login handler
├── admin/
│   ├── upgrade_manager.php      # System Upgrade Manager UI
│   ├── department_admins.php    # Department Admin management
│   └── technicians.php          # Technician management
├── department_admin/
│   └── dashboard.php            # Department Admin dashboard
├── technician/
│   └── dashboard.php            # Technician dashboard
├── database/
│   └── migration_001_phase1.php # Database migration
└── tests/
    └── phase1_tests.php         # Test suite
```

### 4. Files Modified
- **`actions.php`**: Added Phase 1 login handler integration for new roles
- **`login.php`**: Added redirect support for `department_admin` and `technician` roles
- **`admin/dashboard.php`**: Added Phase 1 menu items (System Upgrade Manager, Department Admins, Technicians)

## Deployment Instructions

### Step 1: Run the Database Migration
1. Log in as **Global Admin** (`admin@unilis.com`)
2. Go to **Admin Dashboard** → **System Upgrade Manager** (in the Phase 1 menu)
3. Click **"Run Migration"** for `migration_001_phase1.php`
4. The migration will:
   - Add missing columns to existing tables
   - Create 9 new tables
   - Set admin@unilis.com as Super Admin
   - Record the version in `system_versions`

### Step 2: Create Department Admins
1. Go to **Admin Dashboard** → **Department Admins**
2. Select an existing admin user and assign them to a department
3. That admin can now log in as a Department Admin

### Step 3: Create Technicians
1. Go to **Admin Dashboard** → **Technicians**
2. Fill in the technician details and submit
3. The technician can now log in with their email and password

### Step 4: Run Tests
1. Go to `phase1/tests/phase1_tests.php` (logged in as Global Admin)
2. Verify all tests pass

## Architecture

### Permission System
- **Global Admin**: Has all permissions (checked via `is_super_admin`)
- **Department Admin**: Permissions defined in `$DEPARTMENT_ADMIN_PERMISSIONS` config
- **Technician**: Basic permissions (dashboard, profile)
- **Lecturer**: Permissions derived from `academic_assignments` table
- **Student**: Implicit permissions

### Academic Assignment Types
1. Unit Lecturer
2. Class Supervisor
3. Group Supervisor
4. Teaching Assistant
5. Lab Coordinator
6. Research Coordinator
7. Attachment Coordinator
8. Innovation Mentor

### Future Integration Points
- **SmartLab**: Technician role is prepared for SmartLab integration
- **Dynamic Menus**: `$MENU_STRUCTURE` config is ready for dynamic sidebar generation
- **Permission Engine**: `phase1_has_permission()` can be called from any module

## Rollback
To rollback Phase 1:
1. Go to **System Upgrade Manager**
2. Run the rollback function (drops new tables only)
3. Existing data and upgraded columns remain intact

## Testing
Run `phase1/tests/phase1_tests.php` as Global Admin to verify:
- All database tables exist
- All upgraded columns are present
- Super Admin is configured
- All files are in place
- Login integration is working
- Admin dashboard menu items are present