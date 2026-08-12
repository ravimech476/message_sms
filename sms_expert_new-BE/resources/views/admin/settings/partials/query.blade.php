{{-- Read-only SQL query console (Settings > Query). Super-admin only.
     Executes a single SELECT/SHOW/DESCRIBE/EXPLAIN statement (validated server-side
     in SettingsController::runQuery) and shows the rows. Results are capped at 1000 rows. --}}
<div class="card border-0">
    <div class="card-body">
        <h5 class="mb-1"><i class="material-icons-outlined me-1 align-middle">terminal</i> SQL Query Console</h5>
        <p class="text-muted small mb-3">
            Run a <strong>read-only</strong> query against the live database. Only
            <strong>SELECT / SHOW / DESCRIBE / EXPLAIN</strong> statements are allowed —
            any write/DDL keyword (INSERT, UPDATE, DELETE, DROP, ALTER…) is rejected.
            Results are limited to <strong>1000 rows</strong>.
        </p>

        <div class="alert alert-warning d-flex align-items-start py-2 px-3 mb-3">
            <i class="material-icons-outlined me-2" style="font-size:18px;">warning</i>
            <div class="small">This runs against <strong>production data</strong>. Double-check your query — even read-only queries on large tables can be slow.</div>
        </div>

        <div class="mb-2">
            <label class="form-label small text-muted mb-1">SQL query</label>
            <textarea id="sqlQueryInput" class="form-control" rows="5" spellcheck="false"
                style="font-family:Consolas,Menlo,monospace;font-size:13px;"
                placeholder="SELECT id, mobnum, sentstatus, deliverystatus2 FROM smsg_log ORDER BY id DESC LIMIT 50"></textarea>
            <div class="form-text">Tip: add your own <code>LIMIT</code>; a hard cap of 1000 rows is applied regardless.</div>
        </div>

        <div class="d-flex gap-2 mb-3">
            <button type="button" id="runQueryBtn" class="btn btn-primary">
                <i class="material-icons-outlined font-18 align-middle">play_arrow</i> Run Query
            </button>
            <button type="button" id="clearQueryBtn" class="btn btn-outline-secondary">
                <i class="material-icons-outlined font-18 align-middle">clear</i> Clear
            </button>
            <div id="queryStatus" class="ms-auto small text-muted align-self-center"></div>
        </div>

        <div id="queryError" class="alert alert-danger d-none py-2 px-3 mb-3"></div>

        <div id="queryResultWrap" class="d-none">
            <div id="queryMeta" class="small text-muted mb-2"></div>
            <div class="table-responsive" style="max-height:60vh;overflow:auto;border:1px solid #e5e7eb;border-radius:6px;">
                <table class="table table-sm table-bordered table-striped mb-0" style="font-size:12.5px;">
                    <thead class="table-light" style="position:sticky;top:0;z-index:1;"><tr id="queryHead"></tr></thead>
                    <tbody id="queryBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const url      = "{{ route('admin.settings.query.run') }}";
    const csrf     = "{{ csrf_token() }}";
    const input    = document.getElementById('sqlQueryInput');
    const runBtn   = document.getElementById('runQueryBtn');
    const clearBtn = document.getElementById('clearQueryBtn');
    const statusEl = document.getElementById('queryStatus');
    const errEl    = document.getElementById('queryError');
    const wrap     = document.getElementById('queryResultWrap');
    const meta     = document.getElementById('queryMeta');
    const head     = document.getElementById('queryHead');
    const body     = document.getElementById('queryBody');

    if (!input || !runBtn) return; // tab not rendered

    const esc = s => String(s === null || s === undefined ? '' : s)
        .replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    function showError(msg) {
        errEl.textContent = msg;
        errEl.classList.remove('d-none');
        wrap.classList.add('d-none');
    }

    function render(d) {
        errEl.classList.add('d-none');
        head.innerHTML = '';
        body.innerHTML = '';

        if (!d.columns || d.columns.length === 0) {
            meta.innerHTML = '<span class="text-success">Query OK</span> — 0 rows returned.';
            wrap.classList.remove('d-none');
            return;
        }

        head.innerHTML = '<th style="white-space:nowrap;">#</th>' +
            d.columns.map(c => `<th style="white-space:nowrap;">${esc(c)}</th>`).join('');

        body.innerHTML = d.rows.map((row, i) =>
            '<tr><td class="text-muted">' + (i + 1) + '</td>' +
            d.columns.map(c => {
                const v = row[c];
                return `<td>${v === null ? '<span class="text-muted fst-italic">NULL</span>' : esc(v)}</td>`;
            }).join('') + '</tr>'
        ).join('');

        let m = `<span class="text-success">Query OK</span> — <strong>${d.row_count}</strong> row(s) in ${d.execution_ms} ms.`;
        if (d.truncated) m += ' <span class="text-warning">(capped at 1000 rows — add a tighter LIMIT/WHERE to see more)</span>';
        meta.innerHTML = m;
        wrap.classList.remove('d-none');
    }

    function run() {
        const q = (input.value || '').trim();
        if (!q) { showError('Please enter a query.'); return; }

        runBtn.disabled = true;
        statusEl.textContent = 'Running…';
        errEl.classList.add('d-none');

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ query: q }),
        })
        .then(async r => {
            const d = await r.json().catch(() => ({ success: false, message: 'Invalid server response.' }));
            if (!r.ok || !d.success) {
                showError(d.message || ('Request failed (HTTP ' + r.status + ').'));
            } else {
                render(d);
            }
        })
        .catch(e => showError('Network error: ' + e.message))
        .finally(() => { runBtn.disabled = false; statusEl.textContent = ''; });
    }

    runBtn.addEventListener('click', run);
    clearBtn.addEventListener('click', () => {
        input.value = '';
        wrap.classList.add('d-none');
        errEl.classList.add('d-none');
        meta.innerHTML = '';
    });
    // Ctrl/Cmd + Enter to run
    input.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); run(); }
    });
})();
</script>
