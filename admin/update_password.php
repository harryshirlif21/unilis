<?php
require_once '../config/db.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header("Location: ../index.html");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            throw new Exception("All fields are required");
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception("New password and confirm password do not match");
        }
        
        if (strlen($new_password) < 8) {
            throw new Exception("Password must be at least 8 characters long");
        }
        
        // Get current password from database
        $current_password_hash = '';
        if ($user_role === 'student') {
            $sql = "SELECT password FROM students WHERE id = ?";
        } elseif ($user_role === 'lecturer') {
            $sql = "SELECT password FROM lecturers WHERE id = ?";
        } elseif ($user_role === 'admin') {
            $sql = "SELECT password FROM admins WHERE id = ?";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (!$result) {
            throw new Exception("User not found");
        }
        
        $current_password_hash = $result['password'];
        
        // Verify current password
        if (!password_verify($current_password, $current_password_hash)) {
            throw new Exception("Current password is incorrect");
        }
        
        // Hash new password
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password in database
        if ($user_role === 'student') {
            $update_sql = "UPDATE students SET password = ? WHERE id = ?";
        } elseif ($user_role === 'lecturer') {
            $update_sql = "UPDATE lecturers SET password = ? WHERE id = ?";
        } elseif ($user_role === 'admin') {
            $update_sql = "UPDATE admins SET password = ? WHERE id = ?";
        }
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_password_hash, $user_id);
        $update_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - UNILIS</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    
    <style>
        :root {
            /* Dark Theme - 21st.magic Design System */
            --bg-primary: #0a0a0f;
            --bg-secondary: #1a1a2e;
            --bg-tertiary: #16213e;
            --bg-glass: rgba(255, 255, 255, 0.05);
            --bg-glass-hover: rgba(255, 255, 255, 0.08);
            
            --text-primary: #ffffff;
            --text-secondary: #b8b8d1;
            --text-muted: #6b7280;
            
            --accent-primary: #6366f1;
            --accent-secondary: #8b5cf6;
            --accent-success: #10b981;
            --accent-warning: #f59e0b;
            --accent-error: #ef4444;
            
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-hero: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1), 0 10px 10px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px rgba(0, 0, 0, 0.25);
            
            --blur-sm: blur(4px);
            --blur-md: blur(8px);
            --blur-lg: blur(16px);
            
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-2xl: 1.5rem;
            --radius-3xl: 2rem;
            --radius-full: 9999px;
            
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-normal: 250ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 350ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Light Theme */
        [data-theme="light"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --bg-glass: rgba(0, 0, 0, 0.05);
            --bg-glass-hover: rgba(0, 0, 0, 0.08);
            
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --text-muted: #64748b;
            
            --accent-primary: #4f46e5;
            --accent-secondary: #7c3aed;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-hero: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            transition: background-color var(--transition-normal), color var(--transition-normal);
        }
        
        /* Layout */
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        /* Header */
        .header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .header p {
            color: var(--text-secondary);
            font-size: 1.125rem;
        }
        
        /* Card */
        .card {
            background: var(--bg-glass);
            backdrop-filter: var(--blur-md);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            margin-bottom: 2rem;
            transition: all var(--transition-normal);
        }
        
        [data-theme="light"] .card {
            border: 1px solid rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .card:hover {
            transform: translateY(-4px);
            background: var(--bg-glass-hover);
            box-shadow: var(--shadow-xl);
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--bg-tertiary);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all var(--transition-normal);
        }
        
        [data-theme="light"] .form-input {
            border: 1px solid rgba(0, 0, 0, 0.2);
            background: #ffffff;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all var(--transition-normal);
            border: none;
            font-size: 0.875rem;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left var(--transition-slow);
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn-primary {
            background: var(--accent-primary);
            color: white;
            border: 1px solid var(--accent-primary);
        }
        
        .btn-primary:hover {
            background: var(--accent-secondary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-secondary {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-secondary:hover {
            background: var(--bg-glass-hover);
            color: var(--text-primary);
            transform: translateY(-2px);
        }
        
        [data-theme="light"] .btn-secondary {
            color: var(--accent-primary);
            border: 2px solid var(--accent-primary);
        }
        
        [data-theme="light"] .btn-secondary:hover {
            background: var(--accent-primary);
            color: white;
        }
        
        /* Alert Styles */
        .alert {
            padding: 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-color: var(--accent-success);
            color: var(--accent-success);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--accent-error);
            color: var(--accent-error);
        }
        
        /* Back Link */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--accent-primary);
            text-decoration: none;
            font-weight: 500;
            transition: all var(--transition-normal);
        }
        
        .back-link:hover {
            color: var(--accent-secondary);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body data-theme="light">
    <div class="container">
        <div class="header">
            <h1>Change Password</h1>
            <p>Update your password regularly for security</p>
            <a href="profile.php" class="back-link">
                <span class="material-symbols-outlined">arrow_back</span>
                Back to Profile
            </a>
        </div>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_SESSION['error']); ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['success']); ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <div class="card">
            <form id="passwordForm">
                <div class="form-group">
                    <label class="form-label" for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <a href="profile.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Form validation
        document.getElementById('passwordForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            
            try {
                const response = await fetch('update_password.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Show success message
                    const successDiv = document.createElement('div');
                    successDiv.className = 'alert alert-success';
                    successDiv.textContent = result.message;
                    document.querySelector('.container').insertBefore(successDiv, document.querySelector('.header'));
                    
                    // Clear form
                    e.target.reset();
                    
                    // Redirect after a short delay
                    setTimeout(() => {
                        window.location.href = 'profile.php';
                    }, 2000);
                } else {
                    // Show error message
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-error';
                    errorDiv.textContent = result.message;
                    document.querySelector('.container').insertBefore(errorDiv, document.querySelector('.header'));
                }
                
            } catch (error) {
                // Show general error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-error';
                errorDiv.textContent = 'An error occurred. Please try again.';
                document.querySelector('.container').insertBefore(errorDiv, document.querySelector('.header'));
            }
        });
        
        // Password strength indicator
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        
        function checkPasswordStrength(password) {
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (password.length >= 16) strength++;
            
            // Character variety checks
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            return strength;
        }
        
        newPasswordInput?.addEventListener('input', (e) => {
            const password = e.target.value;
            const strength = checkPasswordStrength(password);
            
            // Update password strength indicator if exists
            const strengthIndicator = document.getElementById('passwordStrength');
            if (strengthIndicator) {
                strengthIndicator.textContent = getStrengthText(strength);
                strengthIndicator.className = getStrengthClass(strength);
            }
        });
        
        confirmPasswordInput?.addEventListener('input', (e) => {
            const password = e.target.value;
            const newPassword = newPasswordInput.value;
            
            if (password && newPassword && password !== newPassword) {
                if (confirmPasswordInput.setCustomValidity('Passwords do not match')) {
                    confirmPasswordInput.setCustomValidity('Passwords do not match');
                    confirmPasswordInput.reportValidity();
                } else {
                    confirmPasswordInput.setCustomValidity('');
                }
            } else {
                confirmPasswordInput.setCustomValidity('');
            }
        });
        
        // Add password strength indicator if not present
        if (!document.getElementById('passwordStrength')) {
            const strengthDiv = document.createElement('div');
            strengthDiv.id = 'passwordStrength';
            strengthDiv.style.css = 'margin-top: 0.5rem; font-size: 0.875rem; color: var(--text-secondary);';
            newPasswordInput?.parentNode.insertBefore(strengthDiv, newPasswordInput);
        }
    </script>
</body>
</html>
