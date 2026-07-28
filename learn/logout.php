<?php
/**
 * Sign a learner out.
 *
 * Only the learner keys are cleared, never the whole session: a member of staff
 * testing the public catalogue in the same browser would otherwise be signed out
 * of the LMS too.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';

learn_logout();

header('Location: /learn/');
exit;
