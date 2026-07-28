<?php
/**
 * Guest access for one meeting: turn it on, share the link, see who used it.
 *
 * A meeting is normally reachable only by its lecturer and the students enrolled
 * in its unit. This page is where a lecturer decides to open a session wider
 * than that - to external learners working through /learn, an invited speaker, or
 * anyone else with no UNILIS account.
 *
 * Only the lecturer who owns the meeting can reach it. Every write goes through
 * config/meeting_guests.php, whose statements all carry `AND lecturer_id = ?`,
 * so ownership is enforced in the query rather than only in the page.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/meeting.php';
require_once __DIR__ . '/../config/meeting_guests.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'lecturer') {
    header('Location: ../login.php');
    exit;
}

$lecturerId = (int)$_SESSION['user_id'];
$meetingId = (int)($_GET['meeting_id'] ?? $_POST['meeting_id'] ?? 0);

function access_e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function access_flash(string $message, string $kind = 'success'): void
{
    $_SESSION['meeting_access_flash'] = ['message' => $message, 'kind' => $kind];
}

function access_csrf(): string
{
    if (empty($_SESSION['meeting_access_csrf'])) {
        $_SESSION['meeting_access_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['meeting_access_csrf'];
}

function access_csrf_valid(?string $supplied): bool
{
    return !empty($_SESSION['meeting_access_csrf'])
        && is_string($supplied)
        && hash_equals($_SESSION['meeting_access_csrf'], $supplied);
}

/**
 * Page chrome, sharing the lecturer tool stylesheet.
 */
function access_head(string $title): void
{
    $stamp = @filemtime(__DIR__ . '/css/studio.css');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= access_e($title) ?> — UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/studio.css?v=<?= (int)$stamp ?>">
</head>
<body>
<header class="st-header">
    <a class="st-brand" href="dashboard.php?tab=meetings">
        <i class="fas fa-user-group"></i>
        <span>Meeting <strong>guests</strong></span>
    </a>
    <nav class="st-nav">
        <a class="st-btn st-btn-ghost" href="dashboard.php?tab=meetings">
            <i class="fas fa-arrow-left"></i> Meetings
        </a>
    </nav>
</header>
<main class="st-main">
    <?php
}

function access_foot(): void
{
    ?>
</main>
</body>
</html>
    <?php
}

function access_stop(string $heading, string $body): void
{
    access_head($heading);
    echo '<div class="st-card"><h2>' . access_e($heading) . '</h2>'
       . '<p class="st-sub">' . $body . '</p>'
       . '<div class="st-actions" style="margin-top:14px;">'
       . '<a class="st-btn st-btn-ghost" href="dashboard.php?tab=meetings">Back to meetings</a></div></div>';
    access_foot();
    exit;
}

if (!meeting_guests_ready($conn)) {
    access_stop(
        'Guest access is not set up yet',
        'Guest links live in columns that one migration adds. An administrator needs to run '
        . '<code>migrate_meeting_guests.php</code> once, from the Database Migrations panel on the '
        . 'admin dashboard.'
    );
}

// The meeting, and the ownership check in one query.
$stmt = $conn->prepare('
    SELECT m.*, u.name AS unit_name, l.name AS lecturer_name
    FROM meetings m
    LEFT JOIN units u ON m.unit_id = u.id
    LEFT JOIN lecturers l ON m.lecturer_id = l.id
    WHERE m.id = ? AND m.lecturer_id = ?
    LIMIT 1
');
$stmt->bind_param('ii', $meetingId, $lecturerId);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$meeting) {
    access_stop('Meeting not found', 'It may have been deleted, or it belongs to another lecturer.');
}

// ── Actions ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect = 'meeting_access.php?meeting_id=' . $meetingId;

    if (!access_csrf_valid($_POST['csrf_token'] ?? null)) {
        access_flash('That form expired. Please try again.', 'error');
        header('Location: ' . $redirect);
        exit;
    }

    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'enable') {
            $listed = !empty($_POST['guest_listed']);
            $mode = (string)($_POST['passcode_mode'] ?? 'keep');

            // Three distinct intentions, and 'keep' is not the same as an empty
            // field: saving the form with the passcode box untouched must not
            // silently remove the passcode.
            if ($mode === 'generate') {
                $passcode = meeting_guest_new_passcode();
            } elseif ($mode === 'none') {
                $passcode = '';
            } elseif ($mode === 'set') {
                $passcode = trim((string)($_POST['passcode'] ?? ''));
                if ($passcode === '') {
                    access_flash('Type a passcode, or choose one of the other options.', 'error');
                    header('Location: ' . $redirect);
                    exit;
                }
                if (mb_strlen($passcode) > 32) {
                    access_flash('A passcode can be at most 32 characters.', 'error');
                    header('Location: ' . $redirect);
                    exit;
                }
            } else {
                $passcode = null;
            }

            $wasOn = (int)$meeting['guest_access'] === 1;
            meeting_guest_enable($conn, $meetingId, $lecturerId, $listed, $passcode);
            access_flash($wasOn ? 'Guest access updated.' : 'Guest access is on. Share the link below.');

        } elseif ($action === 'disable') {
            meeting_guest_disable($conn, $meetingId, $lecturerId);
            access_flash(
                'Guest access is off, and the link has been discarded. Turning it back on issues a '
                . 'new one, so anybody still holding the old link cannot get in.',
                'info'
            );

        } elseif ($action === 'rotate') {
            meeting_guest_rotate($conn, $meetingId, $lecturerId);
            access_flash('A new link has been issued. Every copy of the previous one is now dead.');

        } else {
            access_flash('Unknown action.', 'error');
        }
    } catch (Throwable $e) {
        error_log('meeting_access ' . $action . ': ' . $e->getMessage());
        access_flash('That did not work. Please try again.', 'error');
    }

    header('Location: ' . $redirect);
    exit;
}

