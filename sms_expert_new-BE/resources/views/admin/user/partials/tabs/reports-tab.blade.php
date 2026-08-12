<div class="tab-pane fade {{ session('activeTab') == 'customer-report-log' ? 'show active' : '' }}"
    id="customer-report-log" role="tabpanel">
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h5 class="card-title mb-4">
                <i class="bi bi-file-earmark-text me-2 text-primary"></i>
                Customer Reports
            </h5>

            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <a href="{{ route('admin.export.postpay') }}" class="btn btn-outline-primary w-100">
                        <i class="bi bi-download me-2"></i> Post-Pay Customer Report
                    </a>
                </div>

                <div class="col-12 col-md-4">
                    <a href="{{ route('admin.export.daily_sms') }}" class="btn btn-outline-success w-100">
                        <i class="bi bi-download me-2"></i> Daily SMS Report
                    </a>
                </div>

                <div class="col-12 col-md-4">
                    <a href="{{ route('admin.export.money_transferred') }}" class="btn btn-outline-warning w-100">
                        <i class="bi bi-download me-2"></i> Money Transferred Report
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>