<?php
/**
 * chat/views/chat.php
 *
 * The chat page for students and lecturers. One shell for both roles: a
 * lecturer additionally gets the instruction composer, which is the only
 * role-dependent control on the page.
 */

require_once __DIR__ . '/../config.php';

$chatUser = chat_current_user();

if ($chatUser === null) {
    header('Location: /login.php');
    exit;
}

$csrfToken = chat_csrf_token();
$schemaReady = chat_schema_ready($conn);
$displayName = (string)($_SESSION['user_name'] ?? '');
$isLecturer = $chatUser['role'] === 'lecturer';

// Deep link from a notification: chat.php?conversation=12
$initialConversation = (int)($_GET['conversation'] ?? 0);

// Where "Back" goes, since chat is reachable from either dashboard.
$dashboardUrl = $isLecturer ? '../../lecturer/dashboard.php' : '../../student/dashboard.php';

/**
 * Asset URL with a cache-busting token, so a deploy is not held back by the
 * long max-age these static files are served with.
 */
function chat_asset(string $relativePath): string
{
    $stamp = @filemtime(dirname(__DIR__) . '/' . $relativePath);

    return htmlspecialchars(
        '../' . $relativePath . '?v=' . ($stamp !== false ? $stamp : '0'),
        ENT_QUOTES
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat – UniLIS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= chat_asset('assets/chat.css') ?>">
</head>
<body>
<?php if (!$schemaReady): ?>
    <div class="chat-setup">
        <h1><i class="fas fa-database"></i> Chat is not set up yet</h1>
        <p>
            The chat tables have not been created in this database. An administrator
            needs to run the <code>migrate_chat_system.php</code> migration once —
            from the Database Migrations panel on the admin dashboard, or from a shell with
            <code>docker compose exec app php migrate_chat_system.php</code>.
        </p>
        <a class="chat-btn chat-btn-primary" href="<?= htmlspecialchars($dashboardUrl) ?>">Back to dashboard</a>
    </div>
<?php else: ?>
    <div class="chat-shell" id="chatShell">
        <aside class="chat-sidebar" id="chatSidebar">
            <div class="chat-sidebar-head">
                <a class="chat-back" href="<?= htmlspecialchars($dashboardUrl) ?>" title="Back to dashboard">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1>Chat</h1>
                <button type="button" class="chat-icon-btn" id="refreshBtn" title="Refresh groups">
                    <i class="fas fa-rotate"></i>
                </button>
            </div>

            <div class="chat-sidebar-actions">
                <button type="button" class="chat-btn chat-btn-primary" id="newChatBtn">
                    <i class="fas fa-pen-to-square"></i> New chat
                </button>
                <?php if ($isLecturer): ?>
                    <button type="button" class="chat-btn chat-btn-accent" id="newInstructionBtn">
                        <i class="fas fa-bullhorn"></i> Instructions
                    </button>
                <?php endif; ?>
            </div>

            <input type="search" id="conversationFilter" class="chat-input"
                   placeholder="Filter conversations" autocomplete="off">

            <nav class="chat-tabs" id="chatTabs">
                <button type="button" class="chat-tab is-active" data-filter="all">All</button>
                <button type="button" class="chat-tab" data-filter="direct">Direct</button>
                <button type="button" class="chat-tab" data-filter="group">Groups</button>
                <button type="button" class="chat-tab" data-filter="instructions">Instructions</button>
            </nav>

            <ul class="chat-conversations" id="conversationList">
                <li class="chat-placeholder">Loading conversations…</li>
            </ul>
        </aside>

        <main class="chat-main" id="chatMain">
            <div class="chat-empty" id="chatEmpty">
                <i class="fas fa-comments"></i>
                <h2>Select a conversation</h2>
                <p>Your teams, classmates and lecturers appear on the left.</p>
            </div>

            <section class="chat-pane" id="chatPane" hidden>
                <header class="chat-pane-head">
                    <button type="button" class="chat-icon-btn chat-only-mobile" id="backToList" title="Back">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <div class="chat-pane-title">
                        <h2 id="chatTitle"></h2>
                        <p id="chatSubtitle"></p>
                    </div>
                </header>

                <div class="chat-messages" id="messageList"></div>

                <div class="chat-readonly" id="readOnlyNotice" hidden>
                    <i class="fas fa-lock"></i>
                    This is an instructions channel. Only lecturers can post here.
                </div>

                <form class="chat-composer" id="composer">
                    <textarea id="composerInput" rows="1" placeholder="Write a message…"
                              maxlength="<?= (int)CHAT_MAX_BODY_LENGTH ?>"></textarea>
                    <button type="submit" class="chat-btn chat-btn-primary" id="sendBtn">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </section>
        </main>
    </div>

    <!-- New direct chat -->
    <div class="chat-modal" id="newChatModal" hidden>
        <div class="chat-modal-card">
            <header>
                <h2>Start a chat</h2>
                <button type="button" class="chat-icon-btn" data-close-modal><i class="fas fa-xmark"></i></button>
            </header>
            <input type="search" id="directorySearch" class="chat-input"
                   placeholder="Search classmates, teammates or lecturers" autocomplete="off">
            <ul class="chat-directory" id="directoryList">
                <li class="chat-placeholder">Loading people…</li>
            </ul>
            <p class="chat-hint">
                You can message people you share a unit, team or course with, and the
                lecturers teaching your units.
            </p>
        </div>
    </div>

    <?php if ($isLecturer): ?>
    <!-- Instruction composer -->
    <div class="chat-modal" id="instructionModal" hidden>
        <div class="chat-modal-card">
            <header>
                <h2>Post instructions</h2>
                <button type="button" class="chat-icon-btn" data-close-modal><i class="fas fa-xmark"></i></button>
            </header>

            <label class="chat-label" for="instructionTarget">Send to</label>
            <select id="instructionTarget" class="chat-input">
                <option value="">Loading targets…</option>
            </select>

            <label class="chat-label" for="instructionBody">Instructions</label>
            <textarea id="instructionBody" class="chat-input" rows="6"
                      maxlength="<?= (int)CHAT_MAX_BODY_LENGTH ?>"
                      placeholder="What do your students need to do?"></textarea>

            <label class="chat-check">
                <input type="checkbox" id="instructionEmail">
                <span>Also email this to recipients</span>
            </label>

            <p class="chat-hint" id="instructionHint">
                Instructions always appear in the channel and as an in-app notification.
                Email is only sent when the box above is ticked.
            </p>

            <div class="chat-modal-actions">
                <button type="button" class="chat-btn" data-close-modal>Cancel</button>
                <button type="button" class="chat-btn chat-btn-accent" id="instructionSend">
                    <i class="fas fa-bullhorn"></i> Post instructions
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="chat-toast" id="chatToast" hidden></div>

    <script>
        window.__CHAT__ = {
            csrfToken: <?= json_encode($csrfToken) ?>,
            me: <?= json_encode(['id' => $chatUser['id'], 'role' => $chatUser['role'], 'name' => $displayName]) ?>,
            isLecturer: <?= $isLecturer ? 'true' : 'false' ?>,
            initialConversation: <?= (int)$initialConversation ?>,
            apiBase: '../api/',
            maxLength: <?= (int)CHAT_MAX_BODY_LENGTH ?>
        };
    </script>
    <script src="<?= chat_asset('assets/chat.js') ?>"></script>
<?php endif; ?>
</body>
</html>
