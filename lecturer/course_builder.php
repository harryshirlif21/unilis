<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['lecturer', 'admin', 'department_admin'], true)) {
    header("Location: ../login.php");
    exit;
}

$lecturer_id   = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'] ?? 'Lecturer';

// ── Detect mode: unit_id (ICLM) or course_id (short course) ──────────────
$unit_id   = intval($_GET['unit_id']   ?? 0);
$course_id = intval($_GET['course_id'] ?? 0);
$mode      = $course_id > 0 ? 'short_course' : 'iclm';

// If a department admin or admin is accessing without course_id, default to short course catalogue
if ($mode === 'iclm' && in_array($_SESSION['user_role'], ['department_admin'], true)) {
    header("Location: ../phase1/admin/department_admins.php");
    exit;
}

// ── Short course mode: verify access, load course info ────────────────────
$course_info = null;
if ($mode === 'short_course') {
    $is_admin = in_array($_SESSION['user_role'], ['admin', 'department_admin'], true);

    if ($is_admin) {
        // A global admin may access every course. A department admin is limited
        // to the department assigned in their session.
        $sql = $_SESSION['user_role'] === 'department_admin'
            ? "SELECT * FROM public_courses WHERE id = ? AND department_id = ? LIMIT 1"
            : "SELECT * FROM public_courses WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if ($_SESSION['user_role'] === 'department_admin') {
            $departmentId = (int)($_SESSION['department_id'] ?? 0);
            $stmt->bind_param("ii", $course_id, $departmentId);
        } else {
            $stmt->bind_param("i", $course_id);
        }
        $stmt->execute();
        $course_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        // Lecturers: check assigned tutor or owner
        $stmt = $conn->prepare("
            SELECT pc.*, sct.id AS tutor_id
            FROM public_courses pc
            JOIN short_course_tutors sct ON sct.short_course_id = pc.id
            WHERE pc.id = ? AND sct.lecturer_id = ? AND sct.is_active = 1
            LIMIT 1
        ");
        $stmt->bind_param("ii", $course_id, $lecturer_id);
        $stmt->execute();
        $course_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$course_info) {
            // Fallback: check if lecturer owns the course
            $stmt = $conn->prepare("SELECT * FROM public_courses WHERE id = ? AND created_by_lecturer_id = ? LIMIT 1");
            $stmt->bind_param("ii", $course_id, $lecturer_id);
            $stmt->execute();
            $course_info = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }

    if (!$course_info) {
        header("Location: catalogue.php");
        exit;
    }
}

