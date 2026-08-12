<div id="profile-section" class="card mb-4 border">
    <div class="card-body">
        <h5 class="card-title mb-4">Profile Sections...</h5>
        
        @if (session('success_profile'))
            <div id="flash-message" class="alert alert-success">{{ session('success_profile') }}</div>
        @endif
        @if (session('error_profile'))
            <div id="flash-error-message" class="alert alert-danger">{{ session('error_profile') }}</div>
        @endif

        {{-- Disabled? --}}
        <div class="row g-3 align-items-center mb-2">
            <div class="col-md-2 col-6 fw-semibold">Disabled?</div>
            <div class="col-md-4 col-6">
                <form method="GET" action="{{ route('admin.disable.toggle') }}" class="d-flex align-items-center gap-2 m-0">
                    <input type="hidden" name="username" value="{{ $record->uname }}">
                    <input type="hidden" name="userbigid" value="{{ $record->bigid }}">
                    <input type="hidden" name="setdisabled" value="{{ $record->bit_disabled == '0' ? '1' : '0' }}">

                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" style="transform: scale(1.3); cursor: pointer;"
                            onchange="this.form.setdisabled.value = this.checked ? 1 : 0; this.form.submit();"
                            {{ $record->bit_disabled == '1' ? 'checked' : '' }}>
                    </div>

                    <span class="badge {{ $record->bit_disabled == '1' ? 'bg-success' : 'bg-secondary' }}">
                        {{ $record->bit_disabled == '1' ? 'Yes' : 'No' }}
                    </span>
                </form>
            </div>
        </div>

        {{-- Account Locked? --}}
        <div class="row g-3 align-items-center mb-2">
            <div class="col-md-2 col-6 fw-semibold">Account Locked?</div>
            <div class="col-md-4 col-6">
                <form method="GET" action="{{ route('admin.account.lock') }}" class="d-flex align-items-center gap-2 m-0">
                    <input type="hidden" name="username" value="{{ $record->uname }}">
                    <input type="hidden" name="userbigid" value="{{ $record->bigid }}">
                    <input type="hidden" name="clientcommfail" value="{{ $user_options && $user_options->clientcommfail == 'n' ? 'y' : 'n' }}">

                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" style="transform: scale(1.3); cursor: pointer;"
                            onchange="this.form.clientcommfail.value = this.checked ? 'y' : 'n'; this.form.submit();"
                            {{ $user_options && $user_options->clientcommfail == 'y' ? 'checked' : '' }}>
                    </div>

                    <span class="badge {{ $user_options && $user_options->clientcommfail == 'y' ? 'bg-success' : 'bg-secondary' }}">
                        {{ $user_options && $user_options->clientcommfail == 'y' ? 'Yes' : 'No' }}
                    </span>
                </form>
            </div>
        </div>

        {{-- Profile Locked? --}}
        <div class="row g-3 align-items-center mb-2">
            <div class="col-md-2 col-6 fw-semibold">Profile Locked?</div>
            <div class="col-md-4 col-6">
                <form method="GET" action="{{ route('admin.profile.lock') }}" class="d-flex align-items-center gap-2 m-0">
                    <input type="hidden" name="username" value="{{ $record->uname }}">
                    <input type="hidden" name="userbigid" value="{{ $record->bigid }}">
                    <input type="hidden" name="profileupdate_lockout" value="{{ $user_options && $user_options->profileupdate_lockout == '0' ? '1' : '0' }}">

                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" style="transform: scale(1.3); cursor: pointer;"
                            onchange="this.form.profileupdate_lockout.value = this.checked ? '1' : '0'; this.form.submit();"
                            {{ $user_options && $user_options->profileupdate_lockout == '1' ? 'checked' : '' }}>
                    </div>

                    <span class="badge {{ $user_options && $user_options->profileupdate_lockout == '1' ? 'bg-success' : 'bg-secondary' }}">
                        {{ $user_options && $user_options->profileupdate_lockout == '1' ? 'Yes' : 'No' }}
                    </span>
                </form>
            </div>
        </div>

        {{-- API Type? --}}
        <div class="row g-3 align-items-center mb-2">
            <div class="col-md-2 col-6 fw-semibold">API Type?</div>
            <div class="col-md-4 col-6">
                <form method="GET" action="{{ route('admin.api.type') }}" class="d-flex align-items-center gap-2 m-0">
                    <input type="hidden" name="username" value="{{ $record->uname }}">
                    <input type="hidden" name="userbigid" value="{{ $record->bigid }}">
                    <input type="hidden" name="apitype" value="{{ $user_options && $user_options->apitype == 'o' ? 'w' : 'o' }}">

                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" style="transform: scale(1.3); cursor: pointer;"
                            onchange="this.form.apitype.value = this.checked ? 'w' : 'o'; this.form.submit();"
                            {{ $user_options && $user_options->apitype == 'w' ? 'checked' : '' }}>
                    </div>

                    <span class="badge {{ $user_options && $user_options->apitype == 'w' ? 'bg-success' : 'bg-secondary' }}">
                        {{ $user_options && $user_options->apitype == 'w' ? 'Yes' : 'No' }}
                    </span>
                </form>
            </div>
        </div>

        {{-- IP Restriction? --}}
        <div class="row g-3 align-items-center mb-2">
            <div class="col-md-2 col-6 fw-semibold">IP Restriction?</div>
            <div class="col-md-4 col-6">
                <form method="GET" action="{{ route('admin.ip.address.restriction') }}" class="d-flex align-items-center gap-2 m-0">
                    <input type="hidden" name="userbigid" value="{{ $record->bigid }}">
                    <input type="hidden" name="ip_address_restriction" value="{{ $record->ip_address_restriction == '1' ? '2' : '1' }}">

                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" style="transform: scale(1.3); cursor: pointer;"
                            onchange="this.form.ip_address_restriction.value = this.checked ? '1' : '2'; this.form.submit();"
                            {{ $record->ip_address_restriction == '1' ? 'checked' : '' }}>
                    </div>

                    <span class="badge {{ $record->ip_address_restriction == '1' ? 'bg-success' : 'bg-secondary' }}">
                        {{ $record->ip_address_restriction == '1' ? 'Yes' : 'No' }}
                    </span>
                </form>
            </div>
        </div>

        {{-- Force Logout --}}
        <div class="row g-2 align-items-center mb-2">
            <div class="col-md-2 col-6 fw-semibold">Force Logout?</div>
            <div class="col-md-4 col-6">
                <div class="d-flex flex-column flex-md-row gap-1 justify-content-md-end">
                    <a href="{{ route('admin.force.logout', ['userbigid' => $record->bigid]) }}"
                        class="btn btn-outline-primary btn-sm px-2 py-1 w-100 w-md-auto">
                        Force Logout All Session
                    </a>
                </div>
            </div>
        </div>

        {{-- Profile Update --}}
        <div class="row g-2 align-items-center mb-2">
            <div class="col-md-2 col-6 fw-semibold">Profile Update?</div>
            <div class="col-md-4 col-6">
                @if ($user_options && $user_options->profileupdate_lockout == 1)
                    <div class="d-flex flex-column flex-md-row gap-1 justify-content-md-end">
                        <a href="{{ route('send.profile.update.email', ['id' => $record->id]) }}"
                            onclick="return confirm('Send profile update email?')"
                            class="btn btn-outline-success btn-sm px-2 py-1 w-100 w-md-auto">
                            Send Email
                        </a>
                        <a href="{{ route('admin.profile.update', ['userbigid' => $record->bigid]) }}"
                            onclick="return confirm('Unlock profile update?')"
                            class="btn btn-outline-success btn-sm px-2 py-1 w-100 w-md-auto">
                            Unlock Profile
                        </a>
                    </div>
                @else
                    <div class="d-flex justify-content-md-end">
                        <button class="btn btn-outline-secondary btn-sm px-2 py-1 w-100 w-md-auto text-dark" disabled>
                            No Profile Update
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>