<?php
/**
 * Renders lesson text HTML into bento-style dashboard cards when structured sections are detected.
 */
function render_lesson_text_bento(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    if (!preg_match('/<h[23][^>]*>\s*(definition|key\s+components?|key\s+characteristics?)/i', $html)) {
        return '<div class="glass-card bento-card bento-fade prose-card">' . $html . '</div>';
    }

    $chunks = preg_split('/(?=<h[23][^>]*>)/i', $html);
    $out    = '<div class="bento-text-grid">';

    foreach ($chunks as $chunk) {
        $chunk = trim($chunk);
        if ($chunk === '') {
            continue;
        }

        if (preg_match('/<h[23][^>]*>\s*definition\s*<\/h[23]>/i', $chunk)) {
            $body = preg_replace('/<h[23][^>]*>\s*definition\s*<\/h[23]>/i', '', $chunk, 1);
            $out .= '
            <article class="bento-card bento-fade hero-card col-span-full">
                <div class="hero-card-inner">
                    <div class="hero-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h7v7h-7z"/></svg>
                    </div>
                    <div>
                        <p class="card-eyebrow">Definition</p>
                        <div class="hero-body">' . trim($body) . '</div>
                    </div>
                </div>
            </article>';
            continue;
        }

        if (preg_match('/<h[23][^>]*>\s*key\s+components?\s*<\/h[23]>/i', $chunk)) {
            $body = preg_replace('/<h[23][^>]*>\s*key\s+components?\s*<\/h[23]>/i', '', $chunk, 1);
            $items = lesson_bento_extract_items($body);
            $icons = [
                '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
                '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/></svg>',
                '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
            ];
            $out .= '<div class="bento-components col-span-full">';
            $out .= '<p class="section-label bento-fade"><span>Key Components</span></p>';
            $out .= '<div class="component-grid">';
            $i = 0;
            foreach ($items as $item) {
                $icon = $icons[$i % count($icons)];
                $title = htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8');
                $desc  = $item['body'];
                $out .= "
                <article class=\"bento-card bento-fade component-card\">
                    <div class=\"component-icon\">{$icon}</div>
                    <h3 class=\"component-title\">{$title}</h3>
                    <div class=\"component-desc\">{$desc}</div>
                </article>";
                $i++;
            }
            if ($i === 0) {
                $out .= '<article class="bento-card bento-fade component-card col-span-full"><div class="prose-card">' . $body . '</div></article>';
            }
            $out .= '</div></div>';
            continue;
        }

        if (preg_match('/<h[23][^>]*>\s*key\s+characteristics?\s*<\/h[23]>/i', $chunk)) {
            $body = preg_replace('/<h[23][^>]*>\s*key\s+characteristics?\s*<\/h[23]>/i', '', $chunk, 1);
            $tags = lesson_bento_extract_tags($body);
            $out .= '
            <article class="bento-card bento-fade tags-card col-span-full">
                <p class="section-label"><span>Key Characteristics</span></p>
                <div class="tag-pills">';
            foreach ($tags as $tag) {
                $out .= '<span class="tag-pill">' . htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') . '</span>';
            }
            if (empty($tags)) {
                $out .= '<div class="prose-card">' . $body . '</div>';
            }
            $out .= '</div></article>';
            continue;
        }

        $out .= '<article class="glass-card bento-card bento-fade prose-card col-span-full">' . $chunk . '</article>';
    }

    $out .= '</div>';
    return $out;
}

function lesson_bento_extract_items(string $html): array
{
    $items = [];
    if (preg_match_all('/<h[34][^>]*>(.*?)<\/h[34]>\s*(.*?)(?=<h[34]|$)/is', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $match) {
            $items[] = ['title' => strip_tags($match[1]), 'body' => trim($match[2])];
        }
    }
    if (!empty($items)) {
        return $items;
    }
    if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $html, $m)) {
        foreach ($m[1] as $li) {
            $text = trim(strip_tags($li, '<strong><em><b><i>'));
            if ($text === '') {
                continue;
            }
            $parts = preg_split('/[:\-–—]\s*/', $text, 2);
            $items[] = [
                'title' => $parts[0] ?? 'Component',
                'body'  => isset($parts[1]) ? '<p>' . htmlspecialchars(trim($parts[1]), ENT_NOQUOTES, 'UTF-8') . '</p>' : '<p>' . htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8') . '</p>',
            ];
        }
    }
    if (empty($items) && preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $m)) {
        foreach ($m[1] as $i => $p) {
            $text = trim(strip_tags($p));
            if ($text === '') {
                continue;
            }
            $items[] = ['title' => 'Point ' . ($i + 1), 'body' => '<p>' . htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8') . '</p>'];
        }
    }
    return array_slice($items, 0, 6);
}

function lesson_bento_extract_tags(string $html): array
{
    $tags = [];
    if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $html, $m)) {
        foreach ($m[1] as $li) {
            $t = trim(strip_tags($li));
            if ($t !== '') {
                $tags[] = $t;
            }
        }
    }
    if (empty($tags)) {
        $plain = trim(strip_tags($html));
        if ($plain !== '') {
            $tags = preg_split('/[,;•]|\s+and\s+/i', $plain);
            $tags = array_values(array_filter(array_map('trim', $tags)));
        }
    }
    return $tags;
}
