# UNILIS Phase 1 - Academic Foundation Expansion
## Architecture Design Document

## Overview
This phase extends UNILIS with Department Administration, Technician Management, Academic Assignments, Dynamic Permissions, and System Upgrade Manager - all without affecting existing functionality.

## Design Principles
1. **Non-destructive**: All additions, no modifications to existing code
2. **Backward compatible**: Existing users continue working normally
3. **Additive architecture**: New features integrate into existing architecture
4. **Composable**: Future phases plug into this foundation

## New Database Tables (in `unilis` database)
- `department_admins` - Maps admins to departments
- `technicians` - Technician accounts
- `system_versions` - Version tracking for system upgrades
- `system_migrations` - Migration history
- `system_upgrade_logs` - Upgrade activity log
- `technician_pools` - Groups of technicians
- `pool_technicians` - Technician pool assignments
- `short_courses` - Short course catalog
- `short_course_units` - Short course unit modules
- `short_course_tutors` - Short course tutor assignments

## Upgraded Tables
- `admins` - Added `is_verified`, `is_super_admin`, `created_at`, `updated_at`
- `assignments` - Added `assignment_type`, `user_id`, `user_role`, `reference_type`, `reference_id`, `academic_year`, `is_active`, `assigned_by`, `expires_at` for academic assignment support
- `courses` - Added `course_type`
- `lecturers` - Added `is_verified`, `verification_token`, `token_expires_at`
- `students` - Added `is_verified`, `verification_code`, `token_expires_at`, `verified_at`
- `notifications` - Expanded `user_role` to support new roles

## Role Hierarchy
```
Global Admin (admin) → Full system access
  └── Department Admin (department_admin) → Department-level management
       ├── Lecturer (lecturer) → Teaching & assessment
       ├── Technician (technician) → Lab support (prepared for SmartLab)
       └── Student (student) → Learning & submissions
```

## Academic Assignment Types
1. Unit Lecturer - Teaches a specific unit
2. Class Supervisor - Supervises a class/cohort
3. Group Supervisor - Supervises student groups
4. Teaching Assistant - Assists with teaching
5. Lab Coordinator - Coordinates lab activities
6. Research Coordinator - Coordinates research
7. Attachment Coordinator - Coordinates attachments
8. Innovation Mentor - Mentors innovation projects

## File Structure (New Files)
```
phase1/
├── ARCHITECTURE.md
├── config/
│   └── phase1_config.php          # Phase 1 configuration
├── includes/
│   ├── auth_extended.php          # Extended authentication
│   ├── permission_engine.php      # Dynamic permission engine
│   ├── menu_generator.php         # Dynamic menu generation
│   └── upgrade_manager.php        # System upgrade utilities
├── admin/
│   ├── upgrade_manager.php        # System Upgrade Manager UI
│   ├── department_admins.php      # Department admin management
│   └── technicians.php            # Technician management
├── department_admin/
│   ├── dashboard.php              # Department Admin dashboard
│   ├── departments.php            # Department management
│   ├── courses.php                # Course management
│   ├── units.php                  # Unit management
│   ├── calendar.php               # Academic calendar
│   ├── semesters.php              # Semester configuration
│   ├── practical_units.php        # Practical unit configuration
│   ├── laboratories.php           # Lab registration
│   ├── lab_assignments.php        # Lab assignments
│   ├── technician_pools.php       # Technician pool management
│   ├── technicians.php            # Technician registration
│   ├── supervisors.php            # Class supervisor assignment
│   ├── analytics.php              # Department analytics
│   ├── lecturer_workload.php      # Lecturer workload view
│   └── technician_workload.php    # Technician workload view
├── technician/
│   ├── dashboard.php              # Technician dashboard
│   └── profile.php                # Profile management
├── database/
│   ├── migration_001_phase1.php   # Phase 1 database migration
│   └── rollback_001_phase1.php    # Rollback script
└── tests/
    └── phase1_tests.php           # Phase 1 test suite
```

## Integration Points
1. **Login**: Extended to support new roles (department_admin, technician)
2. **Session**: New role values added to existing session system
3. **Admin Dashboard**: System Upgrade Manager tab added
4. **Database**: New tables added, existing tables untouched
5. **Navigation**: Menu generator prepared for future dynamic menus