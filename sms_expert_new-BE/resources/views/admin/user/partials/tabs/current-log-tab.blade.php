<div class="tab-pane fade {{ session('activeTab') == 'customer-current-log' ? 'show active' : '' }}"
    id="customer-current-log" role="tabpanel">
    <div class="card border">
        <div class="card-body">
            <h5 class="card-title">Current Logged In Session</h5>

            <div class="table-responsive">
                <table id="customer-current-log-view" class="table table-bordered table-striped table-sm align-middle text-center">
                    <thead>
                        <tr>
                            <th>IP Address</th>
                            <th>Login Date</th>
                            <th colspan="2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($usersSessionLogs as $log)
                            <tr>
                                <td>{{ $log->ip_address ?? '' }}</td>
                                <td>
                                    @if (!empty($log->login_date))
                                        @if (is_numeric($log->login_date))
                                            {{ \Carbon\Carbon::createFromTimestamp($log->login_date)->format('d M Y h:i:s') }}
                                        @else
                                            {{ \Carbon\Carbon::parse($log->login_date)->format('d M Y h:i:s') }}
                                        @endif
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.current.force.logout', ['userbigid' => $log->big_id]) }}"
                                        class="btn btn-sm btn-outline-success">
                                        Force Logout
                                    </a>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.force.logout.block') }}">
                                        @csrf
                                        <input type="hidden" name="users_session_logs_id" value="{{ $log->id }}">
                                        <input type="hidden" name="itaggcustid" value="{{ $log->itaggcustid }}">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            Force Logout & Block
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-muted text-center">No active sessions found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>