$guestAccess = (int)$meeting['guest_access'] === 1;
$guestListed = (int)$meeting['guest_listed'] === 1;
$guestToken = (string)($meeting['guest_token'] ?? '');
$passcode = (string)($meeting['guest_passcode'] ?? '');
$guestUrl = $guestAccess && $guestToken !== '' ? getMeetingGuestUrl($guestToken) : null;
$guests = meeting_guest_list($conn, $meetingId);
$csrf = access_csrf();

$flash = $_SESSION['meeting_access_flash'] ?? null;
unset($_SESSION['meeting_access_flash']);

access_head('Guests · ' . (string)$meeting['title']);
?>
<div class="st-page-head">
    <div>
        <h1><?= access_e((string)$meeting['title']) ?></h1>
        <p class="st-sub">
            <?= access_e((string)($meeting['unit_name'] ?? 'No unit')) ?>
            · <?= access_e(date('d M Y, g:i A', strtotime((string)$meeting['scheduled_time']))) ?>
            · <?= (int)$meeting['duration'] ?> min
        </p>
    </div>
    <div class="st-actions">
        <a class="st-btn st-btn-ghost" href="meeting_host.php?meeting_id=<?= $meetingId ?>">
            <i class="fas fa-video"></i> Host the meeting
        </a>
    </div>
</div>

<?php if ($flash !== null):
    $kind = in_array($flash['kind'] ?? '', ['success', 'error', 'info'], true) ? $flash['kind'] : 'info';
    $icon = ['success' => 'fa-circle-check', 'error' => 'fa-circle-exclamation', 'info' => 'fa-circle-info'][$kind];
    ?>
    <div class="st-flash st-flash-<?= $kind ?>">
        <i class="fas <?= $icon ?>"></i>
        <div><?= access_e((string)$flash['message']) ?></div>
    </div>
<?php endif; ?>

