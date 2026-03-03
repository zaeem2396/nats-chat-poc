<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>NATS Chat PoC</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700" rel="stylesheet" />
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'DM Sans', system-ui, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; }
        .layout { display: flex; flex-direction: column; height: 100vh; max-width: 1200px; margin: 0 auto; }
        .header { padding: 1rem 1.5rem; border-bottom: 1px solid #334155; display: flex; align-items: center; justify-content: space-between; }
        .header h1 { font-size: 1.25rem; font-weight: 600; margin: 0; }
        .main { display: flex; flex: 1; min-height: 0; }
        .sidebar { width: 260px; border-right: 1px solid #334155; padding: 1rem; overflow-y: auto; }
        .sidebar h2 { font-size: 0.875rem; font-weight: 600; color: #94a3b8; margin: 0 0 0.75rem 0; }
        .room-form { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
        .room-form input { flex: 1; padding: 0.5rem 0.75rem; border: 1px solid #475569; border-radius: 6px; background: #1e293b; color: #e2e8f0; font-size: 0.875rem; }
        .room-form input::placeholder { color: #64748b; }
        .btn { padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; border: none; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-secondary { background: #334155; color: #e2e8f0; }
        .btn-secondary:hover { background: #475569; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }
        .room-list { list-style: none; padding: 0; margin: 0; }
        .room-list li { margin-bottom: 0.25rem; }
        .room-list button { width: 100%; text-align: left; padding: 0.625rem 0.75rem; border-radius: 6px; background: transparent; color: #e2e8f0; border: none; cursor: pointer; font-size: 0.875rem; }
        .room-list button:hover { background: #1e293b; }
        .room-list button.active { background: #1e3a5f; color: #93c5fd; }
        .content { flex: 1; display: flex; flex-direction: column; min-width: 0; padding: 1rem 1.5rem; }
        .content-empty { flex: 1; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 0.875rem; }
        .room-header { margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid #334155; }
        .room-header h3 { margin: 0 0 0.25rem 0; font-size: 1.125rem; }
        .room-header .meta { font-size: 0.75rem; color: #94a3b8; }
        .history { flex: 1; overflow-y: auto; margin-bottom: 1rem; padding-right: 0.5rem; }
        .message { padding: 0.5rem 0.75rem; margin-bottom: 0.5rem; background: #1e293b; border-radius: 8px; border-left: 3px solid #3b82f6; }
        .message .msg-meta { font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.25rem; }
        .message .msg-content { font-size: 0.875rem; }
        .message.scheduled { border-left-color: #eab308; }
        .forms { display: flex; flex-direction: column; gap: 1rem; }
        .form-group { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: flex-end; }
        .form-group label { display: block; font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.25rem; width: 100%; }
        .form-group input { padding: 0.5rem 0.75rem; border: 1px solid #475569; border-radius: 6px; background: #1e293b; color: #e2e8f0; font-size: 0.875rem; }
        .form-group input[type="number"] { width: 80px; }
        .form-group .flex-1 { flex: 1; min-width: 120px; }
        .analytics-box { padding: 0.75rem 1rem; background: #1e293b; border-radius: 8px; margin-bottom: 1rem; font-size: 0.875rem; }
        .analytics-box strong { color: #93c5fd; }
        .error { padding: 0.5rem 0.75rem; background: #7f1d1d; color: #fecaca; border-radius: 6px; font-size: 0.875rem; margin-bottom: 0.5rem; }
        .success { padding: 0.5rem 0.75rem; background: #14532d; color: #bbf7d0; border-radius: 6px; font-size: 0.875rem; margin-bottom: 0.5rem; }
    </style>
</head>
<body>
    <div class="layout">
        <header class="header">
            <h1>NATS Chat PoC</h1>
        </header>
        <div class="main">
            <aside class="sidebar">
                <h2>Rooms</h2>
                <form class="room-form" id="formNewRoom">
                    <input type="text" id="newRoomName" placeholder="New room name" required>
                    <button type="submit" class="btn btn-primary">Create</button>
                </form>
                <ul class="room-list" id="roomList"></ul>
            </aside>
            <main class="content">
                <div id="contentEmpty" class="content-empty">Select a room or create one.</div>
                <div id="contentRoom" style="display: none;">
                    <div class="room-header" style="display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <h3 id="roomTitle">Room</h3>
                            <div class="meta" id="roomMeta"></div>
                        </div>
                        <button type="button" class="btn btn-secondary" id="btnRefresh">Refresh</button>
                    </div>
                    <div id="messageArea"></div>
                    <div class="analytics-box" id="analyticsBox">Message count: —</div>
                    <div id="formErrors"></div>
                    <div class="forms">
                        <div class="form-group">
                            <label>Send message</label>
                            <input type="number" id="sendUserId" value="1" min="1" placeholder="User ID">
                            <input type="text" id="sendContent" class="flex-1" placeholder="Message content">
                            <button type="button" class="btn btn-primary" id="btnSend">Send</button>
                        </div>
                        <div class="form-group">
                            <label>Schedule message (delay in minutes)</label>
                            <input type="number" id="scheduleUserId" value="1" min="1" placeholder="User ID">
                            <input type="text" id="scheduleContent" class="flex-1" placeholder="Message content">
                            <input type="number" id="scheduleDelay" value="1" min="1" max="1440" placeholder="Min">
                            <button type="button" class="btn btn-secondary" id="btnSchedule">Schedule</button>
                        </div>
                        <div class="form-group">
                            <button type="button" class="btn btn-danger" id="btnFailTest">Send fail-test message (triggers retries → failed_jobs)</button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script>
        const api = '/api';
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        function headers(json = true) {
            const h = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
            if (token) h['X-CSRF-TOKEN'] = token;
            if (json) h['Content-Type'] = 'application/json';
            return h;
        }

        let rooms = [];
        let selectedRoomId = null;

        async function loadRooms() {
            const res = await fetch(api + '/rooms');
            if (!res.ok) throw new Error('Failed to load rooms');
            rooms = await res.json();
            renderRoomList();
        }

        function renderRoomList() {
            const ul = document.getElementById('roomList');
            ul.innerHTML = rooms.map(r => `
                <li><button type="button" data-id="${r.id}" class="${selectedRoomId === r.id ? 'active' : ''}">${escapeHtml(r.name)} (${r.id})</button></li>
            `).join('');
            ul.querySelectorAll('button').forEach(btn => {
                btn.addEventListener('click', () => selectRoom(parseInt(btn.dataset.id, 10)));
            });
        }

        function escapeHtml(s) {
            const div = document.createElement('div');
            div.textContent = s;
            return div.innerHTML;
        }

        function selectRoom(id) {
            selectedRoomId = id;
            renderRoomList();
            document.getElementById('contentEmpty').style.display = 'none';
            document.getElementById('contentRoom').style.display = 'flex';
            document.getElementById('contentRoom').style.flexDirection = 'column';
            const room = rooms.find(r => r.id === id);
            document.getElementById('roomTitle').textContent = room ? room.name : 'Room ' + id;
            document.getElementById('roomMeta').textContent = 'Room ID: ' + id;
            loadHistory();
            loadAnalytics();
        }

        async function loadHistory() {
            if (!selectedRoomId) return;
            const res = await fetch(api + '/rooms/' + selectedRoomId + '/history');
            if (!res.ok) return;
            const messages = await res.json();
            const el = document.getElementById('messageArea');
            if (!messages.length) {
                el.innerHTML = '<div class="history"><p style="color:#64748b;font-size:0.875rem;">No messages yet.</p></div>';
                return;
            }
            el.innerHTML = '<div class="history">' + messages.map(m => `
                <div class="message ${m.content && m.content.includes('Scheduled') ? 'scheduled' : ''}">
                    <div class="msg-meta">User ${m.user_id} · ${new Date(m.timestamp || m.created_at).toLocaleString()}</div>
                    <div class="msg-content">${escapeHtml(m.content)}</div>
                </div>
            `).join('') + '</div>';
        }

        async function loadAnalytics() {
            if (!selectedRoomId) return;
            const res = await fetch(api + '/analytics/room/' + selectedRoomId);
            if (!res.ok) return;
            const data = await res.json();
            document.getElementById('analyticsBox').innerHTML = '<strong>Message count:</strong> ' + (data.message_count ?? '—');
        }

        function showFormMessage(msg, isError) {
            const box = document.getElementById('formErrors');
            box.innerHTML = '<div class="' + (isError ? 'error' : 'success') + '">' + escapeHtml(msg) + '</div>';
            setTimeout(() => { box.innerHTML = ''; }, 4000);
        }

        document.getElementById('formNewRoom').addEventListener('submit', async (e) => {
            e.preventDefault();
            const name = document.getElementById('newRoomName').value.trim();
            if (!name) return;
            try {
                const res = await fetch(api + '/rooms', {
                    method: 'POST',
                    headers: headers(),
                    body: JSON.stringify({ name })
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const msg = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Failed to create room');
                    showFormMessage(msg, true);
                    return;
                }
                document.getElementById('newRoomName').value = '';
                showFormMessage('Room created.');
                await loadRooms();
                selectRoom(data.id);
            } catch (err) {
                showFormMessage(err.message || 'Request failed', true);
            }
        });

        document.getElementById('btnSend').addEventListener('click', async () => {
            if (!selectedRoomId) return;
            const user_id = parseInt(document.getElementById('sendUserId').value, 10);
            const content = document.getElementById('sendContent').value.trim();
            if (!content) { showFormMessage('Enter message content.', true); return; }
            try {
                const res = await fetch(api + '/rooms/' + selectedRoomId + '/message', {
                    method: 'POST',
                    headers: headers(),
                    body: JSON.stringify({ user_id, content })
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const msg = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Send failed');
                    showFormMessage(msg, true);
                    return;
                }
                document.getElementById('sendContent').value = '';
                showFormMessage('Message sent.');
                loadHistory();
                setTimeout(loadAnalytics, 1500);
            } catch (err) {
                showFormMessage(err.message || 'Request failed', true);
            }
        });

        document.getElementById('btnSchedule').addEventListener('click', async () => {
            if (!selectedRoomId) return;
            const user_id = parseInt(document.getElementById('scheduleUserId').value, 10);
            const content = document.getElementById('scheduleContent').value.trim();
            const delay_minutes = parseInt(document.getElementById('scheduleDelay').value, 10) || 1;
            if (!content) { showFormMessage('Enter message content.', true); return; }
            try {
                const res = await fetch(api + '/rooms/' + selectedRoomId + '/schedule', {
                    method: 'POST',
                    headers: headers(),
                    body: JSON.stringify({ user_id, content, delay_minutes })
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const msg = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Schedule failed');
                    showFormMessage(msg, true);
                    return;
                }
                document.getElementById('scheduleContent').value = '';
                showFormMessage('Message scheduled in ' + delay_minutes + ' min.');
            } catch (err) {
                showFormMessage(err.message || 'Request failed', true);
            }
        });

        document.getElementById('btnRefresh').addEventListener('click', () => {
            if (selectedRoomId) { loadHistory(); loadAnalytics(); }
        });

        document.getElementById('btnFailTest').addEventListener('click', async () => {
            if (!selectedRoomId) return;
            try {
                const res = await fetch(api + '/rooms/' + selectedRoomId + '/message', {
                    method: 'POST',
                    headers: headers(),
                    body: JSON.stringify({ user_id: 1, content: 'This is a fail-test message' })
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    showFormMessage('Fail-test message sent. Jobs will retry then land in failed_jobs. Run: php artisan nats-chat:failed-jobs', false);
                } else {
                    showFormMessage(data.message || 'Request failed', true);
                }
            } catch (err) {
                showFormMessage(err.message || 'Request failed', true);
            }
        });

        loadRooms();
    </script>
</body>
</html>
