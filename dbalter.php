<?php
require_once __DIR__ . "/config/db.php";

echo "<h2>Database Table Viewer</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 30px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #4CAF50; color: white; }
    tr:nth-child(even) { background-color: #f2f2f2; }
    tr:hover { background-color: #ddd; }
    h3 { color: #333; margin-top: 30px; }
    .count { color: #666; font-size: 14px; }
</style>";

// Tables to view
$tables = [
    'student_classnotes_subtopic_progress',
    'student_classnotes_progress',
    'classnotes',
    'students',
    'units'
];

foreach ($tables as $table) {
    // Check if table exists
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    
    if ($check->num_rows == 0) {
        echo "<h3>❌ Table: $table</h3>";
        echo "<p>Table does not exist.</p>";
        continue;
    }
    
    // Get row count
    $count_result = $conn->query("SELECT COUNT(*) as cnt FROM $table");
    $count = $count_result->fetch_assoc()['cnt'];
    
    echo "<h3>📋 Table: $table <span class='count'>($count rows)</span></h3>";
    
    // Get table structure
    echo "<details><summary>Show Structure</summary>";
    echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    $structure = $conn->query("DESCRIBE $table");
    while ($col = $structure->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table></details>";
    
    // Get table data (limit to 50 rows)
    $data = $conn->query("SELECT * FROM $table LIMIT 50");
    
    if ($data->num_rows == 0) {
        echo "<p><em>No data in this table.</em></p>";
        continue;
    }
    
    // Get column names
    $fields = $data->fetch_fields();
    
    echo "<table><tr>";
    foreach ($fields as $field) {
        echo "<th>" . htmlspecialchars($field->name) . "</th>";
    }
    echo "</tr>";
    
    // Output rows
    while ($row = $data->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $key => $value) {
            $display = $value;
            
            // Truncate long content (like JSON)
            if ($display !== null && strlen($display) > 100) {
                $display = substr($display, 0, 100) . '...';
            }
            
            echo "<td>" . htmlspecialchars($display ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    
    if ($count > 50) {
        echo "<p><em>Showing first 50 of $count rows.</em></p>";
    }
}

// Special: View a specific classnote's full subtopics_json
echo "<h3>🔍 View Full Classnote Content</h3>";
if (isset($_GET['classnote_id'])) {
    $id = intval($_GET['classnote_id']);
    $stmt = $conn->prepare("SELECT * FROM classnotes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($note = $result->fetch_assoc()) {
        echo "<h4>Classnote ID: $id - " . htmlspecialchars($note['title']) . "</h4>";
        echo "<pre style='background:#f5f5f5; padding:15px; overflow-x:auto; white-space:pre-wrap;'>";
        echo htmlspecialchars(json_encode(json_decode($note['subtopics_json']), JSON_PRETTY_PRINT));
        echo "</pre>";
        
        // Show actual HTML content with image paths
        $subtopics = json_decode($note['subtopics_json'], true);
        if ($subtopics) {
            echo "<h4>Image Paths Found:</h4>";
            foreach ($subtopics as $sub) {
                if (!empty($sub['content'])) {
                    preg_match_all('/src="([^"]*)"/', $sub['content'], $matches);
                    if (!empty($matches[1])) {
                        echo "<ul>";
                        foreach ($matches[1] as $src) {
                            $exists = file_exists($src) ? '✅' : '❌';
                            echo "<li>$exists <code>" . htmlspecialchars($src) . "</code></li>";
                        }
                        echo "</ul>";
                    }
                }
            }
        }
    } else {
        echo "<p>Classnote not found.</p>";
    }
    $stmt->close();
} else {
    echo "<p>Add <code>?classnote_id=X</code> to URL to view full content of a specific classnote.</p>";
}

$conn->close();
?>