<div class="st-card">
    <h2>
        Guest access
        <span class="st-chip <?= $guestAccess ? 'st-chip-live' : 'st-chip-draft' ?>" style="margin-left:8px;">
            <i class="fas <?= $guestAccess ? 'fa-lock-open' : 'fa-lock' ?>"></i>
            <?= $guestAccess ? 'On' : 'Off' ?>
        </span>
    </h2>
    <p class="st-sub">
        With this on, anyone holding the link below can join without a UNILIS account. They enter as a
        guest: no host controls, and their name appears in the room and on the list further down.
        Enrolled students do not need it — they already have their own join link.
    </p>

    <?php if ($guestUrl !== null): ?>
        <div class="st-field" style="margin-top:18px;">
            <label for="guestLink">Guest link</label>
            <div class="st-copy-row">
                <input id="guestLink" type="text" readonly value="<?= access_e($guestUrl) ?>">
                <button class="st-btn st-btn-small" type="button" data-copy="guestLink">
                    <i class="fas fa-copy"></i> Copy
                </button>
            </div>
        </div>

        <div class="st-field">
            <label>Passcode</label>
            <?php if ($passcode !== ''): ?>
                <div class="st-copy-row">
                    <input id="guestPasscode" type="text" readonly value="<?= access_e($passcode) ?>"
                           style="max-width:170px; flex:0 0 auto; letter-spacing:3px; font-weight:700; text-align:center;">
                    <button class="st-btn st-btn-small st-btn-ghost" type="button" data-copy="guestPasscode">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
                <span class="st-hint">Guests must type this as well as opening the link.</span>
            <?php else: ?>
                <span class="st-hint">
                    None — the link alone lets someone in. Set one below if you are sharing the link
                    somewhere you do not control.
                </span>
            <?php endif; ?>
        </div>

        <div class="st-field">
            <label>Listed in UNILIS Learning</label>
            <span class="st-hint">
                <?= $guestListed
                    ? 'Yes — signed-in external learners can find this session at /learn/live.php without the link.'
                    : 'No — only people you send the link to can join.' ?>
            </span>
        </div>
    <?php endif; ?>

    <form method="post" style="margin-top:20px; border-top:1px solid var(--st-line); padding-top:18px;">
        <input type="hidden" name="csrf_token" value="<?= access_e($csrf) ?>">
        <input type="hidden" name="meeting_id" value="<?= $meetingId ?>">
        <input type="hidden" name="action" value="enable">

        <h3><?= $guestAccess ? 'Change the settings' : 'Open this session to guests' ?></h3>

        <label class="st-check" style="margin-top:12px;">
            <input type="checkbox" name="guest_listed" value="1"<?= $guestListed ? ' checked' : '' ?>>
            <div>
                Also list it in UNILIS Learning
                <span>
                    Signed-in external learners see it at /learn/live.php and can join without being
                    sent the link. Leave this off for a session meant for specific invitees.
                </span>
            </div>
        </label>

        <div class="st-field">
            <label>Passcode</label>
            <label class="st-check" style="margin-bottom:6px;">
                <input type="radio" name="passcode_mode" value="keep" checked>
                <div><?= $passcode !== '' ? 'Keep the current passcode' : 'No passcode (link only)' ?></div>
            </label>
            <label class="st-check" style="margin-bottom:6px;">
                <input type="radio" name="passcode_mode" value="generate">
                <div>Generate a new one<span>Six characters, no lookalikes, easy to read out.</span></div>
            </label>
            <label class="st-check" style="margin-bottom:6px;">
                <input type="radio" name="passcode_mode" value="set">
                <div>
                    Use this one
                    <input name="passcode" type="text" maxlength="32" placeholder="e.g. OPENDAY"
                           style="margin-top:6px; max-width:240px;">
                </div>
            </label>
            <?php if ($passcode !== ''): ?>
                <label class="st-check">
                    <input type="radio" name="passcode_mode" value="none">
                    <div>Remove the passcode<span>The link alone will let anyone in.</span></div>
                </label>
            <?php endif; ?>
        </div>

        <div class="st-actions">
            <button class="st-btn <?= $guestAccess ? '' : 'st-btn-green' ?>" type="submit">
                <i class="fas <?= $guestAccess ? 'fa-floppy-disk' : 'fa-lock-open' ?>"></i>
                <?= $guestAccess ? 'Save settings' : 'Turn guest access on' ?>
            </button>
        </div>
    </form>

    <?php if ($guestAccess): ?>
        <div class="st-actions" style="margin-top:18px; border-top:1px solid var(--st-line); padding-top:18px;">
            <form method="post"
                  onsubmit="return confirm('Issue a new link? Everyone still holding the old one will be locked out.');">
                <input type="hidden" name="csrf_token" value="<?= access_e($csrf) ?>">
                <input type="hidden" name="meeting_id" value="<?= $meetingId ?>">
                <input type="hidden" name="action" value="rotate">
                <button class="st-btn st-btn-amber st-btn-small" type="submit">
                    <i class="fas fa-rotate"></i> Issue a new link
                </button>
            </form>
            <form method="post"
                  onsubmit="return confirm('Turn guest access off? The link stops working immediately.');">
                <input type="hidden" name="csrf_token" value="<?= access_e($csrf) ?>">
                <input type="hidden" name="meeting_id" value="<?= $meetingId ?>">
                <input type="hidden" name="action" value="disable">
                <button class="st-btn st-btn-danger st-btn-small" type="submit">
                    <i class="fas fa-lock"></i> Turn guest access off
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="st-card">
    <h2>Guests who joined</h2>
    <p class="st-sub">
        One row per person, recorded when they entered their name — not when they connected, so
        somebody who opened the link and changed their mind still appears here.
    </p>

    <?php if (!$guests): ?>
        <div class="st-empty">
            <?= $guestAccess
                ? 'Nobody has used the link yet.'
                : 'No guests have ever joined this meeting.' ?>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto; margin-top:14px;">
            <table class="st-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Account</th>
                        <th>Joined</th>
                        <th>Last seen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($guests as $guest): ?>
                        <tr>
                            <td><strong><?= access_e((string)$guest['name']) ?></strong></td>
                            <td><?= $guest['email'] !== null ? access_e((string)$guest['email']) : '—' ?></td>
                            <td>
                                <?php if ($guest['learner_id'] !== null): ?>
                                    <span class="st-chip st-chip-info">
                                        <i class="fas fa-id-badge"></i> Registered learner
                                    </span>
                                <?php else: ?>
                                    <span class="st-chip">Anonymous guest</span>
                                <?php endif; ?>
                            </td>
                            <td><?= access_e(date('d M, g:i A', strtotime((string)$guest['joined_at']))) ?></td>
                            <td><?= access_e(date('d M, g:i A', strtotime((string)$guest['last_seen_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="st-sub" style="margin-top:12px;">
            <?= count($guests) ?> guest<?= count($guests) === 1 ? '' : 's' ?> in total.
            Guests are not on the student attendance register — that register is per unit enrolment,
            and a guest is not enrolled in anything.
        </p>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('[data-copy]').forEach(button => {
    button.addEventListener('click', () => {
        const field = document.getElementById(button.dataset.copy);
        if (!field) { return; }
        field.select();
        // navigator.clipboard needs a secure context, which a local HTTP install
        // is not, so fall back to the older command rather than failing silently.
        const done = () => {
            const original = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> Copied';
            setTimeout(() => { button.innerHTML = original; }, 1600);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(field.value).then(done, () => { document.execCommand('copy'); done(); });
        } else {
            document.execCommand('copy');
            done();
        }
    });
});
</script>
<?php
access_foot();
