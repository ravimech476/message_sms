{{-- Async report generation: pick type + date range + name + email, queue it to
     RabbitMQ (reports:consume worker builds the file and emails a link), and a
     History panel lists past jobs with status + in-app download. --}}
@php
    $adminEmailDefault = $adminUser['email'] ?? ($adminUser['contactemail'] ?? '');
@endphp

<div class="card mb-3">
    <div class="card-body">
        <h5 class="mb-3"><i class="material-icons-outlined me-1 align-middle">schedule_send</i>Generate Report (background)</h5>
        <p class="text-muted small mb-3">Pick a report, date range, name and email. We generate it in the background and email a download link when it's ready — it also appears in History below.</p>

        <div id="arAlert" class="alert d-none" role="alert"></div>

        <form id="arForm" class="row g-3">
            @csrf
            <div class="col-md-3">
                <label class="form-label">Report type</label>
                <select class="form-select" name="report_type" id="arType" required>
                    <option value="postpay">Post Pay Report</option>
                    <option value="daily_sms">Daily SMS Report</option>
                    <option value="money_transfer">Money Transferred Report</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Date from</label>
                <input type="date" class="form-control" name="date_from" id="arFrom" value="{{ now()->format('Y-m-d') }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Date to</label>
                <input type="date" class="form-control" name="date_to" id="arTo" value="{{ now()->format('Y-m-d') }}" required>
            </div>
            <div class="col-md-5">
                <label class="form-label">Report name <span class="text-muted small">(optional — auto-named if blank)</span></label>
                <input type="text" class="form-control" name="report_name" id="arName" maxlength="150" placeholder="e.g. Post Pay 01 Jun 2026 - 09 Jun 2026">
            </div>

            <div class="col-md-5">
                <label class="form-label">Customers <span class="text-muted small">(optional — all if blank)</span></label>
                <select class="form-select" name="customer_ids[]" id="arCustomers" multiple>
                    @isset($customers)
                        @foreach ($customers as $c)
                            @php $cn = trim(urldecode($c->busname ?? '')) ?: trim(urldecode($c->contactname ?? '')) ?: ($c->uname ?? ('#' . $c->id)); @endphp
                            <option value="{{ $c->id }}">{{ $cn }}</option>
                        @endforeach
                    @endisset
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Notify email</label>
                <input type="email" class="form-control" name="email" id="arEmail" value="{{ $adminEmailDefault }}" required placeholder="you@company.com">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100" id="arSubmit">
                    <i class="material-icons-outlined font-18 align-middle">send</i> Generate &amp; email
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0"><i class="material-icons-outlined me-1 align-middle">history</i>Report History</h6>
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small" id="arHistUpdated"></span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="arHistRefresh"><i class="material-icons-outlined font-18 align-middle">refresh</i></button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Date range</th>
                        <th>Email</th>
                        <th class="text-center">Status</th>
                        <th>Requested</th>
                        <th class="text-center">Download</th>
                    </tr>
                </thead>
                <tbody id="arHistBody">
                    <tr><td colspan="7" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-2"></span> Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    const requestUrl = "{{ route('admin.reports.request') }}";
    const historyUrl = "{{ route('admin.reports.history') }}";
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
              || document.querySelector('#arForm input[name="_token"]')?.value || '';

    const form = document.getElementById('arForm');
    const alertBox = document.getElementById('arAlert');
    const submitBtn = document.getElementById('arSubmit');
    const histBody = document.getElementById('arHistBody');
    const histUpdated = document.getElementById('arHistUpdated');
    let pollTimer = null;

    function esc(s){return String(s ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

    function showAlert(type, msg) {
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = msg;
        alertBox.classList.remove('d-none');
    }

    const statusBadge = (s) => {
        const map = { pending:'secondary', processing:'info', ready:'success', failed:'danger' };
        return `<span class="badge bg-${map[s] || 'secondary'}">${esc(s)}</span>`;
    };

    function renderHistory(data) {
        if (!data.success) { histBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Could not load history</td></tr>'; return; }
        const rows = (data.data || []);
        histBody.innerHTML = rows.length ? rows.map(r => `
            <tr>
                <td>${esc(r.name)}</td>
                <td>${esc(r.type)}</td>
                <td>${esc(r.date_range)}</td>
                <td>${esc(r.email)}</td>
                <td class="text-center">${statusBadge(r.status)}${r.status==='failed' && r.error ? `<i class="material-icons-outlined text-danger ms-1" style="font-size:16px;cursor:help" title="${esc(r.error)}">error</i>` : ''}</td>
                <td>${esc(r.requested_at || '')}</td>
                <td class="text-center">${r.download_url ? `<a href="${r.download_url}" class="btn btn-sm btn-outline-secondary"><i class="material-icons-outlined font-18 align-middle">download</i></a>` : '<span class="text-muted">—</span>'}</td>
            </tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted py-3">No reports yet</td></tr>';
        histUpdated.textContent = 'Updated ' + new Date().toLocaleTimeString();

        // Keep polling while anything is pending/processing so status updates live.
        const busy = rows.some(r => r.status === 'pending' || r.status === 'processing');
        if (busy && !pollTimer) pollTimer = setInterval(loadHistory, 5000);
        if (!busy && pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    function loadHistory() {
        fetch(historyUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json()).then(renderHistory)
            .catch(() => { histBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Request failed</td></tr>'; });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        alertBox.classList.add('d-none');
        submitBtn.disabled = true;
        const fd = new FormData(form);
        fetch(requestUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: fd,
        })
        .then(async r => {
            const data = await r.json().catch(() => ({}));
            if (!r.ok) {
                const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Could not queue the report.');
                throw new Error(msg);
            }
            return data;
        })
        .then(data => {
            showAlert(data.queued === false ? 'warning' : 'success', data.message || 'Report queued.');
            form.querySelector('#arName').value = '';
            loadHistory();
        })
        .catch(err => showAlert('danger', err.message || 'Could not queue the report.'))
        .finally(() => { submitBtn.disabled = false; });
    });

    document.getElementById('arHistRefresh').addEventListener('click', loadHistory);

    // select2 for the customer picker if available (the page already loads it).
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('#arCustomers').select2({ theme: 'bootstrap-5', width: '100%', placeholder: 'All customers', closeOnSelect: false });
    }

    loadHistory();
})();
</script>
