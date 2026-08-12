<div class="card mb-4 border">
    <div class="card-body">
        <h5 class="card-title">IP Address Restriction</h5>

        <div id="ajax-msg"></div>

        <form id="ip-form" method="POST" action="{{ route('admin.ip.store') }}">
            @csrf
            <input type="hidden" name="bigid" value="{{ $record->bigid }}">
            <input type="hidden" name="record_id" id="record_id" value="">

            <div class="row g-2 align-items-center mb-3">
                <div class="col-auto">
                    <label for="ip_address" class="col-form-label-sm">IP Address</label>
                </div>
                <div class="col-sm-3">
                    <input type="text" class="form-control form-control-sm" id="ip_address"
                        name="ip_address" placeholder="Enter IP Address" required>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm" id="submit-btn">Submit</button>
                </div>
            </div>

            <div id="ip-error" class="text-danger small mt-1"></div>
        </form>

        <h5 class="card-title">Existing IP Addresses</h5>

        <table class="table table-bordered table-sm">
            <thead>
                <tr class="text-center">
                    <th>IP Address</th>
                    <th>Created Date</th>
                    <th colspan="2" class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ipAddresses as $ip)
                    <tr class="text-center">
                        <td>{{ $ip->ip_address ?? '' }}</td>
                        <td>
                            @php
                                $createdDate = '-';
                                if (!empty($ip->created)) {
                                    try {
                                        // Try YmdHis format first (e.g., 20251216094806)
                                        if (preg_match('/^\d{14}$/', $ip->created)) {
                                            $createdDate = \Carbon\Carbon::createFromFormat('YmdHis', $ip->created)->format('d M Y h:i:s');
                                        }
                                        // Try standard datetime format
                                        elseif (strtotime($ip->created) !== false) {
                                            $createdDate = \Carbon\Carbon::parse($ip->created)->format('d M Y h:i:s');
                                        }
                                        // If it's already a Carbon instance
                                        elseif ($ip->created instanceof \Carbon\Carbon) {
                                            $createdDate = $ip->created->format('d M Y h:i:s');
                                        }
                                        else {
                                            $createdDate = $ip->created;
                                        }
                                    } catch (\Exception $e) {
                                        $createdDate = $ip->created ?? '-';
                                    }
                                }
                            @endphp
                            {{ $createdDate }}
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-success edit-btn"
                                data-id="{{ $ip->id }}" data-ip="{{ $ip->ip_address }}">
                                Edit
                            </button>
                        </td>
                        <td class="text-center">
                            <form method="POST" action="{{ route('admin.ip.delete', $ip->id) }}">
                                @csrf
                                <button onclick="return confirm('Are you sure?')" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center">No IPs found for this account.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