// ── ICLM mode: fetch units assigned to this lecturer ─────────────────────
$units = [];
if ($mode === 'iclm') {
    try {
        $stmt = $conn->prepare("
            SELECT u.id, u.name
            FROM units u
            JOIN lecturer_units lu ON u.id = lu.unit_id
            WHERE lu.lecturer_id = ?
            ORDER BY u.name ASC
        ");
        $stmt->bind_param("i", $lecturer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $units[] = $row;
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("course_builder unit fetch: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $mode === 'short_course' ? 'Short Course Builder' : 'Course Builder' ?> — UNILIS</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ── DESIGN SYSTEM ─────────────────────────────────────── */
:root {
    --bg:          #0d0f14;
    --surface:     #161921;
    --surface2:    #1e2230;
    --surface3:    #262c3d;
    --border:      #2a3148;
    --accent:      #4f8ef7;
    --accent2:     #38d9a9;
    --accent3:     #f7934f;
    --danger:      #f75f5f;
    --text:        #e8eaf0;
    --text-muted:  #7a82a0;
    --text-dim:    #4a5270;
    --module-bar:  #1a2035;
    --lesson-row:  #161921;
    --shadow:      0 4px 24px rgba(0,0,0,0.4);
    --radius:      10px;
    --radius-sm:   6px;
    --transition:  0.18s ease;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    overflow-x: hidden;
}

/* ── TOPBAR ─────────────────────────────────────────────── */
.topbar {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 0 32px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
}
.topbar-brand {
    font-family: 'Syne', sans-serif;
    font-weight: 800;
    font-size: 1.1rem;
    letter-spacing: 0.04em;
    color: var(--accent);
}
.topbar-brand span { color: var(--text-muted); font-weight: 400; margin-left: 8px; font-size: 0.85rem; }
.topbar-right { display: flex; align-items: center; gap: 16px; }
.topbar-user { font-size: 0.82rem; color: var(--text-muted); }
.btn-nav {
    background: var(--surface3);
    border: 1px solid var(--border);
    color: var(--text-muted);
    padding: 6px 14px;
    border-radius: var(--radius-sm);
    font-size: 0.8rem;
    cursor: pointer;
    text-decoration: none;
    transition: var(--transition);
    font-family: 'DM Sans', sans-serif;
}
.btn-nav:hover { background: var(--surface2); color: var(--text); }

/* ── LAYOUT ─────────────────────────────────────────────── */
.layout { display: flex; height: calc(100vh - 60px); }

/* ── SIDEBAR ────────────────────────────────────────────── */
.sidebar {
    width: 300px;
    min-width: 300px;
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    padding: 24px 20px;
    gap: 20px;
    overflow-y: auto;
}
.sidebar-section label {
    font-family: 'Syne', sans-serif;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--text-dim);
    display: block;
    margin-bottom: 8px;
}
.unit-select-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}
.unit-select-header label { margin-bottom: 0; }

.styled-select {
    width: 100%;
    background: var(--surface2);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 10px 14px;
    border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem;
    cursor: pointer;
    outline: none;
    transition: var(--transition);
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%237a82a0' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 32px;
}
.styled-select:focus { border-color: var(--accent); }

.sidebar-stats {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.stat-item { text-align: center; }
.stat-num {
    font-family: 'Syne', sans-serif;
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--accent);
    line-height: 1;
}
.stat-label {
    font-size: 0.72rem;
    color: var(--text-dim);
    margin-top: 4px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.sidebar-actions { display: flex; flex-direction: column; gap: 8px; }

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: var(--transition);
    text-decoration: none;
    justify-content: center;
}
.btn-primary   { background: var(--accent);  color: #fff; }
.btn-primary:hover { background: #3a7ce8; transform: translateY(-1px); }
.btn-success   { background: var(--accent2); color: #0d1a15; }
.btn-success:hover { background: #2ec99a; transform: translateY(-1px); }
.btn-warning   { background: var(--accent3); color: #1a0f00; }
.btn-warning:hover { background: #f08040; }
.btn-danger    { background: var(--danger);  color: #fff; }
.btn-danger:hover  { background: #e04040; }
.btn-ghost {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-muted);
}
.btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
.btn-sm { padding: 5px 10px; font-size: 0.78rem; }
.btn-icon { padding: 6px 8px; min-width: 32px; }
.btn:disabled { opacity: 0.45; cursor: not-allowed; transform: none !important; }

/* ── MAIN CONTENT ───────────────────────────────────────── */
.main {
    flex: 1;
    overflow-y: auto;
    padding: 28px 32px;
    display: flex;
    flex-direction: column;
    gap: 24px;
}

/* ── COURSE HEADER CARD ─────────────────────────────────── */
.course-header-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 24px 28px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    animation: fadeSlideIn 0.3s ease;
}
.course-header-info h2 {
    font-family: 'Syne', sans-serif;
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 6px;
}
.course-header-info p {
    font-size: 0.85rem;
    color: var(--text-muted);
    max-width: 500px;
    line-height: 1.5;
}
.outline-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 500;
    margin-top: 10px;
}
.outline-set   { background: rgba(56,217,169,0.15); color: var(--accent2); border: 1px solid rgba(56,217,169,0.3); }
.outline-unset { background: rgba(247,95,95,0.12);  color: var(--danger);  border: 1px solid rgba(247,95,95,0.3); }

/* ── MODULE TREE ────────────────────────────────────────── */
.tree-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.tree-toolbar h3 {
    font-family: 'Syne', sans-serif;
    font-size: 0.9rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--text-muted);
}

#module-tree { display: flex; flex-direction: column; gap: 12px; }

/* ── MODULE CARD ────────────────────────────────────────── */
.module-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    transition: box-shadow var(--transition);
    animation: fadeSlideIn 0.25s ease;
}
.module-card.drag-over { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(79,142,247,0.25); }
.module-card.dragging  { opacity: 0.4; }

.module-header {
    background: var(--module-bar);
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: grab;
    user-select: none;
    border-bottom: 1px solid var(--border);
}
.module-header:active { cursor: grabbing; }
.drag-handle {
    color: var(--text-dim);
    font-size: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding: 2px 4px;
}
.drag-handle span {
    display: block;
    width: 18px;
    height: 2px;
    background: var(--text-dim);
    border-radius: 2px;
    transition: background var(--transition);
}
.module-header:hover .drag-handle span { background: var(--accent); }

.module-number {
    font-family: 'Syne', sans-serif;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    color: var(--accent);
    background: rgba(79,142,247,0.12);
    border: 1px solid rgba(79,142,247,0.25);
    padding: 3px 8px;
    border-radius: 999px;
    min-width: 28px;
    text-align: center;
}
.module-title-wrap { flex: 1; display: flex; align-items: center; gap: 10px; }
.module-title {
    font-family: 'Syne', sans-serif;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text);
    cursor: pointer;
}
.module-title-input {
    background: var(--surface3);
    border: 1px solid var(--accent);
    color: var(--text);
    padding: 5px 10px;
    border-radius: var(--radius-sm);
    font-family: 'Syne', sans-serif;
    font-size: 0.9rem;
    font-weight: 600;
    flex: 1;
    outline: none;
}
.module-actions { display: flex; align-items: center; gap: 6px; }
.module-toggle {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 4px 8px;
    border-radius: var(--radius-sm);
    transition: var(--transition);
    font-size: 0.85rem;
}
.module-toggle:hover { color: var(--text); background: var(--surface3); }

/* ── LESSON LIST ────────────────────────────────────────── */
.lessons-container {
    padding: 12px 16px 16px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.lessons-list { display: flex; flex-direction: column; gap: 6px; min-height: 4px; }

.lesson-row {
    background: var(--lesson-row);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 10px 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: grab;
    transition: border-color var(--transition), background var(--transition);
    animation: fadeSlideIn 0.2s ease;
}
.lesson-row:active { cursor: grabbing; }
.lesson-row:hover  { border-color: var(--surface3); background: var(--surface2); }
.lesson-row.drag-over { border-color: var(--accent2); background: rgba(56,217,169,0.05); }
.lesson-row.dragging  { opacity: 0.35; }

.lesson-num {
    font-family: 'Syne', sans-serif;
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--accent2);
    background: rgba(56,217,169,0.1);
    border: 1px solid rgba(56,217,169,0.2);
    padding: 2px 7px;
    border-radius: 999px;
    min-width: 36px;
    text-align: center;
    white-space: nowrap;
}
.lesson-drag { color: var(--text-dim); font-size: 0.75rem; }
.lesson-title {
    flex: 1;
    font-size: 0.87rem;
    color: var(--text);
    cursor: pointer;
}
.lesson-title:hover { color: var(--accent); }
.lesson-title-input {
    flex: 1;
    background: var(--surface3);
    border: 1px solid var(--accent2);
    color: var(--text);
    padding: 4px 9px;
    border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.85rem;
    outline: none;
}
.lesson-actions { display: flex; gap: 4px; }

.add-lesson-row {
    border: 1px dashed var(--border);
    border-radius: var(--radius-sm);
    padding: 8px 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    color: var(--text-dim);
    font-size: 0.82rem;
    transition: var(--transition);
    margin-top: 4px;
}
.add-lesson-row:hover { border-color: var(--accent2); color: var(--accent2); background: rgba(56,217,169,0.04); }

/* ── EMPTY STATE ────────────────────────────────────────── */
.empty-state {
    text-align: center;
    padding: 60px 40px;
    color: var(--text-dim);
    animation: fadeSlideIn 0.3s ease;
}
.empty-state i { font-size: 2.5rem; margin-bottom: 16px; opacity: 0.4; }
.empty-state h3 {
    font-family: 'Syne', sans-serif;
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-muted);
    margin-bottom: 8px;
}
.empty-state p { font-size: 0.85rem; max-width: 320px; margin: 0 auto; }

/* ── PLACEHOLDER ─────────────────────────────────────────── */
#unit-placeholder {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}
.placeholder-inner { text-align: center; }
.placeholder-inner i { font-size: 3rem; color: var(--text-dim); margin-bottom: 20px; }
.placeholder-inner h2 {
    font-family: 'Syne', sans-serif;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-muted);
    margin-bottom: 8px;
}
.placeholder-inner p { font-size: 0.85rem; color: var(--text-dim); }

/* ── SHORT COURSE INFO ──────────────────────────────────── */
.course-info-card {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.course-info-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 0;
    border-bottom: 1px solid var(--border);
}
.course-info-row:last-child { border-bottom: none; }
.course-info-label {
    font-size: 0.78rem;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.course-info-value {
    font-size: 0.85rem;
    color: var(--text);
    font-weight: 500;
}

/* ── MODAL ───────────────────────────────────────────────── */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(4px);
    z-index: 200;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease;
}
.modal-overlay.open { opacity: 1; pointer-events: all; }
.modal {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 28px 32px;
    width: 480px;
    max-width: 92vw;
    box-shadow: var(--shadow);
    transform: translateY(12px);
    transition: transform 0.2s ease;
}
.modal-overlay.open .modal { transform: translateY(0); }
.modal h3 {
    font-family: 'Syne', sans-serif;
    font-size: 1.05rem;
    font-weight: 700;
    margin-bottom: 20px;
    color: var(--text);
}
.form-group { margin-bottom: 16px; }
.form-group label {
    display: block;
    font-size: 0.78rem;
    font-weight: 500;
    color: var(--text-muted);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.form-input, .form-textarea {
    width: 100%;
    background: var(--surface2);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 10px 14px;
    border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem;
    outline: none;
    transition: border-color var(--transition);
}
.form-input:focus, .form-textarea:focus { border-color: var(--accent); }
.form-textarea { resize: vertical; min-height: 90px; }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }

/* ── IMPORT UNIT MODAL SPECIFICS ─────────────────────────── */
.import-unit-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 320px;
    overflow-y: auto;
    margin-bottom: 4px;
}
.import-unit-card {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 13px 16px;
    display: flex;
    align-items: center;
    gap: 14px;
    cursor: pointer;
    transition: var(--transition);
    user-select: none;
}
.import-unit-card:hover {
    border-color: var(--accent);
    background: var(--surface3);
}
.import-unit-card.selected {
    border-color: var(--accent2);
    background: rgba(56,217,169,0.06);
}
.import-unit-icon {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-sm);
    background: rgba(79,142,247,0.12);
    border: 1px solid rgba(79,142,247,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent);
    font-size: 0.9rem;
    flex-shrink: 0;
}
.import-unit-card.selected .import-unit-icon {
    background: rgba(56,217,169,0.12);
    border-color: rgba(56,217,169,0.3);
    color: var(--accent2);
}
.import-unit-name {
    flex: 1;
    font-size: 0.88rem;
    font-weight: 500;
    color: var(--text);
}
.import-unit-check {
    color: var(--accent2);
    font-size: 0.85rem;
    opacity: 0;
    transition: opacity var(--transition);
}
.import-unit-card.selected .import-unit-check { opacity: 1; }

.import-notice {
    background: rgba(79,142,247,0.06);
    border: 1px solid rgba(79,142,247,0.18);
    border-radius: var(--radius-sm);
    padding: 10px 14px;
    font-size: 0.78rem;
    color: var(--text-muted);
    margin-bottom: 4px;
    line-height: 1.5;
}
.import-notice i { color: var(--accent); margin-right: 5px; }

.empty-import {
    text-align: center;
    padding: 32px 20px;
    color: var(--text-dim);
    font-size: 0.85rem;
}
.empty-import i { display: block; font-size: 1.8rem; margin-bottom: 10px; opacity: 0.35; }

/* ── TOAST ───────────────────────────────────────────────── */
#toast {
    position: fixed;
    bottom: 28px;
    right: 28px;
    z-index: 999;
    display: flex;
    flex-direction: column;
    gap: 8px;
    pointer-events: none;
}
.toast-item {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 12px 18px;
    font-size: 0.85rem;
    color: var(--text);
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    gap: 10px;
    animation: toastIn 0.25s ease, toastOut 0.25s ease 2.5s forwards;
    max-width: 320px;
}
.toast-item.success { border-left: 3px solid var(--accent2); }
.toast-item.error   { border-left: 3px solid var(--danger); }
.toast-item.info    { border-left: 3px solid var(--accent); }

