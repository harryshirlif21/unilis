<?php
/**
 * Live Engagement Module - Premium Lecturer Dashboard (2025/2026)
 * 
 * Modern glassmorphism dashboard with interactive widgets, real-time stats,
 * and the full premium design system.
 * 
 * @package UNILIS\LiveEngagement\Views
 * @version 2.0.0
 */

require_once __DIR__ . '/../bootstrap.php';
le_require_auth();

use LE\Components\UI;

$userId = le_current_user_id();
$role = le_current_user_role();

// Only lecturers can access this dashboard
if ($role !== 'lecturer' && $role !== 'admin') {
    header('Location: ' . getMeetingAppBaseUrl() . '/login.php');
    exit;
}

$sessionModel = new \LE\Models\SessionModel();
$activeSessions = $sessionModel->getLecturerActiveSessions($userId);
$sessionHistory = $sessionModel->getLecturerHistory($userId);
$autoCreate = isset($_GET['create']) && $_GET['create'] === '1';
$defaultSessionType = le_get('type', 'mixed');
$defaultUnitId = (int)le_get('unit_id', 0, true);

// Get courses for dropdown
$db = le_db();
$courses = $db->select(
    "SELECT c.id, c.name FROM courses c 
     JOIN lecturers l ON c.id = l.course_id 
     WHERE l.id = ? ORDER BY c.name",
    [$userId],
    'i'
);

$units = $db->select(
    "SELECT u.id, u.name, u.course_id FROM units u
     JOIN lecturer_units lu ON u.id = lu.unit_id
     WHERE lu.lecturer_id = ? ORDER BY u.name",
    [$userId],
    'i'
);

include __DIR__ . '/../../includes/header.php';
?>
<link rel="stylesheet" href="<?= le_asset_url('css/live-engagement.css') ?>">
<?= le_csrf_meta() ?>
<?= UI::inlineScript() ?>

