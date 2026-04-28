<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🌙 Enhanced Profile Dashboard - UNILIS Academic Platform</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    
    <style>
        :root {
            /* Dark Theme - 21st.magic Design System */
            --bg-primary: #0a0a0f;
            --bg-secondary: #1a1a2e;
            --bg-tertiary: #16213e;
            --bg-glass: rgba(255, 255, 255, 0.05);
            --bg-glass-hover: rgba(255, 255, 255, 0.08);
            
            --text-primary: #ffffff;
            --text-secondary: #b8b8d1;
            --text-muted: #6b7280;
            
            --accent-primary: #6366f1;
            --accent-secondary: #8b5cf6;
            --accent-success: #10b981;
            --accent-warning: #f59e0b;
            --accent-error: #ef4444;
            
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-hero: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1), 0 10px 10px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px rgba(0, 0, 0, 0.25);
            
            --blur-sm: blur(4px);
            --blur-md: blur(8px);
            --blur-lg: blur(16px);
            
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-2xl: 1.5rem;
            --radius-3xl: 2rem;
            --radius-full: 9999px;
            
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-normal: 250ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 350ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
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
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }
        
        .subtitle {
            font-size: 1.25rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }
        
        .showcase-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .showcase-card {
            background: var(--bg-glass);
            backdrop-filter: var(--blur-md);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            box-shadow: var(--shadow-xl);
            transition: all var(--transition-normal);
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
            background: var(--gradient-primary);
            transform: scaleX(0);
            transition: transform var(--transition-slow);
        }
        
        .showcase-card:hover {
            transform: translateY(-8px);
            background: var(--bg-glass-hover);
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
        
        .feature-icon.blue { background: var(--gradient-primary); color: white; }
        .feature-icon.green { background: linear-gradient(135deg, var(--accent-success), #059669); color: white; }
        .feature-icon.purple { background: var(--gradient-secondary); color: white; }
        .feature-icon.orange { background: linear-gradient(135deg, var(--accent-warning), #d97706); color: white; }
        
        .feature-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }
        
        .feature-description {
            color: var(--text-secondary);
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
            color: var(--text-secondary);
        }
        
        .feature-list li .material-symbols-outlined {
            color: var(--accent-primary);
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
            transition: all var(--transition-normal);
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
            transition: left var(--transition-slow);
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow-lg);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
        }
        
        .btn-secondary {
            background: var(--bg-glass);
            color: var(--text-primary);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .btn-secondary:hover {
            background: var(--bg-glass-hover);
            transform: translateY(-2px);
        }
        
        .demo-preview {
            background: var(--bg-glass);
            backdrop-filter: var(--blur-md);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            margin: 2rem 0;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
        }
        
        .demo-preview::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-hero);
        }
        
        .demo-hero {
            background: var(--gradient-hero);
            padding: 2rem;
            border-radius: var(--radius-xl);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .demo-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
        }
        
        .demo-hero-content {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        
        .demo-avatar {
            width: 80px;
            height: 80px;
            border-radius: var(--radius-full);
            background: var(--gradient-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 800;
            border: 3px solid rgba(255, 255, 255, 0.3);
            box-shadow: var(--shadow-xl);
        }
        
        .demo-info h3 {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .demo-info p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.875rem;
        }
        
        .demo-analytics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .demo-analytic-card {
            background: var(--bg-tertiary);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            padding: 1rem;
            text-align: center;
        }
        
        .demo-analytic-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .demo-analytic-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .role-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            justify-content: center;
        }
        
        .role-tab {
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-lg);
            background: var(--bg-glass);
            border: 1px solid rgba(255, 255, 255, 0.2);
            cursor: pointer;
            transition: all var(--transition-normal);
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .role-tab.active {
            background: var(--gradient-primary);
            color: white;
            border-color: var(--accent-primary);
        }
        
        .role-tab:hover {
            transform: translateY(-2px);
            background: var(--bg-glass-hover);
        }
        
        .role-content {
            display: none;
        }
        
        .role-content.active {
            display: block;
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
            
            .demo-hero-content {
                flex-direction: column;
                text-align: center;
                gap: 1.5rem;
            }
            
            .demo-analytics {
                grid-template-columns: 1fr;
            }
            
            .role-tabs {
                flex-direction: column;
            }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header fade-in">
            <h1 class="title">🌙 Enhanced Profile Dashboard</h1>
            <p class="subtitle">Modern dark-mode profile system with hero-based layout and glassmorphism design</p>
        </header>
        
        <div class="showcase-grid">
            <div class="showcase-card fade-in">
                <div class="feature-icon blue">
                    <span class="material-symbols-outlined">dark_mode</span>
                </div>
                <h3 class="feature-title">🌙 Dark Mode Design</h3>
                <p class="feature-description">
                    Beautiful dark theme with carefully crafted color palette and contrast ratios.
                </p>
                <ul class="feature-list">
                    <li><span class="material-symbols-outlined">check_circle</span> Professional dark color scheme</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Optimized contrast ratios</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Eye-friendly for long sessions</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Modern aesthetic appeal</li>
                </ul>
            </div>
            
            <div class="showcase-card fade-in">
                <div class="feature-icon green">
                    <span class="material-symbols-outlined">dashboard</span>
                </div>
                <h3 class="feature-title">🎯 Hero-Based Layout</h3>
                <p class="feature-description">
                    All primary user information consolidated in an impressive hero section.
                </p>
                <ul class="feature-list">
                    <li><span class="material-symbols-outlined">check_circle</span> Large profile avatar with glow</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Key metadata inline display</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Verification badges</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Profile completion progress</li>
                </ul>
            </div>
            
            <div class="showcase-card fade-in">
                <div class="feature-icon purple">
                    <span class="material-symbols-outlined">blur_on</span>
                </div>
                <h3 class="feature-title">✨ Glassmorphism Effects</h3>
                <p class="feature-description">
                    Modern glass morphism design with backdrop filters and transparency.
                </p>
                <ul class="feature-list">
                    <li><span class="material-symbols-outlined">check_circle</span> Backdrop blur effects</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Transparent card backgrounds</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Smooth hover animations</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Depth perception</li>
                </ul>
            </div>
            
            <div class="showcase-card fade-in">
                <div class="feature-icon orange">
                    <span class="material-symbols-outlined">auto_awesome</span>
                </div>
                <h3 class="feature-title">🚀 UI ProMax Features</h3>
                <p class="feature-description">
                    Enterprise-grade UI components with micro-interactions and animations.
                </p>
                <ul class="feature-list">
                    <li><span class="material-symbols-outlined">check_circle</span> Smooth transitions (250ms)</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Hover effects and transforms</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Loading animations</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Toast notifications</li>
                </ul>
            </div>
        </div>
        
        <!-- Demo Preview -->
        <div class="demo-preview fade-in">
            <h2 style="text-align: center; font-size: 2rem; font-weight: 700; margin-bottom: 2rem; color: var(--text-primary);">
                🎭 Interactive Demo Preview
            </h2>
            
            <div class="role-tabs">
                <div class="role-tab active" onclick="switchRole('student')">Student</div>
                <div class="role-tab" onclick="switchRole('lecturer')">Lecturer</div>
                <div class="role-tab" onclick="switchRole('admin')">Admin</div>
            </div>
            
            <!-- Student Demo -->
            <div id="student-demo" class="role-content active">
                <div class="demo-hero">
                    <div class="demo-hero-content">
                        <div class="demo-avatar">JS</div>
                        <div class="demo-info">
                            <h3>John Smith</h3>
                            <p>🎓 Student • Computer Science • Year 3</p>
                        </div>
                    </div>
                </div>
                
                <div class="demo-analytics">
                    <div class="demo-analytic-card">
                        <div class="demo-analytic-value">8</div>
                        <div class="demo-analytic-label">Courses Enrolled</div>
                    </div>
                    <div class="demo-analytic-card">
                        <div class="demo-analytic-value">24</div>
                        <div class="demo-analytic-label">Assignments</div>
                    </div>
                    <div class="demo-analytic-card">
                        <div class="demo-analytic-value">3.8</div>
                        <div class="demo-analytic-label">GPA</div>
                    </div>
                    <div class="demo-analytic-card">
                        <div class="demo-analytic-value">85%</div>
                        <div class="demo-analytic-label">Profile</div>
                    </div>
                </div>
            </div>
            
            <!-- Lecturer Demo -->
            <div id="lecturer-demo" class="role-content">
                <div class="demo-hero">
                    <div class="demo-hero-content">
                        <div class="demo-avatar">DJ</div>
                        <div class="demo-info">
                            <h3>Dr. Jane Doe</h3>
                            <p>👨‍🏫 Lecturer • Computer Science • 8 Years</p>
                        </div>
                    </div>
                </div>
                
                <div class="demo-analytics">
                    <div class="demo-analytic-card">
                        <div class="demo-analytic-value">6</div>
                        <div class="demo-analytic-label">Courses Teaching</div>
                    </div>
                    <div class="demo-analytic-card">
                        <div class="demo-analytic-value">42</div>
                        <div class="demo-analytic-label">Assignments</div>
                    </div>
                    <div class="demo-analytic-card">
                        <div class="demo-analytic-value">156</div>
                        <div class="demo-analytic-label">Students</div>
                    </div>
                    <div class="demo-analytic-card">
                        <div class="demo-analytic-value">92%</div>
                        <div class="demo-analytic-label">Profile</div>
                    </div>
                </div>
            </div>
            
            <!-- Admin Demo -->
            <div id="admin-demo" class="role-content">
                <div class="demo-hero">
                    <div class="demo-hero-content">
                        <div class="demo-avatar">AM</div>
                        <div class="demo-info">
                            <h3>Admin Master</h3>
                            <p>⚙️ Administrator • IT Department • Full Access</p>
                        </div>
                    </div>
                </div>
                
                <div class="demo-analytics">
                    <div class="demo-analytic-card">
                        <div class="demo-analytic-value">12</div>
                        <div class="demo-analytic-label">Total Courses</div>
                    </div>
                    <div class="demo-analytic-card">
                        <div class="demo-analytic-value">847</div>
                        <div class="demo-analytic-label">Total Students</div>
                    </div>
                    <div class="demo-analytic-card">
                        <div class="demo-analytic-value">45</div>
                        <div class="demo-analytic-label">Lecturers</div>
                    </div>
                    <div class="demo-analytic-card">
                        <div class="demo-analytic-value">100%</div>
                        <div class="demo-analytic-label">Profile</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Access Links -->
        <div style="text-align: center; margin-top: 3rem; padding: 2rem; background: var(--gradient-primary); border-radius: var(--radius-2xl);" class="fade-in">
            <h2 style="font-size: 2rem; font-weight: 700; margin-bottom: 1rem; color: white;">🚀 Access Enhanced Profiles</h2>
            <p style="font-size: 1.125rem; margin-bottom: 2rem; color: rgba(255, 255, 255, 0.9);">Experience the new dark-mode profile dashboard (requires login)</p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="../student/profile.php" class="btn btn-white" style="background: white; color: var(--accent-primary);">
                    <span class="material-symbols-outlined">school</span>
                    Student Profile
                </a>
                <a href="../lecturer/profile.php" class="btn btn-white" style="background: white; color: var(--accent-primary);">
                    <span class="material-symbols-outlined">psychology</span>
                    Lecturer Profile
                </a>
                <a href="../admin/profile.php" class="btn btn-white" style="background: white; color: var(--accent-primary);">
                    <span class="material-symbols-outlined">admin_panel_settings</span>
                    Admin Profile
                </a>
            </div>
        </div>
    </div>
    
    <script>
        function switchRole(role) {
            // Update tabs
            document.querySelectorAll('.role-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Update content
            document.querySelectorAll('.role-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(role + '-demo').classList.add('active');
        }
        
        // Add fade-in animation
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                }
            });
        });
        
        document.querySelectorAll('.showcase-card, .demo-preview').forEach(el => {
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
