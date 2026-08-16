<?php
/**
 * Live Engagement Module - Document Helper
 *
 * Utilities for uploaded presentation files (PDF, PPT, PPTX).
 *
 * @package UNILIS\LiveEngagement
 */

if (!defined('UNILIS_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Map a MIME type / extension pair to the live_presentations.file_type enum.
 */
function le_document_file_type(string $mimeType, string $extension): ?string
{
    $extension = strtolower($extension);

    if ($mimeType === 'application/pdf' || $extension === 'pdf') {
        return 'pdf';
    }

    if (in_array($mimeType, [
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ], true) || in_array($extension, ['ppt', 'pptx'], true)) {
        return 'pptx';
    }

    if (str_starts_with($mimeType, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        return 'image';
    }

    if (str_starts_with($mimeType, 'video/') || in_array($extension, ['mp4', 'webm'], true)) {
        return 'video';
    }

    return null;
}

/**
 * Public URL for a stored presentation file (served through the API so .pptx
 * is not blocked by the root .htaccess extension deny-list).
 */
function le_presentation_file_url(int $presentationId): string
{
    return le_module_url('api/presentation_file.php?id=' . $presentationId);
}

/**
 * Best-effort page / slide count for uploaded documents.
 */
function le_count_document_pages(string $path, string $fileType): int
{
    if ($fileType === 'pdf') {
        return le_count_pdf_pages($path);
    }

    if ($fileType === 'pptx') {
        return le_count_pptx_slides($path);
    }

    return 1;
}

function le_count_pdf_pages(string $path): int
{
    $content = @file_get_contents($path);
    if ($content === false) {
        return 1;
    }

    if (preg_match('/\/Type\s*\/Pages[^>]*\/Count\s+(\d+)/s', $content, $match)) {
        return max(1, (int) $match[1]);
    }

    if (preg_match_all('/\/Type\s*\/Page\b/', $content, $matches)) {
        return max(1, count($matches[0]));
    }

    return 1;
}

function le_count_pptx_slides(string $path): int
{
    if (!class_exists('ZipArchive')) {
        return 1;
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return 1;
    }

    $count = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        if (preg_match('#ppt/slides/slide\d+\.xml#', $name)) {
            $count++;
        }
    }
    $zip->close();

    return max(1, $count);
}

/**
 * Extract basic HTML slides from a PPTX file.
 *
 * @return array<int, array{slide_number:int, content_html:string}>
 */
function le_extract_pptx_slides(string $path, string $mediaBaseUrl = ''): array
{
    if (!class_exists('ZipArchive')) {
        return [[
            'slide_number' => 1,
            'content_html' => '<p>Unable to read this PowerPoint file on the server.</p>',
        ]];
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return [[
            'slide_number' => 1,
            'content_html' => '<p>Unable to open this PowerPoint file.</p>',
        ]];
    }

    $slideFiles = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        if (preg_match('#ppt/slides/slide(\d+)\.xml#', $name, $match)) {
            $slideFiles[(int) $match[1]] = $name;
        }
    }

    ksort($slideFiles);

    if ($slideFiles === []) {
        $zip->close();
        return [[
            'slide_number' => 1,
            'content_html' => '<p>This presentation has no readable slides.</p>',
        ]];
    }

    $slideSize = le_pptx_slide_size_emu($zip);

    $slides = [];
    foreach ($slideFiles as $number => $xmlPath) {
        $xml = $zip->getFromName($xmlPath);
        $slides[] = [
            'slide_number' => $number,
            'content_html' => le_pptx_xml_to_html($xml ?: '', $zip, $xmlPath, $mediaBaseUrl, $slideSize),
        ];
    }

    $zip->close();

    return $slides;
}

/**
 * Read the deck's slide size (EMU) from ppt/presentation.xml.
 *
 * @return array{w:int,h:int}
 */
function le_pptx_slide_size_emu(ZipArchive $zip): array
{
    $w = 12192000;
    $h = 6858000;
    $xml = (string) $zip->getFromName('ppt/presentation.xml');
    if ($xml !== '' && preg_match('/<p:sldSz\b[^>]*cx="(\d+)"[^>]*cy="(\d+)"/s', $xml, $m)) {
        $w = (int) $m[1];
        $h = (int) $m[2];
    }
    return ['w' => max(1, $w), 'h' => max(1, $h)];
}

/**
 * Convert a single PPTX slide's XML into positioned HTML that retains the
 * original layout as closely as DrawingML allows. Each text box and picture is
 * placed at its on-slide position/size with its own fonts, colours, weight and
 * alignment, then scaled to fit the presenting stage using container units.
 */
function le_pptx_xml_to_html(string $xml, ZipArchive $zip, string $xmlPath, string $mediaBaseUrl = '', ?array $slideSize = null): string
{
    $size = $slideSize ?: le_pptx_slide_size_emu($zip);
    $sw   = max(1, (int) $size['w']);
    $sh   = max(1, (int) $size['h']);
    $slideWpx = $sw / 914400 * 96;   // rendered slide width in px at 96 dpi

    // Slide -> media relationship map (for pictures).
    $relsPath = preg_replace('#^ppt/slides/(slide\d+\.xml)$#', 'ppt/slides/_rels/$1.rels', $xmlPath);
    $relsXml  = (string) $zip->getFromName($relsPath ?: '');
    $ridMedia = [];
    if (preg_match_all('/Id="(rId\d+)"[^>]*Target="\.\.\/media\/([^"]+)"/', $relsXml, $rm)) {
        foreach ($rm[1] as $i => $rid) {
            $ridMedia[$rid] = 'ppt/media/' . $rm[2][$i];
        }
    }

    $dataUriFor = function (string $rid) use ($zip, $ridMedia): string {
        $path = $ridMedia[$rid] ?? '';
        if ($path === '') {
            return '';
        }
        $binary = $zip->getFromName($path);
        if ($binary === false || $binary === '') {
            return '';
        }
        return 'data:' . le_guess_image_mime(basename($path), $binary) . ';base64,' . base64_encode($binary);
    };

    // A shape's offset (off) and size (ext) in EMU.
    $box = function (string $block): array {
        $b = ['x' => 0, 'y' => 0, 'w' => 0, 'h' => 0];
        if (preg_match('/<a:xfrm\b[^>]*>(.*?)<\/a:xfrm>/s', $block, $f)) {
            if (preg_match('/<a:off\b[^>]*x="(-?\d+)"[^>]*y="(-?\d+)"/', $f[1], $o)) {
                $b['x'] = max(0, (float) $o[1]);
                $b['y'] = max(0, (float) $o[2]);
            }
            if (preg_match('/<a:ext\b[^>]*cx="(-?\d+)"[^>]*cy="(-?\d+)"/', $f[1], $e)) {
                $b['w'] = max(0, (float) $e[1]);
                $b['h'] = max(0, (float) $e[2]);
            }
        }
        return $b;
    };

    // Point size -> cqw, so fonts scale with the presenting stage.
    $fontCqw = function (float $szHundredths) use ($slideWpx): string {
        $pt  = max(1000, $szHundredths) / 100;
        $cqw = $slideWpx > 0 ? $pt * 1.3333 / $slideWpx * 100 : 1.6;
        return number_format($cqw, 3) . 'cqw';
    };

    // One styled <a:r> run -> <span>.
    $runHtml = function (string $run) use ($fontCqw): string {
        $sz = 1800;
        $b = $i = $u = false;
        $color = '#0f172a';
        $family = '';
        if (preg_match('/<a:rPr\b([^>]*)>/s', $run, $rp)) {
            if (preg_match('/\bsz="(-?\d+)"/', $rp[1], $m)) {
                $sz = (int) $m[1];
            }
            $b = (bool) preg_match('/\bb="1"/', $rp[1]);
            $i = (bool) preg_match('/\bi="1"/', $rp[1]);
            $u = (bool) preg_match('/\bu="(sng|single)"/', $rp[1]);
        }
        if (preg_match('/<a:srgbClr\b[^>]*val="([0-9a-fA-F]{6})"/', $run, $cm)) {
            $color = '#' . strtolower($cm[1]);
        }
        if (preg_match('/<a:latin\b[^>]*typeface="([^"]+)"/', $run, $fm)) {
            $family = $fm[1];
        }
        if (!preg_match('/<a:t\b[^>]*>(.*?)<\/a:t>/s', $run, $tm)) {
            return '';
        }
        $text = htmlspecialchars(html_entity_decode($tm[1], ENT_QUOTES | ENT_XML1, 'UTF-8'), ENT_QUOTES, 'UTF-8');
        if ($text === '') {
            return '';
        }

        $style = 'font-size:' . $fontCqw($sz) . ';line-height:1.25;';
        $style .= $b ? 'font-weight:700;' : 'font-weight:400;';
        if ($i) { $style .= 'font-style:italic;'; }
        if ($u) { $style .= 'text-decoration:underline;'; }
        $style .= 'color:' . $color . ';';
        if ($family !== '') { $style .= 'font-family:' . htmlspecialchars($family, ENT_QUOTES, 'UTF-8') . ',sans-serif;'; }

        return '<span style="' . $style . '">' . $text . '</span>';
    };

    $items = '';

    // ── Text boxes & graphic frames (positioned on the slide) ─────────────
    if (preg_match_all('~<(p:sp|p:graphicFrame)\b[^>]*>.*?</\1>~s', $xml, $shapes)) {
        foreach ($shapes[0] as $block) {
            $b = $box($block);
            if ($b['w'] < 1 || $b['h'] < 1) {
                continue;
            }

            $align = 'left';
            if (preg_match('/<a:pPr\b[^>]*algn="(l|c|r|just|ctr|dist)"/', $block, $am)) {
                $al = $am[1];
                $align = $al === 'ctr' ? 'center' : ($al === 'r' ? 'right' : ($al === 'l' ? 'left' : 'justify'));
            }

            $inner = '';
            if (preg_match_all('/<a:r\b[^>]*>.*?<\/a:r>/s', $block, $runs)) {
                foreach ($runs[0] as $run) {
                    $inner .= $runHtml($run);
                }
            }
            if ($inner === '') {
                continue;
            }

            $items .= '<div style="position:absolute;left:' . number_format($b['x'] / $sw * 100, 3) . '%;'
                . 'top:' . number_format($b['y'] / $sh * 100, 3) . '%;'
                . 'width:' . number_format($b['w'] / $sw * 100, 3) . '%;'
                . 'height:' . number_format($b['h'] / $sh * 100, 2) . '%;'
                . 'box-sizing:border-box;overflow:visible;text-align:' . $align . ';">' . $inner . '</div>';
        }
    }

    // ── Pictures (positioned, keep their shape box) ───────────────────────
    if (preg_match_all('/<p:pic\b[^>]*>.*?<\/p:pic>/s', $xml, $pics)) {
        foreach ($pics[0] as $block) {
            $b = $box($block);
            if ($b['w'] < 1 || $b['h'] < 1) {
                continue;
            }
            if (!preg_match('/<a:blip\b[^>]*r:embed="(rId\d+)"/', $block, $bm)) {
                continue;
            }
            $uri = $dataUriFor($bm[1]);
            if ($uri === '') {
                continue;
            }
            $items .= '<img src="' . $uri . '" alt="" style="position:absolute;left:'
                . number_format($b['x'] / $sw * 100, 2) . '%;'
                . 'top:' . number_format($b['y'] / $sh * 100, 2) . '%;'
                . 'width:' . number_format($b['w'] / $sw * 100, 3) . '%;'
                . 'height:' . number_format($b['h'] / $sh * 100, 3) . '%;">';
        }
    }

    if ($items === '') {
        return '<p style="color:#64748b;padding:20px;">Slide content could not be extracted. Download the original file to view it.</p>';
    }

    return '<div class="le-uploaded-slide" style="aspect-ratio:' . number_format($sw / $sh, 5) . ';">' . $items . '</div>';
}

function le_guess_image_mime(string $filename, string $binary): string
{
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    return match ($extension) {
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        default => str_starts_with($binary, "\x89PNG") ? 'image/png' : 'image/jpeg',
    };
}

/**
 * Build a single slide that embeds the original document in an inline viewer.
 *
 * Legacy binary .ppt files are OLE containers, not ZIP archives, so unlike
 * .pptx decks they can't be unpacked into individual slides server-side. We
 * still store the file and render it in an <iframe> so it shows (or offers
 * the file for opening) consistently on both the presenter and student screens.
 */
function le_ppt_embed_html(int $presentationId, string $originalName = ''): string
{
    $fileUrl = $presentationId > 0 ? le_presentation_file_url($presentationId) : '';
    if ($fileUrl === '') {
        return '<p style="color:#64748b;">This PowerPoint file could not be embedded on the server. '
            . 'Download it to view the slides.</p>';
    }

    $label = $originalName !== ''
        ? htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8')
        : 'Presentation';

    return '<div class="le-ppt-embed" style="display:flex;flex-direction:column;gap:12px;align-items:center;width:100%;">'
        . '<iframe src="' . htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8') . '" '
        . 'title="' . $label . '" allowfullscreen '
        . 'style="width:100%;height:72vh;min-height:420px;border:none;border-radius:12px;background:#ffffff;box-shadow:0 8px 28px rgba(0,0,0,.12);"></iframe>'
        . '<a href="' . htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" '
        . 'style="font-size:0.9rem;font-weight:600;color:#1b5e20;text-decoration:underline;">'
        . '<span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px;">open_in_new</span>&nbsp;'
        . 'Open the full presentation'
        . '</a>'
        . '</div>';
}

/**
 * Build slide rows for a newly uploaded presentation file.
 *
 * @return array<int, array<string, mixed>>
 */
function le_build_uploaded_slides(string $storedPath, string $fileType, int $presentationId): array
{
    if ($fileType === 'pptx') {
        // Legacy .ppt is stored as a PowerPoint but is an OLE container, so the
        // ZIP-based extractor would fail. Present the original file embed instead.
        if (strtolower(pathinfo($storedPath, PATHINFO_EXTENSION)) === 'ppt') {
            return [[
                'slide_number' => 1,
                'content_html' => le_ppt_embed_html($presentationId),
            ]];
        }

        return le_extract_pptx_slides($storedPath);
    }

    if ($fileType === 'pdf') {
        $pageCount = le_count_pdf_pages($storedPath);
        $slides = [];
        for ($i = 1; $i <= $pageCount; $i++) {
            $slides[] = [
                'slide_number' => $i,
                'content_html' => '',
            ];
        }

        return $slides;
    }

    if ($fileType === 'image') {
        $url = le_presentation_file_url($presentationId);
        return [[
            'slide_number' => 1,
            'content_html' => '',
            'image_path' => $url,
        ]];
    }

    return [[
        'slide_number' => 1,
        'content_html' => '<p>Unsupported presentation format.</p>',
    ]];
}
