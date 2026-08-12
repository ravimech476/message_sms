@extends('admin.layouts.app')

@section('title', 'Migration Batch History')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Migration Batch: {{ $batchId }}</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.migration.campaign-files') }}">Campaign Files</a></li>
                        <li class="breadcrumb-item active">Batch History</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row">
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-0">{{ $stats['total'] }}</h3>
                    <small class="text-muted">Total Files</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center bg-success text-white">
                <div class="card-body">
                    <h3 class="mb-0">{{ $stats['completed'] }}</h3>
                    <small>Completed</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center bg-info text-white">
                <div class="card-body">
                    <h3 class="mb-0">{{ $stats['skipped'] }}</h3>
                    <small>Skipped</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center bg-danger text-white">
                <div class="card-body">
                    <h3 class="mb-0">{{ $stats['failed'] }}</h3>
                    <small>Failed</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center bg-warning">
                <div class="card-body">
                    <h3 class="mb-0">{{ $stats['pending'] + $stats['processing'] }}</h3>
                    <small>Pending</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    @php
                        $progress = $stats['total'] > 0 ? round((($stats['completed'] + $stats['skipped'] + $stats['failed']) / $stats['total']) * 100) : 0;
                    @endphp
                    <h3 class="mb-0">{{ $progress }}%</h3>
                    <small class="text-muted">Progress</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Files Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Migration Files</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Filename</th>
                                    <th>User BigID</th>
                                    <th>Direction</th>
                                    <th>Status</th>
                                    <th>File Size</th>
                                    <th>Message</th>
                                    <th>Completed At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($migrations as $migration)
                                <tr>
                                    <td><code>{{ $migration->filename }}</code></td>
                                    <td><code>{{ Str::limit($migration->user_bigid, 16) }}</code></td>
                                    <td>
                                        @if($migration->direction == 'old_to_new')
                                            <span class="badge bg-primary">Old → New</span>
                                        @else
                                            <span class="badge bg-warning text-dark">New → Old</span>
                                        @endif
                                    </td>
                                    <td>
                                        @switch($migration->status)
                                            @case('pending')
                                                <span class="badge bg-secondary">Pending</span>
                                                @break
                                            @case('processing')
                                                <span class="badge bg-info">Processing</span>
                                                @break
                                            @case('completed')
                                                <span class="badge bg-success">Completed</span>
                                                @break
                                            @case('skipped')
                                                <span class="badge bg-warning text-dark">Skipped</span>
                                                @break
                                            @case('failed')
                                                <span class="badge bg-danger">Failed</span>
                                                @break
                                        @endswitch
                                    </td>
                                    <td>
                                        @if($migration->file_size)
                                            {{ number_format($migration->file_size / 1024, 2) }} KB
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        <small>{{ Str::limit($migration->status_message, 50) }}</small>
                                    </td>
                                    <td>
                                        {{ $migration->completed_at ? $migration->completed_at->format('d M Y, H:i:s') : '-' }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No migration records found</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $migrations->links() }}
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <a href="{{ route('admin.migration.campaign-files') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Migration
            </a>
        </div>
    </div>
</div>
@endsection
