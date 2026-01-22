<?php
session_start();
require_once '../config/db.php';

/* ============================
   HANDLE APPROVAL ACTION
=============================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id'])) {

    $student_id = (int) $_POST['approve_id'];

    $stmt = $conn->prepare("
        UPDATE students 
        SET is_verified = 1 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $student_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Student approved successfully.";
    } else {
        $_SESSION['error'] = "Failed to approve student.";
    }

    $stmt->close();
    header("Location: pendingreq.php");
    exit;
}

/* ============================
   FETCH PENDING STUDENTS
=============================*/
$result = $conn->query("
    SELECT id, reg_no, name, email  
    FROM students 
    WHERE is_verified = 0
    ORDER BY id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Pending Student Approvals</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background: #2c3e50;
            color: #fff;
        }
        .btn {
            padding: 6px 12px;
            border: none;
            cursor: pointer;
        }
        .approve {
            background: #27ae60;
            color: white;
        }
        .back {
            background: #3498db;
            color: white;
            margin-bottom: 15px;
            display: inline-block;
            padding: 8px 14px;
            text-decoration: none;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            margin-bottom: 10px;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<a href="dashboard.php" class="back">← Back to Dashboard</a>

<h2>Pending Student Verification Requests</h2>

<?php if (!empty($_SESSION['success'])): ?>
    <div class="success">
        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
    <div class="error">
        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Student Name</th>
            <th>Email</th>
            <th>Requested On</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>

    <?php if ($result->num_rows > 0): ?>
        <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $i++; ?></td>
                <td><?= htmlspecialchars($row['name']); ?></td>
                <td><?= htmlspecialchars($row['email']); ?></td>
                <td><?= $row['created_at'] ?? 'N/A'; ?></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="approve_id" value="<?= $row['id']; ?>">
                        <button 
                            class="btn approve"
                            onclick="return confirm('Approve this student?');">
                            Approve
                        </button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
            <td colspan="5" style="text-align:center;">
                No pending requests found.
            </td>
        </tr>
    <?php endif; ?>

    </tbody>
</table>

</body>
</html>
