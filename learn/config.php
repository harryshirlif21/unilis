<?php
/**
 * learn/config.php
 *
 * Bootstrap for the public learning side of the ICLM: the catalogue that people
 * who are not enrolled students or staff can work through.
 */

$learnRoot = dirname(__DIR__);

if (!is_file($learnRoot . '/config/db.php')) {
    throw new Exception('learn/config.php could not locate config/db.php');
}
require_once $learnRoot . '/config/db.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', $learnRoot);
}

define('LEARN_VERSION', '1.0.0');

// How long a verification link stays usable. Long enough to survive a spam
// folder and a night's sleep, short enough that a leaked inbox is not a
// permanent key to the account.
define('LEARN_VERIFY_TTL_HOURS', 48);

// Minimum password length. Deliberately a length floor rather than a character
// class rule: length is what actually resists guessing, and composition rules
// mostly push people towards Password1!.
define('LEARN_MIN_PASSWORD', 10);

// A learner session is separate from the staff/student session. It never sets
// $_SESSION['user_role'], so no existing role check anywhere in the LMS can
// mistake a learner for a student.
define('LEARN_SESSION_KEY', 'learner_id');

require_once __DIR__ . '/includes/learner_auth.php';
require_once __DIR__ . '/includes/learner_mail.php';
