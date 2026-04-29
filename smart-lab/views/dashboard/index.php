<?php
require_once __DIR__.'/../../auth/Auth.php';
Auth::guard();
$initials = strtoupper(substr($user_name ?? 'U', 0, 1) . substr(strrchr($user_name ?? ' U', ' '), 1, 1));
$role_label = ucfirst($user_role ?? 'user');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard — UNILIS SmartLab</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/public/css/app.css">
</head>
<body>

<div class="app-layout">

<!-- ── SIDEBAR ── -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-mark">
      <div class="logo-icon">SL</div>
      <div>
        <div class="logo-name">SmartLab</div>
        <div class="logo-ver">UNILIS v1.0</div>
      </div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-group-label">Main</div>
    <a class="nav-link active" href="<?= APP_URL ?>/dashboard">
      <span class="nav-icon">⊞</span> Dashboard
    </a>
    <a class="nav-link" href="<?= APP_URL ?>/schedule">
      <span class="nav-icon">📅</span> Schedule
    </a>
    <a class="nav-link" href="<?= APP_URL ?>/practicals">
      <span class="nav-icon">🔬</span> Practicals
    </a>

    <div class="nav-group-label">Lab Work</div>
    <a class="nav-link" href="<?= APP_URL ?>/notebooks">
      <span class="nav-icon">📓</span> Lab Notebooks
    </a>
    <a class="nav-link" href="<?= APP_URL ?>/reports">
      <span class="nav-icon">📄</span> Reports
      <span class="nav-badge">3</span>
    </a>

    <div class="nav-group-label">Assets</div>
    <a class="nav-link" href="<?= APP_URL ?>/assets">
      <span class="nav-icon">🗄</span> Assets
    </a>
    <a class="nav-link" href="<?= APP_URL ?>/inventory">
      <span class="nav-icon">📦</span> Inventory
    </a>
    <a class="nav-link" href="<?= APP_URL ?>/blockchain">
      <span class="nav-icon">⛓</span> Blockchain
    </a>

    <div class="nav-group-label">System</div>
    <a class="nav-link" href="<?= APP_URL ?>/audit">
      <span class="nav-icon">🔍</span> Audit Log
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-avatar"><?= $initials ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($user_name ?? 'User') ?></div>
        <div class="user-role"><?= $role_label ?></div>
      </div>
      <a href="<?= APP_URL ?>/auth/logout" class="user-logout" title="Logout">⏻</a>
    </div>
  </div>
</aside>

