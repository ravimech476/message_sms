<div class="tab-pane fade {{ session('activeTab') == 'customer-keywords' ? 'show active' : '' }}"
    id="customer-keywords" role="tabpanel">

    {{-- Platinum Keyword Tools (OLD SYSTEM parity — plat_whois.mes / plat_keyreg.mes /
         plat_keyrenew.mes). Native admin actions on shortcode 60300 for THIS customer:
         check availability, register (charges keyword wallet), renew 12 months. --}}
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h6 class="mb-0">
                <i class="material-icons-outlined align-middle me-1">vpn_key</i>
                Platinum Keyword Tools <span class="text-muted small">(60300)</span>
            </h6>
        </div>
        <div class="card-body">
            <div class="row g-4">
                {{-- Whois + Register share one keyword input (buttons use formaction) --}}
                <div class="col-12 col-lg-7">
                    <form method="POST" action="{{ route('admin.user.keyword-whois', $record->id) }}">
                        @csrf
                        <label class="form-label">Keyword (3–16 letters/numbers)</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="keyword" maxlength="16"
                                pattern="[A-Za-z0-9]{3,16}" required style="text-transform:uppercase;"
                                placeholder="e.g. WIN2WIN"
                                oninput="this.value = this.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase()">
                            <button type="submit" class="btn btn-outline-secondary"
                                formaction="{{ route('admin.user.keyword-whois', $record->id) }}">
                                Whois
                            </button>
                            <button type="submit" class="btn btn-primary"
                                formaction="{{ route('admin.user.keyword-register', $record->id) }}"
                                onclick="return confirm('Register this keyword on 60300 for this customer? One keyword credit will be charged to their wallet.\n\nAre you sure?');">
                                Register
                            </button>
                        </div>
                        <span class="text-muted small">Whois checks availability. Register creates it and charges one keyword credit.</span>
                    </form>
                </div>

                {{-- Renew --}}
                <div class="col-12 col-lg-5">
                    <form method="POST" action="{{ route('admin.user.keyword-renew', $record->id) }}"
                        onsubmit="return confirm('Renew this keyword on 60300 for 12 months? One keyword credit will be charged to this customer\'s wallet.\n\nAre you sure?');">
                        @csrf
                        <label class="form-label">Renew Existing Keyword</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="keyword" maxlength="16"
                                pattern="[A-Za-z0-9]{3,16}" required style="text-transform:uppercase;"
                                placeholder="existing keyword"
                                oninput="this.value = this.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase()">
                            <button type="submit" class="btn btn-warning">Renew 12 months</button>
                        </div>
                        <span class="text-muted small">Extends expiry by one year. Charges one keyword credit.</span>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Pricing & Packages (OLD SYSTEM parity — NFC/upgrade prices, max card, sub keywords,
         platinum access). These columns drive the native Upgrade / NFC / keyword pages. --}}
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h6 class="mb-0">
                <i class="material-icons-outlined align-middle me-1">sell</i>
                Pricing &amp; Packages
            </h6>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.user.update-pricing', $record->id) }}" method="POST" class="row g-3 align-items-end">
                @csrf
                @method('PUT')
                <div class="col-6 col-md-3">
                    <label class="form-label">NFC Starter Pack Price (£)</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="smsinprice"
                        value="{{ $record->smsinprice ?? 0 }}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Silver Upgrade Price (£)</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="silverprice"
                        value="{{ $record->silverprice ?? 0 }}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Gold Upgrade Price (£)</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="goldprice"
                        value="{{ $record->goldprice ?? 0 }}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Platinum Upgrade Price (£)</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="platinumprice"
                        value="{{ $record->platinumprice ?? 0 }}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Sub Keywords (all 60300/80809)</label>
                    <input type="number" step="1" min="0" class="form-control" name="subkeywords" placeholder="leave blank to keep">
                </div>
                <div class="col-6 col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="material-icons-outlined align-middle me-1" style="font-size: 18px;">save</i>
                        Save Pricing
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Routing Configuration (OLD SYSTEM parity — Route 1/2/3 Charge/Tag/Route/HLR%/HLRUnknwn
         + Split%). Saves to users.1s_preferredroute,dohlr,randomizeroute,routetag,sendhlrunknowns,
         chargetype1 (+2/+3) and preferredroutesplit(2). --}}
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h6 class="mb-0">
                <i class="material-icons-outlined align-middle me-1">alt_route</i>
                Routing Configuration
            </h6>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.user.update-routing', $record->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-2">
                        <thead class="table-light">
                            <tr><th>#</th><th>Charge</th><th>Tag</th><th>Route</th><th>HLR%</th><th>HLR Unknown</th><th>Randomize</th></tr>
                        </thead>
                        <tbody>
                            @php
                                $routeRows = [
                                    1 => ['route' => $record->{'1s_preferredroute'} ?? '', 'hlr' => $record->dohlr ?? '', 'rand' => $record->randomizeroute ?? '', 'tag' => $record->routetag ?? '', 'unk' => $record->sendhlrunknowns ?? '', 'charge' => $record->chargetype1 ?? 'pps'],
                                    2 => ['route' => $record->preferredroute2 ?? '', 'hlr' => $record->dohlr2 ?? '', 'rand' => $record->randomizeroute2 ?? '', 'tag' => $record->routetag2 ?? '', 'unk' => $record->sendhlrunknowns2 ?? '', 'charge' => $record->chargetype2 ?? 'pps'],
                                    3 => ['route' => $record->preferredroute3 ?? '', 'hlr' => $record->dohlr3 ?? '', 'rand' => $record->randomizeroute3 ?? '', 'tag' => $record->routetag3 ?? '', 'unk' => $record->sendhlrunknowns3 ?? '', 'charge' => $record->chargetype3 ?? 'pps'],
                                ];
                            @endphp
                            @foreach ($routeRows as $i => $r)
                                <tr>
                                    <td class="fw-semibold">{{ $i }}</td>
                                    <td>
                                        <select class="form-select form-select-sm" name="chargetype{{ $i }}" style="width:80px;">
                                            <option value="pps" {{ $r['charge'] === 'pps' ? 'selected' : '' }}>pps</option>
                                            <option value="ppd" {{ $r['charge'] === 'ppd' ? 'selected' : '' }}>ppd</option>
                                        </select>
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm" name="routetag{{ $i }}" value="{{ $r['tag'] }}" style="width:70px;"></td>
                                    <td><input type="text" class="form-control form-control-sm" name="preferredroute{{ $i }}" value="{{ $r['route'] }}" style="width:90px;"></td>
                                    <td><input type="number" class="form-control form-control-sm" name="dohlr{{ $i }}" value="{{ $r['hlr'] }}" style="width:70px;"></td>
                                    <td>
                                        <select class="form-select form-select-sm" name="sendhlrunknowns{{ $i }}" style="width:70px;">
                                            <option value="y" {{ $r['unk'] === 'y' ? 'selected' : '' }}>y</option>
                                            <option value="n" {{ $r['unk'] === 'n' ? 'selected' : '' }}>n</option>
                                        </select>
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm" name="randomizeroute{{ $i }}" value="{{ $r['rand'] }}" style="width:70px;"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="row g-3 align-items-end">
                    <div class="col-auto">
                        <label class="form-label">Split %</label>
                        <input type="number" class="form-control" name="preferredroutesplit" value="{{ $record->preferredroutesplit ?? 0 }}" style="width:100px;">
                    </div>
                    <div class="col-auto">
                        <label class="form-label">Split % (2)</label>
                        <input type="number" class="form-control" name="preferredroutesplit2" value="{{ $record->preferredroutesplit2 ?? 0 }}" style="width:100px;">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">
                            <i class="material-icons-outlined align-middle me-1" style="font-size: 18px;">save</i>
                            Save Routing
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Sub-Account & Pooled Numbers (OLD SYSTEM parity — gajkdhg.php:2241/2248).
         Saves to users.subusrkey, numpooledvirtsUK, numpooledvirtsUSA, walletminlevel,
         masteruname, introducerref. --}}
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h6 class="mb-0">
                <i class="material-icons-outlined align-middle me-1">account_tree</i>
                Sub-Account &amp; Pooled Numbers
            </h6>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.user.update-sub-account', $record->id) }}" method="POST" class="row g-3 align-items-end">
                @csrf
                @method('PUT')
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label">Sub User Key</label>
                    <input type="text" class="form-control" name="subusrkey" maxlength="40"
                        value="{{ $record->subusrkey ?? '' }}">
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label">Pooled DedVirts (UK)</label>
                    <input type="number" min="0" class="form-control" name="numpooledvirtsUK"
                        value="{{ $record->numpooledvirtsUK ?? 0 }}">
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label">Pooled DedVirts (USA)</label>
                    <input type="number" min="0" class="form-control" name="numpooledvirtsUSA"
                        value="{{ $record->numpooledvirtsUSA ?? 0 }}">
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label">Min Wallet (Sweep)</label>
                    <input type="number" step="0.000001" class="form-control" name="walletminlevel"
                        value="{{ $record->walletminlevel ?? 0 }}">
                </div>
                <div class="col-6 col-md-6 col-lg-3">
                    <label class="form-label">SubAcc Master Ref (8)</label>
                    <input type="text" class="form-control" name="masteruname" maxlength="8"
                        value="{{ $record->masteruname ?? '' }}">
                </div>
                <div class="col-12 col-md-8 col-lg-6">
                    <label class="form-label">Introducer Ref (32 or 'introducer')</label>
                    <input type="text" class="form-control" name="introducerref" maxlength="32"
                        value="{{ $record->introducerref ?? '' }}">
                </div>
                <div class="col-12 col-md-4 col-lg-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="material-icons-outlined align-middle me-1" style="font-size: 18px;">save</i>
                        Save Sub-Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Send Quick SMS (OLD SYSTEM parity — gajkdhg.php:9581). Native send via this
         customer's OWN account: their sender, their wallet, logged in their smsg_log.
         Reuses the new system's API send pipeline (no external/old-system URL). --}}
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h6 class="mb-0">
                <i class="material-icons-outlined align-middle me-1">send</i>
                Send Quick SMS
            </h6>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.user.quick-send', $record->id) }}" method="POST"
                onsubmit="return confirm('Send this SMS from {{ addslashes(urldecode($record->busname ?? $record->uname ?? 'this customer')) }}\'s account? Their wallet will be charged.');">
                @csrf
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label">Sender ID (From)</label>
                        <input type="text" class="form-control" name="quick_from" maxlength="20" required
                            placeholder="e.g. SMSExpert">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">To (mobile)</label>
                        <input type="text" class="form-control" name="quick_to" maxlength="40" required
                            value="{{ str_replace('+', '', $record->mobilenumber ?? '') }}"
                            placeholder="447xxxxxxxxx">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" name="quick_txt" rows="3" required
                            placeholder="Type your message..."></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="material-icons-outlined align-middle me-1" style="font-size: 18px;">send</i>
                            Send
                        </button>
                        <span class="text-muted small ms-2">Billed to this customer's wallet.</span>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="col-12 mb-3">
        <div class="row g-2">
            <div class="col-12 col-md-auto">
                <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#addKeywordModal">
                    Add Keyword
                </button>
            </div>
        </div>
    </div>

    <table id="keyword_all_view" class="table table-striped table-bordered w-100">
        <thead>
            <tr class="text-center">
                <th>Keyword</th>
                <th>Virtual Number</th>
                <th>Purchased Date</th>
                <th>Expiry Date</th>
                <th>Options</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($keywords as $keyword)
                <tr class="text-center">
                    <td>{{ $keyword->keyword ?? '' }}</td>
                    <td>{{ $keyword->smsshortcode->number ?? '' }}</td>
                    <td>{{ $keyword->purchased ?? '' }}</td>
                    <td>{{ $keyword->expiry ?? '' }}</td>
                    <td>
                        <button type="button" class="btn btn-warning btn-sm"
                            data-bs-toggle="modal" data-bs-target="#editKeywordModal{{ $keyword->id }}">
                            Edit
                        </button>
                        <button type="button" class="btn btn-danger btn-sm"
                            data-bs-toggle="modal" data-bs-target="#deleteKeywordModal{{ $keyword->id }}">
                            Delete
                        </button>
                    </td>
                </tr>

                {{-- Edit Keyword Modal --}}
                @include('admin.user.partials.modals.edit-keyword-modal', ['keyword' => $keyword])

                {{-- Delete Keyword Modal --}}
                @include('admin.user.partials.modals.delete-keyword-modal', ['keyword' => $keyword])
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted">No keywords found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Add Keyword Modal --}}
@include('admin.user.partials.modals.add-keyword-modal')
