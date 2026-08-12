<div class="tab-pane fade {{ session('activeTab') == 'customer-rate' ? 'show active' : '' }}" id="customer-rate"
    role="tabpanel">

    <div class="card mb-4 border">
        <div class="card-body">
            <h5 class="card-title mb-4">User SMS Rates - Route-Based Pricing (Old System)</h5>

            {{-- Info Alert --}}
            <div class="alert alert-info mb-4">
                <i class="bi bi-info-circle"></i>
                <strong>Route-Based Pricing:</strong> This system uses the <code>smsg_userroute</code> table to manage
                per-route, per-country pricing for this user. When adding a rate, 6 records are created (7-bit/8-bit x alpha/msisdn/shortcode).
            </div>

            {{-- User Info --}}
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="alert alert-secondary mb-0">
                        <strong>User:</strong> {{ $record->uname ?? 'N/A' }}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-secondary mb-0">
                        <strong>BigID:</strong>
                        <code style="font-size: 0.75rem;">{{ $record->bigid }}</code>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-secondary mb-0">
                        <strong>Business:</strong> {{ urldecode($record->busname ?? 'N/A') }}
                    </div>
                </div>
            </div>

            {{-- UK Rates Section (Old System Format) --}}
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-currency-pound"></i> <strong>Rates...</strong>
                </div>
                <div class="card-body">
                    @php
                        // Group user rates by routenum for UK (44)
                        $ukUserRates = collect($userRoutes ?? [])->filter(function($rate) {
                            return $rate->countrydialcode == '44';
                        })->groupBy('routenum');

                        // Group default rates by routenum for UK (44)
                        $ukDefaultRates = collect($defaultRoutes ?? [])->filter(function($rate) {
                            return $rate->countrydialcode == '44';
                        })->groupBy('routenum');

                        // Route info mapping (matching old system)
                        $routeInfo = [
                            7002 => '-Direct(mBlox)',
                            7029 => '-Direct(CSN/mBlox)',
                            8002 => '-PremiumPlus(mBlox)',
                            8029 => '-PremiumPlus(CSN/mBlox)',
                            3002 => '-Premium(mBlox)',
                            3029 => '-Premium(CSN/mBlox)',
                        ];

                        // Routes to display
                        $displayRoutes = [7002, 7029, 8002, 8029, 3002, 3029];
                    @endphp

                    @foreach($displayRoutes as $routenum)
                        @php
                            $extraInfo = $routeInfo[$routenum] ?? '';
                            $userRate = $ukUserRates->get($routenum)?->first();
                            $defaultRate = $ukDefaultRates->get($routenum)?->first();
                            $hasUserRate = !is_null($userRate);
                            $currentRate = $hasUserRate ? $userRate : $defaultRate;
                            $currentPrice = $currentRate ? number_format($currentRate->userprice, 4) : '0.0300';
                            $currentPriority = $currentRate ? $currentRate->priority : 1;
                            $showGap = in_array($routenum, [7029, 8029]);
                        @endphp

                        <form class="rateForm mb-2" data-routenum="{{ $routenum }}" style="display: inline-block; width: 100%;">
                            <span style="font-size: 90%;">
                                <strong>UK r{{ $routenum }}{{ $extraInfo }}</strong>
                                @if($hasUserRate)
                                    (<a href="javascript:void(0)" onclick="deleteRate({{ $routenum }}, '44', {{ $currentPriority }})" class="text-danger">Del</a>)
                                @endif
                                &nbsp;&nbsp;Rate (£{{ $currentPrice }}):
                                <input type="text" size="6" name="newratesvalue" value="" style="font-size: 90%;" placeholder="New rate">
                                &nbsp;&nbsp;Priority ({{ $currentPriority }}):
                                <input type="text" size="4" name="newratespriority" value="" style="font-size: 90%;" placeholder="Optional">
                                &nbsp;&nbsp;<button type="submit" class="btn btn-sm btn-primary" style="font-size: 80%; padding: 2px 8px;">Add</button>
                            </span>
                        </form>

                        @if($showGap)
                            <br><br>
                        @endif
                    @endforeach

                </div>
            </div>

        </div>
    </div>

    <script>
        const userId = '{{ $record->id }}';
        const userBigid = '{{ $record->bigid }}';
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta ? csrfMeta.content : '';
        const apiBaseUrl = '/api/admin/user-rates';

        // Toast that won't crash the handler if a global showToast isn't loaded
        // on this page (that missing function was the "error" on Add).
        function safeToast(msg, type) {
            try { if (typeof showToast === 'function') { showToast(msg, type); return; } } catch (e) {}
            alert(msg);
        }

        // Inline per-row validation warning shown right under the rate input.
        function showRateWarning(form, input, msg) {
            input.style.borderColor = '#dc3545';
            input.style.background = '#fff5f5';
            let w = form.querySelector('.rate-warning');
            if (!w) {
                w = document.createElement('div');
                w.className = 'rate-warning text-danger';
                w.style.cssText = 'font-size:80%; margin-top:3px; font-weight:600;';
                form.appendChild(w);
            }
            w.textContent = '⚠ ' + msg;
            input.focus();
        }
        function clearRateWarning(form, input) {
            input.style.borderColor = '';
            input.style.background = '';
            const w = form.querySelector('.rate-warning');
            if (w) w.remove();
        }

        // Handle UK rate forms (old system format)
        document.querySelectorAll('.rateForm').forEach(form => {
            const rateInput = form.querySelector('input[name="newratesvalue"]');
            if (rateInput) rateInput.addEventListener('input', () => clearRateWarning(form, rateInput));

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const routenum = parseInt(this.dataset.routenum);
                const valueInput = this.querySelector('input[name="newratesvalue"]');
                const newratesvalue = valueInput.value.trim();
                const newratespriority = this.querySelector('input[name="newratespriority"]').value.trim();

                // VALIDATION — rate column must not be empty.
                if (!newratesvalue) {
                    showRateWarning(this, valueInput, 'Rate column is empty — please enter a rate.');
                    return;
                }
                // VALIDATION — rate must be a valid number.
                if (isNaN(parseFloat(newratesvalue))) {
                    showRateWarning(this, valueInput, 'Rate must be a number (e.g. 0.0350).');
                    return;
                }
                clearRateWarning(this, valueInput);

                const formData = {
                    routenum: routenum,
                    countrydialcode: '44',
                    userprice: parseFloat(newratesvalue)
                };

                // Priority is optional - only include if provided, otherwise backend defaults to 1
                if (newratespriority) {
                    formData.priority = parseInt(newratespriority);
                }

                const btn = this.querySelector('button[type="submit"]');
                if (btn) btn.disabled = true;

                fetch(`${apiBaseUrl}/user/${userId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(formData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // No success toast — bulk pricing operators reload one row
                        // at a time; errors still show so failures are visible.
                        location.reload();
                    } else {
                        if (btn) btn.disabled = false;
                        safeToast('Failed: ' + (data.message || 'could not add rate'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (btn) btn.disabled = false;
                    safeToast('Failed to add rate', 'error');
                });
            });
        });

        // Custom in-page confirm dialog. Replaces native confirm() because
        // Chrome/Firefox suppress repeat native dialogs (showing the
        // "Don't allow this page to create more dialogs" tickbox); once the
        // user opts in, every subsequent confirm() silently returns false
        // and "Del" looks broken. A custom overlay can't be suppressed.
        //
        // Honours a per-session opt-out: ticking "Skip further confirmations"
        // stores `skipConfirm:rate-delete` in sessionStorage so subsequent
        // deletes go straight through until the tab is closed.
        function rateConfirm(message) {
            return new Promise(resolve => {
                if (sessionStorage.getItem('skipConfirm:rate-delete') === '1') {
                    resolve(true);
                    return;
                }

                const overlay = document.createElement('div');
                overlay.style.cssText = `
                    position: fixed; inset: 0;
                    background: rgba(0,0,0,0.45);
                    z-index: 100000;
                    display: flex; align-items: center; justify-content: center;
                `;

                overlay.innerHTML = `
                    <div role="dialog" aria-modal="true"
                         style="background:#fff;border-radius:10px;max-width:420px;width:90%;
                                box-shadow:0 12px 36px rgba(0,0,0,0.25);overflow:hidden;
                                font-family:inherit;">
                        <div style="padding:18px 20px 4px;">
                            <h5 style="margin:0 0 10px;font-size:17px;font-weight:600;color:#212529;">
                                Confirm delete
                            </h5>
                            <p style="margin:0;color:#495057;font-size:14px;line-height:1.45;">
                                ${message}
                            </p>
                        </div>
                        <label style="display:flex;align-items:center;gap:8px;
                                      padding:12px 20px;margin:8px 0 0;cursor:pointer;
                                      font-size:13px;color:#6c757d;">
                            <input type="checkbox" id="rateConfirmSkip"
                                   style="width:16px;height:16px;cursor:pointer;">
                            Don't ask me again this session
                        </label>
                        <div style="display:flex;gap:10px;justify-content:flex-end;
                                    padding:14px 20px 18px;border-top:1px solid #e9ecef;">
                            <button type="button" id="rateConfirmCancel"
                                    style="background:#f1f3f5;border:1px solid #dee2e6;color:#212529;
                                           padding:8px 18px;border-radius:6px;font-size:14px;
                                           cursor:pointer;">
                                Cancel
                            </button>
                            <button type="button" id="rateConfirmOk"
                                    style="background:#dc3545;border:1px solid #dc3545;color:#fff;
                                           padding:8px 18px;border-radius:6px;font-size:14px;
                                           cursor:pointer;font-weight:500;">
                                Delete
                            </button>
                        </div>
                    </div>
                `;

                document.body.appendChild(overlay);

                const close = (result) => {
                    if (result && overlay.querySelector('#rateConfirmSkip').checked) {
                        sessionStorage.setItem('skipConfirm:rate-delete', '1');
                    }
                    overlay.remove();
                    document.removeEventListener('keydown', onKey);
                    resolve(result);
                };

                const onKey = (e) => {
                    if (e.key === 'Escape') close(false);
                    if (e.key === 'Enter')  close(true);
                };

                overlay.querySelector('#rateConfirmOk').addEventListener('click', () => close(true));
                overlay.querySelector('#rateConfirmCancel').addEventListener('click', () => close(false));
                overlay.addEventListener('click', (e) => { if (e.target === overlay) close(false); });
                document.addEventListener('keydown', onKey);

                overlay.querySelector('#rateConfirmOk').focus();
            });
        }

        // Delete rate (matches old system: deletes all 6 records for the route/country/priority)
        async function deleteRate(routenum, countrydialcode, priority) {
            const ok = await rateConfirm(`Are you sure you want to delete the rate for UK r${routenum}?`);
            if (!ok) {
                return;
            }

            fetch(`${apiBaseUrl}/user/${userId}/${routenum}/${countrydialcode}?priority=${priority}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // No success toast — see addRate for rationale.
                    location.reload();
                } else {
                    showToast('Failed: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to delete rate', 'error');
            });
        }

        // Toast notification
        function showToast(message, type) {
            const existingToasts = document.querySelectorAll('.custom-toast');
            existingToasts.forEach(t => t.remove());

            let toastContainer = document.getElementById('customToastContainer');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'customToastContainer';
                toastContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 99999;';
                document.body.appendChild(toastContainer);
            }

            const toast = document.createElement('div');
            toast.className = 'custom-toast';
            const bgColor = type === 'success' ? '#198754' : '#dc3545';
            const icon = type === 'success' ? '✓' : '✕';

            toast.style.cssText = `
                background-color: ${bgColor};
                color: white;
                padding: 15px 20px;
                border-radius: 8px;
                margin-bottom: 10px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                display: flex;
                align-items: center;
                gap: 10px;
                min-width: 300px;
                max-width: 500px;
                animation: slideIn 0.3s ease-out;
                font-size: 14px;
            `;

            toast.innerHTML = `
                <span style="font-size: 18px; font-weight: bold;">${icon}</span>
                <span style="flex: 1;">${message}</span>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; color: white; cursor: pointer; font-size: 18px; padding: 0; margin-left: 10px;">&times;</button>
            `;

            toastContainer.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease-in';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }
    </script>

    <style>
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }

        .rateForm input[type="text"] {
            border: 1px solid #ccc;
            padding: 2px 5px;
            border-radius: 3px;
        }
    </style>

</div>
