<div class="card border">
    <div class="card-body">
        <h5 class="card-title">Blocked Users</h5>

        <table class="table table-bordered table-sm">
            <thead>
                <tr class="text-center">
                    <th>IP Address</th>
                    <th>Blocked Date</th>
                    <th colspan="2" class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($blockedUsers as $ip)
                    <tr class="text-center">
                        <td>{{ $ip->ip_address ?? '' }}</td>
                        <td>{{ \Carbon\Carbon::createFromFormat('YmdHis', $ip->blocked_date)->format('d M Y h:i:s') }}</td>
                        <td align="center">
                            <form method="POST" action="{{ route('admin.blocked-users.unblock', $ip->id) }}">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-sm btn-danger">Unblock</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center">No blocked users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>