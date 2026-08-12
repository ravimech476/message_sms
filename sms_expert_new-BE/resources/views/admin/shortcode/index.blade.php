@extends('admin.layouts.app')
@section('title')
    {{ __('Customers') }}
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
  <main class="main-wrapper" id="main-wrapper">

        <div class="main-content">
            <!--breadcrumb-->
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
                <div class="breadcrumb-title pe-3 title-name">Customers</div>
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
                {{-- <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-12">
                            <form action="platforme.net?itaggpage=controlpanel&requestaction=groups" method="get"
                                class="d-flex justify-content-between align-items-center">
                                <input type="text" id="search" name="searchText" class="form-control me-2"
                                    placeholder="Search" value="">
                                <select id="itemPerPage" class="form-select me-2">
                                    <option value="20" selected="">20 items per page</option>
                                    <option value="50">50 items per page</option>
                                    <option value="100">100 items per page</option>
                                    <option value="250">250 items per page</option>
                                    <option value="500">500 items per page</option>
                                </select>
                                <button type="button" id="submit" class="btn btn-primary"
                                    onclick="searchTextAndItems()">Search</button>
                            </form>
                        </div>
                    </div>

                    <hr>

                    <div class="container">
                        <!-- Header Row -->
                        <div class="row mb-3">
                            <div class="col-12 col-md-3"><strong>Group Name</strong></div>
                            <div class="col-12 col-md-3 text-center"><strong>Members</strong></div>
                            <div class="col-12 col-md-3 text-center"><strong>Edit</strong></div>
                            <div class="col-12 col-md-3 text-center"><strong>Delete</strong></div>
                        </div>

                        <!-- Content Row 1 -->
                        <div class="row mb-3 align-items-center">
                            <div class="col-12 col-md-3">
                                <input name="name_1" class="form-control" value="test" onkeyup="" size="40">
                            </div>
                            <div class="col-12 col-md-3 text-center">
                                0
                            </div>
                            <div class="col-12 col-md-3 text-center">
                                <button class="btn btn-warning btn-sm text-white" onclick="">Edit</button>
                            </div>
                            <div class="col-12 col-md-3 text-center">
                                <button class="btn btn-danger btn-sm" onclick="">Delete</button>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12">
                            <button class="btn btn-primary" onclick="">Add</button>
                            <button class="btn btn-success me-2" onclick="">Save</button>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-end">
                                <div class="pagination">
                                    <a href="javascript:;" class="btn btn-outline-secondary active">1</a>
                                    <!-- You can add more page links here -->
                                    <a href="javascript:;" class="btn btn-outline-secondary">2</a>
                                    <a href="javascript:;" class="btn btn-outline-secondary">3</a>
                                    <a href="javascript:;" class="btn btn-outline-secondary">Next</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div> --}}

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="customer_all_view" class="table table-striped table-bordered" style="width:100%">
                            <thead>
                                <tr>
                                    <th>User Name</th>
                                    {{-- <th>Lab Type</th> --}}
                                    {{-- <th>Password</th> --}}
                                    <th>Type</th>
                                    <th>Business Name</th>
                                    <th>Email</th>
                                    <th>Options</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($shortcodes as $shortcode)
                                <tr>
                                    <td>{{ $shortcode->id }}</td>
                                    <td>{{ $shortcode->number }}</td>
                                    {{-- <td>{{ $shortcode->description }}</td>
                                    <td>
                                        <a href="{{ route('smsshortcodes.edit', $shortcode->id) }}" class="btn btn-warning btn-sm">Edit</a>
                                        <form action="{{ route('smsshortcodes.destroy', $shortcode->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this shortcode?')">Delete</button>
                                        </form>
                                    </td> --}}
                                </tr>
                            @endforeach

                            </tbody>

                        </table>
                        <div class="col-12">
                            <div class="row g-2">
                                <div class="col-12 col-md-auto">
                                    <a href="{{ route('admin.customer.create') }}">
                                        <button class="btn btn-primary w-100">Add Record</button>
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
            $('#customer_all_view').DataTable();
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
