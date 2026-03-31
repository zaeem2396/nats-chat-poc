<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order pipeline — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&family=jetbrains-mono:400,500" rel="stylesheet">
    <style>
        :root {
            --bg: #020617;
            --surface: rgba(15, 23, 42, 0.55);
            --border: rgba(30, 41, 59, 0.9);
            --text: #e2e8f0;
            --muted: #64748b;
            --accent: #34d399;
            --accent-dim: rgba(52, 211, 153, 0.25);
            --warn: #fbbf24;
            --danger: #f87171;
            --font: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            --mono: 'JetBrains Mono', ui-monospace, monospace;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            background-image: radial-gradient(ellipse 120% 80% at 50% -20%, rgba(16, 185, 129, 0.12), transparent);
        }
        .pl-wrap { max-width: 72rem; margin: 0 auto; padding: 0 1rem; }
        @media (min-width: 640px) { .pl-wrap { padding: 0 1.5rem; } }
        .pl-header {
            border-bottom: 1px solid var(--border);
            background: rgba(2, 6, 23, 0.85);
            backdrop-filter: blur(12px);
        }
        .pl-header__inner {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
            gap: 1rem; padding: 1rem 0;
        }
        .pl-kicker { font-size: 0.7rem; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--accent); opacity: 0.9; }
        .pl-title { margin: 0.15rem 0 0; font-size: 1.35rem; font-weight: 600; letter-spacing: -0.02em; color: #fff; }
        @media (min-width: 640px) { .pl-title { font-size: 1.5rem; } }
        .pl-actions { display: flex; align-items: center; gap: 0.75rem; }
        .pl-health {
            display: flex; align-items: center; gap: 0.5rem;
            padding: 0.35rem 0.85rem; border-radius: 9999px;
            border: 1px solid var(--border); background: rgba(15, 23, 42, 0.8);
        }
        .pl-dot { width: 8px; height: 8px; border-radius: 50%; background: #475569; }
        .pl-dot--ok { background: var(--accent); box-shadow: 0 0 8px rgba(52, 211, 153, 0.55); }
        .pl-dot--warn { background: var(--warn); }
        .pl-health span:last-child { font-size: 0.75rem; color: var(--muted); }
        .pl-btn {
            border: 1px solid #334155; background: rgba(30, 41, 59, 0.5);
            color: var(--text); padding: 0.4rem 0.85rem; border-radius: 0.5rem;
            font-size: 0.875rem; font-weight: 500; cursor: pointer; font-family: inherit;
        }
        .pl-btn:hover { border-color: rgba(52, 211, 153, 0.45); background: rgba(30, 41, 59, 0.85); color: #fff; }
        .pl-main { padding: 2rem 0 3rem; display: flex; flex-direction: column; gap: 2rem; }
        .pl-grid4 { display: grid; gap: 1rem; grid-template-columns: 1fr; }
        @media (min-width: 640px) { .pl-grid4 { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 1024px) { .pl-grid4 { grid-template-columns: repeat(4, 1fr); } }
        .pl-card {
            border: 1px solid var(--border); border-radius: 0.75rem; padding: 1.25rem;
            background: var(--surface); box-shadow: 0 12px 40px rgba(0,0,0,0.25);
        }
        .pl-card__label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); margin: 0; }
        .pl-card__value { margin: 0.35rem 0 0; font-size: 1.75rem; font-weight: 600; color: #fff; font-variant-numeric: tabular-nums; }
        .pl-card__value--warn { color: #fde68a; }
        .pl-card__sub { margin: 0.5rem 0 0; font-size: 0.75rem; line-height: 1.45; color: var(--muted); }
        .pl-split { display: grid; gap: 2rem; }
        @media (min-width: 1024px) { .pl-split { grid-template-columns: 2fr 3fr; align-items: start; } }
        .pl-card h2 { margin: 0; font-size: 1.05rem; font-weight: 600; color: #fff; }
        .pl-card .pl-desc { margin: 0.35rem 0 0; font-size: 0.8rem; color: var(--muted); line-height: 1.5; }
        .pl-card code { font-family: var(--mono); font-size: 0.7rem; padding: 0.15rem 0.4rem; border-radius: 0.25rem; background: rgba(15, 23, 42, 0.9); color: rgba(52, 211, 153, 0.85); }
        .pl-form { margin-top: 1.25rem; display: flex; flex-direction: column; gap: 1rem; }
        .pl-field label { display: block; font-size: 0.7rem; font-weight: 500; color: var(--muted); margin-bottom: 0.25rem; }
        .pl-field input {
            width: 100%; padding: 0.5rem 0.65rem; border-radius: 0.5rem; border: 1px solid #334155;
            background: var(--bg); color: #fff; font-size: 0.875rem; font-family: inherit;
        }
        .pl-field input:focus { outline: none; border-color: rgba(52, 211, 153, 0.5); box-shadow: 0 0 0 2px var(--accent-dim); }
        .pl-row2 { display: grid; gap: 0.75rem; grid-template-columns: 1fr 1fr; }
        .pl-submit {
            width: 100%; margin-top: 0.25rem; padding: 0.65rem; border: none; border-radius: 0.5rem;
            background: #059669; color: #fff; font-weight: 600; font-size: 0.875rem; cursor: pointer; font-family: inherit;
            box-shadow: 0 8px 24px rgba(5, 150, 105, 0.25);
        }
        .pl-submit:hover { background: #10b981; }
        .pl-form-status { margin: 0; font-size: 0.8rem; color: var(--muted); min-height: 1.25em; }
        .pl-form-status--ok { color: var(--accent); }
        .pl-form-status--err { color: var(--danger); }
        .pl-detail { display: none; border: 1px solid rgba(6, 78, 59, 0.45); border-radius: 0.75rem; padding: 1rem; background: rgba(15, 23, 42, 0.65); margin-bottom: 1rem; }
        .pl-detail.pl-detail--open { display: block; }
        .pl-detail__head { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; }
        .pl-detail__head h3 { margin: 0; font-size: 0.8rem; font-weight: 600; color: rgba(52, 211, 153, 0.9); }
        .pl-detail__close { border: none; background: none; color: var(--muted); font-size: 0.7rem; cursor: pointer; font-family: inherit; }
        .pl-detail__close:hover { color: #fff; }
        .pl-detail pre {
            margin: 0.75rem 0 0; padding: 0.75rem; border-radius: 0.5rem; background: var(--bg);
            font-family: var(--mono); font-size: 0.7rem; line-height: 1.5; overflow: auto; max-height: 12rem; color: #cbd5e1;
        }
        .pl-table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 0.75rem; background: var(--surface); box-shadow: 0 12px 40px rgba(0,0,0,0.2); }
        .pl-table-head { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); }
        .pl-table-head h2 { margin: 0; font-size: 1rem; font-weight: 600; color: #fff; }
        .pl-table-head p { margin: 0.2rem 0 0; font-size: 0.7rem; color: var(--muted); }
        .pl-table-head code { font-family: var(--mono); font-size: 0.65rem; color: #94a3b8; }
        table.pl-table { width: 100%; min-width: 36rem; border-collapse: collapse; font-size: 0.875rem; }
        .pl-table th {
            text-align: left; padding: 0.65rem 1rem; font-size: 0.65rem; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted);
            background: rgba(15, 23, 42, 0.85); border-bottom: 1px solid var(--border);
        }
        .pl-table th.pl-num { text-align: right; }
        .pl-table td { padding: 0.65rem 1rem; border-bottom: 1px solid rgba(30, 41, 59, 0.6); color: #cbd5e1; }
        .pl-table td.pl-num { text-align: right; font-variant-numeric: tabular-nums; }
        .pl-table td.pl-mono { font-family: var(--mono); font-size: 0.7rem; color: #94a3b8; }
        .pl-table tr.pl-tr--click { cursor: pointer; transition: background 0.12s; }
        .pl-table tr.pl-tr--click:hover { background: rgba(30, 41, 59, 0.35); }
        .pl-badge { display: inline-block; padding: 0.15rem 0.45rem; border-radius: 0.35rem; background: rgba(30, 41, 59, 0.95); font-size: 0.7rem; color: rgba(52, 211, 153, 0.9); }
        .pl-td-center { text-align: center; padding: 2rem 1rem; color: var(--muted); }
        .pl-td-center--err { color: var(--danger); }
        .pl-foot { border-top: 1px solid var(--border); padding-top: 1.5rem; text-align: center; font-size: 0.7rem; color: #475569; }
        .pl-foot code { font-family: var(--mono); font-size: 0.65rem; color: #64748b; }
    </style>
</head>
<body>
    <header class="pl-header">
        <div class="pl-wrap pl-header__inner">
            <div>
                <p class="pl-kicker">NATS JetStream</p>
                <h1 class="pl-title">Order pipeline</h1>
            </div>
            <div class="pl-actions">
                <div class="pl-health">
                    <span id="health-dot" class="pl-dot"></span>
                    <span id="health-label">Checking…</span>
                </div>
                <button type="button" class="pl-btn" id="btn-refresh">Refresh</button>
            </div>
        </div>
    </header>

    <main class="pl-wrap pl-main">
        <section class="pl-grid4">
            <article class="pl-card">
                <p class="pl-card__label">Orders</p>
                <p class="pl-card__value" id="metric-orders">—</p>
                <p class="pl-card__sub" id="metric-orders-breakdown">—</p>
            </article>
            <article class="pl-card">
                <p class="pl-card__label">Payments</p>
                <p class="pl-card__value" id="metric-payments">—</p>
                <p class="pl-card__sub" id="metric-payments-breakdown">—</p>
            </article>
            <article class="pl-card">
                <p class="pl-card__label">Notifications</p>
                <p class="pl-card__value" id="metric-notifications">—</p>
            </article>
            <article class="pl-card">
                <p class="pl-card__label">DLQ rows</p>
                <p class="pl-card__value pl-card__value--warn" id="metric-dlq">—</p>
            </article>
        </section>

        <div class="pl-split">
            <section class="pl-card">
                <h2>New order</h2>
                <p class="pl-desc">POSTs to <code>/api/orders</code> and publishes to JetStream.</p>
                <form id="order-form" class="pl-form">
                    <div class="pl-field">
                        <label for="user_id">User ID</label>
                        <input type="number" name="user_id" id="user_id" value="1" min="1" required>
                    </div>
                    <div class="pl-field">
                        <label for="sku">SKU</label>
                        <input type="text" name="sku" id="sku" value="DEMO-SKU" maxlength="64" required>
                    </div>
                    <div class="pl-row2">
                        <div class="pl-field">
                            <label for="quantity">Quantity</label>
                            <input type="number" name="quantity" id="quantity" value="1" min="1" required>
                        </div>
                        <div class="pl-field">
                            <label for="total_cents">Total (¢)</label>
                            <input type="number" name="total_cents" id="total_cents" value="1999" min="1" required>
                        </div>
                    </div>
                    <button type="submit" class="pl-submit">Place order</button>
                    <p class="pl-form-status" id="form-status"></p>
                </form>
            </section>

            <div>
                <div id="order-detail" class="pl-detail">
                    <div class="pl-detail__head">
                        <h3>Order detail (API)</h3>
                        <button type="button" class="pl-detail__close" id="btn-close-detail">Close</button>
                    </div>
                    <pre id="order-detail-json"></pre>
                </div>

                <div class="pl-table-wrap">
                    <div class="pl-table-head">
                        <h2>Recent orders</h2>
                        <p>Click a row for JSON from <code>GET /api/orders/{id}</code></p>
                    </div>
                    <table class="pl-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>SKU</th>
                                <th class="pl-num">Qty</th>
                                <th class="pl-num">Total</th>
                                <th>Status</th>
                                <th>Pipeline</th>
                            </tr>
                        </thead>
                        <tbody id="orders-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <section class="pl-table-wrap">
            <div class="pl-table-head">
                <h2>Dead letter queue</h2>
                <p>From <code>GET /api/dlq</code></p>
            </div>
            <table class="pl-table" style="min-width: 32rem">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Subject</th>
                        <th>Reason</th>
                        <th class="pl-num">Attempts</th>
                        <th>Failed</th>
                    </tr>
                </thead>
                <tbody id="dlq-tbody"></tbody>
            </table>
        </section>

        <footer class="pl-foot">
            Workers: <code>nats:orders-worker</code>, <code>nats:payments-worker</code>, …
        </footer>
    </main>

    <script>
        const api = (path, options = {}) =>
            fetch(path, {
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...options.headers },
                ...options,
            });

        function setText(id, text) {
            const el = document.getElementById(id);
            if (el) el.textContent = text;
        }

        function setHealth(ok, natsOk) {
            const dot = document.getElementById('health-dot');
            const label = document.getElementById('health-label');
            if (!dot || !label) return;
            const healthy = ok && natsOk;
            dot.className = 'pl-dot' + (healthy ? ' pl-dot--ok' : ok ? ' pl-dot--warn' : '');
            label.textContent = healthy ? 'NATS reachable' : ok ? 'App up — NATS unreachable' : 'Degraded';
        }

        async function refreshHealth() {
            try {
                const r = await api('/api/health');
                const j = await r.json();
                setHealth(r.ok, j.nats_v2_reachable === true);
            } catch {
                setHealth(false, false);
            }
        }

        async function refreshMetrics() {
            try {
                const r = await api('/api/metrics');
                if (!r.ok) throw new Error('metrics');
                const m = await r.json();
                setText('metric-orders', String(m.orders_total ?? '—'));
                setText('metric-payments', String(m.payments_total ?? '—'));
                setText('metric-notifications', String(m.notifications_total ?? '—'));
                setText('metric-dlq', String(m.failed_messages_total ?? '—'));
                const byStatus = m.orders_by_status || {};
                const parts = Object.entries(byStatus).map(([k, v]) => `${k}: ${v}`);
                setText('metric-orders-breakdown', parts.length ? parts.join(' · ') : '—');
                const pay = m.payments_by_status || {};
                const payParts = Object.entries(pay).map(([k, v]) => `${k}: ${v}`);
                setText('metric-payments-breakdown', payParts.length ? payParts.join(' · ') : '—');
            } catch {
                ['metric-orders', 'metric-payments', 'metric-notifications', 'metric-dlq', 'metric-orders-breakdown', 'metric-payments-breakdown'].forEach((id) => setText(id, '—'));
            }
        }

        function formatMoney(cents) {
            const n = Number(cents);
            if (Number.isNaN(n)) return '—';
            return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(n / 100);
        }

        function escapeHtml(s) {
            if (s == null) return '';
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        async function refreshOrders() {
            const tbody = document.getElementById('orders-tbody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="7" class="pl-td-center">Loading…</td></tr>';
            try {
                const r = await api('/api/orders');
                if (!r.ok) throw new Error('orders');
                const rows = await r.json();
                if (!Array.isArray(rows) || rows.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="pl-td-center">No orders yet. Create one with the form.</td></tr>';
                    return;
                }
                tbody.innerHTML = '';
                for (const o of rows) {
                    const tr = document.createElement('tr');
                    tr.className = 'pl-tr--click';
                    tr.innerHTML =
                        '<td class="pl-mono">' + o.id + '</td>' +
                        '<td>' + o.user_id + '</td>' +
                        '<td><strong style="font-weight:500;color:#f1f5f9">' + escapeHtml(o.sku) + '</strong></td>' +
                        '<td class="pl-num">' + o.quantity + '</td>' +
                        '<td class="pl-num">' + formatMoney(o.total_cents) + '</td>' +
                        '<td><span class="pl-badge">' + escapeHtml(o.status) + '</span></td>' +
                        '<td style="color:#94a3b8">' + (o.pipeline_stage ? escapeHtml(o.pipeline_stage) : '—') + '</td>';
                    tr.addEventListener('click', () => loadOrderDetail(o.id));
                    tbody.appendChild(tr);
                }
            } catch {
                tbody.innerHTML = '<tr><td colspan="7" class="pl-td-center pl-td-center--err">Failed to load orders.</td></tr>';
            }
        }

        async function loadOrderDetail(id) {
            const panel = document.getElementById('order-detail');
            const pre = document.getElementById('order-detail-json');
            if (!panel || !pre) return;
            panel.classList.add('pl-detail--open');
            pre.textContent = 'Loading…';
            try {
                const r = await api('/api/orders/' + id);
                const j = await r.json();
                pre.textContent = JSON.stringify(j, null, 2);
            } catch {
                pre.textContent = 'Failed to load order.';
            }
        }

        async function refreshDlq() {
            const tbody = document.getElementById('dlq-tbody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="5" class="pl-td-center">Loading…</td></tr>';
            try {
                const r = await api('/api/dlq?per_page=15');
                if (!r.ok) throw new Error('dlq');
                const page = await r.json();
                const rows = page.data || [];
                if (rows.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="pl-td-center">No DLQ rows.</td></tr>';
                    return;
                }
                tbody.innerHTML = '';
                for (const row of rows) {
                    const tr = document.createElement('tr');
                    const reason = row.error_reason || row.error_message || '—';
                    const rs = String(reason);
                    tr.innerHTML =
                        '<td class="pl-mono">' + row.id + '</td>' +
                        '<td>' + escapeHtml(row.subject || '—') + '</td>' +
                        '<td style="color:#94a3b8">' + escapeHtml(rs.slice(0, 80)) + (rs.length > 80 ? '…' : '') + '</td>' +
                        '<td class="pl-num" style="color:#94a3b8">' + (row.attempts ?? '—') + '</td>' +
                        '<td class="pl-mono" style="font-size:0.65rem">' + (row.failed_at || row.created_at || '—') + '</td>';
                    tbody.appendChild(tr);
                }
            } catch {
                tbody.innerHTML = '<tr><td colspan="5" class="pl-td-center pl-td-center--err">Failed to load DLQ.</td></tr>';
            }
        }

        async function submitOrder(ev) {
            ev.preventDefault();
            const form = ev.target;
            const status = document.getElementById('form-status');
            const payload = {
                user_id: parseInt(form.user_id.value, 10),
                sku: form.sku.value.trim(),
                quantity: parseInt(form.quantity.value, 10),
                total_cents: parseInt(form.total_cents.value, 10),
            };
            if (status) {
                status.textContent = 'Submitting…';
                status.className = 'pl-form-status';
            }
            try {
                const r = await api('/api/orders', { method: 'POST', body: JSON.stringify(payload) });
                const j = await r.json().catch(() => ({}));
                if (!r.ok) {
                    const msg = j.message || (j.errors ? JSON.stringify(j.errors) : 'HTTP ' + r.status);
                    throw new Error(msg);
                }
                if (status) {
                    status.textContent = 'Created order #' + j.id;
                    status.className = 'pl-form-status pl-form-status--ok';
                }
                form.reset();
                form.user_id.value = '1';
                form.sku.value = 'DEMO-SKU';
                form.quantity.value = '1';
                form.total_cents.value = '1999';
                await Promise.all([refreshOrders(), refreshMetrics(), refreshDlq()]);
            } catch (e) {
                if (status) {
                    status.textContent = e.message || 'Request failed';
                    status.className = 'pl-form-status pl-form-status--err';
                }
            }
        }

        async function refreshAll() {
            await Promise.all([refreshHealth(), refreshMetrics(), refreshOrders(), refreshDlq()]);
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('order-form')?.addEventListener('submit', submitOrder);
            document.getElementById('btn-refresh')?.addEventListener('click', () => refreshAll());
            document.getElementById('btn-close-detail')?.addEventListener('click', () => {
                document.getElementById('order-detail')?.classList.remove('pl-detail--open');
            });
            refreshAll();
            setInterval(refreshHealth, 15000);
            setInterval(refreshMetrics, 12000);
        });
    </script>
</body>
</html>
