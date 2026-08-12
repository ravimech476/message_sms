@extends('admin.layouts.app')

@section('title', 'Livebeat - SMS Expert Admin')

@push('style')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    /* ---------- Livebeat report ---------- */
    .lb-page .select2-container--bootstrap-5 .select2-selection { border:1px solid #dfe3e8; border-radius:9px; min-height:38px; }
    .lb-page .select2-container { width:100% !important; }
    .lb-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:1rem; margin-bottom:1.25rem; }
    .lb-kpi { background:#fff; border:1px solid #eceef1; border-radius:14px; padding:1rem 1.15rem; display:flex; align-items:center; gap:.9rem; box-shadow:0 1px 3px rgba(16,24,40,.04); }
    .lb-kpi .ic { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .lb-kpi .ic i { font-size:22px; color:#fff; }
    .lb-kpi .lbl { font-size:.75rem; color:#8a94a6; font-weight:500; }
    .lb-kpi .val { font-size:1.3rem; font-weight:700; color:#293b50; line-height:1.2; }
    .ic-orange{background:linear-gradient(135deg,#ea6118,#d1520e);} .ic-blue{background:linear-gradient(135deg,#3b82f6,#2563eb);}
    .ic-green{background:linear-gradient(135deg,#10b981,#059669);} .ic-slate{background:linear-gradient(135deg,#64748b,#475569);}

    .lb-card { background:#fff; border:1px solid #eceef1; border-radius:14px; box-shadow:0 1px 3px rgba(16,24,40,.04); margin-bottom:1.25rem; }
    .lb-card-head { padding:1rem 1.25rem; border-bottom:1px solid #f0f1f4; display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
    .lb-card-head h5 { margin:0; font-size:1.02rem; font-weight:700; color:#293b50; display:flex; align-items:center; gap:.5rem; }
    .lb-card-head h5 i { color:#ea6118; }
    .lb-card-body { padding:1.25rem; }

    .lb-page .form-label { font-weight:600; color:#48536b; margin-bottom:.35rem; font-size:.8rem; }
    .lb-page .form-control, .lb-page .form-select { border-radius:9px; border:1px solid #dfe3e8; padding:.5rem .75rem; font-size:.85rem; }
    .lb-page .form-control:focus, .lb-page .form-select:focus { border-color:#ea6118; box-shadow:0 0 0 .18rem rgba(234,97,24,.14); }
    .btn-lb { background:linear-gradient(135deg,#ea6118,#d1520e); color:#fff; border:none; padding:.55rem 1.4rem; border-radius:9px; font-weight:600; font-size:.85rem; transition:.25s; display:inline-flex; align-items:center; gap:.35rem; }
    .btn-lb:hover { color:#fff; transform:translateY(-1px); box-shadow:0 6px 16px rgba(234,97,24,.28); }
    .btn-lb-ghost { background:#f1f3f6; color:#48536b; border:none; padding:.55rem 1.2rem; border-radius:9px; font-weight:600; font-size:.85rem; }
    .btn-lb-ghost:hover { background:#e6e9ee; color:#293b50; }

    .lb-table-wrap { overflow-x:auto; border-radius:12px; border:1px solid #eef0f3; }
    .lb-table { width:100%; border-collapse:separate; border-spacing:0; font-size:.8rem; white-space:nowrap; margin:0; }
    .lb-table thead th { position:sticky; top:0; background:#f7f8fa; color:#5b6576; font-weight:600; text-align:right; padding:.65rem .8rem; border-bottom:1px solid #e9ebef; font-size:.72rem; text-transform:uppercase; letter-spacing:.02em; }
    .lb-table thead th.tl, .lb-table td.tl { text-align:left; }
    .lb-table tbody td { padding:.6rem .8rem; text-align:right; border-bottom:1px solid #f1f2f5; color:#374151; }
    .lb-table tbody tr:hover td { background:#fcfcfd; }
    .lb-table td.client { font-weight:600; color:#293b50; max-width:300px; white-space:normal; }
    .lb-uname { display:inline-block; margin-left:.4rem; background:#eef1f4; color:#5b6576; font-weight:600; font-size:.7rem; padding:1px 7px; border-radius:20px; vertical-align:middle; }
    .lb-table td.daemon { font-weight:600; }
    .lb-status-row td { background:#eef1f4 !important; font-weight:700; color:#48536b; text-transform:capitalize; padding:.45rem .8rem; border-bottom:1px solid #e3e7ec; }

    .del-cell b { font-weight:700; }
    .del-d { color:#0f8a4d; } .del-n { color:#c0392b; } .del-o { color:#b7791f; }
    .money-neg { color:#c0392b; font-weight:600; } .money-pos { color:#0f8a4d; font-weight:600; }
    .pence { color:#98a0ad; font-size:.72rem; }

    .lb-summary { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:.9rem 1.5rem; }
    .lb-sum-item { border-left:3px solid #ea6118; padding-left:.7rem; }
    .lb-sum-item .k { font-size:.72rem; color:#8a94a6; font-weight:600; text-transform:uppercase; }
    .lb-sum-item .v { font-size:1.05rem; font-weight:700; color:#293b50; }
    .lb-statuscounts { margin-top:1rem; display:flex; flex-wrap:wrap; gap:.5rem; }
    .lb-chip { background:#f1f3f6; border-radius:20px; padding:.25rem .8rem; font-size:.76rem; font-weight:600; color:#48536b; }
    .lb-chip b { color:#293b50; }
    .lb-refresh-lbl { font-size:.76rem; font-weight:600; color:#8a94a6; }
    .lb-countdown { background:#fff3e9; color:#c65611; }
    .lb-countdown:empty { display:none; }
    #lbRefresh { border-radius:8px; border:1px solid #dfe3e8; font-size:.78rem; padding:.25rem .5rem; }

    .lb-legend { font-size:.76rem; color:#8a94a6; line-height:1.7; }
    .lb-legend code { background:#f1f3f6; padding:1px 5px; border-radius:4px; color:#48536b; }
    .lb-empty { text-align:center; padding:2.5rem 1rem; color:#9aa3b2; }
    .lb-empty i { font-size:42px; display:block; margin-bottom:.5rem; color:#cbd2db; }
</style>
@endpush

@section('content')
<main class="main-wrapper" id="main-wrapper">
    <div class="main-content lb-page">

        {{-- Breadcrumb --}}
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
            <div class="breadcrumb-title pe-3 title-name">Livebeat</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0" style="background: none;">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item">Other Links</li>
                        <li class="breadcrumb-item active" aria-current="page">Livebeat</li>
                    </ol>
                </nav>
            </div>
            <div class="me-2 back-button-container" style="position: absolute; right: 0; top: 50%; transform: translateY(-50%);">
                <button id="backButton" class="btn btn-primary btn-sm"><i class="bx bx-arrow-back"></i> Back</button>
            </div>
        </div>

        {{-- KPI summary --}}
        <div class="lb-kpis">
            <div class="lb-kpi"><div class="ic ic-orange"><i class="material-icons-outlined">sms</i></div>
                <div><div class="lbl">Submitted</div><div class="val">{{ number_format($summary['submitted']) }}</div></div></div>
            <div class="lb-kpi"><div class="ic ic-green"><i class="material-icons-outlined">verified</i></div>
                <div><div class="lbl">Delivered</div><div class="val">{{ number_format($summary['delivered']) }} <small style="font-size:.8rem;color:#8a94a6;">({{ $summary['delivered_pct'] }}%)</small></div></div></div>
            <div class="lb-kpi"><div class="ic ic-blue"><i class="material-icons-outlined">account_balance_wallet</i></div>
                <div><div class="lbl">Revenue</div><div class="val">£{{ number_format($summary['revenue'], 2) }}</div></div></div>
            <div class="lb-kpi"><div class="ic ic-slate"><i class="material-icons-outlined">payments</i></div>
                <div><div class="lbl">Net Profit</div><div class="val" style="color:{{ $summary['net_profit'] < 0 ? '#c0392b' : '#0f8a4d' }};">£{{ number_format($summary['net_profit'], 2) }}</div></div></div>
        </div>

        {{-- Filters --}}
        <div class="lb-card">
            <div class="lb-card-head"><h5><i class="material-icons-outlined">filter_alt</i> Filters</h5></div>
            <div class="lb-card-body">
                <form method="GET" action="{{ route('admin.reports.daemon') }}" class="row g-3 align-items-end">
                    <div class="col-6 col-md-3 col-lg-2"><label class="form-label">From (dosend)</label>
                        <input type="date" name="from_date" value="{{ $filters['from_date'] }}" class="form-control"></div>
                    <div class="col-6 col-md-3 col-lg-2"><label class="form-label">To (dosend)</label>
                        <input type="date" name="to_date" value="{{ $filters['to_date'] }}" class="form-control"></div>
                    <div class="col-6 col-md-3 col-lg-2"><label class="form-label">Country</label>
                        <select name="country" class="form-select">
                            <option value="" {{ $filters['country']==='' ?'selected':'' }}>All countries</option>
                            <option value="44" {{ $filters['country']==='44' ?'selected':'' }}>UK (44)</option>
                            <option value="27" {{ $filters['country']==='27' ?'selected':'' }}>South Africa (27)</option>
                            <option value="other" {{ $filters['country']==='other' ?'selected':'' }}>Rest of World</option>
                        </select></div>
                    <div class="col-6 col-md-3 col-lg-2"><label class="form-label">Supplier</label>
                        <select name="supplier" class="form-select">
                            <option value="">All suppliers</option>
                            @foreach($suppliers as $s)<option value="{{ $s }}" {{ $filters['supplier']===$s ?'selected':'' }}>{{ $s }}</option>@endforeach
                        </select></div>
                    <div class="col-6 col-md-3 col-lg-2"><label class="form-label">Status</label>
                        <select name="sentstatus" class="form-select">
                            <option value="all" {{ $filters['sentstatus']==='all' ?'selected':'' }}>All statuses</option>
                            <option value="ok" {{ $filters['sentstatus']==='ok' ?'selected':'' }}>Sent OK</option>
                            <option value="pending" {{ $filters['sentstatus']==='pending' ?'selected':'' }}>Pending</option>
                            <option value="fail" {{ $filters['sentstatus']==='fail' ?'selected':'' }}>Failed</option>
                            <option value="no" {{ $filters['sentstatus']==='no' ?'selected':'' }}>Not sent</option>
                        </select></div>
                    <div class="col-12 col-md-6 col-lg-4"><label class="form-label">User(s)</label>
                        <select name="user[]" class="form-select lb-user-select" multiple data-placeholder="All users — select one or more">
                            @foreach($customers as $c)
                                @php $label = urldecode($c->busname ?: ($c->contactname ?: $c->uname)); @endphp
                                <option value="{{ $c->bigid }}" {{ in_array($c->bigid, (array) $filters['user']) ? 'selected' : '' }}>{{ $label }} ({{ $c->uname }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn-lb"><i class="material-icons-outlined" style="font-size:18px;">search</i> Run Report</button>
                        <a href="{{ route('admin.reports.daemon') }}" class="btn-lb-ghost">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Results --}}
        <div class="lb-card">
            <div class="lb-card-head">
                <h5><i class="material-icons-outlined">dns</i> Livebeat Breakdown</h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="lb-chip" id="lbUpdated"><i class="material-icons-outlined" style="font-size:14px;vertical-align:-2px;">schedule</i> —</span>
                    <span class="lb-refresh-lbl">Auto-refresh</span>
                    <select id="lbRefresh" class="form-select form-select-sm" style="width:auto;min-width:80px;">
                        <option value="0">Off</option>
                        <option value="15">15s</option>
                        <option value="30">30s</option>
                        <option value="60">60s</option>
                    </select>
                    <span class="lb-chip lb-countdown" id="lbCountdown"></span>
                    <span class="lb-chip">{{ count($rows) }} rows</span>
                </div>
            </div>
            <div class="lb-card-body">
                <div class="lb-table-wrap">
                    <table class="lb-table">
                        <thead>
                            <tr>
                                <th class="tl">Client</th>
                                <th>Volume</th>
                                <th class="tl">Delivered / Non-Del / Other</th>
                                <th>Net Profit</th>
                                <th class="tl">Route</th>
                                <th class="tl">SMSG Daemon</th>
                                <th class="tl">Status</th>
                                <th>Age</th>
                                <th>Gross</th>
                                <th>HLR</th>
                                <th>Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $lastStatus = null; @endphp
                            @forelse($rows as $r)
                                @if($r->status !== $lastStatus)
                                    <tr class="lb-status-row"><td colspan="11">{{ $r->status }}</td></tr>
                                    @php $lastStatus = $r->status; @endphp
                                @endif
                                <tr>
                                    <td class="tl client" title="{{ $r->client }}">
                                        {{ $r->client }}
                                        @if($r->uname)<span class="lb-uname">{{ $r->uname }}</span>@endif
                                    </td>
                                    <td>{{ number_format($r->volume) }}</td>
                                    <td class="tl del-cell">
                                        <span class="del-d"><b>{{ number_format($r->delivered) }}</b> ({{ $r->del_pct }}%)</span> /
                                        <span class="del-n"><b>{{ number_format($r->non_delivered) }}</b> ({{ $r->ndel_pct }}%)</span> /
                                        <span class="del-o"><b>{{ number_format($r->other_status) }}</b> ({{ $r->other_pct }}%)</span>
                                    </td>
                                    <td class="{{ $r->net_profit < 0 ? 'money-neg' : 'money-pos' }}">
                                        £{{ number_format($r->net_profit, 3) }}<br><span class="pence">{{ number_format($r->net_pence, 4) }}p</span>
                                    </td>
                                    <td class="tl">{{ $r->route }}</td>
                                    <td class="tl daemon">{{ $r->daemon_name ?: '—' }}</td>
                                    <td class="tl">{{ $r->status }}</td>
                                    <td>{{ $r->age !== '' ? $r->age : '—' }}</td>
                                    <td>£{{ number_format($r->gross_profit, 2) }}</td>
                                    <td>£{{ number_format($r->hlr_cost, 2) }}</td>
                                    <td>£{{ number_format($r->cost, 3) }}<br><span class="pence">{{ number_format($r->cost_pence, 4) }}p</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="11"><div class="lb-empty"><i class="material-icons-outlined">search_off</i>No messages found for the selected filters.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="lb-legend mt-3">
                    <strong>Client</strong> = daemonpriority + contact − business &nbsp;•&nbsp;
                    <strong>Delivered</strong> = D / Non-Del / Other (% of volume) &nbsp;•&nbsp;
                    <strong>Net Profit</strong> = Gross − HLR (pence = per delivered for 'd' routes, else per submitted) &nbsp;•&nbsp;
                    <strong>Route</strong> = charge basis + route (<code>s</code>=pps, <code>d</code>=ppd) &nbsp;•&nbsp;
                    <strong>Age</strong> shows for un-sent rows only.
                </div>
            </div>
        </div>

        {{-- Summary (always shown, directly below the breakdown) --}}
        <div class="lb-card">
            <div class="lb-card-head"><h5><i class="material-icons-outlined">summarize</i> Summary</h5></div>
            <div class="lb-card-body">
                <div class="lb-summary">
                    <div class="lb-sum-item"><div class="k">Submitted</div><div class="v">{{ number_format($summary['submitted']) }}</div></div>
                    <div class="lb-sum-item"><div class="k">Delivered</div><div class="v">{{ number_format($summary['delivered']) }} ({{ $summary['delivered_pct'] }}%)</div></div>
                    <div class="lb-sum-item"><div class="k">Revenue</div><div class="v">£{{ number_format($summary['revenue'], 2) }}</div></div>
                    <div class="lb-sum-item"><div class="k">Cost</div><div class="v">£{{ number_format($summary['cost'], 2) }}</div></div>
                    <div class="lb-sum-item"><div class="k">Gross Profit</div><div class="v">£{{ number_format($summary['gross_profit'], 2) }}</div></div>
                    <div class="lb-sum-item"><div class="k">Net Profit</div><div class="v" style="color:{{ $summary['net_profit']<0?'#c0392b':'#0f8a4d' }};">£{{ number_format($summary['net_profit'], 2) }}</div></div>
                    <div class="lb-sum-item"><div class="k">Profit / Submitted</div><div class="v">£{{ number_format($summary['per_submitted'], 4) }}</div></div>
                    <div class="lb-sum-item"><div class="k">Profit / Delivered</div><div class="v">£{{ number_format($summary['per_delivered'], 4) }}</div></div>
                </div>
                @if(count($statusCounts))
                <div class="lb-statuscounts">
                    @foreach($statusCounts as $label => $vol)
                        <span class="lb-chip">{{ $label }}: <b>{{ number_format($vol) }}</b></span>
                    @endforeach
                </div>
                @endif
            </div>
        </div>

    </div>
</main>
@endsection

@push('js')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    (function () {
        function initUserSelect() {
            if (!window.jQuery || !jQuery.fn.select2) { return setTimeout(initUserSelect, 150); }
            jQuery('.lb-user-select').select2({
                theme: 'bootstrap-5',
                width: '100%',
                closeOnSelect: false,
                allowClear: true,
                placeholder: jQuery('.lb-user-select').data('placeholder') || 'Select users'
            });
        }
        initUserSelect();
    })();

    // ---- Auto-refresh (old Livebeat refreshed every 15s; freeze = "Off") ----
    (function () {
        var KEY = 'lb_refresh_secs';
        var sel = document.getElementById('lbRefresh');
        var cd  = document.getElementById('lbCountdown');
        var upd = document.getElementById('lbUpdated');
        if (!sel) { return; }

        function pad(n) { return (n < 10 ? '0' : '') + n; }
        var now = new Date();
        if (upd) {
            upd.innerHTML = '<i class="material-icons-outlined" style="font-size:14px;vertical-align:-2px;">schedule</i> updated '
                + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
        }

        var secs = parseInt(localStorage.getItem(KEY), 10);
        if (isNaN(secs)) { secs = 0; }           // default OFF — user turns it on if they want it
        sel.value = String(secs);

        var remaining = secs, timer = null;
        function render() { cd.textContent = secs > 0 ? ('refresh in ' + remaining + 's') : ''; }
        function start() {
            clearInterval(timer);
            remaining = secs;
            render();
            if (secs > 0) {
                timer = setInterval(function () {
                    remaining--;
                    if (remaining <= 0) { window.location.reload(); return; } // reload keeps the current query string (filters)
                    render();
                }, 1000);
            }
        }
        sel.addEventListener('change', function () {
            secs = parseInt(sel.value, 10) || 0;
            localStorage.setItem(KEY, String(secs));
            start();
        });
        start();
    })();
</script>
@endpush
