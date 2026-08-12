@extends('admin.layouts.app')
@section('title')
    {{ __('View Customer') }}
@endsection
@push('style')
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
                <div class="breadcrumb-title pe-3 title-name">View Customer</div>
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
            <div class="row">
                <div class="col-12 col-xl-12 col-xxl-3 d-flex">
                    <div class="card w-100 rounded-4">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="d-flex align-items-center">
                                    <h5 class="mb-0 fw-bold theme-dependent me-2">View Customer</h5>
                                    {{-- <a href="{{ route('create.keyword', ['id' => $record->id]) }}">
                                        <button class="btn btn-primary btn-sm">Add Keyword</button>
                                    </a> --}}
                                </div>
                            </div>
                            
                            <div class="bs-stepper-content">
                                <div id="test-l-1" role="tabpanel" class="bs-stepper-pane"
                                    aria-labelledby="stepper1trigger1">
                                    <form action="{{ route('customers.update', $record->id) }}" method="POST">
                                        @csrf
                                        @method('PUT') 
                                        <div class="row g-3">
                                            <div class="col-12 col-lg-6">
                                                <label class="form-label fw-semibold theme-label-color">Business</label>
                                                <input type="text" class="form-control" name="busname" id="txtURL" maxlength="200" value="{{ urldecode($record->busname ?? '') }}">
                                            </div>
                                            <div class="col-12 col-lg-6">
                                                <label class="form-label fw-semibold theme-label-color">Client</label>
                                                <input type="text" class="form-control" name="contactname" id="txtEmail" maxlength="50" value="{{ urldecode($record->contactname ?? '') }}" required>
                                            </div>
                                            <div class="col-12 col-lg-6">
                                                <label class="form-label fw-semibold theme-label-color">Email</label>
                                                <input type="email" class="form-control" name="contactemail" id="txtName" maxlength="50" value="{{ $record->contactemail ?? '' }}">
                                            </div>
                                            <div class="col-12 col-lg-6">
                                                <label class="form-label fw-semibold theme-label-color">Phone</label>
                                                <input type="text" class="form-control" name="phone" id="txtPhone" maxlength="50" value="{{ $record->phone ?? '' }}">
                                            </div>
                                            <div class="col-12 col-lg-6">
                                                <label class="form-label fw-semibold theme-label-color">Mobile</label>
                                                <input type="text" class="form-control" name="mobilenumber" id="txtMobile" maxlength="50" value="{{ $record->mobilenumber ?? '' }}">
                                            </div>
                                            <div class="col-12 col-lg-6">
                                                <label class="form-label fw-semibold theme-label-color">Web (alt)</label>
                                                <input type="text" class="form-control" name="webalt" id="webalt" maxlength="50" value="{{ $record->webalt ?? '' }}">
                                            </div>
                                        </div>
                                        <br>
                                        <button type="submit" class="btn btn-primary">Update</button>
                                        <a href="{{ route('admin.users') }}" class="btn btn-danger">Cancel</a>
                                    </form>
                                    

                                </div>
                            </div> 
                           

                        </div>
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
