<?php
/**
 * Registration, verification and sessions for external learners.
 *
 * A learner is never a `students` row and never gets $_SESSION['user_role'].
 * That separation is the whole point of the external_learners table: role checks
 * across the LMS test for 'student', 'lecturer' or 'admin', and a learner must
 * fail all three so they cannot reach a single internal page by accident.
 */

/**
 * Whether the schema for this feature has been created.
 */
function learn_schema_ready(mysqli $conn): bool
{
    foreach (['external_learners', 'public_courses', 'certificates'] as $table) {
        $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
        $exists = $result->num_rows > 0;
        $result->free();
        if (!$exists) {
            return false;
        }
    }

    return true;
}

/**
 * The signed-in learner, or null.
 */
function learn_current(mysqli $conn): ?array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $id = (int)($_SESSION[LEARN_SESSION_KEY] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT id, name, email, is_verified, status
        FROM external_learners WHERE id = ? LIMIT 1
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $learner = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Read the row rather than trusting the session, so suspending an account
    // takes effect on the next request instead of whenever they log out.
    if (!$learner || $learner['status'] !== 'active') {
        learn_logout();
        return null;
    }

    return [
        'id' => (int)$learner['id'],
        'name' => (string)$learner['name'],
        'email' => (string)$learner['email'],
        'is_verified' => (int)$learner['is_verified'] === 1,
    ];
}

/**
 * Send the caller to the login page unless a verified learner is signed in.
 */
function learn_require_login(mysqli $conn): array
{
    $learner = learn_current($conn);

    if ($learner === null) {
        header('Location: /learn/login.php');
        exit;
    }
    if (!$learner['is_verified']) {
        header('Location: /learn/verify.php?pending=1');
        exit;
    }

    return $learner;
}

/**
 * CSRF token for the learner forms, minted on demand.
 */
