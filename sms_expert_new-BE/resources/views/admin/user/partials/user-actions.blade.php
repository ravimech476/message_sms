<br>
<div class="card mb-4 border rounded">
    <div class="card-body">
        <h5 class="card-title">User Actions</h5>

        <div class="d-flex flex-wrap align-items-center gap-2">
            {{-- Send Login Details --}}
            <form method="POST" action="{{ route('admin.send.login', ['id' => $record->id]) }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm"
                    onclick="return confirm('Are you sure you want to send login details?')">
                    Send Login Details
                </button>
            </form>

            {{-- Month Report --}}
            <form action="{{ route('set.report.session.month') }}" method="POST" target="_blank">
                @csrf
                <input type="hidden" name="userref" value="{{ $record->bigid ?? '' }}">
                <input type="hidden" name="username" value="{{ $record->uname ?? '' }}">
                <button type="submit" class="btn btn-sm btn-dark">Month</button>
            </form>

            {{-- Day Report --}}
            <form action="{{ route('set.report.session.day') }}" method="POST" target="_blank">
                @csrf
                <input type="hidden" name="userref" value="{{ $record->bigid ?? '' }}">
                <input type="hidden" name="username" value="{{ $record->uname ?? '' }}">
                <button type="submit" class="btn btn-sm btn-dark">Day</button>
            </form>

            {{-- Kill User --}}
            <a href="{{ route('user.kill', ['username' => $record->uname, 'userbigid' => $record->bigid, 'killrecord' => 'ohyes']) }}"
                class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to kill this user?')">
                Kill
            </a>

            {{-- Close Window --}}
            <a href="{{ route('admin.users') }}" class="btn btn-secondary btn-sm">Close</a>

            {{-- Kill & Close --}}
            <a href="{{ route('user.killclose', ['username' => $record->uname, 'userbigid' => $record->bigid, 'killrecord' => 'ohyes', 'killrecordclose' => 'ohyes']) }}"
                class="btn btn-warning btn-sm"
                onclick="return confirm('Are you sure you want to kill & close this user?')">
                Kill & Close
            </a>
        </div><br>

        {{-- Credentials --}}
        <div class="credentials">
            <label><strong>Username :</strong> {{ $record->uname ?? '' }}</label><br>
            <label><strong>Password :</strong> {{ $record->pword ?? '' }}</label>
        </div>

        {{-- Wallet Track Toggle --}}
        <div class="mt-2">
            <form method="POST"
                action="{{ route('user.trackwallet', ['username' => $record->uname, 'userbigid' => $record->bigid, 'trackwallet' => $setValue]) }}"
                onsubmit="return confirm('Are you sure you want to {{ strtolower($actionText) }} wallet tracking?')">
                @csrf
                <div class="row align-items-center g-2">
                    <div class="col-auto">
                        <label for="walletTrackSwitch" class="fw-semibold mb-0">Wallet Tracked?</label>
                    </div>
                    <div class="col-auto">
                        <div class="form-check form-switch" style="padding-top: 3px;">
                            <input class="form-check-input" type="checkbox" role="switch" id="walletTrackSwitch"
                                onchange="this.form.submit()" style="transform: scale(1.4); cursor: pointer;"
                                {{ $trackwalletText === 'Yes' ? 'checked' : '' }}>
                        </div>
                    </div>
                    <div class="col-auto">
                        <span class="badge {{ $trackwalletText === 'Yes' ? 'bg-success' : 'bg-secondary' }}">
                            {{ $trackwalletText }}
                        </span>
                    </div>
                </div>
            </form>
        </div>

        <br>
        <h5 class="card-title">Account Manager</h5>

        <div class="d-flex gap-2">
            @if ($record->masteruname === $record->uname)
                <div class="alert alert-success d-inline-block py-1 px-2 fs-sm">
                    <strong>Master</strong>
                    (<a
                        href="{{ route('admin.acntmgr.toggle', ['username' => $record->uname, 'userbigid' => $record->bigid, 'changeacntmgr' => 'disable']) }}">Disable</a>
                    /
                    <a target="_blank" href="{{ route('campain.link.redirect', ['username' => $record->uname]) }}">Acc
                        Mgr</a>)
                </div>
            @else
                <div class="alert alert-secondary d-inline-block py-1 px-2 fs-sm">
                    <strong>Disabled</strong>
                    (<a
                        href="{{ route('admin.acntmgr.toggle', ['username' => $record->uname, 'userbigid' => $record->bigid, 'changeacntmgr' => 'enable']) }}">Enable
                        + Make Master</a> /
                    <a target="_blank" href="{{ route('campain.link.redirect', ['username' => $record->uname]) }}">Acc
                        Mgr</a>)
                </div>
            @endif
        </div>
        <h5 class="card-title">Sub Accounts</h5>

        @if ($subAccounts->count() > 0)
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#subAccountModal">
                View Sub Account
            </button>
        @else
            <span class="text-muted">No Sub Accounts Found</span>
        @endif
        {{-- ================= Modal ================= --}}
        <div class="modal fade" id="subAccountModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">

                    <div class="modal-header">
                        <h4 class="modal-title">Sub Accounts of <b>{{ urldecode($record->contactname ?? '') }}</b></h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Client Name</th>
                                    <th>Contact Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($subAccounts as $sub)
                                    <tr>
                                        <td>{{ urldecode($sub->busname) }}</td>
                                        <td>{{ urldecode($sub->contactname) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>

        <br><br>
        <h5 class="card-title">Join.Me Links</h5>

        <div class="d-flex flex-wrap align-items-center gap-3">
            <div class="d-flex align-items-center">
                <a class="btn btn-sm btn-info me-2"
                    href="{{ route('user.dashboard.link', ['username' => $record->uname]) }}" target="_blank">
                    Dashboard
                </a>
                <a class="btn btn-sm btn-secondary" target="_blank"
                    href="{{ route('campain.link.redirect', ['username' => $record->uname]) }}">
                    Campaign Manager
                </a>
            </div>
        </div>
    </div>
</div>
<!-- Modal MUST be here (outside container, near body end) -->
<div class="modal fade" id="migrationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Redirect Notice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

            </div>

            <div class="modal-body">
                Your account is available in the old SMS Expert System.
                Click OK to continue.
            </div>

            <div class="modal-footer">
                <button type="button" id="confirmMigration" class="btn btn-primary">
                    OK
                </button>
            </div>

        </div>
    </div>
</div>
