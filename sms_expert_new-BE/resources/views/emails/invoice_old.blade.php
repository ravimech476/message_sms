<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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
</head>

<body>
    <div class="container my-4">
        <div class="card">
            <div class="dotted-border p-4">
                <!-- Payment Information -->
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
                        <p class="mb-1"><b>{{ $invoiceno ?? '' }}</b></p>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <img src="https://www.devsmsexpert.com/brochure2/img/smsexpertlogowhiteback.jpg"
                        alt="SMS Expert Logo" class="img-fluid" width="250">
                </div>

                <div class="mt-4">
                    <h5 class="text-center fw-bold">Invoice</h5>
                    <p class="text-center mb-4 text-warning">Payment in full is due upon receipt.
                        SMS Expert is unable to credit the account until cleared funds are received.</p>
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
                        <p class="fw-bold">Invoice Number: {{ $invoiceno ?? '' }}</p>
                    </div>

                    <div class="col-md-6 text-end">
                        <p class="fw-bold mb-2">Invoiced To</p>
                        <p class="mb-1">{{ urldecode($user->contactname ?? '') }}</p>
                        <p class="mb-1">{{ urldecode($user->busname ?? '') }}</p>
                        <p class="mb-1">{{ $user->address1 ?? '' }}</p>
                        <p class="mb-1">{{ $user->town ?? '' }}</p>
                        {{-- <p class="mb-1">{{ $user->city ?? '' }}</p> --}}
                        <p class="mb-1">{{ $user->country ?? '' }}</p>
                        <p class="mb-1">{{ $user->pcode ?? '' }}</p>
                    </div>
                </div>

                <div>
                    <p class="text-end"><b>{{ date('D j M Y', $invoice->invoicedate ?? time()) }}</b></p>
                </div>

                <div class="table-responsive mt-4">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <td><b>Pre-purchase of SMS Expert Credits</b></td>
                                <td class="text-end">
                                    £{{ number_format($invoice->orderItems->invoice_nonvatprice ?? 0, 2, '.', '') }}
                                </td>
                            </tr>
                            <tr>
                                <td>Total</td>
                                <td class="text-end">
                                    £{{ number_format($invoice->orderItems->invoice_nonvatprice ?? 0, 2, '.', '') }}
                                </td>
                            </tr>
                            <?php
                            $price = $invoice->orderItems->invoice_nonvatprice ?? 0;
                            $vatPercentage = 20;
                            $vatAmount = ($price * $vatPercentage) / 100;
                            ?>
                            <tr>
                                <td>VAT (20.00%)</td>
                                <td class="text-end">£{{ number_format($vatAmount, 2, '.', '') }}</td>
                            </tr>
                            <tr>
                                <td><b>Sub Total</b></td>
                                <td class="text-end">
                                    <b>£{{ number_format($invoice->orderItems->invoice_fullprice ?? 0, 2, '.', '') }}</b>
                                </td>
                            </tr>
                            <tr>
                                <td>Vouchers Used</td>
                                <td class="text-end">£0.00</td>
                            </tr>
                            <tr>
                                <td><b>Grand Total</b></td>
                                <td class="text-end">
                                    <b>£{{ number_format($invoice->orderItems->invoice_fullprice ?? 0, 2, '.', '') }}</b>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
