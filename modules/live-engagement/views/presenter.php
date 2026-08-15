<?php
/**
 * Live Engagement Module - Presenter Runtime
 *
 * The live presenting surface: an immersive slide stage with a floating glass
 * toolbar, a collapsible thumbnail rail, presenter notes, a session timer,
 * laser pointer and a reaction overlay.
 *
 * Before this existed, ?page=presenter redirected to views/session.php - the
 * student view - so a lecturer pressing "Present" landed on a screen that said
 * "Waiting for presenter". Worse, views/presentations.php passes
 * ?presentation_id= rather than ?id=, so the redirect saw no session id and
 * bounced on to the join page.
 *
 * Reached either way:
 *   ?page=presenter&id=<session_id>
 *   ?page=presenter&presentation_id=<presentation_id>
 *
 * Styling comes from assets/css/live-engagement.css, which already defines the
 * glass surfaces, the UNILIS palette and the Inter/Material Symbols pairing.
 * Only chrome unique to presenting is declared locally.
 *
 * @package UNILIS\LiveEngagement\Views
 * @version 1.0.0
 */

require_once __DIR__ . '/../bootstrap.php';

use LE\Components\Layout;
use LE\Components\UI;

// Check for guest access from public presentation link
$isGuest = isset($_SESSION['le_guest_access']) && $_SESSION['le_guest_access'] === true;
$guestPresentationId = (int) ($_SESSION['le_guest_presentation_id'] ?? 0);

// Require auth unless this is a valid guest access
if (!$isGuest) {
    le_require_auth();
}

// Role check only applies to authenticated users
if (!$isGuest && !le_can_present()) {
    header('Location: ' . le_page_url('dashboard'));
    exit;
}

$userId        = le_current_user_id();
$sessionId     = (int) le_get('id', 0, true);
$presentationId = (int) le_get('presentation_id', 0, true);

// For guest access, use the presentation ID from session
if ($isGuest && $guestPresentationId) {
    $presentationId = $guestPresentationId;
}

$presModel    = new \LE\Models\PresentationModel();
$sessionModel = new \LE\Models\SessionModel();
$slideModel   = new \LE\Models\SlideModel();

$presentation = null;
$session      = null;

// For guest access, validate the presentation is actually public
if ($isGuest && $presentationId) {
    try {
        $db = le_db();
        $publicPresentation = $db->fetchOne(
            "SELECT p.*, pp.share_token, pp.expires_at 
             FROM live_presentations p
             LEFT JOIN public_presentations pp ON p.id = pp.presentation_id
             WHERE p.id = ? AND (p.is_public = 1 OR pp.share_token IS NOT NULL)",
            [$presentationId],
            'i'
        );
        
        // Check if public link has expired
        if ($publicPresentation && !empty($publicPresentation['expires_at'])) {
            $expiresAt = strtotime($publicPresentation['expires_at']);
            if ($expiresAt < time()) {
                $publicPresentation = null;
            }
        }
        
        if (!$publicPresentation) {
            // Not a valid public presentation, redirect to join page
            unset($_SESSION['le_guest_access'], $_SESSION['le_guest_presentation_id'], $_SESSION['le_guest_token']);
            header('Location: ?page=join');
            exit;
        }
        
        $presentation = $publicPresentation;
    } catch (Exception $e) {
        error_log("Guest presentation validation error: " . $e->getMessage());
        unset($_SESSION['le_guest_access'], $_SESSION['le_guest_presentation_id'], $_SESSION['le_guest_token']);
        header('Location: ?page=join');
        exit;
    }
}

// Resolve whichever half of the pair we were handed into both.
if ($presentationId && !$isGuest) {
    $presentation = $presModel->find($presentationId);
    if ($presentation) {
        $session = $sessionModel->find((int) $presentation['session_id']);
    }
} elseif ($sessionId && !$isGuest) {
    $session = $sessionModel->find($sessionId);
    if ($session) {
        // A session may carry several decks; the active one wins, else the first.
        $decks = $presModel->findBy('session_id', $sessionId);
        foreach ($decks as $deck) {
            if ((int) $deck['is_active'] === 1) { $presentation = $deck; break; }
        }
        if (!$presentation && $decks) {
            $presentation = $decks[0];
        }
    }
}

