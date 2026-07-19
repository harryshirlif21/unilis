<?php
/**
 * Live Engagement Module - Edit Presentation (Menti-Style)
 * 
 * Interactive slide editor with Mentimeter-style slide types:
 * - Multiple Choice, Word Cloud, Open Text, Rating, Ranking, Quiz, Q&A
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

if (!le_has_role(['lecturer', 'admin'])) {
    header('Location: ' . le_page_url('join'));
    exit;
}

$presentationId = (int)le_get('id', 0, true);
if (!$presentationId) {
    header('Location: ' . le_page_url('presentations'));
    exit;
}

$presModel = new \LE\Models\PresentationModel();
$presentation = $presModel->find($presentationId);

if (!$presentation) {
    http_response_code(404);
    exit('Presentation not found.');
}

// Get slides
$slides = $presModel->getSlides($presentationId);

// Slide type definitions (Menti-style)
$slideTypes = [
    'content'     => ['icon' => 'article',         'label' => 'Content',      'color' => '#1B5E20'],
    'multiple_choice' => ['icon' => 'checklist',    'label' => 'Multiple Choice', 'color' => '#E65100'],
    'word_cloud'  => ['icon' => 'cloud',            'label' => 'Word Cloud',   'color' => '#1565C0'],
    'open_text'   => ['icon' => 'text_fields',      'label' => 'Open Text',    'color' => '#6A1B9A'],
    'rating'      => ['icon' => 'star',             'label' => 'Rating',       'color' => '#2E7D32'],
    'ranking'     => ['icon' => 'format_list_numbered', 'label' => 'Ranking',  'color' => '#C62828'],
    'quiz'        => ['icon' => 'quiz',             'label' => 'Quiz',         'color' => '#F9A825'],
    'qa'          => ['icon' => 'question_answer',  'label' => 'Q&A',          'color' => '#00838F'],
];

// Handle POST updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_details') {
        $presModel->update($presentationId, [
            'title'       => trim($_POST['title'] ?? $presentation['title']),
            'description' => trim($_POST['description'] ?? ''),
        ]);
        header('Location: ' . le_page_url('edit_presentation', ['id' => $presentationId]) . '&saved=1');
        exit;
    }

    if ($action === 'update_slide' && isset($_POST['slide_id'])) {
        $slideId = (int)$_POST['slide_id'];
        $slideModel = new \LE\Models\SlideModel();
        
        $updateData = [
            'content_html'     => $_POST['content_html'] ?? '',
            'duration_seconds' => (int)($_POST['duration_seconds'] ?? 30),
            'notes'            => trim($_POST['notes'] ?? ''),
        ];
        
        // Store slide type and options as JSON in notes field (prefixed)
        if (isset($_POST['slide_type'])) {
            $type = $_POST['slide_type'];
            $options = [];
            
            // Collect type-specific options
            if ($type === 'multiple_choice' && isset($_POST['mc_options'])) {
                $options['choices'] = array_values(array_filter($_POST['mc_options'], fn($v) => trim($v) !== ''));
                $options['allow_multiple'] = !empty($_POST['mc_allow_multiple']);
            }
            if ($type === 'word_cloud') {
                $options['max_words'] = (int)($_POST['wc_max_words'] ?? 30);
            }
            if ($type === 'rating') {
                $options['max_rating'] = (int)($_POST['rating_max'] ?? 5);
                $options['labels'] = [
                    'low'  => trim($_POST['rating_low'] ?? 'Poor'),
                    'high' => trim($_POST['rating_high'] ?? 'Excellent'),
                ];
            }
            if ($type === 'ranking') {
                $options['items'] = array_values(array_filter($_POST['rank_items'] ?? [], fn($v) => trim($v) !== ''));
            }
            if ($type === 'quiz' && isset($_POST['quiz_question'])) {
                $options['question'] = trim($_POST['quiz_question']);
                $options['choices'] = array_values(array_filter($_POST['quiz_options'] ?? [], fn($v) => trim($v) !== ''));
                $options['correct'] = (int)($_POST['quiz_correct'] ?? 0);
            }
            
            $updateData['notes'] = json_encode([
                'type'    => $type,
                'options' => $options,
                'prompt'  => trim($_POST['slide_prompt'] ?? ''),
            ]);
        }
        
        $slideModel->update($slideId, $updateData);
        header('Location: ' . le_page_url('edit_presentation', ['id' => $presentationId]) . '&saved=1');
        exit;
    }

    if ($action === 'add_slide') {
        $slideModel = new \LE\Models\SlideModel();
        $nextNumber = count($slides) + 1;
        $slideModel->create([
            'presentation_id'  => $presentationId,
            'slide_number'     => $nextNumber,
            'content_html'     => '<h2>Slide ' . $nextNumber . '</h2><p>Add your content here...</p>',
            'duration_seconds' => 30,
            'notes'            => json_encode(['type' => 'content', 'options' => [], 'prompt' => '']),
        ]);
        $presModel->update($presentationId, ['total_slides' => $nextNumber]);
        header('Location: ' . le_page_url('edit_presentation', ['id' => $presentationId]) . '&saved=1');
        exit;
    }

    if ($action === 'delete_slide' && isset($_POST['slide_id'])) {
        $slideModel = new \LE\Models\SlideModel();
        $slideModel->delete((int)$_POST['slide_id']);
        // Renumber remaining slides
        $remaining = $presModel->getSlides($presentationId);
        foreach ($remaining as $i => $s) {
            $slideModel->update((int)$s['id'], ['slide_number' => $i + 1]);
        }
        $presModel->update($presentationId, ['total_slides' => count($remaining)]);
        header('Location: ' . le_page_url('edit_presentation', ['id' => $presentationId]) . '&saved=1');
        exit;
    }
}

$saved = isset($_GET['saved']);

Layout::start([
    'title' => 'Edit: ' . $presentation['title'],
    'layout' => 'app',
    'activeNav' => 'presentations',
]);
?>

<style>
/* Menti-style slide type buttons */
.le-slide-type-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 8px;
    margin-bottom: 16px;
}
.le-slide-type-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 14px 8px;
    border: 2px solid var(--le-gray-200);
    border-radius: var(--le-radius-lg);
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--le-gray-600);
    text-align: center;
}
.le-slide-type-btn:hover {
    border-color: var(--le-primary);
    background: var(--le-primary-lighter);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.le-slide-type-btn.active {
    border-color: var(--le-primary);
    background: var(--le-primary-lighter);
    color: var(--le-primary);
    font-weight: 600;
}
.le-slide-type-btn .material-symbols-rounded {
    font-size: 28px;
}
.le-slide-preview {
    background: var(--le-gray-50);
    border: 2px dashed var(--le-gray-300);
    border-radius: var(--le-radius-xl);
    padding: 32px;
    text-align: center;
    min-height: 200px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-bottom: 16px;
}
.le-slide-preview .preview-icon {
    font-size: 48px;
    opacity: 0.3;
}
.le-slide-preview .preview-label {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--le-gray-500);
}
.le-option-row {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 8px;
}
.le-option-row input[type="text"] {
    flex: 1;
}
.le-option-row .le-btn-icon {
    width: 32px;
    height: 32px;
    border-radius: var(--le-radius-full);
    border: 1px solid var(--le-gray-200);
    background: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--le-danger);
    transition: all 0.15s ease;
}
.le-option-row .le-btn-icon:hover {
    background: var(--le-danger-lighter);
    border-color: var(--le-danger);
}
.le-add-option-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    border: 1px dashed var(--le-gray-300);
    border-radius: var(--le-radius-sm);
    background: transparent;
    cursor: pointer;
    font-size: 0.8rem;
    color: var(--le-gray-500);
    transition: all 0.15s ease;
}
.le-add-option-btn:hover {
    border-color: var(--le-primary);
    color: var(--le-primary);
    background: var(--le-primary-lighter);
}
</style>

