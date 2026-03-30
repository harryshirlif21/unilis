<?php
require_once '../config/db.php';
require_once '../includes/notifications.php';
session_start();

// Redirect if not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.html");
    exit;
}

$student_id = $_SESSION['user_id'];

// Handle AJAX mark as read request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_as_read') {
    header('Content-Type: application/json');
    $notification_id = intval($_POST['notification_id']);
    
    if (mark_notification_as_read($conn, $notification_id)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// Get pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 15;

$notifications_data = get_all_notifications($conn, $page, $per_page);
$notifications = $notifications_data['notifications'];
$total_pages = $notifications_data['total_pages'];
$total = $notifications_data['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header h1 {
            color: #333;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
            border: 1px solid #ddd;
        }

        .btn-secondary:hover {
            background: #e8e8e8;
        }

        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .notification-item {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .notification-item:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .notification-item.unread {
            background: linear-gradient(135deg, #f5f7fa 0%, #f0f0f0 100%);
            border-left-color: #ff6b6b;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 15px;
            margin-bottom: 10px;
        }

        .notification-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            flex: 1;
        }

        .notification-item.unread .notification-title {
            font-weight: 700;
            color: #1a1a1a;
        }

        .notification-badge {
            background: #ff6b6b;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .notification-message {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 10px;
        }

        .notification-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding-top: 10px;
            border-top: 1px solid #f0f0f0;
        }

        .notification-time {
            color: #999;
            font-size: 12px;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
        }

        .notification-actions a,
        .notification-actions button {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-view {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-view:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
        }

        .btn-mark-read {
            background: #e8e8e8;
            color: #666;
            border: 1px solid #ddd;
        }

        .btn-mark-read:hover {
            background: #d8d8d8;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .empty-state-icon {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h2 {
            color: #666;
            font-size: 20px;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
            margin-bottom: 30px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 10px 15px;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .pagination a {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            cursor: pointer;
        }

        .pagination a:hover {
            background: #667eea;
            color: white;
        }

        .pagination .current {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            border: none;
        }

        .pagination .disabled {
            color: #ccc;
            cursor: not-allowed;
            background: #f5f5f5;
            border-color: #e0e0e0;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }

            .header h1 {
                justify-content: center;
            }

            .header-actions {
                width: 100%;
                justify-content: center;
            }

            .notification-item {
                padding: 15px;
            }

            .notification-footer {
                flex-direction: column;
                align-items: flex-start;
            }

            .notification-header {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>
                <i class="fas fa-bell"></i>
                Notifications
            </h1>
            <div class="header-badge">
                Total: <?= $total ?> | Unread: <?= count(array_filter($notifications, fn($n) => !$n['is_read'])) ?>
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Notifications List -->
        <?php if (!empty($notifications)): ?>
            <div class="notifications-list">
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?= !$notif['is_read'] ? 'unread' : '' ?>" id="notif-<?= $notif['id'] ?>">
                        <div class="notification-header">
                            <div class="notification-title">
                                <?= htmlspecialchars($notif['title']) ?>
                            </div>
                            <?php if (!$notif['is_read']): ?>
                                <span class="notification-badge">NEW</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="notification-message">
                            <?= htmlspecialchars($notif['message']) ?>
                        </div>

                        <div class="notification-footer">
                            <span class="notification-time">
                                <i class="fas fa-clock"></i>
                                <?php 
                                    $time = strtotime($notif['created_at']);
                                    $now = time();
                                    $diff = $now - $time;
                                    
                                    if ($diff < 60) echo "Just now";
                                    elseif ($diff < 3600) echo floor($diff / 60) . " min ago";
                                    elseif ($diff < 86400) echo floor($diff / 3600) . " hours ago";
                                    elseif ($diff < 604800) echo floor($diff / 86400) . " days ago";
                                    else echo date('M d, Y', $time);
                                ?>
                            </span>
                            <div class="notification-actions">
                                <?php if ($notif['link']): ?>
                                    <a href="<?= htmlspecialchars($notif['link']) ?>" class="btn-view">
                                        <i class="fas fa-arrow-right"></i>
                                        View
                                    </a>
                                <?php endif; ?>
                                <?php if (!$notif['is_read']): ?>
                                    <button class="btn-mark-read" onclick="markAsRead(<?= $notif['id'] ?>)">
                                        <i class="fas fa-check"></i>
                                        Mark as Read
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1"><i class="fas fa-angles-left"></i></a>
                        <a href="?page=<?= $page - 1 ?>"><i class="fas fa-angle-left"></i></a>
                    <?php endif; ?>

                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    if ($start > 1): ?>
                        <a href="?page=1">1</a>
                        <?php if ($start > 2): ?><span>...</span><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($p = $start; $p <= $end; $p++): ?>
                        <?php if ($p === $page): ?>
                            <span class="current"><?= $p ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $p ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($end < $total_pages): ?>
                        <?php if ($end < $total_pages - 1): ?><span>...</span><?php endif; ?>
                        <a href="?page=<?= $total_pages ?>"><?= $total_pages ?></a>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>"><i class="fas fa-angle-right"></i></a>
                        <a href="?page=<?= $total_pages ?>"><i class="fas fa-angles-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <h2>No Notifications Yet</h2>
                <p>You don't have any notifications at the moment.</p>
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-home"></i>
                    Go to Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function markAsRead(notificationId) {
            const formData = new FormData();
            formData.append('action', 'mark_as_read');
            formData.append('notification_id', notificationId);

            fetch('notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = document.getElementById('notif-' + notificationId);
                    if (item) {
                        item.classList.remove('unread');
                        const btn = item.querySelector('.btn-mark-read');
                        if (btn) btn.remove();
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>
