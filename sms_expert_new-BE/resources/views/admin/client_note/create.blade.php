@extends('admin.layouts.app')
@section('title')
    {{ __('Create Client Note') }}
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
                <div class="breadcrumb-title pe-3 title-name">Create Client Note</div>
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
                                    <h5 class="mb-0 fw-bold theme-dependent me-2">Create Client Note</h5>
                                </div>
                            </div>

                            <div class="bs-stepper-content">
                                <div id="test-l-1" role="tabpanel" class="bs-stepper-pane" aria-labelledby="stepper1trigger1">
                                    <form action="{{ route('client.note.store') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="userid" value="{{ $userid }}">
                                        <input type="hidden" name="saveusernotes" value="y">
                                        <input type="hidden" name="oldrowid" id="oldrowid" value="0">
                            
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label for="notes" class="form-label">Note</label>
                                                <textarea name="notes" id="notes" class="form-control" rows="5" cols="50" required></textarea>
                                            </div>
                            
                                            <div class="col-12 col-md-6">
                                                <label for="newnotenextcontactdate" class="form-label">
                                                    Next contact date <small>(optional)</small>
                                                    {{-- Next contact date <small>(optional <i>12 dec 08</i> or <i>'note'</i>)</small> --}}
                                                </label>
                                                <input type="date" name="newnotenextcontactdate" id="newnotenextcontactdate" class="form-control" value="">
                                            </div>
                            
                                            <div class="col-12 col-md-6">
                                                <label for="newnotenextcontacttime" class="form-label">Email subject</label>
                                                <input type="text" name="newnotenextcontacttime" id="newnotenextcontacttime" class="form-control" value="">
                                            </div>
                            
                                            <div class="col-12">
                                                <label class="form-label fw-semibold theme-label-color">Start time (optional)</label>
                                                <div class="form-text">
                                                    <i>'sp'</i>: stevep, <i>'sp2'</i>: stevep2, <i>'am'</i>: admin/accounts(MECHANICS),
                                                    <i>'ap'</i>: admin/accounts(PLAN), <i>'b'</i>: big plan, <i>'bd'</i>: biz dev,
                                                    <i>'lab2'</i>: lab2, <i>'mp'</i>: meeting/plan, <i>'sm1'</i>: sales/marketing 1,
                                                    <i>'sm2'</i>: sales/marketing 2, <i>'sm3'</i>: sales/marketing 3, <i>'smp'</i>: partner,
                                                    <i>'smvp'</i>: vip partner, <i>'cu'</i>: urgent, <i>'chp'</i>: high priority,
                                                    <i>'cp'</i>: priority, <i>'dp'</i>: daily plan
                                                </div>
                                            </div>
                            
                                            <div class="col-12 col-md-6">
                                                <label for="newnotetimelength" class="form-label">Time length (default: <i>10 mins</i>)</label>
                                                <input type="number" name="newnotetimelength" id="newnotetimelength" class="form-control" value="10">
                                            </div>
                                        </div>
                            
                                        <br>
                                        <button type="submit" class="btn btn-primary">Save</button>
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
