@extends('layouts.admin_app')
@section('title')
    {{ __('CRM') }}
@endsection
@push('style')
    <style>
        .btn-buy-sms {
            background-color: #fd7e14 !important;
            color: #fff !important;
            border: 1px solid #fd7e14 !important;
            padding: 0.5rem 1.2rem;
            border-radius: 0.375rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
            font-weight: 500;
        }

        .btn-buy-sms:hover {
            background-color: #e36e0f !important;
            border-color: #e36e0f !important;
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.25);
        }

        .breadcrumb-item+.breadcrumb-item::before {
            content: " / " !important;
            color: #6c757d !important;
        }
    </style>
    @section('content')
        <!--start main wrapper-->
        <main class="main-wrapper">
            <div class="main-content">
                <!--breadcrumb-->
                <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
                    <div class="breadcrumb-title pe-3 title-name">Register New Keyword</div>
                    <div class="ps-3">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0 p-0" style="background: none;">
                                <li class="breadcrumb-item">
                                    <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
                                </li>
                            </ol>
                        </nav>
                    </div>
                </div>
                <!--end breadcrumb-->

                @if (session('success'))
                    <div id="flash-message" class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div id="flash-error-message" class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <div class="row">
                    <div class="col-12 col-xl-12 col-xxl-3 d-flex">
                        <div class="card w-100 rounded-4">
                            <div class="card-body">

                                <p class="mb-2">You are logged in as...
                                    <span class="d-inline-block mt-1 mb-2" style="color: black;">{{ ucfirst(urldecode($user_contactname ?? '')) }}</span>
                                </p>

                                @if (!$canRegister)
                                    {{-- OLD SYSTEM parity (infopage_include_detail2.inc:6713 / 6725) --}}
                                    <div class="d-flex align-items-start justify-content-between mb-3">
                                        <h5 class="mb-0 fw-bold theme-dependent text-danger">Unable to Register Keywords</h5>
                                    </div>
                                    @if (!$isLoggedIn)
                                        <p>Please log in to register a new keyword.</p>
                                    @elseif ($keywordsLeft < 1)
                                        <p>This page is reserved for existing clients who have keywords still to register.</p>
                                        <p>You don't appear to have any remaining un-registered keywords.</p>
                                        <p>Please email <a href="mailto:care@smsexpert.co.uk">care@smsexpert.co.uk</a> to discuss your
                                            account, or upgrade your package to get extra keywords.</p>
                                    @elseif (!$hasPlatinumAccess)
                                        <p>This page is reserved for existing Silver, Gold or Platinum clients.</p>
                                        <p>Please email <a href="mailto:care@smsexpert.co.uk">care@smsexpert.co.uk</a> to discuss
                                            upgrading your account.</p>
                                    @endif
                                @else
                                    <div class="d-flex align-items-start justify-content-between mb-3">
                                        <h5 class="mb-0 fw-bold theme-dependent">Get Additional Keywords on 60300</h5>
                                    </div>
                                    <p class="mb-2">Keywords remaining: <strong>{{ floor($keywordsLeft) }}</strong></p>
                                    <p class="mb-3">Enter a unique keyword (letters and numbers only) to register it on the
                                        <strong>60300</strong> shortcode.</p>

                                    <form action="{{ route('admin.keyword.register.store') }}" method="POST">
                                        @csrf
                                        <div class="mt-2">
                                            Keyword:
                                            <input type="text" name="keyword" maxlength="20" class="maintxt4fields"
                                                pattern="[A-Za-z0-9]+" placeholder="enter unique keyword"
                                                style="text-transform:uppercase;" required
                                                oninput="this.value = this.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase()">
                                            <span class="text-muted">on 60300</span>
                                        </div>

                                        <div class="d-flex justify-content-start mt-3">
                                            <button type="submit" class="btn btn-buy-sms"
                                                onclick="return confirm('Register this keyword on 60300? This uses one of the remaining keyword allowance.\n\nAre you sure?');">
                                                Register Keyword
                                            </button>
                                        </div>
                                    </form>
                                @endif

                            </div>
                        </div>
                    </div>
                </div>

            </div><!--end row-->
        </main>
        <!--end main wrapper-->
        @include('layouts.footer')
    @endsection
    @push('js')
        <script>
            setTimeout(function () {
                let m = document.getElementById('flash-message');
                if (m) m.style.display = 'none';
            }, 3000);
            setTimeout(function () {
                let m = document.getElementById('flash-error-message');
                if (m) m.style.display = 'none';
            }, 4000);
        </script>
    @endpush
