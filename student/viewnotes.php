<?php
require_once '../config/db.php';
require_once __DIR__ . '/../includes/academic_year_history.php';
session_start();

// Redirect if not student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.html");
    exit;
}

$student_id = (int) $_SESSION['user_id'];

// Get student course/year and details
$stmt = $conn->prepare("SELECT s.id, s.course_id, s.year_of_study, s.name, s.email, s.reg_no, s.year_joined, c.name as course_name FROM students s JOIN courses c ON s.course_id = c.id WHERE s.id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Student record not found.");

$course_id = $student['course_id'];
$year_of_study = $student['year_of_study'];
$course_name = $student['course_name'];
$currentAcademicYear = academic_year_history_current_label($conn);
$academicYearOptions = academic_year_history_student_options($student, $currentAcademicYear, $conn);
if ($academicYearOptions === []) {
    $academicYearOptions = [$currentAcademicYear => max(1, (int)($student['year_of_study'] ?? 1))];
}
$selectedAcademicYear = trim((string)($_GET['academic_year'] ?? $currentAcademicYear));
if ($selectedAcademicYear === '' || !array_key_exists($selectedAcademicYear, $academicYearOptions)) {
    $selectedAcademicYear = $currentAcademicYear;
}
$selected_year = (int)($academicYearOptions[$selectedAcademicYear] ?? max(1, (int)($student['year_of_study'] ?? 1)));
$selected_year = max(1, $selected_year);
$selectedAcademicYearRange = academic_year_history_date_range($conn, $selectedAcademicYear);
if ($selectedAcademicYearRange === null) {
    http_response_code(400);
    exit('Invalid academic-year selection.');
}
$yearTiles = [];
foreach ($academicYearOptions as $label => $studyYear) {
    $yearTiles[] = [
        'label' => (string)$label,
        'studyYear' => (int)$studyYear,
        'isCurrent' => $label === $currentAcademicYear,
        'isSelected' => $label === $selectedAcademicYear,
    ];
}

// Get latest notifications
require_once '../includes/notifications.php';
$latest_notifications = get_latest_notifications($conn, 5);

$interactiveCountSql = academic_year_history_table_exists($conn, 'classnotes')
    ? "(SELECT COUNT(*) FROM classnotes WHERE unit_id = u.id AND uploaded_at >= ? AND uploaded_at < ?) AS interactive_count"
    : '0 AS interactive_count';
$units_sql = "
    SELECT DISTINCT u.id, u.name, u.code,
           (SELECT COUNT(*) FROM notes WHERE unit_id = u.id AND uploaded_at >= ? AND uploaded_at < ?) AS file_count,
           {$interactiveCountSql}
    FROM units u
    WHERE u.course_id = ? AND u.year = ?
    ORDER BY u.name
";
$units_stmt = $conn->prepare($units_sql);
if (academic_year_history_table_exists($conn, 'classnotes')) {
    $units_stmt->bind_param(
        'ssssii',
        $selectedAcademicYearRange[0],
        $selectedAcademicYearRange[1],
        $selectedAcademicYearRange[0],
        $selectedAcademicYearRange[1],
        $course_id,
        $selected_year
    );
} else {
    $units_stmt->bind_param('ssii', $selectedAcademicYearRange[0], $selectedAcademicYearRange[1], $course_id, $selected_year);
}
$units_stmt->execute();
$units_result = $units_stmt->get_result();
$units = $units_result->fetch_all(MYSQLI_ASSOC);

$unitNotes = [];
foreach ($units as $unit) {
    $unitNotes[(int)$unit['id']] = ['files' => [], 'interactive' => []];
}

