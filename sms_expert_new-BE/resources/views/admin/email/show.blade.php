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

// Get current date, hour, and rounded minute
$current_date = $now->format('d/m/Y');
$current_hour = $now->format('H');
$current_minute = $now->format('i');
$rounded_minute = floor($current_minute / 5) * 5;
?>

@section('content')
    <!--start main wrapper-->
   <main class="main-wrapper" id="main-wrapper">

        <div class="main-content">
            <!--breadcrumb-->
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
                <div class="breadcrumb-title pe-3 title-name">Send Email</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0" style="background: none;">
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Customer Emails</li>
                            <li class="breadcrumb-item active" aria-current="page">Send Email</li>
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
            <div class="row">
                <div class="col-12 col-xl-12 col-xxl-3 d-flex">
                    <div class="card w-100 rounded-4">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="d-flex align-items-center">
                                    <h5 class="mb-0 fw-bold theme-dependent me-2">Send Email</h5>
                                </div>
                            </div>

                            {{-- <div class="bs-stepper-content">
                                <div id="test-l-1" role="tabpanel" class="bs-stepper-pane"
                                    aria-labelledby="stepper1trigger1">
                                    <form id="bulk_email" action="{{ route('send.bulk.email') }}" method="POST">
                                        @csrf

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold theme-label-color">To</label>
                                            <textarea class="form-control" rows="5" style="white-space: pre-line;" disabled>
                                                @foreach ($userData as $user)
                                                {{ $user->contactemail }}
                                                @endforeach
                                            </textarea>

                                            <!-- Hidden input for sending email addresses -->
                                            <input type="hidden" name="emails"
                                                value="{{ $userData->pluck('contactemail')->implode(',') }}">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold theme-label-color">Subject</label>
                                            <input type="text" name="subject" class="form-control"
                                                placeholder="Enter subject" required>
                                            @error('subject')
                                                <div class="text-danger">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold theme-label-color">Content</label>
                                            <textarea name="message" class="form-control" rows="6" placeholder="Enter message" required></textarea>
                                            @error('message')
                                                <div class="text-danger">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <button type="submit" onclick="submitEmailForm('send_now')"
                                            class="btn btn-primary">Send
                                            Now</button>
                                        <br><br>
                                        <div class="mb-4">
                                            <h6 class="mb-3 fw-bold theme-dependent">Schedule Send Email</h6>
                                            <div class="row g-3 align-items-end">
                                                <div class="col-md-3">
                                                    <input type="date" class="form-control" name="send_date"
                                                        value="{{ \Carbon\Carbon::now('Europe/London')->format('Y-m-d') }}" />
                                                </div>
                                                <div class="col-md-3">
                                                    <select name="send_hh" class="form-select">
                                                        @for ($i = 0; $i <= 23; $i++)
                                                            <option value="{{ sprintf('%02d', $i) }}"
                                                                {{ $i == $current_hour ? 'selected' : '' }}>
                                                                {{ sprintf('%02d', $i) }}
                                                            </option>
                                                        @endfor
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <select name="send_mm" class="form-select">
                                                        @for ($i = 0; $i <= 55; $i += 5)
                                                            <option value="{{ sprintf('%02d', $i) }}"
                                                                {{ $i == $rounded_minute ? 'selected' : '' }}>
                                                                {{ sprintf('%02d', $i) }}
                                                            </option>
                                                        @endfor
                                                    </select>
                                                </div>
                                                <div class="col-md-3 d-grid">
                                                    <button type="submit" onclick="submitEmailForm('send_later')"
                                                        class="btn btn-primary">
                                                        Send At
                                                    </button>
                                                </div>
                                            </div>
                                        </div>



                                    </form>
                                </div>
                            </div> --}}
                            <div class="bs-stepper-content">
                                <div id="test-l-1" role="tabpanel" class="bs-stepper-pane"
                                    aria-labelledby="stepper1trigger1">
                                    <form id="bulk_email" action="{{ route('send.bulk.email') }}" method="POST">
                                        @csrf

                                        <!-- Display TO field -->
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold theme-label-color">To</label>
                                            <textarea class="form-control" rows="5" style="white-space: pre-line;" disabled>
                                            @foreach ($emails as $email)
{{ $email }}
@endforeach
                                            </textarea>

                                            <input type="hidden" name="emails" value="{{ implode(',', $emails) }}">
                                        </div>

                                        <!-- Subject -->
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold theme-label-color">Subject</label>
                                            <input type="text" name="subject" class="form-control"
                                                placeholder="Enter subject" required>
                                            @error('subject')
                                                <div class="text-danger">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <!-- Message -->
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold theme-label-color">Content</label>
                                            <textarea name="message" class="form-control" rows="6" placeholder="Enter message" required></textarea>
                                            @error('message')
                                                <div class="text-danger">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <!-- Buttons -->
                                        <button type="button" onclick="submitEmailForm('send_now')"
                                            class="btn btn-primary">Send Now</button>
                                        <br><br>

                                        <!-- Schedule -->
                                        <div class="mb-4">
                                            <h6 class="mb-3 fw-bold theme-dependent">Schedule Send Email</h6>
                                            <div class="row g-3 align-items-end">
                                                <div class="col-md-3">
                                                    <input type="date" class="form-control" name="send_date"
                                                        value="{{ \Carbon\Carbon::now('Europe/London')->format('Y-m-d') }}" />
                                                </div>
                                                <div class="col-md-3">
                                                    <select name="send_hh" class="form-select">
                                                        @for ($i = 0; $i <= 23; $i++)
                                                            <option value="{{ sprintf('%02d', $i) }}"
                                                                {{ $i == $current_hour ? 'selected' : '' }}>
                                                                {{ sprintf('%02d', $i) }}
                                                            </option>
                                                        @endfor
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <select name="send_mm" class="form-select">
                                                        @for ($i = 0; $i <= 55; $i += 5)
                                                            <option value="{{ sprintf('%02d', $i) }}"
                                                                {{ $i == $rounded_minute ? 'selected' : '' }}>
                                                                {{ sprintf('%02d', $i) }}
                                                            </option>
                                                        @endfor
                                                    </select>
                                                </div>
                                                <div class="col-md-3 d-grid">
                                                    <button type="button" onclick="submitEmailForm('send_later')"
                                                        class="btn btn-success">Send At</button>
                                                </div>
                                            </div>
                                        </div>
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
    {{-- Rich-text editor (self-hosted TinyMCE via CDN, no API key). Gives the admin the
         full toolbar including text alignment, so email formatting is preserved. --}}
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        tinymce.init({
            selector: 'textarea[name="message"]',
            height: 380,
            menubar: false,
            plugins: 'advlist lists link image table code autolink',
            toolbar: 'undo redo | blocks fontfamily fontsize lineheight | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table hr | removeformat | code',
            // Web-safe fonts (email clients fall back to these if a font isn't installed).
            font_family_formats: 'Arial=arial,helvetica,sans-serif; Segoe UI=segoe ui,tahoma,sans-serif; Georgia=georgia,serif; Times New Roman=times new roman,times,serif; Courier New=courier new,courier,monospace; Verdana=verdana,geneva,sans-serif; Tahoma=tahoma,geneva,sans-serif; Trebuchet MS=trebuchet ms,helvetica,sans-serif',
            font_size_formats: '8px 10px 12px 14px 15px 16px 18px 20px 24px 28px 32px 36px 48px',
            line_height_formats: '1 1.2 1.4 1.6 1.8 2 2.5',
            branding: false,
            content_style: "body { font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; font-size:15px; line-height:1.6; text-align:left; }",
            // Images are UPLOADED to the server (hosted URL) rather than embedded as
            // base64 — Gmail/Outlook strip base64 inline images, hosted URLs work.
            automatic_uploads: true,
            paste_data_images: true,   // pasted/dragged images auto-upload via the handler
            file_picker_types: 'image',
            images_upload_handler: function (blobInfo, progress) {
                return new Promise(function (resolve, reject) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '{{ route("admin.email.upload.image") }}');
                    xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
                    xhr.upload.onprogress = function (e) { if (e.lengthComputable) progress(e.loaded / e.total * 100); };
                    xhr.onload = function () {
                        if (xhr.status < 200 || xhr.status >= 300) { reject('HTTP Error: ' + xhr.status); return; }
                        var json;
                        try { json = JSON.parse(xhr.responseText); } catch (err) { reject('Invalid upload response'); return; }
                        if (!json || typeof json.location !== 'string') { reject('Upload response missing location'); return; }
                        resolve(json.location);
                    };
                    xhr.onerror = function () { reject('Image upload failed (network error)'); };
                    var formData = new FormData();
                    formData.append('file', blobInfo.blob(), blobInfo.filename());
                    xhr.send(formData);
                });
            }
        });

        function submitEmailForm(actionType) {
            // Push the editor's HTML back into the underlying <textarea> before submitting.
            if (window.tinymce) { tinymce.triggerSave(); }
            const form = document.getElementById('bulk_email');
            if (actionType === 'send_now') {
                form.action = "{{ route('send.bulk.email') }}"; // Route for immediate sending
            } else {
                form.action = "{{ route('schedule.bulk.email') }}"; // Route for scheduled sending
            }
            form.submit();
        }

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
