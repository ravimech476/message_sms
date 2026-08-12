@extends('layouts.admin_app')
@section('title')
    {{ __('CRM') }}
@endsection
@push('style')
    <style>
        .dotted-border {
            border: 2px dotted #ccc;
            padding: 20px;
            border-radius: 8px;
            background-color: #f9f9f9;
        }

        .alert-secondary {
            padding: 15px;
            background-color: #e9ecef;
            border-radius: 6px;
        }

        .table-bordered td {
            vertical-align: middle;
        }
    </style>
@endpush

@section('content')
    <!--start main wrapper-->
    <main class="main-wrapper">

        <div class="main-content">
            <!--breadcrumb-->
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
                <div class="breadcrumb-title pe-3 title-name">Invoice</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><i class="bx bx-home-alt"></i>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page"><a
                                    href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        </ol>
                    </nav>
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
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3 fw-bold theme-dependent">SMS Order Confirmation + Invoice Details</h5>
                    <p>Thank you for ordering SMS Expert credits from SMS Expert. We have just sent you a separate email
                        containing a copy of the invoice for your records, number <b>{{ $invoice->id ?? '' }}</b>. The
                        invoice is also shown
                        further down this page.</p>
                    <p>You can view/print/re-email a copy of the invoice at any time by signing in to your account on
                        the
                        <a href="{{ route('user.dashboard.link', ['username' => $user->uname]) }}" class="fw-bold">SMS
                            Expert Dashboard</a> and going to the <i>Invoices</i> page.
                    </p>
                    <h6 class="fw-bold mt-4">How to Pay</h6>
                    @if ($max_amount->maxcardpurchase == 0)
                        <p>Please pay this invoice by bank transfer. You will find our bank details on the invoice.</p>
                    @else
                        @php
                            $maxCardPurchase = $max_amount->maxcardpurchase;
                            $vat = 0.2; // 20% VAT
                            $beforeVat = $maxCardPurchase / (1 + $vat); // Convert inclusive (with VAT) to exclusive
                        @endphp
                        <p>
                            Your account is configured to only allow up to £ {{ number_format($maxCardPurchase, 2) }} via
                            Cards or Paypal.
                            Therefore, please pay this invoice by bank transfer. You will find our bank details on the
                            invoice.
                            Please contact us if you wish to discuss payment methods.
                        </p>
                    @endif

                    {{-- <p>Please pay this invoice by bank transfer. You will find our bank details on the invoice.</p> --}}

                    <h6 class="fw-bold mt-4">Processing Your Payment</h6>
                    <p>We look forward to your payment and will top up your SMS wallet as soon as the payment is
                        received and verified. During office hours this can be within minutes, but please bear with us
                        in case we are busy. We will email you again as soon as this is done.</p>

                    <h6 class="fw-bold mt-4">Always Here to Help</h6>
                    <p>Please call or email us for any assistance. Again, many thanks for choosing SMS Expert for your
                        SMS services.</p>

                    <div class="dotted-border p-4">
                        <!-- Payment Information -->
                        {{-- <div class="alert alert-secondary">
                            <h6 class="fw-bold mb-3">Pay Securely by Bank Transfer</h6>
                            <p class="mb-1">Pay: <b>SMS Expert Ltd</b></p>
                            <p class="mb-1">Bank: <b>Tide Bank</b></p>
                            <p class="mb-1">Sort Code: <b>23-69-72</b></p>
                            <p class="mb-1">Account: <b>20177535</b></p>
                            <p class="mb-1">Payment Ref: <b>59</b></p>
                        </div> --}}
                        <div class="alert alert-secondary p-4">
                            <h6 class="fw-bold mb-3 text-center">Pay Securely by Bank Transfer</h6>
                            <div class="d-flex justify-content-between">
                                <p class="mb-1">Pay</p>
                                <p class="mb-1"><b>SMS Expert Ltd</b></p>
                            </div>
                            <div class="d-flex justify-content-between">
                                <p class="mb-1">Bank</p>
                                <p class="mb-1"><b>Tide Bank</b></p>
                            </div>
                            <div class="d-flex justify-content-between">
                                <p class="mb-1">Sort Code</p>
                                <p class="mb-1"><b>23-69-72</b></p>
                            </div>
                            <div class="d-flex justify-content-between">
                                <p class="mb-1">Account</p>
                                <p class="mb-1"><b>20177535</b></p>
                            </div>
                            <div class="d-flex justify-content-between">
                                <p class="mb-1">Payment Ref</p>
                                <p class="mb-1"><b>{{ $invoice->id ?? '' }}</b></p>
                                {{-- <p>{{ number_format($invoice->orderItems->invoice_fullprice, 2, '.', '' ?? '') }}</p> --}}
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <img src="{{ asset('assets/images/auth/smsexpertlogowhiteback.jpg') }}" alt="SMS Expert Logo"
                                class="img-fluid" width="250">
                        </div>

                        <div class="mt-4">
                            <h5 class="text-center fw-bold">Invoice</h5>
                            <p class="text-center mb-4" style="color:rgb(234, 97, 24)">Payment in full is due upon receipt.
                                SMS Expert is
                                unable to credit the account until cleared funds are received.</p>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-6">
                                <p class="fw-bold mb-2">SMS Expert Limited</p>
                                <p class="mb-1">79-93 Ratcliffe Road</p>
                                <p class="mb-1">Sileby</p>
                                <p class="mb-1">Leicestershire</p>
                                <p class="mb-1">LE12 7PU</p>
                                <p class="mb-1">Email: <b>care@smsexpert.co.uk</b></p>
                                <p class="mb-1">Phone: <b>01509 606305</b></p>
                                <p class="mb-1">VAT Number: <b>GB332497592</b></p>
                                <p>Registered in England, No. <b>12106151</b></p>
                                <p class="fw-bold">Invoice Number: {{ $invoice->id ?? '' }}</p>
                            </div>

                            <div class="col-md-6 text-end">
                                <p class="fw-bold mb-2">Invoiced To</p>
                                <p class="mb-1">{{ urldecode($user->contactname ?? '') }}</p>
                                <p class="mb-1">{{ urldecode($user->busname ?? '') }}</p>
                                <p class="mb-1">{{ $user->address1 ?? '' }}</p>
                                <p class="mb-1">{{ $user->town ?? '' }}</p>
                                <p class="mb-1">{{ $user->city ?? '' }}</p>
                                <p class="mb-1">{{ $user->country ?? '' }}</p>
                                <p class="mb-1">{{ $user->pcode ?? '' }}</p>
                            </div>


                        </div>
                        <div>
                            <p class="text-end">
                                <b>{{ \Carbon\Carbon::createFromTimestamp($invoice->invoicedate, 'Europe/London')->format('j M Y') }}</b>
                            </p>
                            {{-- <p class="text-end"><b>{{ date('D j M Y', $invoice->invoicedate ?? '') }}</b></p> --}}
                        </div>

                        <div class="table-responsive mt-4">
                            <table class="table table-bordered">
                                <tbody>
                                    <tr>
                                        <td><b>{{ $invoice->summary }}</b></td>
                                        <td class="text-end">
                                            £{{ number_format($invoice->orderItems->invoice_nonvatprice, 2, '.', '' ?? '') }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Total</td>
                                        <td class="text-end">
                                            £{{ number_format($invoice->orderItems->invoice_nonvatprice, 2, '.', '' ?? '') }}
                                        </td>
                                    </tr>
                                    <?php
                                    $price = $invoice->orderItems->invoice_nonvatprice;
                                    $vatPercentage = 20;
                                    $vatAmount = ($price * $vatPercentage) / 100;
                                    ?>
                                    <tr>
                                        <td>VAT (20.00%)</td>
                                        <td class="text-end">£{{ number_format($vatAmount, 2, '.', '' ?? '') }}</td>
                                    </tr>
                                    <tr>
                                        <td><b>Sub Total</b></td>
                                        <td class="text-end">
                                            <b>£{{ number_format($invoice->orderItems->invoice_fullprice, 2, '.', '' ?? '') }}</b>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Vouchers Used</td>
                                        <td class="text-end">£0.00</td>
                                    </tr>
                                    <tr>
                                        <td><b>Grand Total</b></td>
                                        <td class="text-end">
                                            <b>£{{ number_format($invoice->orderItems->invoice_fullprice, 2, '.', '' ?? '') }}</b>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>

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
        }, 2000);
    </script>
@endpush
