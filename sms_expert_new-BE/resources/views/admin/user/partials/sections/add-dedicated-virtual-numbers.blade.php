<div class="card mb-4 border">
    <div class="card-body">
        <div class="buy-stuff-links p-3 mb-3"
            style="background-color: #f8f9fa; border-radius: 8px; border-left: 4px solid #0d6efd;">
            <strong style="color: #0d6efd; font-size: 1.1em;">🛒 Buy Stuff Links</strong><br>
            <div class="mt-2">
                <span class="fw-semibold" style="color: #495057;">Reg:</span>
                <form method="POST" action="{{ route('admin.keyword.login', ['id' => $record->id]) }}" target="_blank"
                    class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-success me-2 mb-1">
                        <i class="bi bi-key-fill"></i> New Keyword
                    </button>
                </form>
                <br class="d-md-none">
                <span class="fw-semibold" style="color: #495057;">Buy:</span>

                <form method="POST" action="{{ route('admin.invoice.login', ['id' => $record->id]) }}" target="_blank"
                    class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary me-2 mb-1">
                        <i class="bi bi-chat-dots-fill"></i> SMS
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.upgrade.login', ['id' => $record->id]) }}" target="_blank"
                    class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-warning me-2 mb-1">
                        <i class="bi bi-arrow-up-circle-fill"></i> Upgrade
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.nfc.login', ['id' => $record->id]) }}" target="_blank"
                    class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-info me-2 mb-1">
                        <i class="bi bi-wifi"></i> NFC Starter Pack
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.virtual.login', ['id' => $record->id]) }}" target="_blank"
                    class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-secondary mb-1">
                        <i class="bi bi-cart-fill"></i> Other Stuff
                    </button>
                </form>
            </div>
        </div>

        <h5 class="card-title mb-4">Add Dedicated Virtual Numbers</h5>


        @if (session('remaining_virts'))
            <div class="alert alert-info">
                <strong>{{ session('remaining_virts') }} {{ session('provider_type') }} Dedicated Virtual Numbers
                    Remaining!</strong>
            </div>
        @endif

        <form action="{{ route('admin.virtual.numbers.add.dedicated') }}" method="POST">
            @csrf

            <input type="hidden" name="user_id" value="{{ $record->id }}">
            <input type="hidden" name="username" value="{{ $record->uname }}">

            <div class="row g-3">
                {{-- Volume --}}
                <div class="col-md-6">
                    <label for="how_many_to_add" class="form-label fw-semibold">Volume</label>
                    <input type="number" class="form-control" id="how_many_to_add" name="how_many_to_add"
                        value="1" min="1" required>
                </div>

                {{-- Country Code --}}
                <div class="col-md-6">
                    <label for="countrycode" class="form-label fw-semibold">Country Code</label>
                    <input type="text" class="form-control" id="countrycode" name="country_code" value="44"
                        placeholder="e.g., 44" required>
                    <small class="text-muted">Enter the country dialing code (e.g., 44 for UK)</small>
                </div>

                {{-- Expiry Date --}}
                <div class="col-md-6">
                    <label for="new_virts_expiry_date" class="form-label fw-semibold">Expiry Date</label>
                    <input type="date" class="form-control" id="new_virts_expiry_date" name="new_virts_expiry_date"
                        value="{{ \Carbon\Carbon::now()->addYear()->format('Y-m-d') }}" required>
                    <small class="text-muted">Default is one year from today</small>
                </div>

                {{-- Pooled Status --}}
                <div class="col-md-6">
                    <label for="new_virts_pooled" class="form-label fw-semibold">Pooled Status</label>
                    <select class="form-select" id="new_virts_pooled" name="new_virts_pooled" required>
                        <option value="0" selected>Not Pooled</option>
                        <option value="1">Pooled</option>
                        <option value="2">Pooled Default</option>
                    </select>
                    <small class="text-muted">0: Not pooled | 1: Pooled | 2: Pooled default</small>
                </div>

                {{-- Provider --}}
                <div class="col-md-6">
                    <label for="add_ded_virt_type" class="form-label fw-semibold">Provider</label>
                    <select class="form-select" id="add_ded_virt_type" name="add_ded_virt_type" required>
                        <option value="NexmoUK" selected>NexmoUK</option>
                        <option value="mBloxUK">mBloxUK</option>
                        <option value="mBirdUSA">mBirdUSA</option>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Add Dedicated Virtual Numbers
                </button>
            </div>
        </form>
    </div>
</div>
