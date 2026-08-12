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

        .mini-sidebar .user .user-icon:hover,
        .mini-sidebar .user .user-icon:focus {
            /* background-color: #efefef; */
            /* background-color: #fd7e14; */
            color: #008cff !important;
            text-decoration: none;
            background-color: rgba(0, 140, 255, 0.05) !important;
        }

        .breadcrumb-item+.breadcrumb-item::before {
            content: " / " !important;
            color: #6c757d !important;
            /* optional grey */
        }
    </style>
    @section('content')
        <!--start main wrapper-->
        <main class="main-wrapper">

            <div class="main-content">
                <!--breadcrumb-->
                <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
                    <div class="breadcrumb-title pe-3 title-name">Buy SMS</div>
                    <div class="ps-3">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0 p-0" style="background: none;">
                                <li class="breadcrumb-item">
                                    <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
                                </li>
                            </ol>
                    </div>

                </div>
                <!--end breadcrumb-->
                @if (session('success'))
                    <div id="flash-message" class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div id="flash-error-message" class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                @endif
                <div class="row">
                    <div class="col-12 col-xl-12 col-xxl-3 d-flex">
                        <div class="card w-100 rounded-4">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-3">
                                    <h5 class="mb-0 fw-bold theme-dependent">SMS Delivery</h5>
                                </div>
                                <div class="d-flex flex-column justify-content-between gap-4">
                                    <div class="d-flex align-items-center gap-4">
                                        <div class="align-items-center gap-3 flex-grow-1">
                                            <p class="mb-0">With SMS Expert you can send SMS conveniently to individual
                                                mobiles or groups of them directly from our easy to use online dashboard or from
                                                our API's whenever you wish.
                                            </p>
                                        </div>
                                    </div>
                                </div><br>
                                <div class="d-flex align-items-start justify-content-between mb-3">
                                    <h5 class="mb-0 fw-bold theme-dependent"> Buy SMS Now</h5>
                                </div>
                                <div class="d-flex flex-column justify-content-between gap-4">
                                    <div class="d-flex align-items-center gap-4">
                                        <div class="align-items-center gap-3 flex-grow-1">
                                            <p class="mb-0"> Simply choose the monetary amount you wish to spend on new SMS
                                                credits on the right of this page and click the orange Buy SMS button. We'll
                                                then display and email you an invoice containing payment options.</p>
                                        </div>
                                    </div>
                                </div><br>
                                <div class="d-flex align-items-start justify-content-between mb-3">
                                    <h5 class="mb-0 fw-bold theme-dependent"> Buy SMS, Step 2 - Order</h5>
                                </div>
                                <div class="d-flex flex-column justify-content-between gap-4">
                                    <div class="d-flex align-items-center gap-4">
                                        <div class="align-items-center gap-3 flex-grow-1">
                                            <p class="mb-0"> You are logged in as... <br>
                                                <span class="d-inline-block mt-2 mb-2"
                                                    style="color: black;">{{ ucfirst(urldecode($user_contactname ?? '')) }}</span>
                                            </p>
                                            <!-- Adding margin for spacing -->
                                            <p class="mb-0">Please enter the amount (in pounds) that you wish to purchase.</p>
                                            <form action="{{ route('outstanding.buysms.invoice') }}" method="POST">
                                                @csrf
                                                <div class="mt-2">
                                                    <input type="radio" class="form-check-input" name="whatvolume"
                                                        id="whatvolume" value="bespoke" checked>
                                                    Amount... £
                                                    <input type="number" name="whatvolumeother" maxlength="50"
                                                        class="maintxt4fields" min="100" value="500" step="1" required>+vat
                                                </div>
                                                @if ($max_amount->maxcardpurchase == 0)
                                                    <div class="mt-2">
                                                        <p>
                                                            Your account is currently configured to only let you pay by bank
                                                            transfer.
                                                            If you wish to pay by card or Paypal then contact us before creating
                                                            the invoice.
                                                        </p>
                                                    </div>
                                                @else
                                                    @php
                                                        $maxCardPurchase = $max_amount->maxcardpurchase;
                                                        $vat = 0.2; // 20% VAT
                                                        $beforeVat = round($maxCardPurchase / (1 + $vat), 2); // rounded to 2 decimals
                                                    @endphp
                                                    <div class="mt-2">
                                                        <p>
                                                            Your account is configured to let you pay up to £ {{ $beforeVat }}
                                                            + VAT by card or Paypal.
                                                            For larger amounts, please pay by bank transfer, or contact us if
                                                            you wish to buy more than £ {{ $beforeVat }} + VAT by card/Paypal.
                                                        </p>
                                                    </div>
                                                @endif

                                                {{-- <div class="mt-2">
                                                    <p>Your account is currently configured to only let you pay by bank
                                                        transfer. If you wish to pay by card or Paypal then contact us before
                                                        creating the invoice.</p>
                                                </div> --}}
                                                <div class="d-flex justify-content-start mt-2">
                                                    <button type="submit" class="btn btn-buy-sms"
                                                        onclick="return confirm('We will now generate, display & email an SMS Expert invoice so that you can make a secure purchase.\n\nAre you sure?');">
                                                        Buy SMS
                                                    </button>
                                                    {{-- 
                                                <button type="submit" class="btn btn-primary"
                                                    onclick="return confirm('We will now generate, display & email an SMS Expert invoice so that you can make a secure purchase.\n\nAre you sure?');">
                                                    Buy SMS
                                                </button> --}}
                                            </form>

                                        </div>
                                    </div>
                                </div>



                            </div>
                        </div>
                    </div>

                </div>

                <div class="row">
                    <div class="col-12 col-xl-12 col-xxl-3 d-flex">
                        <div class="card w-100 rounded-4">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-3">
                                    <h5 class="mb-0 fw-bold theme-dependent">Additional notes for pre-purchasing blocks of bulk
                                        SMS</h5>
                                </div>
                                <div class="d-flex flex-column justify-content-between gap-4">
                                    <div class="d-flex align-items-center gap-4">
                                        <div class="align-items-center gap-3 flex-grow-1">
                                            <p class="mb-0">Add VAT to all prices. Cleared bank transfer payments are required
                                                unless alternative arrangements have been agreed. In many cases we process your
                                                payment and topup your online wallet immediately, however please allow upto 24
                                                hours for this. Pre-purchased SMS is non-refundable. Rates shown are for SMS
                                                delivery to UK mobiles only. SMS on non-UK mobiles or landlines will vary,
                                                please enquire. We can only credit wallets once we see cleared funds. Payment in
                                                full is due within 3 working days (unless agreed prior to invoice). SMS Expert
                                                reserves right to cancel invoice or revise terms/pricing if payment is late.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!--end row-->

        </main>
        <!--end main wrapper-->
        <!-- Footer -->
        @include('layouts.footer')
        <!-- End Footer -->
    @endsection
    @push('js')
        <script>
            setTimeout(function() {
                let flashMessage = document.getElementById('flash-message');
                if (flashMessage) {
                    flashMessage.style.display = 'none';
                }
            }, 2000);

            setTimeout(function() {
                let flashMessage = document.getElementById('flash-error-message');
                if (flashMessage) {
                    flashMessage.style.display = 'none';
                }
            }, 3000);
        </script>
    @endpush
