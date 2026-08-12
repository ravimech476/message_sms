@extends('campaign.layouts.app')

@section('title', 'View previous SMS campaigns - Campaign Manager')

@push('style')
    <style>
        .dashboard-container {
            background: #f8fafc;
            margin: -2rem;
            padding: 2rem;
        }

        .page-header {
            background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(8, 145, 178, 0.3);
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            color: white;
        }

        .page-header h4 {
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .page-header p {
            opacity: 0.9;
            margin-bottom: 0;
            font-size: 0.9rem;
        }

        .header-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: 0.5rem;
        }

        .header-btn:hover {
            background: white;
            color: #0891b2;
        }

        .header-btn-primary {
            background: linear-gradient(135deg, #ea6118, #d1520e);
            border: none;
        }

        .header-btn-primary:hover {
            background: white;
            color: #ea6118;
        }

        .search-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .data-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .data-card .card-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 1.5rem;
        }

        .data-card .card-header h5 {
            color: #293b50;
            font-weight: 600;
            margin: 0;
        }

        .form-label {
            color: #293b50;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #0891b2;
            box-shadow: 0 0 0 0.2rem rgba(8, 145, 178, 0.15);
        }

        .btn-search {
            background: linear-gradient(135deg, #0891b2, #0e7490);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(8, 145, 178, 0.4);
            color: white;
        }

        .btn-reset {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #64748b;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-reset:hover {
            background: #e2e8f0;
            color: #293b50;
        }

        .legend-bar {
            background: #f8fafc;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            font-size: 0.85rem;
        }

        .table-custom {
            margin-bottom: 0;
        }

        .table-custom thead th {
            background: #f8fafc;
            color: #293b50;
            font-weight: 600;
            padding: 1rem;
            border: none;
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-custom tbody td {
            padding: 1rem;
            border: none;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .table-custom tbody tr:hover {
            background: #f8fafc;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-filewaiting {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .status-firing {
            background: linear-gradient(135deg, #0891b2, #0e7490);
            color: white;
        }

        .status-failed {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
        }

        .status-deleted {
            background: #94a3b8;
            color: white;
        }

        .status-paused {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .status-completed {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            border: 1px solid;
        }

        .action-btn-pause {
            background: rgba(245, 158, 11, 0.1);
            border-color: rgba(245, 158, 11, 0.3);
            color: #d97706;
        }

        .action-btn-pause:hover {
            background: #f59e0b;
            color: white;
        }

        .action-btn-resume {
            background: rgba(22, 163, 74, 0.1);
            border-color: rgba(22, 163, 74, 0.3);
            color: #16a34a;
        }

        .action-btn-resume:hover {
            background: #16a34a;
            color: white;
        }

        .action-btn-delete {
            background: rgba(220, 38, 38, 0.1);
            border-color: rgba(220, 38, 38, 0.3);
            color: #dc2626;
        }

        .action-btn-delete:hover {
            background: #dc2626;
            color: white;
        }

        .action-btn-download {
            background: rgba(8, 145, 178, 0.1);
            border-color: rgba(8, 145, 178, 0.3);
            color: #0891b2;
        }

        .action-btn-download:hover {
            background: #0891b2;
            color: white;
        }

        .action-btn-link {
            background: rgba(124, 58, 237, 0.1);
            border-color: rgba(124, 58, 237, 0.3);
            color: #7c3aed;
        }

        .action-btn-link:hover {
            background: #7c3aed;
            color: white;
        }

        .campaign-name {
            color: #293b50;
            font-weight: 600;
        }

        .campaign-id {
            background: #f1f5f9;
            padding: 0.25rem 0.5rem;
            border-radius: 5px;
            font-family: monospace;
            font-size: 0.85rem;
            color: #64748b;
        }

        .rows-badge {
            background: linear-gradient(135deg, rgba(41, 59, 80, 0.1), rgba(31, 44, 61, 0.1));
            color: #293b50;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .stats-row {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        }

        .stats-row td {
            padding: 0.75rem 1rem !important;
            font-size: 0.85rem;
        }

        .alert-custom-success {
            background: linear-gradient(135deg, rgba(22, 163, 74, 0.1), rgba(21, 128, 61, 0.1));
            border: 1px solid rgba(22, 163, 74, 0.3);
            border-left: 4px solid #16a34a;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            color: #166534;
        }

        .alert-custom-danger {
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.1), rgba(185, 28, 28, 0.1));
            border: 1px solid rgba(220, 38, 38, 0.3);
            border-left: 4px solid #dc2626;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            color: #991b1b;
        }

        .alert-custom-info {
            background: linear-gradient(135deg, rgba(8, 145, 178, 0.1), rgba(14, 116, 144, 0.1));
            border: 1px solid rgba(8, 145, 178, 0.3);
            border-left: 4px solid #0891b2;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            color: #0e7490;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .empty-state h5 {
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #94a3b8;
        }
    </style>
@endpush

@section('content')
    <div class="dashboard-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h4><i class="material-icons-outlined align-middle me-2">history</i>View previous SMS campaigns</h4>
                    <p>Track, manage and download reports for your campaigns</p>
                </div>
                <div style="display: flex; gap: 0.8rem;">

                    <a href="{{ route('campaign.quick') }}" class="btn"
                        style="background:white; color:#0891b2; font-weight:600; border-radius:10px; 
        padding:0.5rem 1rem; display:flex; align-items:center; gap:0.5rem;">
                        <i class="material-icons-outlined" style="font-size:18px;">send</i>
                        Quick Campaign
                    </a>

                    <a href="{{ route('campaign.upload') }}" class="btn"
                        style="background:white; color:#0891b2; font-weight:600; border-radius:10px; 
        padding:0.5rem 1rem; display:flex; align-items:center; gap:0.5rem;">
                        <i class="material-icons-outlined" style="font-size:18px;">upload_file</i>
                        Bulk Campaign
                    </a>

                </div>

                {{-- <div>
                <a href="{{ route('campaign.quick') }}" class="header-btn header-btn-primary">
                    <i class="material-icons-outlined" style="font-size: 18px;">send</i>
                    Quick Campaign
                </a>
                <a href="{{ route('campaign.upload') }}" class="header-btn">
                    <i class="material-icons-outlined" style="font-size: 18px;">upload_file</i>
                    File Upload
                </a>
            </div> --}}
            </div>
        </div>

        <!-- Alert Messages -->
        @if (session('success'))
            <div class="alert-custom-success mb-4 d-flex align-items-center">
                <i class="material-icons-outlined me-2">check_circle</i>
                <div>{!! session('success') !!}</div>
            </div>
        @endif

        @if (session('error'))
            <div class="alert-custom-danger mb-4 d-flex align-items-center">
                <i class="material-icons-outlined me-2">error</i>
                <div>{!! session('error') !!}</div>
            </div>
        @endif

        <!-- Search Card -->
        <div class="search-card">
            <form action="{{ route('campaign.previous.list') }}" method="GET">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Campaign Name</label>
                        <input type="text" name="searchcampaignname" class="form-control"
                            value="{{ $searchCampaignName }}" placeholder="Search by name...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Campaign ID</label>
                        <input type="text" name="searchcampaignid" class="form-control" value="{{ $searchCampaignId }}"
                            placeholder="Search by ID...">
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn-search">
                                <i class="material-icons-outlined align-middle me-1" style="font-size: 18px;">search</i>
                                Search
                            </button>
                            <a href="{{ route('campaign.previous.list') }}" class="btn-reset">
                                <i class="material-icons-outlined align-middle me-1" style="font-size: 18px;">refresh</i>
                                Reset
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Data Card -->
        <div class="data-card">
            <!-- Legend -->
            <div class="legend-bar">
                <div class="legend-item">
                    <i class="material-icons-outlined text-danger" style="font-size: 18px;">delete</i>
                    <span>Remove campaign & delete unsent messages</span>
                </div>
                <div class="legend-item">
                    <i class="material-icons-outlined text-primary" style="font-size: 18px;">download</i>
                    <span>Download delivery report</span>
                </div>
                @if (!empty($shortdomain))
                    <div class="legend-item">
                        <i class="material-icons-outlined" style="font-size: 18px; color: #7c3aed;">link</i>
                        <span>Short URL click report</span>
                    </div>
                @endif
            </div>

            @if ($campaigns->isEmpty())
                <div class="empty-state">
                    <i class="material-icons-outlined">folder_off</i>
                    <h5>No campaigns found</h5>
                    <p>Start by creating a new campaign using Quick Campaign or File Upload</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th width="100">Actions</th>
                                <th>Status</th>
                                <th>Campaign Name</th>
                                <th>Date Uploaded</th>
                                <th>Progress</th>
                                <th>Campaign ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($campaigns as $campaign)
                                <tr>
                                    <td>
                                        <div class="d-flex gap-1">
                                            @if ($campaign->can_modify)
                                                @if ($campaign->status === 'paused')
                                                    <form action="{{ route('campaign.resume') }}" method="POST"
                                                        class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="campaignref"
                                                            value="{{ $campaign->campaignid }}">
                                                        <button type="submit" class="action-btn action-btn-resume"
                                                            title="Resume campaign"
                                                            onclick="return confirm('Resume this campaign?')">
                                                            <i class="material-icons-outlined"
                                                                style="font-size: 16px;">play_arrow</i>
                                                        </button>
                                                    </form>
                                                @else
                                                    <form action="{{ route('campaign.pause') }}" method="POST"
                                                        class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="campaignref"
                                                            value="{{ $campaign->campaignid }}">
                                                        <button type="submit" class="action-btn action-btn-pause"
                                                            title="Pause campaign"
                                                            onclick="return confirm('Pause this campaign?')">
                                                            <i class="material-icons-outlined"
                                                                style="font-size: 16px;">pause</i>
                                                        </button>
                                                    </form>
                                                @endif
                                                <form action="{{ route('campaign.delete') }}" method="POST"
                                                    class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="campaignref"
                                                        value="{{ $campaign->campaignid }}">
                                                    <button type="submit" class="action-btn action-btn-delete"
                                                        title="Delete campaign"
                                                        onclick="return confirm('Are you sure you want to delete this campaign and all unsent messages?')">
                                                        <i class="material-icons-outlined"
                                                            style="font-size: 16px;">delete</i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @php
                                            $statusClass = match ($campaign->status) {
                                                'filewaiting' => 'status-filewaiting',
                                                'firing' => 'status-firing',
                                                'failed' => 'status-failed',
                                                'deleted' => 'status-deleted',
                                                'paused' => 'status-paused',
                                                default => 'status-completed',
                                            };
                                        @endphp
                                        <span
                                            class="status-badge {{ $statusClass }}">{{ $campaign->status_display }}</span>
                                        @if ($campaign->statusinfo)
                                            <small class="d-block text-muted mt-1">{{ $campaign->statusinfo }}</small>
                                        @endif

                                        @if (!in_array($campaign->status, ['filewaiting', 'firing', 'failed', 'deleted']))
                                            <div class="mt-2 d-flex gap-1">
                                                <a href="{{ route('campaign.download.report', ['campaignref' => $campaign->campaignid]) }}"
                                                    class="action-btn action-btn-download"
                                                    title="Download Delivery Report">
                                                    <i class="material-icons-outlined"
                                                        style="font-size: 16px;">download</i>
                                                </a>
                                                @if (!empty($shortdomain))
                                                    <a href="{{ route('campaign.download.shorturl', ['campaignref' => $campaign->campaignid]) }}"
                                                        class="action-btn action-btn-link"
                                                        title="Download Short URL Report">
                                                        <i class="material-icons-outlined"
                                                            style="font-size: 16px;">link</i>
                                                    </a>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="campaign-name">{{ $campaign->campaignname }}</span>
                                    </td>
                                    <td>{{ $campaign->formatted_datetime }}</td>
                                    <td>
                                        <span class="rows-badge">
                                            {{ number_format($campaign->numlinesdone ?? 0) }} /
                                            {{ number_format($campaign->numlines ?? 0) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="campaign-id">{{ $campaign->campaignid }}</span>
                                    </td>
                                </tr>
                                @if ($campaign->dlrstats)
                                    <tr class="stats-row">
                                        <td colspan="6">
                                            <i class="material-icons-outlined align-middle me-1"
                                                style="font-size: 16px; color: #0891b2;">analytics</i>
                                            @php
                                                $dlrstatsdate = '';
                                                if (!empty($campaign->dlrstatsdate)) {
                                                    $dlrstatsdate = date(
                                                        'jS M Y, ga',
                                                        strtotime(
                                                            substr($campaign->dlrstatsdate, 0, 4) .
                                                                '-' .
                                                                substr($campaign->dlrstatsdate, 4, 2) .
                                                                '-' .
                                                                substr($campaign->dlrstatsdate, 6, 2) .
                                                                ' ' .
                                                                substr($campaign->dlrstatsdate, 8, 2) .
                                                                ':' .
                                                                substr($campaign->dlrstatsdate, 10, 2) .
                                                                ':' .
                                                                substr($campaign->dlrstatsdate, 12, 2),
                                                        ),
                                                    );
                                                }
                                            @endphp
                                            <strong>Delivery Status</strong> <span
                                                class="text-muted">({{ $dlrstatsdate }})</span>:
                                            {{ $campaign->dlrstats }}
                                        </td>
                                    </tr>
                                @endif
                                @if ($campaign->clickstats)
                                    <tr class="stats-row">
                                        <td colspan="6">
                                            <i class="material-icons-outlined align-middle me-1"
                                                style="font-size: 16px; color: #7c3aed;">touch_app</i>
                                            @php
                                                $clickstatsdate = '';
                                                if (!empty($campaign->clickstatsdate)) {
                                                    $clickstatsdate = date(
                                                        'jS M Y, ga',
                                                        strtotime(
                                                            substr($campaign->clickstatsdate, 0, 4) .
                                                                '-' .
                                                                substr($campaign->clickstatsdate, 4, 2) .
                                                                '-' .
                                                                substr($campaign->clickstatsdate, 6, 2) .
                                                                ' ' .
                                                                substr($campaign->clickstatsdate, 8, 2) .
                                                                ':' .
                                                                substr($campaign->clickstatsdate, 10, 2) .
                                                                ':' .
                                                                substr($campaign->clickstatsdate, 12, 2),
                                                        ),
                                                    );
                                                }
                                            @endphp
                                            <strong>Click Status</strong> <span
                                                class="text-muted">({{ $clickstatsdate }})</span>:
                                            {{ $campaign->clickstats }}
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