function learn_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['learn_csrf'])) {
        $_SESSION['learn_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['learn_csrf'];
}

function learn_csrf_valid(?string $token): bool
{
    return !empty($_SESSION['learn_csrf'])
        && is_string($token)
        && hash_equals($_SESSION['learn_csrf'], $token);
}

/**
 * Validate a registration and create the account.
 *
 * Returns ['ok' => true, 'learner_id' => int, 'token' => string]
 * or ['ok' => false, 'errors' => string[]].
 *
 * The caller mails the token; this function does not, so registration stays
 * testable without a mail server.
 */
function learn_register(mysqli $conn, array $input): array
{
    $name = trim((string)($input['name'] ?? ''));
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $password = (string)($input['password'] ?? '');
    $confirm = (string)($input['password_confirm'] ?? '');
    $phone = trim((string)($input['phone'] ?? ''));
    $country = trim((string)($input['country'] ?? ''));
    $organisation = trim((string)($input['organisation'] ?? ''));

    $errors = [];

    if ($name === '' || mb_strlen($name) < 2) {
        $errors[] = 'Please give your full name.';
    }
    if (mb_strlen($name) > 120) {
        $errors[] = 'That name is too long.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please give a valid email address.';
    }
    if (mb_strlen($email) > 190) {
        $errors[] = 'That email address is too long.';
    }
    if (mb_strlen($password) < LEARN_MIN_PASSWORD) {
        $errors[] = 'Your password needs at least ' . LEARN_MIN_PASSWORD . ' characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }

    // An address already registered here.
    $stmt = $conn->prepare("SELECT id FROM external_learners WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $taken = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($taken) {
        return ['ok' => false, 'errors' => ['An account already exists for that email address.']];
    }

    // An address belonging to a student or lecturer. Registering it here would
    // give one person two identities with the same email, and the verification
    // mail would be indistinguishable from the internal one.
    foreach (['students', 'lecturers'] as $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        $present = $check->num_rows > 0;
        $check->free();
        if (!$present) {
            continue;
        }

        $stmt = $conn->prepare("SELECT id FROM `$table` WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $internal = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($internal) {
            return [
                'ok' => false,
                'errors' => ['That email address already belongs to a UNILIS account. Sign in at /login.php instead.'],
            ];
        }
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + LEARN_VERIFY_TTL_HOURS * 3600);

    // bind_param takes its arguments by reference, so these have to be real
    // variables rather than ternaries evaluated in the call.
    $phoneOrNull = $phone !== '' ? $phone : null;
    $countryOrNull = $country !== '' ? $country : null;
    $organisationOrNull = $organisation !== '' ? $organisation : null;

    $stmt = $conn->prepare("
        INSERT INTO external_learners
            (name, email, password, phone, country, organisation,
             is_verified, verification_code, token_expires_at)
        VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)
    ");
    $stmt->bind_param(
        'ssssssss',
        $name, $email, $hash,
        $phoneOrNull, $countryOrNull, $organisationOrNull,
        $token, $expires
    );
    $stmt->execute();
    $learnerId = (int)$conn->insert_id;
    $stmt->close();

    return ['ok' => true, 'learner_id' => $learnerId, 'token' => $token, 'name' => $name, 'email' => $email];
}

/**
 * Consume a verification token.
 *
 * Returns ['ok' => bool, 'message' => string, 'already' => bool].
 */
function learn_verify_token(mysqli $conn, string $token): array
{
    if ($token === '') {
        return ['ok' => false, 'already' => false, 'message' => 'No verification code was supplied.'];
    }

    $stmt = $conn->prepare("
        SELECT id, name, is_verified, token_expires_at
        FROM external_learners WHERE verification_code = ? LIMIT 1
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $learner = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$learner) {
        return ['ok' => false, 'already' => false, 'message' => 'That verification link is not valid.'];
    }
    if ((int)$learner['is_verified'] === 1) {
        return ['ok' => true, 'already' => true, 'message' => 'This account is already verified. You can sign in.'];
    }
    if ($learner['token_expires_at'] !== null && strtotime($learner['token_expires_at']) < time()) {
        return ['ok' => false, 'already' => false, 'message' => 'That verification link has expired. Request a new one below.'];
    }

    // Clear the code as it is spent, so the link cannot be replayed.
    $stmt = $conn->prepare("
        UPDATE external_learners
        SET is_verified = 1, verification_code = NULL, token_expires_at = NULL
        WHERE id = ?
    ");
    $stmt->bind_param('i', $learner['id']);
    $stmt->execute();
    $stmt->close();

    return ['ok' => true, 'already' => false, 'message' => 'Your email is verified. You can sign in now.'];
}

/**
 * Issue a fresh verification token for an address.
 *
 * Returns the token, or null when there is nothing to send. The caller must not
 * reveal which of those happened: a different response for a registered and an
 * unregistered address turns this into an account-existence oracle.
 */
function learn_reissue_verification(mysqli $conn, string $email): ?array
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT id, name, is_verified FROM external_learners WHERE email = ? LIMIT 1
    ");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $learner = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$learner || (int)$learner['is_verified'] === 1) {
        return null;
    }

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + LEARN_VERIFY_TTL_HOURS * 3600);

    $stmt = $conn->prepare("
        UPDATE external_learners SET verification_code = ?, token_expires_at = ? WHERE id = ?
    ");
    $stmt->bind_param('ssi', $token, $expires, $learner['id']);
    $stmt->execute();
    $stmt->close();

    return ['token' => $token, 'name' => (string)$learner['name'], 'email' => $email];
}

/**
 * Sign in.
 *
 * Returns ['ok' => true, 'learner' => array] or ['ok' => false, 'error' => string,
 * 'unverified' => bool].
 */
function learn_login(mysqli $conn, string $email, string $password): array
{
    $email = strtolower(trim($email));

    $stmt = $conn->prepare("
        SELECT id, name, email, password, is_verified, status
        FROM external_learners WHERE email = ? LIMIT 1
    ");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $learner = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // One message for a missing account and a wrong password, so this cannot be
    // used to enumerate registered addresses.
    $generic = ['ok' => false, 'unverified' => false, 'error' => 'Those details do not match an account.'];

    if (!$learner) {
        // Still spend the time a hash comparison would, so response timing does
        // not distinguish a missing account from a wrong password.
        password_verify($password, '$2y$10$usesomesillystringforsalt0123456789abcdefghijklmnopqrstuv');
        return $generic;
    }
    if (!password_verify($password, (string)$learner['password'])) {
        return $generic;
    }
    if ($learner['status'] !== 'active') {
        return ['ok' => false, 'unverified' => false, 'error' => 'This account has been suspended.'];
    }
    if ((int)$learner['is_verified'] !== 1) {
        return ['ok' => false, 'unverified' => true, 'error' => 'Please verify your email address first.'];
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // New id on privilege change, so a session fixed before login is useless.
    session_regenerate_id(true);
    $_SESSION[LEARN_SESSION_KEY] = (int)$learner['id'];
    $_SESSION['learner_name'] = (string)$learner['name'];
    $_SESSION['learner_email'] = (string)$learner['email'];

    $stmt = $conn->prepare("UPDATE external_learners SET last_login_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $learner['id']);
    $stmt->execute();
    $stmt->close();

    return [
        'ok' => true,
        'learner' => [
            'id' => (int)$learner['id'],
            'name' => (string)$learner['name'],
            'email' => (string)$learner['email'],
        ],
    ];
}

/**
 * Drop the learner session without disturbing a staff/student session that may
 * share the same PHP session.
 */
function learn_logout(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION[LEARN_SESSION_KEY], $_SESSION['learner_name'], $_SESSION['learner_email']);
}
