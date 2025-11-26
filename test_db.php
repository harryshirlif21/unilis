<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>LearnHub Pro - Student Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; transition: background-color 0.3s; }
    :root { --primary: #6366f1; --success: #10b981; --warning: #f59e0b; --danger: #ef4444; }
    .dark { --primary: #818cf8; }
    .progress-ring { transform: rotate(-90deg); }
    .progress-ring circle { transition: all 1s cubic-bezier(0.4, 0, 0.2, 1); }
    .streak-fire { animation: pulse 2s infinite; }
    @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.7; } }
    .confetti { position: fixed; width: 10px; height: 10px; background: #f00; animation: fall 3s linear forwards; z-index: 9999; pointer-events: none; }
    @keyframes fall { to { transform: translateY(100vh) rotate(720deg); opacity: 0; } }
    .sidebar-collapsed { width: 80px !important; }
    .sidebar-collapsed .nav-text { opacity: 0; transform: translateX(-20px); }
    .sidebar-collapsed .logo-text { opacity: 0; width: 0; }
    .sidebar-collapsed .nav-item:hover .nav-text { opacity: 1; transform: translateX(0); }
  </style>
</head>
<body class="bg-gray-50 dark:bg-slate-950 text-gray-800 dark:text-gray-100 min-h-screen transition-colors">

  <!-- Mobile Hamburger (visible only on small screens) -->
  <button id="mobileMenuBtn" class="fixed top-20 left-4 z-50 lg:hidden bg-white dark:bg-slate-800 p-3 rounded-xl shadow-lg">
    <i data-lucide="menu" class="w-6 h-6"></i>
  </button>

  <!-- Collapsible Sidebar -->
  <aside id="sidebar" class="fixed left-0 top-0 h-full bg-white dark:bg-slate-900 shadow-2xl transition-all duration-300 ease-in-out z-40 flex flex-col w-20 lg:w-64">
    <!-- Logo + Collapse Button -->
    <div class="flex items-center justify-between p-5 border-b dark:border-slate-800">
      <div class="flex items-center space-x-3 overflow-hidden transition-all">
        <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center text-white font-bold text-xl">LH</div>
        <span class="logo-text font-bold text-xl bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent whitespace-nowrap">LearnHub Pro</span>
      </div>
      <button onclick="toggleSidebar()" class="hidden lg:block p-2 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-lg transition">
        <i data-lucide="chevrons-left" id="collapseIcon" class="w-5 h-5"></i>
      </button>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 p-4 space-y-2 overflow-hidden">
      <a href="#" class="nav-item active flex items-center space-x-4 px-4 py-4 rounded-xl transition group">
        <i data-lucide="layout-dashboard" class="w-6 h-6"></i>
        <span class="nav-text">Dashboard</span>
      </a>
      <a href="#" class="nav-item flex items-center space-x-4 px-4 py-4 rounded-xl hover:bg-indigo-50 dark:hover:bg-slate-800 transition group">
        <i data-lucide="book-open" class="w-6 h-6"></i>
        <span class="nav-text">My Courses</span>
      </a>
      <a href="#" class="nav-item flex items-center space-x-4 px-4 py-4 rounded-xl hover:bg-indigo-50 dark:hover:bg-slate-800 transition group">
        <i data-lucide="calendar-check" class="w-6 h-6"></i>
        <span class="nav-text">Calendar & Tasks</span>
      </a>
      <a href="#" class="nav-item flex items-center space-x-4 px-4 py-4 rounded-xl hover:bg-indigo-50 dark:hover:bg-slate-800 transition group">
        <i data-lucide="trending-up" class="w-6 h-6"></i>
        <span class="nav-text">Grades & Analytics</span>
      </a>
      <a href="#" class="nav-item flex items-center space-x-4 px-4 py-4 rounded-xl hover:bg-indigo-50 dark:hover:bg-slate-800 transition group relative">
        <i data-lucide="message-square" class="w-6 h-6"></i>
        <span class="nav-text">Messages</span>
        <span class="absolute top-3 right-3 bg-red-500 text-white text-xs px-2 py-1 rounded-full">9+</span>
      </a>
      <a href="#" class="nav-item flex items-center space-x-4 px-4 py-4 rounded-xl hover:bg-indigo-50 dark:hover:bg-slate-800 transition group">
        <i data-lucide="trophy" class="w-6 h-6"></i>
        <span class="nav-text">Achievements</span>
      </a>
      <a href="#" class="nav-item flex items-center space-x-4 px-4 py-4 rounded-xl hover:bg-indigo-50 dark:hover:bg-slate-800 transition group">
        <i data-lucide="bot" class="w-6 h-6"></i>
        <span class="nav-text">AI Assistant</span>
      </a>
    </nav>
  </aside>

  <!-- Main Content Area (auto-adjusts based on sidebar) -->
  <main id="mainContent" class="transition-all duration-300 lg:ml-64 ml-20 min-h-screen">
    <div class="max-w-7xl mx-auto p-6 pt-8">
      <!-- Header -->
      <header class="bg-white dark:bg-slate-900/80 backdrop-blur-lg shadow-lg rounded-2xl p-6 mb-8 border border-gray-200 dark:border-slate-800">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
          <div>
            <h1 class="text-4xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">Welcome back, Sarah!</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">You're on a <span class="text-orange-500 font-bold">14-day streak</span> — let's make it 15!</p>
          </div>
          <div class="flex items-center gap-6">
            <div class="text-right">
              <div class="text-6xl font-bold text-orange-500 streak-fire">14</div>
              <p class="text-sm text-gray-500">Day Streak • Best: 42</p>
            </div>
            <button onclick="toggleTheme()" class="p-4 bg-gray-100 dark:bg-slate-800 rounded-2xl hover:scale-110 transition">
              <i data-lucide="moon" id="themeIcon" class="w-6 h-6"></i>
            </button>
          </div>
        </div>
      </header>

      <!-- Rest of your amazing dashboard (same as before, just cleaner) -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
        <!-- Stats Cards (same beautiful ones) -->
        <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-lg hover:shadow-xl transition">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-4xl font-bold">6</p>
              <p class="text-gray-500">Active Courses</p>
            </div>
            <i data-lucide="book-open" class="w-12 h-12 text-indigo-500 opacity-30"></i>
          </div>
        </div>
        <!-- Add other 3 cards here (same as previous version) -->
      </div>

      <!-- Active Courses + Tasks Grid -->
      <div class="grid lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-8">
          <!-- Course Cards -->
          <div class="course-card" onclick="confettiBurst()">
            <div class="h-40 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-2xl"></div>
            <div class="relative -mt-24 text-center px-6">
              <div class="inline-block">
                <svg class="w-36 h-36 progress-ring"><circle cx="72" cy="72" r="64" stroke="#e5e7eb" stroke-width="14" fill="none"/><circle cx="72" cy="72" r="64" stroke="#10b981" stroke-width="14" fill="none" stroke-dasharray="402" stroke-dashoffset="80" stroke-linecap="round"/></svg>
                <span class="absolute inset-0 flex items-center justify-center text-3xl font-bold">80%</span>
              </div>
              <h3 class="mt-6 text-2xl font-bold">Advanced Calculus</h3>
              <p class="text-gray-500">Dr. Emma Wilson</p>
              <button class="mt-6 w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-4 rounded-xl font-semibold hover:scale-105 transition">Continue Learning</button>
            </div>
          </div>
        </div>

        <div>
          <!-- Today's Tasks -->
          <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg p-6">
            <h3 class="text-xl font-bold mb-4">Today's Tasks</h3>
            <div class="space-y-3">
              <label class="flex items-center gap-3 cursor-pointer"><input type="checkbox" class="w-5 h-5 text-indigo-600 rounded"> <span>Complete Calculus Quiz</span></label>
              <label class="flex items-center gap-3 cursor-pointer text-red-600"><input type="checkbox"> <span>Submit History Essay (Overdue)</span></label>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- Floating AI Button -->
  <button onclick="toggleAIPanel()" class="fixed bottom-8 right-8 bg-gradient-to-r from-purple-600 to-indigo-600 text-white w-16 h-16 rounded-full shadow-2xl flex items-center justify-center text-3xl hover:scale-110 transition z-50">
    <i data-lucide="bot"></i>
  </button>

  <script>
    lucide.createIcons();

    // Collapsible Sidebar
    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      const main = document.getElementById('mainContent');
      const icon = document.getElementById('collapseIcon');
      
      sidebar.classList.toggle('sidebar-collapsed');
      main.classList.toggle('lg:ml-20');
      main.classList.toggle('lg:ml-64');
      
      icon.setAttribute('data-lucide', sidebar.classList.contains('sidebar-collapsed') ? 'chevrons-right' : 'chevrons-left');
      lucide.createIcons();
    }

    // Mobile Menu Toggle
    document.getElementById('mobileMenuBtn').addEventListener('click', () => {
      document.getElementById('sidebar').classList.toggle('translate-x-0');
      document.getElementById('sidebar').classList.toggle('-translate-x-full');
    });

    // Theme Toggle
    function toggleTheme() {
      document.documentElement.classList.toggle('dark');
      localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
      document.getElementById('themeIcon').setAttribute('data-lucide', document.documentElement.classList.contains('dark') ? 'sun' : 'moon');
      lucide.createIcons();
    }
    if (localStorage.getItem('theme') === 'dark') toggleTheme();

    // Confetti
    function confettiBurst() {
      for(let i = 0; i < 60; i++) {
        const c = document.createElement('div');
        c.className = 'confetti';
        c.style.left = Math.random() * 100 + 'vw';
        c.style.background = ['#f59e0b','#10b981','#6366f1','#ef4444','#8b5cf6'][Math.floor(Math.random()*5)];
        c.style.animationDelay = Math.random() * 2 + 's';
        document.body.appendChild(c);
        setTimeout(() => c.remove(), 4000);
      }
    }

    // AI Panel (same as before)
    function toggleAIPanel() {
      document.getElementById('aiPanel')?.classList.toggle('translate-y-full');
    }
  </script>

  <style>
    .nav-item.active { @apply bg-gradient-to-r from-indigo-600 to-purple-600 text-white; }
    .nav-item:hover { @apply bg-indigo-50 dark:bg-slate-800; }
    .nav-text { @apply transition-all duration-300; }
    .course-card { @apply bg-white dark:bg-slate-800 rounded-3xl shadow-xl overflow-hidden hover:-translate-y-3 transition-all duration-300; }
  </style>
</body>
</html>