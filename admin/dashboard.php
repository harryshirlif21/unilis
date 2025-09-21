<?php
session_start();
require_once '../config/db.php';


if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$admin_res = $conn->query("SELECT * FROM admins WHERE id = $user_id");
$admin = $admin_res->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #2ecc71;
            --text-color: #333;
            --light-bg: #ecf0f1;
            --white: #ffffff;
            --border-color: #ddd;
            --danger-color: #e74c3c;
            --shadow-light: 0 4px 15px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 10px 30px rgba(0, 0, 0, 0.2);
            --info-color: #007bff;
            --warning-color: #ffc107;
            --success-color: #28a745;
            --header-gradient: linear-gradient(to right, #2c3e50, #34495e);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            min-height: 100vh;
            background-color: var(--light-bg);
            color: var(--text-color);
            line-height: 1.6;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: var(--header-gradient);
            color: var(--white);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header h1 {
            margin: 0;
            font-size: 1.8em;
            font-weight: 400;
        }

        .header .admin-info {
            font-size: 1.1em;
            font-weight: 300;
        }

        .hamburger-menu {
            font-size: 1.8em;
            cursor: pointer;
            background: none;
            border: none;
            color: var(--white);
            padding: 5px 10px;
            border-radius: 5px;
            transition: background-color 0.2s ease;
        }

        .hamburger-menu:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }

        .off-canvas-menu {
            position: fixed;
            top: 0;
            right: -300px;
            width: 280px;
            height: 100vh;
            background-color: var(--secondary-color);
            box-shadow: -6px 0 20px rgba(0, 0, 0, 0.3);
            transition: right 0.3s ease-in-out;
            z-index: 200;
            display: flex;
            flex-direction: column;
            padding: 25px 25px 40px 25px;
            box-sizing: border-box;
            overflow-y: auto;
        }

        .off-canvas-menu.active {
            right: 0;
        }

        .off-canvas-menu .close-btn {
            font-size: 2em;
            color: var(--white);
            align-self: flex-end;
            cursor: pointer;
            margin-bottom: 20px;
            transition: color 0.2s ease;
        }

        .off-canvas-menu .close-btn:hover {
            color: var(--danger-color);
        }

        .off-canvas-menu .menu-section-title {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9em;
            margin-top: 20px;
            margin-bottom: 8px;
            padding-left: 10px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding-bottom: 5px;
        }

        .off-canvas-menu .menu-item {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 14px 15px;
            margin-bottom: 8px;
            border: none;
            background: rgba(255, 255, 255, 0.1);
            color: var(--white);
            border-radius: 8px;
            cursor: pointer;
            text-align: left;
            text-decoration: none;
            font-size: 1.05em;
            transition: background-color 0.3s ease, transform 0.2s ease;
            gap: 12px;
            box-sizing: border-box;
        }

        .off-canvas-menu .menu-item:hover {
            background-color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
        }

        .off-canvas-menu .menu-item.logout {
            margin-top: auto;
            background-color: var(--danger-color);
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 150;
            transition: opacity 0.3s ease;
            opacity: 0;
        }

        .overlay.active {
            display: block;
            opacity: 1;
        }

        .content {
            flex: 1;
            padding: 30px;
            background: var(--light-bg);
            overflow-y: auto;
            width: 100%;
            box-sizing: border-box;
        }

        .content h2 {
            color: var(--secondary-color);
            margin-bottom: 25px;
            font-size: 2.2em;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 15px;
            text-align: center;
        }

        .stat-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
            padding: 0 10px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .stat-card {
            background-color: var(--white);
            border-radius: 15px;
            box-shadow: var(--shadow-light);
            padding: 25px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 120px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid var(--border-color);
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-medium);
        }

        .stat-card .icon {
            font-size: 2.8em;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .stat-card .number {
            font-size: 3em;
            font-weight: bold;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 1em;
            color: #666;
            margin-bottom: 10px;
        }

        .stat-card .university-select {
            width: 100%;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }

        .stat-card .university-select select {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            background-color: var(--white);
            color: var(--text-color);
            font-size: 0.9em;
            cursor: pointer;
            transition: border-color 0.2s ease;
        }

        .stat-card .university-select select:hover,
        .stat-card .university-select select:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .stat-card.users .icon, .stat-card.users .number { color: var(--info-color); }
        .stat-card.courses .icon, .stat-card.courses .number { color: var(--primary-color); }
        .stat-card.departments .icon, .stat-card.departments .number { color: var(--warning-color); }
        .stat-card.universities .icon, .stat-card.universities .number { color: var(--success-color); }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
            padding: 0 10px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .chart-container {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow-light);
            padding: 25px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 250px;
            border: 1px solid var(--border-color);
        }

        .chart-container h3 {
            margin-top: 0;
            color: var(--secondary-color);
            font-size: 1.4em;
            margin-bottom: 20px;
            text-align: center;
        }

        .chart-placeholder {
            width: 100%;
            height: 180px;
            background-color: #f0f0f0;
            border: 1px dashed var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #aaa;
            font-style: italic;
            font-size: 0.9em;
        }

        .recent-activity-section {
            margin-bottom: 40px;
            padding: 0 10px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .recent-activity-section h3 {
            color: var(--secondary-color);
            font-size: 1.8em;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
        }

        .table-container {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow-light);
            padding: 20px;
            overflow-x: auto;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95em;
            min-width: 600px;
        }

        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        table th {
            background-color: var(--light-bg);
            color: var(--secondary-color);
            font-weight: bold;
            text-transform: uppercase;
        }

        table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        table tbody tr:hover {
            background-color: #e6f7ff;
        }

        table td .action-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: bold;
            transition: color 0.2s ease;
        }

        table td .action-link:hover {
            color: var(--accent-color);
            text-decoration: underline;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            padding: 0 10px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .action-card {
            background-color: var(--white);
            border-radius: 15px;
            box-shadow: var(--shadow-light);
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 180px;
            border: 1px solid var(--border-color);
        }

        .action-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-medium);
            border-color: var(--primary-color);
        }

        .action-card .icon {
            font-size: 3.8em;
            color: var(--primary-color);
            margin-bottom: 15px;
            transition: color 0.2s ease;
        }

        .action-card:hover .icon {
            color: var(--accent-color);
        }

        .action-card h3 {
            font-size: 1.5em;
            color: var(--secondary-color);
            margin-top: 0;
            margin-bottom: 10px;
        }

        .action-card p {
            font-size: 0.95em;
            color: #666;
            margin: 0;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
        }

        .modal.delete-modal .modal-content {
            width: 90%;
            max-width: 400px;
            margin: 15% auto;
            padding: 20px;
            text-align: center;
        }

        .modal.delete-modal h3 {
            margin: 0 0 15px 0;
            color: var(--danger-color);
        }

        .modal-content {
            background-color: var(--white);
            margin: 10% auto;
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--shadow-medium);
            width: 80%;
            max-width: 800px;
            border: 1px solid var(--border-color);
        }

        .modal-content h3 {
            color: var(--secondary-color);
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.6em;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .close:hover,
        .close:focus {
            color: var(--danger-color);
        }

        .modal-content form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .modal-content label {
            font-size: 1em;
            color: var(--text-color);
            font-weight: 500;
        }

        .modal-content input[type="text"],
        .modal-content select {
            padding: 10px;
            font-size: 0.95em;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            width: 100%;
            box-sizing: border-box;
            transition: border-color 0.2s ease;
        }

        .modal-content input:focus,
        .modal-content select:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .modal-content button[type="submit"],
        .modal-content button[type="button"] {
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.95em;
            transition: background-color 0.2s ease;
            align-self: flex-start;
        }

        .modal-content button[type="submit"]:hover,
        .modal-content button[type="button"]:hover {
            background-color: var(--accent-color);
        }

        .unit-selection {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .unit-selection label {
            margin-right: 10px;
            flex: 0 0 auto;
        }

        .unit-selection select {
            flex: 1;
            min-width: 150px;
            max-width: 200px;
        }

        .unit-box {
            border: 1px solid var(--border-color);
            background-color: #f9f9f9;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: var(--shadow-light);
        }

        .unit-box h4 {
            margin-top: 0;
            margin-bottom: 10px;
            background-color: var(--light-bg);
            padding: 5px 10px;
            border-radius: 4px;
            color: var(--secondary-color);
        }

        .unit-inputs {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .unit-inputs label {
            margin-right: 10px;
            flex: 0 0 auto;
        }

        .unit-inputs input {
            flex: 1;
            min-width: 150px;
            max-width: 250px;
        }

        .success {
            color: var(--success-color);
            font-weight: bold;
            margin-bottom: 15px;
        }

        .error {
            color: var(--danger-color);
            font-weight: bold;
            margin-bottom: 15px;
        }

        .select-container {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .select-container select {
            padding: 10px;
            font-size: 0.95em;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            min-width: 200px;
            max-width: 400px;
        }

        .select-container button {
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.95em;
            transition: background-color 0.2s ease;
        }

        .select-container button:hover {
            background-color: var(--accent-color);
        }

        @media (max-width: 992px) {
            .stat-cards-grid, .charts-grid, .recent-activity-section, .action-grid {
                padding: 0 15px;
            }
            .modal-content {
                width: 80%;
            }
            .unit-selection select,
            .unit-inputs input {
                max-width: 100%;
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 10px 20px;
            }
            .header h1 {
                font-size: 1.5em;
            }
            .header .admin-info {
                font-size: 0.95em;
            }
            .content {
                padding: 20px;
            }
            .stat-cards-grid {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 15px;
            }
            .stat-card .number {
                font-size: 2.2em;
            }
            .stat-card .label {
                font-size: 0.85em;
            }
            .charts-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .chart-container {
                min-height: 220px;
            }
            .recent-activity-section h3 {
                font-size: 1.5em;
            }
            table {
                min-width: 500px;
            }
            .action-grid {
                gap: 15px;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            .action-card {
                padding: 20px;
                min-height: 160px;
            }
            .action-card .icon {
                font-size: 3em;
            }
            .action-card h3 {
                font-size: 1.2em;
            }
            .unit-selection,
            .unit-inputs {
                flex-direction: column;
                align-items: flex-start;
            }
            .unit-selection select,
            .unit-inputs input {
                width: 100%;
                max-width: none;
            }
            .select-container {
                flex-direction: column;
                align-items: flex-start;
            }
            .select-container select {
                width: 100%;
                max-width: none;
            }
        }

        @media (max-width: 480px) {
            .header .admin-info {
                display: none;
            }
            .content {
                padding: 15px;
            }
            .stat-cards-grid {
                grid-template-columns: 1fr;
            }
            .action-grid {
                grid-template-columns: 1fr;
            }
            .action-card {
                min-height: 150px;
            }
            .chart-container {
                min-height: 200px;
            }
            table {
                font-size: 0.85em;
                min-width: 400px;
            }
            table th, table td {
                padding: 8px 10px;
            }
            .modal-content {
                width: 90%;
                margin: 15% auto;
            }
        }
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s ease;
        }

        .btn-primary {
            background-color: #3498db;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-danger {
            background-color: #e74c3c;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .btn-success {
            background-color: #2ecc71;
        }

        .btn-success:hover {
            background-color: #27ae60;
        }

        .btn-secondary {
            background-color: #95a5a6;
        }

        .btn-secondary:hover {
            background-color: #7f8c8d;
        }

        .course-units-header {
            margin-bottom: 20px;
        }

        .course-units-header .select-container {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .course-units-header select {
            flex: 1;
            min-width: 300px;
            max-width: 600px;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 0.95em;
        }

        .button-group {
            display: flex;
            gap: 10px;
        }

        .btn i {
            margin-right: 5px;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        #unitsTableContainer {
            margin-top: 20px;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .action-buttons .btn {
            padding: 4px 8px;
            font-size: 0.9em;
        }

        /* Floating Units Display Styles */
        .floating-display {
            display: none;
            position: fixed;
            right: 20px;
            top: 80px;
            width: 400px;
            max-height: calc(100vh - 100px);
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            z-index: 1000;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .floating-display.active {
            display: flex;
            flex-direction: column;
        }

        .floating-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: var(--primary-color);
            color: white;
        }

        .floating-header h3 {
            margin: 0;
            font-size: 1.2em;
        }

        .floating-header .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 0 5px;
            line-height: 1;
        }

        .floating-header .close-btn:hover {
            color: #ff6b6b;
        }

        .units-grid {
            padding: 15px;
            overflow-y: auto;
            max-height: calc(100vh - 180px);
        }

        .unit-card {
            background: #f8f9fa;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .unit-card:hover {
            transform: translateX(-5px);
            box-shadow: 2px 2px 10px rgba(0,0,0,0.1);
        }

        .unit-info {
            flex: 1;
        }

        .unit-info h4 {
            margin: 0 0 5px 0;
            color: var(--secondary-color);
            font-size: 1.1em;
        }

        .unit-code {
            color: var(--primary-color);
            font-weight: bold;
            font-size: 0.9em;
        }

        .unit-meta {
            color: #666;
            font-size: 0.85em;
            margin-top: 5px;
        }

        .unit-card .delete-btn {
            background-color: transparent;
            border: none;
            color: var(--danger-color);
            cursor: pointer;
            padding: 5px;
            margin-left: 10px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }

        .unit-card .delete-btn:hover {
            background-color: rgba(231, 76, 60, 0.1);
        }

        .unit-card .delete-btn i {
            font-size: 1.2em;
        }

        .loading {
            text-align: center;
            padding: 20px;
            color: var(--secondary-color);
            font-style: italic;
        }

        .error-message {
            text-align: center;
            padding: 20px;
            color: var(--danger-color);
            background-color: rgba(231, 76, 60, 0.1);
            border-radius: 8px;
        }

        .empty-message {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .floating-display {
                width: 90%;
                right: 5%;
                left: 5%;
            }
        }
    </style>
</head>
<body>

<!-- Top Header Bar -->
<header class="header">
    <h1>UNILIS Admin Dashboard</h1>
    <div class="admin-info">Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?></div>
    <button class="hamburger-menu" id="hamburgerMenu"><i class="fas fa-bars"></i></button>
</header>

<!-- Off-Canvas Menu -->
<div class="off-canvas-menu" id="offCanvasMenu">
    <button class="close-btn" id="closeMenuBtn">×</button>
    <h2><?= htmlspecialchars($_SESSION['user_name']) ?></h2>
    <p>Role: System Administrator</p>
    <div class="menu-section-title">Management</div>
    <button class="menu-item" onclick="openModal('universityModal')"><i class="fas fa-university"></i> Add University</button>
    <button class="menu-item" onclick="openModal('departmentModal')"><i class="fas fa-building"></i> Add Department</button>
    <button class="menu-item" onclick="openModal('courseModal')"><i class="fas fa-book"></i> Add Course</button>
    <button class="menu-item" onclick="openModal('unitSingleModal')"><i class="fas fa-cube"></i> Add Single Unit</button>
    <button class="menu-item" onclick="openModal('unitModal')"><i class="fas fa-cubes"></i> Add Multiple Units</button>
    <button class="menu-item" onclick="openModal('lecturerModal')"><i class="fas fa-chalkboard-teacher"></i> Add Lecturer</button>
    <div class="menu-section-title">System</div>
    <button class="menu-item" onclick="alert('System Settings not implemented yet!')"><i class="fas fa-cogs"></i> System Settings</button>
    <a href="../logout.php" class="menu-item logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- Overlay for Off-Canvas Menu -->
<div class="overlay" id="menuOverlay"></div>

<!-- Main Content Area -->
<div class="content">
    <h2>System Overview</h2>

    <!-- Overview Statistics Section -->
    <?php
    $users_count = $conn->query("SELECT COUNT(*) as count FROM (SELECT id FROM students UNION SELECT id FROM lecturers UNION SELECT id FROM admins) as users")->fetch_assoc()['count'];
    $courses_count = $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'];
    $departments_count = $conn->query("SELECT COUNT(*) as count FROM departments")->fetch_assoc()['count'];
    $universities_count = $conn->query("SELECT COUNT(*) as count FROM universities")->fetch_assoc()['count'];
    ?>
    <div class="stat-cards-grid">
        <div class="stat-card users">
            <div class="icon"><i class="fas fa-users"></i></div>
            <div class="number"><?= $users_count ?></div>
            <div class="label">Total Users</div>
        </div>
        <div class="stat-card courses">
            <div class="icon"><i class="fas fa-book"></i></div>
            <div class="number"><?= $courses_count ?></div>
            <div class="label">Active Courses</div>
        </div>
        <div class="stat-card departments">
            <div class="icon"><i class="fas fa-building"></i></div>
            <div class="number"><?= $departments_count ?></div>
            <div class="label">Total Departments</div>
        </div>
        <div class="stat-card universities">
            <div class="icon"><i class="fas fa-university"></i></div>
            <div class="number"><?= $universities_count ?></div>
            <div class="label">Total Universities</div>
            <div class="university-select">
                <select id="universityFilter" onchange="filterByUniversity(this.value)">
                    <option value="">Select University</option>
                    <?php
                    $universities = $conn->query("SELECT id, name FROM universities ORDER BY name ASC");
                    while ($uni = $universities->fetch_assoc()) {
                        echo "<option value='" . $uni['id'] . "'>" . htmlspecialchars($uni['name']) . "</option>";
                    }
                    ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Data Visualization Section (Placeholders) -->
    <div class="charts-grid">
        <div class="chart-container">
            <h3>User Registration Trends</h3>
            <div class="chart-placeholder">Line Chart Placeholder (e.g., last 12 months)</div>
        </div>
        <div class="chart-container">
            <h3>Content Upload Activity</h3>
            <div class="chart-placeholder">Bar Chart Placeholder (Notes, Assignments, Submissions)</div>
        </div>
    </div>

    <!-- Course Units Section -->
    <div class="recent-activity-section">
        <h3>Course Units Management</h3>
        <div class="course-units-header">
            <div class="select-container">
                <select name="course_id" id="courseSelect" required>
                    <option value="">-- Select a Course --</option>
                    <?php
                    $courses_query = $conn->query("
                        SELECT c.id, c.name AS course_name, d.name AS department_name, COUNT(u.id) AS unit_count
                        FROM courses c
                        LEFT JOIN units u ON c.id = u.course_id
                        JOIN departments d ON c.department_id = d.id
                        GROUP BY c.id, c.name, d.name
                        ORDER BY d.name, c.name
                    ");
                    while ($course = $courses_query->fetch_assoc()) {
                        echo "<option value='{$course['id']}'>" . 
                             htmlspecialchars($course['department_name']) . " - " . 
                             htmlspecialchars($course['course_name']) . 
                             " (" . $course['unit_count'] . " units)</option>";
                    }
                    ?>
                </select>
                <div class="button-group">
                    <button type="button" onclick="viewCourseUnits()" class="btn btn-primary">
                        <i class="fas fa-eye"></i> View Units
                    </button>
                    <button type="button" onclick="exportUnitsPDF()" class="btn btn-success">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Units Display -->
    <div id="floatingUnitsDisplay" class="floating-display">
        <div class="floating-header">
            <h3>Course Units</h3>
            <button class="close-btn" onclick="closeFloatingDisplay()">×</button>
        </div>
        <div class="units-grid" id="unitsGrid">
            <!-- Units will be dynamically inserted here -->
        </div>
    </div>

    <!-- Delete Unit Confirmation Modal -->
    <div id="deleteUnitModal" class="modal delete-modal">
        <div class="modal-content">
            <h3>Delete Unit?</h3>
            <p></p>
            <input type="hidden" id="deleteUnitId">
            <div class="modal-actions">
                <button onclick="closeModal('deleteUnitModal')" class="btn btn-secondary">Cancel</button>
                <button onclick="confirmDeleteUnit()" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>

    <!-- Quick Admin Action Cards Section -->
    <div class="action-grid">
        <div class="action-card" onclick="openModal('universityModal')">
            <div class="icon"><i class="fas fa-university"></i></div>
            <h3>Add University</h3>
            <p>Create a new university in the system.</p>
        </div>
        <div class="action-card" onclick="openModal('departmentModal')">
            <div class="icon"><i class="fas fa-building"></i></div>
            <h3>Add Department</h3>
            <p>Add a new department to a university.</p>
        </div>
        <div class="action-card" onclick="openModal('courseModal')">
            <div class="icon"><i class="fas fa-book"></i></div>
            <h3>Add Course</h3>
            <p>Register a new academic course.</p>
        </div>
        <div class="action-card" onclick="openModal('unitSingleModal')">
            <div class="icon"><i class="fas fa-cube"></i></div>
            <h3>Add Single Unit</h3>
            <p>Add a single unit to a course.</p>
        </div>
        <div class="action-card" onclick="openModal('unitModal')">
            <div class="icon"><i class="fas fa-cubes"></i></div>
            <h3>Add Multiple Units</h3>
            <p>Add multiple units to a course.</p>
        </div>
        <div class="action-card" onclick="openModal('lecturerModal')">
            <div class="icon"><i class="fas fa-chalkboard-teacher"></i></div>
            <h3>Add Lecturer</h3>
            <p>Register a new lecturer in the system.</p>
        </div>
    </div>

    <!-- UNIVERSITY MODAL -->
    <div id="universityModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('universityModal')">×</span>
            <h3>Add University</h3>
            <?php if (!empty($university_success)) echo "<p class='success'>$university_success</p>"; ?>
            <?php if (!empty($university_error)) echo "<p class='error'>$university_error</p>"; ?>
            <form method="POST" action="../actions.php">
                <input type="hidden" name="action" value="add_university">
                <label>University Name:</label>
                <input type="text" name="university_name" required>
                <button type="submit">Save</button>
            </form>
        </div>
    </div>


    <!-- Floating Message Div -->
    <div id="floatingMessage" class="floating-message" style="display: none;"></div>


    

    <!-- DEPARTMENT MODAL -->
    <div id="departmentModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('departmentModal')">×</span>
            <h3>Add Department</h3>
            <form id="departmentForm" onsubmit="submitDepartmentForm(event)">
                <input type="hidden" name="action" value="add_department">
                <div class="form-group">
                    <label>Department Name:</label>
                    <input type="text" name="department_name" placeholder="Enter department name" required>
                </div>
                <div class="form-group">
                    <label>Select University:</label>
                    <select name="university_id" required>
                        <option value="">-- Select University --</option>
                        <?php
                        $res = $conn->query("SELECT id, name FROM universities ORDER BY name ASC");
                        while ($row = $res->fetch_assoc()) {
                            echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('departmentModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Add Department</button>
                </div>
            </form>
        </div>
    </div>

    <style>
    .floating-message {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        border-radius: 5px;
        z-index: 9999;
        display: none;
        animation: slideIn 0.5s ease-out;
    }

    .floating-message.success {
        background-color: #28a745;
        color: white;
    }

    .floating-message.error {
        background-color: #dc3545;
        color: white;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    </style>

    

    <!-- COURSE MODAL -->
    <div id="courseModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('courseModal')">×</span>
            <h3>Add Course</h3>
            <?php if (!empty($course_success)) echo "<p class='success'>$course_success</p>"; ?>
            <?php if (!empty($course_error)) echo "<p class='error'>$course_error</p>"; ?>
            <form method="POST" action="../actions.php">
                <input type="hidden" name="action" value="add_course">
                <label>Course Name:</label>
                <input type="text" name="course_name" required>
                <label>Department:</label>
                <select name="department_id" required>
                    <option value="">-- Select Department --</option>
                    <?php
                    $departments = $conn->query("SELECT * FROM departments");
                    while ($d = $departments->fetch_assoc()) {
                        echo "<option value='{$d['id']}'>" . htmlspecialchars($d['name']) . "</option>";
                    }
                    ?>
                </select>
                <button type="submit">Save</button>
            </form>
        </div>
    </div>

    <!-- UNIT SINGLE MODAL -->
    <div id="unitSingleModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('unitSingleModal')">×</span>
            <h3>Add Single Unit</h3>
            <?php if (!empty($unit_success)) echo "<p class='success'>$unit_success</p>"; ?>
            <?php if (!empty($unit_error)) echo "<p class='error'>$unit_error</p>"; ?>
<form method="POST" action="../actions.php">

                <input type="hidden" name="action" value="add_unit">
                <div class="unit-selection">
                    <label>Course:</label>
                    <select name="course_id" required>
                        <option value="">-- Select Course --</option>
                        <?php
                        $courses = $conn->query("SELECT * FROM courses");
                        while ($c = $courses->fetch_assoc()) {
                            echo "<option value='{$c['id']}'>" . htmlspecialchars($c['name']) . "</option>";
                        }
                        ?>
                    </select>
                   <label>Year:</label>
<select name="year" required>
  <option value="">-- Select Year --</option>
  <option value="1">First Year</option>
  <option value="2">Second Year</option>
  <option value="3">Third Year</option>
  <option value="4">Fourth Year</option>
</select>

<label>Semester:</label>
<select name="semester" required>
  <option value="">-- Select Semester --</option>
  <option value="1">Semester 1</option>
  <option value="2">Semester 2</option>
</select>

                </div>
                <label>Unit Name:</label>
                <input type="text" name="unit_name" required>
                <label>Unit Code:</label>
                <input type="text" name="unit_code" required>
                <button type="submit">Save Unit</button>
            </form>
        </div>
    </div>

    <!-- UNIT MODAL (Multiple Units) -->
    <div id="unitModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('unitModal')">×</span>
            <h3>Add Units (Max 8)</h3>
            <?php if (!empty($unit_success)) echo "<p class='success'>$unit_success</p>"; ?>
            <?php if (!empty($unit_error)) echo "<p class='error'>$unit_error</p>"; ?>
            <form method="POST" action="../actions.php">
                <input type="hidden" name="action" value="add_multiple_units">
                <div class="unit-selection">
                    <label>Course:</label>
                    <select name="course_id" required>
                        <option value="">-- Select Course --</option>
                        <?php
                        $courses = $conn->query("SELECT * FROM courses");
                        while ($c = $courses->fetch_assoc()) {
                            echo "<option value='{$c['id']}'>" . htmlspecialchars($c['name']) . "</option>";
                        }
                        ?>
                    </select>
                     <label>Year:</label>
<select name="year" required>
  <option value="">-- Select Year --</option>
  <option value="1">First Year</option>
  <option value="2">Second Year</option>
  <option value="3">Third Year</option>
  <option value="4">Fourth Year</option>
</select>

<label>Semester:</label>
<select name="semester" required>
  <option value="">-- Select Semester --</option>
  <option value="1">Semester 1</option>
  <option value="2">Semester 2</option>
</select>
                </div>
                <hr>
                <div id="unitContainer">
                    <div class="unit-box">
                        <h4>Unit 1</h4>
                        <div class="unit-inputs">
                            <label>Unit Name:</label>
                            <input type="text" name="unit_name[]" required>
                            <label>Unit Code:</label>
                            <input type="text" name="unit_code[]" required>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="addUnit()">+ Add Another Unit</button>
                <button type="submit">Save All Units</button>
            </form>
        </div>
    </div>

    <!-- LECTURER MODAL -->
    <div id="lecturerModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('lecturerModal')">×</span>
            <h3>Add Lecturer</h3>
            <?php if (!empty($lecturer_success)) echo "<p class='success'>$lecturer_success</p>"; ?>
            <?php if (!empty($lecturer_error)) echo "<p class='error'>$lecturer_error</p>"; ?>
            <form method="POST" action="../actions.php">
                <input type="hidden" name="action" value="add_lecturer">
                <label>Name:</label>
                <input type="text" name="lecturer_name" required>
                <label>Email:</label>
                <input type="email" name="lecturer_email" required>
                <label>Password:</label>
                <input type="password" name="lecturer_password" required>
                <label>University:</label>
                <select name="university_id" required>
                    <option value="">-- Select University --</option>
                    <?php
                    $universities = $conn->query("SELECT * FROM universities");
                    while ($u = $universities->fetch_assoc()) {
                        echo "<option value='{$u['id']}'>" . htmlspecialchars($u['name']) . "</option>";
                    }
                    ?>
                </select>
                <button type="submit">Save</button>
            </form>
        </div>
    </div>
</div>



<script>
// ================== Utility: Floating Message ==================
window.showFloatingMessage = function(message, type = 'success') {
    const messageDiv = document.getElementById('floatingMessage');
    if (messageDiv) {
        messageDiv.textContent = message;
        messageDiv.className = `floating-message ${type}`;
        messageDiv.style.display = 'block';
        setTimeout(() => { messageDiv.style.display = 'none'; }, 3000);
    } else {
        alert(`${type.toUpperCase()}: ${message}`);
    }
};

// ================== Modal Handlers ==================
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'block';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
}

// ================== Off-Canvas Menu ==================
const hamburgerBtn = document.getElementById('hamburgerMenu');
const closeMenuBtn = document.getElementById('closeMenuBtn');
const offCanvasMenu = document.getElementById('offCanvasMenu');
const menuOverlay = document.getElementById('menuOverlay');

function toggleOffCanvasMenu() {
    if (offCanvasMenu) offCanvasMenu.classList.toggle('active');
    if (menuOverlay) menuOverlay.classList.toggle('active');
}

if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleOffCanvasMenu);
if (closeMenuBtn) closeMenuBtn.addEventListener('click', toggleOffCanvasMenu);
if (menuOverlay) menuOverlay.addEventListener('click', toggleOffCanvasMenu);

const menuItems = document.querySelectorAll('.off-canvas-menu .menu-item');
menuItems.forEach(item => {
    item.addEventListener('click', () => setTimeout(toggleOffCanvasMenu, 150));
});

// ================== Close modals on outside click ==================
window.onclick = function(event) {
    const modals = document.getElementsByClassName('modal');
    for (let i = 0; i < modals.length; i++) {
        if (event.target === modals[i]) {
            modals[i].style.display = 'none';
        }
    }
};

// ================== Add Unit Logic ==================
let unitCount = 1;
function addUnit() {
    if (unitCount >= 8) {
        showFloatingMessage('Maximum of 8 units allowed.', 'error');
        return;
    }
    unitCount++;
    const container = document.getElementById('unitContainer');
    if (container) {
        const box = document.createElement('div');
        box.className = 'unit-box';
        box.innerHTML = `
            <h4>Unit ${unitCount}</h4>
            <div class="unit-inputs">
                <label>Unit Name:</label>
                <input type="text" name="unit_name[]" required>
                <label>Unit Code:</label>
                <input type="text" name="unit_code[]" required>
            </div>
        `;
        container.appendChild(box);
    }
};

// ================== Populate Courses ==================
function filterByUniversity(universityId) {
    if (!universityId) return;
    fetch(`../actions.php?action=get_university_data&university_id=${universityId}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            const selects = document.querySelectorAll('select[name="course_id"]');
            selects.forEach(select => {
                select.innerHTML = '<option value="">-- Select a Course --</option>';
                if (Array.isArray(data.courses)) {
                    data.courses.forEach(course => {
                        const opt = document.createElement('option');
                        opt.value = course.id;
                        opt.textContent = `${course.name} (${course.unit_count} units)`;
                        select.appendChild(opt);
                    });
                }
            });
        })
        .catch(err => {
            console.error('Error:', err);
            showFloatingMessage('Error loading courses', 'error');
        });
};

// ================== Delete Unit ==================
window.openDeleteUnitModal = function(unitId, unitName) {
    const hidden = document.getElementById('deleteUnitId');
    const p = document.querySelector('#deleteUnitModal .modal-content p');
    if (hidden) hidden.value = unitId;
    if (p) p.textContent = `Delete unit "${unitName}"?`;
    openModal('deleteUnitModal');
};

window.confirmDeleteUnit = function() {
    const unitId = document.getElementById('deleteUnitId').value;
    if (!unitId) {
        showFloatingMessage('Invalid unit ID', 'error');
        return;
    }

    fetch('../actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete_unit&unit_id=${encodeURIComponent(unitId)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showFloatingMessage(data.message || 'Unit deleted successfully', 'success');
            closeModal('deleteUnitModal');
            viewCourseUnits(); // refresh list
        } else {
            showFloatingMessage(data.message || 'Error deleting unit', 'error');
        }
    })
    .catch(err => {
        console.error('Delete error:', err);
        showFloatingMessage('Error deleting unit', 'error');
    });
};

// ================== Export Units PDF ==================
window.exportUnitsPDF = function() {
    const courseSelect = document.getElementById('courseSelect');
    const courseId = courseSelect ? courseSelect.value : '';
    if (!courseId) {
        showFloatingMessage('Please select a course first', 'error');
        return;
    }
    window.open(`../actions.php?action=generate_unit_pdf&course_id=${courseId}`, '_blank');
};

// ================== View Course Units ==================
window.viewCourseUnits = function() {
    const courseSelect = document.getElementById('courseSelect');
    const courseId = courseSelect ? courseSelect.value : '';
    if (!courseId) {
        showFloatingMessage('Please select a course first', 'error');
        return;
    }

    const floatingDisplay = document.getElementById('floatingUnitsDisplay');
    const unitsGrid = document.getElementById('unitsGrid');
    if (floatingDisplay) floatingDisplay.classList.add('active');
    if (unitsGrid) unitsGrid.innerHTML = '<div class="loading">Loading units...</div>';

    fetch(`../actions.php?action=get_course_units&course_id=${courseId}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            const header = document.querySelector('#floatingUnitsDisplay .floating-header h3');
            
            // Show course + department gracefully
            if (header) {
                let courseName = "";
                let deptName = "";
                if (typeof data.course === "object" && data.course !== null) {
                    courseName = data.course.course_name || "";
                    deptName = data.course.department_name || "";
                } else if (typeof data.course === "string") {
                    courseName = data.course;
                }
                header.textContent = deptName ? `${deptName} - ${courseName}` : courseName;
            }

            if (unitsGrid) unitsGrid.innerHTML = '';
            if (data.status !== 'success' || !data.units || data.units.length === 0) {
                if (unitsGrid) unitsGrid.innerHTML = '<div class="empty-message">No units found for this course.</div>';
                return;
            }

            const sortedUnits = data.units.slice().sort((a, b) => {
                const yearA = parseInt(a.year, 10);
                const yearB = parseInt(b.year, 10);
                if (yearA !== yearB) return yearA - yearB;
                return parseInt(a.semester, 10) - parseInt(b.semester, 10);
            });

            const frag = document.createDocumentFragment();
            sortedUnits.forEach(unit => {
                const card = document.createElement('div');
                card.className = 'unit-card';
                card.innerHTML = `
                    <div class="unit-info">
                        <h4>${unit.unit_name}</h4>
                        <span class="unit-code">${unit.unit_code}</span>
                        <div class="unit-meta">Year ${unit.year} | Semester ${unit.semester}</div>
                    </div>
                `;
                const delBtn = document.createElement('button');
                delBtn.className = 'delete-btn';
                delBtn.innerHTML = '<i class="fas fa-trash"></i>';
                delBtn.onclick = () => openDeleteUnitModal(unit.id, unit.unit_name);
                card.appendChild(delBtn);
                frag.appendChild(card);
            });
            if (unitsGrid) unitsGrid.appendChild(frag);
        })
        .catch(err => {
            console.error('Fetch error:', err);
            if (unitsGrid) unitsGrid.innerHTML = '<div class="error-message">Error loading units</div>';
            showFloatingMessage(err.message || 'Error loading units', 'error');
        });
};

// ================== Close Floating Display ==================
window.closeFloatingDisplay = function() {
    const floatingDisplay = document.getElementById('floatingUnitsDisplay');
    if (floatingDisplay) floatingDisplay.classList.remove('active');
};

// ================== Submit Department Form ==================
function submitDepartmentForm(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    fetch('../actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showFloatingMessage(data.message, 'success');
            closeModal('departmentModal');
            setTimeout(() => location.reload(), 2000);
        } else {
            showFloatingMessage(data.message, 'error');
        }
    })
    .catch(() => showFloatingMessage('An error occurred while submitting the form', 'error'));
};
</script>

</body>
</html>