<div class="le-container le-page-enter" style="max-width: 960px;">
    <!-- Header -->
    <div style="margin-bottom: var(--le-space-4);">
        <a href="<?= le_page_url('presentations') ?>" style="display: inline-flex; align-items: center; gap: 6px; color: var(--le-gray-500); text-decoration: none; font-size: 0.9rem; margin-bottom: var(--le-space-2);">
            <span class="material-symbols-rounded" style="font-size: 18px;">arrow_back</span>
            Back to Presentations
        </a>
        <div style="display: flex; align-items: center; gap: var(--le-space-2);">
            <div style="width: 48px; height: 48px; border-radius: var(--le-radius-xl); background: linear-gradient(135deg, var(--le-primary), var(--le-primary-light)); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(27,94,32,0.3);">
                <span class="material-symbols-rounded" style="font-size: 28px; color: white;">edit_note</span>
            </div>
            <div>
                <h1 style="font-size: var(--le-font-size-2xl); font-weight: var(--le-font-weight-bold); margin: 0;"><?= UI::escape($presentation['title']) ?></h1>
                <p style="color: var(--le-gray-500); margin: 2px 0 0; font-size: var(--le-font-size-sm);">
                    <?= count($slides) ?> slide<?= count($slides) !== 1 ? 's' : '' ?> &middot; Created <?= date('M j, Y', strtotime($presentation['created_at'])) ?>
                </p>
            </div>
        </div>
    </div>

    <?php if ($saved): ?>
        <div style="background: var(--le-success-lighter); border: 1px solid var(--le-success); border-radius: var(--le-radius-lg); padding: var(--le-space-2) var(--le-space-3); margin-bottom: var(--le-space-3); color: var(--le-success); font-size: 0.9rem; display: flex; align-items: center; gap: 8px;">
            <span class="material-symbols-rounded" style="font-size: 20px;">check_circle</span>
            Changes saved successfully.
        </div>
    <?php endif; ?>

    <!-- Presentation Details -->
    <div class="le-card-solid" style="margin-bottom: var(--le-space-3);">
        <div class="le-card-header">
            <h2 class="le-card-title">Presentation Details</h2>
        </div>
        <form method="POST" action="" style="padding: var(--le-space-3);">
            <input type="hidden" name="action" value="update_details">
            <div class="le-form-group">
                <label class="le-label le-label-required">Title</label>
                <input type="text" class="le-input" name="title" required value="<?= UI::escape($presentation['title']) ?>">
            </div>
            <div class="le-form-group">
                <label class="le-label">Description</label>
                <textarea class="le-textarea" name="description" rows="3"><?= UI::escape($presentation['description'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="le-btn le-btn-primary">Save Details</button>
        </form>
    </div>

    <!-- Slides List with Menti-Style Editor -->
    <div class="le-card-solid">
        <div class="le-card-header">
            <h2 class="le-card-title">
                <span class="material-symbols-rounded" style="font-size: 22px; color: var(--le-primary);">slideshow</span>
                Slides
            </h2>
            <form method="POST" action="" style="display: inline;">
                <input type="hidden" name="action" value="add_slide">
                <button type="submit" class="le-btn le-btn-sm le-btn-primary">
                    <span class="material-symbols-rounded" style="font-size: 16px;">add</span>
                    Add Slide
                </button>
            </form>
        </div>
        <div style="padding: var(--le-space-3);">
            <?php if (empty($slides)): ?>
                <p style="color: var(--le-gray-500); text-align: center; padding: var(--le-space-4);">No slides yet. Click "Add Slide" to get started.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: var(--le-space-3);">
                    <?php foreach ($slides as $slide): 
                        $slideNotes = json_decode($slide['notes'] ?? '', true);
                        $slideType = $slideNotes['type'] ?? 'content';
                        $slideOptions = $slideNotes['options'] ?? [];
                        $slidePrompt = $slideNotes['prompt'] ?? '';
                        $typeInfo = $slideTypes[$slideType] ?? $slideTypes['content'];
                    ?>
                        <div style="border: 1px solid var(--le-gray-200); border-radius: var(--le-radius-xl); overflow: hidden; transition: all 0.2s ease;" 
                             onmouseover="this.style.borderColor='var(--le-primary)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.06)'"
                             onmouseout="this.style.borderColor='var(--le-gray-200)'; this.style.boxShadow='none'">
                            
                            <!-- Slide Header -->
                            <div style="padding: var(--le-space-2) var(--le-space-3); background: var(--le-gray-50); display: flex; align-items: center; gap: var(--le-space-2); border-bottom: 1px solid var(--le-gray-200);">
                                <span style="width: 32px; height: 32px; border-radius: var(--le-radius-full); background: <?= $typeInfo['color'] ?>; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; flex-shrink: 0;">
                                    <?= (int)$slide['slide_number'] ?>
                                </span>
                                <span style="font-weight: 600; font-size: 0.9rem;">Slide <?= (int)$slide['slide_number'] ?></span>
                                <span style="display: flex; align-items: center; gap: 4px; font-size: 0.8rem; color: <?= $typeInfo['color'] ?>; font-weight: 500;">
                                    <span class="material-symbols-rounded" style="font-size: 16px;"><?= $typeInfo['icon'] ?></span>
                                    <?= $typeInfo['label'] ?>
                                </span>
                                <span style="margin-left: auto; font-size: 0.8rem; color: var(--le-gray-400);">
                                    <?= (int)($slide['duration_seconds'] ?? 30) ?>s
                                </span>
                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Delete this slide?')">
                                    <input type="hidden" name="action" value="delete_slide">
                                    <input type="hidden" name="slide_id" value="<?= (int)$slide['id'] ?>">
                                    <button type="submit" style="background: none; border: none; color: var(--le-danger); cursor: pointer; padding: 4px;" title="Delete slide">
                                        <span class="material-symbols-rounded" style="font-size: 18px;">delete</span>
                                    </button>
                                </form>
                            </div>

                            <!-- Slide Editor -->
                            <form method="POST" action="" style="padding: var(--le-space-3);">
                                <input type="hidden" name="action" value="update_slide">
                                <input type="hidden" name="slide_id" value="<?= (int)$slide['id'] ?>">
                                <input type="hidden" name="slide_type" value="<?= $slideType ?>" class="slide-type-input">

                                <!-- Slide Type Selector (Menti-style) -->
                                <div style="margin-bottom: var(--le-space-3);">
                                    <label class="le-label" style="margin-bottom: 8px; font-size: 0.85rem; font-weight: 600;">Slide Type</label>
                                    <div class="le-slide-type-grid">
                                        <?php foreach ($slideTypes as $typeKey => $typeVal): ?>
                                            <div class="le-slide-type-btn <?= $slideType === $typeKey ? 'active' : '' ?>" 
                                                 onclick="selectSlideType(this, '<?= $typeKey ?>')"
                                                 data-type="<?= $typeKey ?>"
                                                 style="<?= $slideType === $typeKey ? 'border-color: ' . $typeVal['color'] . '; background: ' . $typeVal['color'] . '10;' : '' ?>">
                                                <span class="material-symbols-rounded" style="color: <?= $typeVal['color'] ?>;"><?= $typeVal['icon'] ?></span>
                                                <span><?= $typeVal['label'] ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Slide Prompt / Question -->
                                <div class="le-form-group">
                                    <label class="le-label">Question / Prompt</label>
                                    <input type="text" class="le-input" name="slide_prompt" 
                                           value="<?= UI::escape($slidePrompt) ?>" 
                                           placeholder="Ask your audience something..." style="font-size: 1rem; font-weight: 500;">
                                </div>

                                <!-- Type-Specific Options -->
                                <div id="typeOptions_<?= (int)$slide['id'] ?>" class="type-options-container">
                                    <?php if ($slideType === 'multiple_choice'): ?>
                                        <div class="mc-options">
                                            <label class="le-label">Answer Choices</label>
                                            <?php $choices = $slideOptions['choices'] ?? ['', '']; ?>
                                            <?php foreach ($choices as $ci => $choice): ?>
                                                <div class="le-option-row">
                                                    <span style="width: 24px; height: 24px; border-radius: 50%; border: 2px solid var(--le-gray-300); display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 600; color: var(--le-gray-400); flex-shrink: 0;"><?= chr(65 + $ci) ?></span>
                                                    <input type="text" class="le-input" name="mc_options[]" value="<?= UI::escape($choice) ?>" placeholder="Option <?= chr(65 + $ci) ?>">
                                                    <button type="button" class="le-btn-icon" onclick="this.closest('.le-option-row').remove()">
                                                        <span class="material-symbols-rounded" style="font-size: 16px;">close</span>
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                            <button type="button" class="le-add-option-btn" onclick="addMCOption(this)">
                                                <span class="material-symbols-rounded" style="font-size: 16px;">add</span> Add option
                                            </button>
                                            <label style="display: flex; align-items: center; gap: 8px; margin-top: 8px; font-size: 0.85rem; color: var(--le-gray-600);">
                                                <input type="checkbox" name="mc_allow_multiple" value="1" <?= !empty($slideOptions['allow_multiple']) ? 'checked' : '' ?>>
                                                Allow multiple selections
                                            </label>
                                        </div>
                                    <?php elseif ($slideType === 'word_cloud'): ?>
                                        <div class="le-form-group">
                                            <label class="le-label">Max words per participant</label>
                                            <input type="number" class="le-input" name="wc_max_words" value="<?= (int)($slideOptions['max_words'] ?? 30) ?>" min="1" max="100" style="width: 120px;">
                                        </div>
                                    <?php elseif ($slideType === 'rating'): ?>
                                        <div class="le-grid le-grid-2">
                                            <div class="le-form-group">
                                                <label class="le-label">Max Rating (stars)</label>
                                                <input type="number" class="le-input" name="rating_max" value="<?= (int)($slideOptions['max_rating'] ?? 5) ?>" min="2" max="10">
                                            </div>
                                            <div class="le-form-group">
                                                <label class="le-label">Low label</label>
                                                <input type="text" class="le-input" name="rating_low" value="<?= UI::escape($slideOptions['labels']['low'] ?? 'Poor') ?>">
                                            </div>
                                            <div class="le-form-group">
                                                <label class="le-label">High label</label>
                                                <input type="text" class="le-input" name="rating_high" value="<?= UI::escape($slideOptions['labels']['high'] ?? 'Excellent') ?>">
                                            </div>
                                        </div>
                                    <?php elseif ($slideType === 'ranking'): ?>
                                        <div class="rank-options">
                                            <label class="le-label">Items to Rank</label>
                                            <?php $items = $slideOptions['items'] ?? ['', '']; ?>
                                            <?php foreach ($items as $ri => $item): ?>
                                                <div class="le-option-row">
                                                    <span class="material-symbols-rounded" style="font-size: 18px; color: var(--le-gray-400);">drag_indicator</span>
                                                    <input type="text" class="le-input" name="rank_items[]" value="<?= UI::escape($item) ?>" placeholder="Item <?= $ri + 1 ?>">
                                                    <button type="button" class="le-btn-icon" onclick="this.closest('.le-option-row').remove()">
                                                        <span class="material-symbols-rounded" style="font-size: 16px;">close</span>
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                            <button type="button" class="le-add-option-btn" onclick="addRankItem(this)">
                                                <span class="material-symbols-rounded" style="font-size: 16px;">add</span> Add item
                                            </button>
                                        </div>
                                    <?php elseif ($slideType === 'quiz'): ?>
                                        <div class="le-form-group">
                                            <label class="le-label">Quiz Question</label>
                                            <input type="text" class="le-input" name="quiz_question" value="<?= UI::escape($slideOptions['question'] ?? '') ?>" placeholder="Enter the question...">
                                        </div>
                                        <div class="quiz-options">
                                            <label class="le-label">Answer Choices <span style="font-weight: 400; color: var(--le-gray-400);">(select the correct one)</span></label>
                                            <?php $qchoices = $slideOptions['choices'] ?? ['', '']; ?>
                                            <?php foreach ($qchoices as $qi => $qc): ?>
                                                <div class="le-option-row">
                                                    <input type="radio" name="quiz_correct" value="<?= $qi ?>" <?= ($slideOptions['correct'] ?? 0) === $qi ? 'checked' : '' ?> style="accent-color: var(--le-primary);">
                                                    <input type="text" class="le-input" name="quiz_options[]" value="<?= UI::escape($qc) ?>" placeholder="Answer <?= chr(65 + $qi) ?>">
                                                    <button type="button" class="le-btn-icon" onclick="this.closest('.le-option-row').remove()">
                                                        <span class="material-symbols-rounded" style="font-size: 16px;">close</span>
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                            <button type="button" class="le-add-option-btn" onclick="addQuizOption(this)">
                                                <span class="material-symbols-rounded" style="font-size: 16px;">add</span> Add option
                                            </button>
                                        </div>
                                    <?php elseif ($slideType === 'qa'): ?>
                                        <div style="background: var(--le-info-lighter); border-radius: var(--le-radius-lg); padding: var(--le-space-3); text-align: center; color: var(--le-info);">
                                            <span class="material-symbols-rounded" style="font-size: 36px; display: block; margin-bottom: 8px;">question_answer</span>
                                            <p style="font-weight: 500; margin: 0;">Q&A Session — Audience can ask questions live during the presentation.</p>
                                            <p style="font-size: 0.85rem; margin: 4px 0 0; opacity: 0.8;">No additional configuration needed.</p>
                                        </div>
                                    <?php else: ?>
                                        <!-- Content slide: HTML editor -->
                                        <div class="le-form-group">
                                            <label class="le-label">Content (HTML)</label>
                                            <textarea class="le-textarea" name="content_html" rows="5" style="font-family: monospace; font-size: 0.85rem;"><?= UI::escape($slide['content_html'] ?? '') ?></textarea>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Duration & Notes -->
                                <div class="le-grid le-grid-2" style="margin-top: var(--le-space-3); padding-top: var(--le-space-3); border-top: 1px solid var(--le-gray-100);">
                                    <div class="le-form-group">
                                        <label class="le-label">Duration (seconds)</label>
                                        <input type="number" class="le-input" name="duration_seconds" value="<?= (int)($slide['duration_seconds'] ?? 30) ?>" min="5" max="600">
                                    </div>
                                    <div class="le-form-group">
                                        <label class="le-label">Presenter Notes</label>
                                        <input type="text" class="le-input" name="notes_extra" value="" placeholder="Private notes for presenter">
                                    </div>
                                </div>

                                <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: var(--le-space-2);">
                                    <button type="submit" class="le-btn le-btn-primary le-btn-sm">
                                        <span class="material-symbols-rounded" style="font-size: 16px;">save</span>
                                        Save Slide
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    if (typeof LiveEngagement !== 'undefined') {
        LiveEngagement.init({ isPresenter: true });
    }

    // Slide type selector
    function selectSlideType(el, type) {
        const container = el.closest('form');
        container.querySelectorAll('.le-slide-type-btn').forEach(b => {
            b.classList.remove('active');
            b.style.borderColor = 'var(--le-gray-200)';
            b.style.background = 'white';
        });
        el.classList.add('active');
        el.style.borderColor = el.querySelector('.material-symbols-rounded').style.color;
        el.style.background = el.querySelector('.material-symbols-rounded').style.color + '10';
        container.querySelector('.slide-type-input').value = type;
        
        // Show/hide type options
        const optionsContainer = container.querySelector('.type-options-container');
        const contentField = container.querySelector('textarea[name="content_html"]');
        
        // For non-content types, hide the HTML editor and show type-specific UI
        if (type !== 'content') {
            // We'll reload the page to show the correct form
            container.querySelector('button[type="submit"]').click();
        }
    }

    // Add MC option
    function addMCOption(btn) {
        const container = btn.closest('.mc-options');
        const rows = container.querySelectorAll('.le-option-row');
        const letter = String.fromCharCode(65 + rows.length);
        const row = document.createElement('div');
        row.className = 'le-option-row';
        row.innerHTML = `
            <span style="width: 24px; height: 24px; border-radius: 50%; border: 2px solid var(--le-gray-300); display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 600; color: var(--le-gray-400); flex-shrink: 0;">${letter}</span>
            <input type="text" class="le-input" name="mc_options[]" placeholder="Option ${letter}">
            <button type="button" class="le-btn-icon" onclick="this.closest('.le-option-row').remove()">
                <span class="material-symbols-rounded" style="font-size: 16px;">close</span>
            </button>
        `;
        btn.parentNode.insertBefore(row, btn);
    }

    // Add Rank item
    function addRankItem(btn) {
        const container = btn.closest('.rank-options');
        const rows = container.querySelectorAll('.le-option-row');
        const row = document.createElement('div');
        row.className = 'le-option-row';
        row.innerHTML = `
            <span class="material-symbols-rounded" style="font-size: 18px; color: var(--le-gray-400);">drag_indicator</span>
            <input type="text" class="le-input" name="rank_items[]" placeholder="Item ${rows.length + 1}">
            <button type="button" class="le-btn-icon" onclick="this.closest('.le-option-row').remove()">
                <span class="material-symbols-rounded" style="font-size: 16px;">close</span>
            </button>
        `;
        btn.parentNode.insertBefore(row, btn);
    }

    // Add Quiz option
    function addQuizOption(btn) {
        const container = btn.closest('.quiz-options');
        const rows = container.querySelectorAll('.le-option-row');
        const letter = String.fromCharCode(65 + rows.length);
        const row = document.createElement('div');
        row.className = 'le-option-row';
        row.innerHTML = `
            <input type="radio" name="quiz_correct" value="${rows.length}" style="accent-color: var(--le-primary);">
            <input type="text" class="le-input" name="quiz_options[]" placeholder="Answer ${letter}">
            <button type="button" class="le-btn-icon" onclick="this.closest('.le-option-row').remove()">
                <span class="material-symbols-rounded" style="font-size: 16px;">close</span>
            </button>
        `;
        btn.parentNode.insertBefore(row, btn);
    }
</script>

<?php Layout::end(); ?>