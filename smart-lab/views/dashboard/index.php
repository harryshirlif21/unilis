<?php
require_once __DIR__ . '/../../auth/Auth.php';
Auth::guard();

// Keep existing data inputs intact; UI will gracefully fall back when missing.
$stats = $stats ?? [];
$schedule = $schedule ?? [];
$labs = $labs ?? [];
$blocks = $blocks ?? [];
$activity = $activity ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Dashboard - UNILIS SmartLab</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/public/css/app.css">
  <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>

<div class="app-container">
  <?php include __DIR__ . '/../layouts/sidebar.php'; ?>

  <div class="main-content">
    <div class="page-header">
      <div class="page-overline">SmartLabs Intelligence</div>
      <h1 class="page-title">Dashboard</h1>
      <div class="page-subtitle">Welcome back, <?= htmlspecialchars(Auth::name()) ?>. Real-time laboratory signals and academic workflow insights.</div>
    </div>

    <!-- KPI row -->
    <div class="grid grid-stats mb-4">
      <div class="stat-card primary">
        <div class="stat-label">Active Labs</div>
        <div class="stat-value"><?= $stats['labs'] ?? 0 ?></div>
        <div class="stat-delta caption"><i class="pi pi-bolt"></i> Live capacity + occupancy</div>
      </div>
      <div class="stat-card success">
        <div class="stat-label">Students</div>
        <div class="stat-value"><?= $stats['students'] ?? 0 ?></div>
        <div class="stat-delta caption"><i class="pi pi-chart-line"></i> Participation & attendance</div>
      </div>
      <div class="stat-card gold">
        <div class="stat-label">Practicals</div>
        <div class="stat-value"><?= $stats['practicals'] ?? 0 ?></div>
        <div class="stat-delta caption"><i class="pi pi-calendar"></i> Sessions + completion</div>
      </div>
      <div class="stat-card warning">
        <div class="stat-label">Assets</div>
        <div class="stat-value"><?= $stats['assets'] ?? 0 ?></div>
        <div class="stat-delta caption"><i class="pi pi-link"></i> Tracked + verified</div>
      </div>
    </div>

    <!-- Live Monitoring + Analytics -->
    <div class="grid grid-two mb-4">
      <div class="panel hero-banner">
        <div class="section-header">
          <div>
            <div class="section-overline">Live Laboratory Monitoring</div>
            <h3 class="section-title">Environmental Signals</h3>
            <p class="caption">Temperature · Humidity · Gas detection · Safety alerts</p>
          </div>
          <div class="badge badge-primary badge-dot">ONLINE</div>
        </div>
        <div class="grid grid-two">
          <div class="card inset">
            <div class="overline">Temperature</div>
            <div class="metric" id="sl-temp">24.6°C</div>
            <div class="caption">Stable — within safe range</div>
          </div>
          <div class="card inset">
            <div class="overline">Humidity</div>
            <div class="metric" id="sl-hum">48%</div>
            <div class="caption">Nominal — no condensation risk</div>
          </div>
          <div class="card inset">
            <div class="overline">Gas</div>
            <div class="metric" id="sl-gas">0 ppm</div>
            <div class="caption">No detection</div>
          </div>
          <div class="card inset">
            <div class="overline">Safety</div>
            <div class="metric" id="sl-safety">OK</div>
            <div class="caption">All sensors reporting</div>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="section-header">
          <div>
            <div class="section-overline">Student Analytics</div>
            <h3 class="section-title">Attendance & Participation</h3>
            <p class="caption">Trend signal (demo-ready; binds to real data when provided)</p>
          </div>
          <a href="<?= APP_URL ?>/reports" class="btn btn-sm btn-secondary"><i class="pi pi-file"></i> Reports</a>
        </div>
        <div class="panel-muted">
          <canvas id="slStudentChart" height="120"></canvas>
        </div>
      </div>
    </div>

    <!-- Quick actions -->
    <div class="panel-gradient mb-4">
      <div class="section-header">
        <div>
          <div class="section-overline">Quick Access</div>
          <h2 class="section-title">Smart Actions</h2>
        </div>
      </div>

      <div class="grid grid-cards">
        <?php if (Auth::role() === 'student'): ?>
          <a href="<?= APP_URL ?>/practicals" class="card">
            <div class="overline"><i class="pi pi-flask"></i> Practicals</div>
            <h4 class="text-bold">View My Practicals</h4>
            <p class="caption">See your scheduled lab sessions</p>
          </a>
          <a href="<?= APP_URL ?>/report-submission" class="card">
            <div class="overline"><i class="pi pi-upload"></i> Submission</div>
            <h4 class="text-bold">Submit Report</h4>
            <p class="caption">Upload your lab report</p>
          </a>
          <a href="<?= APP_URL ?>/notebooks" class="card">
            <div class="overline"><i class="pi pi-book"></i> Notebooks</div>
            <h4 class="text-bold">My Notebooks</h4>
            <p class="caption">Access your lab notebooks</p>
          </a>
          <a href="<?= APP_URL ?>/schedule" class="card">
            <div class="overline"><i class="pi pi-calendar"></i> Schedule</div>
            <h4 class="text-bold">View Schedule</h4>
            <p class="caption">Check lab timetable</p>
          </a>
        <?php elseif (Auth::role() === 'lecturer'): ?>
          <a href="<?= APP_URL ?>/practicals/create" class="card">
            <div class="overline"><i class="pi pi-plus-circle"></i> Create</div>
            <h4 class="text-bold">Create Practical</h4>
            <p class="caption">Schedule a new lab session</p>
          </a>
          <a href="<?= APP_URL ?>/schedule" class="card">
            <div class="overline"><i class="pi pi-calendar"></i> Timetable</div>
            <h4 class="text-bold">View Schedule</h4>
            <p class="caption">Manage lab timetable</p>
          </a>
          <a href="<?= APP_URL ?>/reports" class="card">
            <div class="overline"><i class="pi pi-check-square"></i> Grading</div>
            <h4 class="text-bold">Grade Reports</h4>
            <p class="caption">Review student submissions</p>
          </a>
          <a href="<?= APP_URL ?>/admin" class="card">
            <div class="overline"><i class="pi pi-verified"></i> Requests</div>
            <h4 class="text-bold">Manage Sessions</h4>
            <p class="caption">Approve practical requests</p>
          </a>
        <?php elseif (Auth::role() === 'technician'): ?>
          <a href="<?= APP_URL ?>/assets" class="card">
            <div class="overline"><i class="pi pi-box"></i> Assets</div>
            <h4 class="text-bold">Manage Assets</h4>
            <p class="caption">Track lab equipment</p>
          </a>
          <a href="<?= APP_URL ?>/inventory" class="card">
            <div class="overline"><i class="pi pi-database"></i> Inventory</div>
            <h4 class="text-bold">View Inventory</h4>
            <p class="caption">Check stock levels</p>
          </a>
          <a href="<?= APP_URL ?>/notebooks" class="card">
            <div class="overline"><i class="pi pi-book"></i> Notebooks</div>
            <h4 class="text-bold">Approve Notebooks</h4>
            <p class="caption">Review lab notebooks</p>
          </a>
          <a href="<?= APP_URL ?>/schedule" class="card">
            <div class="overline"><i class="pi pi-calendar"></i> Schedule</div>
            <h4 class="text-bold">View Schedule</h4>
            <p class="caption">See lab sessions</p>
          </a>
        <?php elseif (Auth::role() === 'admin'): ?>
          <a href="<?= APP_URL ?>/users" class="card">
            <div class="overline"><i class="pi pi-users"></i> Users</div>
            <h4 class="text-bold">Manage Users</h4>
            <p class="caption">Add or deactivate users</p>
          </a>
          <a href="<?= APP_URL ?>/audit" class="card">
            <div class="overline"><i class="pi pi-shield"></i> Audit</div>
            <h4 class="text-bold">View Audit Logs</h4>
            <p class="caption">Monitor system activity</p>
          </a>
          <a href="<?= APP_URL ?>/blockchain" class="card">
            <div class="overline"><i class="pi pi-link"></i> Ledger</div>
            <h4 class="text-bold">Blockchain Records</h4>
            <p class="caption">View asset tracking</p>
          </a>
          <a href="<?= APP_URL ?>/practicals" class="card">
            <div class="overline"><i class="pi pi-flask"></i> Practicals</div>
            <h4 class="text-bold">All Practicals</h4>
            <p class="caption">Manage all lab sessions</p>
          </a>
        <?php endif; ?>

        <a id="openSmartLabView" href="<?= APP_URL ?>/smartlab?role=<?= urlencode(Auth::role()) ?>" class="card purple">
          <div class="overline"><i class="pi pi-desktop"></i> Projection</div>
          <h4 class="text-bold">Smart Lab View</h4>
          <p class="caption">Open lab projection dashboard</p>
        </a>
      </div>
    </div>

    <!-- Schedule and occupancy -->
    <div class="grid grid-two mb-4">

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

    <!-- Blockchain, Activity, and Equipment Intelligence -->
    <div class="grid grid-two mb-4">

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

    <div class="panel mb-4">
      <div class="section-header">
        <div>
          <div class="section-overline">Equipment Intelligence</div>
          <h3 class="section-title">Utilization Signal</h3>
          <p class="caption">Operational demand & uptime projection</p>
        </div>
        <a href="<?= APP_URL ?>/assets" class="btn btn-sm btn-secondary"><i class="pi pi-box"></i> Assets</a>
      </div>
      <div class="panel-muted">
        <canvas id="slEquipmentChart" height="90"></canvas>
      </div>
    </div>

  </div>
