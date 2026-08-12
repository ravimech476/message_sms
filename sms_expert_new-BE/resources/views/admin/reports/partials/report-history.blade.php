{{-- Shared Report History (all report types). Handles submitting every per-tab
     .ar-form, auto-filling the editable report name, and live status polling. --}}
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0"><i class="material-icons-outlined me-1 align-middle">history</i>Report History <span id="arHistType" class="text-muted fw-normal small"></span></h6>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" id="arHistRefresh">
                    <i class="material-icons-outlined font-18 align-middle">refresh</i>
                </button>
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
              || document.querySelector('.ar-form input[name="_token"]')?.value || '';
    const histBody = document.getElementById('arHistBody');
    const today = "{{ now()->format('Y-m-d') }}";
    let pollTimer = null;

    function esc(s){return String(s ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
    const badge=(s)=>{const m={pending:'secondary',processing:'info',ready:'success',failed:'danger'};return `<span class="badge bg-${m[s]||'secondary'}">${esc(s)}</span>`;};

    function renderHistory(data){
        if(!data.success){histBody.innerHTML='<tr><td colspan="7" class="text-center text-muted py-3">Could not load history</td></tr>';return;}
        const rows=data.data||[];
        histBody.innerHTML = rows.length ? rows.map(r=>`
            <tr>
                <td>${esc(r.name)}</td>
                <td>${esc(r.type)}</td>
                <td>${esc(r.date_range)}</td>
                <td>${esc(r.email)}</td>
                <td class="text-center">${badge(r.status)}${(r.status==='failed'&&r.error)?` <i class="material-icons-outlined text-danger" style="font-size:16px;cursor:help" title="${esc(r.error)}">error</i>`:''}</td>
                <td>${esc(r.requested_at||'')}</td>
                <td class="text-center">${r.download_url?`<a href="${r.download_url}" class="btn btn-sm btn-outline-secondary"><i class="material-icons-outlined font-18 align-middle">download</i></a>`:'<span class="text-muted">—</span>'}</td>
            </tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted py-3">No reports yet</td></tr>';
        const busy=rows.some(r=>r.status==='pending'||r.status==='processing');
        if(busy&&!pollTimer)pollTimer=setInterval(loadHistory,5000);
        if(!busy&&pollTimer){clearInterval(pollTimer);pollTimer=null;}
    }
    const histType = document.getElementById('arHistType');
    function activeType(){
        const f = document.querySelector('.tab-pane.active .ar-form');
        return f ? f.dataset.type : '';
    }
    function loadHistory(){
        const t = activeType();
        const activeBtn = document.querySelector('#reportTabs button.active');
        if(histType) histType.textContent = activeBtn ? ('— ' + activeBtn.textContent.trim()) : '';
        const u = historyUrl + (t ? ('?type=' + encodeURIComponent(t)) : '');
        fetch(u,{headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.json()).then(renderHistory)
            .catch(()=>{histBody.innerHTML='<tr><td colspan="7" class="text-center text-muted py-3">Request failed</td></tr>';});
    }

    // Auto-fill the editable report name from type + dates (until the user edits it).
    function autoName(form){
        const name=form.querySelector('.ar-name');
        if(!name || name.dataset.dirty==='1') return;
        const label=name.dataset.typelabel||'Report';
        const fmt=(d)=>{ if(!d) return ''; const dt=new Date(d+'T00:00:00'); return isNaN(dt)?'':dt.toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}); };
        const from=fmt(form.querySelector('.ar-from')?.value||'');
        const to=fmt(form.querySelector('.ar-to')?.value||'');
        name.value = `${label} ${from} - ${to}`;
    }

    // Enforce: date_from <= today, and date_to between date_from and today.
    function clampDates(form){
        const from=form.querySelector('.ar-from');
        const to=form.querySelector('.ar-to');
        if(!from || !to) return;
        if(from.value && from.value > today) from.value = today;   // no future "from"
        if(to.value && to.value > today) to.value = today;         // no future "to"
        if(from.value){
            to.min = from.value;                                   // "to" can't be before "from"
            if(to.value && to.value < from.value) to.value = from.value;
        }
    }

    document.querySelectorAll('.ar-form').forEach(function(f){ clampDates(f); autoName(f); });

    function onDateOrName(e){
        if(e.target.classList.contains('ar-name')) e.target.dataset.dirty='1';
        if(e.target.classList.contains('ar-from')||e.target.classList.contains('ar-to')){
            const form=e.target.closest('.ar-form'); if(form){ clampDates(form); autoName(form); }
        }
    }
    document.addEventListener('input', onDateOrName);
    document.addEventListener('change', onDateOrName); // date pickers fire 'change'

    // select2 lib loads at the bottom of the page; init once it's available.
    window.addEventListener('load', function(){
        if(window.jQuery && jQuery.fn.select2){
            jQuery('.ar-customers').select2({theme:'bootstrap-5',width:'100%',placeholder:'All customers',closeOnSelect:false});
        }
    });

    // Submit any per-tab generate form.
    document.addEventListener('submit', function(e){
        const form=e.target.closest('.ar-form');
        if(!form) return;
        e.preventDefault();
        const alertBox=form.parentElement.querySelector('.ar-alert');
        const btn=form.querySelector('button[type=submit]');
        if(alertBox) alertBox.classList.add('d-none');
        if(btn) btn.disabled=true;
        fetch(requestUrl,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},body:new FormData(form)})
            .then(async r=>{const d=await r.json().catch(()=>({})); if(!r.ok) throw new Error(d.message||(d.errors?Object.values(d.errors).flat().join(' '):'Could not queue the report.')); return d;})
            .then(d=>{ if(alertBox){alertBox.className='alert alert-'+(d.queued===false?'warning':'success'); alertBox.textContent=d.message||'Report queued.'; alertBox.classList.remove('d-none');} loadHistory(); })
            .catch(err=>{ if(alertBox){alertBox.className='alert alert-danger'; alertBox.textContent=err.message; alertBox.classList.remove('d-none');} })
            .finally(()=>{ if(btn) btn.disabled=false; });
    });

    document.getElementById('arHistRefresh').addEventListener('click', loadHistory);
    // Reload (and re-filter) the history whenever the report tab changes.
    document.querySelectorAll('#reportTabs button[data-bs-toggle="tab"]').forEach(function(btn){
        btn.addEventListener('shown.bs.tab', function(){
            if(pollTimer){ clearInterval(pollTimer); pollTimer=null; } // reset polling for the new type
            loadHistory();
        });
    });
    loadHistory();
})();
</script>
