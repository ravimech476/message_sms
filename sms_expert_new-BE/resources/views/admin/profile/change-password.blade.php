@extends('admin.layouts.app')

@section('title')
    {{ __('Change My Password') }}
@endsection

@section('content')
{{-- The layout's <main class="main-wrapper"> wrapper is commented out, so each page provides its own (see admin/layouts/app.blade.php line 671). Without it the content sits underneath the fixed header. --}}
<main class="main-wrapper" id="main-wrapper">
    <div class="main-content">
        <!-- Breadcrumb (same format as admin-users edit page) -->
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
            <div class="breadcrumb-title pe-3 title-name">Change Password</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Change Password</li>
                    </ol>
                </nav>
            </div>
            <div class="me-2 back-button-container" style="position: absolute; right: 0; top: 50%; transform: translateY(-50%);">
                <button id="backButton" class="btn btn-primary btn-sm" onclick="history.back();">
                    <i class="bx bx-arrow-back"></i> Back
                </button>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-7 col-xl-6">
                <div class="card">
                    <div class="card-header d-flex align-items-center gap-2">
                        <i class="material-icons-outlined">lock_reset</i>
                        <h5 class="mb-0">Change My Password</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            Signed in as <strong>{{ $admin['username'] ?? '' }}</strong>
                            ({{ $admin['email'] ?? '' }}).
                            Use this form to change your own password — you do
                            not need a super-admin to do this for you.
                        </p>

                        @if (session('success'))
                            <div class="alert alert-success d-flex align-items-center gap-2">
                                <i class="material-icons-outlined">check_circle</i>
                                <span>{{ session('success') }}</span>
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="alert alert-danger d-flex align-items-center gap-2">
                                <i class="material-icons-outlined">error</i>
                                <span>{{ session('error') }}</span>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('admin.profile.change-password.update') }}" autocomplete="off">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                <input type="password"
                                       name="current_password"
                                       class="form-control @error('current_password') is-invalid @enderror"
                                       autocomplete="current-password"
                                       required>
                                @error('current_password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">New Password <span class="text-danger">*</span></label>
                                <input type="password"
                                       name="password"
                                       class="form-control @error('password') is-invalid @enderror"
                                       minlength="8"
                                       autocomplete="new-password"
                                       required>
                                <small class="form-text text-muted">Minimum 8 characters.</small>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password"
                                       name="password_confirmation"
                                       class="form-control"
                                       minlength="8"
                                       autocomplete="new-password"
                                       required>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <a href="{{ route('admin.dashboard') }}" class="btn btn-light">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="material-icons-outlined align-middle me-1">save</i>
                                    Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
