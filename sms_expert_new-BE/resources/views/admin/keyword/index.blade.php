@extends('admin.layouts.app')
@section('title')
    {{ __('View Keywords') }}
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
    </style>
@endpush
<?php
// $get_description = $user->options->first();
?>

@section('content')
    <!--start main wrapper-->
    <main class="main-wrapper">

        <div class="main-content">
            <!--breadcrumb-->
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
                <div class="breadcrumb-title pe-3 title-name">View Keywords</div>
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
                    <div class="table-responsive">
                        <table id="keyword_all_view" class="table table-striped table-bordered" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Keyword</th>
                                    <th>Purchased Date</th>
                                    <th>Expiry Date</th>
                                    {{-- <th>Next Renewal</th> --}}
                                    <th>Options</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($keywords as $keyword)
                                <tr>
                                    <td>{{ $keyword->keyword ?? '' }}</td>
                                    <td>{{ $keyword->purchased ?? '' }}</td>
                                    <td>{{ $keyword->expiry  ?? ''}}</td>
                                    <td>
                                        <a href="{{ route('keyword.edit', $keyword->id) }}" class="btn btn-warning btn-sm">Edit</a>
                                        <a href="{{ route('keyword.delete', $keyword->id) }}" class="btn btn-danger btn-sm">Delete</a>
                                    </td>
                                    {{-- <td>{{ $keyword->nextcontactaboutrenewal }}</td> --}}
                                </tr>
                            {{-- @empty
                                <tr>
                                    <td colspan="4" class="text-center">No keywords found.</td>
                                </tr> --}}
                            @endforeach

                            </tbody>

                        </table>
                        <div class="col-12">
                            <div class="row g-2">
                                <div class="col-12 col-md-auto">
                                    <a href="{{ route('create.keyword', ['id' => $user->id]) }}"">
                                        <button class="btn btn-primary w-100">Add Keyword</button>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <br>
                    </div>
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
    <script src="assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
    <script src="assets/plugins/metismenu/metisMenu.min.js"></script>
    <script src="assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
    <script src="assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        $(document).ready(function() {
            $('#keyword_all_view').DataTable();
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
