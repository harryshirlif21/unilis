<?php
/**
 * Live Engagement Module - Main Dashboard
 * 
 * Entry point for lecturers to manage live sessions.
 * 
 * @package UNILIS\LiveEngagement\Views
 * @version 1.0.0
 */

require_once __DIR__ . '/../bootstrap.php';
le_require_auth();

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

<div class="le-container">
    <!-- Page Header -->
    <div class="le-card-solid" style="margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>
                <h1 style="font-size: 1.8rem; margin: 0;">Live Engagement</h1>
                <p style="color: var(--le-gray-600); margin: 4px 0 0;">Create and manage interactive live sessions</p>
            </div>
            <button class="le-btn le-btn-primary le-btn-lg" onclick="showCreateSessionModal()">
                <span>+</span> New Session
            </button>
        </div>
    </div>

    <div class="le-grid le-grid-2" style="margin-bottom: 24px;">
        <!-- Active Sessions -->
        <div class="le-card-solid">
            <div class="le-card-header">
                <h2 class="le-card-title">Active Sessions</h2>
                <span class="le-badge le-badge-live">
                    <?= count($activeSessions) ?> Live
                </span>
            </div>
            
            <?php if (empty($activeSessions)): ?>
                <div class="le-empty-state">
                    <div class="le-empty-icon">🎯</div>
                    <div class="le-empty-title">No Active Sessions</div>
                    <div class="le-empty-text">Create a new session to start engaging with your students in real-time.</div>
                    <button class="le-btn le-btn-primary" style="margin-top: 16px;" onclick="showCreateSessionModal()">
                        Create Session
                    </button>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($activeSessions as $session): ?>
                        <div class="le-card" style="cursor: pointer;" onclick="openSession(<?= $session['id'] ?>)">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <h3 style="margin: 0; font-size: 1rem;"><?= le_escape($session['title']) ?></h3>
                                    <div style="display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap;">
                                        <span class="le-badge le-badge-<?= $session['status'] ?>">
                                            <?= $session['status'] ?>
                                        </span>
                                        <span style="font-size: 0.8rem; color: var(--le-gray-500);">
                                            👥 <?= $session['online_count'] ?? 0 ?> online
                                        </span>
                                        <span style="font-size: 0.8rem; color: var(--le-gray-500);">
                                            📊 <?= $session['total_participants'] ?? 0 ?> total
                                        </span>
                                    </div>
                                </div>
                                <div class="le-session-code le-session-code-sm" 
                                     onclick="event.stopPropagation(); copyCode('<?= $session['session_code'] ?>')"
                                     title="Click to copy">
                                    <?= $session['session_code'] ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Stats -->
        <div class="le-card-solid">
            <div class="le-card-header">
                <h2 class="le-card-title">Quick Stats</h2>
            </div>
            <div class="le-grid le-grid-2" style="gap: 16px;">
                <div style="text-align: center; padding: 16px; background: var(--le-primary-light); border-radius: var(--le-radius-sm);">
                    <div style="font-size: 2rem; font-weight: 700; color: var(--le-primary);">
                        <?= count($activeSessions) ?>
                    </div>
                    <div style="font-size: 0.85rem; color: var(--le-gray-600);">Active Sessions</div>
                </div>
                <div style="text-align: center; padding: 16px; background: #d4edda; border-radius: var(--le-radius-sm);">
                    <div style="font-size: 2rem; font-weight: 700; color: var(--le-success);">
                        <?= array_sum(array_column($activeSessions, 'online_count')) ?>
                    </div>
                    <div style="font-size: 0.85rem; color: var(--le-gray-600);">Online Now</div>
                </div>
                <div style="text-align: center; padding: 16px; background: #fff3cd; border-radius: var(--le-radius-sm);">
                    <div style="font-size: 2rem; font-weight: 700; color: var(--le-warning);">
                        <?= count($sessionHistory) ?>
                    </div>
                    <div style="font-size: 0.85rem; color: var(--le-gray-600);">Past Sessions</div>
                </div>
                <div style="text-align: center; padding: 16px; background: #cce5ff; border-radius: var(--le-radius-sm);">
                    <div style="font-size: 2rem; font-weight: 700; color: var(--le-info);">
                        <?= array_sum(array_column($sessionHistory, 'total_participants')) ?>
                    </div>
                    <div style="font-size: 0.85rem; color: var(--le-gray-600);">Total Participants</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Session History -->
    <div class="le-card-solid">
        <div class="le-card-header">
            <h2 class="le-card-title">Session History</h2>
        </div>
        
        <?php if (empty($sessionHistory)): ?>
            <div class="le-empty-state">
                <div class="le-empty-icon">📋</div>
                <div class="le-empty-title">No Past Sessions</div>
                <div class="le-empty-text">Your completed sessions will appear here.</div>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--le-gray-200);">
                            <th style="text-align: left; padding: 12px 16px; font-size: 0.85rem;">Title</th>
                            <th style="text-align: left; padding: 12px 16px; font-size: 0.85rem;">Code</th>
                            <th style="text-align: left; padding: 12px 16px; font-size: 0.85rem;">Date</th>
                            <th style="text-align: left; padding: 12px 16px; font-size: 0.85rem;">Participants</th>
                            <th style="text-align: left; padding: 12px 16px; font-size: 0.85rem;">Engagement</th>
                            <th style="text-align: left; padding: 12px 16px; font-size: 0.85rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessionHistory as $session): ?>
                            <tr style="border-bottom: 1px solid var(--le-gray-100);">
                                <td style="padding: 12px 16px; font-weight: 500;"><?= le_escape($session['title']) ?></td>
                                <td style="padding: 12px 16px; font-family: monospace;"><?= $session['session_code'] ?></td>
                                <td style="padding: 12px 16px; color: var(--le-gray-600);">
                                    <?= $session['actual_end'] ? date('M j, Y', strtotime($session['actual_end'])) : 'N/A' ?>
                                </td>
                                <td style="padding: 12px 16px;"><?= $session['total_participants'] ?? 0 ?></td>
                                <td style="padding: 12px 16px;">
                                    <?php if (isset($session['engagement_score'])): ?>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="flex: 1; height: 6px; background: var(--le-gray-200); border-radius: 3px; max-width: 100px;">
                                                <div style="width: <?= $session['engagement_score'] ?>%; height: 100%; background: var(--le-success); border-radius: 3px;"></div>
                                            </div>
                                            <span style="font-size: 0.8rem;"><?= $session['engagement_score'] ?>%</span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--le-gray-400);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <div class="le-btn-group">
                                        <a href="?page=report&id=<?= $session['id'] ?>" class="le-btn le-btn-sm le-btn-secondary">Report</a>
                                        <button class="le-btn le-btn-sm le-btn-outline" onclick="deleteSession(<?= $session['id'] ?>)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Session Modal -->
