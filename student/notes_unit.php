<?php
require_once '../config/db.php';
session_start();

// Redirect if not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.html");
    exit;
}

$student_id = $_SESSION['user_id'];
$unit_id = (int)($_GET['unit_id'] ?? 0);

try {
    // Validate unit_id
    if ($unit_id <= 0) {
        throw new Exception("Invalid unit ID");
    }

    // Verify student is enrolled in this unit
    $auth_sql = "
        SELECT 1
        FROM units u
        JOIN student_unit_enrollments sue ON u.id = sue.unit_id
        WHERE u.id = ? 
            AND sue.student_id = ?
            AND sue.semester = u.semester
            AND sue.academic_year = u.year
        LIMIT 1
    ";
    
    $auth_stmt = $conn->prepare($auth_sql);
    $auth_stmt->bind_param("ii", $unit_id, $student_id);
    $auth_stmt->execute();
    
    if ($auth_stmt->get_result()->num_rows === 0) {
        throw new Exception("You are not enrolled in this unit");
    }
    $auth_stmt->close();

    // Get unit information
    $unit_sql = "
        SELECT 
            u.unit_name,
            u.unit_code,
            u.description,
            u.semester,
            u.year,
            l.name AS lecturer_name
        FROM units u
        LEFT JOIN lecturers l ON u.lecturer_id = l.id
        WHERE u.id = ?
        LIMIT 1
    ";
    
    $unit_stmt = $conn->prepare($unit_sql);
    $unit_stmt->bind_param("i", $unit_id);
    $unit_stmt->execute();
    $unit = $unit_stmt->get_result()->fetch_assoc();
    
    if (!$unit) {
        throw new Exception("Unit not found");
    }
    $unit_stmt->close();

    // Get notes for this unit
    $notes_sql = "
        SELECT 
            n.id AS note_id,
            n.title,
            n.description,
            n.uploaded_at,
            nf.id AS file_id,
            nf.original_name,
            nf.file_size,
            nf.mime_type,
            nf.uploaded_at AS file_uploaded_at
        FROM notes n
        LEFT JOIN note_files nf ON n.id = nf.note_id
        WHERE n.unit_id = ? 
            AND n.student_id = ?
        ORDER BY n.uploaded_at DESC, nf.uploaded_at DESC
    ";
    
    $notes_stmt = $conn->prepare($notes_sql);
    $notes_stmt->bind_param("ii", $unit_id, $student_id);
    $notes_stmt->execute();
    $notes = $notes_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $notes_stmt->close();

} catch (Exception $e) {
    error_log("Error loading unit notes: " . $e->getMessage());
    $_SESSION['error'] = "Error loading unit notes.";
    header("Location: notes.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($unit['unit_name']); ?> - Notes - UniLIS</title>
    
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

        .back-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--color-primary);
            text-decoration: none;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .back-link:hover {
            background: rgba(59, 130, 246, 0.1);
        }

        .unit-info {
            text-align: center;
        }

        .unit-code {
            font-size: 0.9rem;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: var(--text-4);
            margin-bottom: 0.5rem;
        }

        .unit-name {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 0.5rem;
        }

        .notes-count {
            font-size: 1.1rem;
            color: var(--text-2);
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Notes Grid */
        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        /* Note Tile Card */
        .note-tile {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: 1px solid var(--border-subtle);
        }

        .note-tile:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--border-strong);
        }

        .note-content {
            padding: 1.5rem;
        }

        .file-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            text-align: center;
        }

        .pdf-icon { color: #dc2626; }
        .doc-icon { color: #2563eb; }
        .image-icon { color: #10b981; }
        .default-icon { color: var(--text-4); }

        .note-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.5rem;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .note-description {
            font-size: 0.9rem;
            color: var(--text-3);
            line-height: 1.5;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .note-meta {
            font-size: 0.8rem;
            color: var(--text-4);
            margin-bottom: 1rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--color-primary);
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: var(--border-subtle);
            color: var(--text);
            border: 1px solid var(--border-strong);
        }

        .btn-secondary:hover {
            background: var(--border-strong);
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

        .upload-btn {
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

        .upload-btn:hover {
            background: #059669;
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

            .notes-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        @media (max-width: 480px) {
            .header {
                padding: 1rem;
            }

            .unit-name {
                font-size: 1.5rem;
            }
        }

        /* File type detection */
        .file-size {
            color: var(--text-4);
            font-size: 0.75rem;
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="header">
    <div class="header-content">
        <a href="notes.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Back to Notes
        </a>
        <div class="unit-info">
            <div class="unit-code"><?= htmlspecialchars($unit['unit_code']); ?></div>
            <div class="unit-name"><?= htmlspecialchars($unit['unit_name']); ?></div>
            <div class="notes-count"><?= count($notes); ?> notes in this unit</div>
        </div>
    </div>
</header>

<!-- Main Content -->
<main class="container">
    <?php if (empty($notes)): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="empty-title">No Notes Yet</div>
            <div class="empty-description">
                No notes have been uploaded for this unit yet. Check back later or contact your lecturer for study materials.
            </div>
        </div>
    <?php else: ?>
        <!-- Notes Grid -->
        <div class="notes-grid">
            <?php foreach ($notes as $note): ?>
                <?php
                // Determine file type and icon
                $iconClass = 'default-icon';
                if ($note['mime_type']) {
                    if (strpos($note['mime_type'], 'pdf') !== false) {
                        $iconClass = 'pdf-icon';
                    } elseif (strpos($note['mime_type'], 'word') !== false || strpos($note['mime_type'], 'document') !== false) {
                        $iconClass = 'doc-icon';
                    } elseif (strpos($note['mime_type'], 'image') !== false) {
                        $iconClass = 'image-icon';
                    }
                }
                
                $fileSize = $note['file_size'] ? number_format($note['file_size'] / 1024, 1) . ' KB' : 'Unknown size';
                ?>
                
                <div class="note-tile">
                    <div class="note-content">
                        <div class="file-icon">
                            <i class="fas <?= $iconClass; ?>"></i>
                        </div>
                        
                        <div class="note-title">
                            <?= htmlspecialchars($note['title'] ?: 'Untitled Note'); ?>
                        </div>
                        
                        <?php if ($note['description']): ?>
                            <div class="note-description">
                                <?= htmlspecialchars($note['description']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="note-meta">
                            <i class="fas fa-clock"></i>
                            <?= date('d M Y H:i', strtotime($note['file_uploaded_at'] ?: $note['uploaded_at'])); ?>
                            <span class="file-size">• <?= $fileSize; ?></span>
                        </div>
                        
                        <div class="action-buttons">
                            <?php if ($note['file_id']): ?>
                                <a href="../assets/uploads/<?= htmlspecialchars($note['original_name']); ?>" 
                                   target="_blank" 
                                   class="btn btn-primary">
                                    <i class="fas fa-eye"></i>
                                    Open
                                </a>
                                <a href="../assets/uploads/<?= htmlspecialchars($note['original_name']); ?>" 
                                   download 
                                   class="btn btn-secondary">
                                    <i class="fas fa-download"></i>
                                    Download
                                </a>
                            <?php else: ?>
                                <span style="color: var(--text-4); font-size: 0.9rem;">
                                    No file attached
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to tiles
    const tiles = document.querySelectorAll('.note-tile');
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
