LiveEngagement.shim={};

    LiveEngagement.init();

    const LE_AUTHENTICATED = ;

    function currentNode() { return document.getElementById('sessionCode').value.trim().toUpperCase(); }
    function currentName() { return document.getElementById('displayName').value.trim(); }
    function currentEmail() {
        const input = document.getElementById('guestEmail');
        return input ? input.value.trim().toLowerCase() : '';
    }

    function setBusy(busy) {
        document.getElementById('loadingSpinner').style.display = busy ? 'flex' : 'none';
        const btn = document.getElementById('joinButton');
        btn.disabled = busy;
        btn.innerHTML = busy
            ? '<div class="le-spinner le-spinner-sm" style="border-color: rgba(255,255,255,0.3); border-top-color: white;"></div> Joining...'
            : '<span class="material-symbols-rounded" style="font-size: 22px;">login</span> Join Session';
    }

    // ── Join after the code and display name have been supplied ────
    async function performJoin(code, displayName, email) {
        document.getElementById('errorMessage').style.display = 'none';
        setBusy(true);
        try {
            const check = await LiveEngagement.checkSessionCode(code);
            if (!check.exists) {
                showError('Session not found. Please check the code and try again.', {
                    step: 'check', code: 'LE_SESSION_NOT_FOUND',
                    detail: 'No live session matched the code "' + code + '".',
                });
                return;
            }
            if (!check.active) {
                showError('This session is not currently active.', {
                    step: 'check', code: 'LE_SESSION_NOT_ACTIVE',
                    detail: 'The session for code "' + code + '" has status "' + (check.session && check.session.status) + '". Please ask the presenter to start it.',
                });
                return;
            }
            const result = await LiveEngagement.joinSession(code, displayName || 'Participant', email);
            window.location.href = '?page=session&id=' + result.session.id;
        } catch (error) {
            showError(error, { step: error.step || 'join', detail: error.detail, code: error.code, requestId: error.requestId, status: error.status, data: error.data });
        } finally {
            setBusy(false);
        }
    }

    async function authenticateGuest(displayName, email) {
        let response;
        try {
            response = await fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'join_as_guest', name: displayName, email: email }),
            });
        } catch (e) {
            const err = new Error('Could not reach the server. Please check your connection and try again.');
            err.step = 'guest_auth';
            err.code = 'LE_NETWORK';
            err.detail = e.message || String(e);
            throw err;
        }

        let data = {};
        try {
            data = await response.json();
        } catch (parseError) {
            const err = new Error('The server returned an invalid response while preparing your join.');
            err.step = 'guest_auth';
            err.code = 'LE_INVALID_RESPONSE';
            err.status = response.status;
            try {
                err.detail = 'Response was not valid JSON. HTTP ' + response.status + '. Body: ' + (await response.text()).slice(0, 500);
            } catch (_) {
                err.detail = 'Response was not valid JSON. HTTP ' + response.status + '.';
            }
            throw err;
        }

        if (!response.ok || data.success !== true) {
            const err = new Error(
                (Array.isArray(data.errors) && data.errors[0])
                || data.error
                || 'Unable to prepare the session join. Please try again.'
            );
            err.step = data.step || 'guest_auth';
            err.code = data.code || 'LE_GUEST_REJECTED';
            err.requestId = data.request_id || '';
            err.status = response.status;
            err.detail = data.detail || '';
            err.data = data;
            throw err;
        }
    }

    // ── Form submit ───────────────────────────────────────────────
    async function joinSession(event) {
        event.preventDefault();
        const code = currentNode();
        const displayName = currentName();

        if (!code) { showError('Please enter a session code'); return; }
        if (!displayName) {
            showError('Please enter your display name');
            document.getElementById('displayName').focus();
            return;
        }
        const email = currentEmail();
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showError('Please enter a valid email address');
            document.getElementById('guestEmail').focus();
            return;
        }

        setBusy(true);
        try {
            if (!LE_AUTHENTICATED) {
                await authenticateGuest(displayName, email);
            }
            await performJoin(code, displayName, email);
        } catch (error) {
            showError(error, { step: error.step || 'join', detail: error.detail, code: error.code, requestId: error.requestId, status: error.status, data: error.data });
            setBusy(false);
        }
    }

    // ── Join from the "Available Sessions" list (logged-in users) ─
    async function joinBySessionId(sessionId) {
        try {
            const session = await LiveEngagement.getSession(sessionId);
            if (session.status === 'active') {
                const result = await LiveEngagement.joinSession(session.session_code, '');
                window.location.href = '?page=session&id=' + result.session.id;
            } else {
                showError('Session is not active', {
                    step: 'list', code: 'LE_SESSION_NOT_ACTIVE',
                    detail: 'The session for id "' + sessionId + '" has status "' + (session.status) + '".',
                });
            }
        } catch (error) {
            showError(error, { step: error.step || 'join', detail: error.detail, code: error.code, requestId: error.requestId, status: error.status, data: error.data });
        }
    }

    function showError(message, opts = {}) {
        const box = document.getElementById('errorMessage');
        const text = document.getElementById('errorMessageText');
        const detailsBox = document.getElementById('errorDetails');
        const toggle = document.getElementById('errorDetailsToggle');
        const copyBtn = document.getElementById('errorDetailsCopy');

        // Accept an Error object (with .detail/.code/.step/.requestId/.status)
        // or a plain string plus an opts object.
        if (message instanceof Error) {
            opts.detail = opts.detail || message.detail;
            opts.code = opts.code || message.code;
            opts.step = opts.step || message.step;
            opts.requestId = opts.requestId || message.requestId;
            opts.status = opts.status || message.status;
            opts.data = opts.data || message.data;
            message = message.message;
        }
        message = message || 'Failed to prepare the session join. Please try again.';

        text.textContent = message;

        // Compose a human-readable technical block.
        const lines = [];
        if (opts.step) lines.push('Step: ' + opts.step);
        if (opts.code) lines.push('Error code: ' + opts.code);
        if (opts.status) lines.push('HTTP status: ' + opts.status);
        if (opts.requestId) lines.push('Reference: ' + opts.requestId);
        if (opts.detail) lines.push('Technical detail: ' + opts.detail);
        if (opts.data && typeof opts.data === 'object') {
            lines.push('Server response: ' + JSON.stringify(opts.data));
        }

        const hasDetail = lines.length > 0;
        detailsBox.textContent = hasDetail ? lines.join('\n') : '';
        detailsBox.style.display = 'none';
        toggle.textContent = 'Show technical details';
        toggle.dataset.open = '0';
        toggle.style.display = hasDetail ? 'inline-flex' : 'none';
        copyBtn.style.display = hasDetail ? 'inline-flex' : 'none';

        box.style.display = 'block';
        console.error('Live Engagement join error:', message, opts);
    }

    // ── Auto-submit on Enter ──────────────────────────────────────
    document.getElementById('sessionCode').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('displayName').focus();
        }
    });

    document.getElementById('displayName').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('joinForm').dispatchEvent(new Event('submit'));
        }
    });

