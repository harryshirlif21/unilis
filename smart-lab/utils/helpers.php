<?php
function redirect(string $path): void {
    header('Location: '.APP_URL.'/'.ltrim($path, '/')); exit;
}
function renderView(string $template, array $data = []): void {
    extract($data);
    require_once __DIR__.'/../views/layouts/header.php';
    require_once __DIR__.'/../views/'.$template.'.php';
    require_once __DIR__.'/../views/layouts/footer.php';
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
