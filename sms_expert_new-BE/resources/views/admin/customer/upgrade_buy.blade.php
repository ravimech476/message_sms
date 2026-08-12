@extends('layouts.admin_app')
@section('title')
    {{ __('CRM') }}
@endsection
@push('style')
    <style>
        .btn-buy-sms {
            background-color: #fd7e14 !important;
            color: #fff !important;
            border: 1px solid #fd7e14 !important;
            padding: 0.5rem 1.2rem;
            border-radius: 0.375rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
            font-weight: 500;
        }

        .btn-buy-sms:hover {
            background-color: #e36e0f !important;
            border-color: #e36e0f !important;
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.25);
        }

        .breadcrumb-item+.breadcrumb-item::before {
            content: " / " !important;
            color: #6c757d !important;
        }

        .upgrade-pkg {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 0.9rem 1rem;
            margin-bottom: 0.6rem;
            cursor: pointer;
        }

        .upgrade-pkg:hover {
            border-color: #fd7e14;
            background: #fff7ee;
        }
    </style>
    @section('content')
        <!--start main wrapper-->
        <main class="main-wrapper">
            <div class="main-content">
                <!--breadcrumb-->
                <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
                    <div class="breadcrumb-title pe-3 title-name">Upgrade Account</div>
                    <div class="ps-3">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0 p-0" style="background: none;">
                                <li class="breadcrumb-item">
                                    <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
                                </li>
                            </ol>
                        </nav>
                    </div>
                </div>
                <!--end breadcrumb-->

                @if (session('success'))
                    <div id="flash-message" class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div id="flash-error-message" class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <div class="row">
                    <div class="col-12 col-xl-12 col-xxl-3 d-flex">
                        <div class="card w-100 rounded-4">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-3">
                                    <h5 class="mb-0 fw-bold theme-dependent">Upgrade Your Account</h5>
                                </div>

                                <p class="mb-2">You are logged in as...
                                    <span class="d-inline-block mt-1 mb-2" style="color: black;">{{ ucfirst(urldecode($user_contactname ?? '')) }}</span>
                                </p>
                                @if (!empty($currentType))
                                    <p class="mb-2">Current package: <strong>{{ ucfirst($currentType) }}</strong></p>
                                @endif
                                <p class="mb-3">Choose an upgrade package below. We'll display and email you an invoice
                                    containing our bank details. All prices are <strong>per year, excluding VAT</strong>.</p>

                                <form action="{{ route('admin.upgrade.buy') }}" method="POST">
                                    @csrf
                                    @foreach ($packages as $key => $price)
                                        <label class="upgrade-pkg d-block">
                                            <input type="radio" class="form-check-input me-2" name="package"
                                                value="{{ $key }}" {{ $loop->first ? 'checked' : '' }}>
                                            <strong>{{ ucfirst($key) }} upgrade</strong>
                                            &mdash; £{{ number_format($price, 2) }} + VAT
                                            <span class="text-muted">(£{{ number_format($price * 1.2, 2) }} inc VAT)</span>
                                        </label>
                                    @endforeach

                                    <div class="d-flex justify-content-start mt-3">
                                        <button type="submit" class="btn btn-buy-sms"
                                            onclick="return confirm('We will now generate, display & email an SMS Expert invoice for the selected upgrade.\n\nAre you sure?');">
                                            Buy Upgrade
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 col-xl-12 col-xxl-3 d-flex">
                        <div class="card w-100 rounded-4">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-3">
                                    <h5 class="mb-0 fw-bold theme-dependent">Additional notes for upgrades</h5>
                                </div>
                                <p class="mb-0">Please add VAT to all prices shown. Cleared bank transfer payments are required
                                    unless alternative arrangements have been agreed. In many cases we process your payment and
                                    apply your upgrade immediately, however please allow up to 24 hours for this. Purchases are
                                    non-refundable. Payment in full is due within 3 working days (unless agreed prior to invoice).
                                    SMS Expert reserves the right to cancel the invoice or revise terms/pricing if payment is late.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!--end row-->
        </main>
        <!--end main wrapper-->
        @include('layouts.footer')
    @endsection
    @push('js')
        <script>
            setTimeout(function () {
                let m = document.getElementById('flash-message');
                if (m) m.style.display = 'none';
            }, 2000);
            setTimeout(function () {
                let m = document.getElementById('flash-error-message');
                if (m) m.style.display = 'none';
            }, 3000);
        </script>
    @endpush
