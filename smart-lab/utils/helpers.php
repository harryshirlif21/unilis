<?php
function redirect(string $path): void {
    header('Location: '.APP_URL.'/'.ltrim($path, '/')); exit;
}
function renderView(string $template, array $data = []): void {
    extract($data);
    $viewPath = __DIR__.'/../views/'.$template.'.php';
    
    // Debug: Check if APP_URL is defined
    if (!defined('APP_URL')) {
        error_log("ERROR: APP_URL not defined in renderView for template: $template");
    }
    
    // Include header
    try {
        require_once __DIR__.'/../views/layouts/header.php';
    } catch (Exception $e) {
        error_log("ERROR in header.php: " . $e->getMessage());
        throw $e;
    }
    
    // Ensure the view file exists before requiring
    if (file_exists($viewPath)) {
        try {
            require $viewPath;
        } catch (Exception $e) {
            error_log("ERROR in view $template: " . $e->getMessage());
            throw $e;
        }
    } else {
        echo '<div class="alert alert-error">View file not found: ' . htmlspecialchars($viewPath) . '</div>';
        error_log("View file not found: $viewPath");
    }
    
    // Include footer
    try {
        require_once __DIR__.'/../views/layouts/footer.php';
    } catch (Exception $e) {
        error_log("ERROR in footer.php: " . $e->getMessage());
        throw $e;
    }
}
function sanitize(string $v): string {
    return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8');
}
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data); exit;
}
function generateSignature(string $techId, string $docId): string {
    return hash('sha256', $techId.$docId.date('Y-m-d').APP_NAME);
}
function logActivity(string $userId, string $action, string $module): void {
    $db = getDB();
    $db->prepare(
        "INSERT INTO audit_logs (user_id,action,module,ip_address,created_at)
         VALUES (?,?,?,?,NOW())"
    )->execute([$userId, $action, $module, $_SERVER['REMOTE_ADDR'] ?? '']);
}

function getPracticalAccessWindow(array $item, ?DateTimeInterface $now = null): array {
    $date = trim((string)($item['scheduled_date'] ?? ''));
    $startTime = trim((string)($item['start_time'] ?? ''));

    if ($date === '' || $startTime === '') {
        return [
            'can_take' => false,
            'is_upcoming' => false,
            'is_expired' => true,
            'window_starts_at' => null,
            'window_ends_at' => null,
            'minutes_to_start' => null,
            'minutes_since_start' => null,
            'label' => 'Schedule unavailable',
            'description' => 'This practical has no valid start time.'
        ];
    }

    $now = $now ? DateTimeImmutable::createFromInterface($now) : new DateTimeImmutable('now');

    try {
        $startAt = new DateTimeImmutable($date . ' ' . $startTime);
    } catch (Exception $e) {
        return [
            'can_take' => false,
            'is_upcoming' => false,
            'is_expired' => true,
            'window_starts_at' => null,
            'window_ends_at' => null,
            'minutes_to_start' => null,
            'minutes_since_start' => null,
            'label' => 'Invalid schedule',
            'description' => 'Unable to parse the practical schedule time.'
        ];
    }

    $windowStart = $startAt->modify('-30 minutes');
    $windowEnd = $startAt->modify('+20 minutes');
    $canTake = ($now >= $windowStart && $now <= $windowEnd);
    $minutesToStart = (int)floor(($startAt->getTimestamp() - $now->getTimestamp()) / 60);
    $minutesSinceStart = (int)floor(($now->getTimestamp() - $startAt->getTimestamp()) / 60);

    if ($canTake) {
        $label = 'Available now';
        $description = 'You can start this practical now. Access closes 20 minutes after start time.';
    } elseif ($now < $windowStart) {
        $label = 'Upcoming';
        $description = 'Available 30 minutes before the scheduled start time.';
    } else {
        $label = 'Closed';
        $description = 'This practical is closed. Access ends 20 minutes after the scheduled start time.';
    }

    return [
        'can_take' => $canTake,
        'is_upcoming' => $now < $windowStart,
        'is_expired' => $now > $windowEnd,
        'window_starts_at' => $windowStart->format('Y-m-d H:i:s'),
        'window_ends_at' => $windowEnd->format('Y-m-d H:i:s'),
        'minutes_to_start' => $minutesToStart,
        'minutes_since_start' => $minutesSinceStart,
        'label' => $label,
        'description' => $description
    ];
}

function sanitizeHTML(string $html): string {
    // Allow only safe HTML tags and attributes
    $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'sub', 'sup',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'a', 'img', 'div', 'span',
        'code', 'pre', 'blockquote'
    ];
    
    $allowedAttributes = [
        'href' => ['a'],
        'src' => ['img'],
        'alt' => ['img'],
        'title' => ['a', 'img', 'abbr'],
        'target' => ['a'],
        'class' => ['*'],
        'style' => ['*'],
        'colspan' => ['td', 'th'],
        'rowspan' => ['td', 'th'],
        'border' => ['table']
    ];
    
    // Remove script tags and on* event handlers
    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
    $html = preg_replace('/on\w+="[^"]*"/i', '', $html);
    $html = preg_replace('/on\w+=\'[^\']*\'/i', '', $html);
    
    // Remove javascript: protocol
    $html = preg_replace('/javascript:/i', '', $html);
    
    // Use DOMDocument to parse and clean HTML
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    
    // Load HTML with UTF-8 encoding
    $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    
    // Remove unwanted elements
    $xpath = new DOMXPath($dom);
    
    // Remove script and style elements
    foreach ($xpath->query('//script | //style') as $node) {
        $node->parentNode->removeChild($node);
    }
    
    // Clean each element
    foreach ($xpath->query('//*') as $node) {
        // Remove disallowed tags
        if (!in_array(strtolower($node->nodeName), $allowedTags)) {
            // Replace with text content
            if ($node->hasChildNodes()) {
                $fragment = $dom->createDocumentFragment();
                while ($node->firstChild) {
                    $fragment->appendChild($node->firstChild);
                }
                $node->parentNode->replaceChild($fragment, $node);
            } else {
                $node->parentNode->removeChild($node);
            }
            continue;
        }
        
        // Clean attributes
        $attributesToRemove = [];
        foreach ($node->attributes as $attr) {
            $attrName = strtolower($attr->name);
            $allowed = false;
            
            foreach ($allowedAttributes as $allowedAttr => $allowedTags) {
                if ($attrName === $allowedAttr) {
                    if ($allowedTags === ['*'] || in_array(strtolower($node->nodeName), $allowedTags)) {
                        $allowed = true;
                        break;
                    }
                }
            }
            
            // Allow data-* attributes (for TinyMCE)
            if (strpos($attrName, 'data-') === 0) {
                $allowed = true;
            }
            
            if (!$allowed) {
                $attributesToRemove[] = $attrName;
            }
        }
        
        foreach ($attributesToRemove as $attrName) {
            $node->removeAttribute($attrName);
        }
    }
    
    // Save cleaned HTML
    $cleanedHTML = $dom->saveHTML($dom->documentElement);
    
    // Remove XML declaration and extra tags added by DOMDocument
    $cleanedHTML = preg_replace('/^<\?xml[^>]*>/', '', $cleanedHTML);
    $cleanedHTML = preg_replace('/^<!DOCTYPE[^>]*>/', '', $cleanedHTML);
    $cleanedHTML = preg_replace('/<\/?(html|body)[^>]*>/', '', $cleanedHTML);
    
    return trim($cleanedHTML);
}
