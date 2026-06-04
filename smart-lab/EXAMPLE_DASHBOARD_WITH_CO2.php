<?php
/**
 * Example: Student Dashboard with CO2 Monitor Widget
 * 
 * This example shows how to integrate the CO2 monitor widget
 * into your existing student dashboard.
 */

// Your existing includes
// require_once __DIR__ . '/../../auth/Auth.php';
// require_once __DIR__ . '/../../utils/helpers.php';

// Check authentication (your existing code)
// if (!Auth::check()) redirect('auth/login');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard — UNILIS SmartLab</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/css/app.css">
</head>
<body class="dashboard-layout">

<div class="container">
    
    <!-- PAGE HEADER -->
    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Welcome back, <?= Auth::user()['full_name'] ?? 'Student' ?></p>
    </div>

    <!-- ╔════════════════════════════════════════════════════════════════╗
         ║  CO2 MONITOR WIDGET - INCLUDE HERE                            ║
         ╚════════════════════════════════════════════════════════════════╝
    -->
    <?php include __DIR__ . '/../../components/co2_monitor_widget.php'; ?>

    <!-- DASHBOARD CONTENT GRID -->
    <div class="dashboard-grid">

        <!-- Quick Stats Section -->
        <section class="dashboard-section">
            <h2>Quick Stats</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-content">
                        <div class="stat-value">8</div>
                        <div class="stat-label">Practicals Completed</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📝</div>
                    <div class="stat-content">
                        <div class="stat-value">5</div>
                        <div class="stat-label">Lab Notebooks</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⏰</div>
                    <div class="stat-content">
                        <div class="stat-value">2h 30m</div>
                        <div class="stat-label">Lab Time This Week</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Upcoming Practicals Section -->
        <section class="dashboard-section">
            <h2>Your Practicals</h2>
            <div class="practicals-list">
                <div class="practical-card">
                    <div class="practical-header">
                        <h3>Chemistry Lab - Titration</h3>
                        <span class="schedule-badge">Tomorrow, 2:00 PM</span>
                    </div>
                    <p class="practical-desc">Standard acid-base titration experiment</p>
                    <button class="btn btn-primary btn-sm">Take Practical</button>
                </div>

                <div class="practical-card">
                    <div class="practical-header">
                        <h3>Physics Lab - Optics</h3>
                        <span class="schedule-badge">Friday, 3:00 PM</span>
                    </div>
                    <p class="practical-desc">Light refraction and lens experiments</p>
                    <button class="btn btn-primary btn-sm">Take Practical</button>
                </div>
            </div>
        </section>

        <!-- Recent Activity Section -->
        <section class="dashboard-section">
            <h2>Recent Activity</h2>
            <div class="activity-feed">
                <div class="activity-item">
                    <div class="activity-icon">✓</div>
                    <div class="activity-content">
                        <div class="activity-title">Completed Biology Lab</div>
                        <div class="activity-time">2 hours ago</div>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon">📝</div>
                    <div class="activity-content">
                        <div class="activity-title">Submitted Lab Notebook</div>
                        <div class="activity-time">1 day ago</div>
                    </div>
                </div>
            </div>
        </section>

    </div>
    <!-- END DASHBOARD GRID -->

</div>
<!-- END CONTAINER -->

<!-- Basic Styles (if not using full stylesheet) -->
<style>
    .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1rem; }
    .page-header { margin-bottom: 2rem; }
    .page-header h1 { font-size: 2rem; font-weight: 700; margin: 0; }
    .page-header p { color: #666; margin: 0.5rem 0 0; }
    .dashboard-grid { display: grid; gap: 2rem; grid-template-columns: 1fr; }
    .dashboard-section { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
    .dashboard-section h2 { margin: 0 0 1rem; font-size: 1.2rem; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
    .stat-card { display: flex; gap: 1rem; padding: 1rem; border: 1px solid #eee; border-radius: 8px; }
    .stat-icon { font-size: 2rem; }
    .stat-value { font-size: 1.5rem; font-weight: 700; }
    .stat-label { color: #666; font-size: 0.85rem; }
    .practicals-list { display: grid; gap: 1rem; }
    .practical-card { padding: 1rem; border: 1px solid #eee; border-radius: 8px; }
    .practical-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
    .practical-header h3 { margin: 0; font-size: 1rem; }
    .schedule-badge { background: #e3f2fd; color: #1976d2; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; }
    .practical-desc { color: #666; margin: 0.5rem 0; font-size: 0.9rem; }
    .btn { padding: 0.5rem 1rem; border: none; border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 500; }
    .btn-primary { background: #1976d2; color: white; }
    .btn-sm { padding: 0.4rem 0.8rem; }
    .activity-feed { display: grid; gap: 1rem; }
    .activity-item { display: flex; gap: 1rem; padding: 1rem; border-left: 3px solid #1976d2; }
    .activity-icon { font-size: 1.5rem; }
    .activity-title { font-weight: 600; }
    .activity-time { color: #999; font-size: 0.85rem; }
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: 1fr; }
    }
</style>

</body>
</html>
