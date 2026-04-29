<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UNILIS SmartLab - <?= $title ?? 'Dashboard' ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            /* BACKGROUNDS — body, sections, hero zones */
            --bg-body:    linear-gradient(145deg, #eef2f7 0%, #e8edf5 50%, #edf1f7 100%);
            --bg-section: linear-gradient(135deg, #f5f7fa 0%, #eef1f8 100%);
            --bg-hero:    linear-gradient(160deg, #e8f0fe 0%, #f0f4ff 60%, #faf5ff 100%);
            --bg-alt:     linear-gradient(135deg, #fafbff 0%, #f3f6fd 100%);
            --bg-subtle:  #e8edf5;
            --bg-muted:   #dde3ed;

            /* SURFACES — card and panel fills */
            --surface:           #ffffff;
            --surface-raised:    linear-gradient(160deg, #ffffff 0%, #fafbff 100%);
            --surface-inset:     #f4f6fb;
            --surface-glass:     rgba(255,255,255,0.82);
            --surface-alt:       #f1f5f9;
            --surface-highlight: linear-gradient(135deg, #eff6ff 0%, #f5f3ff 100%);

            /* ACCENT SURFACES — tinted card backgrounds */
            --surface-blue:   linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            --surface-green:  linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            --surface-gold:   linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            --surface-orange: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
            --surface-purple: linear-gradient(135deg, #faf5ff 0%, #ede9fe 100%);

            /* COLORS — brand and semantic */
            --color-primary:        #2563eb;
            --color-primary-dark:   #1d4ed8;
            --color-primary-soft:   rgba(37,99,235,0.10);
            --color-primary-border: rgba(37,99,235,0.22);
            --color-green:          #16a34a;
            --color-green-dark:     #15803d;
            --color-green-soft:     rgba(22,163,74,0.10);
            --color-green-border:   rgba(22,163,74,0.22);
            --color-gold:           #b45309;
            --color-gold-soft:      rgba(180,83,9,0.10);
            --color-gold-border:    rgba(180,83,9,0.22);
            --color-orange:         #ea580c;
            --color-orange-soft:    rgba(234,88,12,0.10);
            --color-orange-border:  rgba(234,88,12,0.22);
            --color-danger:         #dc2626;
            --color-danger-soft:    rgba(220,38,38,0.10);
            --color-danger-border:  rgba(220,38,38,0.22);
            --color-purple:         #7c3aed;
            --color-purple-soft:    rgba(124,58,237,0.10);

            /* TEXT — hierarchy */
            --text:         #0a0f1e;
            --text-2:       #374151;
            --text-3:       #6b7280;
            --text-4:       #9ca3af;
            --text-inverse: #ffffff;
            --text-primary: #1d4ed8;
            --text-success: #15803d;
            --text-gold:    #92400e;
            --text-warning: #9a3412;
            --text-danger:  #b91c1c;

            /* BORDERS */
            --border:         #dde3ee;
            --border-strong:  #c4ccd8;
            --border-subtle:  #eaeff6;

            /* SPACING */
            --space-xs:  4px;
            --space-sm:  8px;
            --space-md:  16px;
            --space-lg:  24px;
            --space-xl:  32px;
            --space-2xl: 48px;
            --space-3xl: 64px;

            /* RADIUS */
            --radius-sm:   6px;
            --radius-md:   9px;
            --radius-lg:   13px;
            --radius-xl:   18px;
            --radius-2xl:  24px;
            --radius-pill: 999px;

            /* SHADOWS */
            --shadow-xs:    0 1px 2px rgba(10,15,30,0.05);
            --shadow-sm:    0 2px 6px rgba(10,15,30,0.07), 0 1px 2px rgba(10,15,30,0.04);
            --shadow-card:  0 2px 8px rgba(10,15,30,0.07), 0 0 1px rgba(10,15,30,0.08);
            --shadow-md:    0 4px 16px rgba(10,15,30,0.09), 0 2px 4px rgba(10,15,30,0.05);
            --shadow-lg:    0 8px 28px rgba(10,15,30,0.11), 0 3px 8px rgba(10,15,30,0.06);
            --shadow-xl:    0 16px 48px rgba(10,15,30,0.13), 0 6px 16px rgba(10,15,30,0.07);
            --shadow-inset: inset 0 2px 4px rgba(10,15,30,0.05);
            --shadow-focus: 0 0 0 3px rgba(37,99,235,0.20);
            --shadow-blue:  0 4px 16px rgba(37,99,235,0.18);
            --shadow-green: 0 4px 16px rgba(22,163,74,0.16);

            /* TRANSITIONS */
            --transition-fast:   all 0.15s ease;
            --transition-normal: all 0.22s ease;
            --transition-slow:   all 0.35s ease;
        }
        
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: var(--bg-body);
            background-attachment: fixed;
            background-size: cover;
            color: var(--text);
            line-height: 1.6;
            font-size: 14px;
            -webkit-font-smoothing: antialiased;
        }
        
        /* Bold Typography Scale */
        h1 {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.3px;
            line-height: 1.25;
            color: var(--text);
        }

        h2 {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.2px;
            line-height: 1.3;
            color: var(--text);
        }

        h3 {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0px;
            color: var(--text);
        }

        h4 {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.1px;
            color: var(--text);
        }

        .overline {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.7px;
            text-transform: uppercase;
            color: var(--text-4);
        }

        .metric {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--text);
        }

        p, span, div {
            font-size: 14px;
            font-weight: 400;
            line-height: 1.6;
            color: var(--text-2);
        }

        .caption {
            font-size: 12px;
            font-weight: 400;
            color: var(--text-3);
        }

        .text-bold {
            font-weight: 700;
            color: var(--text);
        }

        .text-accent-bold {
            font-weight: 700;
            color: var(--color-primary);
        }

        .text-success-bold {
            font-weight: 700;
            color: var(--color-green);
        }

        .text-gold-bold {
            font-weight: 700;
            color: var(--color-gold);
        }

        .text-italic-note {
            font-style: italic;
            color: var(--text-3);
        }

        /* Large Numbers for Stats/KPIs */
        .large-number {
            font-weight: 800;
            font-size: 2.5rem;
            line-height: 1;
            color: var(--text);
        }

        /* Premium Panel System */
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            box-shadow: var(--shadow-sm);
        }

        .panel-muted {
            background: var(--surface-inset);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: var(--space-md);
        }

        .panel-gradient {
            background: var(--bg-section);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-xl);
            padding: var(--space-xl);
        }

        .panel-accent {
            background: var(--surface-highlight);
            border: 1px solid var(--color-primary-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
        }

        .callout {
            background: var(--surface-blue);
            border-left: 4px solid var(--color-primary);
            border-radius: 0 var(--radius-md) var(--radius-md) 0;
            padding: var(--space-md) var(--space-lg);
            color: var(--text-primary);
            font-weight: 500;
        }

        .hero-banner {
            background: var(--bg-hero);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-2xl);
            padding: var(--space-2xl) var(--space-xl);
            box-shadow: var(--shadow-md);
        }

        /* Glass Topbar */
        .topbar {
            height: 60px;
            background: var(--surface-glass);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--border-subtle);
            padding: 0 var(--space-lg);
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: var(--shadow-xs);
            position: sticky; top: 0; z-index: 100;
        }

        .topbar-btn {
            width: 36px; height: 36px; border-radius: var(--radius-sm);
            background: var(--surface); border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 16px; color: var(--text-2);
            transition: var(--transition-fast); position: relative;
        }
        .topbar-btn:hover { 
            border-color: var(--border-strong); 
            color: var(--text); 
            box-shadow: var(--shadow-sm);
        }
        .notif-dot {
            position: absolute; top: 6px; right: 6px;
            width: 7px; height: 7px; border-radius: 50%;
            background: var(--color-danger); border: 1px solid var(--surface);
        }

        /* Clean White Sidebar */
        .sidebar {
            width: 220px;
            background: var(--surface);
            border-right: 1px solid var(--border);
            padding: var(--space-lg) var(--space-md);
            box-shadow: 2px 0 10px rgba(10,15,30,0.04);
            display: flex;
            flex-direction: column;
            gap: var(--space-sm);
            position: fixed; top: 0; left: 0; bottom: 0;
            z-index: 200; transition: transform .25s;
        }

        .sidebar-logo {
            padding: 0 var(--space-md) var(--space-lg);
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }

        .logo-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--color-primary);
            letter-spacing: 0.15px;
        }

        /* Navigation with Bold Hierarchy */
        .sidebar-nav a {
            display: block;
            color: var(--text-2);
            text-decoration: none;
            padding: 8px 10px;
            margin: 3px 0;
            border-radius: var(--radius-md);
            font-weight: 500;
            transition: var(--transition-fast);
        }

        .sidebar-nav a:hover {
            background: var(--surface-inset);
            color: var(--text);
            font-weight: 600;
        }

        .sidebar-nav a.active {
            background: var(--color-primary-soft);
            color: var(--color-primary);
            font-weight: 700;
            border-left: 3px solid var(--color-primary);
            padding-left: 7px;
        }

        .nav-section-header {
            padding: 10px 8px 4px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.7px;
            color: var(--text-4);
            text-transform: uppercase;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: var(--space-xl);
            background: transparent;
            max-width: 1380px;
            margin: 0 auto;
        }

        /* Page Header System */
        .page-overline {
            font-size: 10px; font-weight: 700; letter-spacing: 0.7px;
            text-transform: uppercase; color: var(--text-4);
            margin-bottom: 6px;
        }

        .page-title {
            font-size: 26px; font-weight: 800; color: var(--text);
            letter-spacing: -0.3px;
        }

        .page-subtitle {
            font-size: 13px; color: var(--text-3);
            margin-top: 4px;
        }

        /* Enhanced Stat Cards with Gradient Variants */
        .stat-card {
            border-radius: var(--radius-lg);
            padding: var(--space-lg) 18px;
            box-shadow: var(--shadow-card);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: var(--space-sm);
            transition: var(--transition-normal);
        }

        .stat-card:hover { 
            border-color: var(--border-strong); 
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card.primary {
            background: linear-gradient(135deg, #ffffff 55%, #eff6ff 100%);
            border-left: 4px solid var(--color-primary);
        }

        .stat-card.success {
            background: linear-gradient(135deg, #ffffff 55%, #f0fdf4 100%);
            border-left: 4px solid var(--color-green);
        }

        .stat-card.gold {
            background: linear-gradient(135deg, #ffffff 55%, #fffbeb 100%);
            border-left: 4px solid var(--color-gold);
        }

        .stat-card.warning {
            background: linear-gradient(135deg, #ffffff 55%, #fff7ed 100%);
            border-left: 4px solid var(--color-orange);
        }

        .stat-card.danger {
            background: linear-gradient(135deg, #ffffff 55%, #fff1f2 100%);
            border-left: 4px solid var(--color-danger);
        }

        .stat-number {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--text);
            line-height: 1;
        }

        .stat-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: var(--text-3);
        }

        /* Premium Cards */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            box-shadow: var(--shadow-card);
            transition: var(--transition-normal);
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: var(--border-strong);
        }

        .card-header {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: var(--space-md);
            padding-bottom: var(--space-sm);
            border-bottom: 1px solid var(--border);
            color: var(--text);
            letter-spacing: 0.15px;
        }

        /* Premium Buttons */
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border: none;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.1px;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition-fast);
        }

        .btn:focus {
            outline: none;
            box-shadow: var(--shadow-focus);
        }

        .btn:hover {
            text-decoration: none;
            transform: translateY(-1px);
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: var(--text-inverse);
            box-shadow: 0 2px 8px rgba(37,99,235,0.28);
        }

        .btn-primary:hover {
            filter: brightness(1.08);
        }

        .btn-success {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: var(--text-inverse);
            box-shadow: 0 2px 8px rgba(22,163,74,0.25);
        }

        .btn-success:hover {
            filter: brightness(1.07);
        }

        .btn-warning {
            background: linear-gradient(135deg, #ea580c, #c2410c);
            color: var(--text-inverse);
        }

        .btn-warning:hover {
            filter: brightness(1.07);
        }

        .btn-gold {
            background: linear-gradient(135deg, #b45309, #92400e);
            color: var(--text-inverse);
        }

        .btn-gold:hover {
            filter: brightness(1.07);
        }

        .btn-secondary {
            background: var(--surface);
            color: var(--text);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-xs);
        }

        .btn-secondary:hover {
            background: var(--surface-inset);
            border-color: var(--border-strong);
        }

        .btn-danger {
            background: var(--color-danger-soft);
            color: var(--color-danger);
            border: 1px solid var(--color-danger-border);
        }

        .btn-danger:hover {
            background: var(--color-danger);
            color: var(--text-inverse);
        }

        .btn-sm {
            padding: 5px 11px;
            font-size: 12px;
            border-radius: var(--radius-sm);
        }

        /* Premium Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: var(--radius-pill);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.1px;
            line-height: 1;
            border: 1px solid transparent;
        }

        .badge-primary {
            background: var(--color-primary-soft);
            color: var(--color-primary);
            border: 1px solid var(--color-primary-border);
        }

        .badge-success {
            background: var(--color-green-soft);
            color: var(--color-green);
            border: 1px solid var(--color-green-border);
        }

        .badge-gold {
            background: var(--color-gold-soft);
            color: var(--color-gold);
            border: 1px solid var(--color-gold-border);
        }

        .badge-warning {
            background: var(--color-orange-soft);
            color: var(--color-orange);
            border: 1px solid var(--color-orange-border);
        }

        .badge-danger {
            background: var(--color-danger-soft);
            color: var(--color-danger);
            border: 1px solid var(--color-danger-border);
        }

        .badge-neutral {
            background: var(--surface-inset);
            color: var(--text-3);
            border: 1px solid var(--border-subtle);
        }

        /* Premium Tables */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: var(--space-md);
            text-align: left;
            border-bottom: 1px solid var(--border-subtle);
        }

        .table th {
            background: var(--surface-inset);
            font-weight: 700;
            color: var(--text-3);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .table tr:hover {
            background: var(--bg-subtle);
        }

        .table td {
            font-size: 13px;
            color: var(--text-2);
        }

        .table td.emphasis {
            font-weight: 600;
            color: var(--text);
        }

        /* Grid System */
        .grid {
            display: grid;
            gap: var(--space-lg);
        }

        .grid-cols-1 { grid-template-columns: repeat(1, 1fr); }
        .grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
        .grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
        .grid-cols-4 { grid-template-columns: repeat(4, 1fr); }

        /* Form Controls */
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            font-size: 13px;
            background: var(--surface);
            color: var(--text);
            box-shadow: var(--shadow-inset);
            transition: var(--transition-fast);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: var(--shadow-focus);
            background: var(--surface);
        }

        .form-control::placeholder {
            color: var(--text-4);
        }

        .form-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.2px;
            color: var(--text-2);
            margin-bottom: 5px;
        }

        .form-group {
            margin-bottom: var(--space-md);
        }

        /* Utility Classes */
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .mb-1 { margin-bottom: var(--space-xs); }
        .mb-2 { margin-bottom: var(--space-sm); }
        .mb-3 { margin-bottom: var(--space-md); }
        .mb-4 { margin-bottom: var(--space-lg); }
        .mt-1 { margin-top: var(--space-xs); }
        .mt-2 { margin-top: var(--space-sm); }
        .mt-3 { margin-top: var(--space-md); }
        .mt-4 { margin-top: var(--space-lg); }
        .p-1 { padding: var(--space-xs); }
        .p-2 { padding: var(--space-sm); }
        .p-3 { padding: var(--space-md); }
        .p-4 { padding: var(--space-lg); }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--border-strong); }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">🧪 UNILIS SmartLab</div>
                <nav>
                    <ul class="nav">
                        <li><a href="<?= APP_URL ?>/dashboard" class="<?= $title === 'Dashboard' ? 'active' : '' ?>">Dashboard</a></li>
                        <li><a href="<?= APP_URL ?>/practicals" class="<?= $title === 'Practicals' ? 'active' : '' ?>">Practicals</a></li>
                        <li><a href="<?= APP_URL ?>/practical-requests" class="<?= $title === 'Practical Requests' ? 'active' : '' ?>">Request Redo</a></li>
                        <li><a href="<?= APP_URL ?>/report-submission" class="<?= $title === 'Report Submission' ? 'active' : '' ?>">Submit Report</a></li>
                        <li><a href="<?= APP_URL ?>/notebooks" class="<?= $title === 'Notebooks' ? 'active' : '' ?>">Notebooks</a></li>
                        <li><a href="<?= APP_URL ?>/assets" class="<?= $title === 'Assets' ? 'active' : '' ?>">Assets</a></li>
                        <li><a href="<?= APP_URL ?>/reports" class="<?= $title === 'Reports' ? 'active' : '' ?>">Reports</a></li>
                        <li><a href="<?= APP_URL ?>/auth/logout">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>
    
    <div class="layout">
        <aside class="sidebar">
            <ul class="sidebar-nav">
                <li><a href="<?= APP_URL ?>/dashboard">📊 Dashboard</a></li>
                <li><a href="<?= APP_URL ?>/practicals">🔬 Practicals</a></li>
                <li><a href="<?= APP_URL ?>/practical-requests">📋 Request Redo</a></li>
                <li><a href="<?= APP_URL ?>/report-submission">� Submit Report</a></li>
                <li><a href="<?= APP_URL ?>/notebooks">📓 Notebooks</a></li>
                <li><a href="<?= APP_URL ?>/reports">📝 Reports</a></li>
                <li><a href="<?= APP_URL ?>/blockchain">⛓️ Blockchain</a></li>
                <li><a href="<?= APP_URL ?>/audit">🔍 Audit Log</a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <?php if (isset($error) && $error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if (isset($success) && $success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?= $content ?? '' ?>
        </main>
    </div>
</body>
</html>
