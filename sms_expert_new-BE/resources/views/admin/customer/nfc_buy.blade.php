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
    </style>
    @section('content')
        <!--start main wrapper-->
        <main class="main-wrapper">
            <div class="main-content">
                <!--breadcrumb-->
                <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
                    <div class="breadcrumb-title pe-3 title-name">NFC Starter Pack</div>
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
                                    <h5 class="mb-0 fw-bold theme-dependent">Buy NFC Starter Pack</h5>
                                </div>

                                <p class="mb-2">You are logged in as...
                                    <span class="d-inline-block mt-1 mb-2" style="color: black;">{{ ucfirst(urldecode($user_contactname ?? '')) }}</span>
                                </p>
                                <p class="mb-3"><strong>NFC Starter Pack</strong> — 15 taggs + 1 Year Management/Reporting.
                                    We'll display and email you an invoice containing our bank details. Price is
                                    <strong>excluding VAT</strong>.</p>

                                <form action="{{ route('admin.nfc.buy') }}" method="POST">
                                    @csrf
                                    <div class="mt-2">
                                        NFC Starter Pack price... £
                                        <input type="number" name="whatvolumeother" maxlength="50" class="maintxt4fields"
                                            min="1" step="1" value="{{ $nfcPrice > 0 ? (int) $nfcPrice : '' }}"
                                            placeholder="enter price" required>+vat
                                    </div>

                                    <div class="d-flex justify-content-start mt-3">
                                        <button type="submit" class="btn btn-buy-sms"
                                            onclick="return confirm('We will now generate, display & email an SMS Expert invoice for the NFC Starter Pack.\n\nAre you sure?');">
                                            Buy NFC Starter Pack
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
                                    <h5 class="mb-0 fw-bold theme-dependent">Additional notes</h5>
                                </div>
                                <p class="mb-0">Please add VAT to all prices shown. Cleared bank transfer payments are required
                                    unless alternative arrangements have been agreed. We'll prepare your NFC account and post your
                                    NFC taggs once payment clears (please allow up to 24 hours). Purchases are non-refundable.
                                    Payment in full is due within 3 working days (unless agreed prior to invoice).</p>
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
