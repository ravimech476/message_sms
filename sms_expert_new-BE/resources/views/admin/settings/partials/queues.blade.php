{{-- Queues tab: per-topic pending message counts from RabbitMQ. The "View" link
     opens a dedicated page (admin.settings.queue-view) listing that queue's
     messages. Counts load on demand via AJAX so a slow/unreachable RabbitMQ never
     blocks the Settings page. --}}
<div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
    <div>
        <h5 class="mb-1"><i class="material-icons-outlined me-1 align-middle">stacked_bar_chart</i>Queue Status (topic-wise)</h5>
        <small class="text-muted">Pending = messages waiting in the queue (not yet consumed). Updated <span id="queueCheckedAt">—</span></small>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary fs-6" id="queueTotalBadge">Total pending: —</span>
        <div class="form-check form-switch d-flex align-items-center mb-0 ms-2 ps-0">
            <input class="form-check-input mt-0 me-2" type="checkbox" role="switch" id="queueAutoRefresh"
                   style="margin-left:0 !important; cursor:pointer;">
            <label class="form-check-label small mb-0" for="queueAutoRefresh" style="cursor:pointer;">Auto (10s)</label>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary" id="queueRefreshBtn">
            <i class="material-icons-outlined font-18 align-middle">refresh</i> Refresh
        </button>
    </div>
</div>

<div id="queueAlert" class="alert alert-danger d-none" role="alert"></div>

<div class="table-responsive">
    <table class="table table-bordered table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th style="width:30%">Topic</th>
                <th>Queue</th>
                <th class="text-end" style="width:120px">Pending</th>
                <th class="text-end" style="width:120px">Consumers</th>
                <th class="text-center" style="width:120px">Status</th>
                <th class="text-center" style="width:90px">Messages</th>
            </tr>
        </thead>
        <tbody id="queueTableBody">
            <tr><td colspan="6" class="text-center text-muted py-4">
                <span class="spinner-border spinner-border-sm me-2"></span> Loading queue status…
            </td></tr>
        </tbody>
    </table>
</div>

<script>
(function () {
    const statusUrl   = "{{ route('admin.settings.queue-status') }}";
    const queueViewUrl = "{{ route('admin.settings.queue-view') }}";
    const tbody = document.getElementById('queueTableBody');
    const alertBox = document.getElementById('queueAlert');
    const totalBadge = document.getElementById('queueTotalBadge');
    const checkedAt = document.getElementById('queueCheckedAt');
    const refreshBtn = document.getElementById('queueRefreshBtn');
    const autoChk = document.getElementById('queueAutoRefresh');
    let timer = null;

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function render(data) {
        alertBox.classList.add('d-none');
        if (!data.success) {
            alertBox.textContent = data.message || 'Could not load queue status.';
            alertBox.classList.remove('d-none');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No data</td></tr>';
            totalBadge.textContent = 'Total pending: —';
            return;
        }
        const rows = (data.queues || []).map(q => {
            const pending = Number(q.messages) || 0;
            const pendCls = pending > 0 ? 'text-danger fw-bold' : 'text-muted';
            const consCls = (Number(q.consumers) || 0) > 0 ? 'text-success' : 'text-danger';
            const status = !q.exists
                ? '<span class="badge bg-secondary">not declared</span>'
                : ((Number(q.consumers) || 0) > 0
                    ? '<span class="badge bg-success">consuming</span>'
                    : '<span class="badge bg-warning text-dark">no consumer</span>');
            const viewBtn = (q.exists && pending > 0)
                ? `<a href="${queueViewUrl}?queue=${encodeURIComponent(q.queue)}" class="btn btn-sm btn-outline-secondary">View</a>`
                : `<button type="button" class="btn btn-sm btn-outline-secondary" disabled>View</button>`;
            return `<tr>
                <td>${esc(q.label)}</td>
                <td><code>${esc(q.queue)}</code></td>
                <td class="text-end ${pendCls}">${pending.toLocaleString()}</td>
                <td class="text-end ${consCls}">${(Number(q.consumers)||0).toLocaleString()}</td>
                <td class="text-center">${status}</td>
                <td class="text-center">${viewBtn}</td>
            </tr>`;
        });
        tbody.innerHTML = rows.length ? rows.join('') : '<tr><td colspan="6" class="text-center text-muted py-4">No queues</td></tr>';
        totalBadge.textContent = 'Total pending: ' + (Number(data.total_pending) || 0).toLocaleString();
        checkedAt.textContent = data.checked_at || '—';
    }

    function load() {
        refreshBtn.disabled = true;
        fetch(statusUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(render)
            .catch(err => {
                alertBox.textContent = 'Request failed: ' + err;
                alertBox.classList.remove('d-none');
            })
            .finally(() => { refreshBtn.disabled = false; });
    }

    refreshBtn.addEventListener('click', load);
    autoChk.addEventListener('change', function () {
        if (this.checked) { timer = setInterval(load, 10000); } else { clearInterval(timer); timer = null; }
    });

    // Load when the Queues tab is visible (server-side tab: pane has show/active on load).
    const pane = document.getElementById('queues');
    if (pane && pane.classList.contains('active')) {
        load();
    } else {
        let loaded = false;
        document.querySelectorAll('a[href*="tab=queues"]').forEach(a =>
            a.addEventListener('click', () => { if (!loaded) { loaded = true; setTimeout(load, 300); } }));
    }
})();
</script>
