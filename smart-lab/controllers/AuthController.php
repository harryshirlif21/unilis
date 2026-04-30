<?php
require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../utils/helpers.php';
require_once __DIR__.'/../utils/Validator.php';

class AuthController {

    public function index($param = null) { $this->login(); }

    // ── LOGIN ──────────────────────────────────────────────────
    public function login($param = null) {
        if (Auth::check()) { redirect('dashboard'); }

        $error = '';
        $mfaRequired = false;
        $availableMethods = Auth::getAuthMethods();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $method = sanitize($_POST['auth_method'] ?? 'password');

            if ($method === 'password') {
                $input = sanitize($_POST['reg_number'] ?? '');
                $pass = $_POST['password'] ?? '';

                if (empty($input)) {
                    $error = 'Please enter your registration number or email.';
                } elseif (empty($pass)) {
                    $error = 'Please enter your password.';
                } else {
                    // Detect if input is email or registration number
                    $isEmail = filter_var($input, FILTER_VALIDATE_EMAIL);
                    $loginSuccess = false;
                    
                    if ($isEmail) {
                        // Try email-based login for admin/technician/lecturer
                        $loginSuccess = Auth::loginByEmail($input, $pass);
                    } else {
                        // Try reg_number-based login for students
                        $loginSuccess = Auth::login($input, $pass);
                    }
                    
                    if ($loginSuccess) {
                        // Check if MFA is required
                        if (Auth::requireMultiFactor()) {
                            $mfaCode = Auth::initiateMultiFactor(Auth::id());
                            $mfaRequired = true;
                        } else {
                            logActivity(Auth::id(), 'login_password', 'auth');
                            redirect('dashboard');
                        }
                    } else {
                        $error = 'Invalid ' . ($isEmail ? 'email' : 'registration number') . ' or password.';
                    }
                }

            } elseif ($method === 'biometric') {
                $biometricData = $_POST['biometric_data'] ?? '';
                
                if (empty($biometricData)) {
                    $error = 'Biometric authentication data is required.';
                } else {
                    $biometricHash = hash('sha256', $biometricData . BIOMETRIC_SALT);
                    if (Auth::loginBiometric($biometricHash)) {
                        if (Auth::requireMultiFactor()) {
                            $mfaCode = Auth::initiateMultiFactor(Auth::id());
                            $mfaRequired = true;
                        } else {
                            logActivity(Auth::id(), 'login_biometric', 'auth');
                            redirect('dashboard');
                        }
                    } else {
                        $error = 'Biometric authentication failed.';
                    }
                }

            } elseif ($method === 'qr') {
                $qrToken = sanitize($_POST['qr_token'] ?? '');
                $sessionId = sanitize($_POST['session_id'] ?? '');
                
                if (empty($qrToken) || empty($sessionId)) {
                    $error = 'QR code and session ID are required.';
                } elseif (Auth::loginByQR($qrToken, $sessionId)) {
                    logActivity(Auth::id(), 'login_qr', 'auth');
                    redirect('dashboard');
                } else {
                    $error = 'Invalid QR code or session.';
                }

            } elseif ($method === 'code') {
                $action = sanitize($_POST['action'] ?? '');
                
                if ($action === 'send_otp') {
                    // Step 1: Send OTP to email
                    $regNumber = sanitize($_POST['reg_number'] ?? '');
                    
                    if (empty($regNumber)) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => 'Registration number is required.']);
                        return;
                    }
                    
                    // Look up user by registration number
                    $db = getDB();
                    $stmt = $db->prepare("SELECT id, full_name, email FROM users WHERE reg_number = ? AND is_active = 1 LIMIT 1");
                    $stmt->execute([$regNumber]);
                    $user = $stmt->fetch();
                    