if (!$presentation) {
    header('Location: ' . le_page_url('presentations'));
    exit;
}

// Only the lecturer who owns the session may drive it (skip for guests)
if (!$isGuest) {
    if (!$session) {
        header('Location: ' . le_page_url('presentations'));
        exit;
    }
    
    if ((int) $session['lecturer_id'] !== $userId && le_current_user_role() !== 'admin') {
        header('Location: ' . le_page_url('dashboard'));
        exit;
    }
}

$slides = $presentation
    ? $slideModel->findBy('presentation_id', (int) $presentation['id'])
    : [];

$currentSlide = $presentation ? max(1, (int) $presentation['current_slide']) : 0;
$documentFileType = $presentation['file_type'] ?? '';
$documentFileUrl = !empty($presentation['file_path'])
    ? le_presentation_file_url((int) $presentation['id'])
    : '';

Layout::start([
    'title'     => ($presentation['title'] ?? $session['title'] ?? 'Presenting'),
    'layout'    => 'immersive',
    'bodyClass' => 'le-presenter-page',
]);
?>

<style>
/* Presenter-only chrome. Everything else comes from the module stylesheet. */
.le-presenter-shell {
    position: fixed;
    inset: 0;
    display: grid;
    grid-template-columns: 232px 1fr;
    background:
        radial-gradient(1200px 600px at 12% -10%, rgba(27, 94, 32, .28), transparent 60%),
        radial-gradient(900px 500px at 105% 110%, rgba(249, 168, 37, .20), transparent 55%),
        #0b1a0e;
    color: #eaf3ea;
    overflow: hidden;
    transition: grid-template-columns var(--le-transition-base, 240ms) ease;
}
.le-presenter-shell[data-rail="hidden"] { grid-template-columns: 0 1fr; }

