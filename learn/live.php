<?php
/**
 * Live sessions open to external learners.
 *
 * A meeting reaches this page only if its host both turned guest access on and
 * ticked "list it in UNILIS Learning". Guest access alone means "anyone with the
 * link", which is not the same as "advertise it to every registered learner" —
 * a session for three invited guests should not appear here because the host
 * needed a link for them.
 *
 * Signed-in learners only. The list is short, but it is still a directory of
 * sessions somebody could sit in on, and that does not belong on the open web.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/layout.php';
require_once dirname(__DIR__) . '/config/meeting.php';
require_once dirname(__DIR__) . '/config/meeting_guests.php';

learn_require_schema($conn);
$learner = learn_require_login($conn);

// The guest-access migration is separate from the one that created /learn, so
// this page can load on a database where the columns do not exist yet.
$ready = meeting_guests_ready($conn);
$sessions = $ready ? meeting_guest_listed_sessions($conn) : [];

// Split on whether the session has actually begun, not on whether it can be
// joined: the room lets people in early, so every listed session is joinable and
// grouping on that would put everything under "happening now".
$live = array_values(array_filter($sessions, static fn($s) => !empty($s['has_started'])));
$upcoming = array_values(array_filter($sessions, static fn($s) => empty($s['has_started'])));

learn_head(['title' => 'Live sessions', 'learner' => $learner]);
?>
<section class="ln-hero">
    <h1>Live sessions</h1>
    <p>
        Talks and classes the university has opened to learners outside it. You join as a guest —
        no enrolment needed.
        <?php if ($live): ?>
            <?= count($live) ?> happening now.
        <?php endif; ?>
    </p>
</section>

<?php if (!$ready): ?>
    <?php learn_notice(
        'Guest access to meetings has not been set up on this installation yet. '
        . 'An administrator needs to run migrate_meeting_guests.php once.',
        'info'
    ); ?>

<?php elseif (!$sessions): ?>
    <div class="ln-empty">
        <span class="material-symbols-rounded">event_busy</span>
        <h2>Nothing scheduled right now</h2>
        <p>
            When a lecturer opens a session to the public it shows up here. If someone has sent you a
            link to a session directly, that link still works — it does not need to be listed.
        </p>
        <p style="margin-top:16px;"><a class="ln-btn ln-btn-primary" href="/learn/">Browse courses</a></p>
    </div>

<?php else: ?>
    <?php foreach ([['Happening now', $live], ['Coming up', $upcoming]] as [$heading, $group]): ?>
        <?php if (!$group) { continue; } ?>

        <h2 style="margin:26px 0 14px; font-size:1.1rem;"><?= learn_e($heading) ?></h2>

        <?php foreach ($group as $session):
            $startsAt = strtotime((string)$session['scheduled_time']);
            ?>
            <div class="ln-card" style="margin-bottom:14px;">
                <div style="display:flex; gap:16px; align-items:flex-start; flex-wrap:wrap;">
                    <div style="flex:1 1 260px; min-width:0;">
                        <h3 style="margin:0 0 6px;">
                            <?= learn_e((string)$session['title']) ?>
                            <?php if (!empty($session['has_started'])): ?>
                                <span class="ln-chip ln-chip-done" style="margin-left:6px;">
                                    <span class="material-symbols-rounded">sensors</span> Live
                                </span>
                            <?php endif; ?>
                        </h3>
                        <p class="ln-sub" style="margin:0;">
                            <?php if (!empty($session['lecturer_name'])): ?>
                                Hosted by <?= learn_e((string)$session['lecturer_name']) ?>
                            <?php endif; ?>
                            <?php if (!empty($session['unit_name'])): ?>
                                · <?= learn_e((string)$session['unit_name']) ?>
                            <?php endif; ?>
                        </p>
                        <div class="ln-meta" style="margin-top:8px;">
                            <span class="ln-chip">
                                <span class="material-symbols-rounded">schedule</span>
                                <?= $startsAt ? learn_e(date('D d M, g:i A', $startsAt)) : 'Not scheduled' ?>
                            </span>
                            <span><?= (int)$session['duration'] ?> minutes</span>
                        </div>
                    </div>
                    <div>
                        <a class="ln-btn <?= !empty($session['has_started']) ? 'ln-btn-primary' : 'ln-btn-ghost' ?>"
                           href="<?= learn_e((string)$session['join_url']) ?>">
                            <span class="material-symbols-rounded">videocam</span>
                            <?= !empty($session['has_started']) ? 'Join now' : 'Open session page' ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <p class="ln-sub" style="margin-top:22px;">
        Some sessions ask for a passcode as well as the link. If one does and you do not have it,
        ask whoever told you about the session.
    </p>
<?php endif; ?>
<?php
learn_foot();
