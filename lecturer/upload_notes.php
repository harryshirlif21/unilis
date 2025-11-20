<?php
session_start();
require_once 'config/db.php';

// --- Authentication ---
if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer'){
    header("Location: login.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];

// --- Handle Form Submission ---
if(isset($_POST['upload_note'])){
    $unit_id = $_POST['unit_id'];
    
    if(isset($_FILES['note_file']) && $_FILES['note_file']['error'] === 0){
        $uploadDir = 'uploads/notes/';
        if(!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $fileName = time() . '_' . basename($_FILES['note_file']['name']);
        $filePath = $uploadDir . $fileName;

        if(move_uploaded_file($_FILES['note_file']['tmp_name'], $filePath)){
            // Insert into classnotes
            $stmt = $conn->prepare("INSERT INTO classnotes (unit_id, lecturer_id, file_path) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $unit_id, $lecturer_id, $filePath);
            if($stmt->execute()){
                $note_id = $stmt->insert_id;

                // Insert student progress entries
                $sql = "INSERT INTO student_notes_progress (student_id, note_id)
                        SELECT s.id, ? 
                        FROM students s
                        JOIN courses c ON s.course_id = c.id
                        JOIN units u ON u.course_id = c.id
                        WHERE u.id = ?";
                $stmt2 = $conn->prepare($sql);
                $stmt2->bind_param("ii", $note_id, $unit_id);
                $stmt2->execute();

                $success = "Note uploaded successfully!";
            } else {
                $error = "Database error: " . $stmt->error;
            }
        } else {
            $error = "Failed to move uploaded file.";
        }
    } else {
        $error = "Please select a file to upload.";
    }
}

// --- Fetch Units for Dropdown ---
$units = [];
$sql = "SELECT u.id, u.name, u.code 
        FROM units u
        JOIN lecturer_units lu ON lu.unit_id = u.id
        WHERE lu.lecturer_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()){
    $units[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Lecturer Notes Upload</title>
<style>
body{font-family: Arial,sans-serif; padding:20px; background:#f5f5f5;}
.container{max-width:700px; margin:auto; background:white; padding:20px; border-radius:10px; box-shadow:0 2px 5px rgba(0,0,0,0.1);}
input[type="file"], select, button{padding:8px; margin-top:10px; width:100%; box-sizing:border-box;}
.success{color:green;}
.error{color:red;}
</style>
</head>
<body>
<div class="container">
    <h1>Upload Class Notes</h1>

    <?php if(isset($success)) echo "<p class='success'>$success</p>"; ?>
    <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Select Unit:</label>
        <select name="unit_id" required>
            <option value="">-- Choose Unit --</option>
            <?php foreach($units as $unit): ?>
                <option value="<?= $unit['id'] ?>"><?= htmlspecialchars($unit['code'].' - '.$unit['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label>Upload Note (PDF/DOCX):</label>
        <input type="file" name="note_file" accept=".pdf,.doc,.docx" required>

        <button type="submit" name="upload_note">Upload Note</button>
    </form>

    <hr>
    <h2>Your Uploaded Notes</h2>
    <ul>
    <?php
        $sql2 = "SELECT cn.id, cn.file_path, u.name AS unit_name, u.code AS unit_code 
                 FROM classnotes cn
                 JOIN units u ON u.id = cn.unit_id
                 WHERE cn.lecturer_id = ?
                 ORDER BY cn.uploaded_at DESC";
        $stmt = $conn->prepare($sql2);
        $stmt->bind_param("i", $lecturer_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while($note = $res->fetch_assoc()){
            echo "<li><strong>{$note['unit_code']} - {$note['unit_name']}</strong>: 
                  <a href='{$note['file_path']}' target='_blank'>View</a></li>";
        }
    ?>
    </ul>
</div>
</body>
</html>