<!-- ── MAIN ── -->
<div class="main">

  <!-- Page Header -->
  <div class="page-header">
    <div class="page-overline">Overview</div>
    <h1 class="page-title">Dashboard</h1>
    <div class="page-subtitle">Welcome back, <?= htmlspecialchars($user_name ?? 'User') ?>. Here's what's happening in your lab today.</div>
  </div>

  <!-- Hero Banner -->
  <div class="hero-banner">
    <h2>Lab Management Center</h2>
    <p>Monitor active sessions, track assets, and manage laboratory operations from your central dashboard.</p>
  </div>

  <!-- Content -->
  <div class="content">

    <!-- Stats Section -->
    <div class="section-header">
      <div>
        <div class="section-overline">Laboratory Metrics</div>
        <h2 class="section-title">Live Statistics</h2>
      </div>
    </div>

    <div class="grid grid-stats">
      <div class="stat-card primary">
        <div class="stat-label">Active Labs</div>
        <div class="stat-value"><?= $stats['labs'] ?? 0 ?></div>
        <div class="stat-delta">All operational</div>
      </div>
      <div class="stat-card gold">
        <div class="stat-label">Students</div>
        <div class="stat-value"><?= $stats['students'] ?? 0 ?></div>
        <div class="stat-delta">Enrolled this term</div>
      </div>
      <div class="stat-card success">
        <div class="stat-label">Practicals</div>
        <div class="stat-value"><?= $stats['practicals'] ?? 0 ?></div>
        <div class="stat-delta">Active & published</div>
      </div>
      <div class="stat-card primary">
        <div class="stat-label">Assets</div>
        <div class="stat-value"><?= $stats['assets'] ?? 0 ?></div>
        <div class="stat-delta">Available now</div>
      </div>
      <div class="stat-card warning">
        <div class="stat-label">Live Sessions</div>
        <div class="stat-value"><?= $stats['sessions'] ?? 0 ?></div>
        <div class="stat-delta">Open right now</div>
      </div>
      <div class="stat-card primary">
        <div class="stat-label">Blockchain</div>
        <div class="stat-value"><?= $stats['blocks'] ?? 0 ?></div>
        <div class="stat-delta">Blocks recorded</div>
      </div>
    </div>

    <!-- Quick Actions Panel -->
    <div class="panel-gradient">
      <div class="section-header">
        <div>
          <div class="section-overline">Quick Access</div>
          <h2 class="section-title">Quick Actions</h2>
        </div>
      </div>
      <div class="grid grid-cards">
        <a href="<?= APP_URL ?>/notebooks/create" class="card card-hover">
          <div class="card-body">
            <div class="text-lg mb-2">📓</div>
            <h4 class="text-bold">Create Notebook</h4>
            <p class="caption">Start a new lab notebook</p>
          </div>
        </a>
        <a href="<?= APP_URL ?>/practicals/create" class="card card-hover">
          <div class="card-body">
            <div class="text-lg mb-2">🔬</div>
            <h4 class="text-bold">Schedule Practical</h4>
            <p class="caption">Plan a lab session</p>
          </div>
        </a>
        <a href="<?= APP_URL ?>/practical-requests/create" class="card card-hover">
          <div class="card-body">
            <div class="text-lg mb-2">📋</div>
            <h4 class="text-bold">Request Redo</h4>
            <p class="caption">Request to redo a practical</p>
          </div>
        </a>
        <a href="<?= APP_URL ?>/report-submission" class="card card-hover">
          <div class="card-body">
            <div class="text-lg mb-2">📝</div>
            <h4 class="text-bold">Submit Report</h4>
            <p class="caption">Complete lab report</p>
          </div>
        </a>
        <a href="<?= APP_URL ?>/assets/create" class="card card-hover">
          <div class="card-body">
            <div class="text-lg mb-2">📦</div>
            <h4 class="text-bold">Add Asset</h4>
            <p class="caption">Register new equipment</p>
          </div>
        </a>
        <a id="openSmartLabView" href="<?= APP_URL ?>/smartlab?role=<?= urlencode($user_role) ?>" class="card card-hover smartlab-card">
          <div class="card-body">
            <div class="text-lg mb-2">📺</div>
            <h4 class="text-bold">Smart Lab View</h4>
            <p class="caption">Open lab projection dashboard</p>
          </div>
        </a>
      </div>
    </div>

    <!-- Schedule and Occupancy Grid -->
    <div class="grid grid-two">

      <!-- Today Schedule -->
      <div class="panel">
        <div class="section-header">
          <div>
            <div class="section-overline">Today</div>
            <h3 class="section-title">Today's Schedule</h3>
            <p class="caption"><?= date('l, d F Y') ?></p>
          </div>
          <div>
            <a href="<?= APP_URL ?>/schedule" class="btn btn-sm btn-secondary">View all</a>
          </div>
        </div>
        <div class="panel-muted">
          <?php if (!empty($schedule)): ?>
            <?php foreach ($schedule as $s): ?>
            <div class="p-3 mb-2" style="background: var(--surface); border-radius: var(--radius-md); border: 1px solid var(--border-subtle);">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="overline text-primary"><?= date('H:i', strtotime($s['start_time'])) ?> – <?= date('H:i', strtotime($s['end_time'])) ?></div>
                  <h4 class="text-bold mt-1"><?= htmlspecialchars($s['title']) ?></h4>
                  <p class="caption"><?= htmlspecialchars($s['lab_name']) ?> · <?= $s['lab_code'] ?></p>
                </div>
                <div>
                  <?php
                    $badge_class = match($s['status']) {
                      'ongoing'   => 'badge-success',
                      'published' => 'badge-primary',
                      'completed' => 'badge-neutral',
                      default     => 'badge-warning'
                    };
                  ?>
                  <span class="badge <?= $badge_class ?>"><?= ucfirst($s['status']) ?></span>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty-state">
              <h3>No practicals scheduled</h3>
              <p>Nothing scheduled for today</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Lab Occupancy -->
      <div class="panel">
        <div class="section-header">
          <div>
            <div class="section-overline">Capacity</div>
            <h3 class="section-title">Lab Occupancy</h3>
            <p class="caption">Current capacity usage</p>
          </div>
        </div>
        <div class="panel-muted">
          <?php if (!empty($labs)): ?>
            <?php foreach ($labs as $i => $lab): ?>
              <?php
                $pct = $lab['max_capacity'] > 0
                  ? round(($lab['current_count'] / $lab['max_capacity']) * 100)
                  : 0;
                $variant_class = match($i % 5) {
                  0 => 'primary',
                  1 => 'gold', 
                  2 => 'success',
                  3 => 'warning',
                  4 => 'primary'
                };
              ?>
              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="text-bold"><?= htmlspecialchars($lab['name']) ?></span>
                  <span class="badge badge-<?= $variant_class ?>"><?= $lab['current_count'] ?>/<?= $lab['max_capacity'] ?></span>
                </div>
                <div style="height: 8px; background: var(--bg-subtle); border-radius: var(--radius-pill); overflow: hidden;">
                  <div style="height: 100%; width: <?= $pct ?>%; background: var(--color-<?= $variant_class ?>); border-radius: var(--radius-pill);"></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty-state">
              <h3>No labs found</h3>
              <p>No laboratory data available</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Blockchain and Activity Grid -->
    <div class="grid grid-two">

      <!-- Recent Blockchain Blocks -->
      <div class="panel">
        <div class="section-header">
          <div>
            <div class="section-overline">Ledger</div>
            <h3 class="section-title">Blockchain Ledger</h3>
            <p class="caption">Latest asset transactions</p>
          </div>
          <div>
            <a href="<?= APP_URL ?>/blockchain" class="btn btn-sm btn-secondary">View chain</a>
          </div>
        </div>
        <div class="panel-muted">
          <?php if (!empty($blocks)): ?>
            <?php foreach ($blocks as $block): ?>
              <?php $bdata = json_decode($block['block_data'], true); ?>
              <div class="p-3 mb-2" style="background: var(--surface); border-radius: var(--radius-md); border: 1px solid var(--border-subtle);">
                <div class="d-flex align-items-start gap-2">
                  <div>
                    <span class="badge badge-primary">#<?= $block['block_index'] ?></span>
                  </div>
                  <div class="flex-1">
                    <h4 class="text-bold"><?= htmlspecialchars(ucfirst($bdata['action'] ?? $bdata['event'] ?? 'Block')) ?></h4>
                    <?php if (!empty($bdata['asset_id'])): ?>
                      <p class="caption"><?= htmlspecialchars($bdata['asset_id']) ?></p>
                    <?php endif; ?>
                    <div class="d-flex gap-2 mt-1">
                      <span class="overline"><?= date('d M Y H:i', strtotime($block['timestamp'])) ?></span>
                      <?php if (!empty($bdata['lab_id'])): ?>
                        <span class="overline"><?= htmlspecialchars($bdata['lab_id']) ?></span>
                      <?php endif; ?>
                    </div>
                    <div class="caption mt-1" style="font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text-3);">
                      <?= substr($block['hash'], 0, 40) ?>...
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty-state">
              <h3>Genesis block only</h3>
              <p>No transactions yet</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="panel">
        <div class="section-header">
          <div>
            <div class="section-overline">Audit Trail</div>
            <h3 class="section-title">Recent Activity</h3>
            <p class="caption">System audit trail</p>
          </div>
          <div>
            <a href="<?= APP_URL ?>/audit" class="btn btn-sm btn-secondary">Full log</a>
          </div>
        </div>
        <div class="panel-muted">
          <?php
          $variant_colors = ['primary', 'gold', 'success', 'warning', 'primary'];
          if (!empty($activity)):
            foreach ($activity as $i => $act):
              $variant_class = $variant_colors[$i % count($variant_colors)];
          ?>
          <div class="p-3 mb-2" style="background: var(--surface); border-radius: var(--radius-md); border: 1px solid var(--border-subtle);">
            <div class="d-flex align-items-start gap-2">
              <div>
                <div style="width: 8px; height: 8px; border-radius: var(--radius-pill); background: var(--color-<?= $variant_class ?>);"></div>
              </div>
              <div class="flex-1">
                <h4 class="text-bold"><?= htmlspecialchars($act['full_name'] ?? 'System') ?></h4>
                <p class="caption"><?= htmlspecialchars(str_replace('_',' ', $act['action'])) ?></p>
                <div class="d-flex gap-2 mt-1">
                  <span class="overline"><?= date('d M H:i', strtotime($act['created_at'])) ?></span>
                  <span class="overline"><?= htmlspecialchars($act['module'] ?? '') ?></span>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; else: ?>
            <div class="empty-state">
              <h3>No recent activity</h3>
              <p>System activity will appear here</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->
