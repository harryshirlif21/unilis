<?php if (isset($error) && $error): ?>
  <div id="page-error" data-msg="<?= htmlspecialchars($error) ?>"></div>
<?php endif; ?>
<?php if (isset($success) && $success): ?>
  <div id="page-success" data-msg="<?= htmlspecialchars($success) ?>"></div>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>SmartLab Users - UNILIS SmartLab</title>
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
          <h1>SmartLab Users</h1>
          <div class="breadcrumb">
            <a href="<?= APP_URL ?>/dashboard">Dashboard</a>
            <span class="separator">/</span>
            <span class="current">Users</span>
          </div>
        </div>
        <div class="page-actions">
          <a href="<?= APP_URL ?>/auth/registerStaff" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Staff
          </a>
          <span class="total-count"><?= $total_users ?> Total Users</span>
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-card-content">
          <div class="stat-card-icon">
            <i class="fas fa-users"></i>
          </div>
          <div class="stat-card-info">
            <div class="stat-card-number"><?= $role_counts['all'] ?></div>
            <div class="stat-card-label">All Users</div>
          </div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-card-content">
          <div class="stat-card-icon">
            <i class="fas fa-graduation-cap"></i>
          </div>
          <div class="stat-card-info">
            <div class="stat-card-number"><?= $role_counts['student'] ?></div>
            <div class="stat-card-label">Students</div>
          </div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-card-content">
          <div class="stat-card-icon">
            <i class="fas fa-chalkboard-teacher"></i>
          </div>
          <div class="stat-card-info">
            <div class="stat-card-number"><?= $role_counts['lecturer'] ?></div>
            <div class="stat-card-label">Lecturers</div>
          </div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-card-content">
          <div class="stat-card-icon">
            <i class="fas fa-user-shield"></i>
          </div>
          <div class="stat-card-info">
            <div class="stat-card-number"><?= $role_counts['technician'] + $role_counts['admin'] ?></div>
            <div class="stat-card-label">Staff</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Search and Filter Bar -->
    <div class="filter-bar">
      <div class="filter-left">
        <div class="search-input">
          <i class="fas fa-search"></i>
          <input type="text" id="searchInput" placeholder="Search by name, reg number, or email..." value="<?= htmlspecialchars($current_search) ?>">
        </div>
      </div>
      
      <div class="filter-right">
        <select id="roleFilter" class="filter-select">
          <option value="all" <?= $current_role_filter === 'all' ? 'selected' : '' ?>>All Roles</option>
          <option value="student" <?= $current_role_filter === 'student' ? 'selected' : '' ?>>Students</option>
          <option value="lecturer" <?= $current_role_filter === 'lecturer' ? 'selected' : '' ?>>Lecturers</option>
          <option value="technician" <?= $current_role_filter === 'technician' ? 'selected' : '' ?>>Technicians</option>
          <option value="admin" <?= $current_role_filter === 'admin' ? 'selected' : '' ?>>Admins</option>
        </select>
        
        <select id="statusFilter" class="filter-select">
          <option value="all" <?= $current_status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
          <option value="active" <?= $current_status_filter === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $current_status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
    </div>

    <!-- Users Table -->
    <div class="table-container">
      <table class="data-table" id="usersTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Full Name</th>
            <th>Reg / Staff No</th>
            <th>Email</th>
            <th>Role</th>
            <th>Department</th>
            <th>Assigned Lab</th>
            <th>Status</th>
            <th>Joined Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="usersTableBody">
          <?php if (!empty($users)): ?>
            <?php foreach ($users as $index => $user): ?>
              <tr data-role="<?= htmlspecialchars($user['role']) ?>" data-status="<?= $user['is_active'] ? 'active' : 'inactive' ?>">
                <td><?= $index + 1 ?></td>
                <td>
                  <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($user['full_name']) ?></div>
                  </div>
                </td>
                <td><?= htmlspecialchars($user['reg_number'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td>
                  <span class="badge badge-<?= htmlspecialchars($user['role']) ?>">
                    <?= ucfirst(htmlspecialchars($user['role'])) ?>
                  </span>
                </td>
                <td><?= htmlspecialchars($user['department'] ?? 'N/A') ?></td>
                <td>
                  <?php if ($user['lab_id'] && isset($labs[$user['lab_id']])): ?>
                    <?= htmlspecialchars($labs[$user['lab_id']]) ?>
                  <?php else: ?>
                    <span class="text-muted">Not assigned</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($user['is_active']): ?>
                    <span class="badge badge-success">Active</span>
                  <?php else: ?>
                    <span class="badge badge-danger">Inactive</span>
                  <?php endif; ?>
                </td>
                <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                <td>
                  <div class="table-actions">
                    <button class="btn btn-sm btn-primary" onclick="viewUser('<?= htmlspecialchars($user['id']) ?>')">
                      <i class="fas fa-eye"></i>
                    </button>
                    
                    <?php if ($user['id'] !== Auth::id()): ?>
                      <form method="POST" action="<?= APP_URL ?>/users/toggle" style="display: inline;">
                        <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">
                        <button type="submit" class="btn btn-sm <?= $user['is_active'] ? 'btn-warning' : 'btn-success' ?>" 
                                onclick="return confirm('Are you sure you want to <?= $user['is_active'] ? 'deactivate' : 'activate' ?> this user?')">
                          <i class="fas fa-<?= $user['is_active'] ? 'ban' : 'check' ?>"></i>
                          <?= $user['is_active'] ? 'Deactivate' : 'Activate' ?>
                        </button>
                      </form>
                      
                      <?php if ($user['role'] !== 'admin'): ?>
                        <form method="POST" action="<?= APP_URL ?>/users/delete" style="display: inline;">
                          <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">
                          <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to deactivate this user?')">
                            <i class="fas fa-trash"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="10" class="text-center">
                <div class="empty-state">
                  <i class="fas fa-users"></i>
                  <h3>No users found</h3>
                  <p>Try adjusting your search or filter criteria.</p>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- JavaScript for live filtering -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const roleFilter = document.getElementById('roleFilter');
    const statusFilter = document.getElementById('statusFilter');
    const tableRows = document.querySelectorAll('#usersTableBody tr');
    
    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedRole = roleFilter.value;
        const selectedStatus = statusFilter.value;
        
        tableRows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const role = row.getAttribute('data-role');
            const status = row.getAttribute('data-status');
            
            const matchesSearch = text.includes(searchTerm);
            const matchesRole = selectedRole === 'all' || role === selectedRole;
            const matchesStatus = selectedStatus === 'all' || status === selectedStatus;
            
            if (matchesSearch && matchesRole && matchesStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    searchInput.addEventListener('input', filterTable);
    roleFilter.addEventListener('change', filterTable);
    statusFilter.addEventListener('change', filterTable);
});

function viewUser(userId) {
    window.location.href = '<?= APP_URL ?>/users/view/' + userId;
}
</script>

</body>
</html>
