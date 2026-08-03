<?php
/**
 * Phase 1 - Academic Foundation Expansion Configuration
 * UNILIS - University Learning Integrated System
 * 
 * This file contains all configuration for Phase 1 features.
 * It is loaded by the extended auth system and permission engine.
 */

// Prevent direct access
if (!defined('PHASE1_ACCESS') && !defined('STDIN')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access to this file is forbidden.');
}

// ── Phase 1 Version ──────────────────────────────────────────────────────────
define('PHASE1_VERSION', '1.0.0');
define('PHASE1_RELEASE_DATE', '2026-07-30');

// ── New Role Definitions ─────────────────────────────────────────────────────
define('ROLE_DEPARTMENT_ADMIN', 'department_admin');
define('ROLE_TECHNICIAN', 'technician');

// ── Academic Assignment Types ────────────────────────────────────────────────
$ACADEMIC_ASSIGNMENTS = [
    'unit_lecturer'       => 'Unit Lecturer',
    'class_supervisor'    => 'Class Supervisor',
    'group_supervisor'    => 'Group Supervisor',
    'teaching_assistant'  => 'Teaching Assistant',
    'lab_coordinator'     => 'Lab Coordinator',
    'research_coordinator'=> 'Research Coordinator',
    'attachment_coordinator' => 'Attachment Coordinator',
    'innovation_mentor'   => 'Innovation Mentor',
];

// ── Department Admin Permissions ─────────────────────────────────────────────
$DEPARTMENT_ADMIN_PERMISSIONS = [
    'manage_departments'        => 'Manage Departments',
    'manage_courses'            => 'Manage Courses',
    'manage_units'              => 'Manage Units',
    'configure_academic_calendar' => 'Configure Academic Calendar',
    'configure_semester'        => 'Configure Semester',
    'configure_practical_units' => 'Configure Practical Units',
    'register_laboratories'     => 'Register Laboratories',
    'assign_laboratories'       => 'Assign Laboratories',
    'configure_technician_pools'=> 'Configure Technician Pools',
    'assign_class_supervisors'  => 'Assign Class Supervisors',
    'register_technicians'      => 'Register Technicians',
    'view_department_analytics' => 'View Department Analytics',
    'view_lecturer_workload'    => 'View Lecturer Workload',
    'view_technician_workload'  => 'View Technician Workload',
];

// ── Technician Permissions ───────────────────────────────────────────────────
$TECHNICIAN_PERMISSIONS = [
    'access_dashboard'          => 'Access Dashboard',
    'manage_profile'            => 'Manage Profile',
    // Future SmartLab permissions will be added here in later phases
];

// ── Permission Mapping: Role → Permissions ──────────────────────────────────
$ROLE_PERMISSION_MAP = [
    'admin'             => [], // Global admin has all permissions (checked separately)
    'department_admin'  => $DEPARTMENT_ADMIN_PERMISSIONS,
    'lecturer'          => [], // Lecturer permissions come from academic assignments
    'technician'        => $TECHNICIAN_PERMISSIONS,
    'student'           => [], // Student permissions are implicit
];

// ── Menu Structure (for future dynamic menu generation) ──────────────────────
$MENU_STRUCTURE = [
    'admin' => [
        'label' => 'Global Admin',
        'icon'  => 'fa-shield-alt',
        'items' => [
            ['label' => 'Dashboard',           'url' => 'admin/dashboard.php',           'icon' => 'fa-tachometer-alt'],
            ['label' => 'System Upgrade',       'url' => 'phase1/admin/upgrade_manager.php', 'icon' => 'fa-database'],
            ['label' => 'Department Admins',    'url' => 'phase1/admin/department_admins.php', 'icon' => 'fa-user-tie'],
            ['label' => 'Technicians',          'url' => 'phase1/admin/technicians.php',     'icon' => 'fa-tools'],
        ],
    ],
    'department_admin' => [
        'label' => 'Department Admin',
        'icon'  => 'fa-building',
        'items' => [
            ['label' => 'Dashboard',            'url' => 'phase1/department_admin/dashboard.php', 'icon' => 'fa-tachometer-alt'],
            ['label' => 'Departments',          'url' => 'phase1/department_admin/departments.php', 'icon' => 'fa-building'],
            ['label' => 'Courses',              'url' => 'phase1/department_admin/courses.php',     'icon' => 'fa-book'],
            ['label' => 'Units',                'url' => 'phase1/department_admin/units.php',       'icon' => 'fa-cube'],
            ['label' => 'Academic Calendar',    'url' => 'phase1/department_admin/calendar.php',    'icon' => 'fa-calendar'],
            ['label' => 'Semesters',            'url' => 'phase1/department_admin/semesters.php',   'icon' => 'fa-calendar-alt'],
            ['label' => 'Practical Units',      'url' => 'phase1/department_admin/practical_units.php', 'icon' => 'fa-flask'],
            ['label' => 'Laboratories',         'url' => 'phase1/department_admin/laboratories.php',    'icon' => 'fa-microscope'],
            ['label' => 'Lab Assignments',      'url' => 'phase1/department_admin/lab_assignments.php', 'icon' => 'fa-tasks'],
            ['label' => 'Technician Pools',     'url' => 'phase1/department_admin/technician_pools.php','icon' => 'fa-users-cog'],
            ['label' => 'Technicians',          'url' => 'phase1/department_admin/technicians.php',     'icon' => 'fa-tools'],
            ['label' => 'Class Supervisors',    'url' => 'phase1/department_admin/supervisors.php',     'icon' => 'fa-chalkboard-teacher'],
            ['label' => 'Analytics',            'url' => 'phase1/department_admin/analytics.php',       'icon' => 'fa-chart-bar'],
            ['label' => 'Lecturer Workload',    'url' => 'phase1/department_admin/lecturer_workload.php', 'icon' => 'fa-user-clock'],
            ['label' => 'Technician Workload',  'url' => 'phase1/department_admin/technician_workload.php', 'icon' => 'fa-hard-hat'],
        ],
    ],
    'technician' => [
        'label' => 'Technician',
        'icon'  => 'fa-tools',
        'items' => [
            ['label' => 'Dashboard',            'url' => 'phase1/technician/dashboard.php', 'icon' => 'fa-tachometer-alt'],
            ['label' => 'Profile',              'url' => 'phase1/technician/profile.php',   'icon' => 'fa-user'],
        ],
    ],
];