<div class="tab-pane fade {{ session('activeTab') == 'customer-wallet-balance' ? 'show active' : '' }}"
    id="customer-wallet-balance" role="tabpanel">
    <div class="bs-stepper-content">
        <div id="test-l-1" role="tabpanel" class="bs-stepper-pane" aria-labelledby="stepper1trigger1">

            @php
                $bought = $record->smsg_wallet ?? 0;
                $used = ($record->smsg_server1_sent ?? 0) + ($record->smsg_server2_sent ?? 0);
                $remaining = $bought - $used;
            @endphp

            {{-- Wallet Summary --}}
            <div class="mb-4">
                <h5 class="fw-bold">
                    Bought &pound;{{ number_format($bought ?? 0, 3, '.', '') }}
                    &nbsp; Used &pound;{{ number_format($used ?? 0, 3, '.', '') }}
                    &nbsp; Remaining &pound;{{ number_format($remaining ?? 0, 3, '.', '') }}
                </h5>
            </div>

            {{-- Update Wallet Balance --}}
            <form action="{{ route('admin.wallet.update', $record->id) }}" method="POST" class="mb-5">
                @csrf
                @method('PUT')
                <h5 class="fw-bold mb-3">Wallet Balance</h5>
                {{--
                    IMPORTANT — DO NOT change these money/limit fields back to <input type="number">.
                    Reasons (both were regressions we must not reintroduce via reused code):
                      1) type="number" shows browser up/down SPINNER ARROWS, which were removed before.
                      2) type="number" applies min/max/step restrictions. Real customer values are huge
                         (e.g. FLR's Daily Bulk SMS Limit = 600000). There must be NO upper limit.
                    type="text" + inputmode gives a numeric keypad on mobile with NO spinners and NO
                    min/max/step cap. Values are validated server-side (WalletUserController: numeric).
                --}}
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold theme-label-color">Wallet</label>
                        <input type="text" name="smsg_wallet" inputmode="decimal" class="form-control"
                            value="{{ number_format($record->smsg_wallet ?? 0, 4, '.', '') }}" required>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>

            {{-- Wallet Loan & Daily Bulk SMS Limit --}}
            <form action="{{ route('admin.wallet.loan', $record->id) }}" method="POST" class="mb-5">
                @csrf
                @method('PUT')
                <h5 class="fw-bold mb-3">Wallet Loan & Daily Bulk SMS Limit</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold theme-label-color">Wallet Loan</label>
                        {{-- text+inputmode, NOT number: no spinner arrows, no min/max/step cap (see note above). --}}
                        <input type="text" name="walletloan" inputmode="decimal" class="form-control"
                            value="{{ number_format($record->walletloan ?? 0, 4, '.', '') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold theme-label-color">Daily Bulk SMS Limit</label>
                        {{-- text+inputmode, NOT number (see note above): NO upper limit (customers go
                             into the hundreds of thousands, e.g. FLR = 600000) and NO spinner arrows.
                             number_format(..., 0, '.', '') keeps it comma-free so the value renders. --}}
                        <input type="text" name="bulk_throughput" inputmode="numeric" class="form-control"
                            value="{{ number_format($record->bulk_throughput ?? 0, 0, '.', '') }}" required>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>

            {{-- Plat Keyword Wallet --}}
            <form action="{{ route('admin.wallet.plat', $record->id) }}" method="POST">
                @csrf
                @method('PUT')
                <h5 class="fw-bold mb-3">Plat Keyword Wallet</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold theme-label-color">Plat (api/mms/60300)?</label>
                        <select name="platinumaccess" class="form-select" required>
                            <option value="y" {{ $record->platinumaccess == 'y' ? 'selected' : '' }}>Yes</option>
                            <option value="n" {{ $record->platinumaccess == 'n' ? 'selected' : '' }}>No</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold theme-label-color">Plat Keyword Wallet</label>
                        {{-- text+inputmode, NOT number (see note above): no spinner arrows, no min/max/step cap. --}}
                        <input type="text" name="platkeywordwallet" inputmode="numeric" class="form-control"
                            value="{{ $record->platkeywordwallet ?? 0 }}" required>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>

        </div>
    </div>
</div>