<div class="le-container le-page-enter">
    <!-- ============================================================ -->
    <!-- Page Header -->
    <!-- ============================================================ -->
    <div style="margin-bottom: var(--le-space-4);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: var(--le-space-2);">
            <div>
                <div style="display: flex; align-items: center; gap: var(--le-space-2); margin-bottom: var(--le-space-1);">
                    <div style="width: 48px; height: 48px; border-radius: var(--le-radius-xl); background: linear-gradient(135deg, var(--le-primary), var(--le-primary-light)); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(27,94,32,0.3);">
                        <span class="material-symbols-rounded" style="font-size: 28px; color: white;">live_tv</span>
                    </div>
                    <div>
                        <h1 style="font-size: var(--le-font-size-3xl); font-weight: var(--le-font-weight-bold); margin: 0; letter-spacing: var(--le-letter-spacing-tight);">
                            Live Engagement
                        </h1>
                        <p style="color: var(--le-gray-500); margin: 2px 0 0; font-size: var(--le-font-size-sm);">
                            Create interactive presentations and engage students in real-time
                        </p>
                    </div>
                </div>
            </div>
            <div style="display: flex; gap: var(--le-space-2); flex-wrap: wrap; align-items: center;">
                <?= UI::themeSwitcher() ?>
                <button class="le-btn le-btn-primary le-btn-lg" onclick="openCommandPalette()" title="Command Palette (⌘K)">
                    <span class="material-symbols-rounded" style="font-size: 20px;">terminal</span>
                    Commands
                </button>
                <button class="le-btn le-btn-primary le-btn-lg" onclick="showCreateSessionModal()">
                    <span class="material-symbols-rounded" style="font-size: 20px;">add</span>
                    Create Session
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- Quick Stats Row -->
    <!-- ============================================================ -->
    <div class="le-grid le-grid-4" style="margin-bottom: var(--le-space-4);">
        <?php
        $totalActive = count($activeSessions);
        $totalOnline = array_sum(array_column($activeSessions, 'online_count'));
        $totalPast = count($sessionHistory);
        $totalParticipants = array_sum(array_column($sessionHistory, 'total_participants'));
        $engagementAvg = $totalPast > 0 ? round(array_sum(array_column($sessionHistory, 'engagement_score')) / $totalPast) : 0;

        echo UI::statCard('Active Sessions', (string)$totalActive, 'rocket_launch', '', 'primary');
        echo UI::statCard('Students Online', (string)$totalOnline, 'people', $totalOnline > 0 ? "↑ {$totalOnline} now" : '', 'success');
        echo UI::statCard('Past Sessions', (string)$totalPast, 'history', '', 'info');
        echo UI::statCard('Engagement Rate', $totalPast > 0 ? "{$engagementAvg}%" : 'N/A', 'trending_up', $engagementAvg > 50 ? '↑ Great' : '↓ Needs improvement', $engagementAvg > 50 ? 'success' : 'warning');
        ?>
    </div>

    <!-- ============================================================ -->
    <!-- Main Grid: Active Sessions + Quick Actions -->
    <!-- ============================================================ -->
    <div class="le-grid le-grid-sidebar" style="margin-bottom: var(--le-space-4); grid-template-columns: 1fr 320px;">
        <!-- Active Sessions -->
        <div class="le-card-solid">
            <div class="le-card-header">
                <div style="display: flex; align-items: center; gap: var(--le-space-2);">
                    <h2 class="le-card-title">
                        <span class="material-symbols-rounded" style="font-size: 22px; color: var(--le-primary);">podcasts</span>
                        Active Sessions
                    </h2>
                </div>
                <div style="display: flex; align-items: center; gap: var(--le-space-2);">
                    <?php if ($totalActive > 0): ?>
                        <span class="le-badge le-badge-live"><?= $totalActive ?> Live</span>
                    <?php endif; ?>
                    <button class="le-btn-icon le-btn-ghost" onclick="location.reload()" title="Refresh">
                        <span class="material-symbols-rounded" style="font-size: 20px;">refresh</span>
                    </button>
                </div>
            </div>
            
            <?php if (empty($activeSessions)): ?>
                <?= UI::emptyState(
                    '🎯',
                    'No Active Sessions',
                    'Create a new session to start engaging with your students in real-time. Your sessions will appear here with live participant counts and engagement metrics.',
                    UI::button('Create Session', 'primary', [
                        'icon' => 'add',
                        'size' => 'lg',
                        'onclick' => 'showCreateSessionModal()'
                    ])
                ) ?>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: var(--le-space-2);">
                    <?php foreach ($activeSessions as $session): ?>
                        <div class="le-card-interactive" style="border: 1px solid var(--le-gray-200); border-radius: var(--le-radius-xl); padding: var(--le-space-3); transition: all var(--le-transition); cursor: pointer;"
                             onclick="openSession(<?= $session['id'] ?>)"
                             onmouseover="this.style.borderColor='var(--le-primary)'; this.style.boxShadow='var(--le-shadow-md)'"
                             onmouseout="this.style.borderColor='var(--le-gray-200)'; this.style.boxShadow='none'">
                            <div class="le-flex-between" style="margin-bottom: var(--le-space-2);">
                                <div style="display: flex; align-items: center; gap: var(--le-space-2);">
                                    <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--le-success); box-shadow: 0 0 0 3px rgba(22,163,74,0.2); animation: le-pulse 2s ease-in-out infinite;"></div>
                                    <h3 style="margin: 0; font-size: var(--le-font-size-base); font-weight: var(--le-font-weight-semibold);">
                                        <?= UI::escape($session['title']) ?>
                                    </h3>
                                </div>
                                <?= UI::sessionCode($session['session_code'], true) ?>
                            </div>
                            <div style="display: flex; gap: var(--le-space-2); flex-wrap: wrap; align-items: center;">
                                <span class="le-badge le-badge-<?= $session['status'] ?>">
                                    <?= $session['status'] ?>
                                </span>
                                <span style="display: flex; align-items: center; gap: 4px; font-size: var(--le-font-size-sm); color: var(--le-gray-500);">
                                    <span class="material-symbols-rounded" style="font-size: 18px;">people</span>
                                    <?= $session['online_count'] ?? 0 ?> online
                                </span>
                                <span style="display: flex; align-items: center; gap: 4px; font-size: var(--le-font-size-sm); color: var(--le-gray-500);">
                                    <span class="material-symbols-rounded" style="font-size: 18px;">group</span>
                                    <?= $session['total_participants'] ?? 0 ?> total
                                </span>
                                <?php if (!empty($session['session_type'])): ?>
                                    <span class="le-tag"><?= $session['session_type'] ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar: Quick Actions + Activity -->
        <div style="display: flex; flex-direction: column; gap: var(--le-space-3);">
            <!-- Quick Actions -->
            <div class="le-card-solid">
                <div class="le-card-header">
                    <h2 class="le-card-title">
                        <span class="material-symbols-rounded" style="font-size: 22px; color: var(--le-primary);">bolt</span>
                        Quick Actions
                    </h2>
                </div>
                <div style="display: flex; flex-direction: column; gap: var(--le-space-2);">
                    <button class="le-btn le-btn-primary le-btn-lg" style="width: 100%; justify-content: flex-start; border-radius: var(--le-radius-lg); padding: 14px 18px;" 
                            onclick="showCreateSessionModal()">
                        <span class="material-symbols-rounded" style="font-size: 22px;">add_circle</span>
                        New Session
                    </button>
                    <button class="le-btn le-btn-secondary le-btn-lg" style="width: 100%; justify-content: flex-start; border-radius: var(--le-radius-lg); padding: 14px 18px;" 
                            onclick="window.location.href='?page=join'">
                        <span class="material-symbols-rounded" style="font-size: 22px;">login</span>
                        Join as Student
                    </button>
                    <button class="le-btn le-btn-secondary le-btn-lg" style="width: 100%; justify-content: flex-start; border-radius: var(--le-radius-lg); padding: 14px 18px;" 
                            onclick="window.location.href='?page=presentations'">
                        <span class="material-symbols-rounded" style="font-size: 22px;">slideshow</span>
                        Presentations
                    </button>
                    <div style="height: 1px; background: var(--le-gray-200); margin: 4px 0;"></div>
                    <button class="le-btn le-btn-ghost le-btn-sm" style="width: 100%; justify-content: flex-start; border-radius: var(--le-radius-lg); padding: 10px 14px;" 
                            onclick="LiveEngagement.openCommandPalette()">
                        <span class="material-symbols-rounded" style="font-size: 20px;">keyboard_command_key</span>
                        Command Palette (⌘K)
                    </button>
                </div>
            </div>

            <!-- Recent Activity Feed -->
            <div class="le-card-solid">
                <div class="le-card-header">
                    <h2 class="le-card-title">
                        <span class="material-symbols-rounded" style="font-size: 22px; color: var(--le-primary);">activity</span>
                        Activity
                    </h2>
                </div>
                <div style="display: flex; flex-direction: column; gap: var(--le-space-2);">
                    <?php if (empty($sessionHistory) && empty($activeSessions)): ?>
                        <div style="text-align: center; padding: var(--le-space-3); color: var(--le-gray-400); font-size: var(--le-font-size-sm);">
                            No recent activity. Create your first session to get started!
                        </div>
                    <?php else: ?>
                        <?php 
                        $recentItems = array_merge(
                            array_map(function($s) { return ['type' => 'active', 'data' => $s, 'time' => $s['actual_start'] ?? $s['created_at']]; }, $activeSessions),
                            array_map(function($s) { return ['type' => 'past', 'data' => $s, 'time' => $s['actual_end'] ?? $s['created_at']]; }, $sessionHistory)
                        );
                        usort($recentItems, function($a, $b) { return strtotime($b['time']) - strtotime($a['time']); });
                        $recentItems = array_slice($recentItems, 0, 5);
                        ?>
                        <?php foreach ($recentItems as $item): 
                            $s = $item['data'];
                            $isActive = $item['type'] === 'active';
                        ?>
                            <div style="display: flex; align-items: flex-start; gap: var(--le-space-2); padding: var(--le-space-1) 0;">
                                <div style="width: 32px; height: 32px; border-radius: var(--le-radius-full); background: <?= $isActive ? 'var(--le-success-lighter)' : 'var(--le-gray-100)' ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <span class="material-symbols-rounded" style="font-size: 16px; color: <?= $isActive ? 'var(--le-success)' : 'var(--le-gray-500)' ?>;">
                                        <?= $isActive ? 'podcasts' : 'check_circle' ?>
                                    </span>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-size: var(--le-font-size-sm); font-weight: var(--le-font-weight-medium);">
                                        <?= UI::escape($s['title']) ?>
                                    </div>
                                    <div style="font-size: var(--le-font-size-xs); color: var(--le-gray-500);">
                                        <?php if ($isActive): ?>
                                            <span style="color: var(--le-success);">● Live</span> · 
                                        <?php endif; ?>
                                        <?= LiveEngagement::timeAgo($item['time']) ?>
                                    </div>
                                </div>
                                <?php if ($isActive): ?>
                                    <span class="le-status-dot le-status-dot-active" style="margin-top: 8px;"></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- Session History -->
    <!-- ============================================================ -->
    <div class="le-card-solid">
        <div class="le-card-header">
            <div style="display: flex; align-items: center; gap: var(--le-space-2);">
                <h2 class="le-card-title">
                    <span class="material-symbols-rounded" style="font-size: 22px; color: var(--le-primary);">history</span>
                    Session History
                </h2>
            </div>
            <?php if (!empty($sessionHistory)): ?>
                <span class="le-badge le-badge-neutral"><?= count($sessionHistory) ?> sessions</span>
            <?php endif; ?>
        </div>
        
        <?php if (empty($sessionHistory)): ?>
            <div style="padding: var(--le-space-4);">
                <?= UI::emptyState(
                    '📋',
                    'No Past Sessions',
                    'Your completed sessions with engagement analytics, participation data, and exportable reports will appear here.'
                ) ?>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <?php
                $headers = ['Title', 'Code', 'Date', 'Duration', 'Participants', 'Engagement', 'Actions'];
                $rows = [];
                foreach ($sessionHistory as $session):
                    $rows[] = [
                        '<strong>' . UI::escape($session['title']) . '</strong>',
                        '<code style="font-family: var(--le-font-mono); font-size: 0.85rem;">' . $session['session_code'] . '</code>',
                        '<span style="color: var(--le-gray-600); font-size: 0.9rem;">' . ($session['actual_end'] ? date('M j, Y', strtotime($session['actual_end'])) : 'N/A') . '</span>',
                        '<span style="color: var(--le-gray-600); font-size: 0.9rem;">' . ($session['duration_minutes'] ? $session['duration_minutes'] . 'm' : 'N/A') . '</span>',
                        '<span style="font-weight: var(--le-font-weight-medium);">' . ($session['total_participants'] ?? 0) . '</span>',
                        isset($session['engagement_score']) 
                            ? '<div style="display: flex; align-items: center; gap: 8px;">
                                <div style="flex: 1; height: 6px; background: var(--le-gray-200); border-radius: 3px; max-width: 100px;">
                                    <div style="width: ' . $session['engagement_score'] . '%; height: 100%; background: linear-gradient(90deg, var(--le-primary), var(--le-primary-light)); border-radius: 3px;"></div>
                                </div>
                                <span style="font-size: 0.85rem; font-weight: var(--le-font-weight-semibold); color: var(--le-primary);">' . $session['engagement_score'] . '%</span>
                            </div>'
                            : '<span style="color: var(--le-gray-400);">N/A</span>',
                        '<div style="display: flex; gap: 4px;">
                            <a href="?page=report&id=' . $session['id'] . '" class="le-btn le-btn-sm le-btn-secondary">Report</a>
                            <button class="le-btn le-btn-sm le-btn-ghost" onclick="deleteSession(' . $session['id'] . ')">Delete</button>
                        </div>'
                    ];
                endforeach;
                echo UI::table($headers, $rows);
                ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- Create Session Modal (Premium) -->
