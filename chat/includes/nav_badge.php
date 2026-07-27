<?php
/**
 * Unread-chat badge for a dashboard sidebar.
 *
 * Include once, just before </body>, on any page that renders a nav item
 * containing an element with id="chatNavBadge". Kept as a shared partial so the
 * student and lecturer dashboards - which use different icon sets and different
 * sidebar markup - do not each carry their own copy of the polling logic.
 *
 * The endpoint it calls is deliberately the cheap one: a single indexed count,
 * no group sync and no conversation list, because this runs on every dashboard.
 */
?>
<style>
    .chat-nav-badge {
        display: inline-grid;
        place-items: center;
        min-width: 19px;
        height: 19px;
        margin-left: auto;
        padding: 0 6px;
        border-radius: 10px;
        background: #dc2626;
        color: #fff;
        font-size: 0.7rem;
        font-weight: 700;
        line-height: 1;
    }
    .chat-nav-badge[hidden] { display: none; }
</style>
<script>
(function () {
    var badge = document.getElementById('chatNavBadge');
    if (!badge) { return; }

    // Relative to the dashboard, which lives one directory below the app root.
    var ENDPOINT = '../chat/api/unread_count.php';
    var POLL_MS = 45000;

    function refresh() {
        if (document.hidden) { return; }

        fetch(ENDPOINT, { credentials: 'same-origin' })
            .then(function (response) {
                // 503 means the migration has not been run yet. Stay silent
                // rather than logging an error on every dashboard load.
                if (!response.ok) { return null; }
                return response.json();
            })
            .then(function (data) {
                if (!data || !data.success) { return; }
                var total = data.unread_total || 0;
                badge.textContent = total > 99 ? '99+' : total;
                badge.hidden = total === 0;
            })
            .catch(function () { /* Offline or logged out: leave the badge as is. */ });
    }

    refresh();
    setInterval(refresh, POLL_MS);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { refresh(); }
    });
}());
</script>
