<div class="monthly-sales-wrap">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
        <div>
            <h5 class="mb-1"><i class="bx bx-line-chart"></i> Monthly Sales</h5>
            <small class="text-muted">Invoice revenue per month (ex-VAT), split by payment method — ported from the legacy monthly-sales report.</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="mb-0 small">Months:</label>
            <select id="msMonths" class="form-select form-select-sm" style="width:auto;">
                <option value="6">Last 6</option>
                <option value="12" selected>Last 12</option>
                <option value="24">Last 24</option>
            </select>
            <button id="msReload" type="button" class="btn btn-sm btn-primary"><i class="bx bx-refresh"></i> Reload</button>
        </div>
    </div>

    <div id="monthlySalesContainer">
        <div class="text-center text-muted py-5">Loading monthly sales&hellip;</div>
    </div>
</div>

<script>
(function () {
    const dataUrl = "{{ route('admin.reports.monthly-sales.data') }}";
    const invDlBase = "{{ url('admin/invoice') }}";
    let loaded = false;

    const money = n => '£' + Number(n || 0).toLocaleString('en-GB', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    function statusBadge(st) {
        if (st === 'paid') return '<span class="badge bg-success">Paid</span>';
        if (st === 'notyetpaid') return '<span class="badge bg-warning text-dark">Not yet paid</span>';
        if (st === 'deleted') return '<span class="badge bg-danger">Deleted</span>';
        return '<span class="badge bg-secondary">' + esc(st) + '</span>';
    }

    function render(months) {
        const c = document.getElementById('monthlySalesContainer');
        if (!months || !months.length) {
            c.innerHTML = '<div class="text-center text-muted py-5">No sales data found.</div>';
            return;
        }
        let html = '';
        months.forEach((m, idx) => {
            const collapseId = 'msMonth' + idx;
            const rows = m.lines.length
                ? m.lines.map(l => `
                    <tr>
                      <td>${esc(l.invoiceref)}</td>
                      <td>${esc(l.date)}</td>
                      <td>${esc(l.customer)}</td>
                      <td>${esc(l.method)}</td>
                      <td>${statusBadge(l.status)}</td>
                      <td class="text-end">${money(l.amount)}</td>
                    </tr>`).join('')
                : '<tr><td colspan="6" class="text-center text-muted">No invoices this month.</td></tr>';
            html += `
            <div class="card mb-3 border">
              <div class="card-header d-flex flex-wrap align-items-center justify-content-between" style="cursor:pointer;"
                   data-bs-toggle="collapse" data-bs-target="#${collapseId}">
                <div><strong>${esc(m.month)}</strong> <small class="text-muted">(${m.count} invoice${m.count == 1 ? '' : 's'})</small></div>
                <div class="d-flex flex-wrap gap-3 small align-items-center">
                   <span><strong>Total:</strong> ${money(m.total)}</span>
                   <span class="text-muted">PayPal/Card: ${money(m.paypal_total)}</span>
                   <span class="text-muted">BACS: ${money(m.bacs_total)}</span>
                   <span class="text-warning">Not yet paid: ${money(m.notyetpaid_total)}</span>
                </div>
              </div>
              <div id="${collapseId}" class="collapse ${idx === 0 ? 'show' : ''}">
                <div class="table-responsive">
                  <table class="table table-sm mb-0">
                    <thead>
                      <tr><th>Invoice #</th><th>Date</th><th>Customer</th><th>Method</th><th>Status</th><th class="text-end">Amount (ex-VAT)</th></tr>
                    </thead>
                    <tbody>${rows}</tbody>
                  </table>
                </div>
              </div>
            </div>`;
        });
        c.innerHTML = html;
    }

    function load() {
        const c = document.getElementById('monthlySalesContainer');
        const months = document.getElementById('msMonths').value;
        c.innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border text-secondary" role="status"></div><div class="mt-2">Loading monthly sales&hellip;</div></div>';
        fetch(dataUrl + '?months=' + encodeURIComponent(months), {headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(r => r.json())
            .then(d => { d && d.success ? render(d.months) : (c.innerHTML = '<div class="text-danger text-center py-5">Failed to load monthly sales.</div>'); })
            .catch(() => { c.innerHTML = '<div class="text-danger text-center py-5">Error loading monthly sales.</div>'; });
        loaded = true;
    }

    const tabBtn = document.getElementById('monthly-sales-tab');
    if (tabBtn) {
        tabBtn.addEventListener('shown.bs.tab', () => { if (!loaded) load(); });
    }
    // Load immediately if this is the active tab on page load.
    if (document.querySelector('#monthly-sales.active')) {
        load();
    }
    document.getElementById('msReload').addEventListener('click', load);
    document.getElementById('msMonths').addEventListener('change', () => { if (loaded) load(); });
})();
</script>
