<?php
/**
 * Technician Management
 * UNILIS Academic Foundation Expansion
 * Global Admin can create and manage technician accounts
 */

define('PHASE1_ACCESS', true);
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_extended.php';

// Only Global Admin can access
phase1_guard_role('admin', '../../login.php');

$message = '';
$message_type = '';

// Handle actions
$action = $_POST['action'] ?? '';

if ($action === 'add_technician') {
    $staff_id = trim($_POST['staff_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $department_id = (int)($_POST['department_id'] ?? 0);
    $specialization = trim($_POST['specialization'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    
    if ($staff_id && $name && $email && $password) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO technicians (staff_id, name, email, phone, password, department_id, specialization, qualification, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param('sssssiss', $staff_id, $name, $email, $phone, $hashed, $department_id, $specialization, $qualification);
        if ($stmt->execute()) {
            $message = "Technician '$name' added successfully!";
            $message_type = 'success';
            phase1_log_upgrade('add_technician', "Technician $name ($email) added", 'success', [], $conn);
        } else {
            $message = "Failed to add technician: " . $stmt->error;
            $message_type = 'error';
        }
        $stmt->close();
    } else {
        $message = "Staff ID, Name, Email, and Password are required.";
        $message_type = 'error';
    }
}

if ($action === 'toggle_technician') {
    $id = (int)($_POST['id'] ?? 0);
    $current = (int)($_POST['current'] ?? 0);
    $new = $current ? 0 : 1;
    $stmt = $conn->prepare("UPDATE technicians SET is_active = ? WHERE id = ?");
    $stmt->bind_param('ii', $new, $id);
    if ($stmt->execute()) {
        $message = "Technician status updated.";
        $message_type = 'success';
    }
    $stmt->close();
}

// Get all technicians
$technicians = $conn->query("
    SELECT t.*, d.name as department_name 
    FROM technicians t 
    LEFT JOIN departments d ON t.department_id = d.id 
    ORDER BY t.name
");

// Get departments
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Management - UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; color: #333; }
        .header { background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #fff; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 20px; }
        .header a { color: #fff; text-decoration: none; opacity: .8; }
        .header a:hover { opacity: 1; }
        .container { max-width: 1100px; margin: 0 auto; padding: 24px; }
        .message { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
        .message.success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .message.error { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
        .card { background: #fff; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.1); margin-bottom: 20px; overflow: hidden; }
        .card-header { padding: 16px 20px; border-bottom: 1px solid #e5e7eb; font-weight: 600; font-size: 15px; }
        .card-body { padding: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background: #f8fafc; padding: 10px 14px; text-align: left; font-size: 12px; text-transform: uppercase; color: #6b7280; border-bottom: 2px solid #e5e7eb; }
        td { padding: 10px 14px; border-bottom: 1px solid #f0f0f0; }
        tr:hover td { background: #f9fafb; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-grid .full { grid-column: 1 / -1; }
        label { display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px; }
        input, select, textarea { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-warning { background: #d97706; color: #fff; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-inactive { background: #fee2e2; color: #dc2626; }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-tools"></i> Technician Management</h1>
        <a href="../../admin/dashboard.php"><i class="fas fa-arrow-left"></i> Back to Admin Dashboard</a>
    </div>
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">Add New Technician</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_technician">
                    <div class="form-grid">
                        <div>
                            <label>Staff ID *</label>
                            <input type="text" name="staff_id" required placeholder="e.g., TEC001">
                        </div>
                        <div>
                            <label>Full Name *</label>
                            <input type="text" name="name" required>
                        </div>
                        <div>
                            <label>Email *</label>
                            <input type="email" name="email" required>
                        </div>
                        <div>
                            <label>Phone</label>
                            <input type="text" name="phone" placeholder="+2547XX XXX XXX">
                        </div>
                        <div>
                            <label>Password *</label>
                            <input type="password" name="password" required minlength="8">
                        </div>
                        <div>
                            <label>Department</label>
                            <select name="department_id">
                                <option value="">-- Select --</option>
                                <?php while ($d = $departments->fetch_assoc()): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label>Specialization</label>
                            <input type="text" name="specialization" placeholder="e.g., Network, Hardware, Lab">
                        </div>
                        <div>
                            <label>Qualification</label>
                            <input type="text" name="qualification" placeholder="e.g., Diploma, Degree">
                        </div>
                        <div class="full">
                            <button type="submit" class="btn btn-primary">Add Technician</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Registered Technicians</div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>Staff ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Specialization</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($technicians && $technicians->num_rows > 0): ?>
                            <?php while ($t = $technicians->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($t['staff_id']) ?></td>
                                    <td><?= htmlspecialchars($t['name']) ?></td>
                                    <td><?= htmlspecialchars($t['email']) ?></td>
                                    <td><?= htmlspecialchars($t['department_name'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($t['specialization'] ?? '—') ?></td>
                                    <td><span class="badge <?= $t['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $t['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                    <td>
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="action" value="toggle_technician">
                                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                            <input type="hidden" name="current" value="<?= $t['is_active'] ?>">
                                            <button type="submit" class="btn btn-warning"><?= $t['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center;color:#6b7280;padding:20px;">No technicians registered yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>