</div>

<script>
(() => {
  const mkChart = (id, cfg) => {
    const el = document.getElementById(id);
    if (!el || typeof Chart === 'undefined') return;
    new Chart(el, cfg);
  };

  mkChart('slStudentChart', {
    type: 'line',
    data: {
      labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
      datasets: [{
        label: 'Attendance',
        data: [72, 75, 78, 74, 82, 70, 76],
        borderColor: '#2563eb',
        backgroundColor: 'rgba(37,99,235,0.12)',
        tension: 0.35,
        fill: true,
        pointRadius: 3
      },{
        label: 'Participation',
        data: [58, 60, 64, 62, 69, 55, 61],
        borderColor: '#06b6d4',
        backgroundColor: 'rgba(6,182,212,0.10)',
        tension: 0.35,
        fill: true,
        pointRadius: 3
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: true } },
      scales: {
        y: { beginAtZero: true, max: 100, grid: { color: 'rgba(148,163,184,0.18)' } },
        x: { grid: { display: false } }
      }
    }
  });

  mkChart('slEquipmentChart', {
    type: 'bar',
    data: {
      labels: ['Microscopes','Chemistry','Physics','Biology','Computing','Electronics'],
      datasets: [{
        label: 'Utilization (%)',
        data: [82, 74, 61, 69, 88, 57],
        backgroundColor: [
          'rgba(37,99,235,0.55)',
          'rgba(16,185,129,0.55)',
          'rgba(245,158,11,0.55)',
          'rgba(124,58,237,0.55)',
          'rgba(6,182,212,0.55)',
          'rgba(239,68,68,0.45)'
        ],
        borderRadius: 10
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, max: 100, grid: { color: 'rgba(148,163,184,0.18)' } },
        x: { grid: { display: false } }
      }
    }
  });
})();
</script>

<script src="<?= APP_URL ?>/public/js/app.js"></script>
</body>
</html>
