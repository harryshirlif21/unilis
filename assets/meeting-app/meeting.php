<?php
/**
 * UNILIS Meeting frontend shell.
 *
 * PHP rather than static HTML so every asset URL can carry a cache-busting
 * token. The JS and CSS here are served with `Cache-Control: max-age=46736`
 * (~13h), so a plain `<script src="js/meeting.js">` keeps running the previous
 * deploy's code in any browser that already holds the file — long after the
 * server has the fix. That is exactly how a shipped "join without a camera"
 * fix kept throwing NotFoundError for users.
 *
 * The token is each file's mtime, which git rewrites on pull, so it changes on
 * deploy for the files that actually changed and there is nothing to remember
 * to bump by hand.
 */

/**
 * Asset path with a cache-busting version token appended.
 */
function meeting_asset(string $relativePath): string
{
    $stamp = @filemtime(__DIR__ . '/' . $relativePath);

    return htmlspecialchars(
        $relativePath . '?v=' . ($stamp !== false ? $stamp : '0'),
        ENT_QUOTES
    );
}

// Load order matters: webrtc-core defines the namespace the rest attach to,
// and meeting.js boots the app, so it stays last.
$meetingScripts = [
    'js/webrtc-core.js',
    'js/webrtc-media.js',
    'js/webrtc-rooms.js',
    'js/ui-theme.js',
    'js/ui-layout.js',
    'js/ui-sidebar.js',
    'js/ui-notifications.js',
    'js/participants.js',
    'js/chat.js',
    'js/breakouts.js',
    'js/whiteboard.js',
    'js/screenshare.js',
    'js/polls.js',
    'js/recording.js',
    'js/captions.js',
    'js/attendance.js',
    'js/settings.js',
    'js/network.js',
    'js/meeting.js',
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>UNILIS Meeting</title>
    <link rel="stylesheet" href="<?= meeting_asset('css/meeting.css') ?>">
    <link rel="stylesheet" href="<?= meeting_asset('css/meeting-dark.css') ?>">
</head>
<body>
    <div id="app"></div>
    <script>
        // Parse meeting config from URL query parameters
        (function() {
            const params = new URLSearchParams(window.location.search);
            const wsUrl = params.get('ws_signaling_url') || '/ws/signaling';
            window.__MEETING_CONFIG__ = {
                meeting_id: parseInt(params.get('meeting_id') || '0'),
                user_id: parseInt(params.get('user_id') || '0'),
                role: params.get('role') || 'student',
                display_name: params.get('display_name') || 'User',
                title: params.get('title') || 'Meeting',
                unit_name: params.get('unit_name') || '',
                lecturer_name: params.get('lecturer_name') || '',
                scheduled_time: params.get('scheduled_time') || '',
                duration: parseInt(params.get('duration') || '60'),
                external_link: params.get('external_link') || '',
                back_url: params.get('back_url') || '/',
                is_host: params.get('role') === 'lecturer',
                ws_signaling_url: wsUrl,
                ws_media_url: params.get('ws_media_url') || '/ws/media',
            };
        })();
    </script>
<?php foreach ($meetingScripts as $script): ?>
    <script src="<?= meeting_asset($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
