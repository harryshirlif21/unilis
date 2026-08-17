<?php
/**
 * Content renderer for short course lesson content.
 * Parses JSON block content from the lesson editor and renders it for learners.
 */

function render_lesson_content(string $content_html): string {
    if (empty($content_html)) {
        return '';
    }

    // Try to parse as JSON - if it's not valid JSON, return as-is (legacy HTML)
    $decoded = json_decode($content_html, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // It's legacy HTML content, return as-is
        return $content_html;
    }

    // It's JSON block content - render it
    if (!is_array($decoded)) {
        return $content_html;
    }

    $output = '';
    
    // Handle single block
    if (isset($decoded['type'])) {
        $output .= render_block($decoded);
    } 
    // Handle array of blocks
    elseif (is_array($decoded) && isset($decoded[0])) {
        foreach ($decoded as $block) {
            $output .= render_block($block);
        }
    }

    return $output;
}

function render_block(array $block): string {
    $type = $block['type'] ?? 'text';
    $content = $block['content'] ?? '';

    switch ($type) {
        case 'text':
            return render_text_block($content);
        case 'image':
            return render_image_block($content);
        case 'video':
            return render_video_block($content);
        case 'audio':
            return render_audio_block($content);
        case 'diagram':
            return render_diagram_block($content);
        case 'pdf':
            return render_pdf_block($content);
        case 'ppt':
            return render_ppt_block($content);
        default:
            return '';
    }
}

function render_text_block(string $content): string {
    return '<div class="ln-content-text">' . $content . '</div>';
}

