<?php // Sidebar partial - included by views that need it ?>

<aside class="promax-sidebar" x-data="{ expanded: false }">
  <div class="promax-sidebar-header">
    <div class="promax-logo">
      <div class="promax-logo-icon">SL</div>
      <div>
        <div class="text-prose font-semibold">UNILIS SmartLab</div>
        <div class="text-xs opacity-70">Laboratory Management</div>
      </div>
    </div>
  </div>
  
  <nav>
    <ul class="promax-nav-list">
      <!-- Dashboard - All Roles -->
      <li class="promax-nav-item">
        <a href="<?= APP_URL ?>/dashboard" class="promax-nav-link <?= (strpos($_SERVER['REQUEST_URI'], '/dashboard') !== false) ? 'active' : '' ?>">
          <i class="fas fa-grid promax-nav-icon"></i>
          <span>Dashboard</span>
        </a>
      </li>
      
      <!-- Schedule - All Roles -->
      <li class="promax-nav-item">
        <a href="<?= APP_URL ?>/schedule" class="promax-nav-link <?= (strpos($_SERVER['REQUEST_URI'], '/schedule') !== false) ? 'active' : '' ?>">
          <i class="fas fa-calendar promax-nav-icon"></i>
          <span>Schedule</span>
        </a>
      </li>
      
      <!-- Practicals - All Roles -->
      <li class="promax-nav-item">
        <a href="<?= APP_URL ?>/practicals" class="promax-nav-link <?= (strpos($_SERVER['REQUEST_URI'], '/practicals') !== false) ? 'active' : '' ?>">
          <i class="fas fa-flask promax-nav-icon"></i>
          <span>Practicals</span>
        </a>
      </li>
      
      <!-- Practical Requests - Students Only -->
      <?php if (Auth::role() === 'student'): ?>
      <li class="promax-nav-item">
        <a href="<?= APP_URL ?>/practical-requests" class="promax-nav-link <?= (strpos($_SERVER['REQUEST_URI'], '/practical-requests') !== false) ? 'active' : '' ?>">
          <i class="fas fa-clipboard promax-nav-icon"></i>
          <span>Practical Requests</span>
        </a>
      </li>
      <?php endif; ?>
      
      <!-- Manage Requests - Admin, Lecturer, Technician -->
      <?php if (in_array(Auth::role(), ['admin', 'lecturer', 'technician'])): ?>
      <li class="promax-nav-item">
        <a href="<?= APP_URL ?>/admin" class="promax-nav-link <?= (strpos($_SERVER['REQUEST_URI'], '/admin') !== false) ? 'active' : '' ?>">
          <i class="fas fa-check-circle promax-nav-icon"></i>
          <span>Manage Requests</span>
        </a>
      </li>
      <?php endif; ?>
      
      <!-- Notebooks - All Roles -->
      <li class="promax-nav-item">
        <a href="<?= APP_URL ?>/notebooks" class="promax-nav-link <?= (strpos($_SERVER['REQUEST_URI'], '/notebooks') !== false) ? 'active' : '' ?>">
          <i class="fas fa-book promax-nav-icon"></i>
          <span>Notebooks</span>
        </a>
      </li>
      
      <!-- Reports - All Roles -->
      <li class="promax-nav-item">
        <a href="<?= APP_URL ?>/reports" class="promax-nav-link <?= (strpos($_SERVER['REQUEST_URI'], '/reports') !== false) ? 'active' : '' ?>">
          <i class="fas fa-file-text promax-nav-icon"></i>
          <span>Reports</span>
        </a>
      </li>
      
      <!-- Report Submission - Students Only -->
      <?php if (Auth::role() === 'student'): ?>
      <li class="promax-nav-item">
        <a href="<?= APP_URL ?>/report-submission" class="promax-nav-link <?= (strpos($_SERVER['REQUEST_URI'], '/report-submission') !== false) ? 'active' : '' ?>">
          <i class="fas fa-upload promax-nav-icon"></i>
          <span>Report Submission</span>
        </a>
      </li>
      <?php endif; ?>
      
      <!-- Assets - Admin, Technician, Lecturer -->
      <?php if (in_array(Auth::role(), ['admin', 'technician', 'lecturer'])): ?>
      <li class="promax-nav-item">
        <a href="<?= APP_URL ?>/assets" class="promax-nav-link <?= (strpos($_SERVER['REQUEST_URI'], '/assets') !== false) ? 'active' : '' ?>">
          <i class="fas fa-package promax-nav-icon"></i>
          <span>Assets</span>
        </a>
      </li>
      <?php endif; ?>
      
      <!-- Inventory - Admin, Technician -->
      <?php if (in_array(Auth::role(), ['admin', 'technician'])): ?>
      <li class="promax-nav-item">
        <a href="<?= APP_URL ?>/inventory" class="promax-nav-link <?= (strpos($_SERVER['REQUEST_URI'], '/inventory') !== false) ? 'active' : '' ?>">
          <i class="fas fa-layers promax-nav-icon"></i>
          <span>Inventory</span>
        </a>
      </li>
      <?php endif; ?>
      
      <!-- Blockchain - Admin, Technician -->
      <?php if (in_array(Auth::role(), ['admin', 'technician'])): ?>
      <li class="promax-nav-item">
        <a href="<?= APP_URL ?>/blockchain" class="promax-nav-link <?= (strpos($_SERVER['REQUEST_URI'], '/blockchain') !== false) ? 'active' : '' ?>">
          <i class="fas fa-link promax-nav-icon"></i>
          <span>Blockchain</span>
        </a>
      </li>
      <?php endif; ?>
      
      <!-- Audit Logs - Admin Only -->
      <?php if (Auth::role() === 'admin'): ?>
      <li class="promax-nav-item">
        <a href="<?= APP_URL ?>/audit" class="promax-nav-link <?= (strpos($_SERVER['REQUEST_URI'], '/audit') !== false) ? 'active' : '' ?>">
          <i class="fas fa-shield promax-nav-icon"></i>
          <span>Audit Logs</span>
        </a>
      </li>
      
      <!-- Users - Admin Only -->
      <li class="promax-nav-item">
        <a href="<?= APP_URL ?>/users" class="promax-nav-link <?= (strpos($_SERVER['REQUEST_URI'], '/users') !== false) ? 'active' : '' ?>">
          <i class="fas fa-users promax-nav-icon"></i>
          <span>Users</span>
        </a>
      </li>
      <?php endif; ?>
      
      <!-- Divider -->
      <li class="promax-nav-item" style="margin: var(--space-lg) var(--space-lg); border-top: 1px solid var(--dark-border);"></li>
      
      <!-- Logout - All Roles -->
      <li class="promax-nav-item">
        <a href="<?= APP_URL ?>/auth/logout" class="promax-nav-link" style="color: var(--danger);">
          <i class="fas fa-log-out promax-nav-icon"></i>
          <span>Logout</span>
        </a>
      </li>
    </ul>
  </nav>
  
  <!-- User Info -->
  <div class="promax-sidebar-footer" style="position: absolute; bottom: 0; left: 0; right: 0; padding: var(--space-lg); border-top: 1px solid var(--dark-border);">
    <div class="glass-dark" style="padding: var(--space-md); border-radius: 8px;">
      <div style="display: flex; align-items: center; gap: var(--space-md);">
        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary), var(--accent)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.875rem;">
          <?= strtoupper(substr(Auth::name(), 0, 2)) ?>
        </div>
        <div style="flex: 1;">
          <div style="color: white; font-weight: 500; font-size: 0.875rem;"><?= htmlspecialchars(Auth::name()) ?></div>
          <div style="color: rgba(255, 255, 255, 0.7); font-size: 0.75rem; text-transform: capitalize;"><?= htmlspecialchars(Auth::role()) ?></div>
        </div>
      </div>
    </div>
  </div>
</aside>
