<?php
require_once '../config/db.php';
session_start();
// Fetch upcoming meetings count
try {
    $meetings_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM meetings 
        WHERE unit_id IN (
            SELECT id FROM units WHERE course_id = ? AND year = ?
        ) 
        AND scheduled_time >= NOW()
    ");
    $meetings_stmt->bind_param("ii", $course_id, $year_of_study);
    $meetings_stmt->execute();
    $meetings_count = $meetings_stmt->get_result()->fetch_assoc()['count'];
    $meetings_stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("Error fetching meetings count: " . $e->getMessage());
    $meetings_count = 0;
    $_SESSION['error'] = "Unable to load meetings count.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upcoming Meetings</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">

<div id="meetings-content" class="max-w-6xl mx-auto">

    <!-- Meetings Section -->
    <section class="bg-white rounded-2xl shadow p-6 mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-semibold text-gray-800">Upcoming Meetings</h2>
            <span class="text-gray-500 font-medium"><?php echo $meetings_count; ?> Meeting<?php echo $meetings_count != 1 ? 's' : ''; ?></span>
        </div>

        <div class="overflow-x-auto">
            <?php
            try {
                $now = date('Y-m-d H:i:s');
                $meeting_query = $conn->prepare("
                    SELECT m.id, m.title, m.scheduled_time, u.name AS unit_name 
                    FROM meetings m 
                    JOIN units u ON m.unit_id = u.id 
                    WHERE u.course_id = ? AND u.year = ? AND m.scheduled_time >= ?
                    ORDER BY m.scheduled_time ASC
                ");
                $meeting_query->bind_param("iis", $course_id, $year_of_study, $now);
                $meeting_query->execute();
                $meetings = $meeting_query->get_result();

                if ($meetings->num_rows === 0) {
                    // No meetings placeholder
                    echo '<div class="flex flex-col items-center justify-center py-16 text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7H3v12a2 2 0 002 2z" />
                            </svg>
                            <p class="text-lg font-medium">No upcoming meetings scheduled.</p>
                            <p class="text-sm mt-1 text-gray-500">Check back later or contact your instructor.</p>
                          </div>';
                } else {
                    // Meetings table
                    echo '<table class="w-full table-auto border-collapse text-left">';
                    echo '<thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="py-3 px-4 text-sm font-semibold text-gray-700 uppercase">Title</th>
                                <th class="py-3 px-4 text-sm font-semibold text-gray-700 uppercase">Unit</th>
                                <th class="py-3 px-4 text-sm font-semibold text-gray-700 uppercase">Scheduled Time</th>
                                <th class="py-3 px-4 text-sm font-semibold text-gray-700 uppercase">Action</th>
                            </tr>
                          </thead>
                          <tbody class="text-gray-800">';
                    while ($meeting = $meetings->fetch_assoc()) {
                        echo "<tr class='border-b border-gray-200 hover:bg-gray-50'>
                                <td class='py-3 px-4'>" . htmlspecialchars($meeting['title']) . "</td>
                                <td class='py-3 px-4'>" . htmlspecialchars($meeting['unit_name']) . "</td>
                                <td class='py-3 px-4 text-sm'>" . date("d M Y, h:i A", strtotime($meeting['scheduled_time'])) . "</td>
                                <td class='py-3 px-4'>
                                    <a href='meeting_ide.php?meeting_id=" . htmlspecialchars($meeting['id']) . "' target='_blank' class='text-orange-500 hover:underline font-medium'>Join Meeting</a>
                                </td>
                              </tr>";
                    }
                    echo '</tbody></table>';
                }

                $meeting_query->close();
            } catch (mysqli_sql_exception $e) {
                error_log("Error fetching meetings: " . $e->getMessage());
                echo "<div class='text-center text-red-500 py-6'>Error loading meetings. Please contact the administrator.</div>";
                $_SESSION['error'] = "Unable to load meetings.";
            }
            ?>
        </div>
    </section>
</div>

</body>
</html>
