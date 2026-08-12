<div class="tab-pane fade {{ session('activeTab') == 'customer-current-flag' ? 'show active' : '' }}"
    id="customer-current-flag" role="tabpanel">

    <div class="card shadow-sm border">
        <div class="card-body">

            <h5 class="card-title mb-4">
                {{-- <i class="bi bi-flag me-2"></i> --}}
                Current Migration Flag
            </h5>

            <form method="POST" action="{{ route('customers.flag.update', $record->id) }}">
                @csrf
                @method('PUT')

                <div class="row">

                    <div class="col-md-6">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Migration Flag
                            </label>

                            <select name="migration_flag" class="form-select">
                                <option value="old" {{ $record->migration_flag == 'old' ? 'selected' : '' }}>
                                    Old System
                                </option>

                                <option value="new" {{ $record->migration_flag == 'new' ? 'selected' : '' }}>
                                    New System
                                </option>
                            </select>

                        </div>

                    </div>

                </div>

                <div class="mt-3">

                    <button type="submit" class="btn btn-primary px-4">
                        Update
                    </button>

                </div>

            </form>

        </div>
    </div>

    {{-- Migration Flag Change History --}}
    <div class="card shadow-sm border mt-4">
        <div class="card-body">

            <h5 class="card-title mb-4">
                Migration Flag History
            </h5>

            {{-- Current Migration Status --}}
            <div class="alert {{ $record->migration_flag == 'new' ? 'alert-success' : 'alert-secondary' }} mb-4">
                <strong>Current Status:</strong>
                <span class="badge {{ $record->migration_flag == 'new' ? 'bg-success' : 'bg-secondary' }} ms-2">
                    {{ $record->migration_flag == 'old' ? 'Old System' : 'New System' }}
                </span>
            </div>

            @if(isset($flagLogs) && $flagLogs->count() > 0)
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Old Flag</th>
                                <th>New Flag</th>
                                {{-- <th>Changed By</th> --}}
                                <th>Changed IP</th>
                                <th>Changed At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($flagLogs as $index => $log)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <span class="badge {{ $log->old_flag == 'old' ? 'bg-secondary' : 'bg-success' }}">
                                            {{ $log->old_flag == 'old' ? 'Old system' : 'New system' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $log->new_flag == 'old' ? 'bg-secondary' : 'bg-success' }}">
                                            {{ $log->new_flag == 'old' ? 'Old system' : 'New system' }}
                                        </span>
                                    </td>
                                    {{-- <td>{{ $log->changed_by ?? 'N/A' }}</td> --}}
                                    <td>{{ $log->changed_ip ?? 'N/A' }}</td>
                                    <td>
                                        @if($log->changed_at)
                                            {{ \Carbon\Carbon::parse($log->changed_at)->format('d M Y H:i:s') }}
                                        @elseif($log->created_at)
                                            {{ \Carbon\Carbon::parse($log->created_at)->format('d M Y H:i:s') }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    No migration flag changes recorded yet.
                </div>
            @endif

        </div>
    </div>

</div>