<div id="createSessionModal" class="le-modal-overlay" style="display: none;">
    <div class="le-modal">
        <div class="le-modal-header">
            <h3 class="le-card-title">Create New Session</h3>
            <button class="le-modal-close" onclick="closeModal('createSessionModal')">&times;</button>
        </div>
        <form id="createSessionForm" onsubmit="createSession(event)">
            <div class="le-modal-body">
                <div class="le-form-group">
                    <label class="le-label">Session Title *</label>
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
                                <option value="<?= $course['id'] ?>"><?= le_escape($course['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="le-form-group">
                        <label class="le-label">Unit</label>
                        <select class="le-select" name="unit_id" id="unitSelect">
                            <option value="">Select Unit</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= $unit['id'] ?>" data-course="<?= $unit['course_id'] ?>"><?= le_escape($unit['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="le-form-group">
                    <label class="le-label">Session Type</label>
                    <select class="le-select" name="session_type">
                        <option value="mixed">Mixed (All Features)</option>
                        <option value="presentation">Presentation Only</option>
                        <option value="poll">Polling Only</option>
                        <option value="quiz">Quiz Only</option>
                        <option value="whiteboard">Whiteboard Only</option>
                    </select>
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
                    <label class="le-label">
                        <input type="checkbox" name="allow_anonymous" value="1">
                        Allow anonymous participants
                    </label>
                </div>
                <?= le_csrf_field() ?>
            </div>
            <div class="le-modal-footer">
                <button type="button" class="le-btn le-btn-secondary" onclick="closeModal('createSessionModal')">Cancel</button>
                <button type="submit" class="le-btn le-btn-primary">Create Session</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= le_asset_url('js/live-engagement.js') ?>"></script>
<script>
    // Initialize
    LiveEngagement.init({
        isPresenter: true,
    });

    // Show create session modal
    function showCreateSessionModal() {
        document.getElementById('createSessionModal').style.display = 'flex';
    }

    // Close modal
    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    // Create session
    async function createSession(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
            const session = await LiveEngagement.createSession(data);
            LiveEngagement.showToast('Session created! Code: ' + session.session_code, 'success');
            closeModal('createSessionModal');
            setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            console.error('Failed to create session:', error);
        }
    }

    // Open session presenter view
    function openSession(sessionId) {
        window.location.href = '?page=presenter&id=' + sessionId;
    }

    // Copy session code
    function copyCode(code) {
        navigator.clipboard.writeText(code).then(() => {
            LiveEngagement.showToast('Code copied: ' + code, 'success');
        });
    }

    // Delete session
    async function deleteSession(sessionId) {
        if (!confirm('Delete this session and all its data?')) return;
        
        try {
            const response = await fetch('modules/live-engagement/api/session.php?action=delete&id=' + sessionId, {
                method: 'DELETE',
                headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
            });
            if (response.ok) {
                LiveEngagement.showToast('Session deleted', 'success');
                setTimeout(() => window.location.reload(), 1000);
            }
        } catch (error) {
            console.error('Delete failed:', error);
        }
    }

    // Load units based on course selection
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
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>