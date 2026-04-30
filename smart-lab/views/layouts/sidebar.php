<?php // Sidebar partial — included by views that need it ?>

<aside class="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">
      <div class="logo-icon">SL</div>
      <div class="logo-text">
        <div class="logo-title">UNILIS SmartLab</div>
        <div class="logo-sub">Laboratory Management</div>
      </div>
    </div>
  </div>
  
  <nav class="sidebar-nav">
    <ul class="nav-list">
      <!-- Dashboard - All Roles -->
      <li class="nav-item">
        <a href="<?= APP_URL ?>/dashboard" class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'dashboard.php') ? 'active' : '' ?>">
          <i class="fas fa-grid"></i>
          <span>Dashboard</span>
        </a>
      </li>
      
      <!-- Schedule - All Roles -->
      <li class="nav-item">
        <a href="<?= APP_URL ?>/schedule" class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'schedule.php') ? 'active' : '' ?>">
          <i class="fas fa-calendar"></i>
          <span>Schedule</span>
        </a>
      </li>
      
      <!-- Practicals - All Roles -->
      <li class="nav-item">
        <a href="<?= APP_URL ?>/practicals" class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'practicals.php') ? 'active' : '' ?>">
          <i class="fas fa-flask"></i>
          <span>Practicals</span>
        </a>
      </li>
      
      <!-- Practical Requests - Students Only -->
      <?php if (Auth::role() === 'student'): ?>
      <li class="nav-item">
        <a href="<?= APP_URL ?>/practical-requests" class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'practical-requests.php') ? 'active' : '' ?>">
          <i class="fas fa-clipboard"></i>
          <span>Practical Requests</span>
        </a>
      </li>
      <?php endif; ?>
      
      <!-- Manage Requests - Admin, Lecturer, Technician -->
      <?php if (in_array(Auth::role(), ['admin', 'lecturer', 'technician'])): ?>
      <li class="nav-item">
        <a href="<?= APP_URL ?>/admin" class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'admin.php') ? 'active' : '' ?>">
          <i class="fas fa-check-circle"></i>
          <span>Manage Requests</span>
        </a>
      </li>
      <?php endif; ?>
      
      <!-- Notebooks - All Roles -->
      <li class="nav-item">
        <a href="<?= APP_URL ?>/notebooks" class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'notebooks.php') ? 'active' : '' ?>">
          <i class="fas fa-book"></i>
          <span>Notebooks</span>
        </a>
      </li>
      
      <!-- Reports - All Roles -->
      <li class="nav-item">
        <a href="<?= APP_URL ?>/reports" class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'reports.php') ? 'active' : '' ?>">
          <i class="fas fa-file-text"></i>
          <span>Reports</span>
        </a>
      </li>
      
      <!-- Report Submission - Students Only -->
      <?php if (Auth::role() === 'student'): ?>
      <li class="nav-item">
        <a href="<?= APP_URL ?>/report-submission" class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'report-submission.php') ? 'active' : '' ?>">
          <i class="fas fa-upload"></i>
          <span>Report Submission</span>
        </a>
      </li>
      <?php endif; ?>
      
      <!-- Assets - Admin, Technician, Lecturer -->
      <?php if (in_array(Auth::role(), ['admin', 'technician', 'lecturer'])): ?>
      <li class="nav-item">
        <a href="<?= APP_URL ?>/assets" class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'assets.php') ? 'active' : '' ?>">
          <i class="fas fa-package"></i>
          <span>Assets</span>
        </a>
      </li>
      <?php endif; ?>
      
      <!-- Inventory - Admin, Technician -->
      <?php if (in_array(Auth::role(), ['admin', 'technician'])): ?>
      <li class="nav-item">
        <a href="<?= APP_URL ?>/inventory" class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'inventory.php') ? 'active' : '' ?>">
          <i class="fas fa-layers"></i>
          <span>Inventory</span>
        </a>
      </li>
      <?php endif; ?>
      
      <!-- Blockchain - Admin, Technician -->
      <?php if (in_array(Auth::role(), ['admin', 'technician'])): ?>
      <li class="nav-item">
        <a href="<?= APP_URL ?>/blockchain" class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'blockchain.php') ? 'active' : '' ?>">
          <i class="fas fa-link"></i>
          <span>Blockchain</span>
        </a>
      </li>
      <?php endif; ?>
      
      <!-- Audit Logs - Admin Only -->
      <?php if (Auth::role() === 'admin'): ?>
      <li class="nav-item">
        <a href="<?= APP_URL ?>/audit" class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'audit.php') ? 'active' : '' ?>">
          <i class="fas fa-shield"></i>
          <span>Audit Logs</span>
        </a>
      </li>
      
      <!-- Users - Admin Only -->
      <li class="nav-item">
        <a href="<?= APP_URL ?>/users" class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'users.php') ? 'active' : '' ?>">
          <i class="fas fa-users"></i>
          <span>Users</span>
        </a>
      </li>
      <?php endif; ?>
      
      <!-- Divider -->
      <li class="nav-divider"></li>
      
      <!-- Logout - All Roles -->
      <li class="nav-item">
        <a href="<?= APP_URL ?>/auth/logout" class="nav-link nav-logout">
          <i class="fas fa-log-out"></i>
          <span>Logout</span>
        </a>
      </li>
    </ul>
  </nav>
  
  <!-- User Info -->
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar">
        <div class="avatar-circle">
          <?= strtoupper(substr(Auth::name(), 0, 2)) ?>
        </div>
      </div>
      <div class="user-details">
        <div class="user-name"><?= htmlspecialchars(Auth::name()) ?></div>
        <div class="user-role"><?= ucfirst(htmlspecialchars(Auth::role())) ?></div>
      </div>
    </div>
  </div>
</aside>
