<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎨 Modern Dashboard Showcase - UNILIS</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    
    <style>
        :root {
            /* Modern Color Palette */
            --primary-50: #f0f9ff;
            --primary-100: #e0f2fe;
            --primary-200: #bae6fd;
            --primary-300: #7dd3fc;
            --primary-400: #38bdf8;
            --primary-500: #0ea5e9;
            --primary-600: #0284c7;
            --primary-700: #0369a1;
            --primary-800: #075985;
            --primary-900: #0c4a6e;
            
            --secondary-50: #fdf4ff;
            --secondary-100: #fae8ff;
            --secondary-200: #f5d0fe;
            --secondary-300: #f0abfc;
            --secondary-400: #e879f9;
            --secondary-500: #d946ef;
            --secondary-600: #c026d3;
            --secondary-700: #a21caf;
            --secondary-800: #86198f;
            --secondary-900: #701a75;
            
            --accent-50: #fefce8;
            --accent-100: #fef9c3;
            --accent-200: #fef08a;
            --accent-300: #fde047;
            --accent-400: #facc15;
            --accent-500: #eab308;
            --accent-600: #ca8a04;
            --accent-700: #a16207;
            --accent-800: #854d0e;
            --accent-900: #713f12;
            
            --success-50: #f0fdf4;
            --success-100: #dcfce7;
            --success-200: #bbf7d0;
            --success-300: #86efac;
            --success-400: #4ade80;
            --success-500: #22c55e;
            --success-600: #16a34a;
            --success-700: #15803d;
            --success-800: #166534;
            --success-900: #14532d;
            
            --warning-50: #fffbeb;
            --warning-100: #fef3c7;
            --warning-200: #fde68a;
            --warning-300: #fcd34d;
            --warning-400: #fbbf24;
            --warning-500: #f59e0b;
            --warning-600: #d97706;
            --warning-700: #b45309;
            --warning-800: #92400e;
            --warning-900: #78350f;
            
            --error-50: #fef2f2;
            --error-100: #fee2e2;
            --error-200: #fecaca;
            --error-300: #fca5a5;
            --error-400: #f87171;
            --error-500: #ef4444;
            --error-600: #dc2626;
            --error-700: #b91c1c;
            --error-800: #991b1b;
            --error-900: #7f1d1d;
            
            --neutral-50: #fafafa;
            --neutral-100: #f5f5f5;
            --neutral-200: #e5e5e5;
            --neutral-300: #d4d4d4;
            --neutral-400: #a3a3a3;
            --neutral-500: #737373;
            --neutral-600: #525252;
            --neutral-700: #404040;
            --neutral-800: #262626;
            --neutral-900: #171717;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --shadow-2xl: 0 25px 50px -12px rgb(0 0 0 / 0.25);
            
            /* Border Radius */
            --radius-sm: 0.375rem;
            --radius: 0.5rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            --radius-2xl: 2rem;
            --radius-full: 9999px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--primary-50) 0%, var(--secondary-50) 100%);
            min-height: 100vh;
            color: var(--neutral-900);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .title {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }
        
        .subtitle {
            font-size: 1.25rem;
            color: var(--neutral-600);
            margin-bottom: 2rem;
        }
        
        .showcase-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .showcase-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border: 1px solid var(--neutral-200);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            box-shadow: var(--shadow-xl);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .showcase-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-500), var(--secondary-500));
            transform: scaleX(0);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .showcase-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-2xl);
        }
        
        .showcase-card:hover::before {
            transform: scaleX(1);
        }
        
        .feature-icon {
            width: 64px;
            height: 64px;
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1.5rem;
        }
        
        .feature-icon.blue { background: linear-gradient(135deg, var(--primary-500), var(--primary-600)); color: white; }
        .feature-icon.green { background: linear-gradient(135deg, var(--success-500), var(--success-600)); color: white; }
        .feature-icon.purple { background: linear-gradient(135deg, var(--secondary-500), var(--secondary-600)); color: white; }
        .feature-icon.orange { background: linear-gradient(135deg, var(--warning-500), var(--warning-600)); color: white; }
        
        .feature-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--neutral-900);
            margin-bottom: 1rem;
        }
        
        .feature-description {
            color: var(--neutral-600);
            margin-bottom: 1.5rem;
            line-height: 1.7;
        }
        
        .feature-list {
            list-style: none;
        }
        
        .feature-list li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            color: var(--neutral-700);
        }
        
        .feature-list li .material-symbols-outlined {
            color: var(--primary-600);
            font-size: 1.25rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            font-size: 0.875rem;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
            color: white;
            box-shadow: var(--shadow);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-secondary {
            background: var(--neutral-100);
            color: var(--neutral-700);
            border: 1px solid var(--neutral-200);
        }
        
        .btn-secondary:hover {
            background: var(--neutral-200);
            transform: translateY(-1px);
        }
        
        .comparison-section {
            margin-top: 3rem;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-2xl);
            border: 1px solid var(--neutral-200);
        }
        
        .comparison-title {
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 2rem;
            color: var(--neutral-900);
        }
        
        .comparison-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .comparison-card {
            padding: 1.5rem;
            border-radius: var(--radius-xl);
            border: 1px solid var(--neutral-200);
        }
        
        .comparison-card.old {
            background: var(--error-50);
            border-color: var(--error-200);
        }
        
        .comparison-card.new {
            background: var(--success-50);
            border-color: var(--success-200);
        }
        
        .comparison-card h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .comparison-card.old h3 {
            color: var(--error-700);
        }
        
        .comparison-card.new h3 {
            color: var(--success-700);
        }
        
        .comparison-list {
            list-style: none;
        }
        
        .comparison-list li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .comparison-card.old .comparison-list li {
            color: var(--error-600);
        }
        
        .comparison-card.new .comparison-list li {
            color: var(--success-600);
        }
        
        .cta-section {
            text-align: center;
            margin-top: 3rem;
            padding: 2rem;
            background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
            border-radius: var(--radius-2xl);
            color: white;
        }
        
        .cta-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .cta-description {
            font-size: 1.125rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-white {
            background: white;
            color: var(--primary-600);
        }
        
        .btn-white:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .title {
                font-size: 2rem;
            }
            
            .showcase-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .comparison-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
            }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid var(--neutral-200);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--neutral-600);
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header fade-in">
            <h1 class="title">🎨 Modern Dashboard Reimagined</h1>
            <p class="subtitle">Experience the stunning new UNILIS student dashboard with 21st.dev magic and Google UI principles</p>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">100%</div>
                    <div class="stat-label">Modern Design</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">60+</div>
                    <div class="stat-label">UI Components</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">∞</div>
                    <div class="stat-label">Animations</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">4.9</div>
                    <div class="stat-label">User Experience</div>
                </div>
            </div>
        </header>
        
        <div class="showcase-grid">
            <div class="showcase-card fade-in">
                <div class="feature-icon blue">
                    <span class="material-symbols-outlined">palette</span>
                </div>
                <h3 class="feature-title">🎨 Modern Design System</h3>
                <p class="feature-description">
                    Built with Google Material Design principles and 21st.dev aesthetics for a truly modern experience.
                </p>
                <ul class="feature-list">
                    <li><span class="material-symbols-outlined">check_circle</span> Inter font family</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Material Design icons</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Consistent color palette</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Professional typography</li>
                </ul>
            </div>
            
            <div class="showcase-card fade-in">
                <div class="feature-icon green">
                    <span class="material-symbols-outlined">devices</span>
                </div>
                <h3 class="feature-title">📱 Fully Responsive</h3>
                <p class="feature-description">
                    Perfect experience on every device - from mobile phones to desktop computers.
                </p>
                <ul class="feature-list">
                    <li><span class="material-symbols-outlined">check_circle</span> Mobile-first design</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Touch-friendly interface</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Adaptive layouts</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Optimized performance</li>
                </ul>
            </div>
            
            <div class="showcase-card fade-in">
                <div class="feature-icon purple">
                    <span class="material-symbols-outlined">auto_awesome</span>
                </div>
                <h3 class="feature-title">✨ Micro-interactions</h3>
                <p class="feature-description">
                    Smooth animations and delightful interactions that make every action feel premium.
                </p>
                <ul class="feature-list">
                    <li><span class="material-symbols-outlined">check_circle</span> Hover effects</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Smooth transitions</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Loading animations</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Interactive feedback</li>
                </ul>
            </div>
            
            <div class="showcase-card fade-in">
                <div class="feature-icon orange">
                    <span class="material-symbols-outlined">dashboard_customize</span>
                </div>
                <h3 class="feature-title">🚀 Enhanced Features</h3>
                <p class="feature-description">
                    Powerful new features and improved functionality for a better learning experience.
                </p>
                <ul class="feature-list">
                    <li><span class="material-symbols-outlined">check_circle</span> Real-time notifications</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Progress tracking</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Activity dashboard</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Quick actions</li>
                </ul>
            </div>
        </div>
        
        <div class="comparison-section fade-in">
            <h2 class="comparison-title">🔄 Before vs After</h2>
            <div class="comparison-grid">
                <div class="comparison-card old">
                    <h3>
                        <span class="material-symbols-outlined">thumb_down</span>
                        Old Dashboard
                    </h3>
                    <ul class="comparison-list">
                        <li><span class="material-symbols-outlined">close</span> Basic styling</li>
                        <li><span class="material-symbols-outlined">close</span> Limited responsiveness</li>
                        <li><span class="material-symbols-outlined">close</span> No animations</li>
                        <li><span class="material-symbols-outlined">close</span> Outdated design</li>
                        <li><span class="material-symbols-outlined">close</span> Poor UX</li>
                    </ul>
                </div>
                
                <div class="comparison-card new">
                    <h3>
                        <span class="material-symbols-outlined">thumb_up</span>
                        New Dashboard
                    </h3>
                    <ul class="comparison-list">
                        <li><span class="material-symbols-outlined">check_circle</span> Modern Material Design</li>
                        <li><span class="material-symbols-outlined">check_circle</span> Fully responsive</li>
                        <li><span class="material-symbols-outlined">check_circle</span> Smooth animations</li>
                        <li><span class="material-symbols-outlined">check_circle</span> Professional aesthetics</li>
                        <li><span class="material-symbols-outlined">check_circle</span> Excellent UX</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="cta-section fade-in">
            <h2 class="cta-title">🚀 Ready to Experience the New Dashboard?</h2>
            <p class="cta-description">Try out the completely redesigned student dashboard with modern UI and enhanced features.</p>
            <div class="cta-buttons">
                <a href="dashboard.php" class="btn btn-white">
                    <span class="material-symbols-outlined">play_arrow</span>
                    Try New Dashboard
                </a>
                <a href="dashboard_backup.php" class="btn btn-secondary">
                    <span class="material-symbols-outlined">history</span>
                    View Original
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Add fade-in animation on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                }
            });
        });
        
        document.querySelectorAll('.showcase-card, .comparison-section, .cta-section').forEach(el => {
            observer.observe(el);
        });
        
        // Add interactive hover effects
        document.querySelectorAll('.showcase-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-8px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0) scale(1)';
            });
        });
    </script>
</body>
</html>