/* ── LOADING ─────────────────────────────────────────────── */
.spinner {
    width: 16px; height: 16px;
    border: 2px solid var(--border);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
    display: inline-block;
}

/* ── ANIMATIONS ──────────────────────────────────────────── */
@keyframes fadeSlideIn {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes toastIn {
    from { opacity: 0; transform: translateX(20px); }
    to   { opacity: 1; transform: translateX(0); }
}
@keyframes toastOut {
    from { opacity: 1; }
    to   { opacity: 0; transform: translateX(20px); }
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── OUTLINE MODAL SPECIFICS ─────────────────────────────── */
.char-count { font-size: 0.72rem; color: var(--text-dim); text-align: right; margin-top: 4px; }

/* ── RESPONSIVE ──────────────────────────────────────────── */
@media (max-width: 768px) {
    .layout { flex-direction: column; }
    .sidebar { width: 100%; min-width: unset; height: auto; }
    .main { padding: 16px; }
    .topbar { padding: 0 16px; }
}
</style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar">
    <div class="topbar-brand">
        UNILIS <span><?= $mode === 'short_course' ? 'Short Course Builder' : 'Course Builder' ?></span>
    </div>
    <div class="topbar-right">
        <span class="topbar-user"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($lecturer_name) ?></span>
        <?php if ($mode === 'short_course'): ?>
            <a href="catalogue.php" class="btn-nav"><i class="fas fa-globe"></i> Short Courses</a>
        <?php else: ?>
            <a href="lesson_editor.php" class="btn-nav"><i class="fas fa-pen-nib"></i> Lesson Editor</a>
            <a href="assessment_builder.php" class="btn-nav"><i class="fas fa-tasks"></i> Assessments</a>
        <?php endif; ?>
        <a href="dashboard.php" class="btn-nav"><i class="fas fa-home"></i> Dashboard</a>
    </div>
</header>

<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <?php if ($mode === 'short_course' && $course_info): ?>
            <!-- Short course info sidebar -->
            <div class="sidebar-section">
                <label><i class="fas fa-graduation-cap"></i> &nbsp;<?= htmlspecialchars($course_info['title']) ?></label>
                <div class="course-info-card">
                    <div class="course-info-row">
                        <span class="course-info-label">Level</span>
                        <span class="course-info-value"><?= htmlspecialchars(ucfirst($course_info['level'])) ?></span>
                    </div>
                    <div class="course-info-row">
                        <span class="course-info-label">Status</span>
                        <span class="course-info-value"><?= (int)$course_info['is_published'] === 1 ? 'Published' : 'Draft' ?></span>
                    </div>
                    <?php if ($course_info['estimated_hours']): ?>
                    <div class="course-info-row">
                        <span class="course-info-label">Est. Hours</span>
                        <span class="course-info-value"><?= (float)$course_info['estimated_hours'] ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="course-info-row">
                        <span class="course-info-label">Pass Mark</span>
                        <span class="course-info-value"><?= (int)$course_info['pass_mark'] ?>%</span>
                    </div>
                    <?php if (!empty($course_info['summary'])): ?>
                    <div style="font-size:0.82rem;color:var(--text-muted);padding-top:6px;border-top:1px solid var(--border)">
                        <?= htmlspecialchars($course_info['summary']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- ICLM mode: unit selector -->
            <div class="sidebar-section">
                <div class="unit-select-header">
                    <label><i class="fas fa-book"></i> &nbsp;Select Unit</label>
                </div>
                <select class="styled-select" id="unit-select">
                    <option value="">— choose a unit —</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="sidebar-stats" id="sidebar-stats" style="display:none">
            <div class="stat-item">
                <div class="stat-num" id="stat-modules">0</div>
                <div class="stat-label">Modules</div>
            </div>
            <div class="stat-item">
                <div class="stat-num" id="stat-lessons">0</div>
                <div class="stat-label">Lessons</div>
            </div>
        </div>

        <div class="sidebar-actions" id="sidebar-actions" style="display:none">
            <button class="btn btn-primary" onclick="openAddModuleModal()">
                <i class="fas fa-plus"></i> Add Module
            </button>
            <button class="btn btn-ghost" onclick="openOutlineModal()">
                <i class="fas fa-align-left"></i> Course Outline
            </button>
            <?php if ($mode === 'iclm' && count($units) > 1): ?>
            <button class="btn btn-ghost" id="btn-import-unit" onclick="openImportUnitModal()" style="border-color:rgba(247,147,79,0.4);color:var(--accent3)">
                <i class="fas fa-file-import"></i> Import From Unit
            </button>
            <?php endif; ?>
            <a id="btn-go-lessons" href="#" class="btn btn-success" style="display:none">
                <i class="fas fa-pen-nib"></i> Edit Lessons
            </a>
        </div>

        <?php if ($mode === 'short_course'): ?>
        <div style="margin-top: auto; padding-top: 16px; border-top: 1px solid var(--border);">
            <a href="catalogue.php" class="btn btn-ghost" style="width:100%;">
                <i class="fas fa-arrow-left"></i> Back to Short Courses
            </a>
        </div>
        <?php endif; ?>

        <div style="margin-top: auto; padding-top: 16px; border-top: 1px solid var(--border);">
            <p style="font-size:0.75rem; color:var(--text-dim); line-height:1.6;">
                <i class="fas fa-grip-lines" style="color:var(--accent)"></i>
                Drag modules and lessons to reorder them. Click titles to rename inline.
            </p>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main" id="main-content">

        <?php if ($mode === 'short_course'): ?>
            <!-- Short course is auto-loaded -->
            <div id="course-content" style="display:flex;flex-direction:column;gap:24px;">
                <div class="course-header-card" id="course-header-card">
                    <div class="course-header-info">
                        <h2 id="course-unit-name"><?= htmlspecialchars($course_info['title']) ?></h2>
                        <p id="course-description"><?= htmlspecialchars($course_info['description'] ?? 'No description set yet.') ?></p>
                        <span class="outline-badge <?= !empty($course_info['description']) ? 'outline-set' : 'outline-unset' ?>" id="outline-status">
                            <i class="fas <?= !empty($course_info['description']) ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                            <?= !empty($course_info['description']) ? 'Description set' : 'Description not set' ?>
                        </span>
                    </div>
                    <div>
                        <button class="btn btn-ghost btn-sm" onclick="openOutlineModal()">
                            <i class="fas fa-edit"></i> Edit Description
                        </button>
                    </div>
                </div>

                <div>
                    <div class="tree-toolbar">
                        <h3><i class="fas fa-sitemap"></i> &nbsp;Course Structure</h3>
                        <button class="btn btn-primary btn-sm" onclick="openAddModuleModal()">
                            <i class="fas fa-plus"></i> Add Module
                        </button>
                    </div>
                </div>

                <div id="module-tree"></div>
            </div>
        <?php else: ?>
            <!-- ICLM mode: placeholder until a unit is selected -->
            <div id="unit-placeholder">
                <div class="placeholder-inner">
                    <i class="fas fa-layer-group"></i>
                    <h2>No Unit Selected</h2>
                    <p>Select a unit from the sidebar to start building your course structure.</p>
                </div>
            </div>

            <div id="course-content" style="display:none">
                <div class="course-header-card" id="course-header-card">
                    <div class="course-header-info">
                        <h2 id="course-unit-name">Unit Name</h2>
                        <p id="course-description">No description set yet.</p>
                        <span class="outline-badge outline-unset" id="outline-status">
                            <i class="fas fa-circle-xmark"></i> Outline not set
                        </span>
                    </div>
                    <div>
                        <button class="btn btn-ghost btn-sm" onclick="openOutlineModal()">
                            <i class="fas fa-edit"></i> Edit Outline
                        </button>
                    </div>
                </div>

                <div>
                    <div class="tree-toolbar">
                        <h3><i class="fas fa-sitemap"></i> &nbsp;Course Structure</h3>
                        <button class="btn btn-primary btn-sm" onclick="openAddModuleModal()">
                            <i class="fas fa-plus"></i> Add Module
                        </button>
                    </div>
                </div>

                <div id="module-tree"></div>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- ── MODALS ─────────────────────────────────────────────── -->

<!-- Add/Edit Module Modal -->
<div class="modal-overlay" id="module-modal">
    <div class="modal">
        <h3 id="module-modal-title"><i class="fas fa-cubes"></i> Add Module</h3>
        <div class="form-group">
            <label>Module Title</label>
            <input type="text" class="form-input" id="module-title-input"
                   placeholder="e.g. Introduction to Networking">
        </div>
        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeModal('module-modal')">Cancel</button>
            <button class="btn btn-primary" id="module-save-btn" onclick="saveModule()">
                <i class="fas fa-save"></i> Save Module
            </button>
        </div>
    </div>
</div>

<!-- Course Outline Modal -->
<div class="modal-overlay" id="outline-modal">
    <div class="modal" style="width:560px">
        <h3><i class="fas fa-align-left"></i> <?= $mode === 'short_course' ? 'Course Description' : 'Course Outline' ?></h3>
        <div class="form-group">
            <label><?= $mode === 'short_course' ? 'Course Description' : 'Course Description' ?></label>
            <textarea class="form-textarea" id="outline-description"
                      placeholder="Brief overview of this course..."
                      oninput="updateCharCount(this,'desc-count',500)"></textarea>
            <div class="char-count"><span id="desc-count">0</span>/500</div>
        </div>
        <?php if ($mode === 'iclm'): ?>
        <div class="form-group">
            <label>Course Outline / Syllabus</label>
            <textarea class="form-textarea" id="outline-content"
                      placeholder="Week 1: Introduction&#10;Week 2: Core Concepts&#10;..."
                      style="min-height:140px"
                      oninput="updateCharCount(this,'outline-count',2000)"></textarea>
            <div class="char-count"><span id="outline-count">0</span>/2000</div>
        </div>
        <?php endif; ?>
        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeModal('outline-modal')">Cancel</button>
            <button class="btn btn-success" onclick="saveOutline()">
                <i class="fas fa-save"></i> Save
            </button>
        </div>
    </div>
</div>

<!-- ── IMPORT FROM UNIT MODAL ─────────────────────────────── -->
<div class="modal-overlay" id="import-unit-modal">
    <div class="modal" style="width:520px">
        <h3><i class="fas fa-file-import" style="color:var(--accent3)"></i> Import From Another Unit</h3>

        <div class="import-notice">
            <i class="fas fa-circle-info"></i>
            Modules and lessons from the selected unit will be added to
            <strong id="import-target-name" style="color:var(--text)">this unit</strong>.
            Modules with the same title are skipped automatically — no duplicates will be created.
        </div>

        <div class="form-group" style="margin-top:14px">
            <label>Choose a source unit</label>
            <div class="import-unit-list" id="import-unit-list">
                <!-- populated by JS -->
            </div>
        </div>

        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeModal('import-unit-modal')">Cancel</button>
            <button class="btn btn-warning" id="import-confirm-btn" onclick="confirmImport()" disabled>
                <i class="fas fa-file-import"></i> Import Modules
            </button>
        </div>
    </div>
</div>

<!-- Toast container -->
<div id="toast"></div>

<!-- ── JAVASCRIPT ─────────────────────────────────────────── -->
<script>
// ─────────────────────────────────────────────────────────────
// STATE
// ─────────────────────────────────────────────────────────────
const MODE        = '<?= $mode ?>';
const COURSE_ID   = <?= $course_id ?: 'null' ?>;
const LECTURER_ID = <?= $lecturer_id ?>;

let selectedUnitId   = MODE === 'short_course' ? null : null;
let selectedUnitName = '';
let modules          = [];
let outline          = null;
let editingModuleId  = null;

// All units from PHP (for import modal)
const ALL_UNITS = <?= json_encode($units) ?>;

// ─────────────────────────────────────────────────────────────
// INIT
// ─────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    if (MODE === 'short_course') {
        loadShortCourseTree();
    } else {
        // ICLM mode: set up unit selector
        const sel = document.getElementById('unit-select');
        if (sel) {
            sel.addEventListener('change', function() {
                selectedUnitId   = this.value || null;
                selectedUnitName = this.options[this.selectedIndex].text;
                if (!selectedUnitId) { showPlaceholder(); return; }
                loadCourseTree();
            });
        }
    }
});

