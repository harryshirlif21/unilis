<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎨 Profile System Demo - UNILIS Academic Platform</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    
    <style>
        :root {
            /* Academic Color Palette */
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
        .feature-icon.orange { background: linear-gradient(135deg, var(--accent-500), var(--accent-600)); color: white; }
        
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
        
        .profile-demo {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border: 1px solid var(--neutral-200);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            margin: 2rem 0;
            box-shadow: var(--shadow-xl);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: var(--radius-full);
            background: linear-gradient(135deg, var(--primary-500), var(--secondary-500));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: 700;
            box-shadow: var(--shadow-xl);
        }
        
        .profile-info h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--neutral-900);
            margin-bottom: 0.5rem;
        }
        
        .profile-info p {
            color: var(--primary-600);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .profile-stats {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
        }
        
        .profile-stat {
            text-align: center;
        }
        
        .profile-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--neutral-900);
        }
        
        .profile-stat-label {
            font-size: 0.75rem;
            color: var(--neutral-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .profile-section {
            background: var(--neutral-50);
            border: 1px solid var(--neutral-200);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
        }
        
        .profile-section h4 {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--neutral-900);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .profile-field {
            margin-bottom: 1rem;
        }
        
        .profile-field label {
            display: block;
            font-weight: 600;
            color: var(--neutral-700);
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }
        
        .profile-field input {
            width: 100%;
            padding: 0.5rem 1rem;
            border: 2px solid var(--neutral-200);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            background: white;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .profile-field input:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
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
            background: var(--neutral-100);
            border: 1px solid var(--neutral-200);
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
        }
        
        .role-tab.active {
            background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
            color: white;
            border-color: var(--primary-500);
        }
        
        .role-tab:hover {
            transform: translateY(-2px);
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
            
            .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 1.5rem;
            }
            
            .profile-stats {
                justify-content: center;
                gap: 1rem;
            }
            
            .profile-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
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
            animation: fadeIn 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header fade-in">
            <h1 class="title">🎨 Academic Profile System</h1>
            <p class="subtitle">Beautiful profile management system designed for UNILIS with Docker compatibility</p>
        </header>
        
        <div class="showcase-grid">
            <div class="showcase-card fade-in">
                <div class="feature-icon blue">
                    <span class="material-symbols-outlined">person</span>
                </div>
                <h3 class="feature-title">🎯 Role-Based Profiles</h3>
                <p class="feature-description">
                    Dynamic profile system that adapts to user roles with specialized fields and functionality.
                </p>
                <ul class="feature-list">
                    <li><span class="material-symbols-outlined">check_circle</span> Student profiles with academic info</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Lecturer profiles with professional details</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Admin profiles with access control</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Role-specific field validation</li>
                </ul>
            </div>
            
            <div class="showcase-card fade-in">
                <div class="feature-icon green">
                    <span class="material-symbols-outlined">database</span>
                </div>
                <h3 class="feature-title">🗄️ Docker Compatible</h3>
                <p class="feature-description">
                    Works seamlessly with your existing Docker setup and database structure.
                </p>
                <ul class="feature-list">
                    <li><span class="material-symbols-outlined">check_circle</span> No database migrations required</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Uses existing table structure</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Environment variable support</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Production-ready code</li>
                </ul>
            </div>
            
            <div class="showcase-card fade-in">
                <div class="feature-icon purple">
                    <span class="material-symbols-outlined">palette</span>
                </div>
                <h3 class="feature-title">🎨 Modern UI Design</h3>
                <p class="feature-description">
                    Beautiful interface with glass morphism effects and smooth animations.
                </p>
                <ul class="feature-list">
                    <li><span class="material-symbols-outlined">check_circle</span> 21st.magic design principles</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Glass morphism effects</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Smooth micro-interactions</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Responsive design</li>
                </ul>
            </div>
            
            <div class="showcase-card fade-in">
                <div class="feature-icon orange">
                    <span class="material-symbols-outlined">security</span>
                </div>
                <h3 class="feature-title">🔒 Secure & Safe</h3>
                <p class="feature-description">
                    Enterprise-grade security with proper validation and access control.
                </p>
                <ul class="feature-list">
                    <li><span class="material-symbols-outlined">check_circle</span> Input sanitization</li>
                    <li><span class="material-symbols-outlined">check_circle</span> SQL injection prevention</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Secure file uploads</li>
                    <li><span class="material-symbols-outlined">check_circle</span> Role-based access control</li>
                </ul>
            </div>
        </div>
        
        <!-- Profile Demo -->
        <div class="profile-demo fade-in">
            <h2 style="text-align: center; font-size: 2rem; font-weight: 700; margin-bottom: 2rem; color: var(--neutral-900);">
                🎭 Interactive Profile Demo
            </h2>
            
            <div class="role-tabs">
                <div class="role-tab active" onclick="switchRole('student')">Student</div>
                <div class="role-tab" onclick="switchRole('lecturer')">Lecturer</div>
                <div class="role-tab" onclick="switchRole('admin')">Admin</div>
            </div>
            
            <!-- Student Profile -->
            <div id="student-profile" class="role-content active">
                <div class="profile-header">
                    <div class="profile-avatar">JS</div>
                    <div class="profile-info">
                        <h3>John Smith</h3>
                        <p>🎓 Student</p>
                        <div class="profile-stats">
                            <div class="profile-stat">
                                <div class="profile-stat-value">3</div>
                                <div class="profile-stat-label">Year</div>
                            </div>
                            <div class="profile-stat">
                                <div class="profile-stat-value">3.8</div>
                                <div class="profile-stat-label">GPA</div>
                            </div>
                            <div class="profile-stat">
                                <div class="profile-stat-value">24</div>
                                <div class="profile-stat-label">Units</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="profile-grid">
                    <div class="profile-section">
                        <h4>
                            <span class="material-symbols-outlined" style="color: var(--primary-600);">person</span>
                            Basic Information
                        </h4>
                        <div class="profile-field">
                            <label>Full Name</label>
                            <input type="text" value="John Smith" readonly>
                        </div>
                        <div class="profile-field">
                            <label>Email</label>
                            <input type="email" value="john.smith@unilis.edu" readonly>
                        </div>
                        <div class="profile-field">
                            <label>Phone</label>
                            <input type="tel" value="+254 712 345 678" readonly>
                        </div>
                    </div>
                    
                    <div class="profile-section">
                        <h4>
                            <span class="material-symbols-outlined" style="color: var(--success-600);">school</span>
                            Academic Information
                        </h4>
                        <div class="profile-field">
                            <label>Registration Number</label>
                            <input type="text" value="SC/2023/001" readonly>
                        </div>
                        <div class="profile-field">
                            <label>Course</label>
                            <input type="text" value="Computer Science" readonly>
                        </div>
                        <div class="profile-field">
                            <label>Year of Study</label>
                            <input type="text" value="Year 3" readonly>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Lecturer Profile -->
            <div id="lecturer-profile" class="role-content">
                <div class="profile-header">
                    <div class="profile-avatar">DJ</div>
                    <div class="profile-info">
                        <h3>Dr. Jane Doe</h3>
                        <p>👨‍🏫 Lecturer</p>
                        <div class="profile-stats">
                            <div class="profile-stat">
                                <div class="profile-stat-value">8</div>
                                <div class="profile-stat-label">Experience</div>
                            </div>
                            <div class="profile-stat">
                                <div class="profile-stat-value">CS</div>
                                <div class="profile-stat-label">Department</div>
                            </div>
                            <div class="profile-stat">
                                <div class="profile-stat-value">PhD</div>
                                <div class="profile-stat-label">Qualification</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="profile-grid">
                    <div class="profile-section">
                        <h4>
                            <span class="material-symbols-outlined" style="color: var(--primary-600);">person</span>
                            Basic Information
                        </h4>
                        <div class="profile-field">
                            <label>Full Name</label>
                            <input type="text" value="Dr. Jane Doe" readonly>
                        </div>
                        <div class="profile-field">
                            <label>Email</label>
                            <input type="email" value="jane.doe@unilis.edu" readonly>
                        </div>
                        <div class="profile-field">
                            <label>Phone</label>
                            <input type="tel" value="+254 723 456 789" readonly>
                        </div>
                    </div>
                    
                    <div class="profile-section">
                        <h4>
                            <span class="material-symbols-outlined" style="color: var(--success-600);">psychology</span>
                            Professional Information
                        </h4>
                        <div class="profile-field">
                            <label>Staff ID</label>
                            <input type="text" value="STF000123" readonly>
                        </div>
                        <div class="profile-field">
                            <label>Department</label>
                            <input type="text" value="Computer Science" readonly>
                        </div>
                        <div class="profile-field">
                            <label>Specialization</label>
                            <input type="text" value="Machine Learning" readonly>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Admin Profile -->
            <div id="admin-profile" class="role-content">
                <div class="profile-header">
                    <div class="profile-avatar">AM</div>
                    <div class="profile-info">
                        <h3>Admin Master</h3>
                        <p>⚙️ Administrator</p>
                        <div class="profile-stats">
                            <div class="profile-stat">
                                <div class="profile-stat-value">Super</div>
                                <div class="profile-stat-label">Access Level</div>
                            </div>
                            <div class="profile-stat">
                                <div class="profile-stat-value">IT</div>
                                <div class="profile-stat-label">Department</div>
                            </div>
                            <div class="profile-stat">
                                <div class="profile-stat-value">Full</div>
                                <div class="profile-stat-label">Scope</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="profile-grid">
                    <div class="profile-section">
                        <h4>
                            <span class="material-symbols-outlined" style="color: var(--primary-600);">person</span>
                            Basic Information
                        </h4>
                        <div class="profile-field">
                            <label>Full Name</label>
                            <input type="text" value="Admin Master" readonly>
                        </div>
                        <div class="profile-field">
                            <label>Email</label>
                            <input type="email" value="admin@unilis.edu" readonly>
                        </div>
                        <div class="profile-field">
                            <label>Phone</label>
                            <input type="tel" value="+254 734 567 890" readonly>
                        </div>
                    </div>
                    
                    <div class="profile-section">
                        <h4>
                            <span class="material-symbols-outlined" style="color: var(--success-600);">admin_panel_settings</span>
                            System Information
                        </h4>
                        <div class="profile-field">
                            <label>Admin ID</label>
                            <input type="text" value="ADM000001" readonly>
                        </div>
                        <div class="profile-field">
                            <label>Department</label>
                            <input type="text" value="IT Department" readonly>
                        </div>
                        <div class="profile-field">
                            <label>Access Level</label>
                            <input type="text" value="Super Admin" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Access Links -->
        <div style="text-align: center; margin-top: 3rem; padding: 2rem; background: linear-gradient(135deg, var(--primary-600), var(--secondary-600)); border-radius: var(--radius-2xl); color: white;" class="fade-in">
            <h2 style="font-size: 2rem; font-weight: 700; margin-bottom: 1rem;">🚀 Access Your Profiles</h2>
            <p style="font-size: 1.125rem; margin-bottom: 2rem; opacity: 0.9;">Click below to access your profile page (requires login)</p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="../student/profile.php" class="btn btn-white" style="background: white; color: var(--primary-600);">
                    <span class="material-symbols-outlined">school</span>
                    Student Profile
                </a>
                <a href="../lecturer/profile.php" class="btn btn-white" style="background: white; color: var(--primary-600);">
                    <span class="material-symbols-outlined">psychology</span>
                    Lecturer Profile
                </a>
                <a href="../admin/profile.php" class="btn btn-white" style="background: white; color: var(--primary-600);">
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
            document.getElementById(role + '-profile').classList.add('active');
        }
        
        // Add fade-in animation
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                }
            });
        });
        
        document.querySelectorAll('.showcase-card, .profile-demo').forEach(el => {
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
