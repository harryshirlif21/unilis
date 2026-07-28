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

    $presentations = $presentationModel->getUserPresentations($userId, $search, $courseFilter, $sort, $currentPage, $perPage);
    $totalPresentations = $presentationModel->countUserPresentations($userId, $search, $courseFilter);

    // Get courses for filter
    $courses = $db->select("SELECT id, name FROM courses ORDER BY name");
} catch (Exception $e) {
    error_log("Presentations query error: " . $e->getMessage());
    $presentations = [];
    $totalPresentations = 0;
    $courses = [];
}

Layout::start([
    'title' => 'Presentations',
    'layout' => 'app',
    'activeNav' => 'presentations',
]);
?>

<div class="le-container le-page-enter">
    <!-- ============================================================ -->
    <!-- Page Header -->
    <!-- ============================================================ -->
    <div style="margin-bottom: var(--le-space-4);">
        <div class="le-flex-between" style="flex-wrap: wrap; gap: var(--le-space-2);">
            <div style="display: flex; align-items: center; gap: var(--le-space-2);">
                <div style="width: 48px; height: 48px; border-radius: var(--le-radius-xl); background: linear-gradient(135deg, var(--le-primary), var(--le-primary-light)); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(27,94,32,0.3);">
                    <span class="material-symbols-rounded" style="font-size: 28px; color: white;">slideshow</span>
                </div>
                <div>
                    <h1 style="font-size: var(--le-font-size-3xl); font-weight: var(--le-font-weight-bold); margin: 0;">Presentations</h1>
                    <p style="color: var(--le-gray-500); margin: 2px 0 0; font-size: var(--le-font-size-sm);">
                        <?= $totalPresentations ?> presentation<?= $totalPresentations !== 1 ? 's' : '' ?> in your library
                    </p>
                </div>
            </div>
            <div style="display: flex; gap: var(--le-space-2); flex-wrap: wrap; align-items: center;">
                <?= UI::themeSwitcher() ?>
                <button class="le-btn le-btn-primary le-btn-lg" onclick="showUploadModal()">
                    <span class="material-symbols-rounded" style="font-size: 20px;">upload</span>
                    Upload
                </button>
                <button class="le-btn le-btn-primary le-btn-lg" onclick="showCreateModal()">
                    <span class="material-symbols-rounded" style="font-size: 20px;">add</span>
                    New Presentation
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- Search & Filters -->
    <!-- ============================================================ -->
    <div class="le-card-solid" style="margin-bottom: var(--le-space-4);">
        <form method="GET" style="display: flex; gap: var(--le-space-2); flex-wrap: wrap; align-items: flex-end;">
            <input type="hidden" name="page" value="presentations">
            <div style="flex: 1; min-width: 200px;">
                <label class="le-label">Search</label>
                <div class="le-input-group">
                    <span class="le-input-group-addon">
                        <span class="material-symbols-rounded" style="font-size: 20px;">search</span>
                    </span>
                    <input type="text" class="le-input" name="search" value="<?= UI::escape($search) ?>" placeholder="Search presentations..." style="border: none;">
                </div>
            </div>
            <div style="min-width: 200px;">
                <label class="le-label">Course</label>
                <select class="le-select" name="course_id" onchange="this.form.submit()">
                    <option value="">All Courses</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= $course['id'] ?>" <?= $courseFilter === (int)$course['id'] ? 'selected' : '' ?>>
                            <?= UI::escape($course['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="min-width: 150px;">
                <label class="le-label">Sort by</label>
                <select class="le-select" name="sort" onchange="this.form.submit()">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                    <option value="views" <?= $sort === 'views' ? 'selected' : '' ?>>Most Viewed</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A-Z</option>
                </select>
            </div>
            <button type="submit" class="le-btn le-btn-primary" style="margin-bottom: 0;">Search</button>
            <?php if ($search || $courseFilter): ?>
                <a href="?page=presentations" class="le-btn le-btn-ghost" style="margin-bottom: 0;">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ============================================================ -->
    <!-- Presentation Grid -->
    <!-- ============================================================ -->
    <?php if (empty($presentations)): ?>
        <div class="le-card-solid">
            <?= UI::emptyState(
                '📁',
                'No Presentations Yet',
                'Upload a PDF, PowerPoint, or create a new presentation to get started. Your presentations will appear here with previews and version history.',
                UI::button('Upload Presentation', 'primary', [
                    'icon' => 'upload',
                    'size' => 'lg',
                    'onclick' => 'showUploadModal()'
                ]) . ' ' .
                UI::button('Create Blank', 'secondary', [
                    'icon' => 'add',
                    'size' => 'lg',
                    'onclick' => 'showCreateModal()'
                ])
            ) ?>
        </div>
    <?php else: ?>
        <div class="le-grid le-grid-auto" style="gap: var(--le-space-3);">
            <?php foreach ($presentations as $pres): ?>
                <div class="le-card-solid" style="padding: 0; overflow: hidden; display: flex; flex-direction: column;">
                    <!-- Thumbnail -->
                    <div style="height: 160px; background: linear-gradient(135deg, var(--le-primary-lighter), var(--le-gray-100)); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                        <?php if (!empty($pres['thumbnail_path'])): ?>
                            <img src="<?= UI::escape($pres['thumbnail_path']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <span class="material-symbols-rounded" style="font-size: 48px; color: var(--le-gray-300);">description</span>
                        <?php endif; ?>
                        <div style="position: absolute; top: var(--le-space-2); right: var(--le-space-2); display: flex; gap: 4px;">
                            <span class="le-badge le-badge-<?= $pres['visibility'] ?? 'private' ?>" style="font-size: 0.65rem;">
                                <?= $pres['visibility'] ?? 'private' ?>
                            </span>
                            <?php if (!empty($pres['version']) && $pres['version'] > 1): ?>
                                <span class="le-badge le-badge-neutral" style="font-size: 0.65rem;">v<?= $pres['version'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Content -->
                    <div style="padding: var(--le-space-3); flex: 1; display: flex; flex-direction: column;">
                        <h3 style="font-size: var(--le-font-size-base); font-weight: var(--le-font-weight-semibold); margin: 0 0 4px; line-height: 1.3;">
                            <?= UI::escape($pres['title']) ?>
                        </h3>
                        <?php if (!empty($pres['description'])): ?>
                            <p style="font-size: var(--le-font-size-sm); color: var(--le-gray-500); margin: 0 0 var(--le-space-2); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?= UI::escape($pres['description']) ?>
                            </p>
                        <?php endif; ?>
                        
                        <div style="display: flex; gap: var(--le-space-1); flex-wrap: wrap; margin-bottom: var(--le-space-2);">
                            <?php if (!empty($pres['course_name'])): ?>
                                <span class="le-tag"><?= UI::escape($pres['course_name']) ?></span>
                            <?php endif; ?>
                            <span class="le-tag"><?= $pres['total_slides'] ?? 0 ?> slides</span>
                            <span class="le-tag"><?= $pres['views'] ?? 0 ?> views</span>
                        </div>
                        
                        <div style="display: flex; align-items: center; gap: var(--le-space-1); font-size: var(--le-font-size-xs); color: var(--le-gray-400); margin-top: auto;">
                            <span class="material-symbols-rounded" style="font-size: 14px;">schedule</span>
                            <?= date('M j, Y', strtotime($pres['created_at'])) ?>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div style="padding: var(--le-space-2) var(--le-space-3); border-top: 1px solid var(--le-gray-100); display: flex; gap: 4px; flex-wrap: wrap;">
                        <button class="le-btn le-btn-sm le-btn-primary" onclick="openPresentation(<?= $pres['id'] ?>)">
                            <span class="material-symbols-rounded" style="font-size: 16px;">play_arrow</span>
                            Open
                        </button>
                        <button class="le-btn le-btn-sm le-btn-secondary" onclick="editPresentation(<?= $pres['id'] ?>)">
                            <span class="material-symbols-rounded" style="font-size: 16px;">edit</span>
                        </button>
                        <button class="le-btn le-btn-sm le-btn-ghost" onclick="duplicatePresentation(<?= $pres['id'] ?>)">
                            <span class="material-symbols-rounded" style="font-size: 16px;">content_copy</span>
                        </button>
                        <button class="le-btn le-btn-sm le-btn-ghost" onclick="deletePresentation(<?= $pres['id'] ?>)" style="color: var(--le-danger);">
                            <span class="material-symbols-rounded" style="font-size: 16px;">delete</span>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPresentations > $perPage): 
            $totalPages = ceil($totalPresentations / $perPage);
        ?>
        <div style="display: flex; justify-content: center; gap: var(--le-space-1); margin-top: var(--le-space-4);">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=presentations&p=<?= $i ?>&search=<?= urlencode($search) ?>&course_id=<?= $courseFilter ?>&sort=<?= $sort ?>"
                   class="le-btn le-btn-sm <?= $i === $currentPage ? 'le-btn-primary' : 'le-btn-ghost' ?>"
                   style="min-width: 36px;">
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
                        Supports PDF, PPTX, images, and video (max 50MB)
                    </p>
                    <input type="file" id="fileInput" name="file" style="display: none;" accept=".pdf,.pptx,.jpg,.jpeg,.png,.gif,.mp4,.webm" onchange="handleFileSelect(this)">
                </div>

                <div id="fileInfo" style="display: none; padding: var(--le-space-2) var(--le-space-3); background: var(--le-success-lighter); border-radius: var(--le-radius-lg); margin-bottom: var(--le-space-3);">
                    <div class="le-flex-between">
                        <span id="fileName" style="font-weight: var(--le-font-weight-medium);"></span>
                        <span id="fileSize" style="color: var(--le-gray-500); font-size: 0.85rem;"></span>
                    </div>
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

    function openPresentation(id) {
        window.location.href = '?page=presenter&presentation_id=' + id;
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