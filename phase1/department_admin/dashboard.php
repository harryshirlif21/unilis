<?php
/**
 * Department Admin Dashboard
 * UNILIS Academic Foundation Expansion
 */

define('PHASE1_ACCESS', true);
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_extended.php';

// Only department_admin can access
phase1_guard_role(ROLE_DEPARTMENT_ADMIN, '../../login.php');

$admin_id = $_SESSION['user_id'];
$department_id = $_SESSION['department_id'];

// Get department info
$dept = $conn->query("SELECT name FROM departments WHERE id = $department_id")->fetch_assoc();
$department_name = $dept ? $dept['name'] : 'Unknown';

// Get stats
$courses_count = $conn->query("SELECT COUNT(*) as c FROM courses WHERE department_id = $department_id")->fetch_assoc()['c'];
$units_count = $conn->query("SELECT COUNT(*) as c FROM units u JOIN courses c ON u.course_id = c.id WHERE c.department_id = $department_id")->fetch_assoc()['c'];
$lecturers_count = $conn->query("SELECT COUNT(*) as c FROM lecturers WHERE department_id = $department_id")->fetch_assoc()['c'];
$students_count = $conn->query("SELECT COUNT(*) as c FROM students WHERE department_id = $department_id")->fetch_assoc()['c'];
$technicians_count = $conn->query("SELECT COUNT(*) as c FROM technicians WHERE department_id = $department_id")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Admin - <?= htmlspecialchars($department_name) ?> - UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; color: #333; }
        .header { background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #fff; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 20px; }
        .header .user-info { font-size: 13px; opacity: .8; }
        .header a { color: #fff; text-decoration: none; opacity: .8; margin-left: 16px; }
        .header a:hover { opacity: 1; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .welcome { margin-bottom: 24px; }
        .welcome h2 { font-size: 22px; color: #1e3a8a; }
        .welcome p { color: #6b7280; font-size: 14px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #fff; border-radius: 10px; padding: 20px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.1); border-top: 3px solid #2563eb; }
        .stat-card .num { font-size: 28px; font-weight: 700; color: #1e3a8a; }
        .stat-card .lbl { font-size: 13px; color: #6b7280; margin-top: 4px; }
        .actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
        .action-card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.1); cursor: pointer; transition: all .2s; border: 1px solid #e5e7eb; }
        .action-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.15); }
        .action-card .icon { font-size: 24px; color: #2563eb; margin-bottom: 10px; }
        .action-card h3 { font-size: 15px; margin-bottom: 4px; }
        .action-card p { font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-building"></i> <?= htmlspecialchars($department_name) ?> - Department Admin</h1>
        <div>
            <span class="user-info"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
            <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    <div class="container">
        <div class="welcome">
            <h2>Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?></h2>
            <p>Manage your department's academic operations from here.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="num"><?= $courses_count ?></div><div class="lbl">Courses</div></div>
            <div class="stat-card"><div class="num"><?= $units_count ?></div><div class="lbl">Units</div></div>
            <div class="stat-card"><div class="num"><?= $lecturers_count ?></div><div class="lbl">Lecturers</div></div>
            <div class="stat-card"><div class="num"><?= $students_count ?></div><div class="lbl">Students</div></div>
            <div class="stat-card"><div class="num"><?= $technicians_count ?></div><div class="lbl">Technicians</div></div>
        </div>

        <div class="actions-grid">
            <div class="action-card" onclick="alert('Course management - coming soon')">
                <div class="icon"><i class="fas fa-book"></i></div>
                <h3>Manage Courses</h3>
                <p>Add, edit, and organize courses</p>
            </div>
            <div class="action-card" onclick="location.href='short_courses_analytics.php'">
                <div class="icon"><i class="fas fa-chart-line"></i></div>
                <h3>Short Courses Analytics</h3>
                <p>Short course banners, registrations, tutors &amp; sponsors</p>
            </div>
            <div class="action-card" onclick="alert('Unit management - coming soon')">
                <div class="icon"><i class="fas fa-cube"></i></div>
                <h3>Manage Units</h3>
                <p>Configure units per course</p>
            </div>
            <div class="action-card" onclick="alert('Academic calendar - coming soon')">
                <div class="icon"><i class="fas fa-calendar"></i></div>
                <h3>Academic Calendar</h3>
                <p>Set academic year and semesters</p>
            </div>
            <div class="action-card" onclick="alert('Laboratory management - coming soon')">
                <div class="icon"><i class="fas fa-microscope"></i></div>
                <h3>Laboratories</h3>
                <p>Register and assign labs</p>
            </div>
            <div class="action-card" onclick="alert('Technician management - coming soon')">
                <div class="icon"><i class="fas fa-tools"></i></div>
                <h3>Technicians</h3>
                <p>Manage technician pools</p>
            </div>
            <div class="action-card" onclick="alert('Class supervisors - coming soon')">
                <div class="icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <h3>Class Supervisors</h3>
                <p>Assign class supervisors</p>
            </div>
            <div class="action-card" onclick="alert('Analytics - coming soon')">
                <div class="icon"><i class="fas fa-chart-bar"></i></div>
                <h3>Analytics</h3>
                <p>View department statistics</p>
            </div>
            <div class="action-card" onclick="alert('Workload - coming soon')">
                <div class="icon"><i class="fas fa-user-clock"></i></div>
                <h3>Workload</h3>
                <p>View lecturer and technician workload</p>
            </div>
        </div>
    </div>
</body>
</html>