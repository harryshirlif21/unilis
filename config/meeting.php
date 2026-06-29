<?php
/**
 * Meeting media server configuration (Python WebSocket renderer).
 * Used by meeting_host.php and meeting_join.php in the browser.
 */

/**
 * WebSocket URL for the Python meeting media server.
 *
 * Override with MEETING_MEDIA_WS_URL for production (e.g. wss://your-domain.com/ws/media).
 * Otherwise derives host from the current HTTP request so online tests work without extra setup.
 */
function getMeetingMediaWsUrl(): string
{
    $explicit = getenv('MEETING_MEDIA_WS_URL');
    if ($explicit !== false && $explicit !== '') {
        return $explicit;
    }

    $port = getenv('MEETING_MEDIA_PORT') ?: '8765';
    $path = '/ws/media';

    if (PHP_SAPI === 'cli') {
        return "ws://localhost:{$port}{$path}";
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = preg_replace('/:\d+$/', '', $host);

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $scheme = $isSecure ? 'wss' : 'ws';

    return "{$scheme}://{$host}:{$port}{$path}";
}
