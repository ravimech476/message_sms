@if(session('server_success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('server_success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if(session('server_error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('server_error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row g-3">
    <!-- Old Server Settings -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header" style="background-color: #ffc107; color: #212529;">
                <h5 class="card-title mb-0">
                    <i class="material-icons-outlined me-2" style="vertical-align: middle;">dns</i>Old Server Settings
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.settings.server.save') }}" method="POST">
                    @csrf
                    <input type="hidden" name="server_type" value="old_server">
                    <input type="hidden" name="redirect_to_tab" value="1">

                    <div class="mb-3">
                        <label class="form-label">Host / IP Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="host"
                            value="{{ old('host', $oldServer->host ?? '') }}"
                            placeholder="e.g., 192.168.1.100 or old.server.com" required>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Port <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="port"
                                    value="{{ old('port', $oldServer->port ?? 22) }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Connection Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="connection_type" required>
                                    <option value="sftp" {{ ($oldServer->connection_type ?? 'sftp') == 'sftp' ? 'selected' : '' }}>SFTP</option>
                                    <option value="ftp" {{ ($oldServer->connection_type ?? '') == 'ftp' ? 'selected' : '' }}>FTP</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username"
                            value="{{ old('username', $oldServer->username ?? '') }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password @if(isset($oldServer) && $oldServer->exists) <small class="text-muted">(leave blank to keep current)</small> @endif</label>
                        <input type="password" class="form-control" name="password"
                            placeholder="{{ isset($oldServer) && $oldServer->exists ? '••••••••' : 'Enter password' }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Campaign Files Path <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="campaign_file_path"
                            value="{{ old('campaign_file_path', $oldServer->campaign_file_path ?? '') }}"
                            placeholder="/var/www/campaigns" required>
                        <small class="text-muted">Full path to the directory containing campaign CSV files</small>
                    </div>

                    <!-- Connection Status -->
                    <div class="mb-3" id="old-server-status">
                        @if(isset($oldServer) && $oldServer->last_tested_at)
                        <div class="alert {{ $oldServer->last_test_status == 'success' ? 'alert-success' : 'alert-danger' }} mb-0">
                            <strong>Last Test:</strong> {{ $oldServer->last_tested_at->format('d M Y, H:i') }}<br>
                            <strong>Status:</strong> {{ ucfirst($oldServer->last_test_status) }}<br>
                            <small>{{ $oldServer->last_test_message }}</small>
                        </div>
                        @endif
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">save</i> Save Settings
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="testServerConnection('old_server')">
                            <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">power</i> Test Connection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- New Server Settings -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header" style="background-color: #198754; color: #fff;">
                <h5 class="card-title mb-0">
                    <i class="material-icons-outlined me-2" style="vertical-align: middle;">dns</i>New Server Settings
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.settings.server.save') }}" method="POST">
                    @csrf
                    <input type="hidden" name="server_type" value="new_server">
                    <input type="hidden" name="redirect_to_tab" value="1">

                    <div class="mb-3">
                        <label class="form-label">Host / IP Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="host"
                            value="{{ old('host', $newServer->host ?? '') }}"
                            placeholder="e.g., 192.168.1.101 or new.server.com" required>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Port <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="port"
                                    value="{{ old('port', $newServer->port ?? 22) }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Connection Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="connection_type" required>
                                    <option value="sftp" {{ ($newServer->connection_type ?? 'sftp') == 'sftp' ? 'selected' : '' }}>SFTP</option>
                                    <option value="ftp" {{ ($newServer->connection_type ?? '') == 'ftp' ? 'selected' : '' }}>FTP</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username"
                            value="{{ old('username', $newServer->username ?? '') }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password @if(isset($newServer) && $newServer->exists) <small class="text-muted">(leave blank to keep current)</small> @endif</label>
                        <input type="password" class="form-control" name="password"
                            placeholder="{{ isset($newServer) && $newServer->exists ? '••••••••' : 'Enter password' }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Campaign Files Path <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="campaign_file_path"
                            value="{{ old('campaign_file_path', $newServer->campaign_file_path ?? '') }}"
                            placeholder="/var/www/campaigns" required>
                        <small class="text-muted">Full path to the directory containing campaign CSV files</small>
                    </div>

                    <!-- Connection Status -->
                    <div class="mb-3" id="new-server-status">
                        @if(isset($newServer) && $newServer->last_tested_at)
                        <div class="alert {{ $newServer->last_test_status == 'success' ? 'alert-success' : 'alert-danger' }} mb-0">
                            <strong>Last Test:</strong> {{ $newServer->last_tested_at->format('d M Y, H:i') }}<br>
                            <strong>Status:</strong> {{ ucfirst($newServer->last_test_status) }}<br>
                            <small>{{ $newServer->last_test_message }}</small>
                        </div>
                        @endif
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">save</i> Save Settings
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="testServerConnection('new_server')">
                            <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">power</i> Test Connection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Info Card -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="mb-3"><i class="material-icons-outlined text-info me-2" style="vertical-align: middle;">info</i>Information</h5>
                <ul class="mb-0">
                    <li>Configure both old and new server SFTP/FTP credentials to enable campaign file migration.</li>
                    <li>The <strong>Campaign Files Path</strong> should point to the directory where campaign CSV files are stored.</li>
                    <li>Test connection before using migration features to ensure credentials are correct.</li>
                    <li>Passwords are stored encrypted in the database.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Quick Link to Migration -->
<div class="row mt-3">
    <div class="col-12">
        <div class="card bg-light">
            <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h6 class="mb-1">Ready to migrate campaign files?</h6>
                    <small class="text-muted">Go to the Campaign File Migration page to start migrating files between servers.</small>
                </div>
                <a href="{{ route('admin.migration.campaign-files') }}" class="btn btn-primary">
                    <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">sync_alt</i> Go to Migration
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function testServerConnection(serverType) {
    const statusDiv = document.getElementById(serverType.replace('_', '-') + '-status');
    statusDiv.innerHTML = '<div class="alert alert-info mb-0"><i class="material-icons-outlined me-2" style="vertical-align: middle;">sync</i>Testing connection...</div>';

    fetch('{{ route("admin.settings.server.test") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ server_type: serverType })
    })
    .then(response => response.json())
    .then(data => {
        const alertClass = data.success ? 'alert-success' : 'alert-danger';
        const icon = data.success ? 'check_circle' : 'cancel';
        statusDiv.innerHTML = `
            <div class="alert ${alertClass} mb-0">
                <i class="material-icons-outlined me-2" style="vertical-align: middle;">${icon}</i>
                <strong>${data.success ? 'Success' : 'Failed'}:</strong> ${data.message}
            </div>
        `;
    })
    .catch(error => {
        statusDiv.innerHTML = '<div class="alert alert-danger mb-0"><i class="material-icons-outlined me-2" style="vertical-align: middle;">error</i>Connection test failed: ' + error.message + '</div>';
    });
}
</script>
