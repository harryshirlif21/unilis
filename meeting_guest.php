<?php
/**
 * Public guest entrance to a meeting.
 *
 * Reached with ?t=<guest_token>, the unguessable half of the link a lecturer
 * shares. No login: the whole point is that people who have no UNILIS account -
 * external learners, invited speakers, visitors - can get into a session.
 *
 * The token is the permission. That is a deliberate trade: it means anyone the
 * link is forwarded to can join, which is exactly how a meeting invitation
 * behaves, and it is why guest access is off by default, why the host can add a
 * passcode, and why regenerating the token revokes every copy at once.
 *
 * A guest is recorded before they enter, so the host's attendance list answers
 * "who was in the room" for guests as well as students.
 */

session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/meeting.php';
require_once __DIR__ . '/config/meeting_guests.php';

// A signed-in external learner is linked to their guest row, so the host can see
// which guests were registered learners. Loaded only if the /learn side exists.
$learner = null;
$learnConfig = __DIR__ . '/learn/config.php';
if (is_file($learnConfig)) {
    require_once $learnConfig;
    if (learn_schema_ready($conn)) {
        $learner = learn_current($conn);
    }
}

$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');

/**
 * Render a standalone message page and stop.
 */
function guest_stop(string $heading, string $message, string $icon = '🔒'): void
{
    guest_page_head();
    echo '<div class="card"><div class="glyph">' . $icon . '</div>'
       . '<h1>' . htmlspecialchars($heading, ENT_QUOTES) . '</h1>'
       . '<p class="subtitle">' . htmlspecialchars($message, ENT_QUOTES) . '</p>'
       . '<a class="btn btn-ghost" href="/">Go to UNILIS</a></div>';
    guest_page_foot();
    exit;
}