// ─────────────────────────────────────────────────────────────
// SHORT COURSE: LOAD FROM PUBLIC TABLES
// ─────────────────────────────────────────────────────────────
function loadShortCourseTree() {
    fetch(`ajax/short_course_get_tree.php?course_id=${COURSE_ID}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { toast(data.message || 'Failed to load', 'error'); return; }
            modules = data.modules || [];
            outline = data.outline || null;

            document.getElementById('sidebar-stats').style.display   = 'grid';
            document.getElementById('sidebar-actions').style.display = 'flex';

            renderOutlineHeader();
            renderModuleTree();
            updateStats();
        })
        .catch(() => toast('Network error loading course', 'error'));
}

// ─────────────────────────────────────────────────────────────
// ICLM: LOAD COURSE TREE
// ─────────────────────────────────────────────────────────────
function loadCourseTree() {
    fetch(`ajax/get_course_tree.php?unit_id=${selectedUnitId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { toast(data.message || 'Failed to load', 'error'); return; }
            modules = data.modules || [];
            outline = data.outline || null;

            document.getElementById('unit-placeholder').style.display    = 'none';
            document.getElementById('course-content').style.display      = 'flex';
            document.getElementById('course-content').style.flexDirection = 'column';
            document.getElementById('course-content').style.gap          = '24px';
            document.getElementById('sidebar-stats').style.display       = 'grid';
            document.getElementById('sidebar-actions').style.display     = 'flex';

            document.getElementById('course-unit-name').textContent = selectedUnitName;
            renderOutlineHeader();
            renderModuleTree();
            updateStats();
        })
        .catch(() => toast('Network error loading course', 'error'));
}

