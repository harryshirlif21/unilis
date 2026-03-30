<?php
require_once '../config/db.php';
session_start();

// Redirect if not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.html");
    exit;
}

$student_id = $_SESSION['user_id'];

try {
    // Get student info
    $student_stmt = $conn->prepare("SELECT id, name, email, reg_no, course_id, year_of_study FROM students WHERE id = ?");
    $student_stmt->bind_param("i", $student_id);
    $student_stmt->execute();
    $student = $student_stmt->get_result()->fetch_assoc();
    if (!$student) {
        throw new Exception("Student not found.");
    }
    $course_id = $student['course_id'];
    $year_of_study = $student['year_of_study'];
    $student_stmt->close();

    // Get enrolled units with notes count
    $units_sql = "
        SELECT 
            u.id,
            u.unit_name,
            u.unit_code,
            u.description,
            COUNT(nf.id) AS notes_count,
            l.name AS lecturer_name,
            u.semester,
            u.year
        FROM units u
        JOIN student_unit_enrollments sue ON u.id = sue.unit_id
        LEFT JOIN notes n ON n.unit_id = u.id AND n.student_id = ?
        LEFT JOIN note_files nf ON n.id = nf.note_id
        LEFT JOIN lecturers l ON u.lecturer_id = l.id
        WHERE sue.student_id = ? 
            AND sue.semester = u.semester
            AND sue.academic_year = u.year
        GROUP BY u.id, u.unit_name, u.unit_code, u.description, l.name, u.semester, u.year
        ORDER BY u.unit_name ASC
    ";
    
    $units_stmt = $conn->prepare($units_sql);
    $units_stmt->bind_param("ii", $student_id, $student_id);
    $units_stmt->execute();
    $units = $units_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $units_stmt->close();

} catch (Exception $e) {
    error_log("Error loading units: " . $e->getMessage());
    $_SESSION['error'] = "Error loading units.";
    $units = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Notes - UniLIS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --color-primary: #3b82f6;
            --color-green: #10b981;
            --color-gold: #f59e0b;
            --text: #1e293b;
            --text-2: #475569;
            --text-3: #64748b;
            --text-4: #94a3b8;
            --border-subtle: #e2e8f0;
            --border-strong: #cbd5e1;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e0e7ff 100%);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: white;
            box-shadow: var(--shadow-sm);
            padding: 1.5rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header .subtitle {
            font-size: 1rem;
            color: var(--text-3);
            font-weight: 400;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--text-2);
        }

        .user-info i {
            font-size: 1.2rem;
            color: var(--color-primary);
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Unit Tiles Grid */
        .units-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        /* Unit Tile Card */
        .unit-tile {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            border: 1px solid var(--border-subtle);
        }

        .unit-tile:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--border-strong);
        }

        .tile-accent {
            height: 4px;
            background: var(--color-primary);
        }

        .tile-accent.green { background: var(--color-green); }
        .tile-accent.gold { background: var(--color-gold); }
        .tile-accent.blue { background: #3b82f6; }
        .tile-accent.purple { background: #8b5cf6; }

        .tile-content {
            padding: 1.5rem;
        }

        .unit-code {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: var(--text-4);
            margin-bottom: 0.5rem;
        }

        .unit-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.75rem;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .unit-description {
            font-size: 0.9rem;
            color: var(--text-3);
            line-height: 1.5;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .tile-divider {
            height: 1px;
            background: var(--border-subtle);
            margin: 1rem 0;
        }

        .stats-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .notes-count {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--color-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .lecturer-info {
            font-size: 0.85rem;
            color: var(--text-3);
        }

        .semester-info {
            font-size: 0.8rem;
            color: var(--text-4);
            background: var(--border-subtle);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }

        .tile-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .view-notes-btn {
            background: var(--color-primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .view-notes-btn:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            border: 2px dashed var(--border-subtle);
            margin-top: 2rem;
        }

        .empty-icon {
            font-size: 4rem;
            color: var(--text-4);
            margin-bottom: 1rem;
        }

        .empty-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-2);
            margin-bottom: 0.5rem;
        }

        .empty-description {
            color: var(--text-3);
            margin-bottom: 2rem;
        }

        .enroll-btn {
            background: var(--color-green);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .enroll-btn:hover {
            background: #059669;
        }

        /* Hide interactive notes link */
        .interactive-notes-section {
            display: none;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .container {
                padding: 1rem;
            }

            .units-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .stats-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .header {
                padding: 1rem;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .tile-content {
                padding: 1rem;
            }

            .unit-name {
                font-size: 1.1rem;
            }
        }

        /* Loading Animation */
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border-subtle);
            border-top: 4px solid var(--color-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="header">
    <div class="header-content">
        <div>
            <h1>
                <i class="fas fa-book-open"></i>
                My Notes
            </h1>
            <div class="subtitle">Access your study materials and resources</div>
        </div>
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <span><?= htmlspecialchars($student['name']); ?></span>
        </div>
    </div>
</header>

<!-- Main Content -->
<main class="container">
    <?php if (empty($units)): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-book"></i>
            </div>
            <div class="empty-title">No Units Added Yet</div>
            <div class="empty-description">
                You haven't enrolled in any units yet. Enrol in units to start accessing your notes and study materials.
            </div>
            <a href="my_units.php" class="enroll-btn">
                <i class="fas fa-plus"></i>
                Browse Units
            </a>
        </div>
    <?php else: ?>
        <!-- Units Grid -->
        <div class="units-grid">
            <?php foreach ($units as $index => $unit): ?>
                <?php
                // Determine accent color based on index
                $accentColors = ['blue', 'green', 'gold', 'purple'];
                $accentClass = $accentColors[$index % count($accentColors)];
                ?>
                
                <div class="unit-tile" onclick="openUnitNotes(<?= $unit['id']; ?>)">
                    <div class="tile-accent <?= $accentClass; ?>"></div>
                    <div class="tile-content">
                        <div class="unit-code"><?= htmlspecialchars($unit['unit_code']); ?></div>
                        <div class="unit-name"><?= htmlspecialchars($unit['unit_name']); ?></div>
                        
                        <?php if ($unit['description']): ?>
                            <div class="unit-description"><?= htmlspecialchars($unit['description']); ?></div>
                        <?php endif; ?>
                        
                        <div class="tile-divider"></div>
                        
                        <div class="stats-row">
                            <div class="notes-count">
                                <i class="fas fa-file-alt"></i>
                                <?= $unit['notes_count']; ?> Notes
                            </div>
                            <div class="semester-info">
                                Semester <?= $unit['semester']; ?>
                            </div>
                        </div>
                        
                        <?php if ($unit['lecturer_name']): ?>
                            <div class="lecturer-info">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <?= htmlspecialchars($unit['lecturer_name']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="tile-footer">
                        <div class="lecturer-info">
                            <?= $unit['year']; ?>
                        </div>
                        <a href="notes_unit.php?unit_id=<?= $unit['id']; ?>" class="view-notes-btn">
                            View Notes
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Hide Interactive Notes Section -->
<div class="interactive-notes-section">
    <!-- This section is intentionally hidden as per requirements -->
</div>

<script>
function openUnitNotes(unitId) {
    window.location.href = 'notes_unit.php?unit_id=' + unitId;
}

// Add some interactivity
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to tiles
    const tiles = document.querySelectorAll('.unit-tile');
    tiles.forEach(tile => {
        tile.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px) scale(1.02)';
        });
        
        tile.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
});
</script>

</body>
</html>
