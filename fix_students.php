<?php
require_once __DIR__ . "/config/db.php";

$result = $conn->query("SELECT * FROM meetings ORDER BY id ASC");

if ($result && $result->num_rows > 0) {
    echo "<h2>Meetings Table Data</h2>";
    echo "<table border='1' cellpadding='5'>
            <tr>
                <th>id</th>
                <th>lecturer_id</th>
                <th>unit_id</th>
                <th>title</th>
                <th>meeting_link</th>
                <th>scheduled_time</th>
                <th>duration</th>
                <th>created_at</th>
            </tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $col) {
            echo "<td>" . htmlspecialchars($col ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>ℹ️ No data found in the meetings table.</p>";
}

$conn->close();
?>
