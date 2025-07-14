<?php
session_start();
include '../actions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - UNILIS</title>
    <style>
        body { font-family: Arial; padding: 20px; max-width: 900px; margin: auto; }
        button { padding: 10px 20px; margin: 10px; }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 60%;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>

<h2>Welcome, <?= $_SESSION['user_name'] ?> (Admin)</h2>
<p>Select an action:</p>

<!-- Action Buttons -->
<button onclick="openModal('universityModal')">Add University</button>
<button onclick="openModal('departmentModal')">Add Department</button>
<button onclick="openModal('courseModal')">Add Course</button>
<button onclick="openModal('unitModal')">Add Unit</button>
<button onclick="openModal('lecturerModal')">Add Lecturer</button>
<a href="../logout.php" style="color: red; text-decoration: none;">Logout</a>

<!-- UNIVERSITY MODAL -->
<div id="universityModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal('universityModal')">&times;</span>
    <h3>Add University</h3>
    <?php if (!empty($university_success)) echo "<p class='success'>$university_success</p>"; ?>
    <?php if (!empty($university_error)) echo "<p class='error'>$university_error</p>"; ?>
    <form method="POST">
        <input type="hidden" name="action" value="add_university">
        <label>University Name:</label>
        <input type="text" name="university_name" required>
        <button type="submit">Save</button>
    </form>
  </div>
</div>

<!-- DEPARTMENT MODAL -->
<div id="departmentModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal('departmentModal')">&times;</span>
    <h3>Add Department</h3>
    <?php if (!empty($department_success)) echo "<p class='success'>$department_success</p>"; ?>
    <?php if (!empty($department_error)) echo "<p class='error'>$department_error</p>"; ?>
    <form method="POST">
    <input type="hidden" name="action" value="add_department">

    <label>Department Name:</label>
    <input type="text" name="department_name" required>

    <label>Select University:</label>
    <select name="university_id" required>
        <option value="">-- Select University --</option>
        <?php
        $res = $conn->query("SELECT id, name FROM universities ORDER BY name ASC");
        while ($row = $res->fetch_assoc()) {
            echo "<option value='{$row['id']}'>{$row['name']}</option>";
        }
        ?>
    </select>

    <button type="submit">Add Department</button>
</form>

  </div>
</div>

<!-- COURSE MODAL -->
<div id="courseModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal('courseModal')">&times;</span>
    <h3>Add Course</h3>
    <?php if (!empty($course_success)) echo "<p class='success'>$course_success</p>"; ?>
    <?php if (!empty($course_error)) echo "<p class='error'>$course_error</p>"; ?>
    <form method="POST">
        <input type="hidden" name="action" value="add_course">
        <label>Course Name:</label>
        <input type="text" name="course_name" required>
        <label>Department:</label>
        <select name="department_id" required>
            <option value="">-- Select Department --</option>
            <?php
            $departments = $conn->query("SELECT * FROM departments");
            while ($d = $departments->fetch_assoc()) {
                echo "<option value='{$d['id']}'>{$d['name']}</option>";
            }
            ?>
        </select>
        <button type="submit">Save</button>
    </form>
  </div>
</div>

<!-- UNIT MODAL -->
<div id="unitModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal('unitModal')">&times;</span>
    <h3>Add Unit</h3>
    <?php if (!empty($unit_success)) echo "<p class='success'>$unit_success</p>"; ?>
    <?php if (!empty($unit_error)) echo "<p class='error'>$unit_error</p>"; ?>
    <form method="POST">
        <input type="hidden" name="action" value="add_unit">
        <label>Unit Name:</label>
        <input type="text" name="unit_name" required>
        <label>Unit Code:</label>
        <input type="text" name="unit_code" required>
        <label>Course:</label>
        <select name="course_id" required>
            <option value="">-- Select Course --</option>
            <?php
            $courses = $conn->query("SELECT * FROM courses");
            while ($c = $courses->fetch_assoc()) {
                echo "<option value='{$c['id']}'>{$c['name']}</option>";
            }
            ?>
        </select>
        <button type="submit">Save</button>
    </form>
  </div>
</div>

<!-- LECTURER MODAL -->
<div id="lecturerModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal('lecturerModal')">&times;</span>
    <h3>Add Lecturer</h3>
    <?php if (!empty($lecturer_success)) echo "<p class='success'>$lecturer_success</p>"; ?>
    <?php if (!empty($lecturer_error)) echo "<p class='error'>$lecturer_error</p>"; ?>
    <form method="POST">
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
                echo "<option value='{$u['id']}'>{$u['name']}</option>";
            }
            ?>
        </select>
        <button type="submit">Save</button>
    </form>
  </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).style.display = "block";
}
function closeModal(id) {
    document.getElementById(id).style.display = "none";
}
window.onclick = function(event) {
    const modals = document.getElementsByClassName("modal");
    for (let i = 0; i < modals.length; i++) {
        if (event.target === modals[i]) {
            modals[i].style.display = "none";
        }
    }
}
</script>

</body>
</html>