function render_image_block(string $content): string {
    $data = json_decode($content, true);
    if (!$data) return '';
    
    $src = $data['src'] ?? '';
    $caption = $data['caption'] ?? '';
    
    if (empty($src)) return '';
    
    // Normalize path
    if (strpos($src, 'http') !== 0 && strpos($src, '/') !== 0) {
        $src = '/' . ltrim($src, '/');
    }
    
    $html = '<div class="ln-content-image">';
    $html .= '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '" style="max-width:100%;height:auto;">';
    if (!empty($caption)) {
        $html .= '<p class="ln-caption">' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $html .= '</div>';
    
    return $html;
}

function render_video_block(string $content): string {
    $data = json_decode($content, true);
    if (!$data) return '';
    
    $vidType = $data['type'] ?? 'url';
    $url = $data['url'] ?? '';
    $embed = $data['embed'] ?? '';
    $src = $data['src'] ?? '';
    
    $html = '<div class="ln-content-video">';
    
    if ($vidType === 'upload' && !empty($src)) {
        // Normalize path
        if (strpos($src, 'http') !== 0 && strpos($src, '/') !== 0) {
            $src = '/' . ltrim($src, '/');
        }
        $html .= '<video controls style="max-width:100%;"><source src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '">Your browser does not support video.</video>';
    } elseif (!empty($embed)) {
        $html .= '<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;">';
        $html .= '<iframe src="' . htmlspecialchars($embed, ENT_QUOTES, 'UTF-8') . '" style="position:absolute;top:0;left:0;width:100%;height:100%;" frameborder="0" allowfullscreen></iframe>';
        $html .= '</div>';
    } elseif (!empty($url)) {
        $html .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" class="ln-btn ln-btn-ghost">';
        $html .= '<span class="material-symbols-rounded">play_circle</span> Watch Video';
        $html .= '</a>';
    }
    
    $html .= '</div>';
    return $html;
}

function render_audio_block(string $content): string {
    $data = json_decode($content, true);
    if (!$data) return '';
    
    $src = $data['src'] ?? '';
    $name = $data['name'] ?? '';
    
    if (empty($src)) return '';
    
    // Normalize path
    if (strpos($src, 'http') !== 0 && strpos($src, '/') !== 0) {
        $src = '/' . ltrim($src, '/');
    }
    
    $html = '<div class="ln-content-audio">';
    $html .= '<audio controls style="width:100%;"><source src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '">Your browser does not support audio.</audio>';
    if (!empty($name)) {
        $html .= '<p class="ln-caption">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $html .= '</div>';
    
    return $html;
}

function render_diagram_block(string $content): string {
    $data = json_decode($content, true);
    if (!$data) return '';
    
    $src = $data['src'] ?? '';
    $caption = $data['caption'] ?? '';
    
    if (empty($src)) return '';
    
    // Normalize path
    if (strpos($src, 'http') !== 0 && strpos($src, '/') !== 0) {
        $src = '/' . ltrim($src, '/');
    }
    
    $html = '<div class="ln-content-diagram">';
    $html .= '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '" style="max-width:100%;height:auto;">';
    if (!empty($caption)) {
        $html .= '<p class="ln-caption">' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $html .= '</div>';
    
    return $html;
}

function render_pdf_block(string $content): string {
    $data = json_decode($content, true);
    if (!$data) return '';
    
    $src = $data['src'] ?? '';
    $name = $data['name'] ?? '';
    $caption = $data['caption'] ?? '';
    
    if (empty($src)) return '';
    
    // Normalize path
    if (strpos($src, 'http') !== 0 && strpos($src, '/') !== 0) {
        $src = '/' . ltrim($src, '/');
    }
    
    $html = '<div class="ln-content-pdf">';
    $html .= '<div class="ln-pdf-header">';
    $html .= '<span class="material-symbols-rounded">picture_as_pdf</span>';
    $html .= '<strong>' . htmlspecialchars($name ?: 'PDF Document', ENT_QUOTES, 'UTF-8') . '</strong>';
    $html .= '</div>';
    
    // Use learn-side PDF preview handler
    $previewUrl = '/learn/pdf_preview.php?file=' . urlencode($src) . '&embed=1';
    $html .= '<iframe src="' . htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') . '" style="width:100%;height:600px;border:1px solid var(--ln-line);border-radius:8px;"></iframe>';
    
    // Also provide download link
    $html .= '<p style="margin-top:12px;"><a href="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" class="ln-btn ln-btn-small ln-btn-ghost">';
    $html .= '<span class="material-symbols-rounded">download</span> Download PDF';
    $html .= '</a></p>';
    
    if (!empty($caption)) {
        $html .= '<p class="ln-caption">' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    
    $html .= '</div>';
    return $html;
}

function render_ppt_block(string $content): string {
    $data = json_decode($content, true);
    if (!$data) return '';
    
    $src = $data['src'] ?? '';
    $name = $data['name'] ?? '';
    $caption = $data['caption'] ?? '';
    
    if (empty($src)) return '';
    
    // Normalize path
    if (strpos($src, 'http') !== 0 && strpos($src, '/') !== 0) {
        $src = '/' . ltrim($src, '/');
    }
    
    $html = '<div class="ln-content-ppt">';
    $html .= '<div class="ln-ppt-header">';
    $html .= '<span class="material-symbols-rounded">slideshow</span>';
    $html .= '<strong>' . htmlspecialchars($name ?: 'Presentation', ENT_QUOTES, 'UTF-8') . '</strong>';
    $html .= '</div>';
    
    // Use learn-side PPT preview handler
    $previewUrl = '/learn/ppt_preview.php?file=' . urlencode($src) . '&embed=1';
    $html .= '<iframe src="' . htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') . '" style="width:100%;height:500px;border:1px solid var(--ln-line);border-radius:8px;"></iframe>';
    
    // Also provide download link
    $html .= '<p style="margin-top:12px;"><a href="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" class="ln-btn ln-btn-small ln-btn-ghost">';
    $html .= '<span class="material-symbols-rounded">download</span> Download Presentation';
    $html .= '</a></p>';
    
    if (!empty($caption)) {
        $html .= '<p class="ln-caption">' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    
    $html .= '</div>';
    return $html;
}
