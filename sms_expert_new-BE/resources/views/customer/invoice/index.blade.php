@extends('layouts.app')
@section('title', 'Invoices - SMS Expert')

@push('style')
    <style>
        .back-btn {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .invoices-container {
            background: #f8fafc;
            min-height: 100vh;
            margin: -2rem;
            padding: 2rem;
        }

        .invoices-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            margin-bottom: 2rem;
        }

        .invoices-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #ea6118, #293b50);
        }

        .invoices-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .section-header {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            padding: 1.5rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .section-title {
            color: #293b50;
            font-weight: 700;
            font-size: 1.2rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-content {
            padding: 2rem;
        }

        .breadcrumb-container {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
        }

        .breadcrumb {
            margin: 0;
            background: transparent;
        }

        .breadcrumb-item a {
            color: #ea6118;
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: #64748b;
        }

        .breadcrumb-title {
            color: #293b50;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .back-button {
            background: linear-gradient(135deg, #64748b, #475569);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3);
            color: white;
        }

        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
        }

        .alert-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
        }

        .data-table {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .table {
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table thead th {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            color: #293b50;
            font-weight: 700;
            padding: 1.5rem 1rem;
            border: none;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody td {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            color: #475569;
        }

        .table tbody tr:hover {
            background: #f8fafc;
            transform: translateX(2px);
            transition: all 0.2s ease;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .invoice-link {
            color: #ea6118;
            text-decoration: none;
            font-weight: 600;
            font-family: 'Courier New', monospace;
            background: #fef7ed;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            border: 1px solid #fed7aa;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .invoice-link:hover {
            background: #ea6118;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(234, 97, 24, 0.3);
        }

        .amount-display {
            font-family: 'Courier New', monospace;
            background: #f0fdf4;
            color: #15803d;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            border: 1px solid #bbf7d0;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .date-display {
            background: #f8fafc;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            color: #293b50;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .summary-badge {
            background: linear-gradient(135deg, #0891b2, #0e7490);
            color: white;
            padding: 0.35rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .stats-summary {
            background: linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.1));
            border: 2px solid #ea6118;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            text-align: center;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: #ea6118;
            margin-bottom: 0.5rem;
        }

        .stats-label {
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        .icon-primary {
            color: #ea6118;
            font-size: 1.2rem;
        }

        .info-card {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border: 2px solid #0891b2;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-card h5 {
            color: #0891b2;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-card p {
            color: #64748b;
            margin-bottom: 0;
            line-height: 1.6;
        }

        .dt-buttons {
            margin-bottom: 1rem;
        }

        .dt-button {
            background: linear-gradient(135deg, #ea6118, #293b50) !important;
            border: none !important;
            color: white !important;
            border-radius: 8px !important;
            padding: 0.5rem 1rem !important;
            font-weight: 600 !important;
            margin-right: 0.5rem !important;
        }

        .dt-button:hover {
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 12px rgba(234, 97, 24, 0.4) !important;
        }

        .no-data {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }

        .no-data i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .credit-notes-section {
            margin-top: 2rem;
        }

        .credit-note-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .credit-note-header {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            padding: 1rem 1.5rem;
            border-bottom: 2px solid #f59e0b;
        }

        .credit-note-title {
            color: #92400e;
            font-weight: 700;
            font-size: 1.1rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .credit-amount {
            background: #fef2f2;
            color: #dc2626;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            border: 1px solid #fecaca;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .credit-amount::before {
            content: '-';
            font-weight: bold;
        }

        .no-credit-notes {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            color: #64748b;
        }

        .instruction-text {
            background: linear-gradient(135deg, #fef7ed, #fed7aa);
            border: 2px solid #f59e0b;
            border-radius: 10px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            color: #92400e;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .invoice-title {
            padding: 10px 15px;
            /* Fix left alignment */
            margin-bottom: 10px;
            color: #293b50;
            /* Your required color */
            font-weight: 600;
        }

        .boxed-title {
            background: #f7f9fc;
            border-left: 4px solid #293b50;
            padding: 12px 15px;
            margin-bottom: 15px;
            color: #293b50;
            font-weight: 600;
        }
    </style>
@endpush

@section('content')
    <div class="invoices-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb-container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">
                    <i class="material-icons-outlined icon-primary">receipt_long</i>
                    Invoices
                </div>&nbsp;
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        {{-- <li class="breadcrumb-item">
                        <i class="material-icons-outlined">home</i>
                    </li> --}}
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active">Invoices</li>
                    </ol>
                </nav>
            </div>
            <button id="backButton" class="btn btn-outline-secondary back-btn">
                <i class="material-icons-outlined me-1">arrow_back</i> Back
            </button>
        </div>

        <!-- Flash Messages -->
        @if (session('success'))
            <div class="alert alert-success" id="flash-message">
                <i class="material-icons-outlined">check_circle</i>
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger" id="flash-error-message">
                <i class="material-icons-outlined">error</i>
                {{ session('error') }}
            </div>
        @endif

        <!-- Information Card -->
        <div class="info-card">
            <h5>
                <i class="material-icons-outlined">info</i>
                Invoice Management
            </h5>
            <p>
                View and manage all your SMS Expert invoices. Click on any invoice number to view detailed Invoice
                information.
                You can also access your credit notes and payment history from this page.
            </p>
        </div>

        <!-- Instruction Text -->
        <div class="instruction-text">
            <i class="material-icons-outlined">touch_app</i>
            <span>Click the invoice number to view the Invoice Details.</span>
        </div>

        <!-- Main Content -->
        <div class="invoices-card">
            @if ((isset($invoice) && count($invoice) > 0) || (isset($invoiceProforma) && $invoiceProforma->count() > 0))
                <!-- Statistics Summary -->
                <div class="stats-summary">
                    <div class="stat-item">
                        <div class="stats-number">{{ count($invoice ?? []) }}</div>
                        <div class="stats-label">Total Invoices</div>
                    </div>
                    <div class="stat-item">
                        <div class="stats-number">{{ isset($invoiceProforma) ? $invoiceProforma->count() : 0 }}</div>
                        <div class="stats-label">Pro Forma Invoices</div>
                    </div>
                    <div class="stat-item">
                        <div class="stats-number">£ {{ number_format((isset($invoice) ? $invoice->sum('fullprice') : 0) + (isset($invoiceProforma) ? $invoiceProforma->sum('fullprice') : 0), 2) }}</div>
                        <div class="stats-label">Total Amount</div>
                    </div>
                    <div class="stat-item">
                        <div class="stats-number">{{ $creditNotes->count() }}</div>
                        <div class="stats-label">Credit Notes</div>
                    </div>
                </div>

                <div class="section-header">
                    <h5 class="section-title">
                        <i class="material-icons-outlined">receipt</i>
                        Your Invoices
                    </h5>
                </div><br>
                @if (isset($invoiceOrder) && $invoiceOrder->count())
                   <h5 class="invoice-title">Click the invoice number to view the Invoice Details.</h5>

                    <div class="data-table">
                        <table id="invoice_all_view" class="table">
                            <thead>
                                <tr class="text-center">
                                    <th><i class="material-icons-outlined">receipt_long</i> Invoice</th>
                                    <th><i class="material-icons-outlined">date_range</i> Date</th>
                                    <th><i class="material-icons-outlined">currency_pound</i> Amount</th>
                                    <th><i class="material-icons-outlined">description</i> Summary</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($invoiceOrder as $invoices)
                                    <tr class="text-center">
                                        <td>
                                            <a href="{{ route('invoice.show', $invoices->invoiceref) }}"
                                                class="invoice-link">
                                                <i class="material-icons-outlined">visibility</i>
                                                {{ $invoices->invoiceref }}
                                            </a>
                                        </td>

                                        <td>
                                            <div class="date-display">
                                                <i class="material-icons-outlined">calendar_today</i>
                                                {{ date('j M Y', $invoices->createddate) }}
                                            </div>
                                        </td>

                                        <td>
                                            <div class="amount-display">
                                                <i class="material-icons-outlined">currency_pound</i>
                                                {{ number_format($invoices->fullprice, 2) }}
                                            </div>
                                        </td>

                                        <td>
                                            <span class="summary-badge">
                                                <i class="material-icons-outlined">credit_card</i>
                                                {{ $invoices->summary }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <br>

                @if (isset($invoiceProforma) && $invoiceProforma->count())
                    <h5 class="invoice-title">Click the invoice number to view the <strong>Pro Forma</strong> Invoice Details.</h5>

                    <div class="data-table">
                        <table id="invoice_all_view" class="table">
                            <thead>
                                <tr class="text-center">
                                    <th><i class="material-icons-outlined">receipt_long</i> Invoice</th>
                                    <th><i class="material-icons-outlined">date_range</i> Date</th>
                                    <th><i class="material-icons-outlined">currency_pound</i> Amount</th>
                                    <th><i class="material-icons-outlined">description</i> Summary</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($invoiceProforma as $invoices)
                                    <tr class="text-center">
                                        <td>
                                            <a href="{{ route('invoice.show', $invoices->invoiceref) }}"
                                                class="invoice-link">
                                                <i class="material-icons-outlined">visibility</i>
                                                {{ $invoices->invoiceref }}
                                            </a>
                                        </td>

                                        <td>
                                            <div class="date-display">
                                                <i class="material-icons-outlined">calendar_today</i>
                                                {{ date('j M Y', $invoices->createddate) }}
                                            </div>
                                        </td>

                                        <td>
                                            <div class="amount-display">
                                                <i class="material-icons-outlined">currency_pound</i>
                                                {{ number_format($invoices->fullprice, 2) }}
                                            </div>
                                        </td>

                                        <td>
                                            <span class="summary-badge">
                                                <i class="material-icons-outlined">credit_card</i>
                                                {{ $invoices->summary }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif






                {{-- <div class="data-table">
                    <table id="invoice_all_view" class="table">
                        <thead>
                            <tr class="text-center">
                                <th>
                                    <i class="material-icons-outlined">receipt_long</i>
                                    Invoice
                                </th>
                                <th>
                                    <i class="material-icons-outlined">date_range</i>
                                    Date
                                </th>
                                <th>
                                    <i class="material-icons-outlined">currency_pound</i>
                                    Amount
                                </th> --}}
                {{-- <th>
                                <i class="material-icons-outlined">description</i>
                                Summary
                            </th> --}}
                {{-- </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice as $invoices)
                                <tr class="text-center">
                                    <td>
                                        <a href="{{ route('invoice.show', $invoices->invoiceref) }}" class="invoice-link">
                                            <i class="material-icons-outlined">visibility</i>
                                            {{ $invoices->invoiceref ?? 'N/A' }}
                                        </a>
                                    </td>
                                    <td>
                                        <div class="date-display">
                                            <i class="material-icons-outlined">calendar_today</i>
                                            {{ date('j M Y', $invoices->createddate ?? time()) }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="amount-display">
                                            <i class="material-icons-outlined">payments</i>
                                            £{{ number_format($invoices->fullprice ?? 0, 2) }}
                                        </div>
                                    </td> --}}
                {{-- <td>
                                    <span class="summary-badge">
                                        <i class="material-icons-outlined">credit_card</i>
                                        Pre-purchase of SMS Expert Credits
                                    </span>
                                </td> --}}
                {{-- </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div> --}}
            @else
                <!-- No Data State -->
                <div class="section-card">
                    <div class="no-data">
                        <i class="material-icons-outlined">receipt_long</i>
                        <h4>No Invoices Found</h4>
                        <p>You don't have any invoices yet. Invoices will appear here when you make purchases or subscribe
                            to services.</p>
                    </div>
                </div>
            @endif
        </div>

        <!-- Credit Notes Section -->
        <div class="credit-notes-section">
            <div class="credit-note-card">
                <div class="credit-note-header">
                    <h6 class="credit-note-title">
                        <i class="material-icons-outlined">receipt</i>
                        Credit Notes
                    </h6>
                </div>
                <div class="section-content">
                    @if ($creditNotes->isNotEmpty())
                        <div class="data-table">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>
                                            <i class="material-icons-outlined">confirmation_number</i>
                                            Credit Note Number
                                        </th>
                                        <th>
                                            <i class="material-icons-outlined">money_off</i>
                                            Amount (inc. VAT)
                                        </th>
                                        <th>
                                            <i class="material-icons-outlined">receipt_long</i>
                                            Against Invoice
                                        </th>
                                        <th>
                                            <i class="material-icons-outlined">date_range</i>
                                            Issue Date
                                        </th>
                                        <th>
                                            <i class="material-icons-outlined">info</i>
                                            Reason
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($creditNotes as $note)
                                        <tr>
                                            <td>
                                                <span class="invoice-link"
                                                    style="background: #fef2f2; border-color: #fecaca; color: #dc2626;">
                                                    <i class="material-icons-outlined">note</i>
                                                    {{ $note->id ?? 'N/A' }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="credit-amount">
                                                    <i class="material-icons-outlined">money_off</i>
                                                    £{{ number_format($note->price_inc_vat ?? 0, 2) }}
                                                </div>
                                            </td>
                                            <td>
                                                <span class="invoice-link">
                                                    <i class="material-icons-outlined">link</i>
                                                    {{ $note->invoice_id ?? 'N/A' }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="date-display">
                                                    <i class="material-icons-outlined">calendar_today</i>
                                                    {{ \Carbon\Carbon::parse($note->date_inserted)->format('jS F Y') ?? 'N/A' }}
                                                </div>
                                            </td>
                                            <td>
                                                <span class="summary-badge"
                                                    style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                                                    <i class="material-icons-outlined">info</i>
                                                    {{ $note->reason ?? 'No reason provided' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="no-credit-notes">
                            <i class="material-icons-outlined">note_add</i>
                            <h6 class="mt-2 mb-2">No Credit Notes</h6>
                            <p class="mb-0">You currently have no credit notes. Credit notes will appear here if any
                                refunds or adjustments are processed.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script src="{{ asset('assets/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize DataTable
            // if (document.getElementById('invoice_all_view')) {
            //     $('#invoice_all_view').DataTable({
            //         responsive: true,
            //         pageLength: 25,
            //         lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            //         dom: 'Bfrtip',
            //         buttons: [
            //             {
            //                 extend: 'csv',
            //                 text: '<i class="material-icons-outlined">download</i> Export CSV',
            //                 className: 'dt-button'
            //             },
            //             {
            //                 extend: 'print',
            //                 text: '<i class="material-icons-outlined">print</i> Print',
            //                 className: 'dt-button'
            //             }
            //         ],
            //         language: {
            //             search: "Search invoices:",
            //             lengthMenu: "Show _MENU_ invoices per page",
            //             info: "Showing _START_ to _END_ of _TOTAL_ invoices",
            //             infoEmpty: "No invoices available",
            //             infoFiltered: "(filtered from _MAX_ total invoices)",
            //             zeroRecords: "No matching invoices found",
            //             emptyTable: "No invoices available"
            //         },
            //         columnDefs: [
            //             { orderable: false, targets: 3 } // Disable sorting on Summary column
            //         ],
            //         order: [[1, 'desc']] // Sort by date descending by default
            //     });
            // }

            // Auto-hide flash messages
            setTimeout(function() {
                const flashMessages = document.querySelectorAll('#flash-message, #flash-error-message');
                flashMessages.forEach(msg => {
                    if (msg) {
                        msg.style.opacity = '0';
                        msg.style.transform = 'translateY(-20px)';
                        setTimeout(() => msg.style.display = 'none', 300);
                    }
                });
            }, 4000);

            // Smooth animations
            const cards = document.querySelectorAll('.invoices-card, .credit-note-card, .stats-summary');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Invoice link hover effects
            const invoiceLinks = document.querySelectorAll('.invoice-link');
            invoiceLinks.forEach(link => {
                link.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-1px) scale(1.05)';
                });

                link.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            console.log('Invoices page loaded successfully!');

            document.querySelectorAll('.material-icons-outlined.money').forEach(function(el) {
                el.innerHTML = '£'; // replace icon with pound sign
            });

        });
    </script>
@endpush
