<?php
/**
 * Diagnostic script to check why the "Take Practical" button is missing.
 * Upload to your server and access via browser: https://your-domain/smart-lab/check_practical_button.php
 * 
 * This checks:
 * 1. Practicals in the database with their dates
 * 2. Access window calculations
 * 3. The current server time vs practical times
 */

// Use your production config
require_once __DIR__ . '/config/database_production.php';
// If the above fails, try: require_once __DIR__ . '/config/database.php';

echo "<h1>🔍 Take Practical Button Diagnostic</h1>";

try {
    $db = getDB();
    
    // Get the current practicals
    $stmt = $db->query("SELECT id, title, status, scheduled_date, start_time, end_time FROM practicals ORDER BY status, scheduled_date DESC");
    $practicals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Current Practs in Database</h2>";
    echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse;width:100%;font-family:sans-serif;'>";
    echo "<tr style='background:#f0f0f0;'><th>ID</th><th>Title</th><th>Status</th><th>Date</th><th>Start</th><th>End</th><th>Access Check</th></tr>";
    
    $now = new DateTimeImmutable('now');
    echo "<tr><td colspan='7'><strong>Server Current Time: " . $now->format('Y-m-d H:i:s') . " (TZ: " . date_default_timezone_get() . ")</strong></td></tr>";
    
    foreach ($practicals as $p) {
        $canTake = 'N/A';
        $reason = '';
        
        if ($p['status'] === 'published') {
            // Simulate what access window calculation does
            if (empty($p['scheduled_date']) || empty($p['start_time'])) {
                $canTake = '❌ NO';
                $reason = 'Missing date or start_time';
            } else {
                try {
                    $startAt = new DateTimeImmutable($p['scheduled_date'] . ' ' . $p['start_time']);
                    $windowStart = $startAt->modify('-30 minutes');
                    $windowEnd = $startAt->modify('+20 minutes');
                    
                    if ($now >= $windowStart && $now <= $windowEnd) {
                        $canTake = '✅ YES (within window)';
                    } elseif ($now < $windowStart) {
                        $minutes = (int)floor(($startAt->getTimestamp() - $now->getTimestamp()) / 60);
                        $canTake = "⏳ Upcoming (opens in {$minutes} min)";
                    } else {
                        $canTake = '❌ Expired (window ended)';
                    }
                    $reason = "Window: {$windowStart->format('H:i')} - {$windowEnd->format('H:i')}";
                } catch (Exception $e) {
                    $canTake = '❌ Error';
                    $reason = $e->getMessage();
                }
            }
        } else {
            $canTake = '❌ Not published';
            $reason = "Status is '{$p['status']}'";
        }
        
        $color = strpos($canTake, '✅') !== false ? '#d4edda' : (strpos($canTake, '❌') !== false ? '#f8d7da' : '#fff3cd');
        echo "<tr style='background:{$color}'>";
        echo "<td>" . htmlspecialchars(substr($p['id'], 0, 12)) . "…</td>";
        echo "<td>" . htmlspecialchars($p['title']) . "</td>";
        echo "<td>{$p['status']}</td>";
        echo "<td>{$p['scheduled_date']}</td>";
        echo "<td>{$p['start_time']}</td>";
        echo "<td>{$p['end_time']}</td>";
        echo "<td><strong>{$canTake}</strong><br><small>{$reason}</small></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>📋 Likely Cause & Solution</h2>";
    echo "<ul>";
    
    $publishedCount = count(array_filter($practicals, fn($p) => $p['status'] === 'published'));
    $withinWindow = false;
    foreach ($practicals as $p) {
        if ($p['status'] === 'published' && !empty($p['scheduled_date']) && !empty($p['start_time'])) {
            try {
                $startAt = new DateTimeImmutable($p['scheduled_date'] . ' ' . $p['start_time']);
                $windowStart = $startAt->modify('-30 minutes');
                $windowEnd = $startAt->modify('+20 minutes');
                if ($now >= $windowStart && $now <= $windowEnd) {
                    $withinWindow = true;
                    break;
                }
            } catch (Exception $e) {}
        }
    }
    
    if ($publishedCount === 0) {
        echo "<li>❌ No published practicals found. A practical must be <strong>published</strong> for the button to show.</li>";
    } elseif (!$withinWindow) {
        echo "<li>⚠️ All published practicals have dates/times that fall outside the access window.</li>";
        echo "<li>The access window opens <strong>30 minutes before</strong> start_time and closes <strong>20 minutes after</strong> start_time.</li>";
        echo "<li><strong>Solution:</strong> Create a new practical with a start_time that is near the current server time, OR adjust the existing practical's start_time.</li>";
    } else {
        echo "<li>✅ At least one practical is within the access window.</li>";
        echo "<li>If the button still doesn't show, there may be another issue.";
    }
    echo "</ul>";
    
    echo "<h2>🛠 Quick Fix Options</h2>";
    echo "<p>To immediately fix, create a new practical with current server time, or run a quick update:</p>";
    echo "<pre style='background:#f5f5f5;padding:15px;border-radius:8px;'>";
    echo "SQL to update an existing published practical's time to NOW:\n\n";
    echo "UPDATE practicals \n";
    echo "SET scheduled_date = '" . $now->format('Y-m-d') . "', \n";
    echo "    start_time = '" . $now->format('H:i:s') . "', \n";
    echo "    end_time = '" . $now->modify('+2 hours')->format('H:i:s') . "' \n";
    echo "WHERE id = 'PASTE_PRACTICAL_ID_HERE';\n";
    echo "</pre>";

} catch (Exception $e) {
    echo "<h2>❌ Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}