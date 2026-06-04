<?php
session_start();
include 'actions.php';

// Redirect if already logged in (any role)
if (isset($_SESSION['user_id']) || isset($_SESSION['user_role'])) {
    switch ($_SESSION['user_role'] ?? '') {
        case 'student':
            header("Location: student/dashboard.php");
            break;
        case 'lecturer':
            header("Location: lecturer/dashboard.php");
            break;
        case 'admin':
            header("Location: admin/dashboard.php");
            break;
        default:
            // Fallback: if session exists but role missing
            header("Location: student/dashboard.php");
            break;
    }
    exit;
}

// Flash message for login errors (from actions.php)
$login_error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']); // Clear after display
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - UNILIS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy: '#1e3a8a',
                        gold: '#d4af37',
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl h-auto md:h-[550px] flex overflow-hidden">
        <!-- Left Panel -->
        <div class="hidden md:flex w-2/5 text-white flex-col justify-between p-10 relative overflow-hidden">
            <!-- Background Image -->
            <div class="absolute inset-0 bg-cover bg-center bg-no-repeat" 
                 style="background-image: url('https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1740&q=80');">
            </div>
            
            <!-- Glass Effect Overlay -->
            <div class="absolute inset-0 bg-gradient-to-br from-pink-500/30 to-blue-600/30"></div>
            
            <!-- Additional Glass Effect for depth -->
            <div class="absolute inset-0 bg-white/10"></div>
            
            <!-- Content -->
            <div class="relative z-10">
                <div class="quote-top text-sm uppercase tracking-wider opacity-90 bg-white/20 rounded-lg px-4 py-2 inline-block shadow-lg border border-white/10">A WISE QUOTE</div>
            </div>
            
            <div class="text-content relative z-10">
                <div class="main-text text-4xl font-bold leading-tight mb-5 drop-shadow-xl text-shadow">Get Everything You Want</div>
                <div class="sub-text text-sm leading-relaxed opacity-95 drop-shadow-lg max-w-xs">
                    You can get everything you want if you work hard through the process and stick to the plan.
                </div>
            </div>
        </div>

        <!-- Right Panel -->
        <div class="w-full md:w-3/5 bg-white p-8 md:p-10 flex flex-col justify-center items-center text-center">
            <div class="logo text-2xl font-bold text-navy mb-6 flex items-center gap-2">
                <i class="fas fa-graduation-cap"></i> UNILIS
            </div>

            <h2 class="text-3xl font-bold text-gray-800 mb-2">Welcome Back</h2>
            <p class="text-gray-600 mb-6">Enter your email and password to access your account</p>

            <!-- Error Messages -->
            <?php if ($login_error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 w-full max-w-sm">
                    <?= htmlspecialchars($login_error) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['unverified'])): ?>
                <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4 w-full max-w-sm">
                    <strong>Please verify your email first.</strong><br>
                    Check your inbox for the verification link, or 
                    <a href="verify.php" class="text-blue-600 font-medium">click here to resend</a>.
                </div>
            <?php endif; ?>

            <form method="POST" action="actions.php" class="w-full max-w-sm">
                <input type="hidden" name="action" value="universal_login">
                
                <div class="mb-4">
                    <label for="email" class="block text-gray-700 text-sm font-medium mb-2 text-left">Email:</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="Enter your email" 
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy focus:border-transparent">
                </div>

                <div class="mb-4">
                    <label for="password" class="block text-gray-700 text-sm font-medium mb-2 text-left">Password:</label>
                    <div class="relative">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               placeholder="Enter your password" 
                               required
                               class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy focus:border-transparent">
                        <i class="fas fa-eye absolute right-3 top-3 cursor-pointer text-gray-400 hover:text-gray-600" id="togglePassword"></i>
                    </div>
                </div>

                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center">
                        <input type="checkbox" id="remember" name="remember" class="mr-2">
                        <label for="remember" class="text-sm text-gray-600">Remember me</label>
                    </div>
                    <a href="update_password.php" class="text-sm text-navy hover:text-navy/80">Forgot password?</a>
                </div>

                <button type="submit" class="w-full bg-navy text-white py-2 px-4 rounded-lg hover:bg-navy/90 transition duration-200 font-medium">Sign In</button>
            </form>

            <div class="mt-4 w-full max-w-sm bg-blue-50 border border-blue-200 rounded-lg p-4 text-left">
                <div class="text-sm font-semibold text-blue-900 mb-1">SmartLab Access</div>
                <p class="text-sm text-blue-800 mb-3">RFID attendance, CO2 monitoring, and lab analytics are available in the SmartLab portal.</p>
                <a href="smart-lab/" class="inline-flex items-center justify-center w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-200 font-medium">
                    Open SmartLab
                </a>
            </div>

            <div class="mt-6">
                <a href="student/signup.php" class="text-navy hover:text-navy/80 text-sm">Don't have an account? Register as Student</a>
            </div>
        </div>
    </div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            togglePassword.classList.toggle('fa-eye');
            togglePassword.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>