// ── Error details toggle / copy handlers ─────────────────────
    document.getElementById('errorDetailsToggle').addEventListener('click', function() {
        const open = this.dataset.open === '1';
        this.dataset.open = open ? '0' : '1';
        document.getElementById('errorDetails').style.display = open ? 'none' : 'block';
        this.textContent = open ? 'Show technical details' : 'Hide technical details';
    });
    document.getElementById('errorDetailsCopy').addEventListener('click', async function() {
        const detailsBox = document.getElementById('errorDetails');
        const text = detailsBox.textContent || document.getElementById('errorMessageText').textContent;
        try {
            await navigator.clipboard.writeText(text);
            this.textContent = 'Copied!';
            setTimeout(() => { this.textContent = 'Copy details'; }, 1800);
        } catch (_) {
            this.textContent = 'Copy failed';
            setTimeout(() => { this.textContent = 'Copy details'; }, 1800);
        }
    });
    // ── Auto-focus code input ─────────────────────────────────────
    
    document.getElementById('sessionCode').focus();
    
    document.getElementById('displayName').focus();
    

    // ── Resume a join after returning from the UNILIS login ───────
    (function resumeJoinAfterLogin() {
        const params = new URLSearchParams(window.location.search);
        const code = (params.get('code') || '').trim().toUpperCase();
        if (code && !document.getElementById('sessionCode').value) {
            document.getElementById('sessionCode').value = code;
            document.getElementById('displayName').focus();
        }
    })();
