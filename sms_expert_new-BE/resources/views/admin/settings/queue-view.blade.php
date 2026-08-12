@extends('admin.layouts.app')

@section('title')
    {{ __('Queue: ' . $label) }}
@endsection

@section('content')
<main class="main-wrapper" id="main-wrapper">
    <div class="main-content">
        <!-- breadcrumb -->
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
            <div class="breadcrumb-title pe-3 title-name">Queue Messages</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0" style="background: none;">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.settings', ['tab' => 'queues']) }}" class="text-decoration-none">Queues</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>
                    </ol>
                </nav>
            </div>
            <div class="me-2" style="position: absolute; right: 0; top: 50%; transform: translateY(-50%);">
                <a href="{{ route('admin.settings', ['tab' => 'queues']) }}" class="btn btn-primary btn-sm">
                    <i class="bx bx-arrow-back"></i> Back to Queues
                </a>
            </div>
        </div>
        <!-- end breadcrumb -->

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                    <div>
                        <h5 class="mb-1">
                            <i class="material-icons-outlined me-1 align-middle">inventory_2</i>{{ $label }}
                        </h5>
                        <small class="text-muted">
                            <code>{{ $queue }}</code> — up to <strong>50</strong> messages, read-only (requeued, not consumed).
                            Loaded <span id="qmCheckedAt">—</span>
                        </small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary fs-6" id="qmCountBadge">Messages: —</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="qmReloadBtn">
                            <i class="material-icons-outlined font-18 align-middle">refresh</i> Reload
                        </button>
                    </div>
                </div>

                <div id="qmBody">
                    <div class="text-center text-muted py-5">
                        <span class="spinner-border spinner-border-sm me-2"></span> Loading messages…
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
(function () {
    const msgUrl = "{{ route('admin.settings.queue-messages') }}";
    const queue  = @json($queue);
    const qmBody = document.getElementById('qmBody');
    const qmCountBadge = document.getElementById('qmCountBadge');
    const qmCheckedAt = document.getElementById('qmCheckedAt');
    const qmReloadBtn = document.getElementById('qmReloadBtn');

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function renderMessages(data) {
        if (!data.success) {
            qmBody.innerHTML = `<div class="alert alert-danger mb-0">${esc(data.message || 'Could not read queue.')}</div>`;
            qmCountBadge.textContent = 'Messages: —';
            return;
        }
        qmCountBadge.textContent = 'Messages: ' + (Number(data.count) || 0).toLocaleString();
        qmCheckedAt.textContent = data.checked_at || '—';
        if (!data.messages || !data.messages.length) {
            qmBody.innerHTML = '<div class="text-center text-muted py-5">Queue is empty right now.</div>';
            return;
        }
        qmBody.innerHTML = data.messages.map((m, idx) => {
            const summary = m.summary || {};
            const sumRows = Object.keys(summary).map(k =>
                `<tr><td class="text-muted" style="width:30%">${esc(k)}</td><td>${esc(typeof summary[k] === 'object' ? JSON.stringify(summary[k]) : summary[k])}</td></tr>`
            ).join('') || '<tr><td colspan="2" class="text-muted">No summary fields</td></tr>';
            const raw = esc(JSON.stringify(m.payload, null, 2));
            const rd = m.redelivered ? ' <span class="badge bg-warning text-dark ms-1">redelivered</span>' : '';
            const cid = 'qmraw' + idx;
            return `<div class="card border mb-2">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <strong>#${idx + 1}</strong>${rd}
                    <button class="btn btn-sm btn-link p-0" type="button" data-bs-toggle="collapse" data-bs-target="#${cid}">raw payload</button>
                </div>
                <div class="card-body py-2">
                    <table class="table table-sm mb-0"><tbody>${sumRows}</tbody></table>
                    <div class="collapse mt-2" id="${cid}"><pre class="bg-light p-2 mb-0" style="max-height:300px;overflow:auto;">${raw}</pre></div>
                </div>
            </div>`;
        }).join('');
    }

    function load() {
        qmReloadBtn.disabled = true;
        qmBody.innerHTML = '<div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span> Loading messages…</div>';
        fetch(`${msgUrl}?queue=${encodeURIComponent(queue)}&limit=50`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(renderMessages)
            .catch(err => { qmBody.innerHTML = `<div class="alert alert-danger mb-0">Request failed: ${esc(err)}</div>`; })
            .finally(() => { qmReloadBtn.disabled = false; });
    }

    qmReloadBtn.addEventListener('click', load);
    load();
})();
</script>
@endsection