</div><!-- /app-layout -->

<style>
/* Premium Dashboard Styles */
.d-flex { display: flex; }
.align-items-start { align-items: flex-start; }
.align-items-center { align-items: center; }
.justify-content-between { justify-content: space-between; }
.gap-2 { gap: var(--space-sm); }
.gap-3 { gap: var(--space-md); }
.flex-1 { flex: 1; }
.text-lg { font-size: 18px; }
.mt-1 { margin-top: var(--space-xs); }
.mb-1 { margin-bottom: var(--space-xs); }
.mb-2 { margin-bottom: var(--space-sm); }
.mb-3 { margin-bottom: var(--space-md); }
.p-3 { padding: var(--space-md); }

/* Card hover effects */
.card-hover {
  transition: var(--transition-normal);
  text-decoration: none;
  color: var(--text);
}

.card-hover:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-lg);
  text-decoration: none;
  color: var(--text);
}

.card-body {
  padding: var(--space-lg);
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .grid-cards {
    grid-template-columns: 1fr;
  }
  
  .grid-two {
    grid-template-columns: 1fr;
  }
  
  .hero-banner {
    padding: var(--space-xl) var(--space-lg);
  }
  
  .hero-banner h2 {
    font-size: 18px;
  }
}

/* Premium stat card animations */
.stat-card {
  transition: var(--transition-normal);
}

.stat-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-lg);
}

/* Panel animations */
.panel {
  transition: var(--transition-normal);
}

.panel:hover {
  box-shadow: var(--shadow-md);
}

/* Badge animations */
.badge {
  transition: var(--transition-fast);
}

.badge:hover {
  transform: scale(1.05);
}

/* Progress bar animations */
[style*="background: var(--color-"] {
  transition: width 0.5s ease;
}

/* Empty state improvements */
.empty-state {
  transition: var(--transition-normal);
}

.empty-state:hover {
  border-color: var(--border-strong);
}
</style>

<script src="<?= APP_URL ?>/public/js/app.js"></script>
</body>
</html>
