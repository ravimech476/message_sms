@php
    $au = $adminUser ?? Session::get('admin_user');
    $adminEmailDefault = $au['email'] ?? ($au['contactemail'] ?? '');
    $curYm     = now()->format('Y-m');
    $lastYrYm  = now()->subYear()->format('Y-m');

    // Earliest invoice month — the From/To calendars start here so they don't show
    // empty years before any invoice exists.
    $minPaid = \Illuminate\Support\Facades\DB::table('invoices')->where('paiddate', '>', 0)->min('paiddate');
    $minInv  = \Illuminate\Support\Facades\DB::table('invoices')->where('invoicedate', '>', 0)->min('invoicedate');
    $earliestCandidates = array_filter([$minPaid, $minInv]);
    $earliestTs = !empty($earliestCandidates) ? min($earliestCandidates) : strtotime('-5 years');
    $earliestYm = date('Y-m', (int) $earliestTs);
    if ($lastYrYm < $earliestYm) { $lastYrYm = $earliestYm; } // keep default From in range
@endphp

<style>
    .ms-invoice-link { color: #7b2ff7; font-weight: 600; text-decoration: none; }
    .ms-invoice-link:hover { color: #5a1bc0; text-decoration: underline; }
</style>

<h5 class="mb-1"><i class="material-icons-outlined me-1 align-middle">schedule_send</i>Sales Report</h5>
<p class="text-muted small mb-3">Pick a <strong>From</strong> and <strong>To</strong> month (use the calendar to change month/year), and optionally customers. Click <strong>Show Report</strong> to preview, or <strong>Generate Report</strong> to build an Excel in the background — we email you a download link and it appears in <strong>Report History</strong> below.</p>

<div class="ar-alert alert d-none mb-3" role="alert"></div>

<form class="ar-form row g-3" data-type="monthly_sales">
    @csrf
    <input type="hidden" name="report_type" value="monthly_sales">
    <input type="hidden" class="ar-from" name="date_from" value="">
    <input type="hidden" class="ar-to" name="date_to" value="">
    <input type="hidden" class="ar-search" name="search" value="">

    <div class="col-md-4">
        <label class="form-label">From (Month &amp; Year)</label>
        <input type="month" class="form-control" id="msFrom" min="{{ $earliestYm }}" max="{{ $curYm }}" value="{{ $lastYrYm }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">To (Month &amp; Year)</label>
        <input type="month" class="form-control" id="msTo" min="{{ $earliestYm }}" max="{{ $curYm }}" value="{{ $curYm }}">
    </div>
    <div class="col-md-4 d-flex align-items-end">
        <div id="msFilterError" class="text-danger small d-none"></div>
    </div>

    <div class="col-md-6">
        <label class="form-label d-block mb-1">Status <span class="text-muted small">(all if none ticked)</span></label>
        <div class="d-flex flex-wrap gap-3">
            <div class="form-check"><input class="form-check-input ms-status" type="checkbox" value="paid" id="msStPaid" checked><label class="form-check-label" for="msStPaid">Paid</label></div>
            <div class="form-check"><input class="form-check-input ms-status" type="checkbox" value="notyetpaid" id="msStNot" checked><label class="form-check-label" for="msStNot">Not yet paid</label></div>
            <div class="form-check"><input class="form-check-input ms-status" type="checkbox" value="deleted" id="msStDel" checked><label class="form-check-label" for="msStDel">Deleted</label></div>
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label d-block mb-1">Payment Method <span class="text-muted small">(all if none ticked)</span></label>
        <div class="d-flex flex-wrap gap-3">
            <div class="form-check"><input class="form-check-input ms-method" type="checkbox" value="PAYPAL" id="msMethPp" checked><label class="form-check-label" for="msMethPp">PayPal / Card</label></div>
            <div class="form-check"><input class="form-check-input ms-method" type="checkbox" value="BACS" id="msMethBacs" checked><label class="form-check-label" for="msMethBacs">BACS</label></div>
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label">Report name <span class="text-muted small">(auto-filled — you can edit)</span></label>
        <input type="text" class="form-control ar-name" name="report_name" maxlength="150" data-typelabel="Sales Report">
    </div>
    <div class="col-md-6">
        <label class="form-label">Customers <span class="text-muted small">(optional — all if blank)</span></label>
        <select class="form-select ar-customers" id="msCustomers" name="customer_ids[]" multiple>
            @isset($customers)
                @foreach ($customers as $c)
                    @php $cn = trim(urldecode($c->busname ?? '')) ?: trim(urldecode($c->contactname ?? '')) ?: ($c->uname ?? ('#' . $c->id)); @endphp
                    <option value="{{ $c->id }}">{{ $cn }}</option>
                @endforeach
            @endisset
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Notify email</label>
        <input type="email" class="form-control ar-email" name="email" value="{{ $adminEmailDefault }}" required placeholder="you@company.com">
        <small class="text-muted d-block mt-1"><i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">mail</i> Once the report is ready, you will receive an email with a download link.</small>
    </div>

    <div class="col-12 d-flex gap-2">
        <button type="button" id="msShow" class="btn btn-outline-primary">
            <i class="material-icons-outlined font-18 align-middle">search</i> Show Report
        </button>
        <button type="submit" class="btn btn-primary">
            <i class="material-icons-outlined font-18 align-middle">send</i> Generate Report
        </button>
    </div>
</form>

<hr class="my-3">

<div id="monthlySalesContainer">
    <div class="text-center text-muted py-4">Choose From / To and click <strong>Show Report</strong> to preview.</div>
</div>

<script>
(function () {
    const dataUrl  = "{{ route('admin.reports.monthly-sales.data') }}";
    // Base for the customer admin page; '__ID__' is swapped for the real users.id per row.
    const userUrlTpl = "{{ route('admin.user.show', ['id' => '__ID__']) }}";
    const customerUrl = id => userUrlTpl.replace('__ID__', encodeURIComponent(id));
    const root     = document.getElementById('monthly-sales');
    const fromMonthEl = document.getElementById('msFrom');
    const toMonthEl   = document.getElementById('msTo');
    const fromEl = root.querySelector('.ar-from'), toEl = root.querySelector('.ar-to');
    const nameEl = root.querySelector('.ar-name');
    const custEl = document.getElementById('msCustomers');
    const statusEls = root.querySelectorAll('.ms-status');
    const methodEls = root.querySelectorAll('.ms-method');
    const searchEl = root.querySelector('.ar-search');
    const errEl  = document.getElementById('msFilterError');
    const container = document.getElementById('monthlySalesContainer');
    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    const money = n => '£' + Number(n||0).toLocaleString('en-GB',{minimumFractionDigits:2,maximumFractionDigits:2});
    const esc = s => String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    function statusBadge(st){ if(st==='paid')return '<span class="badge bg-success">Paid</span>'; if(st==='notyetpaid')return '<span class="badge bg-warning text-dark">Not yet paid</span>'; if(st==='deleted')return '<span class="badge bg-danger">Deleted</span>'; return '<span class="badge bg-secondary">'+esc(st)+'</span>'; }

    const now = new Date();
    const curVal = now.getFullYear() * 100 + (now.getMonth() + 1);
    const todayStr = now.toISOString().slice(0,10);

    const mv  = ym => { const p=(ym||'').split('-'); return parseInt(p[0],10)*100 + parseInt(p[1],10); };          // 'YYYY-MM' -> YYYYMM
    const lbl = ym => { const p=(ym||'').split('-'); return (monthNames[parseInt(p[1],10)-1]||'') + ' ' + p[0]; };

    // Validate (From <= current month, To >= From) + keep hidden dates + name in sync.
    function syncDates(){
        const fromYm = fromMonthEl.value, toYm = toMonthEl.value;
        const fromVal = mv(fromYm), toVal = mv(toYm);
        let err = '';
        if (!fromYm || !toYm) err = 'Please choose both a From and To month.';
        else if (fromVal > curVal) err = 'The "From" month cannot be after the current month.';
        else if (toVal < fromVal) err = 'The "To" month must be the same as or after the "From" month.';

        if (err) { errEl.textContent = err; errEl.classList.remove('d-none'); }
        else { errEl.classList.add('d-none'); }

        // Keep the To picker's minimum aligned to From.
        if (fromYm) toMonthEl.min = fromYm;

        let from = fromYm ? `${fromYm}-01` : todayStr;
        let to   = toYm   ? `${toYm}-01`   : todayStr;
        if (from > todayStr) from = todayStr;   // never send a future date to Generate
        if (to > todayStr)   to = todayStr;
        if (fromEl) fromEl.value = from;
        if (toEl)   toEl.value = to;

        const label = (fromYm && toYm) ? `${lbl(fromYm)} - ${lbl(toYm)}` : '';
        if (nameEl && label && (nameEl.value === '' || nameEl.dataset.auto === '1')) { nameEl.value = 'Sales Report ' + label; nameEl.dataset.auto = '1'; }

        return { from, to, valid: err === '' };
    }
    if (nameEl) nameEl.addEventListener('input', () => { nameEl.dataset.auto = '0'; });
    [fromMonthEl, toMonthEl].forEach(el => el.addEventListener('change', syncDates));
    syncDates();

    function selectedCustomers(){ return custEl ? Array.from(custEl.selectedOptions).map(o => o.value) : []; }
    function selectedStatuses(){ return Array.from(statusEls).filter(c => c.checked).map(c => c.value); }
    function selectedMethods(){ return Array.from(methodEls).filter(c => c.checked).map(c => c.value); }
    // Carry status + payment-method filters into the background Generate via the
    // form's `search` field as JSON. All-ticked (or none) in a group = no filter.
    function updateSearch(){
        if (!searchEl) return;
        const s = selectedStatuses(), m = selectedMethods();
        const st = (s.length === 0 || s.length === statusEls.length) ? [] : s;
        const mt = (m.length === 0 || m.length === methodEls.length) ? [] : m;
        searchEl.value = (st.length || mt.length) ? JSON.stringify({ statuses: st, methods: mt }) : '';
    }
    statusEls.forEach(c => c.addEventListener('change', updateSearch));
    methodEls.forEach(c => c.addEventListener('change', updateSearch));
    updateSearch();

    function render(monthsData){
        if (!monthsData || !monthsData.length){ container.innerHTML = '<div class="text-center text-muted py-4">No sales found for the selected filters.</div>'; return; }
        let html = '';
        monthsData.forEach((m, idx) => {
            const id = 'msM' + idx;
            const invoiceCell = l => l.customer_id
                ? `<a href="${customerUrl(l.customer_id)}" target="_blank" rel="noopener" class="ms-invoice-link" title="Open customer in a new tab">${esc(l.invoiceref)}</a>`
                : esc(l.invoiceref);
            const rows = m.lines.length
                ? m.lines.map(l => `<tr><td>${invoiceCell(l)}</td><td>${esc(l.date)}</td><td>${esc(l.customer)}</td><td>${esc(l.method)}</td><td>${statusBadge(l.status)}</td><td class="text-end">${money(l.amount)}</td></tr>`).join('')
                : '<tr><td colspan="6" class="text-center text-muted">No invoices this month.</td></tr>';
            html += `<div class="card mb-3 border"><div class="card-header d-flex flex-wrap align-items-center justify-content-between" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#${id}"><div><strong>${esc(m.month)}</strong> <small class="text-muted">(${m.count} invoice${m.count==1?'':'s'})</small></div><div class="d-flex flex-wrap gap-3 small"><span><strong>Total:</strong> ${money(m.total)}</span><span class="text-muted">PayPal/Card: ${money(m.paypal_total)}</span><span class="text-muted">BACS: ${money(m.bacs_total)}</span><span class="text-warning">Not yet paid: ${money(m.notyetpaid_total)}</span></div></div><div id="${id}" class="collapse ${idx===0?'show':''}"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Invoice #</th><th>Date</th><th>Customer</th><th>Method</th><th>Status</th><th class="text-end">Amount (ex-VAT)</th></tr></thead><tbody>${rows}</tbody></table></div></div></div>`;
        });
        container.innerHTML = html;
    }

    function show(){
        const r = syncDates();
        if (!r.valid){ container.innerHTML = '<div class="text-danger text-center py-4">' + esc(errEl.textContent) + '</div>'; return; }
        const params = new URLSearchParams();
        params.set('date_from', r.from);
        params.set('date_to', r.to);
        selectedCustomers().forEach(c => params.append('customer_ids[]', c));
        selectedStatuses().forEach(s => params.append('statuses[]', s));
        selectedMethods().forEach(m => params.append('methods[]', m));
        container.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border text-secondary" role="status"></div><div class="mt-2">Loading&hellip;</div></div>';
        fetch(dataUrl + '?' + params.toString(), {headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r => r.json())
            .then(d => { d && d.success ? render(d.months) : (container.innerHTML = '<div class="text-danger text-center py-4">Failed to load.</div>'); })
            .catch(() => { container.innerHTML = '<div class="text-danger text-center py-4">Error loading.</div>'; });
    }
    document.getElementById('msShow').addEventListener('click', show);

    // Block the background Generate (shared .ar-form handler) when the range is invalid.
    root.querySelector('.ar-form').addEventListener('submit', function (e) {
        if (!syncDates().valid) { e.preventDefault(); e.stopImmediatePropagation(); errEl.classList.remove('d-none'); }
    }, true);
})();
</script>
