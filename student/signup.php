<?php
include '../actions.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Signup</title>
    <style>
        body { font-family: Arial; padding: 20px; max-width: 600px; margin: auto; }
        input, select { width: 100%; padding: 8px; margin: 8px 0; }
        button { padding: 10px 20px; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h2>Student Signup</h2>

    <?php if (!empty($success)): ?><p class="success"><?= $success ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="error"><?= $error ?></p><?php endif; ?>

    <form method="POST">
    <input type="hidden" name="action" value="signup_student">

    <label>Reg No:</label>
    <input type="text" name="reg_no" required>

    <label>Full Name:</label>
    <input type="text" name="name" required>

    <label>Email:</label>
    <input type="email" name="email" required>

    <label>University:</label>
    <select name="university" required>
        <option value="">-- Select University --</option>
        <?php
        $res = $conn->query("SELECT * FROM universities");
        while ($row = $res->fetch_assoc()) {
            echo "<option value='{$row['id']}'>{$row['name']}</option>";
        }
        ?>
    </select>

    <label>Department:</label>
    <select name="department" required>
        <option value="">-- Select Department --</option>
        <?php
        $res = $conn->query("SELECT * FROM departments");
        while ($row = $res->fetch_assoc()) {
            echo "<option value='{$row['id']}'>{$row['name']}</option>";
        }
        ?>
    </select>

    <label>Course:</label>
    <select name="course" required>
        <option value="">-- Select Course --</option>
        <?php
        $res = $conn->query("SELECT * FROM courses");
        while ($row = $res->fetch_assoc()) {
            echo "<option value='{$row['id']}'>{$row['name']}</option>";
        }
        ?>
    </select>

    <label>Year of Study:</label>
    <input type="number" name="year_of_study" min="1" max="6" required>

    <label>Year Joined:</label>
    <input type="number" name="year_joined" min="2000" max="<?= date('Y') ?>" required>

    <label>Password:</label>
    <input type="password" name="password" required>

    <label>Confirm Password:</label>
    <input type="password" name="confirm_password" required>

    <button type="submit">Register</button>
</form>

</body>
</html>
