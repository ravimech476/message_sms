{{-- Per-report-type "Generate Report (background)" form. $reportType is passed
     by the parent tab. No immediate table / export buttons — only Generate. --}}
@php
    $au = $adminUser ?? Session::get('admin_user');
    $adminEmailDefault = $au['email'] ?? ($au['contactemail'] ?? '');
    $typeLabel = \App\Models\ReportJob::typeLabel($reportType);
@endphp

<h5 class="mb-1"><i class="material-icons-outlined me-1 align-middle">schedule_send</i>Generate {{ $typeLabel }}</h5>
<p class="text-muted small mb-3">Choose a date range, edit the name if you like, enter the email, then click <strong>Generate Report</strong>. We build it in the background and email you a download link when it's ready — it also appears in <strong>Report History</strong> below.</p>

<div class="ar-alert alert d-none mb-3" role="alert"></div>

<form class="ar-form row g-3" data-type="{{ $reportType }}">
    @csrf
    <input type="hidden" name="report_type" value="{{ $reportType }}">

    <div class="col-md-3">
        <label class="form-label">Date from</label>
        <input type="date" class="form-control ar-from" name="date_from"
               value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Date to</label>
        <input type="date" class="form-control ar-to" name="date_to"
               value="{{ now()->format('Y-m-d') }}" min="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Report name <span class="text-muted small">(auto-filled — you can edit)</span></label>
        <input type="text" class="form-control ar-name" name="report_name" maxlength="150" data-typelabel="{{ $typeLabel }}">
    </div>

    <div class="col-md-6">
        <label class="form-label">Customers <span class="text-muted small">(optional — all if blank)</span></label>
        <select class="form-select ar-customers" name="customer_ids[]" multiple>
            @isset($customers)
                @foreach ($customers as $c)
                    @php $cn = trim(urldecode($c->busname ?? '')) ?: trim(urldecode($c->contactname ?? '')) ?: ($c->uname ?? ('#' . $c->id)); @endphp
                    <option value="{{ $c->id }}">{{ $cn }}</option>
                @endforeach
            @endisset
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Notify email</label>
        <input type="email" class="form-control ar-email" name="email" value="{{ $adminEmailDefault }}" required placeholder="you@company.com">
        <small class="text-muted d-block mt-1"><i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">mail</i> Once the report is ready, you will receive an email with a download link.</small>
    </div>
    <div class="col-md-6 d-flex align-items-start" style="margin-top:30px;">
        <button type="submit" class="btn btn-primary">
            <i class="material-icons-outlined font-18 align-middle">send</i> Generate Report
        </button>
    </div>
</form>