function showPlaceholder() {
    document.getElementById('unit-placeholder').style.display  = 'flex';
    document.getElementById('course-content').style.display    = 'none';
    document.getElementById('sidebar-stats').style.display     = 'none';
    document.getElementById('sidebar-actions').style.display   = 'none';
}

function renderOutlineHeader() {
    const desc  = document.getElementById('course-description');
    const badge = document.getElementById('outline-status');
    let hasContent = false;
    if (outline && outline.description) {
        desc.textContent = outline.description;
        hasContent = true;
    } else if (outline && outline.outline) {
        desc.textContent = outline.outline;
        hasContent = true;
    } else {
        desc.textContent = MODE === 'short_course' ? 'No description set yet. Click Edit Description to add one.' : 'No description set yet. Click Edit Outline to add one.';
        hasContent = false;
    }
    if (hasContent) {
        badge.className  = 'outline-badge outline-set';
        badge.innerHTML  = '<i class="fas fa-circle-check"></i> ' + (MODE === 'short_course' ? 'Description set' : 'Outline set');
    } else {
        badge.className  = 'outline-badge outline-unset';
        badge.innerHTML  = '<i class="fas fa-circle-xmark"></i> ' + (MODE === 'short_course' ? 'Description not set' : 'Outline not set');
    }
}

function updateStats() {
    const totalLessons = modules.reduce((s, m) => s + (m.lessons ? m.lessons.length : 0), 0);
    document.getElementById('stat-modules').textContent = modules.length;
    document.getElementById('stat-lessons').textContent = totalLessons;
    const btn = document.getElementById('btn-go-lessons');
    if (selectedUnitId || COURSE_ID) {
        if (MODE === 'short_course') {
            // In short course mode, link to lesson_editor with course_id
            btn.href = `lesson_editor.php?course_id=${COURSE_ID}`;
        } else {
            btn.href = `lesson_editor.php?unit_id=${selectedUnitId}`;
        }
        btn.style.display = 'flex';
    }
}