<!-- ============================================================ -->
<div id="createSessionModal" class="le-modal-overlay" style="display: none;">
    <div class="le-modal">
        <div class="le-modal-header">
            <div style="display: flex; align-items: center; gap: var(--le-space-2);">
                <div style="width: 40px; height: 40px; border-radius: var(--le-radius-lg); background: var(--le-primary-lighter); display: flex; align-items: center; justify-content: center;">
                    <span class="material-symbols-rounded" style="color: var(--le-primary); font-size: 22px;">add_circle</span>
                </div>
                <h3 class="le-card-title" style="font-size: 1.1rem;">Create New Session</h3>
            </div>
            <button class="le-modal-close" onclick="closeModal('createSessionModal')">&times;</button>
        </div>
        <form id="createSessionForm" onsubmit="createSession(event)">
            <div class="le-modal-body">
                <div class="le-form-group">
                    <label class="le-label le-label-required">Session Title</label>
                    <input type="text" class="le-input" name="title" required placeholder="e.g. Introduction to Algorithms - Live Q&A">
                </div>
                
                <div class="le-form-group">
                    <label class="le-label">Description</label>
                    <textarea class="le-textarea" name="description" placeholder="What is this session about?" rows="3"></textarea>
                </div>

                <div class="le-grid le-grid-2">
                    <div class="le-form-group">
                        <label class="le-label">Course</label>
                        <select class="le-select" name="course_id" onchange="loadUnits(this.value)">
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>"><?= UI::escape($course['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="le-form-group">
                        <label class="le-label">Unit</label>
                        <select class="le-select" name="unit_id" id="unitSelect">
                            <option value="">Select Unit</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= $unit['id'] ?>" data-course="<?= $unit['course_id'] ?>"><?= UI::escape($unit['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="le-form-group">
                    <label class="le-label">Session Type</label>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;">
                        <?php
                        $types = [
                            'mixed' => ['icon' => 'all_inclusive', 'label' => 'All Features'],
                            'presentation' => ['icon' => 'slideshow', 'label' => 'Presentation'],
                            'poll' => ['icon' => 'poll', 'label' => 'Polling'],
                            'quiz' => ['icon' => 'quiz', 'label' => 'Quiz'],
                            'whiteboard' => ['icon' => 'draw', 'label' => 'Whiteboard'],
                        ];
                        foreach ($types as $type => $info):
                            $selected = $type === $defaultSessionType ? ' selected' : '';
                        ?>
                            <label class="le-poll-option" style="margin: 0; padding: 12px; text-align: center; flex-direction: column; gap: 4px; cursor: pointer; border-radius: var(--le-radius-lg);<?= $selected ? ' border-color: var(--le-primary); background: var(--le-primary-lighter);' : '' ?>"
                                   onclick="selectSessionType(this, '<?= $type ?>')">
                                <span class="material-symbols-rounded" style="font-size: 24px; color: var(--le-gray-500);"><?= $info['icon'] ?></span>
                                <span style="font-size: 0.8rem; color: var(--le-gray-600);"><?= $info['label'] ?></span>
                                <input type="radio" name="session_type" value="<?= $type ?>" style="display: none;" <?= $type === $defaultSessionType ? 'checked' : '' ?>>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="le-grid le-grid-2">
                    <div class="le-form-group">
                        <label class="le-label">Duration (minutes)</label>
                        <input type="number" class="le-input" name="duration_minutes" value="60" min="5" max="480">
                    </div>
                    <div class="le-form-group">
                        <label class="le-label">Max Participants</label>
                        <input type="number" class="le-input" name="max_participants" value="0" min="0" placeholder="0 = Unlimited">
                    </div>
                </div>

                <div class="le-form-group">
                    <?= UI::toggle('allow_anonymous', 'Allow anonymous participants', false) ?>
                </div>

                <?= le_csrf_field() ?>
            </div>
            <div class="le-modal-footer">
                <button type="button" class="le-btn le-btn-secondary" onclick="closeModal('createSessionModal')">Cancel</button>
                <button type="submit" class="le-btn le-btn-primary" id="createSessionBtn">
                    <span class="material-symbols-rounded" style="font-size: 20px;">rocket_launch</span>
                    Create Session
                </button>
            </div>
        </form>
    </div>