/* ── Thumbnail rail ─────────────────────────────────────────── */
.le-rail {
    display: flex;
    flex-direction: column;
    min-width: 0;
    background: rgba(255, 255, 255, .06);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border-right: 1px solid rgba(255, 255, 255, .10);
    overflow: hidden;
}
.le-rail-head {
    display: flex; align-items: center; gap: 8px;
    padding: 16px 16px 10px;
    font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
    color: rgba(255, 255, 255, .55);
}
.le-rail-list { flex: 1; overflow-y: auto; padding: 0 12px 16px; display: flex; flex-direction: column; gap: 8px; }
.le-thumb {
    position: relative;
    display: flex; gap: 10px; align-items: center;
    padding: 8px;
    border: 1px solid rgba(255, 255, 255, .10);
    border-radius: 12px;
    background: rgba(255, 255, 255, .04);
    color: inherit;
    cursor: pointer;
    text-align: left;
    font: inherit;
    transition: transform 160ms ease, background 160ms ease, border-color 160ms ease;
}
.le-thumb:hover { transform: translateY(-1px); background: rgba(255, 255, 255, .09); }
.le-thumb.is-current { border-color: var(--le-secondary, #F9A825); background: rgba(249, 168, 37, .14); }
.le-thumb-num {
    flex-shrink: 0; width: 26px; height: 26px; display: grid; place-items: center;
    border-radius: 8px; background: rgba(0, 0, 0, .35);
    font-size: 12px; font-weight: 700;
}
.le-thumb-title { font-size: 12px; line-height: 1.35; opacity: .85; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* ── Stage ──────────────────────────────────────────────────── */
.le-stage-wrap { position: relative; display: flex; flex-direction: column; min-width: 0; }
.le-stage {
    flex: 1;
    display: grid;
    place-items: center;
    padding: 28px 28px 104px;
    min-height: 0;
}
.le-slide {
    width: min(100%, 1280px);
    aspect-ratio: 16 / 9;
    max-height: 100%;
    display: grid;
    place-items: center;
    padding: 40px;
    border-radius: 20px;
    background: rgba(255, 255, 255, .96);
    color: #14210f;
    box-shadow: 0 24px 70px rgba(0, 0, 0, .45);
    overflow: auto;
    animation: leSlideIn 260ms ease;
}
@keyframes leSlideIn { from { opacity: 0; transform: translateY(8px) scale(.995); } to { opacity: 1; transform: none; } }
.le-slide img { max-width: 100%; max-height: 100%; object-fit: contain; }
.le-slide-empty { text-align: center; color: rgba(255, 255, 255, .8); }
.le-slide-empty .material-symbols-rounded { font-size: 64px; opacity: .5; }

/* ── Top status strip ───────────────────────────────────────── */
.le-stage-top {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    padding: 14px 20px;
}
.le-pill {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 7px 14px;
    border-radius: 999px;
    background: rgba(255, 255, 255, .10);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, .14);
    font-size: 13px; font-weight: 500;
}
.le-pill .material-symbols-rounded { font-size: 18px; }
.le-pill-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing: .14em; font-weight: 700; color: var(--le-secondary, #F9A825); }
.le-pill-live { background: rgba(220, 38, 38, .18); border-color: rgba(220, 38, 38, .45); }
.le-live-dot { width: 8px; height: 8px; border-radius: 50%; background: #ef4444; animation: lePulse 1.6s ease-in-out infinite; }
@keyframes lePulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: .45; transform: scale(.82); } }

/* ── Floating toolbar ───────────────────────────────────────── */
.le-toolbar {
    position: absolute;
    left: 50%; bottom: 22px;
    transform: translateX(-50%);
    display: flex; align-items: center; gap: 6px;
    padding: 8px;
    border-radius: 999px;
    background: rgba(18, 34, 20, .72);
    backdrop-filter: blur(22px);
    -webkit-backdrop-filter: blur(22px);
    border: 1px solid rgba(255, 255, 255, .14);
    box-shadow: 0 16px 46px rgba(0, 0, 0, .48);
    z-index: 20;
}
.le-tool {
    position: relative;
    width: 44px; height: 44px;
    display: grid; place-items: center;
    border: none; border-radius: 50%;
    background: transparent;
    color: #eaf3ea;
    cursor: pointer;
    transition: background 160ms ease, transform 160ms ease;
}
.le-tool:hover { background: rgba(255, 255, 255, .14); transform: translateY(-1px); }
.le-tool:disabled { opacity: .35; cursor: not-allowed; transform: none; }
.le-tool.is-on { background: var(--le-secondary, #F9A825); color: #1a1400; }
.le-tool-danger { background: rgba(220, 38, 38, .9); color: #fff; }
.le-tool-danger:hover { background: #dc2626; }
.le-tool .material-symbols-rounded { font-size: 22px; }
.le-tool-sep { width: 1px; height: 26px; margin: 0 4px; background: rgba(255, 255, 255, .16); }
.le-tool-count {
    min-width: 62px; padding: 0 12px;
    font-size: 13px; font-weight: 600; font-variant-numeric: tabular-nums;
    text-align: center; opacity: .9;
}

/* Ripple feedback on toolbar presses. */
.le-tool::after {
    content: ''; position: absolute; inset: 0; border-radius: 50%;
    background: rgba(255, 255, 255, .5); opacity: 0; transform: scale(.4);
}
.le-tool:active::after { animation: leRipple 420ms ease-out; }
@keyframes leRipple { from { opacity: .35; transform: scale(.4); } to { opacity: 0; transform: scale(1.35); } }

/* ── Notes panel ────────────────────────────────────────────── */
.le-notes {
    position: absolute;
    right: 20px; bottom: 86px;
    width: min(360px, calc(100vw - 40px));
    max-height: 46vh;
    display: flex; flex-direction: column;
    padding: 16px;
    border-radius: 16px;
    background: rgba(18, 34, 20, .84);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, .14);
    box-shadow: 0 18px 50px rgba(0, 0, 0, .5);
    z-index: 19;
    animation: leSlideUp 200ms ease;
}
@keyframes leSlideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
.le-notes[hidden] { display: none; }
.le-notes h4 { margin: 0 0 8px; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; opacity: .6; }
.le-notes-body { overflow-y: auto; font-size: 14px; line-height: 1.6; white-space: pre-wrap; opacity: .92; }

/* ── Reaction overlay ───────────────────────────────────────── */
.le-reaction-layer { position: absolute; inset: 0; pointer-events: none; overflow: hidden; z-index: 15; }
.le-float-reaction { position: absolute; bottom: 96px; font-size: 30px; animation: leFloatUp 2.8s ease-out forwards; }
@keyframes leFloatUp {
    0%   { opacity: 0; transform: translateY(0) scale(.6); }
    12%  { opacity: 1; transform: translateY(-14px) scale(1.1); }
    100% { opacity: 0; transform: translateY(-260px) scale(.9); }
}

/* ── Laser pointer ──────────────────────────────────────────── */
.le-laser {
    position: fixed; z-index: 40; width: 18px; height: 18px;
    margin: -9px 0 0 -9px; border-radius: 50%; pointer-events: none;
    background: radial-gradient(circle, rgba(255,60,60,.95) 0%, rgba(255,60,60,.55) 40%, transparent 70%);
    box-shadow: 0 0 18px 6px rgba(255, 60, 60, .45);
}
.le-laser[hidden] { display: none; }
body.le-laser-on .le-stage { cursor: none; }

@media (max-width: 900px) {
    .le-presenter-shell { grid-template-columns: 0 1fr; }
    .le-stage { padding: 16px 16px 140px; }
    
    /* Google Meet style bottom navigation for mobile */
    .le-toolbar {
        left: 0; bottom: 0;
        transform: none;
        width: 100%;
        justify-content: space-around;
        padding: 12px 16px;
        padding-bottom: calc(12px + env(safe-area-inset-bottom));
        border-radius: 0;
        background: rgba(18, 34, 20, .95);
        border-top: 1px solid rgba(255, 255, 255, .14);
        border-left: none;
        border-right: none;
        border-bottom: none;
        box-shadow: 0 -4px 20px rgba(0, 0, 0, .4);
    }
    
    .le-tool {
        width: 52px; height: 52px;
        background: rgba(255, 255, 255, .12);
        border-radius: 50%;
    }
    
    .le-tool:hover {
        background: rgba(255, 255, 255, .2);
        transform: scale(1.05);
    }
    
    .le-tool.is-on {
        background: var(--le-secondary, #F9A825);
        color: #1a1400;
    }
    
    .le-tool-danger {
        background: rgba(220, 38, 38, .9);
        color: #fff;
    }
    
    .le-tool-sep { display: none; }
    
    .le-tool-count {
        display: none;
    }
    
    .le-notes {
        right: 16px; bottom: 160px;
        width: calc(100vw - 32px);
        max-height: 40vh;
    }
}

@media (prefers-reduced-motion: reduce) {
    .le-slide, .le-notes, .le-float-reaction { animation: none; }
    .le-live-dot { animation: none; }
}
</style>

<div class="le-presenter-shell" id="presenterShell" data-rail="shown">

    <!-- ── Slide thumbnails ───────────────────────────────────── -->
    <aside class="le-rail" id="slideRail">
        <div class="le-rail-head">
            <span class="material-symbols-rounded" style="font-size:18px;">view_carousel</span>
            Slides
        </div>
        <div class="le-rail-list" id="slideRailList"></div>
    </aside>

    <!-- ── Stage ──────────────────────────────────────────────── -->
    <div class="le-stage-wrap">
        <div class="le-stage-top">
            <span class="le-pill le-pill-live"><span class="le-live-dot"></span> Live</span>
            <span class="le-pill"><span class="material-symbols-rounded">tag</span>
                <span class="le-pill-code"><?= UI::escape($session['session_code']) ?></span>
            </span>
            <span class="le-pill"><span class="material-symbols-rounded">group</span>
                <span id="participantCount">0</span> joined
            </span>
            <span class="le-pill"><span class="material-symbols-rounded">timer</span>
                <span id="sessionTimer">00:00</span>
            </span>
            <span class="le-pill" style="margin-left:auto; max-width:38ch; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                <?= UI::escape($presentation['title'] ?? $session['title']) ?>
            </span>
        </div>

        <div class="le-stage" id="stage">
            <div class="le-slide" id="slideCanvas"></div>
        </div>

        <div class="le-reaction-layer" id="reactionLayer"></div>

        <!-- ── Notes ──────────────────────────────────────────── -->
        <div class="le-notes" id="notesPanel" hidden>
            <h4>Presenter notes</h4>
            <div class="le-notes-body" id="notesBody">No notes for this slide.</div>
        </div>

        <!-- ── Floating toolbar ───────────────────────────────── -->
        <div class="le-toolbar" role="toolbar" aria-label="Presenter controls">
            <button class="le-tool" id="railBtn" title="Toggle slide rail (T)" aria-label="Toggle slide rail">
                <span class="material-symbols-rounded">view_sidebar</span>
            </button>
            <span class="le-tool-sep"></span>
            <button class="le-tool" id="prevBtn" title="Previous slide (←)" aria-label="Previous slide">
                <span class="material-symbols-rounded">chevron_left</span>
            </button>
            <span class="le-tool-count" id="slideCounter">– / –</span>
            <button class="le-tool" id="nextBtn" title="Next slide (→)" aria-label="Next slide">
                <span class="material-symbols-rounded">chevron_right</span>
            </button>
            <span class="le-tool-sep"></span>
            <button class="le-tool" id="notesBtn" title="Presenter notes (N)" aria-label="Presenter notes">
                <span class="material-symbols-rounded">sticky_note_2</span>
            </button>
            <button class="le-tool" id="laserBtn" title="Laser pointer (L)" aria-label="Laser pointer">
                <span class="material-symbols-rounded">my_location</span>
            </button>
            <button class="le-tool" id="pollBtn" title="Polls" aria-label="Polls">
                <span class="material-symbols-rounded">bar_chart</span>
            </button>
            <button class="le-tool" id="fullscreenBtn" title="Fullscreen (F)" aria-label="Fullscreen">
                <span class="material-symbols-rounded">fullscreen</span>
            </button>
            <span class="le-tool-sep"></span>
            <button class="le-tool le-tool-danger" id="endBtn" title="End session" aria-label="End session">
                <span class="material-symbols-rounded">stop_circle</span>
            </button>
        </div>
    </div>
</div>

<div class="le-laser" id="laserDot" hidden></div>

<?php if ($documentFileType === 'pdf' && $documentFileUrl): ?>
<script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/build/pdf.min.mjs" type="module"></script>
<?php endif; ?>

<script type="module">
(function () {
    'use strict';

    const SESSION_ID      = <?= (int) $session['id'] ?>;
    const PRESENTATION_ID = <?= (int) ($presentation['id'] ?? 0) ?>;
    const API_BASE        = <?= json_encode(le_module_url('api') . '/') ?>;
    const CSRF            = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const FILE_TYPE       = <?= json_encode($documentFileType) ?>;
    const FILE_URL        = <?= json_encode($documentFileUrl) ?>;
    const SLIDES          = <?= json_encode(array_map(static fn($s) => [
                                    'id'      => (int) $s['id'],
                                    'number'  => (int) $s['slide_number'],
                                    'image'   => $s['image_path'] ?? '',
                                    'html'    => $s['content_html'] ?? '',
                                    'notes'   => $s['notes'] ?? '',
                                ], $slides), JSON_UNESCAPED_SLASHES) ?>;

    let current = <?= (int) $currentSlide ?> || (SLIDES.length ? 1 : 0);
    let laserOn = false;
    let syncing = false;
    let pdfDoc = null;

    <?php if ($documentFileType === 'pdf' && $documentFileUrl): ?>
    import * as pdfjsLib from 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/build/pdf.min.mjs';
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/build/pdf.worker.min.mjs';

    async function ensurePdfDoc() {
        if (pdfDoc || FILE_TYPE !== 'pdf' || !FILE_URL) {
            return pdfDoc;
        }
        pdfDoc = await pdfjsLib.getDocument({ url: FILE_URL, withCredentials: true }).promise;
        return pdfDoc;
    }

    async function renderPdfPage(pageNumber) {
        const doc = await ensurePdfDoc();
        if (!doc) {
            return false;
        }

        const page = await doc.getPage(pageNumber);
        const viewport = page.getViewport({ scale: 1 });
        const maxWidth = Math.min(window.innerWidth * 0.82, 1280);
        const scale = maxWidth / viewport.width;
        const scaled = page.getViewport({ scale });

        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        canvas.width = scaled.width;
        canvas.height = scaled.height;
        canvas.style.maxWidth = '100%';
        canvas.style.maxHeight = '100%';

        await page.render({ canvasContext: context, viewport: scaled }).promise;
        el.canvas.appendChild(canvas);
        return true;
    }
    <?php else: ?>
    async function renderPdfPage() { return false; }
    <?php endif; ?>

    const el = {
        shell:      document.getElementById('presenterShell'),
        rail:       document.getElementById('slideRailList'),
        canvas:     document.getElementById('slideCanvas'),
        counter:    document.getElementById('slideCounter'),
        prev:       document.getElementById('prevBtn'),
        next:       document.getElementById('nextBtn'),
        notes:      document.getElementById('notesPanel'),
        notesBody:  document.getElementById('notesBody'),
        laser:      document.getElementById('laserDot'),
        timer:      document.getElementById('sessionTimer'),
        count:      document.getElementById('participantCount'),
        reactions:  document.getElementById('reactionLayer'),
    };

    function toast(msg, kind) {
        if (window.LiveEngagement && LiveEngagement.showToast) LiveEngagement.showToast(msg, kind || 'info');
    }

    async function api(endpoint, options) {
        const opts = options || {};
        const init = {
            method: opts.method || 'GET',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF,
                'X-Requested-With': 'XMLHttpRequest',
            },
        };
        if (opts.body) init.body = JSON.stringify(opts.body);

        const res = await fetch(API_BASE + endpoint, init);
        const text = await res.text();
        let data;
        try { data = text ? JSON.parse(text) : {}; }
        catch (e) { throw new Error('The server returned an invalid response (HTTP ' + res.status + ').'); }
        if (!res.ok || data.success === false) {
            throw new Error(data.message || (data.errors && data.errors[0]) || 'Request failed');
        }
        return data.data !== undefined ? data.data : data;
    }

    // ── Rendering ──────────────────────────────────────────────
    function slideAt(n) {
        return SLIDES.find(s => s.number === n) || null;
    }

    function renderRail() {
        el.rail.textContent = '';
        SLIDES.forEach(s => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'le-thumb' + (s.number === current ? ' is-current' : '');
            btn.addEventListener('click', () => goTo(s.number));

            const num = document.createElement('span');
            num.className = 'le-thumb-num';
            num.textContent = s.number;

            const title = document.createElement('span');
            title.className = 'le-thumb-title';
            // textContent: slide HTML is authored content and must not be
            // executed inside the rail label.
            title.textContent = (s.html || '').replace(/<[^>]*>/g, ' ').trim().slice(0, 40) || ('Slide ' + s.number);

            btn.appendChild(num);
            btn.appendChild(title);
            el.rail.appendChild(btn);
        });
    }

    async function renderSlide() {
        const s = slideAt(current);
        el.canvas.textContent = '';

        if (FILE_TYPE === 'pdf' && FILE_URL) {
            const rendered = await renderPdfPage(current);
            if (rendered) {
                el.counter.textContent = SLIDES.length ? (current + ' / ' + SLIDES.length) : '– / –';
                el.prev.disabled = current <= 1;
                el.next.disabled = !SLIDES.length || current >= SLIDES.length;
                el.notesBody.textContent = (s && s.notes) ? s.notes : 'No notes for this slide.';
                Array.prototype.forEach.call(el.rail.children, (node, i) => {
                    node.classList.toggle('is-current', SLIDES[i] && SLIDES[i].number === current);
                });
                return;
            }
        }

        if (!s) {
            const empty = document.createElement('div');
            empty.className = 'le-slide-empty';
            empty.style.color = '#5a6b58';
            const icon = document.createElement('span');
            icon.className = 'material-symbols-rounded';
            icon.textContent = 'slideshow';
            const msg = document.createElement('p');
            msg.style.marginTop = '12px';
            msg.textContent = SLIDES.length
                ? 'Slide ' + current + ' is empty.'
                : 'This presentation has no slides yet. Add some from the editor.';
            empty.appendChild(icon);
            empty.appendChild(msg);
            el.canvas.appendChild(empty);
        } else if (s.image) {
            const img = document.createElement('img');
            img.src = s.image;
            img.alt = 'Slide ' + s.number;
            el.canvas.appendChild(img);
        } else {
            // Slide bodies are lecturer-authored rich text stored by the editor,
            // so they are rendered as markup here by design.
            const body = document.createElement('div');
            body.style.width = '100%';
            body.innerHTML = s.html || '';
            el.canvas.appendChild(body);
        }

        el.counter.textContent = SLIDES.length ? (current + ' / ' + SLIDES.length) : '– / –';
        el.prev.disabled = current <= 1;
        el.next.disabled = !SLIDES.length || current >= SLIDES.length;
        el.notesBody.textContent = (s && s.notes) ? s.notes : 'No notes for this slide.';

        Array.prototype.forEach.call(el.rail.children, (node, i) => {
            node.classList.toggle('is-current', SLIDES[i] && SLIDES[i].number === current);
        });
    }

    // ── Navigation ─────────────────────────────────────────────
    async function goTo(n) {
        if (!SLIDES.length) return;
        const target = Math.max(1, Math.min(n, SLIDES.length));
        if (target === current || syncing) return;

        current = target;
        renderSlide().catch(() => {});          // paint first; the network call must not stall the deck

        if (!PRESENTATION_ID) return;
        syncing = true;
        try {
            await api('presentation.php?action=goto_slide', {
                method: 'POST',
                body: { presentation_id: PRESENTATION_ID, slide: target },
            });
        } catch (err) {
            toast('Slide changed locally but the room was not updated: ' + err.message, 'error');
        } finally {
            syncing = false;
        }
    }

    // ── Toolbar ────────────────────────────────────────────────
    el.prev.addEventListener('click', () => goTo(current - 1));
    el.next.addEventListener('click', () => goTo(current + 1));

    document.getElementById('railBtn').addEventListener('click', () => {
        const shown = el.shell.getAttribute('data-rail') === 'shown';
        el.shell.setAttribute('data-rail', shown ? 'hidden' : 'shown');
    });

    document.getElementById('notesBtn').addEventListener('click', (e) => {
        el.notes.hidden = !el.notes.hidden;
        e.currentTarget.classList.toggle('is-on', !el.notes.hidden);
    });

    document.getElementById('laserBtn').addEventListener('click', (e) => {
        laserOn = !laserOn;
        e.currentTarget.classList.toggle('is-on', laserOn);
        document.body.classList.toggle('le-laser-on', laserOn);
        el.laser.hidden = true;
    });

    document.addEventListener('mousemove', (e) => {
        if (!laserOn) return;
        el.laser.hidden = false;
        el.laser.style.left = e.clientX + 'px';
        el.laser.style.top = e.clientY + 'px';
    });

    document.getElementById('fullscreenBtn').addEventListener('click', () => {
        if (!document.fullscreenElement) document.documentElement.requestFullscreen().catch(() => {});
        else document.exitFullscreen().catch(() => {});
    });

    document.getElementById('pollBtn').addEventListener('click', () => {
        // The poll builder lives in the session dashboard; send the presenter
        // there rather than leaving a button that does nothing.
        window.open('?page=dashboard#polls', '_blank', 'noopener');
    });

    document.getElementById('endBtn').addEventListener('click', async () => {
        if (!confirm('End this session for everyone?')) return;
        try {
            if (PRESENTATION_ID) {
                await api('presentation.php?action=set_active', {
                    method: 'POST',
                    body: { presentation_id: PRESENTATION_ID, active: false },
                });
            }
            await api('session.php?action=end', { method: 'POST', body: { id: SESSION_ID } });
            window.location.href = '?page=dashboard';
        } catch (err) {
            toast('Could not end the session: ' + err.message, 'error');
        }
    });

    // ── Keyboard ───────────────────────────────────────────────
    document.addEventListener('keydown', (e) => {
        if (e.target.matches('input, textarea, select')) return;
        switch (e.key) {
            case 'ArrowRight': case 'PageDown': case ' ': e.preventDefault(); goTo(current + 1); break;
            case 'ArrowLeft':  case 'PageUp':            e.preventDefault(); goTo(current - 1); break;
            case 'Home':                                  e.preventDefault(); goTo(1); break;
            case 'End':                                   e.preventDefault(); goTo(SLIDES.length); break;
            case 't': case 'T': document.getElementById('railBtn').click(); break;
            case 'n': case 'N': document.getElementById('notesBtn').click(); break;
            case 'l': case 'L': document.getElementById('laserBtn').click(); break;
            case 'f': case 'F': document.getElementById('fullscreenBtn').click(); break;
        }
    });

    // ── Timer ──────────────────────────────────────────────────
    const startedAt = Date.now();
    setInterval(() => {
        const secs = Math.floor((Date.now() - startedAt) / 1000);
        const h = Math.floor(secs / 3600);
        const m = Math.floor((secs % 3600) / 60);
        const s = secs % 60;
        const pad = (v) => String(v).padStart(2, '0');
        el.timer.textContent = (h ? pad(h) + ':' : '') + pad(m) + ':' + pad(s);
    }, 1000);

    // ── Live room state ────────────────────────────────────────
    const REACTION_GLYPHS = { like: '👍', love: '❤️', laugh: '😂', wow: '😮', clap: '👏' };
    let lastReactionTotal = null;

    async function pollRoom() {
        if (document.hidden) return;
        try {
            const p = await api('session.php?action=participants&id=' + SESSION_ID);
            if (p && p.participants) el.count.textContent = p.participants.length;
        } catch (e) { /* transient: the counter simply holds its last value */ }

        try {
            const r = await api('session.php?action=reactions&id=' + SESSION_ID);
            if (r && r.reactions) {
                const total = Object.values(r.reactions).reduce((a, b) => a + Number(b || 0), 0);
                // First pass establishes the baseline; without this every
                // reaction accumulated before the presenter opened the page
                // would burst onto the stage at once.
                if (lastReactionTotal !== null && total > lastReactionTotal) {
                    const burst = Math.min(total - lastReactionTotal, 12);
                    const kinds = Object.keys(r.reactions).filter(k => Number(r.reactions[k]) > 0);
                    for (let i = 0; i < burst; i++) {
                        floatReaction(REACTION_GLYPHS[kinds[i % kinds.length]] || '👍');
                    }
                }
                lastReactionTotal = total;
            }
        } catch (e) { /* reactions are decorative; ignore failures */ }
    }

    function floatReaction(glyph) {
        const node = document.createElement('span');
        node.className = 'le-float-reaction';
        node.textContent = glyph;
        node.style.left = (12 + Math.floor(Math.random() * 76)) + '%';
        node.style.animationDelay = (Math.random() * 0.5) + 's';
        el.reactions.appendChild(node);
        setTimeout(() => node.remove(), 3600);
    }

    // ── Boot ───────────────────────────────────────────────────
    renderRail();
    renderSlide().catch(() => {});
    pollRoom();
    setInterval(pollRoom, 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) pollRoom(); });

    // Announce the deck as live so students start following it.
    if (PRESENTATION_ID) {
        api('presentation.php?action=set_active', {
            method: 'POST',
            body: { presentation_id: PRESENTATION_ID, active: true },
        }).catch(() => toast('Students may not follow your slides: the deck could not be marked live.', 'error'));
    }
}());
</script>

<?php
Layout::end();
