@extends('admin.layouts.app')
@section('title')
    {{ __('CRM') }}
@endsection
@push('style')
    <style>
        /* Change breadcrumb separator to "/" */
        .breadcrumb-item+.breadcrumb-item::before {
            content: " / " !important;
            color: #6c757d !important;
            /* optional grey */
        }
    </style>
@endpush
<?php
// Generate unique bigid
$bigid = md5(uniqid(rand(), true));
$newuserid = md5(uniqid(rand(), 1));
$newusr = substr($newuserid, 0, 8);
$newpwd = substr($newuserid, 8, 8);

?>

@section('content')
    <!--start main wrapper-->
    <main class="main-wrapper" id="main-wrapper">

        <div class="main-content">
            <!--breadcrumb-->
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
                <div class="breadcrumb-title pe-3 title-name">Add Customer</div>
                <div class="ps-3">
                    {{-- <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><i class="bx bx-home-alt"></i>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page"><a
                                    href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                                    <li class="breadcrumb-item active">Add Customer</li>
                        </ol>
                    </nav> --}}
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0" style="background: none;">
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Customers</li>
                            <li class="breadcrumb-item active" aria-current="page">Add Customer</li>
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
                            <div class="d-flex align-items-start justify-content-between mb-3">
                                <h5 class="mb-0 fw-bold theme-dependent">Add Customer</h5>
                            </div>
                            <div class="bs-stepper-content">
                                <div id="test-l-1" role="tabpanel" class="bs-stepper-pane"
                                    aria-labelledby="stepper1trigger1">
                                    <form action="{{ route('save.client.lead') }}" method="POST" id="myForm">
                                        @csrf
                                        <div class="row g-3">
                                            {{-- Core Fields (Like Old System) --}}
                                            <div class="col-12 col-lg-6">
                                                <label for="newuserid" class="form-label">BigID</label>
                                                <input type="text" class="form-control" id="newuserid" name="newuserid"
                                                    size="100" value="{{ $bigid ?? '' }}" readonly>
                                            </div>
                                            <div class="col-12 col-lg-6">
                                                <label for="newusr" class="form-label">Username <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="newusr" name="newusr"
                                                    size="100" value="{{ $newusr ?? '' }}" required>
                                            </div>
                                            <div class="col-12 col-lg-6">
                                                <label for="newpwd" class="form-label">Password <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="newpwd" name="newpwd"
                                                    size="100" value="{{ $newpwd ?? '' }}" required>
                                            </div>

                                            <div class="col-12 col-lg-6">
                                                <label for="contactname" class="form-label">Contact Name (or task)</label>
                                                <input type="text" class="form-control" id="contactname"
                                                    name="contactname" size="100" value="">
                                            </div>
                                            <div class="col-12 col-lg-6">
                                                <label for="businessname" class="form-label">Business Name (or task description)</label>
                                                <input type="text" class="form-control" id="businessname"
                                                    name="businessname" size="100" value="">
                                            </div>

                                            <div class="col-12 col-lg-6">
                                                <label for="theemail" class="form-label">Email</label>
                                                <input type="email" class="form-control" id="theemail"
                                                    name="theemail" size="100" value="">
                                            </div>

                                            <div class="col-12 col-lg-6">
                                                <label for="thephone" class="form-label">Phone</label>
                                                <input type="text" class="form-control" id="thephone"
                                                    name="thephone" size="100" value=""
                                                    oninput="this.value = this.value.replace(/[^0-9+]/g, '')">
                                            </div>

                                            <div class="col-12 col-lg-6">
                                                <label for="themobile" class="form-label">Mobile</label>
                                                <input type="text" class="form-control" id="themobile"
                                                    name="themobile" size="100" value=""
                                                    oninput="this.value = this.value.replace(/[^0-9+]/g, '')">
                                            </div>

                                            <div class="col-12 col-lg-6">
                                                <label class="form-label">Customer Type</label>
                                                <br>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="customer_type"
                                                        id="customer_type_prepaid" value="Prepaid" checked>
                                                    <label class="form-check-label"
                                                        for="customer_type_prepaid">Prepaid</label>
                                                </div>

                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="customer_type"
                                                        id="customer_type_postpaid" value="Postpaid">
                                                    <label class="form-check-label"
                                                        for="customer_type_postpaid">Postpaid</label>
                                                </div>
                                            </div>

                                        </div>
                                        <input type="hidden" name="thelab2id" value="itagg">
                                        <input type="hidden" name="savequicknewclientlead" value="y">

                                        @php($onboardingRate = \App\Http\Controllers\Admin\GlobalPricingController::getPricingValues()['onboarding_sms_rate'])
                                        <div class="alert alert-info d-flex align-items-center mt-2" role="alert">
                                            <i class="material-icons-outlined me-2">sell</i>
                                            <div>
                                                New accounts are created with a UK SMS rate of
                                                <strong>£{{ number_format((float)$onboardingRate, 4) }}</strong>
                                                ({{ rtrim(rtrim(number_format((float)$onboardingRate * 100, 2), '0'), '.') }} pence/text).
                                                Change this in <a href="{{ route('admin.global-pricing.index') }}">Global Pricing</a>.
                                            </div>
                                        </div>
                                        <br>
                                        <button type="submit" class="btn btn-primary">Save</button>
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
        // Auto-hide flash messages
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
