<?php
/**
 * Live Engagement Module - Presentation Library (Premium 2025/2026)
 * 
 * Full-featured presentation repository with search, filtering, 
 * versioning, and preview capabilities.
 * 
 * @package UNILIS\LiveEngagement\Views
 * @version 2.0.0
 */

require_once __DIR__ . '/../bootstrap.php';
le_require_auth();

use LE\Components\Layout;
use LE\Components\UI;

$userId = le_current_user_id();
$role = le_current_user_role();

// Ensure we have a valid user ID
if (!$userId) {
    error_log("Presentations: No user ID found in session");
    header('Location: ' . le_page_url('home'));
    exit;
}

try {
    $db = le_db();
    $presentationModel = new \LE\Models\PresentationModel();

    // Get presentations with search/filter
    $search = le_get('search', '');
    $courseFilter = (int)le_get('course_id', 0, true);
    $sort = le_get('sort', 'newest');
    $currentPage = max(1, (int)le_get('p', 1, true));
    // Carried through to the "Create Presentation" link so a deck created from
    // a session-scoped view stays attached to that session. Read from the
    // request because it was previously never assigned at all: showCreateModal()
    // interpolated an undefined variable, which emitted two PHP warnings in the
    // middle of a JavaScript expression and broke the whole inline script.
    $selectedSessionId = (int)le_get('session_id', 0, true);
    $perPage = 20;

    $presentations = $presentationModel->getUserPresentations($userId, $search, $courseFilter, $sort, $currentPage, $perPage, $selectedSessionId);
    $totalPresentations = $presentationModel->countUserPresentations($userId, $search, $courseFilter, $selectedSessionId);

    // Get courses for filter
    $courses = $db->select("SELECT id, name FROM courses ORDER BY name");

    // Sessions for upload modal (scoped to this lecturer)
    $sessionModel = new \LE\Models\SessionModel();
    $uploadSessions = array_merge(
        $sessionModel->getLecturerActiveSessions($userId),
        $sessionModel->getLecturerScheduledSessions($userId)
    );
} catch (Exception $e) {
    error_log("Presentations query error: " . $e->getMessage());
    $presentations = [];
    $totalPresentations = 0;
    $courses = [];
    $uploadSessions = [];
    $selectedSessionId = (int)le_get('session_id', 0, true);
}

Layout::start([
    'title' => 'Presentations',
    'layout' => 'app',
    'activeNav' => 'presentations',
]);
?>

