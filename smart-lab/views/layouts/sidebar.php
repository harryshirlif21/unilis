<?php // Sidebar partial - included by views that need it ?>

<?php
  $uri = $_SERVER['REQUEST_URI'] ?? '';
  $isActive = function(string $segment) use ($uri): bool {
    return strpos($uri, $segment) !== false;
  };
?>

<aside class="sl-sidebar" data-sl-sidebar>
  <div class="sl-sidebar__header">
    <a href="<?= APP_URL ?>/dashboard" class="sl-sidebar__brand">
      <div class="sl-sidebar__brandIcon">SL</div>
      <div class="sl-sidebar__brandText">
        <div class="sl-sidebar__brandName">SmartLabs</div>
        <div class="sl-sidebar__brandSub">UNILIS Laboratory Ecosystem</div>
      </div>
    </a>
    <button class="sl-icon-btn sl-sidebar__collapseBtn" type="button" aria-label="Toggle sidebar" data-sl-sidebar-toggle>
      <i class="pi pi-bars"></i>
    </button>
  </div>

  <nav class="sl-sidebar__nav" aria-label="Primary">
    <div class="sl-nav__groupLabel">Core</div>
    <a href="<?= APP_URL ?>/dashboard" class="sl-nav__link <?= $isActive('/dashboard') ? 'active' : '' ?>">
      <i class="pi pi-th-large sl-nav__icon"></i>
      <span>Dashboard</span>
    </a>

    <a href="<?= APP_URL ?>/schedule" class="sl-nav__link <?= $isActive('/schedule') ? 'active' : '' ?>">
      <i class="pi pi-calendar sl-nav__icon"></i>
      <span>Schedule</span>
    </a>

    <a href="<?= APP_URL ?>/practicals" class="sl-nav__link <?= $isActive('/practicals') ? 'active' : '' ?>">
      <i class="pi pi-flask sl-nav__icon"></i>
      <span>Practicals</span>
    </a>

    <?php if (Auth::role() === 'student'): ?>
      <a href="<?= APP_URL ?>/practical-requests" class="sl-nav__link <?= $isActive('/practical-requests') ? 'active' : '' ?>">
        <i class="pi pi-clipboard sl-nav__icon"></i>
        <span>Practical Requests</span>
      </a>
    <?php endif; ?>

    <?php if (in_array(Auth::role(), ['admin', 'lecturer', 'technician'])): ?>
      <a href="<?= APP_URL ?>/admin" class="sl-nav__link <?= $isActive('/admin') ? 'active' : '' ?>">
        <i class="pi pi-check-circle sl-nav__icon"></i>
        <span>Manage Requests</span>
      </a>
    <?php endif; ?>

    <div class="sl-nav__groupLabel">Academic</div>
    <a href="<?= APP_URL ?>/notebooks" class="sl-nav__link <?= $isActive('/notebooks') ? 'active' : '' ?>">
      <i class="pi pi-book sl-nav__icon"></i>
      <span>Notebooks</span>
    </a>

    <a href="<?= APP_URL ?>/reports" class="sl-nav__link <?= $isActive('/reports') ? 'active' : '' ?>">
      <i class="pi pi-file-edit sl-nav__icon"></i>
      <span>Reports</span>
    </a>

    <?php if (Auth::role() === 'student'): ?>
      <a href="<?= APP_URL ?>/report-submission" class="sl-nav__link <?= $isActive('/report-submission') ? 'active' : '' ?>">
        <i class="pi pi-upload sl-nav__icon"></i>
        <span>Report Submission</span>
      </a>
    <?php endif; ?>

    <?php if (in_array(Auth::role(), ['admin', 'technician', 'lecturer'])): ?>
      <div class="sl-nav__groupLabel">Operations</div>
      <a href="<?= APP_URL ?>/assets" class="sl-nav__link <?= $isActive('/assets') ? 'active' : '' ?>">
        <i class="pi pi-box sl-nav__icon"></i>
        <span>Assets</span>
      </a>
    <?php endif; ?>

    <?php if (in_array(Auth::role(), ['admin', 'technician'])): ?>
      <a href="<?= APP_URL ?>/inventory" class="sl-nav__link <?= $isActive('/inventory') ? 'active' : '' ?>">
        <i class="pi pi-database sl-nav__icon"></i>
        <span>Inventory</span>
      </a>
    <?php endif; ?>

    <?php if (in_array(Auth::role(), ['admin', 'technician'])): ?>
      <a href="<?= APP_URL ?>/blockchain" class="sl-nav__link <?= $isActive('/blockchain') ? 'active' : '' ?>">
        <i class="pi pi-link sl-nav__icon"></i>
        <span>Blockchain</span>
      </a>
    <?php endif; ?>

    <?php if (Auth::role() === 'admin'): ?>
      <div class="sl-nav__groupLabel">System</div>
      <a href="<?= APP_URL ?>/audit" class="sl-nav__link <?= $isActive('/audit') ? 'active' : '' ?>">
        <i class="pi pi-shield sl-nav__icon"></i>
        <span>Audit Logs</span>
      </a>
      <a href="<?= APP_URL ?>/users" class="sl-nav__link <?= $isActive('/users') ? 'active' : '' ?>">
        <i class="pi pi-users sl-nav__icon"></i>
        <span>Users</span>
      </a>
    <?php endif; ?>

    <div class="sl-sidebar__spacer"></div>

    <a href="<?= APP_URL ?>/auth/logout" class="sl-nav__link sl-nav__link--danger">
      <i class="pi pi-sign-out sl-nav__icon"></i>
      <span>Logout</span>
    </a>
  </nav>

  <div class="sl-sidebar__footer">
    <div class="sl-userCard">
      <div class="sl-userCard__avatar">
        <?= strtoupper(substr(Auth::name(), 0, 2)) ?>
      </div>
      <div class="sl-userCard__meta">
        <div class="sl-userCard__name"><?= htmlspecialchars(Auth::name()) ?></div>
        <div class="sl-userCard__role"><?= htmlspecialchars(ucfirst(Auth::role())) ?></div>
      </div>
      <button class="sl-icon-btn" type="button" aria-label="Toggle theme" data-sl-theme-toggle>
        <i class="pi pi-moon"></i>
      </button>
    </div>
  </div>
</aside>

<div class="sl-sidebarOverlay" data-sl-sidebar-overlay aria-hidden="true"></div>
