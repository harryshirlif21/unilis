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

    $slides = [];
    foreach ($slideFiles as $number => $xmlPath) {
        $xml = $zip->getFromName($xmlPath);
        $slides[] = [
            'slide_number' => $number,
            'content_html' => le_pptx_xml_to_html($xml ?: '', $zip, $xmlPath, $mediaBaseUrl),
        ];
    }

    $zip->close();

    return $slides;
}

function le_pptx_xml_to_html(string $xml, ZipArchive $zip, string $xmlPath, string $mediaBaseUrl): string
{
    $parts = [];

    if (preg_match_all('/<a:t[^>]*>(.*?)<\/a:t>/s', $xml, $textMatches)) {
        foreach ($textMatches[1] as $chunk) {
            $text = trim(html_entity_decode(strip_tags($chunk), ENT_QUOTES | ENT_XML1, 'UTF-8'));
            if ($text !== '') {
                $parts[] = '<p style="margin:0 0 12px;font-size:1.25rem;line-height:1.5;">'
                    . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
            }
        }
    }

    $relsPath = preg_replace('#^ppt/slides/(slide\d+\.xml)$#', 'ppt/slides/_rels/$1.rels', $xmlPath);
    $relsXml = $relsPath ? $zip->getFromName($relsPath) : false;

    if ($relsXml && preg_match_all('/Target="\.\.\/media\/([^"]+)"/', $relsXml, $mediaMatches)) {
        foreach ($mediaMatches[1] as $mediaFile) {
            $mediaPath = 'ppt/media/' . $mediaFile;
            $binary = $zip->getFromName($mediaPath);
            if ($binary === false) {
                continue;
            }

            $mime = le_guess_image_mime($mediaFile, $binary);
            $dataUri = 'data:' . $mime . ';base64,' . base64_encode($binary);
            $parts[] = '<img src="' . htmlspecialchars($dataUri, ENT_QUOTES, 'UTF-8') . '" alt="" '
                . 'style="max-width:100%;max-height:55vh;object-fit:contain;margin:12px auto;display:block;">';
        }
    }

    if ($parts === []) {
        return '<p style="color:#64748b;">Slide content could not be extracted. Download the original file to view it.</p>';
    }

    return '<div class="le-uploaded-slide">' . implode('', $parts) . '</div>';
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
