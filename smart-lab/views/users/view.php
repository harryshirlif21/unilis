<?php if (isset($error) && $error): ?>
  <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>User Details - UNILIS SmartLab</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/public/css/app.css">
</head>
<body>

<div class="app-container">
  <!-- Sidebar -->
  <?php include __DIR__.'/../layouts/sidebar.php'; ?>
  
  <!-- Main Content -->
  <div class="main-content">
    <!-- Header -->
    <div class="page-header">
      <div class="page-header-content">
        <div class="page-title">
          <h1>User Details</h1>
          <div class="breadcrumb">
            <a href="<?= APP_URL ?>/dashboard">Dashboard</a>
            <span class="separator">/</span>
            <a href="<?= APP_URL ?>/users">Users</a>
            <span class="separator">/</span>
            <span class="current"><?= htmlspecialchars($user['full_name']) ?></span>
          </div>
        </div>
        <div class="page-actions">
          <a href="<?= APP_URL ?>/users" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Users
          </a>
        </div>
      </div>
    </div>

    <!-- User Profile Card -->
    <div class="card">
      <div class="card-header">
        <h2>User Profile</h2>
      </div>
      <div class="card-body">
        <div class="user-profile">
          <div class="user-avatar">
            <div class="avatar-circle">
              <?= strtoupper(substr($user['full_name'], 0, 2)) ?>
            </div>
          </div>
          <div class="user-details">
            <h3><?= htmlspecialchars($user['full_name']) ?></h3>
            <p class="user-email"><?= htmlspecialchars($user['email']) ?></p>
            <div class="user-meta">
              <span class="badge badge-<?= htmlspecialchars($user['role']) ?>">
                <?= ucfirst(htmlspecialchars($user['role'])) ?>
              </span>
              <?php if ($user['is_active']): ?>
                <span class="badge badge-success">Active</span>
              <?php else: ?>
                <span class="badge badge-danger">Inactive</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        
        <div class="user-info-grid">
          <div class="info-item">
            <label>Registration/Staff Number</label>
            <span><?= htmlspecialchars($user['reg_number'] ?? 'N/A') ?></span>
          </div>
          <div class="info-item">
            <label>Department</label>
            <span><?= htmlspecialchars($user['department'] ?? 'N/A') ?></span>
          </div>
          <div class="info-item">
            <label>Joined Date</label>
            <span><?= date('F j, Y', strtotime($user['created_at'])) ?></span>
          </div>
          <div class="info-item">
            <label>Last Updated</label>
            <span><?= date('F j, Y', strtotime($user['updated_at'])) ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="card">
      <div class="card-header">
        <h2>Recent Activity</h2>
      </div>
      <div class="card-body">
        <?php if (!empty($activities)): ?>
          <div class="activity-timeline">
            <?php foreach ($activities as $activity): ?>
              <div class="activity-item">
                <div class="activity-icon">
                  <i class="fas fa-<?= $this->getActivityIcon($activity['action']) ?>"></i>
                </div>
                <div class="activity-content">
                  <div class="activity-title"><?= htmlspecialchars($activity['action']) ?></div>
                  <div class="activity-description">
                    <?= htmlspecialchars($activity['description'] ?? 'No description') ?>
                  </div>
                  <div class="activity-time">
                    <?= $this->formatActivityTime($activity['created_at']) ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <i class="fas fa-history"></i>
            <h3>No recent activity</h3>
            <p>This user hasn't performed any actions recently.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

</body>
</html>

<?php
// Helper functions for activity display
function getActivityIcon($action) {
    $icons = [
        'login' => 'sign-in-alt',
        'logout' => 'sign-out-alt',
        'user_registered' => 'user-plus',
        'practical_created' => 'flask',
        'notebook_created' => 'book',
        'report_submitted' => 'file-text',
        'login_password' => 'key',
        'login_biometric' => 'fingerprint',
        'login_qr' => 'qrcode'
    ];
    return $icons[$action] ?? 'circle';
}

function formatActivityTime($timestamp) {
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } else {
        return date('M j, Y g:i A', $time);
    }
}
?>