<style>
/* Presentations page specific styles using live-dash.css variables */
.ld-presentations-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}
.ld-presentations-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.ld-presentations-title-group {
    display: flex;
    align-items: center;
    gap: 12px;
}
.ld-presentations-icon {
    width: 48px;
    height: 48px;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--green), var(--green-mid));
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(27,94,32,0.3);
}
.ld-presentations-icon .material-symbols-rounded {
    font-size: 28px;
    color: white;
}
.ld-presentations-title {
    font-size: 32px;
    font-weight: 700;
    color: var(--text);
    margin: 0;
}
.ld-presentations-subtitle {
    font-size: 14px;
    color: var(--muted);
    margin-top: 4px;
}
.ld-presentations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
}
.ld-presentation-card {
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 20px;
    padding: 20px;
    transition: all 0.2s ease;
    cursor: pointer;
}
.ld-presentation-card:hover {
    transform: translateY(-2px);
    border-color: rgba(102,242,154,.25);
    box-shadow: 0 10px 28px rgba(0,0,0,.09);
}
.ld-presentation-thumbnail {
    width: 100%;
    height: 160px;
    background: var(--panel-2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
    overflow: hidden;
}
.ld-presentation-thumbnail .material-symbols-rounded {
    font-size: 48px;
    color: var(--muted);
}
.ld-presentation-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ld-presentation-meta {
    font-size: 12px;
    color: var(--muted);
    margin-bottom: 12px;
}
.ld-presentation-actions {
    display: flex;
    gap: 8px;
}
</style>

<div class="ld">
    <div class="ld-presentations-container">
        <!-- Page Header -->
        <div class="ld-presentations-header">
            <div class="ld-presentations-title-group">
                <div class="ld-presentations-icon">
                    <span class="material-symbols-rounded">slideshow</span>
                </div>
                <div>
                    <h1 class="ld-presentations-title">Presentations</h1>
                    <p class="ld-presentations-subtitle">
                        <?= $totalPresentations ?> presentation<?= $totalPresentations !== 1 ? 's' : '' ?> in your library
                    </p>
                </div>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                <button class="ld-btn primary" onclick="showUploadModal()">
                    <span class="material-symbols-rounded">upload</span>
                    Upload
                </button>
                <button class="ld-btn primary" onclick="showCreateModal()">
                    <span class="material-symbols-rounded">add</span>
                    New Presentation
                </button>
            </div>
        </div>

    <?php if ($selectedSessionId > 0): ?>
        <div style="margin-bottom: 24px; padding: 16px; background: var(--panel); border: 1px solid var(--line); border-left: 4px solid var(--green); border-radius: 12px;">
            <p style="margin: 0; color: var(--muted); font-size: 14px;">
                Showing presentations for session #<?= (int) $selectedSessionId ?>.
                Uploads here will be attached to this session.
            </p>
        </div>
    <?php endif; ?>

    <!-- Search & Filters -->
    <div style="margin-bottom: 24px; padding: 20px; background: var(--panel); border: 1px solid var(--line); border-radius: 20px;">
        <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
            <input type="hidden" name="page" value="presentations">
            <?php if ($selectedSessionId > 0): ?>
                <input type="hidden" name="session_id" value="<?= (int) $selectedSessionId ?>">
            <?php endif; ?>
            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px;">Search</label>
                <div style="position: relative;">
                    <span class="material-symbols-rounded" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 20px;">search</span>
                    <input type="text" name="search" value="<?= le_esc($search) ?>" placeholder="Search presentations..." 
                           style="width: 100%; padding: 12px 12px 12px 40px; background: var(--panel-2); border: 1px solid var(--line); border-radius: 12px; color: var(--text); font-size: 14px;">
                </div>
            </div>
            <div style="min-width: 200px;">
                <label style="display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px;">Course</label>
                <select name="course_id" onchange="this.form.submit()" 
                        style="width: 100%; padding: 12px 16px; background: var(--panel-2); border: 1px solid var(--line); border-radius: 12px; color: var(--text); font-size: 14px;">
                    <option value="">All Courses</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= $course['id'] ?>" <?= $courseFilter === (int)$course['id'] ? 'selected' : '' ?>>
                            <?= le_esc($course['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="min-width: 150px;">
                <label style="display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px;">Sort by</label>
                <select name="sort" onchange="this.form.submit()"
                        style="width: 100%; padding: 12px 16px; background: var(--panel-2); border: 1px solid var(--line); border-radius: 12px; color: var(--text); font-size: 14px;">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                    <option value="views" <?= $sort === 'views' ? 'selected' : '' ?>>Most Viewed</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A-Z</option>
                </select>
            </div>
            <button type="submit" class="ld-btn primary">Search</button>
            <?php if ($search || $courseFilter): ?>
                <a href="?page=presentations" class="ld-btn secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Presentation Grid -->
    <?php if (empty($presentations)): ?>
        <div style="padding: 60px 20px; text-align: center; background: var(--panel); border: 1px solid var(--line); border-radius: 20px;">
            <span class="material-symbols-rounded" style="font-size: 48px; color: var(--muted); display: block; margin-bottom: 16px;">folder_open</span>
            <h3 style="font-size: 18px; font-weight: 600; color: var(--text); margin-bottom: 8px;">No Presentations Yet</h3>
            <p style="font-size: 14px; color: var(--muted); margin-bottom: 24px; max-width: 400px; margin-left: auto; margin-right: auto;">
                Upload a PDF, PowerPoint, or create a new presentation to get started.
            </p>
            <div style="display: flex; gap: 12px; justify-content: center;">
                <button class="ld-btn primary" onclick="showUploadModal()">
                    <span class="material-symbols-rounded">upload</span>
                    Upload Presentation
                </button>
                <button class="ld-btn secondary" onclick="showCreateModal()">
                    <span class="material-symbols-rounded">add</span>
                    Create Blank
                </button>
            </div>
        </div>
    <?php else: ?>
        <div class="ld-presentations-grid">
            <?php foreach ($presentations as $pres): ?>
                <div class="ld-presentation-card">
                    <!-- Thumbnail -->
                    <div class="ld-presentation-thumbnail">
                        <?php if (!empty($pres['thumbnail_path'])): ?>
                            <img src="<?= le_esc($pres['thumbnail_path']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <span class="material-symbols-rounded">description</span>
                        <?php endif; ?>
                        <div style="position: absolute; top: 12px; right: 12px; display: flex; gap: 4px;">
                            <span style="font-size: 10px; padding: 4px 8px; background: rgba(102,242,154,.12); color: var(--green-2); border-radius: 99px;">
                                <?= $pres['visibility'] ?? 'private' ?>
                            </span>
                            <?php if (!empty($pres['version']) && $pres['version'] > 1): ?>
                                <span style="font-size: 10px; padding: 4px 8px; background: var(--panel-2); color: var(--muted); border-radius: 99px;">v<?= $pres['version'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Content -->
                    <div style="padding: 20px; flex: 1; display: flex; flex-direction: column;">
                        <h3 style="font-size: 16px; font-weight: 600; margin: 0 0 8px; line-height: 1.3; color: var(--text);">
                            <?= le_esc($pres['title']) ?>
                        </h3>
                        <?php if (!empty($pres['description'])): ?>
                            <p style="font-size: 13px; color: var(--muted); margin: 0 0 12px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?= le_esc($pres['description']) ?>
                            </p>
                        <?php endif; ?>
                        
                        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px;">
                            <?php if (!empty($pres['course_name'])): ?>
                                <span style="font-size: 11px; padding: 4px 10px; background: var(--panel-2); color: var(--muted); border-radius: 99px;"><?= le_esc($pres['course_name']) ?></span>
                            <?php endif; ?>
                            <span style="font-size: 11px; padding: 4px 10px; background: var(--panel-2); color: var(--muted); border-radius: 99px;"><?= $pres['total_slides'] ?? 0 ?> slides</span>
                            <span style="font-size: 11px; padding: 4px 10px; background: var(--panel-2); color: var(--muted); border-radius: 99px;"><?= $pres['views'] ?? 0 ?> views</span>
                        </div>
                        
                        <div style="display: flex; align-items: center; gap: 4px; font-size: 11px; color: var(--muted); margin-top: auto;">
                            <span class="material-symbols-rounded" style="font-size: 14px;">schedule</span>
                            <?= date('M j, Y', strtotime($pres['created_at'])) ?>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div style="padding: 12px 20px; border-top: 1px solid var(--line); display: flex; gap: 8px; flex-wrap: wrap;">
                        <button class="ld-small-btn open" onclick="presentPresentation(<?= $pres['id'] ?>)">Present</button>
                        <button class="ld-small-btn" onclick="sharePresentation(<?= $pres['id'] ?>, '<?= le_esc($pres['title']) ?>')">Share</button>
                        <button class="ld-small-btn ld-icon-only" onclick="editPresentation(<?= $pres['id'] ?>)" title="Edit">
                            <span class="material-symbols-rounded">edit</span>
                        </button>
                        <button class="ld-small-btn ld-icon-only" onclick="duplicatePresentation(<?= $pres['id'] ?>)" title="Duplicate">
                            <span class="material-symbols-rounded">content_copy</span>
                        </button>
                        <button class="ld-small-btn ld-icon-only" onclick="deletePresentation(<?= $pres['id'] ?>)" title="Delete" style="color: var(--orange);">
                            <span class="material-symbols-rounded">delete</span>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPresentations > $perPage): 
            $totalPages = ceil($totalPresentations / $perPage);
        ?>
        <div style="display: flex; justify-content: center; gap: 8px; margin-top: 24px;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=presentations&p=<?= $i ?>&search=<?= urlencode($search) ?>&course_id=<?= $courseFilter ?>&sort=<?= $sort ?><?= $selectedSessionId ? '&session_id=' . (int) $selectedSessionId : '' ?>"
                   class="ld-btn <?= $i === $currentPage ? 'primary' : 'secondary' ?>"
                   style="min-width: 36px; padding: 10px 14px;">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- Upload Modal -->
<!-- ============================================================ -->
<div id="uploadModal" class="le-modal-overlay" style="display: none;">
    <div class="le-modal le-modal-lg">
        <div class="le-modal-header">
            <div style="display: flex; align-items: center; gap: var(--le-space-2);">
                <div style="width: 40px; height: 40px; border-radius: var(--le-radius-lg); background: var(--le-primary-lighter); display: flex; align-items: center; justify-content: center;">
                    <span class="material-symbols-rounded" style="color: var(--le-primary); font-size: 22px;">upload</span>
                </div>
                <h3 class="le-card-title">Upload Presentation</h3>
            </div>
            <button class="le-modal-close" onclick="closeModal('uploadModal')">&times;</button>
        </div>
        <form id="uploadForm" enctype="multipart/form-data" onsubmit="uploadPresentation(event)">
            <div class="le-modal-body">
                <!-- Drop Zone -->
                <div id="dropZone" data-dropzone="presentation-upload"
                     style="border: 2px dashed var(--le-gray-300); border-radius: var(--le-radius-xl); padding: var(--le-space-6); text-align: center; cursor: pointer; transition: all var(--le-transition); margin-bottom: var(--le-space-3);"
                     onmouseover="this.style.borderColor='var(--le-primary)'; this.style.background='var(--le-primary-lighter)'"
                     onmouseout="this.style.borderColor='var(--le-gray-300)'; this.style.background=''"
                     onclick="document.getElementById('fileInput').click()">
                    <div style="font-size: 3rem; margin-bottom: var(--le-space-2);">📄</div>
                    <h3 style="font-size: 1.1rem; margin-bottom: 4px;">Drop files here or click to upload</h3>
                    <p style="color: var(--le-gray-500); font-size: 0.9rem; margin: 0;">
                        Supports PDF and PowerPoint (.ppt / .pptx). Max 50MB.
                    </p>
                    <input type="file" id="fileInput" name="file" style="display: none;" accept=".pdf,.ppt,.pptx,application/pdf,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation" onchange="handleFileSelect(this)">
                </div>

                <div id="fileInfo" style="display: none; padding: var(--le-space-2) var(--le-space-3); background: var(--le-success-lighter); border-radius: var(--le-radius-lg); margin-bottom: var(--le-space-3);">
                    <div class="le-flex-between">
                        <span id="fileName" style="font-weight: var(--le-font-weight-medium);"></span>
                        <span id="fileSize" style="color: var(--le-gray-500); font-size: 0.85rem;"></span>
                    </div>
                </div>

                <div class="le-form-group">
                    <label class="le-label le-label-required">Session</label>
                    <select class="le-select" name="session_id" required>
                        <option value="">Select a session</option>
                        <?php foreach ($uploadSessions as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= $selectedSessionId === (int) $s['id'] ? 'selected' : '' ?>>
                                <?= UI::escape($s['title']) ?> (<?= UI::escape($s['session_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="le-form-group">
                    <label class="le-label le-label-required">Title</label>
                    <input type="text" class="le-input" name="title" required placeholder="Presentation title">
                </div>
                <div class="le-form-group">
                    <label class="le-label">Description</label>
                    <textarea class="le-textarea" name="description" placeholder="Brief description of this presentation" rows="2"></textarea>
                </div>
                <div class="le-grid le-grid-2">
                    <div class="le-form-group">
                        <label class="le-label">Course</label>
                        <select class="le-select" name="course_id">
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>"><?= UI::escape($course['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="le-form-group">
                        <label class="le-label">Visibility</label>
                        <select class="le-select" name="visibility">
                            <option value="private">Private</option>
                            <option value="course">Course Only</option>
                            <option value="institution">Institution</option>
                        </select>
                    </div>
                </div>
                <div class="le-form-group">
                    <label class="le-label">Tags</label>
                    <input type="text" class="le-input" name="tags" placeholder="e.g. algorithms, data-structures, lecture-1">
                </div>
                <?= le_csrf_field() ?>
            </div>
            <div class="le-modal-footer">
                <button type="button" class="le-btn le-btn-secondary" onclick="closeModal('uploadModal')">Cancel</button>
                <button type="submit" class="le-btn le-btn-primary" id="uploadBtn">
                    <span class="material-symbols-rounded" style="font-size: 20px;">cloud_upload</span>
                    Upload
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ============================================================ -->
<!-- Create Presentation Modal -->
<!-- ============================================================ -->
<div id="createPresModal" class="le-modal-overlay" style="display: none;">
    <div class="le-modal">
        <div class="le-modal-header">
            <div style="display: flex; align-items: center; gap: var(--le-space-2);">
                <div style="width: 40px; height: 40px; border-radius: var(--le-radius-lg); background: var(--le-primary-lighter); display: flex; align-items: center; justify-content: center;">
                    <span class="material-symbols-rounded" style="color: var(--le-primary); font-size: 22px;">slideshow</span>
                </div>
                <h3 class="le-card-title" style="font-size: 1.1rem;">New Presentation</h3>
            </div>
            <button class="le-modal-close" onclick="closeModal('createPresModal')">&times;</button>
        </div>
        <form id="createPresForm" onsubmit="createBlankPresentation(event)">
            <div class="le-modal-body">
                <div id="createPresStatus" role="status" aria-live="polite" hidden
                     style="margin-bottom: var(--le-space-3); padding: var(--le-space-2) var(--le-space-3); border-radius: var(--le-radius-lg); font-size: var(--le-font-size-sm);"></div>
                <div class="le-form-group">
                    <label class="le-label le-label-required">Presentation Title</label>
                    <input type="text" class="le-input" name="title" required placeholder="e.g. Presentation 1 - Introduction to ...">
                </div>
                <div class="le-form-group">
                    <label class="le-label">Description (optional)</label>
                    <textarea class="le-textarea" name="description" placeholder="Brief description of this presentation" rows="3"></textarea>
                </div>
                <div class="le-form-group">
                    <label class="le-label le-label-required">Session</label>
                    <select class="le-select" name="session_id" required>
                        <option value="">Select a session</option>
                        <?php
                        // Get lecturer's sessions for the dropdown
                        $sessionModel = new \LE\Models\SessionModel();
                        $sessions = $sessionModel->getLecturerActiveSessions($userId);
                        $sessions = array_merge($sessions, $sessionModel->getLecturerScheduledSessions($userId));
                        foreach ($sessions as $s):
                        ?>
                        <option value="<?= (int)$s['id'] ?>" <?= ((int)le_get('session_id', 0, true) === (int)$s['id']) ? 'selected' : '' ?>>
                            <?= UI::escape($s['title']) ?> (<?= UI::escape($s['session_code']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="le-grid le-grid-2">
                    <div class="le-form-group">
                        <label class="le-label">Total Slides</label>
                        <input type="number" class="le-input" name="total_slides" value="5" min="1" max="100">
                    </div>
                    <div class="le-form-group">
                        <label class="le-label">Slide Duration (sec)</label>
                        <input type="number" class="le-input" name="slide_duration" value="30" min="5" max="600">
                    </div>
                </div>
                <?= le_csrf_field() ?>
            </div>
            <div class="le-modal-footer">
                <button type="button" class="le-btn le-btn-secondary" onclick="closeModal('createPresModal')">Cancel</button>
                <button type="submit" class="le-btn le-btn-primary" id="createPresBtn">
                    <span class="material-symbols-rounded" style="font-size: 20px;">slideshow</span>
                    Create Presentation
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    if (typeof LiveEngagement !== 'undefined') {
        LiveEngagement.init({ isPresenter: true });
    }

    async function createBlankPresentation(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        const btn = document.getElementById('createPresBtn');
        const status = document.getElementById('createPresStatus');

        status.hidden = true;
        btn.disabled = true;
        btn.innerHTML = '<div class="le-spinner le-spinner-sm" style="border-color: rgba(255,255,255,0.3); border-top-color: white;"></div> Creating...';

        try {
            const response = await fetch('<?= le_module_url('api/presentation.php') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({ action: 'create', ...data }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.error || 'Failed to create presentation');
            }
            status.textContent = 'Presentation created successfully!';
            status.style.color = 'var(--le-success)';
            status.hidden = false;
            LiveEngagement.showToast('Presentation created', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            status.textContent = error.message || 'Unable to create presentation.';
            status.style.color = 'var(--le-danger)';
            status.hidden = false;
            LiveEngagement.showToast(error.message || 'Failed to create', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-rounded" style="font-size: 20px;">slideshow</span> Create Presentation';
        }
    }

    function showUploadModal() {
        document.getElementById('uploadModal').style.display = 'flex';
    }

    function showCreateModal() {
        window.location.href = '<?= le_page_url('create_presentation') ?>' + (<?= json_encode($selectedSessionId ?: 0) ?> ? '&session_id=' + <?= json_encode($selectedSessionId ?: 0) ?> : '');
    }

    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    function handleFileSelect(input) {
        const file = input.files[0];
        if (!file) return;
        
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileSize').textContent = formatFileSize(file.size);
        document.getElementById('fileInfo').style.display = 'block';

        const titleInput = document.querySelector('#uploadForm input[name="title"]');
        if (titleInput && !titleInput.value.trim()) {
            titleInput.value = file.name.replace(/\.[^.]+$/, '');
        }
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    async function uploadPresentation(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        const btn = document.getElementById('uploadBtn');
        
        btn.disabled = true;
        btn.innerHTML = '<div class="le-spinner le-spinner-sm" style="border-color: rgba(255,255,255,0.3); border-top-color: white;"></div> Uploading...';

        try {
            formData.append('action', 'upload');
            const response = await fetch('<?= le_module_url('api/upload.php') ?>', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });
            const result = await response.json();
            
            if (result.success) {
                LiveEngagement.showToast('Presentation uploaded successfully', 'success');
                closeModal('uploadModal');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                throw new Error(result.error || 'Upload failed');
            }
        } catch (error) {
            LiveEngagement.showToast(error.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-rounded" style="font-size: 20px;">cloud_upload</span> Upload';
        }
    }

    async function presentPresentation(id) {
        try {
            await fetch('<?= le_module_url('api/presentation.php') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ action: 'set_active', presentation_id: id, active: true }),
            });
        } catch (error) {
            LiveEngagement.showToast('Opening presenter…', 'info');
        }

        window.location.href = '?page=presenter&presentation_id=' + id;
    }

    function openPresentation(id) {
        presentPresentation(id);
    }

    async function sharePresentation(id, title) {
        try {
            // Mark presentation as public and get share token
            const response = await fetch('<?= le_module_url('api/presentation.php') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({ action: 'make_public', id: id }),
            });
            const result = await response.json();
            
            if (!response.ok || !result.success) {
                throw new Error(result.error || 'Failed to make presentation public');
            }
            
            // Generate shareable link with presentation code
            const shareUrl = window.location.origin + window.location.pathname + '?page=join&presentation_id=' + id;
            
            // Create modal to show share options
            const modalHtml = `
                <div class="le-modal-overlay" id="shareModal" style="display: flex;">
                    <div class="le-modal">
                        <div class="le-modal-header">
                            <h3 style="margin: 0;">Share Presentation</h3>
                            <button class="le-modal-close" onclick="closeModal('shareModal')">&times;</button>
                        </div>
                        <div class="le-modal-body">
                            <p style="margin-bottom: 16px; color: var(--le-gray-600);">
                                Share "${title}" with anyone using the link below:
                            </p>
                            <div style="display: flex; gap: 8px; margin-bottom: 16px;">
                                <input type="text" id="shareLinkInput" value="${shareUrl}" readonly
                                       style="flex: 1; padding: 10px 12px; border: 1px solid var(--le-gray-200); border-radius: 8px; background: var(--le-gray-50);">
                                <button onclick="copyShareLink()" class="le-btn le-btn-primary">
                                    <span class="material-symbols-rounded" style="font-size: 16px;">content_copy</span>
                                    Copy
                                </button>
                            </div>
                            <div style="background: var(--le-success-lighter); padding: 12px; border-radius: 8px; font-size: 13px; color: var(--le-gray-600);">
                                <strong>✓ Public Access Enabled</strong><br>
                                Anyone with this link can view the presentation without logging in.
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('shareModal');
            if (existingModal) existingModal.remove();
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            LiveEngagement.showToast('Presentation is now public', 'success');
        } catch (error) {
            LiveEngagement.showToast(error.message || 'Failed to enable public sharing', 'error');
        }
    }

    function copyShareLink() {
        const input = document.getElementById('shareLinkInput');
        input.select();
        document.execCommand('copy');
        LiveEngagement.showToast('Link copied to clipboard', 'success');
    }

    function editPresentation(id) {
        window.location.href = '?page=edit_presentation&id=' + id;
    }

    async function duplicatePresentation(id) {
        try {
            const response = await fetch('<?= le_module_url('api/presentation.php') ?>?action=duplicate&id=' + id, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });
            const result = await response.json();
            if (result.success) {
                LiveEngagement.showToast('Presentation duplicated', 'success');
                setTimeout(() => window.location.reload(), 1000);
            }
        } catch (error) {
            LiveEngagement.showToast('Failed to duplicate', 'error');
        }
    }

    async function deletePresentation(id) {
        if (!confirm('Delete this presentation permanently? This cannot be undone.')) return;
        try {
            const response = await fetch('<?= le_module_url('api/presentation.php') ?>?action=delete&id=' + id, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });
            if (response.ok) {
                LiveEngagement.showToast('Presentation deleted', 'success');
                setTimeout(() => window.location.reload(), 1000);
            }
        } catch (error) {
            LiveEngagement.showToast('Failed to delete', 'error');
        }
    }
</script>

<?php
Layout::end();