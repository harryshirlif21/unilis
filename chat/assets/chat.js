/**
 * Chat client.
 *
 * Two independent polls rather than one: the open thread is checked every few
 * seconds with a since_id so the request is usually empty and cheap, while the
 * conversation list — which runs group discovery server-side — is checked far
 * less often. Both back off when the tab is hidden.
 *
 * Message bodies are written with textContent, never innerHTML, so a message
 * containing markup renders as the characters the sender typed.
 */
(function () {
    'use strict';

    var CFG = window.__CHAT__;
    if (!CFG) { return; }

    var THREAD_POLL_MS = 3000;
    var LIST_POLL_MS = 12000;

    var state = {
        conversations: [],
        activeId: 0,
        canPost: false,
        lastMessageId: 0,
        filter: 'all',
        search: '',
        threadTimer: null,
        listTimer: null,
        sending: false,
        lastRenderedDay: ''
    };

    var el = {
        shell: document.getElementById('chatShell'),
        list: document.getElementById('conversationList'),
        filter: document.getElementById('conversationFilter'),
        tabs: document.getElementById('chatTabs'),
        empty: document.getElementById('chatEmpty'),
        pane: document.getElementById('chatPane'),
        title: document.getElementById('chatTitle'),
        subtitle: document.getElementById('chatSubtitle'),
        messages: document.getElementById('messageList'),
        composer: document.getElementById('composer'),
        input: document.getElementById('composerInput'),
        sendBtn: document.getElementById('sendBtn'),
        fileInput: document.getElementById('fileInput'),
        attachBtn: document.getElementById('attachBtn'),
        attachPreview: document.getElementById('attachPreview'),
        attachName: document.getElementById('attachName'),
        attachSize: document.getElementById('attachSize'),
        attachClear: document.getElementById('attachClear'),
        refreshBtn: document.getElementById('refreshBtn'),
        backBtn: document.getElementById('backToList'),
        newChatBtn: document.getElementById('newChatBtn'),
        newChatModal: document.getElementById('newChatModal'),
        directorySearch: document.getElementById('directorySearch'),
        directoryList: document.getElementById('directoryList'),
        instructionBtn: document.getElementById('newInstructionBtn'),
        instructionModal: document.getElementById('instructionModal'),
        instructionTarget: document.getElementById('instructionTarget'),
        instructionBody: document.getElementById('instructionBody'),
        instructionEmail: document.getElementById('instructionEmail'),
        instructionSend: document.getElementById('instructionSend'),
        toast: document.getElementById('chatToast')
    };

    // ── Networking ────────────────────────────────────────────────────────

    function api(path, options) {
        var opts = options || {};
        var init = { credentials: 'same-origin', headers: {} };

        if (opts.body) {
            init.method = 'POST';
            init.headers['Content-Type'] = 'application/json';
            opts.body.csrf_token = CFG.csrfToken;
            init.body = JSON.stringify(opts.body);
        }

        return fetch(CFG.apiBase + path, init).then(function (response) {
            return response.json().catch(function () {
                throw new Error('The server returned an unreadable response.');
            }).then(function (data) {
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Request failed (' + response.status + ')');
                }
                return data;
            });
        });
    }

    function toast(message, kind) {
        el.toast.textContent = message;
        el.toast.className = 'chat-toast' + (kind ? ' is-' + kind : '');
        el.toast.hidden = false;
        clearTimeout(toast._timer);
        toast._timer = setTimeout(function () { el.toast.hidden = true; }, 4200);
    }

    // ── Formatting ────────────────────────────────────────────────────────

    /** MySQL DATETIME -> Date, without relying on browser parsing of 'YYYY-MM-DD HH:MM:SS'. */
    function parseDate(value) {
        if (!value) { return null; }
        var parts = String(value).split(/[- :]/);
        if (parts.length < 6) { return null; }
        return new Date(parts[0], parts[1] - 1, parts[2], parts[3], parts[4], parts[5]);
    }

    function formatTime(value) {
        var date = parseDate(value);
        if (!date) { return ''; }
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function formatListTime(value) {
        var date = parseDate(value);
        if (!date) { return ''; }
        var now = new Date();
        var sameDay = date.toDateString() === now.toDateString();
        return sameDay
            ? date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            : date.toLocaleDateString([], { day: 'numeric', month: 'short' });
    }

    function formatDay(value) {
        var date = parseDate(value);
        if (!date) { return ''; }
        var today = new Date();
        var yesterday = new Date();
        yesterday.setDate(today.getDate() - 1);

        if (date.toDateString() === today.toDateString()) { return 'Today'; }
        if (date.toDateString() === yesterday.toDateString()) { return 'Yesterday'; }
        return date.toLocaleDateString([], { day: 'numeric', month: 'long', year: 'numeric' });
    }

    function initials(name) {
        var words = String(name || '?').trim().split(/\s+/).slice(0, 2);
        return words.map(function (w) { return w.charAt(0).toUpperCase(); }).join('') || '?';
    }

    var ICONS = {
        team: 'fa-users',
        course: 'fa-graduation-cap',
        course_year: 'fa-graduation-cap',
        unit_announce: 'fa-bullhorn'
    };

    // ── Conversation list ─────────────────────────────────────────────────

    function matchesFilter(conversation) {
        if (state.filter === 'direct') { return conversation.type === 'direct'; }
        if (state.filter === 'instructions') { return conversation.type === 'unit_announce'; }
        if (state.filter === 'group') {
            return conversation.type === 'team'
                || conversation.type === 'course'
                || conversation.type === 'course_year';
        }
        return true;
    }

    function matchesSearch(conversation) {
        if (!state.search) { return true; }
        return conversation.title.toLowerCase().indexOf(state.search) !== -1;
    }

    function renderConversations() {
        var visible = state.conversations.filter(function (c) {
            return matchesFilter(c) && matchesSearch(c);
        });

        el.list.textContent = '';

        if (!visible.length) {
            var empty = document.createElement('li');
            empty.className = 'chat-placeholder';
            empty.textContent = state.conversations.length
                ? 'Nothing matches that filter.'
                : 'No conversations yet. Use “New chat” to start one.';
            el.list.appendChild(empty);
            return;
        }

        visible.forEach(function (conversation) {
            var item = document.createElement('li');

            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'chat-conversation' + (conversation.id === state.activeId ? ' is-active' : '');
            button.addEventListener('click', function () { openConversation(conversation.id); });

            var avatar = document.createElement('span');
            avatar.className = 'chat-avatar';
            avatar.setAttribute('data-kind', conversation.type);
            if (ICONS[conversation.type]) {
                var icon = document.createElement('i');
                icon.className = 'fas ' + ICONS[conversation.type];
                avatar.appendChild(icon);
            } else {
                avatar.textContent = initials(conversation.title);
            }

            var body = document.createElement('div');
            body.className = 'chat-conversation-body';

            var top = document.createElement('div');
            top.className = 'chat-conversation-top';

            var name = document.createElement('span');
            name.className = 'chat-conversation-name';
            name.textContent = conversation.title;
            top.appendChild(name);

            if (conversation.last_message_at) {
                var time = document.createElement('span');
                time.className = 'chat-conversation-time';
                time.textContent = formatListTime(conversation.last_message_at);
                top.appendChild(time);
            }

            var preview = document.createElement('p');
            preview.className = 'chat-conversation-preview';
            preview.textContent = conversation.last_body || conversation.subtitle;

            body.appendChild(top);
            body.appendChild(preview);

            button.appendChild(avatar);
            button.appendChild(body);

            if (conversation.unread_count > 0) {
                var badge = document.createElement('span');
                badge.className = 'chat-badge';
                badge.textContent = conversation.unread_count > 99 ? '99+' : conversation.unread_count;
                button.appendChild(badge);
            }

            item.appendChild(button);
            el.list.appendChild(item);
        });
    }

    function loadConversations(force) {
        return api('conversations.php' + (force ? '?force=1' : ''))
            .then(function (data) {
                state.conversations = data.conversations;
                renderConversations();

                if (state.activeId) {
                    var active = state.conversations.filter(function (c) {
                        return c.id === state.activeId;
                    })[0];
                    if (active) {
                        el.title.textContent = active.title;
                        el.subtitle.textContent = active.subtitle;
                    }
                }
                return data;
            })
            .catch(function (error) {
                el.list.textContent = '';
                var failed = document.createElement('li');
                failed.className = 'chat-placeholder';
                failed.textContent = error.message;
                el.list.appendChild(failed);
            });
    }

    // ── Thread ────────────────────────────────────────────────────────────

    function appendMessage(message) {
        var day = formatDay(message.created_at);
        if (day && day !== state.lastRenderedDay) {
            var divider = document.createElement('div');
            divider.className = 'chat-day';
            divider.textContent = day;
            el.messages.appendChild(divider);
            state.lastRenderedDay = day;
        }

        var isMine = message.sender_id === CFG.me.id && message.sender_role === CFG.me.role;

        var wrapper = document.createElement('div');
        wrapper.className = 'chat-message'
            + (isMine ? ' is-mine' : '')
            + (message.is_instruction ? ' is-instruction' : '');

        var meta = document.createElement('p');
        meta.className = 'chat-message-meta';
        meta.textContent = (isMine ? 'You' : (message.sender_name || 'Unknown'))
            + ' · ' + formatTime(message.created_at);
        wrapper.appendChild(meta);

        var bubble = document.createElement('div');
        bubble.className = 'chat-bubble';

        if (message.is_instruction) {
            var tag = document.createElement('span');
            tag.className = 'chat-instruction-tag';
            var tagIcon = document.createElement('i');
            tagIcon.className = 'fas fa-bullhorn';
            tag.appendChild(tagIcon);
            tag.appendChild(document.createTextNode('Instructions'));
            bubble.appendChild(tag);
            bubble.appendChild(document.createElement('br'));
        }

        // textContent, not innerHTML: a body containing markup must render as
        // the characters the sender typed.
        if (message.body) {
            bubble.appendChild(document.createTextNode(message.body));
        }

        if (message.attachment) {
            bubble.appendChild(renderAttachment(message));
        }

        wrapper.appendChild(bubble);

        el.messages.appendChild(wrapper);

        if (message.id > state.lastMessageId) {
            state.lastMessageId = message.id;
        }
    }

    /**
     * Attachment block for a message bubble.
     *
     * The href goes through the API rather than at the file, so the download is
     * authorised per request and the stored path never reaches the client.
     */
    function renderAttachment(message) {
        var att = message.attachment;
        var wrap = document.createElement('div');
        wrap.className = 'chat-attachment' + (message.body ? ' has-caption' : '');

        var href = CFG.apiBase + att.url;

        if (att.is_image) {
            var link = document.createElement('a');
            link.href = href;
            link.target = '_blank';
            link.rel = 'noopener';
            var img = document.createElement('img');
            img.src = href;
            img.alt = att.name;
            img.className = 'chat-attachment-image';
            img.loading = 'lazy';
            link.appendChild(img);
            wrap.appendChild(link);
        }

        var row = document.createElement('a');
        row.className = 'chat-attachment-file';
        row.href = href;
        // download makes the browser save it rather than navigate, and keeps the
        // original filename even though the stored name is random.
        row.setAttribute('download', att.name);

        var icon = document.createElement('i');
        icon.className = 'fas fa-paperclip';
        var name = document.createElement('span');
        name.className = 'chat-attachment-name';
        name.textContent = att.name;
        var size = document.createElement('span');
        size.className = 'chat-attachment-size';
        size.textContent = att.size_label;

        row.appendChild(icon);
        row.appendChild(name);
        row.appendChild(size);
        wrap.appendChild(row);

        return wrap;
    }

    function isScrolledToBottom() {
        return el.messages.scrollHeight - el.messages.scrollTop - el.messages.clientHeight < 80;
    }

    function scrollToBottom() {
        el.messages.scrollTop = el.messages.scrollHeight;
    }

    function openConversation(conversationId) {
        state.activeId = conversationId;
        state.lastMessageId = 0;
        state.lastRenderedDay = '';
        el.messages.textContent = '';
        el.empty.hidden = true;
        el.pane.hidden = false;

        if (el.shell) { el.shell.setAttribute('data-mobile-view', 'thread'); }

        var conversation = state.conversations.filter(function (c) {
            return c.id === conversationId;
        })[0];
        el.title.textContent = conversation ? conversation.title : 'Conversation';
        el.subtitle.textContent = conversation ? conversation.subtitle : '';

        renderConversations();

        return fetchMessages().then(function () {
            scrollToBottom();
            // The unread badge is now stale for this thread; clear it locally
            // so the count does not linger until the next list poll.
            if (conversation) {
                conversation.unread_count = 0;
                renderConversations();
            }
        });
    }

    function fetchMessages() {
        if (!state.activeId) { return Promise.resolve(); }

        var conversationId = state.activeId;
        var url = 'messages.php?conversation_id=' + conversationId + '&since_id=' + state.lastMessageId;

        return api(url).then(function (data) {
            // A slow response for a thread the user has already left must not
            // paint into the thread they are now looking at.
            if (data.conversation_id !== state.activeId) { return; }

            // Every conversation is two-way now, including unit instruction
            // channels, so can_post is only a server-side backstop. The
            // composer stays put rather than being swapped for a notice.
            state.canPost = data.can_post;
            el.composer.hidden = false;

            if (!data.messages.length) { return; }

            var wasAtBottom = isScrolledToBottom();
            data.messages.forEach(appendMessage);
            if (wasAtBottom) { scrollToBottom(); }
        }).catch(function (error) {
            if (state.activeId === conversationId) {
                toast(error.message, 'error');
            }
        });
    }

    function sendMessage() {
        var body = el.input.value.trim();
        var file = el.fileInput.files[0] || null;

        // A file on its own is a valid message; text on its own is too.
        if ((!body && !file) || state.sending || !state.activeId) { return; }

        state.sending = true;
        el.sendBtn.disabled = true;

        var request = file
            ? uploadFile(file, body)
            : api('send.php', { body: { conversation_id: state.activeId, body: body } });

        request
            .then(function () {
                el.input.value = '';
                clearAttachment();
                autoGrow();
                // Let the poll deliver it, so the message is rendered from the
                // same source as everyone else's and cannot appear twice.
                return fetchMessages().then(scrollToBottom);
            })
            .catch(function (error) { toast(error.message, 'error'); })
            .finally(function () {
                state.sending = false;
                el.sendBtn.disabled = false;
                el.input.focus();
            });
    }

    /**
     * Files go up as multipart rather than JSON, so this cannot reuse api().
     */
    function uploadFile(file, caption) {
        var form = new FormData();
        form.append('conversation_id', state.activeId);
        form.append('body', caption || '');
        form.append('csrf_token', CFG.csrfToken);
        form.append('file', file);

        return fetch(CFG.apiBase + 'upload.php', {
            method: 'POST',
            credentials: 'same-origin',
            // No Content-Type header: the browser has to set the multipart
            // boundary itself, and naming the type here would omit it.
            body: form,
        }).then(function (response) {
            return response.json().catch(function () {
                throw new Error('The server returned an unreadable response.');
            }).then(function (data) {
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Upload failed (' + response.status + ')');
                }
                return data;
            });
        });
    }

    function formatBytes(bytes) {
        if (bytes < 1024) { return bytes + ' B'; }
        if (bytes < 1024 * 1024) { return (bytes / 1024).toFixed(1) + ' KB'; }
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function clearAttachment() {
        el.fileInput.value = '';
        el.attachPreview.hidden = true;
    }

    function autoGrow() {
        el.input.style.height = 'auto';
        el.input.style.height = Math.min(el.input.scrollHeight, 160) + 'px';
    }

    // ── Directory ─────────────────────────────────────────────────────────

    function loadDirectory() {
        var query = el.directorySearch.value.trim();
        el.directoryList.textContent = '';
        var loading = document.createElement('li');
        loading.className = 'chat-placeholder';
        loading.textContent = 'Searching…';
        el.directoryList.appendChild(loading);

        api('directory.php?q=' + encodeURIComponent(query))
            .then(function (data) {
                el.directoryList.textContent = '';

                if (!data.people.length) {
                    var none = document.createElement('li');
                    none.className = 'chat-placeholder';
                    none.textContent = 'Nobody matches that search.';
                    el.directoryList.appendChild(none);
                    return;
                }

                data.people.forEach(function (person) {
                    var item = document.createElement('li');
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'chat-directory-item';

                    var avatar = document.createElement('span');
                    avatar.className = 'chat-avatar';
                    avatar.setAttribute('data-kind', 'direct');
                    avatar.textContent = initials(person.name);

                    var text = document.createElement('span');
                    var name = document.createElement('span');
                    name.className = 'chat-directory-name';
                    name.textContent = person.name;
                    var sub = document.createElement('span');
                    sub.className = 'chat-directory-sub';
                    sub.textContent = person.subtitle;
                    text.appendChild(name);
                    text.appendChild(document.createElement('br'));
                    text.appendChild(sub);

                    var pill = document.createElement('span');
                    pill.className = 'chat-role-pill';
                    pill.textContent = person.role === 'lecturer' ? 'Lecturer' : 'Student';

                    button.appendChild(avatar);
                    button.appendChild(text);
                    button.appendChild(pill);
                    button.addEventListener('click', function () { startDirect(person); });

                    item.appendChild(button);
                    el.directoryList.appendChild(item);
                });
            })
            .catch(function (error) {
                el.directoryList.textContent = '';
                var failed = document.createElement('li');
                failed.className = 'chat-placeholder';
                failed.textContent = error.message;
                el.directoryList.appendChild(failed);
            });
    }

    function startDirect(person) {
        api('start_direct.php', { body: { user_id: person.id, user_role: person.role } })
            .then(function (data) {
                closeModals();
                return loadConversations(true).then(function () {
                    return openConversation(data.conversation_id);
                });
            })
            .catch(function (error) { toast(error.message, 'error'); });
    }

    // ── Instructions (lecturers) ──────────────────────────────────────────

    function loadInstructionTargets() {
        el.instructionTarget.textContent = '';
        var loading = document.createElement('option');
        loading.value = '';
        loading.textContent = 'Loading targets…';
        el.instructionTarget.appendChild(loading);

        api('instruction_targets.php')
            .then(function (data) {
                el.instructionTarget.textContent = '';

                if (!data.units.length && !data.courses.length) {
                    var none = document.createElement('option');
                    none.value = '';
                    none.textContent = 'You are not assigned to any units yet';
                    el.instructionTarget.appendChild(none);
                    return;
                }

                if (data.units.length) {
                    var unitGroup = document.createElement('optgroup');
                    unitGroup.label = 'Units you teach';
                    data.units.forEach(function (unit) {
                        var option = document.createElement('option');
                        option.value = 'unit:' + unit.unit_id + ':0';
                        option.textContent = unit.label + ' (' + unit.student_count + ' students)';
                        unitGroup.appendChild(option);
                    });
                    el.instructionTarget.appendChild(unitGroup);
                }

                data.courses.forEach(function (course) {
                    var group = document.createElement('optgroup');
                    group.label = course.name;

                    var all = document.createElement('option');
                    all.value = 'course:' + course.course_id + ':0';
                    all.textContent = 'Whole course (' + course.student_count + ' students)';
                    group.appendChild(all);

                    course.years.forEach(function (year) {
                        var option = document.createElement('option');
                        option.value = 'course_year:' + course.course_id + ':' + year.year;
                        option.textContent = 'Year ' + year.year + ' (' + year.student_count + ' students)';
                        group.appendChild(option);
                    });

                    el.instructionTarget.appendChild(group);
                });
            })
            .catch(function (error) {
                el.instructionTarget.textContent = '';
                var failed = document.createElement('option');
                failed.value = '';
                failed.textContent = error.message;
                el.instructionTarget.appendChild(failed);
            });
    }

    function postInstruction() {
        var target = el.instructionTarget.value;
        var body = el.instructionBody.value.trim();

        if (!target) { toast('Choose who these instructions are for.', 'error'); return; }
        if (!body) { toast('Write the instructions first.', 'error'); return; }

        var parts = target.split(':');
        el.instructionSend.disabled = true;

        api('post_instruction.php', {
            body: {
                target_type: parts[0],
                target_id: parseInt(parts[1], 10),
                year: parseInt(parts[2], 10),
                body: body,
                send_email: el.instructionEmail.checked
            }
        })
            .then(function (data) {
                var summary = 'Instructions posted to ' + data.recipients + ' student'
                    + (data.recipients === 1 ? '' : 's') + '.';
                if (el.instructionEmail.checked) {
                    summary += ' Emailed ' + data.emails_sent
                        + (data.emails_failed ? ', ' + data.emails_failed + ' failed' : '') + '.';
                }
                toast(summary, data.emails_failed ? 'error' : 'success');

                el.instructionBody.value = '';
                el.instructionEmail.checked = false;
                closeModals();

                return loadConversations(true).then(function () {
                    return openConversation(data.conversation_id);
                });
            })
            .catch(function (error) { toast(error.message, 'error'); })
            .finally(function () { el.instructionSend.disabled = false; });
    }

    // ── Modals ────────────────────────────────────────────────────────────

    function closeModals() {
        if (el.newChatModal) { el.newChatModal.hidden = true; }
        if (el.instructionModal) { el.instructionModal.hidden = true; }
    }

    // ── Polling ───────────────────────────────────────────────────────────

    function startPolling() {
        stopPolling();
        state.threadTimer = setInterval(function () {
            if (document.hidden || !state.activeId) { return; }
            fetchMessages();
        }, THREAD_POLL_MS);

        state.listTimer = setInterval(function () {
            if (document.hidden) { return; }
            loadConversations(false);
        }, LIST_POLL_MS);
    }

    function stopPolling() {
        clearInterval(state.threadTimer);
        clearInterval(state.listTimer);
    }

    // ── Wiring ────────────────────────────────────────────────────────────

    el.composer.addEventListener('submit', function (event) {
        event.preventDefault();
        sendMessage();
    });

    el.attachBtn.addEventListener('click', function () { el.fileInput.click(); });
    el.attachClear.addEventListener('click', clearAttachment);

    el.fileInput.addEventListener('change', function () {
        var file = el.fileInput.files[0];
        if (!file) { clearAttachment(); return; }

        // Checked again on the server; this only spares the user a pointless
        // upload of something that would be rejected on arrival.
        if (file.size > CFG.maxUploadBytes) {
            toast('That file is larger than the ' + formatBytes(CFG.maxUploadBytes) + ' limit.', 'error');
            clearAttachment();
            return;
        }

        el.attachName.textContent = file.name;
        el.attachSize.textContent = formatBytes(file.size);
        el.attachPreview.hidden = false;
        el.input.focus();
    });

    el.input.addEventListener('input', autoGrow);
    el.input.addEventListener('keydown', function (event) {
        // Enter sends, Shift+Enter makes a new line.
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    });

    el.filter.addEventListener('input', function () {
        state.search = el.filter.value.trim().toLowerCase();
        renderConversations();
    });

    el.tabs.addEventListener('click', function (event) {
        var tab = event.target.closest('.chat-tab');
        if (!tab) { return; }
        state.filter = tab.getAttribute('data-filter');
        Array.prototype.forEach.call(el.tabs.querySelectorAll('.chat-tab'), function (node) {
            node.classList.toggle('is-active', node === tab);
        });
        renderConversations();
    });

    el.refreshBtn.addEventListener('click', function () {
        el.refreshBtn.disabled = true;
        loadConversations(true).finally(function () { el.refreshBtn.disabled = false; });
    });

    if (el.backBtn) {
        el.backBtn.addEventListener('click', function () {
            if (el.shell) { el.shell.setAttribute('data-mobile-view', 'list'); }
        });
    }

    el.newChatBtn.addEventListener('click', function () {
        el.newChatModal.hidden = false;
        el.directorySearch.value = '';
        loadDirectory();
        el.directorySearch.focus();
    });

    var directoryDebounce = null;
    el.directorySearch.addEventListener('input', function () {
        clearTimeout(directoryDebounce);
        directoryDebounce = setTimeout(loadDirectory, 250);
    });

    if (el.instructionBtn) {
        el.instructionBtn.addEventListener('click', function () {
            el.instructionModal.hidden = false;
            loadInstructionTargets();
        });
        el.instructionSend.addEventListener('click', postInstruction);
    }

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-close-modal]')) { closeModals(); }
        // Clicking the backdrop, but not the card, dismisses a modal.
        if (event.target.classList.contains('chat-modal')) { closeModals(); }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') { closeModals(); }
    });

    document.addEventListener('visibilitychange', function () {
        // Catch up immediately on return rather than waiting out the interval.
        if (!document.hidden) {
            loadConversations(false);
            fetchMessages();
        }
    });

    // ── Boot ──────────────────────────────────────────────────────────────

    el.shell.setAttribute('data-mobile-view', 'list');

    loadConversations(true).then(function () {
        if (CFG.initialConversation) {
            var exists = state.conversations.some(function (c) {
                return c.id === CFG.initialConversation;
            });
            if (exists) { openConversation(CFG.initialConversation); }
        }
        startPolling();
    });
}());
