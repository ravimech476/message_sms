@extends('admin.layouts.app')
@section('title')
    {{ __('Global Pricing') }}
@endsection

@section('content')
    <main class="main-wrapper" id="main-wrapper">
        <div class="main-content">
            <!--breadcrumb-->
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
                <div class="breadcrumb-title pe-3 title-name">Global Pricing</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0" style="background: none;">
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Global Pricing</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <!--end breadcrumb-->

            @if(session('pricing_success'))
                <div class="alert alert-success"><i class="material-icons-outlined align-middle me-1">check_circle</i> {{ session('pricing_success') }}</div>
            @endif
            @if(session('pricing_error'))
                <div class="alert alert-danger"><i class="material-icons-outlined align-middle me-1">error</i> {{ session('pricing_error') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif

            <div class="row">
                <div class="col-12 col-xl-9">
                    <form action="{{ route('admin.global-pricing.save') }}" method="POST">
                        @csrf

                        {{-- Onboarding rate --}}
                        <div class="card rounded-4 border mb-3">
                            <div class="card-header bg-transparent"><h5 class="mb-0"><i class="material-icons-outlined align-middle me-1">person_add</i> New Customer Onboarding Rate</h5></div>
                            <div class="card-body">
                                <p class="text-muted">Default UK SMS rate applied to a new customer's routes when the account is created (<code>/admin/customer/create</code>) and shown on their rate tab. Must be at or above cost price.</p>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Onboarding SMS rate (£ per SMS)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">£</span>
                                            <input type="number" step="0.0001" min="0" name="onboarding_sms_rate" class="form-control"
                                                   value="{{ old('onboarding_sms_rate', $p['onboarding_sms_rate']) }}" required>
                                        </div>
                                        <div class="form-text">e.g. <strong>0.0457</strong> = 4.57 pence.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Contracts pricing (customer-facing, display-only for them) --}}
                        <div class="card rounded-4 border mb-3">
                            <div class="card-header bg-transparent"><h5 class="mb-0"><i class="material-icons-outlined align-middle me-1">receipt_long</i> Contracts Pricing</h5></div>
                            <div class="card-body">
                                <p class="text-muted">Shown on the customer <code>/contracts</code> page for information only. Editing here updates what customers see.</p>

                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Effective from</label>
                                        <input type="date" name="contract_effective_date" class="form-control"
                                               value="{{ old('contract_effective_date', $p['contract_effective_date']) }}" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Virtual UK number / keyword (£ per year)</label>
                                        <div class="input-group"><span class="input-group-text">£</span>
                                            <input type="number" step="0.01" min="0" name="contract_virtual_number_price_year" class="form-control"
                                                   value="{{ old('contract_virtual_number_price_year', $p['contract_virtual_number_price_year']) }}" required></div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Overseas SMS (pence per text)</label>
                                        <input type="number" step="0.01" min="0" name="contract_overseas_rate_pence" class="form-control"
                                               value="{{ old('contract_overseas_rate_pence', $p['contract_overseas_rate_pence']) }}" required>
                                    </div>
                                </div>

                                <hr>
                                <h6 class="mb-2">UK SMS — monthly volume tiers (£ per text)</h6>
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label">Tier 1: up to (volume)</label>
                                        <input type="number" step="1" min="0" name="contract_uk_tier1_upto" class="form-control"
                                               value="{{ old('contract_uk_tier1_upto', $p['contract_uk_tier1_upto']) }}" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Tier 1 rate</label>
                                        <div class="input-group"><span class="input-group-text">£</span>
                                            <input type="number" step="0.0001" min="0" name="contract_uk_tier1_rate" class="form-control"
                                                   value="{{ old('contract_uk_tier1_rate', $p['contract_uk_tier1_rate']) }}" required></div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Tier 2: up to (volume)</label>
                                        <input type="number" step="1" min="0" name="contract_uk_tier2_upto" class="form-control"
                                               value="{{ old('contract_uk_tier2_upto', $p['contract_uk_tier2_upto']) }}" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Tier 2 rate</label>
                                        <div class="input-group"><span class="input-group-text">£</span>
                                            <input type="number" step="0.0001" min="0" name="contract_uk_tier2_rate" class="form-control"
                                                   value="{{ old('contract_uk_tier2_rate', $p['contract_uk_tier2_rate']) }}" required></div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Over Tier 2 — rate</label>
                                        <div class="input-group"><span class="input-group-text">£</span>
                                            <input type="number" step="0.0001" min="0" name="contract_uk_tier3_rate" class="form-control"
                                                   value="{{ old('contract_uk_tier3_rate', $p['contract_uk_tier3_rate']) }}" required></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success"><i class="material-icons-outlined align-middle me-1">save</i> Save Pricing</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
@endsection
