@extends('campaign.layouts.app')

@section('title', 'View STOP Blacklist - Campaign Manager')

@push('style')
    <style>
        .dashboard-container {
            background: #f8fafc;
            margin: -2rem;
            padding: 2rem;
        }

        .page-header {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
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

        .info-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .info-card .card-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 1.5rem;
        }

        .info-card .card-header h5 {
            color: #293b50;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-card .card-body {
            padding: 1.5rem;
        }

        .alert-custom-info {
            background: linear-gradient(135deg, rgba(8, 145, 178, 0.1), rgba(14, 116, 144, 0.1));
            border: 1px solid rgba(8, 145, 178, 0.2);
            border-left: 4px solid #0891b2;
            border-radius: 10px;
            padding: 1.25rem;
            color: #0e7490;
            margin-bottom: 1.5rem;
        }

        .alert-custom-warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.1));
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-left: 4px solid #f59e0b;
            border-radius: 10px;
            padding: 1.25rem;
            color: #92400e;
            margin-bottom: 1.5rem;
        }

        .download-section {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 15px;
            padding: 3rem;
            text-align: center;
        }

        .download-section i {
            font-size: 80px;
            color: #cbd5e1;
            margin-bottom: 1.5rem;
        }

        .download-section h5 {
            color: #293b50;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .download-section p {
            color: #64748b;
            margin-bottom: 1.5rem;
        }

        .btn-download {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            border: none;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-download:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(220, 38, 38, 0.4);
            color: white;
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
        }

        .header-btn:hover {
            background: white;
            color: #dc2626;
        }
    </style>
@endpush

@section('content')
    <div class="dashboard-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h4><i class="material-icons-outlined align-middle me-2">block</i>View STOP Blacklist</h4>
                    <p>View and download your blacklisted mobile numbers</p>
                </div>
                 <a href="{{ route('campaign.blacklist.download') }}" class="btn"
                        style="background:white; color:#dc2626; font-weight:600; border-radius:10px; 
        padding:0.5rem 1rem; display:flex; align-items:center; gap:0.5rem;">
                        <i class="material-icons-outlined" style="font-size:18px;">download</i>
                        Download Report
                    </a>
                {{-- <a href="{{ route('campaign.blacklist.download') }}" class="header-btn">
                    <i class="material-icons-outlined" style="font-size: 18px;">download</i>
                    Download Report
                </a> --}}
            </div>
        </div>

        <!-- Info Card -->
        <div class="info-card">
            <div class="card-header">
                <h5>
                    <i class="material-icons-outlined">info</i>
                    About the STOP Blacklist
                </h5>
            </div>
            <div class="card-body">
                <div class="alert-custom-info">
                    <div class="d-flex">
                        <i class="material-icons-outlined me-3" style="font-size: 24px;">info</i>
                        <div>
                            <strong class="d-block mb-2">What is the STOP Blacklist?</strong>
                            <p class="mb-2">
                                Click the download button to retrieve your STOP Blacklist report. This report shows all
                                mobile numbers
                                that you have sent SMS to that have sent in a STOP or STOP ALL request, together with the
                                date and time.
                            </p>
                            <p class="mb-0">
                                If you have previously uploaded batches of mobile numbers to your Blacklist then these will
                                also be shown in the report.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="alert-custom-warning">
                    <div class="d-flex">
                        <i class="material-icons-outlined me-3" style="font-size: 24px;">warning</i>
                        <div>
                            <strong class="d-block mb-2">Important Notice</strong>
                            <p class="mb-2">
                                You are <strong>unable to send any further texts</strong> to the numbers found in this
                                blacklist.
                            </p>
                            <p class="mb-0">
                                To remove this blacklisting facility from your account, please contact us.
                                Note: If people have texted STOP multiple times, this report may show duplicate numbers.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Download Section -->
                <div class="download-section">
                    <i class="material-icons-outlined">file_download</i>
                    <h5>Download Your Blacklist Report</h5>
                    <p>Get a CSV file containing all blacklisted mobile numbers with dates</p>
                    <a href="{{ route('campaign.blacklist.download') }}" class="btn-download">
                        {{-- <i class="material-icons-outlined">download</i> --}}
                        Click here to download
                    </a>

                </div>
            </div>
        </div>
    </div>
@endsection
