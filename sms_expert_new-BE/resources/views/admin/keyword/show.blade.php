@extends('admin.layouts.app')
@section('title')
    {{ __('Edit Keyword') }}
@endsection
@push('style')
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
                <div class="breadcrumb-title pe-3 title-name">Edit Keyword</div>
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
                                    <h5 class="mb-0 fw-bold theme-dependent me-2">Edit Keyword</h5>
                                </div>
                            </div>
                            
                            <div class="bs-stepper-content">
                                <div id="test-l-1" role="tabpanel" class="bs-stepper-pane"
                                    aria-labelledby="stepper1trigger1">
                                    <form action="{{ route('keyword.update', $keyword->id) }}" method="POST">
                                        @csrf
                                        <div class="row g-3">
                                            <div class="col-12 col-lg-6">
                                                <label class="form-label fw-semibold theme-label-color">Keyword</label>
                                                <input type="text" name="keyword" class="form-control" value="{{ $keyword->keyword ?? '' }}" required>
                                            </div>
                                            <div class="col-12 col-lg-6">
                                                <label class="form-label fw-semibold theme-label-color">Forwarding Email</label>
                                                <input type="email" class="form-control" name="theemail" id="theemail"  value="{{ $keyword->forwarding_email ?? '' }}" required>  
                                            </div>
                                            <div class="col-12 col-lg-6">
                                                <label class="form-label fw-semibold theme-label-color">Start Date</label>
                                                <input type="date" name="purchased" class="form-control" value="{{ $keyword->purchased  ?? ''}}" required>
                                            </div>
                                            <div class="col-12 col-lg-6">
                                                <label class="form-label fw-semibold theme-label-color">End Date</label>
                                                <input type="date" name="expiry" class="form-control" value="{{ $keyword->expiry ?? '' }}" required>
                                            </div>
                                        </div>
                                        <br>
                                        <button type="submit" class="btn btn-primary">Save</button>
                                        {{-- <a href="{{ route('keywords.view', $keyword->users_bigid) }}" class="btn btn-danger">Cancel</a> --}}
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