                    if (!$user) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => 'No account found with that registration number.']);
                        return;
                    }
                    
                    // Generate 6-digit OTP
                    $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    
                    // Delete old unused OTPs for this user
                    $db->prepare("DELETE FROM otp_codes WHERE user_id = ? AND used = 0")->execute([$user['id']]);
                    
                    // Insert new OTP
                    $otpId = bin2hex(random_bytes(16));
                    $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes
                    $stmt = $db->prepare("INSERT INTO otp_codes (id, user_id, code, type, expires_at) VALUES (?, ?, ?, 'auth_code', ?)");
                    $stmt->execute([$otpId, $user['id'], $otpCode, $expiresAt]);
                    
                    // Send OTP email
                    require_once __DIR__.'/../utils/Mailer.php';
                    $emailSent = Mailer::sendOTP($user['email'], $user['full_name'], $otpCode);
                    
                    if ($emailSent) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'masked_email' => Mailer::maskEmail($user['email']),
                            'user_id' => $user['id']
                        ]);
                        return;
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => 'Failed to send OTP email. Please try again.']);
                        return;
                    }
                    
                } elseif ($action === 'verify_otp') {
                    // Step 2: Verify OTP and login
                    $userId = sanitize($_POST['user_id'] ?? '');
                    $otpCode = sanitize($_POST['otp_code'] ?? '');
                    
                    if (empty($userId) || empty($otpCode)) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => 'Invalid request.']);
                        return;
                    }
                    
                    // Verify OTP
                    $db = getDB();
                    $stmt = $db->prepare("SELECT * FROM otp_codes WHERE user_id = ? AND code = ? AND type = 'auth_code' AND used = 0 AND expires_at > NOW() LIMIT 1");
                    $stmt->execute([$userId, $otpCode]);
                    $otpRecord = $stmt->fetch();
                    
                    if (!$otpRecord) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => 'Invalid or expired OTP code.']);
                        return;
                    }
                    
                    // Mark OTP as used
                    $db->prepare("UPDATE otp_codes SET used = 1 WHERE id = ?")->execute([$otpRecord['id']]);
                    
                    // Get user and start session
                    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        // Start session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['user_name'] = $user['full_name'];
                        $_SESSION['lab_id'] = $user['lab_id'] ?? '';
                        $_SESSION['auth_method'] = 'auth_code_otp';
                        
                        logActivity($user['id'], 'login_auth_code_otp', 'auth');
                        
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'redirect' => APP_URL . '/dashboard']);
                        return;
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => 'User account not found.']);
                        return;
                    }
                    
                } elseif ($action === 'verify_session_code') {
                    // Original lab session code functionality
                    $code = strtoupper(implode('', $_POST['code'] ?? []));
                    if (strlen($code) !== 6) {
                        $error = 'Please enter all 6 characters of your code.';
                    } else {
                        $result = Auth::loginByCode($code);
                        if ($result['success']) {
                            // Store session data for student selection
                            $_SESSION['pending_session'] = $result['session'];
                            $_SESSION['available_students'] = $result['students'];
                            renderView('auth/select_student', [
                                'session' => $result['session'],
                                'students' => $result['students']
                            ]);
                            return;
                        } else {
                            $error = $result['message'];
                        }
                    }
                } else {
                    // Default to session code verification if no action specified
                    $code = strtoupper(implode('', $_POST['code'] ?? []));
                    if (strlen($code) !== 6) {
                        $error = 'Please enter all 6 characters of your code.';
                    } else {
                        $result = Auth::loginByCode($code);
                        if ($result['success']) {
                            // Store session data for student selection
                            $_SESSION['pending_session'] = $result['session'];
                            $_SESSION['available_students'] = $result['students'];
                            renderView('auth/select_student', [
                                'session' => $result['session'],
                                'students' => $result['students']
                            ]);
                            return;
                        } else {
                            $error = $result['message'];
                        }
                    }
                }
            }

            // Handle MFA verification
            if ($mfaRequired && isset($_POST['mfa_code'])) {
                $mfaCode = strtoupper($_POST['mfa_code'] ?? '');
                if (Auth::verifyMultiFactor($mfaCode)) {
                    logActivity(Auth::id(), 'login_mfa_verified', 'auth');
                    unset($_SESSION['mfa_code'], $_SESSION['mfa_expires']);
                    redirect('dashboard');
                } else {
                    $error = 'Invalid verification code.';
                }
            }
        }

        renderView('auth/login', [
            'error' => $error,
            'mfaRequired' => $mfaRequired,
            'availableMethods' => $availableMethods
        ]);
    }

    // ── SELECT STUDENT FOR SESSION ───────────────────────────────────
    public function selectStudent($param = null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $studentId = sanitize($_POST['student_id'] ?? '');
            $sessionId = $_SESSION['pending_session']['id'] ?? '';
            
            if (empty($studentId) || empty($sessionId)) {
                $error = 'Invalid selection.';
                renderView('auth/select_student', [
                    'session' => $_SESSION['pending_session'],
                    'students' => $_SESSION['available_students'],
                    'error' => $error
                ]);
                return;
            }
            
            if (Auth::selectStudentForSession($studentId, $sessionId)) {
                logActivity(Auth::id(), 'login_code_selected', 'auth');
                unset($_SESSION['pending_session'], $_SESSION['available_students']);
                redirect('dashboard');
            } else {
                $error = 'Failed to select student. Session may have expired.';
                renderView('auth/select_student', [
                    'session' => $_SESSION['pending_session'],
                    'students' => $_SESSION['available_students'],
                    'error' => $error
                ]);
            }
        } else {
            redirect('auth/login');
        }
    }

    // ── REGISTER ───────────────────────────────────────────────
    public function register($param = null) {
        if (Auth::check()) { redirect('dashboard'); }

        $error   = '';
        $success = '';
        $db      = getDB();

        // Load labs for dropdown
        $labs = $db->query("SELECT id, name, lab_code FROM labs WHERE is_active = 1 ORDER BY name")->fetchAll();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Collect inputs
            $full_name  = sanitize($_POST['full_name']  ?? '');
            $reg_number = sanitize($_POST['reg_number'] ?? '');
            $email      = sanitize($_POST['email']      ?? '');
            $role       = sanitize($_POST['role']       ?? '');
            $department = sanitize($_POST['department'] ?? '');
            $lab_id     = sanitize($_POST['lab_id']     ?? '') ?: null;
            $password   = $_POST['password']         ?? '';
            $confirm    = $_POST['password_confirm'] ?? '';

            // Validate
            $v = new Validator();
            $v->required('Full name',  $full_name)
              ->required('Reg number', $reg_number)
              ->required('Email',      $email)
              ->email('Email',         $email)
              
              ->required('Password',   $password)
              ->minLength('Password',  $password, 8);

            if (!$v->passes()) {
                $error = implode(' ', $v->errors());
            } elseif ($password !== $confirm) {
                $error = 'Passwords do not match.';
            } elseif (!in_array($role, ['student','lecturer','technician','admin'])) {
                $error = 'Invalid role selected.';
            } else {
                // Check duplicates
                $chk = $db->prepare(
                    "SELECT id FROM users WHERE reg_number = ? OR email = ? LIMIT 1"
                );
                $chk->execute([$reg_number, $email]);
                if ($chk->fetch()) {
                    $error = 'Registration number or email already exists.';
                } else {
                    // Insert user
                    $id   = bin2hex(random_bytes(16));
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $ins  = $db->prepare(
                        "INSERT INTO users
                         (id, reg_number, full_name, email, password, role, department, lab_id, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)"
                    );
                    $ins->execute([
                        $id, $reg_number, $full_name,
                        $email, $hash, $role, $department, $lab_id
                    ]);

                    logActivity($id, 'user_registered', 'auth');
                    $success = "Account created for $full_name ($reg_number). You can now log in.";
                }
            }
        }

        renderView('auth/register', [
            'error'   => $error,
            'success' => $success,
            'labs'    => $labs,
        ]);
    }

    // ── REGISTER STAFF ───────────────────────────────────────────────
    public function registerStaff($param = null) {
        if (Auth::check()) { redirect('dashboard'); }

        $error   = '';
        $success = '';
        $db      = getDB();

        // Load labs for dropdown
        $labs = $db->query("SELECT id, name, lab_code FROM labs WHERE is_active = 1 ORDER BY name")->fetchAll();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validate staff registration key
            $staffKey = $_POST['staff_key'] ?? '';
            if ($staffKey !== STAFF_REGISTRATION_KEY) {
                $error = 'Invalid staff registration key. Please contact your administrator.';
            } else {
                // Get form data
                $fullName     = sanitize($_POST['full_name'] ?? '');
                $regNumber    = sanitize($_POST['reg_number'] ?? '');
                $email        = sanitize($_POST['email'] ?? '');
                $role         = sanitize($_POST['role'] ?? '');
                $department   = sanitize($_POST['department'] ?? '');
                $labId        = sanitize($_POST['lab_id'] ?? '');
                $password     = $_POST['password'] ?? '';
                $passwordConf = $_POST['password_confirm'] ?? '';

                // Validate role (only allow admin, lecturer, technician)
                $allowedRoles = ['admin', 'lecturer', 'technician'];
                if (!in_array($role, $allowedRoles)) {
                    $error = 'Invalid role selected.';
                } elseif (empty($fullName) || empty($regNumber) || empty($email) || empty($role) || empty($department)) {
                    $error = 'All required fields must be filled.';
                } elseif ($role !== 'admin' && empty($labId)) {
                    $error = 'Laboratory assignment is required for lecturers and technicians.';
                } elseif (strlen($password) < 8) {
                    $error = 'Password must be at least 8 characters long.';
                } elseif ($password !== $passwordConf) {
                    $error = 'Passwords do not match.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Please enter a valid email address.';
                } else {
                    // Check for duplicates
                    $checkStmt = $db->prepare("SELECT id FROM users WHERE reg_number = ? OR email = ? LIMIT 1");
                    $checkStmt->execute([$regNumber, $email]);
                    
                    if ($checkStmt->fetch()) {
                        $error = 'A user with this registration number or email already exists.';
                    } else {
                        // Create staff user
                        $id = bin2hex(random_bytes(16));
                        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                        
                        $insertStmt = $db->prepare("
                            INSERT INTO users (id, reg_number, full_name, email, password, role, department, lab_id, is_active, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                        ");
                        
                        $params = [$id, $regNumber, $fullName, $email, $passwordHash, $role, $department];
                        if ($role !== 'admin') {
                            $params[] = $labId;
                        } else {
                            $params[] = null;
                        }
                        
                        if ($insertStmt->execute($params)) {
                            logActivity($id, 'staff_registered', 'auth');
                            $success = "Staff account created for $fullName ($regNumber). You can now log in.";
                        } else {
                            $error = 'Failed to create staff account. Please try again.';
                        }
                    }
                }
            }
        }

        renderView('auth/register_staff', [
            'error'   => $error,
            'success' => $success,
            'labs'    => $labs,
        ]);
    }

    // ── LOGOUT ─────────────────────────────────────────────────
    public function logout($param = null) {
        if (Auth::check()) {
            logActivity(Auth::id(), 'logout', 'auth');
        }
        Auth::logout();
    }
}