</div>

<script src="<?= le_asset_url('js/live-engagement.js') ?>"></script>
<script>
    // Initialize
    LiveEngagement.init({ isPresenter: true });

    // Auto-show create modal if triggered
    const AUTO_CREATE = <?= json_encode($autoCreate) ?>;
    const DEFAULT_SESSION_TYPE = <?= json_encode($defaultSessionType) ?>;
    const DEFAULT_UNIT_ID = <?= json_encode($defaultUnitId) ?>;

    // ============================================================
    // Session Type Selector
    // ============================================================
    function selectSessionType(el, type) {
        document.querySelectorAll('.le-poll-option[onclick]').forEach(opt => {
            opt.style.borderColor = 'var(--le-gray-200)';
            opt.style.background = '';
            opt.querySelector('input[type="radio"]').checked = false;
        });
        el.style.borderColor = 'var(--le-primary)';
        el.style.background = 'var(--le-primary-lighter)';
        el.querySelector('input[type="radio"]').checked = true;
    }

    // ============================================================
    // Modal
    // ============================================================
    function showCreateSessionModal() {
        const modal = document.getElementById('createSessionModal');
        modal.style.display = 'flex';
        
        // Set defaults if provided
        if (DEFAULT_SESSION_TYPE) {
            const typeOption = document.querySelector(`input[name="session_type"][value="${DEFAULT_SESSION_TYPE}"]`);
            if (typeOption) {
                typeOption.checked = true;
                const label = typeOption.closest('.le-poll-option');
                if (label) selectSessionType(label, DEFAULT_SESSION_TYPE);
            }
        }
        
        if (DEFAULT_UNIT_ID) {
            document.getElementById('unitSelect').value = String(DEFAULT_UNIT_ID);
        }
    }

    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    // ============================================================
    // Create Session
    // ============================================================
    async function createSession(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        const btn = document.getElementById('createSessionBtn');
        
        btn.disabled = true;
        btn.innerHTML = '<div class="le-spinner le-spinner-sm" style="border-color: rgba(255,255,255,0.3); border-top-color: white;"></div> Creating...';

        try {
            const session = await LiveEngagement.createSession(data);
            LiveEngagement.showToast('Session created! Code: ' + session.session_code, 'success');
            closeModal('createSessionModal');
            setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            console.error('Failed to create session:', error);
            LiveEngagement.showToast(error.message || 'Failed to create session', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-rounded" style="font-size: 20px;">rocket_launch</span> Create Session';
        }
    }

    // ============================================================
    // Session Actions
    // ============================================================
    function openSession(sessionId) {
        window.location.href = '?page=presenter&id=' + sessionId;
    }

    async function deleteSession(sessionId) {
        if (!confirm('Delete this session and all its data? This action cannot be undone.')) return;
        
        try {
            const response = await fetch('<?= LE_MODULE_URL ?>/api/session.php?action=delete&id=' + sessionId, {
                method: 'DELETE',
                headers: { 
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (response.ok) {
                LiveEngagement.showToast('Session deleted', 'success');
                setTimeout(() => window.location.reload(), 1000);
            }
        } catch (error) {
            console.error('Delete failed:', error);
            LiveEngagement.showToast('Failed to delete session', 'error');
        }
    }

    // ============================================================
    // Load Units
    // ============================================================
    function loadUnits(courseId) {
        const select = document.getElementById('unitSelect');
        select.value = '';
        Array.from(select.options).forEach(option => {
            if (option.value === '' || !option.dataset.course || option.dataset.course === courseId) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
            }
        });
    }

    // ============================================================
    // Command Palette
    // ============================================================
    function openCommandPalette() {
        LiveEngagement.openCommandPalette();
    }

    // Auto-show create modal
    if (AUTO_CREATE) {
        showCreateSessionModal();
    }
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>