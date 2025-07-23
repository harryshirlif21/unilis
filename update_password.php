<?php
session_start();
include 'actions.php'; // Includes $conn and generate_csrf_token()
$csrf_token = generate_csrf_token(); // Generate CSRF token
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Password - UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4CAF50;
            --secondary-color: #3f51b5;
            --text-color: #333;
            --light-bg: #f5f5f5;
            --white: #ffffff;
            --border-color: #e0e0e0;
            --danger-color: #f44336;
            --shadow-light: 0 2px 10px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.15);
            --accent-gradient: linear-gradient(to top right, #ff00cc, #3333ff);
            --yellowgreen-color: #9ACD32;
            --input-border-visible: #b0b0b0;
            --link-color: #FF9800;
            --secondary-text-color: #666;
            --button-bg-color: #3f51b5;
        }

        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-image: url('https://placehold.co/1920x1080/000000/FFFFFF?text=Background+Image'); /* REPLACE WITH PATH TO trim2.jpg */
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            color: var(--text-color);
            line-height: 1.6;
        }

        .update-wrapper {
            background-color: var(--white);
            border-radius: 20px;
            box-shadow: var(--shadow-medium);
            width: 90%;
            max-width: 500px;
            padding: 40px;
            box-sizing: border-box;
            text-align: center;
        }

        .update-wrapper h2 {
            color: var(--yellowgreen-color);
            margin-bottom: 20px;
            font-size: 2em;
            font-weight: 600;
        }

        .input-field {
            margin-bottom: 20px;
            text-align: left;
            width: 100%;
            max-width: 300px;
            margin-left: auto;
            margin-right: auto;
        }

        .input-field label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--secondary-color);
            font-size: 0.9em;
        }

        .input-field input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--input-border-visible);
            border-radius: 8px;
            font-size: 1em;
            box-sizing: border-box;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .input-field input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        }

        .error {
            color: var(--danger-color);
            font-size: 0.85em;
            margin-bottom: 20px;
            text-align: center;
        }

        .success {
            color: green;
            font-size: 0.85em;
            margin-bottom: 20px;
            text-align: center;
        }

        .update-btn {
            width: 100%;
            max-width: 300px;
            background-color: var(--primary-color);
            color: var(--white);
            padding: 15px 20px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            margin-top: 10px;
        }

        .update-btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(63, 81, 181, 0.4);
        }

        .back-link {
            margin-top: 20px;
            font-size: 0.85em;
            color: var(--link-color);
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .update-wrapper {
                padding: 20px;
            }
            .update-wrapper h2 {
                font-size: 1.6em;
            }
            .input-field {
                max-width: 100%;
            }
            .update-btn {
                font-size: 1em;
                padding: 12px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="update-wrapper">
        <h2>Update Password</h2>
        <?php
        if (isset($_SESSION['login_error'])) {
            echo '<p class="error">' . $_SESSION['login_error'] . '</p>';
            unset($_SESSION['login_error']);
        }
        if (isset($_SESSION['login_success'])) {
            echo '<p class="success">' . $_SESSION['login_success'] . '</p>';
            unset($_SESSION['login_success']);
        }
        ?>

        <form method="POST" action="actions.php?action=update_password">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="input-field">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>
            <div class="input-field">
                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div class="input-field">
                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="update-btn">Update Password</button>
        </form>
        <a href="login.php" class="back-link">Back to Login</a>
    </div>
</body>
</html>