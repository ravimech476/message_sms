<div class="card mb-4 border">
    <div class="card-body">
        <h5 class="card-title">Outstanding Invoices Buy...</h5>

        <div class="mb-3">
            {{-- Live/Staff buttons --}}
            <form method="POST" action="{{ route('admin.invoice.login', ['id' => $record->id]) }}" target="_blank"
                class="d-inline">
                @csrf
                <button type="submit" class="btn btn-success btn-sm">Live/Staff</button>
            </form>

            <form method="POST" action="{{ route('admin.invoice.login', ['id' => $record->id]) }}" target="_blank"
                class="d-inline">
                @csrf
                <button type="submit" class="btn btn-secondary btn-sm">Live/Client</button>
            </form>
        </div>

        {{-- Invoices List --}}
        @foreach ($invoices as $outstand)
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2 small">
                <strong> - {{ $outstand->id }}</strong>
                (£{{ number_format($outstand->easilyamount, 2) }},
                {{ \Carbon\Carbon::createFromTimestamp($outstand->invoicedate, 'Europe/London')->format('D jS M y H:i') }})
                &nbsp;

                {{-- PayPal Form --}}
                <form method="POST" action="{{ route('admin.invoice.paypal', $outstand->id) }}"
                    style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-link btn-sm text-success p-0 m-0 align-baseline"
                        onclick="return confirm('Process PayPal payment for invoice #{{ $outstand->id }}? This will mark the invoice as paid and credit the wallet.')">
                        PayPal
                    </button>
                </form> /

                {{-- BACS Form --}}
                <form method="POST" action="{{ route('admin.invoice.bacs', $outstand->id) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-link btn-sm text-primary p-0 m-0 align-baseline"
                        onclick="return confirm('Process BACS payment for invoice #{{ $outstand->id }}? This will mark the invoice as paid and credit the wallet.')">
                        BACS
                    </button>
                </form> /

                {{-- Cancel Form --}}
                <form method="POST" action="{{ route('admin.invoice.cancel', $outstand->id) }}"
                    style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-link btn-sm text-danger p-0 m-0 align-baseline"
                        onclick="return confirm('Are you sure you want to cancel invoice {{ $outstand->id }}?')">
                        Cancel
                    </button>
                </form> /

                {{-- Download Invoice --}}
                <a href="{{ route('admin.invoice.download', $outstand->id) }}" target="_blank"
                    class="btn btn-link btn-sm text-info p-0 m-0 align-baseline" title="Download Invoice">
                    <i class="bi bi-download"></i> Download
                </a>
            </div>
        @endforeach

        {{-- <div class="mb-3">
            <form method="POST" action="{{ route('admin.update.maxcard', $record->id) }}"
                class="d-flex align-items-end gap-2">
                @csrf
                @method('PUT')
                <div class="flex-grow-1">
                    <label for="maxCardAmount" class="form-label fw-semibold">
                        Max Card/PayPal Amount (incl VAT):
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">£</span>
                        <input type="number" class="form-control" id="maxCardAmount" name="maxcardpurchase"
                            step="1" min="0" value="{{ $user_options->maxcardpurchase ?? 0 }}"
                            placeholder="Enter amount">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Update</button>
            </form>
        </div> --}}
        <div class="mb-3">
            <form method="POST" action="{{ route('admin.update.maxcard', $record->id) }}"
                class="d-flex align-items-end gap-2">
                @csrf
                @method('PUT')

                <div style="width: 300px;">
                    <label for="maxCardAmount" class="form-label fw-semibold">
                        Max Card/PayPal Amount (incl VAT):
                    </label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">£</span>
                        <input type="number" class="form-control form-control-sm" 
                        id="maxCardAmount"
                        name="maxcardpurchase"
                        step="1"
                        min="0"
                        value="{{ $user_options->maxcardpurchase ?? 0 }}"
                        placeholder="Enter amount">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-sm">Update</button> <!-- same height as input -->
            </form>
        </div>

    </div>
</div>
