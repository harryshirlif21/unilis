<?php
/**
 * Live Engagement Module - Create Presentation (Step Builder)
 * 
 * Step-by-step presentation creation with sequential slide management.
 * Each presentation is tied to a session.
 * 
 * @package UNILIS\LiveEngagement\Views
 * @version 1.0.0
 */

require_once __DIR__ . '/../bootstrap.php';
le_require_auth();

use LE\Components\Layout;
use LE\Components\UI;

$userId = le_current_user_id();
$role = le_current_user_role();

if (!le_can_present()) {
    header('Location: ' . le_page_url('join'));
    exit;
}

$db = le_db();

// Get sessions for dropdown
$sessionModel = new \LE\Models\SessionModel();
$sessions = array_merge(
    $sessionModel->getLecturerActiveSessions($userId),
    $sessionModel->getLecturerScheduledSessions($userId)
);
$selectedSessionId = (int)le_get('session_id', 0, true);

// Handle POST: save presentation + redirect to slide editor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $sessionId = (int)($_POST['session_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $totalSlides = max(1, min(100, (int)($_POST['total_slides'] ?? 5)));

    if (!$sessionId || $title === '') {
        $error = 'Session and title are required.';
    } else {
        try {
            $presModel = new \LE\Models\PresentationModel();
            $presId = $presModel->create([
                'session_id'     => $sessionId,
                'title'          => $title,
                'description'    => $description,
                'total_slides'   => $totalSlides,
                'current_slide'  => 0,
                'is_active'      => 0,
                'allow_download' => 1,
                'allow_annotations' => 1,
                'file_type'      => 'blank',
            ]);

            if ($presId) {
                // Create blank slides
                $slideModel = new \LE\Models\SlideModel();
                for ($i = 1; $i <= $totalSlides; $i++) {
                    $slideModel->create([
                        'presentation_id'   => $presId,
                        'slide_number'      => $i,
                        'content_html'      => '<h2>Slide ' . $i . '</h2><p>Add your content here...</p>',
                        'duration_seconds'  => (int)($_POST['slide_duration'] ?? 30),
                    ]);
                }

                header('Location: ' . le_page_url('edit_presentation', ['id' => $presId]));
                exit;
            }
            $error = 'Failed to create presentation record.';
        } catch (\Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

Layout::start([
    'title' => 'Create Presentation',
    'layout' => 'app',
    'activeNav' => 'presentations',
]);
?>

<div class="le-container le-page-enter" style="max-width: 720px;">
    <!-- ============================================================ -->
    <!-- Page Header -->
    <!-- ============================================================ -->
    <div style="margin-bottom: var(--le-space-4);">
        <a href="<?= le_page_url('presentations') ?>" style="display: inline-flex; align-items: center; gap: 6px; color: var(--le-gray-500); text-decoration: none; font-size: 0.9rem; margin-bottom: var(--le-space-2);">
            <span class="material-symbols-rounded" style="font-size: 18px;">arrow_back</span>
            Back to Presentations
        </a>
        <div style="display: flex; align-items: center; gap: var(--le-space-2);">
            <div style="width: 48px; height: 48px; border-radius: var(--le-radius-xl); background: linear-gradient(135deg, var(--le-primary), var(--le-primary-light)); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(27,94,32,0.3);">
                <span class="material-symbols-rounded" style="font-size: 28px; color: white;">slideshow</span>
            </div>
            <div>
                <h1 style="font-size: var(--le-font-size-2xl); font-weight: var(--le-font-weight-bold); margin: 0;">Create Presentation</h1>
                <p style="color: var(--le-gray-500); margin: 2px 0 0; font-size: var(--le-font-size-sm);">
                    Set up your presentation and slides step by step
                </p>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- Error message -->
    <!-- ============================================================ -->
    <?php if (isset($error)): ?>
        <div style="background: var(--le-danger-lighter); border: 1px solid var(--le-danger); border-radius: var(--le-radius-lg); padding: var(--le-space-2) var(--le-space-3); margin-bottom: var(--le-space-3); color: var(--le-danger); font-size: 0.9rem;">
            <span class="material-symbols-rounded" style="font-size: 18px; vertical-align: middle; margin-right: 6px;">error</span>
            <?= UI::escape($error) ?>
        </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- Step 1: Presentation Info -->
    <!-- ============================================================ -->
    <div class="le-card-solid" style="margin-bottom: var(--le-space-3);">
        <div class="le-card-header">
            <div style="display: flex; align-items: center; gap: var(--le-space-2);">
                <div style="width: 32px; height: 32px; border-radius: var(--le-radius-full); background: var(--le-primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700;">1</div>
                <h2 class="le-card-title">Presentation Details</h2>
            </div>
        </div>
        <form method="POST" action="" style="padding: var(--le-space-3);">
            <input type="hidden" name="action" value="create">

            <div class="le-form-group">
                <label class="le-label le-label-required">Session</label>
                <select class="le-select" name="session_id" required>
                    <option value="">Select a session</option>
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= $selectedSessionId === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= UI::escape($s['title']) ?> (<?= UI::escape($s['session_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($sessions)): ?>
                    <p style="font-size: 0.8rem; color: var(--le-warning); margin-top: 4px;">
                        No sessions available. <a href="<?= le_page_url('dashboard') ?>" style="color: var(--le-primary);">Create a session first</a>.
                    </p>
                <?php endif; ?>
            </div>

            <div class="le-form-group">
                <label class="le-label le-label-required">Presentation Title</label>
                <input type="text" class="le-input" name="title" required
                       placeholder="e.g. Presentation 1 - Introduction to Algorithms"
                       value="<?= UI::escape(le_get('title', '')) ?>">
            </div>

            <div class="le-form-group">
                <label class="le-label">Description (optional)</label>
                <textarea class="le-textarea" name="description" rows="3"
                          placeholder="What is this presentation about?"><?= UI::escape(le_get('description', '')) ?></textarea>
            </div>

            <!-- ============================================================ -->
            <!-- Step 2: Slides Configuration -->
            <!-- ============================================================ -->
            <div style="margin-top: var(--le-space-4);">
                <div style="display: flex; align-items: center; gap: var(--le-space-2); margin-bottom: var(--le-space-3);">
                    <div style="width: 32px; height: 32px; border-radius: var(--le-radius-full); background: var(--le-primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700;">2</div>
                    <h2 class="le-card-title" style="margin: 0;">Slides Configuration</h2>
                </div>
            </div>

            <div class="le-grid le-grid-2" style="margin-bottom: var(--le-space-3);">
                <div class="le-form-group">
                    <label class="le-label">Number of Slides</label>
                    <input type="number" class="le-input" name="total_slides" value="5" min="1" max="100">
                    <p style="font-size: 0.75rem; color: var(--le-gray-400); margin-top: 2px;">You can add/remove slides later.</p>
                </div>
                <div class="le-form-group">
                    <label class="le-label">Default Slide Duration (sec)</label>
                    <input type="number" class="le-input" name="slide_duration" value="30" min="5" max="600">
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- Slide preview (visual indicator) -->
            <!-- ============================================================ -->
            <div style="background: var(--le-gray-50); border-radius: var(--le-radius-lg); padding: var(--le-space-3); margin-bottom: var(--le-space-3);">
                <p style="font-size: 0.85rem; color: var(--le-gray-500); margin: 0 0 var(--le-space-2); font-weight: 500;">Slide Preview</p>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;" id="slidePreview">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <div style="width: 60px; height: 45px; background: white; border: 2px solid var(--le-gray-300); border-radius: var(--le-radius-sm); display: flex; align-items: center; justify-content: center; font-size: 0.7rem; color: var(--le-gray-500); font-weight: 500;">
                            <?= $i ?>
                        </div>
                    <?php endfor; ?>
                </div>
                <p style="font-size: 0.75rem; color: var(--le-gray-400); margin: var(--le-space-1) 0 0;">
                    After creation, you'll be able to edit each slide individually with full content.
                </p>
            </div>

            <div style="display: flex; gap: var(--le-space-2); justify-content: flex-end; padding-top: var(--le-space-2); border-top: 1px solid var(--le-gray-200);">
                <a href="<?= le_page_url('presentations') ?>" class="le-btn le-btn-secondary">Cancel</a>
                <button type="submit" class="le-btn le-btn-primary le-btn-lg">
                    <span class="material-symbols-rounded" style="font-size: 20px;">rocket_launch</span>
                    Create & Edit Slides
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Live update slide preview when total_slides changes
    document.querySelector('input[name="total_slides"]')?.addEventListener('input', function() {
        const count = Math.min(100, Math.max(1, parseInt(this.value) || 1));
        const container = document.getElementById('slidePreview');
        container.innerHTML = '';
        for (let i = 1; i <= Math.min(count, 30); i++) {
            const div = document.createElement('div');
            div.style.cssText = 'width: 60px; height: 45px; background: white; border: 2px solid var(--le-gray-300); border-radius: var(--le-radius-sm); display: flex; align-items: center; justify-content: center; font-size: 0.7rem; color: var(--le-gray-500); font-weight: 500;';
            div.textContent = i;
            container.appendChild(div);
        }
        if (count > 30) {
            const more = document.createElement('div');
            more.style.cssText = 'width: 60px; height: 45px; background: var(--le-gray-100); border: 2px dashed var(--le-gray-300); border-radius: var(--le-radius-sm); display: flex; align-items: center; justify-content: center; font-size: 0.6rem; color: var(--le-gray-400);';
            more.textContent = '+' + (count - 30);
            container.appendChild(more);
        }
    });

    if (typeof LiveEngagement !== 'undefined') {
        LiveEngagement.init({ isPresenter: true });
    }
</script>

<?php Layout::end(); ?>