// ─────────────────────────────────────────────────────────────
// RENDER MODULE TREE
// ─────────────────────────────────────────────────────────────
function renderModuleTree() {
    const tree = document.getElementById('module-tree');
    tree.innerHTML = '';

    if (modules.length === 0) {
        tree.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-layer-group"></i>
                <h3>No Modules Yet</h3>
                <p>Click "Add Module" to create your first chapter, or use "Import From Unit" to reuse content from another unit you teach.</p>
            </div>`;
        return;
    }

    modules.forEach((mod, idx) => tree.appendChild(buildModuleCard(mod, idx)));
    initModuleDrag();
}

function buildModuleCard(mod, idx) {
    const card = document.createElement('div');
    card.className  = 'module-card';
    card.dataset.id = mod.id;
    card.draggable  = true;

    const lessons = mod.lessons || [];
    card.innerHTML = `
        <div class="module-header" id="mh-${mod.id}">
            <div class="drag-handle"><span></span><span></span><span></span></div>
            <span class="module-number">M${idx + 1}</span>
            <div class="module-title-wrap">
                <span class="module-title" id="mt-${mod.id}"
                      ondblclick="inlineEditModule(${mod.id})"
                      title="Double-click to rename">
                    ${escHtml(mod.title)}
                </span>
            </div>
            <div class="module-actions">
                <button class="btn btn-ghost btn-sm btn-icon" onclick="openAddLessonInline(${mod.id})" title="Add Lesson">
                    <i class="fas fa-plus" style="color:var(--accent2)"></i>
                </button>
                <button class="btn btn-ghost btn-sm btn-icon" onclick="toggleModule(${mod.id})" id="toggle-${mod.id}" title="Collapse/Expand">
                    <i class="fas fa-chevron-down"></i>
                </button>
                <button class="btn btn-ghost btn-sm btn-icon" onclick="confirmDeleteModule(${mod.id}, '${escAttr(mod.title)}')" title="Delete Module">
                    <i class="fas fa-trash" style="color:var(--danger)"></i>
                </button>
            </div>
        </div>
        <div class="lessons-container" id="lc-${mod.id}">
            <div class="lessons-list" id="ll-${mod.id}" data-module-id="${mod.id}">
                ${lessons.map(l => buildLessonRowHTML(l, mod.id)).join('')}
            </div>
            <div class="add-lesson-row" onclick="openAddLessonInline(${mod.id})">
                <i class="fas fa-plus-circle"></i><span>Add Lesson</span>
            </div>
        </div>`;
    return card;
}

function buildLessonRowHTML(lesson, moduleId) {
    const lessonId = lesson.id;
    let editLink;
    if (MODE === 'short_course') {
        editLink = `lesson_editor.php?course_id=${COURSE_ID}&lesson_id=${lessonId}`;
    } else {
        editLink = `lesson_editor.php?lesson_id=${lessonId}&unit_id=${selectedUnitId}`;
    }
    return `
        <div class="lesson-row" draggable="true" data-id="${lesson.id}" data-module="${moduleId}">
            <i class="fas fa-grip-vertical lesson-drag" style="color:var(--text-dim);font-size:0.75rem"></i>
            <span class="lesson-num">L${lesson.lesson_number || (lesson.position !== undefined ? lesson.position + 1 : '')}</span>
            <span class="lesson-title" ondblclick="inlineEditLesson(${lesson.id}, ${moduleId})" id="lt-${lesson.id}" title="Double-click to rename">
                ${escHtml(lesson.title)}
            </span>
            <div class="lesson-actions">
                <a href="${editLink}" class="btn btn-ghost btn-sm btn-icon" title="Edit Content">
                    <i class="fas fa-pen" style="color:var(--accent)"></i>
                </a>
                <button class="btn btn-ghost btn-sm btn-icon" onclick="confirmDeleteLesson(${lesson.id}, '${escAttr(lesson.title)}', ${moduleId})" title="Delete">
                    <i class="fas fa-trash" style="color:var(--danger)"></i>
                </button>
            </div>
        </div>`;
}

// ─────────────────────────────────────────────────────────────
// TOGGLE MODULE COLLAPSE
// ─────────────────────────────────────────────────────────────
function toggleModule(moduleId) {
    const lc   = document.getElementById(`lc-${moduleId}`);
    const btn  = document.getElementById(`toggle-${moduleId}`);
    const icon = btn.querySelector('i');
    if (lc.style.display === 'none') {
        lc.style.display = '';
        icon.className   = 'fas fa-chevron-down';
    } else {
        lc.style.display = 'none';
        icon.className   = 'fas fa-chevron-right';
    }
}

// ─────────────────────────────────────────────────────────────
// INLINE EDIT MODULE TITLE
// ─────────────────────────────────────────────────────────────
function inlineEditModule(moduleId) {
    const titleEl = document.getElementById(`mt-${moduleId}`);
    const current = titleEl.textContent.trim();
    const mod     = modules.find(m => m.id === moduleId);
    titleEl.style.display = 'none';
    const input = document.createElement('input');
    input.type      = 'text';
    input.className = 'module-title-input';
    input.value     = current;
    titleEl.parentNode.insertBefore(input, titleEl.nextSibling);
    input.focus(); input.select();
    const commit = () => {
        const newTitle = input.value.trim();
        input.remove(); titleEl.style.display = '';
        if (!newTitle || newTitle === current) return;
        titleEl.textContent = newTitle;
        mod.title = newTitle;
        ajaxSaveModule(moduleId, newTitle);
    };
    input.addEventListener('blur', commit);
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter') commit();
        if (e.key === 'Escape') { input.remove(); titleEl.style.display = ''; }
    });
}

// ─────────────────────────────────────────────────────────────
// INLINE EDIT LESSON TITLE
// ─────────────────────────────────────────────────────────────
function inlineEditLesson(lessonId, moduleId) {
    const titleEl = document.getElementById(`lt-${lessonId}`);
    const current = titleEl.textContent.trim();
    const mod     = modules.find(m => m.id === moduleId);
    const lesson  = mod.lessons.find(l => l.id === lessonId);
    titleEl.style.display = 'none';
    const input = document.createElement('input');
    input.type      = 'text';
    input.className = 'lesson-title-input';
    input.value     = current;
    titleEl.parentNode.insertBefore(input, titleEl.nextSibling);
    input.focus(); input.select();
    const commit = () => {
        const newTitle = input.value.trim();
        input.remove(); titleEl.style.display = '';
        if (!newTitle || newTitle === current) return;
        titleEl.textContent = newTitle;
        lesson.title = newTitle;
        ajaxSaveLesson(lessonId, moduleId, newTitle);
    };
    input.addEventListener('blur', commit);
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter') commit();
        if (e.key === 'Escape') { input.remove(); titleEl.style.display = ''; }
    });
}

// ─────────────────────────────────────────────────────────────
// ADD LESSON INLINE
// ─────────────────────────────────────────────────────────────
function openAddLessonInline(moduleId) {
    const list = document.getElementById(`ll-${moduleId}`);
    if (list.querySelector('.new-lesson-input-row')) return;
    const row = document.createElement('div');
    row.className = 'lesson-row new-lesson-input-row';
    row.style.borderColor = 'var(--accent2)';
    row.innerHTML = `
        <i class="fas fa-plus-circle" style="color:var(--accent2)"></i>
        <input type="text" class="lesson-title-input" placeholder="Lesson title..." style="flex:1">
        <button class="btn btn-success btn-sm" id="new-lesson-save-btn">Add</button>
        <button class="btn btn-ghost btn-sm" onclick="this.closest('.new-lesson-input-row').remove()">✕</button>`;
    list.appendChild(row);
    const input   = row.querySelector('input');
    const saveBtn = row.querySelector('#new-lesson-save-btn');
    input.focus();
    const submit = () => {
        const title = input.value.trim();
        if (!title) { input.focus(); return; }
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner"></span>';
        ajaxAddLesson(moduleId, title, row);
    };
    saveBtn.addEventListener('click', submit);
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter') submit();
        if (e.key === 'Escape') row.remove();
    });
}

// ─────────────────────────────────────────────────────────────
// MODULE MODAL
// ─────────────────────────────────────────────────────────────
function openAddModuleModal() {
    editingModuleId = null;
    document.getElementById('module-modal-title').innerHTML = '<i class="fas fa-cubes"></i> Add Module';
    document.getElementById('module-title-input').value = '';
    openModal('module-modal');
    setTimeout(() => document.getElementById('module-title-input').focus(), 150);
}

function saveModule() {
    const title = document.getElementById('module-title-input').value.trim();
    if (!title) { toast('Module title is required', 'error'); return; }
    const btn = document.getElementById('module-save-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Saving...';
    const body = new FormData();
    if (MODE === 'short_course') {
        body.append('course_id', COURSE_ID);
    } else {
        body.append('unit_id', selectedUnitId);
    }
    body.append('lecturer_id', LECTURER_ID);
    body.append('title', title);
    if (editingModuleId) body.append('module_id', editingModuleId);

    const endpoint = MODE === 'short_course' ? 'ajax/short_course_save_module.php' : 'ajax/save_module.php';
    fetch(endpoint, { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            if (data.success) { toast(data.message, 'success'); closeModal('module-modal'); reloadTree(); }
            else toast(data.message, 'error');
        })
        .catch(() => toast('Network error', 'error'))
        .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Module'; });
}

function ajaxSaveModule(moduleId, title) {
    const body = new FormData();
    body.append('module_id', moduleId);
    if (MODE === 'short_course') {
        body.append('course_id', COURSE_ID);
    } else {
        body.append('unit_id', selectedUnitId);
    }
    body.append('lecturer_id', LECTURER_ID);
    body.append('title', title);

    const endpoint = MODE === 'short_course' ? 'ajax/short_course_save_module.php' : 'ajax/save_module.php';
    fetch(endpoint, { method: 'POST', body })
        .then(r => r.json())
        .then(d => toast(d.success ? 'Module renamed' : d.message, d.success ? 'success' : 'error'))
        .catch(() => toast('Rename failed', 'error'));
}

// ─────────────────────────────────────────────────────────────
// DELETE MODULE
// ─────────────────────────────────────────────────────────────
function confirmDeleteModule(moduleId, title) {
    if (!confirm(`Delete module "${title}" and all its lessons?\n\nThis cannot be undone.`)) return;
    const body = new FormData();
    body.append('module_id', moduleId);
    if (MODE === 'short_course') {
        body.append('course_id', COURSE_ID);
    } else {
        body.append('unit_id', selectedUnitId);
    }

    const endpoint = MODE === 'short_course' ? 'ajax/short_course_delete_module.php' : 'ajax/delete_module.php';
    fetch(endpoint, { method: 'POST', body })
        .then(r => r.json())
        .then(d => { if (d.success) { toast('Module deleted', 'success'); reloadTree(); } else toast(d.message, 'error'); })
        .catch(() => toast('Delete failed', 'error'));
}

// ─────────────────────────────────────────────────────────────
// ADD / SAVE LESSON
// ─────────────────────────────────────────────────────────────
function ajaxAddLesson(moduleId, title, rowEl) {
    const body = new FormData();
    body.append('module_id', moduleId);
    if (MODE === 'short_course') {
        body.append('course_id', COURSE_ID);
    } else {
        body.append('unit_id', selectedUnitId);
    }
    body.append('title', title);

    const endpoint = MODE === 'short_course' ? 'ajax/short_course_save_lesson.php' : 'ajax/save_lesson.php';
    fetch(endpoint, { method: 'POST', body })
        .then(r => r.json())
        .then(d => {
            if (d.success) { toast('Lesson added', 'success'); rowEl.remove(); reloadTree(); }
            else { toast(d.message, 'error'); rowEl.querySelector('button').disabled = false; rowEl.querySelector('button').textContent = 'Add'; }
        })
        .catch(() => toast('Network error', 'error'));
}

function ajaxSaveLesson(lessonId, moduleId, title) {
    const body = new FormData();
    body.append('lesson_id', lessonId);
    body.append('module_id', moduleId);
    if (MODE === 'short_course') {
        body.append('course_id', COURSE_ID);
    } else {
        body.append('unit_id', selectedUnitId);
    }
    body.append('title', title);

    const endpoint = MODE === 'short_course' ? 'ajax/short_course_save_lesson.php' : 'ajax/save_lesson.php';
    fetch(endpoint, { method: 'POST', body })
        .then(r => r.json())
        .then(d => toast(d.success ? 'Lesson renamed' : d.message, d.success ? 'success' : 'error'))
        .catch(() => toast('Rename failed', 'error'));
}

// ─────────────────────────────────────────────────────────────
// DELETE LESSON
// ─────────────────────────────────────────────────────────────
function confirmDeleteLesson(lessonId, title, moduleId) {
    if (!confirm(`Delete lesson "${title}"?\n\nAll content blocks will be removed.`)) return;
    const body = new FormData();
    body.append('lesson_id', lessonId);
    if (MODE === 'short_course') {
        body.append('course_id', COURSE_ID);
    } else {
        body.append('unit_id', selectedUnitId);
    }

    const endpoint = MODE === 'short_course' ? 'ajax/short_course_delete_lesson.php' : 'ajax/delete_lesson.php';
    fetch(endpoint, { method: 'POST', body })
        .then(r => r.json())
        .then(d => { if (d.success) { toast('Lesson deleted', 'success'); reloadTree(); } else toast(d.message, 'error'); })
        .catch(() => toast('Delete failed', 'error'));
}

// ─────────────────────────────────────────────────────────────
// RELOAD TREE
// ─────────────────────────────────────────────────────────────
function reloadTree() {
    if (MODE === 'short_course') {
        loadShortCourseTree();
    } else {
        loadCourseTree();
    }
}

// ─────────────────────────────────────────────────────────────
// COURSE OUTLINE
// ─────────────────────────────────────────────────────────────
function openOutlineModal() {
    document.getElementById('outline-description').value = outline ? (outline.description || '') : '';
    if (MODE === 'iclm') {
        document.getElementById('outline-content').value = outline ? (outline.outline || '') : '';
        updateCharCount(document.getElementById('outline-content'), 'outline-count', 2000);
    }
    updateCharCount(document.getElementById('outline-description'), 'desc-count', 500);
    openModal('outline-modal');
}

function saveOutline() {
    const desc    = document.getElementById('outline-description').value.trim();
    const content = MODE === 'iclm' ? document.getElementById('outline-content').value.trim() : '';
    const body    = new FormData();

    if (MODE === 'short_course') {
        body.append('course_id', COURSE_ID);
    } else {
        body.append('unit_id', selectedUnitId);
    }
    body.append('lecturer_id', LECTURER_ID);
    body.append('description', desc);
    if (MODE === 'iclm') {
        body.append('outline', content);
    }

    const endpoint = MODE === 'short_course' ? 'ajax/short_course_save_outline.php' : 'ajax/save_course_outline.php';
    fetch(endpoint, { method: 'POST', body })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                if (MODE === 'short_course') {
                    outline = { description: desc };
                } else {
                    outline = { description: desc, outline: content };
                }
                renderOutlineHeader();
                toast('Saved', 'success');
                closeModal('outline-modal');
            } else {
                toast(d.message, 'error');
            }
        })
        .catch(() => toast('Network error', 'error'));
}

// ─────────────────────────────────────────────────────────────
// IMPORT FROM UNIT (ICLM mode only)
// ─────────────────────────────────────────────────────────────
let selectedImportSourceId = null;

function openImportUnitModal() {
    if (!selectedUnitId) { toast('Please select a target unit first', 'error'); return; }
    if (MODE !== 'iclm') { toast('Import is only available for ICLM units', 'error'); return; }

    selectedImportSourceId = null;
    document.getElementById('import-confirm-btn').disabled = true;
    document.getElementById('import-target-name').textContent = selectedUnitName;

    const list = document.getElementById('import-unit-list');
    const others = ALL_UNITS.filter(u => String(u.id) !== String(selectedUnitId));

    if (others.length === 0) {
        list.innerHTML = `
            <div class="empty-import">
                <i class="fas fa-folder-open"></i>
                You only have one unit assigned. There is nothing to import from.
            </div>`;
    } else {
        list.innerHTML = others.map(u => `
            <div class="import-unit-card" data-id="${u.id}" onclick="selectImportSource(${u.id}, this)">
                <div class="import-unit-icon"><i class="fas fa-book-open"></i></div>
                <span class="import-unit-name">${escHtml(u.name)}</span>
                <i class="fas fa-check-circle import-unit-check"></i>
            </div>`).join('');
    }

    openModal('import-unit-modal');
}

function selectImportSource(unitId, cardEl) {
    document.querySelectorAll('.import-unit-card').forEach(c => c.classList.remove('selected'));
    cardEl.classList.add('selected');
    selectedImportSourceId = unitId;
    document.getElementById('import-confirm-btn').disabled = false;
}

function confirmImport() {
    if (!selectedImportSourceId || !selectedUnitId) return;
    if (MODE !== 'iclm') return;

    const sourceName = ALL_UNITS.find(u => String(u.id) === String(selectedImportSourceId))?.name || 'that unit';
    if (!confirm(`Import all modules and lessons from "${sourceName}" into "${selectedUnitName}"?\n\nModules with the same title will be skipped.`)) return;

    const btn = document.getElementById('import-confirm-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Importing...';

    const body = new FormData();
    body.append('source_unit_id', selectedImportSourceId);
    body.append('target_unit_id', selectedUnitId);

    fetch('ajax/copy_unit_content.php', { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal('import-unit-modal');
                toast(data.message, 'success');
                loadCourseTree();
            } else {
                toast(data.message || 'Import failed', 'error');
            }
        })
        .catch(() => toast('Network error', 'error'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-file-import"></i> Import Modules';
        });
}

// ─────────────────────────────────────────────────────────────
// DRAG & DROP — MODULES
// ─────────────────────────────────────────────────────────────
let dragSrcModule = null;

function initModuleDrag() {
    const cards = document.querySelectorAll('.module-card');
    cards.forEach(card => {
        card.addEventListener('dragstart', e => { dragSrcModule = card; card.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; });
        card.addEventListener('dragend',   () => { card.classList.remove('dragging'); document.querySelectorAll('.module-card').forEach(c => c.classList.remove('drag-over')); dragSrcModule = null; });
        card.addEventListener('dragover',  e => { e.preventDefault(); if (card !== dragSrcModule) card.classList.add('drag-over'); });
        card.addEventListener('dragleave', () => card.classList.remove('drag-over'));
        card.addEventListener('drop', e => {
            e.preventDefault(); card.classList.remove('drag-over');
            if (!dragSrcModule || dragSrcModule === card) return;
            const tree = document.getElementById('module-tree');
            const all  = [...tree.querySelectorAll('.module-card')];
            const si   = all.indexOf(dragSrcModule), di = all.indexOf(card);
            if (si < di) tree.insertBefore(dragSrcModule, card.nextSibling);
            else         tree.insertBefore(dragSrcModule, card);
            saveModuleOrder();
        });
    });
    document.querySelectorAll('.lessons-list').forEach(list => initLessonDrag(list));
}

function saveModuleOrder() {
    const ids  = [...document.querySelectorAll('#module-tree .module-card')].map(c => parseInt(c.dataset.id));
    const body = new FormData();
    if (MODE === 'short_course') {
        body.append('course_id', COURSE_ID);
    } else {
        body.append('unit_id', selectedUnitId);
    }
    body.append('order', JSON.stringify(ids));

    const endpoint = MODE === 'short_course' ? 'ajax/short_course_reorder_modules.php' : 'ajax/reorder_modules.php';
    fetch(endpoint, { method: 'POST', body })
        .then(r => r.json())
        .then(d => { if (d.success) reloadTree(); })
        .catch(() => toast('Reorder failed', 'error'));
}

// ─────────────────────────────────────────────────────────────
// DRAG & DROP — LESSONS
// ─────────────────────────────────────────────────────────────
let dragSrcLesson = null;

function initLessonDrag(list) {
    list.querySelectorAll('.lesson-row').forEach(row => {
        row.addEventListener('dragstart', e => { dragSrcLesson = row; row.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; e.stopPropagation(); });
        row.addEventListener('dragend',   () => { row.classList.remove('dragging'); list.querySelectorAll('.lesson-row').forEach(r => r.classList.remove('drag-over')); dragSrcLesson = null; });
        row.addEventListener('dragover',  e => { e.preventDefault(); e.stopPropagation(); if (row !== dragSrcLesson) row.classList.add('drag-over'); });
        row.addEventListener('dragleave', () => row.classList.remove('drag-over'));
        row.addEventListener('drop', e => {
            e.preventDefault(); e.stopPropagation(); row.classList.remove('drag-over');
            if (!dragSrcLesson || dragSrcLesson === row) return;
            const rows = [...list.querySelectorAll('.lesson-row')];
            const si   = rows.indexOf(dragSrcLesson), di = rows.indexOf(row);
            if (si < di) list.insertBefore(dragSrcLesson, row.nextSibling);
            else         list.insertBefore(dragSrcLesson, row);
            saveLessonOrder(list.dataset.moduleId);
        });
    });
}

function saveLessonOrder(moduleId) {
    const list = document.getElementById(`ll-${moduleId}`);
    const ids  = [...list.querySelectorAll('.lesson-row')].map(r => parseInt(r.dataset.id)).filter(id => !isNaN(id));
    const body = new FormData();
    body.append('module_id', moduleId);
    if (MODE === 'short_course') {
        body.append('course_id', COURSE_ID);
    } else {
        body.append('unit_id', selectedUnitId);
    }
    body.append('order', JSON.stringify(ids));

    const endpoint = MODE === 'short_course' ? 'ajax/short_course_reorder_lessons.php' : 'ajax/reorder_lessons.php';
    fetch(endpoint, { method: 'POST', body })
        .then(r => r.json())
        .then(() => {})
        .catch(() => toast('Lesson reorder failed', 'error'));
}

// ─────────────────────────────────────────────────────────────
// MODAL HELPERS
// ─────────────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });
});

// ─────────────────────────────────────────────────────────────
// CHAR COUNT
// ─────────────────────────────────────────────────────────────
function updateCharCount(el, countId, max) {
    const len = el.value.length;
    document.getElementById(countId).textContent = len;
    document.getElementById(countId).style.color = len > max * 0.9 ? 'var(--danger)' : '';
}

// ─────────────────────────────────────────────────────────────
// TOAST
// ─────────────────────────────────────────────────────────────
function toast(msg, type = 'info') {
    const container = document.getElementById('toast');
    const el = document.createElement('div');
    el.className = `toast-item ${type}`;
    const icons = { success: 'fa-circle-check', error: 'fa-circle-xmark', info: 'fa-circle-info' };
    el.innerHTML = `<i class="fas ${icons[type] || 'fa-circle-info'}"></i> ${escHtml(msg)}`;
    container.appendChild(el);
    setTimeout(() => el.remove(), 2800);
}

// ─────────────────────────────────────────────────────────────
// UTILS
// ─────────────────────────────────────────────────────────────
function escHtml(s) {
    return String(s||'').replace(/&/g,'&').replace(/</g,'<').replace(/>/g,'>').replace(/"/g,'"');
}
function escAttr(s) {
    return String(s||'').replace(/'/g,"\\'").replace(/"/g,'"');
}
</script>
</body>
</html>