function guest_csrf_token(): string
{
    if (empty($_SESSION['meeting_guest_csrf'])) {
        $_SESSION['meeting_guest_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['meeting_guest_csrf'];
}

function guest_csrf_valid(?string $supplied): bool
{
    return !empty($_SESSION['meeting_guest_csrf'])
        && is_string($supplied)
        && hash_equals($_SESSION['meeting_guest_csrf'], $supplied);
}

if (!meeting_guests_ready($conn)) {
    guest_stop(
        'Guest access is not set up',
        'An administrator needs to run migrate_meeting_guests.php once, from the Database '
        . 'Migrations panel on the admin dashboard.',
        '🛠'
    );
}

$meeting = meeting_by_guest_token($conn, $token);

if ($meeting === null) {
    // The same answer whether the token never existed, was switched off, or was
    // regenerated. Distinguishing them would tell someone probing links which
    // guesses were close.
    guest_stop(
        'This link is not valid',
        'It may have been turned off or replaced. Ask whoever invited you for a current link.',
        '🔗'
    );
}

$meetingId = (int)$meeting['id'];
$needsPasscode = trim((string)($meeting['guest_passcode'] ?? '')) !== '';
$identity = meeting_guest_current($meetingId);
$errors = [];

// Rate limit passcode guesses per session. The link itself is the hard part, but
// a passcode is six characters and there is no reason to allow unlimited tries.
$attemptKey = 'meeting_guest_attempts_' . $meetingId;
$attempts = (int)($_SESSION[$attemptKey] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!guest_csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'That form expired. Please try again.';
    } elseif ($attempts >= 10) {
        $errors[] = 'Too many attempts. Close this tab and open the link again in a few minutes.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));

        if (mb_strlen($name) < 2) {
            $errors[] = 'Please give the name you want shown in the meeting.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'That email address does not look right. Leave it blank if you prefer.';
        }
        if (!meeting_guest_passcode_ok($meeting, $_POST['passcode'] ?? null)) {
            $_SESSION[$attemptKey] = $attempts + 1;
            $errors[] = 'That passcode does not match.';
        }

        if (!$errors) {
            unset($_SESSION[$attemptKey]);

            $identity = meeting_guest_admit(
                $conn,
                $meetingId,
                $name,
                $email !== '' ? $email : ($learner['email'] ?? null),
                $learner !== null ? (int)$learner['id'] : null
            );

            // Redirect so the launch card is a GET: a refresh on the card must
            // not re-post the form.
            header('Location: meeting_guest.php?t=' . urlencode($token));
            exit;
        }
    }
}

$joinable = isMeetingJoinable($meeting);
$startsAt = strtotime((string)$meeting['scheduled_time']);
$prefillName = $identity['name']
    ?? $learner['name']
    ?? ($_SESSION['user_name'] ?? '');
$prefillEmail = $learner['email'] ?? '';

// Only build the room URL once there is an identity: buildMeetingFrontendUrl()
// puts the display name and participant id in the query string, and there is
// nothing meaningful to put there before the guest has been admitted.
$roomUrl = null;
if ($identity !== null) {
    $roomUrl = buildMeetingFrontendUrl(
        'guest',
        $meeting,
        (int)$identity['participant_id'],
        (string)$identity['name'],
        getMeetingAppBaseUrl() . '/meeting_guest.php?t=' . urlencode($token)
    );
}

function guest_page_head(): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join a UNILIS meeting</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #4f46e5 0%, #0ea5e9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #1f2937;
        }
        .card {
            background: #fff;
            border-radius: 22px;
            padding: 34px;
            max-width: 520px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.28);
        }
        .glyph { font-size: 2.2rem; text-align: center; margin-bottom: 10px; }
        h1 { font-size: 1.4rem; text-align: center; margin-bottom: 6px; }
        .subtitle { color: #6b7280; font-size: 0.9rem; text-align: center; margin-bottom: 22px; }
        .info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px 18px;
            margin-bottom: 20px;
        }
        .info .row { display: flex; justify-content: space-between; gap: 14px; padding: 5px 0; font-size: 0.88rem; }
        .info .row .label { color: #6b7280; }
        .info .row .value { font-weight: 600; text-align: right; }
        .field { margin-bottom: 15px; }
        .field label { display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 5px; }
        .field input {
            width: 100%;
            padding: 11px 13px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font: inherit;
            font-size: 0.92rem;
        }
        .field input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,0.15); }
        .field .hint { display: block; color: #6b7280; font-size: 0.78rem; margin-top: 4px; }
        .field input.code { text-transform: uppercase; letter-spacing: 4px; font-weight: 700; text-align: center; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            width: 100%;
            padding: 13px 22px;
            border: none;
            border-radius: 999px;
            font: inherit;
            font-size: 0.98rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            margin-bottom: 10px;
        }
        .btn-primary { background: #4f46e5; color: #fff; }
        .btn-primary:hover { background: #4338ca; }
        .btn-ghost { background: transparent; color: #6b7280; font-size: 0.88rem; }
        .alert {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 0.86rem;
            margin-bottom: 18px;
        }
        .alert ul { margin: 0; padding-left: 18px; }
        .notice {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 0.86rem;
            margin-bottom: 18px;
        }
        .badge {
            display: inline-block;
            background: #dcfce7;
            color: #166534;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 2px 9px;
            border-radius: 999px;
        }
        .foot { text-align: center; font-size: 0.76rem; color: #9ca3af; margin-top: 16px; }
        .foot a { color: #6b7280; }
    </style>
</head>
<body>
    <?php
}

function guest_page_foot(): void
{
    ?>
</body>
</html>
    <?php
}

guest_page_head();
?>
<div class="card">
    <div class="glyph">🎥</div>
    <h1><?= htmlspecialchars((string)$meeting['title'], ENT_QUOTES) ?></h1>
    <p class="subtitle">
        <?= $identity !== null ? 'You are on the guest list for this session.' : 'You have been invited to join this session as a guest.' ?>
    </p>

    <?php if ($errors): ?>
        <div class="alert"><ul>
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error, ENT_QUOTES) ?></li>
            <?php endforeach; ?>
        </ul></div>
    <?php endif; ?>

    <div class="info">
        <?php if (!empty($meeting['unit_name'])): ?>
            <div class="row"><span class="label">Subject</span><span class="value"><?= htmlspecialchars((string)$meeting['unit_name'], ENT_QUOTES) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($meeting['lecturer_name'])): ?>
            <div class="row"><span class="label">Host</span><span class="value"><?= htmlspecialchars((string)$meeting['lecturer_name'], ENT_QUOTES) ?></span></div>
        <?php endif; ?>
        <div class="row">
            <span class="label">Starts</span>
            <span class="value">
                <?= $startsAt ? htmlspecialchars(date('d M Y, g:i A', $startsAt), ENT_QUOTES) : 'Not scheduled' ?>
                <?php if ($joinable && $startsAt && $startsAt <= time()): ?>
                    <span class="badge">LIVE</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="row"><span class="label">Length</span><span class="value"><?= (int)$meeting['duration'] ?> min</span></div>
        <div class="row"><span class="label">You join as</span><span class="value">Guest</span></div>
    </div>

    <?php if (!$joinable): ?>
        <div class="notice">
            This session has finished. The link stays valid for the host's records, but there is
            nothing to join.
        </div>
        <a class="btn btn-ghost" href="/">Go to UNILIS</a>

    <?php elseif ($identity !== null && $roomUrl !== null): ?>
        <a class="btn btn-primary" href="<?= htmlspecialchars($roomUrl, ENT_QUOTES) ?>">
            🚀 Join as <?= htmlspecialchars((string)$identity['name'], ENT_QUOTES) ?>
        </a>
        <p class="foot">
            You can join without a camera or microphone — the room will ask, and "join anyway" works.
        </p>

    <?php else: ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(guest_csrf_token(), ENT_QUOTES) ?>">
            <input type="hidden" name="t" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">

            <div class="field">
                <label for="name">Your name</label>
                <input id="name" name="name" type="text" required maxlength="120" autocomplete="name"
                       value="<?= htmlspecialchars((string)$prefillName, ENT_QUOTES) ?>"
                       placeholder="How you want to appear in the meeting">
            </div>

            <div class="field">
                <label for="email">Email <span style="font-weight:400; color:#9ca3af;">(optional)</span></label>
                <input id="email" name="email" type="email" maxlength="190" autocomplete="email"
                       value="<?= htmlspecialchars((string)$prefillEmail, ENT_QUOTES) ?>">
                <span class="hint">Only used so the host knows who attended.</span>
            </div>

            <?php if ($needsPasscode): ?>
                <div class="field">
                    <label for="passcode">Passcode</label>
                    <input id="passcode" name="passcode" class="code" type="text" required maxlength="32"
                           autocomplete="off" autocapitalize="characters" spellcheck="false">
                    <span class="hint">The host will have given you this with the link.</span>
                </div>
            <?php endif; ?>

            <button class="btn btn-primary" type="submit">Continue</button>
        </form>

        <p class="foot">
            Your name is shown to everyone in the meeting and recorded on the host's attendance list.
            <?php if ($learner !== null): ?>
                <br>Signed in to UNILIS Learning as <?= htmlspecialchars((string)$learner['name'], ENT_QUOTES) ?>.
            <?php endif; ?>
        </p>
    <?php endif; ?>
</div>
<?php
guest_page_foot();
