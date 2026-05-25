/**
 * Modern lesson dashboard — interactions, AI assistant, voice, progress chart
 */
(function () {
    'use strict';

    const cfg = window.LESSON_DASHBOARD || {};
    const contentScroll = document.getElementById('content-scroll');
    const progressFill = document.getElementById('module-progress-fill');
    const readBar = document.getElementById('read-progress');
    const themeBtn = document.getElementById('theme-toggle');

    // ── Theme (dark / light) ─────────────────────────────────────
    const savedTheme = localStorage.getItem('lesson-theme');
    if (savedTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    themeBtn?.addEventListener('click', () => {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        document.documentElement.setAttribute('data-theme', isDark ? 'light' : 'dark');
        localStorage.setItem('lesson-theme', isDark ? 'light' : 'dark');
        updateThemeIcon();
        renderProgressChart();
    });

    function updateThemeIcon() {
        if (!themeBtn) return;
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        themeBtn.innerHTML = dark
            ? '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
    }
    updateThemeIcon();

    // ── Button ripple ────────────────────────────────────────────
    document.querySelectorAll('.btn').forEach((btn) => {
        btn.addEventListener('click', function (e) {
            const rect = this.getBoundingClientRect();
            const ripple = document.createElement('span');
            ripple.className = 'ripple';
            const size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = e.clientX - rect.left - size / 2 + 'px';
            ripple.style.top = e.clientY - rect.top - size / 2 + 'px';
            this.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        });
    });

    // ── Reading progress (navbar + read bar) ─────────────────────
    function updateReadProgress() {
        if (!contentScroll) return;
        const scrollTop = contentScroll.scrollTop;
        const scrollMax = contentScroll.scrollHeight - contentScroll.clientHeight;
        const pct = scrollMax > 0 ? Math.min(100, (scrollTop / scrollMax) * 100) : 100;
        if (readBar) readBar.style.width = pct + '%';
        const readPctEl = document.getElementById('read-pct');
        if (readPctEl) readPctEl.textContent = Math.round(pct) + '%';
    }
    contentScroll?.addEventListener('scroll', updateReadProgress, { passive: true });
    updateReadProgress();

    // ── Module progress bar animation ──────────────────────────────
    if (progressFill && cfg.moduleProgress != null) {
        requestAnimationFrame(() => {
            progressFill.style.width = cfg.moduleProgress + '%';
        });
    }

    // ── Progress chart (sidebar) ─────────────────────────────────
    function renderProgressChart() {
        const canvas = document.getElementById('progress-chart');
        if (!canvas || !cfg.lessonChart?.length) return;

        const ctx = canvas.getContext('2d');
        const dpr = window.devicePixelRatio || 1;
        const w = canvas.clientWidth;
        const h = canvas.clientHeight || 80;
        canvas.width = w * dpr;
        canvas.height = h * dpr;
        ctx.scale(dpr, dpr);

        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        const items = cfg.lessonChart;
        const barW = Math.max(8, (w - 20) / items.length - 6);
        const maxH = h - 24;

        ctx.clearRect(0, 0, w, h);
        items.forEach((item, i) => {
            const x = 10 + i * (barW + 6);
            const barH = item.done ? maxH : maxH * 0.35;
            const grad = ctx.createLinearGradient(0, h - barH, 0, h);
            if (item.done) {
                grad.addColorStop(0, '#6366f1');
                grad.addColorStop(1, '#8b5cf6');
            } else if (item.current) {
                grad.addColorStop(0, '#f59e0b');
                grad.addColorStop(1, '#fbbf24');
            } else {
                grad.addColorStop(0, dark ? '#334155' : '#e2e8f0');
                grad.addColorStop(1, dark ? '#1e293b' : '#cbd5e1');
            }
            ctx.fillStyle = grad;
            ctx.beginPath();
            ctx.roundRect(x, h - 8 - barH, barW, barH, 4);
            ctx.fill();
        });
    }
    renderProgressChart();
    window.addEventListener('resize', renderProgressChart);

    // ── Mobile sidebar ─────────────────────────────────────────────
    const lessonSidebar = document.getElementById('lesson-sidebar');
    const lessonNavToggle = document.getElementById('lessonNavToggle');

    lessonNavToggle?.addEventListener('click', () => {
        lessonSidebar?.classList.toggle('open');
        lessonNavToggle.setAttribute('aria-expanded', lessonSidebar?.classList.contains('open') ? 'true' : 'false');
    });

    document.querySelectorAll('.lesson-link').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 768px)').matches) {
                lessonSidebar?.classList.remove('open');
            }
        });
    });

    // ── AI assistant ─────────────────────────────────────────────
    const aiToggle = document.getElementById('ai-toggle');
    const aiPanel = document.getElementById('ai-panel');
    const appShell = document.querySelector('.app-shell');
    aiToggle?.addEventListener('click', () => {
        if (window.matchMedia('(max-width: 1200px)').matches) {
            aiPanel?.classList.toggle('ai-mobile-open');
        } else {
            appShell?.classList.toggle('ai-collapsed');
        }
    });
    document.getElementById('ai-close')?.addEventListener('click', () => {
        appShell?.classList.add('ai-collapsed');
        aiPanel?.classList.remove('ai-mobile-open');
    });

    const aiForm = document.getElementById('ai-form');
    const aiInput = document.getElementById('ai-input');
    const aiMessages = document.getElementById('ai-messages');

    function appendAiMessage(text, role) {
        const div = document.createElement('div');
        div.className = 'ai-msg ' + role;
        div.textContent = text;
        aiMessages?.appendChild(div);
        aiMessages?.scrollTop = aiMessages.scrollHeight;
    }

    if (aiMessages && !aiMessages.children.length) {
        appendAiMessage(
            "Hi! I'm your study assistant. Ask me to explain any concept from this lesson in simple terms.",
            'bot'
        );
    }

    aiForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        const q = (aiInput?.value || '').trim();
        if (!q) return;
        appendAiMessage(q, 'user');
        aiInput.value = '';
        appendAiMessage(generateAiReply(q), 'bot');
    });

    function generateAiReply(question) {
        const title = cfg.lessonTitle || 'this lesson';
        const lower = question.toLowerCase();
        if (lower.includes('definition') || lower.includes('what is')) {
            return `In "${title}", focus on the core definition at the top of the lesson. Try relating it to a real-world example you already know — that helps memory stick.`;
        }
        if (lower.includes('component') || lower.includes('ledger') || lower.includes('consensus')) {
            return 'Key components usually work together: a shared ledger records data, the P2P network distributes it, and consensus keeps everyone aligned. Review each card in the Key Components section.';
        }
        if (lower.includes('characteristic') || lower.includes('feature')) {
            return 'Characteristics describe properties of the system (e.g. decentralization, immutability). Use the tag badges as quick revision flashcards.';
        }
        if (lower.includes('quiz') || lower.includes('test')) {
            return cfg.hasAssessments
                ? 'Use the "Take Quiz" button to attempt the linked assessment for this lesson.'
                : 'No quiz is linked to this lesson yet. Mark the lesson complete and check back later.';
        }
        return `Good question about "${title}". Re-read the highlighted sections, then summarize the answer in your own words. If you're stuck, note the specific term and ask your lecturer.`;
    }

    // ── Quick summary (client-side extract) ───────────────────────
    const summaryEl = document.getElementById('ai-summary-text');
    if (summaryEl) {
        const hero = document.querySelector('.hero-body');
        const tags = [...document.querySelectorAll('.tag-pill')].map((t) => t.textContent);
        const components = [...document.querySelectorAll('.component-title')].map((t) => t.textContent);
        let summary = '';
        if (hero) {
            summary = hero.textContent.trim().slice(0, 220);
            if (hero.textContent.length > 220) summary += '…';
        }
        if (components.length) {
            summary += (summary ? ' ' : '') + 'Topics: ' + components.join(', ') + '.';
        }
        if (tags.length) {
            summary += ' Key traits: ' + tags.slice(0, 4).join(', ') + '.';
        }
        summaryEl.textContent = summary || 'Read through the lesson cards below. Key ideas will appear here as you progress.';
    }

    // ── Voice narration (Web Speech API) ─────────────────────────
    const voiceBtn = document.getElementById('voice-narrate');
    let speaking = false;

    voiceBtn?.addEventListener('click', () => {
        if (!('speechSynthesis' in window)) {
            alert('Voice narration is not supported in this browser.');
            return;
        }
        if (speaking) {
            window.speechSynthesis.cancel();
            speaking = false;
            voiceBtn.classList.remove('active');
            return;
        }
        const parts = [];
        document.querySelectorAll('.hero-body, .component-desc, .tag-pill, .prose-card').forEach((el) => {
            const t = el.textContent.trim();
            if (t) parts.push(t);
        });
        const text = parts.join('. ').slice(0, 3000);
        if (!text) {
            alert('No lesson text found to narrate.');
            return;
        }
        const utter = new SpeechSynthesisUtterance(text);
        utter.rate = 0.95;
        utter.onend = () => {
            speaking = false;
            voiceBtn?.classList.remove('active');
        };
        speaking = true;
        voiceBtn.classList.add('active');
        window.speechSynthesis.speak(utter);
    });

    // ── Quiz modal ───────────────────────────────────────────────
    window.openQuizModal = function () {
        const overlay = document.getElementById('quiz-modal');
        if (overlay) overlay.classList.add('open');
        else if (cfg.hasAssessments) {
            document.getElementById('ap-overlay')?.classList.add('open');
        } else {
            alert('No quiz is available for this lesson yet.');
        }
    };

    document.querySelectorAll('[data-close-modal]').forEach((el) => {
        el.addEventListener('click', () => {
            el.closest('.modal-overlay')?.classList.remove('open');
        });
    });

    document.querySelectorAll('.modal-overlay').forEach((overlay) => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });

    // ── PDF toggle (global for inline onclick) ───────────────────
    window.togglePdfPane = function (id) {
        const wrap = document.getElementById(id);
        const frame = document.getElementById('frame-' + id);
        const label = document.getElementById('toggle-label-' + id);
        if (!wrap) return;
        const isOpen = wrap.style.height !== '0px' && wrap.style.height !== '';
        if (isOpen) {
            wrap.style.height = '0';
            wrap.style.overflow = 'hidden';
            if (label) label.textContent = 'Show';
        } else {
            if (frame) {
                const ds = frame.getAttribute('data-src');
                if (ds && (!frame.src || frame.src === window.location.href)) frame.src = ds;
            }
            wrap.style.height = '';
            wrap.style.overflow = 'visible';
            if (label) label.textContent = 'Hide';
        }
    };

    // Expose scroll container for lesson_view inline script
    window.lessonContentScroll = contentScroll;
})();
