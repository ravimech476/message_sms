@extends('admin.layouts.app')
@section('title')
    {{ __('CRM') }}
@endsection
@push('style')
    <style>
        .button19 {
            background-color: green;
            color: white;
            font-size: 12px;
            padding: 4px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        div.dataTables_wrapper div.dataTables_filter {
            text-align: left !important;
        }
        .breadcrumb-item+.breadcrumb-item::before {
            content: " / " !important;
            color: #6c757d !important;
            /* optional grey */
        }
    </style>
@endpush
<?php
// $get_description = $user->options->first();
?>

@section('content')
    <!--start main wrapper-->
   <main class="main-wrapper" id="main-wrapper">

        <div class="main-content">
            <!--breadcrumb-->
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
                <div class="breadcrumb-title pe-3 title-name">Customer Emails</div>
                <div class="ps-3">
                      <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0" style="background: none;">
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Customer Emails</li>
                        </ol>
                    </nav>
                    {{-- <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><i class="bx bx-home-alt"></i>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page"><a
                                    href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        </ol>
                    </nav> --}}
                </div>
                <!-- Back Button -->
                <div class="me-2 back-button-container"
                    style="position: absolute; right: 0; top: 50%; transform: translateY(-50%);">
                    <button id="backButton" class="btn btn-primary btn-sm">
                        <i class="bx bx-arrow-back"></i> Back
                    </button>
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

                    <div class="col-12">
                        <div class="row g-2">
                            {{-- <div class="col-12 col-md-auto">
                                <a href="{{ route('email.form') }}">
                                    <button class="btn btn-primary w-100 btn-sm">Send Email</button>
                                </a>
                            </div> --}}
                            <!-- Hidden form that will carry selected emails -->
                            <div class="col-12 col-md-auto">
                                <form id="emailRedirectForm" action="{{ route('email.form') }}" method="GET">
                                    <input type="hidden" name="emails" id="selectedEmailsInput">
                                    <button type="submit" class="btn btn-success btn-sm w-100">Send Email</button>
                                </form>
                            </div>
                            {{-- <div class="col-12 col-md-auto">
                                <a href="{{ route('admin.export.postpay') }}">
                                    <button class="btn btn-primary w-100 btn-sm"> Post Pay Customer Report</button>
                                </a>
                            </div>
                            <div class="col-12 col-md-auto">
                                <a href="{{ route('admin.export.daily_sms') }}">
                                    <button class="btn btn-primary w-100 btn-sm"> Daily SMS Report</button>
                                </a>
                            </div>
                            <div class="col-12 col-md-auto">
                                <a href="{{ route('admin.export.money_transferred') }}">
                                    <button class="btn btn-primary w-100 btn-sm"> Money Transferred Report </button>
                                </a>
                            </div> --}}
                        </div>
                    </div>
                    <br>

                    <table id="email_all_view" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr class="text-center">
                                <th>Select</th>
                                <th>User Name</th>
                                <th>Business Name</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Rows populated via server-side DataTables AJAX (see init script below) --}}
                        </tbody>
                    </table>

                    {{-- <table id="email_all_view" class="table table-striped table-bordered" style="width:100%">
                            <thead>
                                <tr class="text-center">
                                    <th>User Name</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($userData as $record)
                                    <tr class="text-center">
                                        <td>
                                            {{ $record->uname ?? '' }}
                                        </td>
                                        <td>
                                            {{ $record->contactemail ?? '' }}
                                        </td>
                                    </tr>
                                @endforeach

                            </tbody>

                        </table> --}}
                    {{-- <div class="col-12">
                            <div class="row g-2">
                                <div class="col-12 col-md-auto">
                                    <a href="{{ route('email.form') }}">
                                        <button class="btn btn-primary w-100">Send Email</button>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <br> --}}
                </div>

            </div>
        </div>


    </main>
    <!--end main wrapper-->
    <!-- Footer -->
    @include('admin.layouts.footer')
    <!-- End Footer -->
@endsection
@push('js')
    {{-- <script src="assets/js/bootstrap.bundle.min.js"></script> --}}
    <script src="{{ asset('assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/plugins/metismenu/metisMenu.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>
    <script>
        // Selected emails persist across pages (DataTables only renders the current page in the DOM).
        let selectedEmails = new Set();

        $(document).ready(function() {
            const table = $('#email_all_view').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('admin.client.emails.data') }}",
                    type: 'GET'
                },
                columns: [
                    { data: 'select',       name: 'select',       orderable: false, searchable: false, className: 'text-center' },
                    { data: 'uname',        name: 'uname',        className: 'text-center' },
                    { data: 'busname',      name: 'busname',      className: 'text-center' },
                    { data: 'contactemail', name: 'contactemail', className: 'text-center' },
                ],
                order: [[1, 'asc']],
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                dom: "<'row mb-3'<'col-md-6'f><'col-md-6 text-end'l>>" +
                    "<'row'<'col-12'tr>>" +
                    "<'row mt-3'<'col-md-6'i><'col-md-6 text-end'p>>",
                responsive: true,
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    zeroRecords: "No matching records found",
                    processing: "Loading..."
                }
            });

            // Restore checkbox state on every page draw (DataTables redraws on page/search/sort).
            table.on('draw', function() {
                $('.email-checkbox').each(function() {
                    this.checked = selectedEmails.has(this.value);
                });
            });

            // Track which emails the user has ticked across pages.
            $(document).on('change', '.email-checkbox', function() {
                if (this.checked) {
                    selectedEmails.add(this.value);
                } else {
                    selectedEmails.delete(this.value);
                }
            });

            // "Send Email" button:
            //   - If any rows are ticked: send to that selection.
            //   - If nothing is ticked: send to ALL customers (server resolves "all" via emailform()).
            $('#emailRedirectForm').on('submit', function(e) {
                if (selectedEmails.size > 0) {
                    e.preventDefault();
                    $('#selectedEmailsInput').val(Array.from(selectedEmails).join(','));
                    this.submit();
                }
                // else: submit with empty `emails` param → controller falls back to all customers.
            });
        });

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