$fileNotesStmt = $conn->prepare("
    SELECT n.unit_id, n.id, n.file_path, n.uploaded_at, n.status
    FROM notes n
    INNER JOIN units u ON u.id = n.unit_id
    WHERE u.course_id = ? AND u.year = ?
      AND n.uploaded_at >= ? AND n.uploaded_at < ?
    ORDER BY u.name, n.uploaded_at DESC
");
$fileNotesStmt->bind_param('iiss', $course_id, $selected_year, $selectedAcademicYearRange[0], $selectedAcademicYearRange[1]);
$fileNotesStmt->execute();
$fileNotesResult = $fileNotesStmt->get_result();
while ($note = $fileNotesResult->fetch_assoc()) {
    $unitNotes[(int)$note['unit_id']]['files'][] = $note;
}
$fileNotesStmt->close();

if (academic_year_history_table_exists($conn, 'classnotes')) {
    $interactiveNotesStmt = $conn->prepare("
        SELECT cn.unit_id, cn.id, cn.title, cn.uploaded_at
        FROM classnotes cn
        INNER JOIN units u ON u.id = cn.unit_id
        WHERE u.course_id = ? AND u.year = ?
          AND cn.uploaded_at >= ? AND cn.uploaded_at < ?
        ORDER BY u.name, cn.uploaded_at DESC
    ");
    $interactiveNotesStmt->bind_param('iiss', $course_id, $selected_year, $selectedAcademicYearRange[0], $selectedAcademicYearRange[1]);
    $interactiveNotesStmt->execute();
    $interactiveNotesResult = $interactiveNotesStmt->get_result();
    while ($note = $interactiveNotesResult->fetch_assoc()) {
        $unitNotes[(int)$note['unit_id']]['interactive'][] = $note;
    }
    $interactiveNotesStmt->close();
}

$units = array_values(array_filter($units, static function (array $unit) use ($unitNotes): bool {
    $notes = $unitNotes[(int)$unit['id']] ?? ['files' => [], 'interactive' => []];
    return $notes['files'] !== [] || $notes['interactive'] !== [];
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Notes - UniLis</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- External CSS -->
    <link rel="stylesheet" href="css/styles.css">
    
    <style>
        
        body {
            background: #fff;
        }
        
        .notes-container {
            margin-top: 90px;
            margin-left: 0;
            padding: 20px;
            min-height: calc(100vh - 90px);
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            background: #fff;
        }

        .notes-header {
            margin-bottom: 30px;
        }

        .notes-header h1 {
            color: #1a1a2e;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .notes-header p {
            color: #555;
            font-size: 16px;
        }

        .year-tile-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 20px 0 24px;
        }

        .year-tile {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 150px;
            padding: 0.9rem 1.1rem;
            border-radius: 12px;
            background: #fff;
            border: 1px solid #dbe3f0;
            text-decoration: none;
            color: #1f2937;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        }

        .year-tile:hover {
            transform: translateY(-2px);
            border-color: #667eea;
            box-shadow: 0 10px 22px rgba(102, 126, 234, 0.12);
        }

        .year-tile.active {
            background: linear-gradient(135deg, #667eea, #4f46e5);
            color: white;
            border-color: transparent;
        }

        .year-tile small {
            display: block;
            opacity: 0.8;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .units-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .unit-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #f0f0f0;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .unit-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: transparent;
        }

        .unit-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
        }

        .unit-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .unit-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-size: 20px;
            margin-right: 15px;
        }

        .unit-info h3 {
            color: #333;
            font-size: 20px;
            margin-bottom: 5px;
        }

        .unit-info p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }

        .unit-stats {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            padding: 15px 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stat-item i {
            color: #667eea;
            font-size: 16px;
        }

        .stat-item span {
            color: #555;
            font-size: 14px;
            font-weight: 500;
        }

        .stat-count {
            background: transparent;
            color: #667eea;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #e0e0e0;
        }

        .view-notes-btn {
            width: 100%;
            padding: 12px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .view-notes-btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.2);
        }

        .notes-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, 0.62);
        }

        .notes-modal.active {
            display: flex;
        }

        .notes-modal-content {
            width: min(720px, 100%);
            max-height: min(80vh, 760px);
            overflow-y: auto;
            padding: 24px;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
        }

        .notes-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        .notes-modal-header h2 {
            margin: 0;
            color: #1a1a2e;
            font-size: 22px;
        }

        .notes-modal-close {
            border: 0;
            background: transparent;
            color: #64748b;
            font-size: 26px;
            line-height: 1;
            cursor: pointer;
        }

        .modal-note-section {
            margin-top: 20px;
        }

        .modal-note-section h3 {
            margin: 0 0 10px;
            color: #334155;
            font-size: 16px;
        }

        .modal-note-list {
            display: grid;
            gap: 10px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .modal-note-list li {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
        }

        .modal-note-date {
            display: block;
            margin-top: 4px;
            color: #64748b;
            font-size: 13px;
        }

        .modal-note-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .modal-note-actions a,
        .modal-full-view {
            color: #4f46e5;
            font-weight: 600;
            text-decoration: none;
        }

        .modal-full-view {
            display: inline-block;
            margin-top: 20px;
        }

        @media (max-width: 480px) {
            .notes-modal-content {
                padding: 18px;
            }

            .modal-note-list li {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #333;
        }

        .empty-state p {
            font-size: 16px;
            max-width: 400px;
            margin: 0 auto;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .notes-container {
                margin-left: 0;
                margin-top: 70px;
                padding: 15px;
            }

            .units-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .unit-card {
                padding: 20px;
            }

            .unit-stats {
                gap: 15px;
            }

            .notes-header h1 {
                font-size: 24px;
            }
        }

        @media (max-width: 480px) {
            .unit-header {
                flex-direction: column;
                text-align: center;
            }

            .unit-icon {
                margin-right: 0;
                margin-bottom: 10px;
            }

            .unit-stats {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="welcome-msg">
            <strong>👋 Welcome back!</strong>
        </div>

        <!-- Navigation Icons Container -->
        <div class="nav-icons-container">
            <!-- Notifications -->
            <div class="nav-icon" id="notifications-icon" style="position:relative; cursor:pointer;">
                <i class="fas fa-bell"></i>
                <!-- Red circle indicator for new notifications -->
                <span id="notificationCount" 
                      style="position:absolute; top:0; right:0; width:12px; height:12px; background:red; border-radius:50%; display:block;">
                </span>
            </div>
            <div class="nav-icon" id="profile-icon" style="cursor: pointer;">
                <i class="fas fa-user"></i>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="notes-container">
        <div class="notes-header">
            <h1>📚 My Notes</h1>
            <p><?= htmlspecialchars($selectedAcademicYear) ?> • Year <?= $selected_year ?> • Select a unit to view available notes</p>
            <div class="year-tile-grid" aria-label="Academic-year selection">
                <?php foreach ($yearTiles as $yearTile): ?>
                    <a class="year-tile <?= $yearTile['isSelected'] ? 'active' : '' ?>"
                       href="viewnotes.php?academic_year=<?= urlencode($yearTile['label']) ?>"
                       title="Open <?= htmlspecialchars($yearTile['label']) ?> notes">
                        <span>
                            <?= htmlspecialchars($yearTile['label']) ?>
                            <?php if ($yearTile['isCurrent']): ?>
                                <small>Current</small>
                            <?php else: ?>
                                <small>Year <?= (int)$yearTile['studyYear'] ?></small>
                            <?php endif; ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($units !== []): ?>
            <div class="units-grid">
                <?php foreach ($units as $unit): ?>
                    <?php $unitId = (int)$unit['id']; ?>
                    <div class="unit-card">
                        <div class="unit-header">
                            <div class="unit-icon">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="unit-info">
                                <h3><?= htmlspecialchars($unit['name']) ?></h3>
                                <p><?= htmlspecialchars($unit['code']) ?></p>
                            </div>
                        </div>
                        
                        <div class="unit-stats">
                            <div class="stat-item">
                                <i class="fas fa-file-alt"></i>
                                <span>Files:</span>
                                <span class="stat-count"><?= $unit['file_count'] ?></span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-laptop-code"></i>
                                <span>Interactive:</span>
                                <span class="stat-count"><?= $unit['interactive_count'] ?></span>
                            </div>
                        </div>
                        
                        <button type="button" class="view-notes-btn" data-modal-target="unit-notes-modal-<?= $unitId ?>">
                            <i class="fas fa-arrow-right"></i> View Notes
                        </button>
                    </div>
                    <div class="notes-modal" id="unit-notes-modal-<?= $unitId ?>" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="unit-notes-title-<?= $unitId ?>">
                        <div class="notes-modal-content">
                            <div class="notes-modal-header">
                                <div>
                                    <h2 id="unit-notes-title-<?= $unitId ?>"><?= htmlspecialchars($unit['name']) ?> (<?= htmlspecialchars($unit['code']) ?>)</h2>
                                    <span class="modal-note-date"><?= htmlspecialchars($selectedAcademicYear) ?> • Year <?= $selected_year ?></span>
                                </div>
                                <button type="button" class="notes-modal-close" aria-label="Close notes">&times;</button>
                            </div>

                            <section class="modal-note-section">
                                <h3>File Notes (<?= count($unitNotes[$unitId]['files']) ?>)</h3>
                                <?php if ($unitNotes[$unitId]['files'] !== []): ?>
                                    <ul class="modal-note-list">
                                        <?php foreach ($unitNotes[$unitId]['files'] as $note): ?>
                                            <?php $noteStatus = $note['status'] ?? 'active'; ?>
                                            <li>
                                                <div>
                                                    <strong><?= $noteStatus === 'deleted' ? 'File note (removed)' : 'File note' ?></strong>
                                                    <span class="modal-note-date"><?= date('d M Y, h:i A', strtotime($note['uploaded_at'])) ?></span>
                                                </div>
                                                <?php if ($noteStatus !== 'deleted'): ?>
                                                    <?php $filePath = '../assets/uploads/' . (string)$note['file_path']; ?>
                                                    <div class="modal-note-actions">
                                                        <a href="<?= htmlspecialchars($filePath, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">View</a>
                                                        <a href="<?= htmlspecialchars($filePath, ENT_QUOTES, 'UTF-8') ?>" download>Download</a>
                                                    </div>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p>No file notes were uploaded during this academic year.</p>
                                <?php endif; ?>
                            </section>

                            <section class="modal-note-section">
                                <h3>Interactive Notes (<?= count($unitNotes[$unitId]['interactive']) ?>)</h3>
                                <?php if ($unitNotes[$unitId]['interactive'] !== []): ?>
                                    <ul class="modal-note-list">
                                        <?php foreach ($unitNotes[$unitId]['interactive'] as $note): ?>
                                            <li>
                                                <div>
                                                    <strong><?= htmlspecialchars($note['title']) ?></strong>
                                                    <span class="modal-note-date"><?= date('d M Y, h:i A', strtotime($note['uploaded_at'])) ?></span>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <a class="modal-full-view" href="unit_notes.php?unit_id=<?= $unitId ?>&amp;academic_year=<?= urlencode($selectedAcademicYear) ?>#interactive-notes">Open interactive notes</a>
                                <?php else: ?>
                                    <p>No interactive notes were uploaded during this academic year.</p>
                                <?php endif; ?>
                            </section>

                            <a class="modal-full-view" href="unit_notes.php?unit_id=<?= $unitId ?>&amp;academic_year=<?= urlencode($selectedAcademicYear) ?>">Open full notes page</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h3>No Notes Available</h3>
                <p>No notes were uploaded for your course during <?= htmlspecialchars($selectedAcademicYear) ?> (Year <?= $selected_year ?>).</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Profile popup -->
    <div class="popup" id="profile-popup">
        <h3><?php echo htmlspecialchars($student['name']); ?></h3>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
        <p><strong>Reg No:</strong> <?php echo htmlspecialchars($student['reg_no']); ?></p>
        <p><strong>Course:</strong> <?php echo htmlspecialchars($course_name); ?></p>
        <p><strong>Year:</strong> <?php echo htmlspecialchars($student['year_of_study']); ?></p>
        <p><strong>Joined:</strong> <?php echo htmlspecialchars($student['year_joined']); ?></p>
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; text-align: center;">
            <a href="my_progress.php" style="background: #667eea; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; margin-right: 10px; text-decoration: none; display: inline-block;">
                <i class="fas fa-chart-line"></i> My Progress
            </a>
            <a href="../logout.php" style="background: #dc3545; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <!-- Latest notifications popup -->
    <div id="notifications-content" class="popup">
        <h3>Latest Notifications</h3>
        <ul>
            <?php if(empty($latest_notifications)): ?>
                <li>No notifications</li>
            <?php else: ?>
                <?php foreach($latest_notifications as $notif): ?>
                    <li>
                        <strong><?= htmlspecialchars($notif['title']) ?></strong>
                        <p><?= htmlspecialchars($notif['message']) ?></p>
                        <small><?= date('d M Y, h:i A', strtotime($notif['created_at'])) ?></small>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
        <div style="margin-top: 15px; text-align: center;">
            <button onclick="window.location.href='notifications.php'" style="background: #4A90E2; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer;">
                View All Notifications
            </button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const noteModalButtons = document.querySelectorAll('[data-modal-target]');
        noteModalButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const modal = document.getElementById(button.dataset.modalTarget);
                if (modal) {
                    modal.classList.add('active');
                    modal.setAttribute('aria-hidden', 'false');
                    modal.querySelector('.notes-modal-close')?.focus();
                }
            });
        });

        document.querySelectorAll('.notes-modal').forEach((modal) => {
            const closeButton = modal.querySelector('.notes-modal-close');
            const closeModal = () => {
                modal.classList.remove('active');
                modal.setAttribute('aria-hidden', 'true');
            };
            closeButton?.addEventListener('click', closeModal);
            modal.addEventListener('click', (event) => {
                if (event.target === modal) closeModal();
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                document.querySelectorAll('.notes-modal.active').forEach((modal) => {
                    modal.classList.remove('active');
                    modal.setAttribute('aria-hidden', 'true');
                });
            }
        });

        // Profile popup functionality
        const profileIcon = document.getElementById('profile-icon');
        const profilePopup = document.getElementById('profile-popup');
        
        if (profileIcon && profilePopup) {
            profileIcon.addEventListener('click', () => {
                const isVisible = profilePopup.style.display === 'block';
                profilePopup.style.display = isVisible ? 'none' : 'block';
                
                // Hide notifications popup
                const notificationsPopup = document.getElementById('notifications-content');
                if (notificationsPopup) {
                    notificationsPopup.style.display = 'none';
                }
            });
        }

        // Notifications functionality
        const notificationsIcon = document.getElementById('notifications-icon');
        const notificationsPopup = document.getElementById('notifications-content');
        
        if (notificationsIcon && notificationsPopup) {
            notificationsIcon.addEventListener('click', () => {
                const isVisible = notificationsPopup.style.display === 'block';
                notificationsPopup.style.display = isVisible ? 'none' : 'block';
                
                // Hide profile popup
                if (profilePopup) {
                    profilePopup.style.display = 'none';
                }
            });
        }

        // Close popups when clicking outside
        document.addEventListener('click', (e) => {
            if (!profileIcon?.contains(e.target) && !profilePopup?.contains(e.target)) {
                profilePopup.style.display = 'none';
            }
            if (!notificationsIcon?.contains(e.target) && !notificationsPopup?.contains(e.target)) {
                notificationsPopup.style.display = 'none';
            }
        });
    });

    function logout() {
        window.location.href = "../logout.php";
    }
    </script>
</body>
</html>
