<div class="tab-pane fade {{ session('activeTab') == 'customer-profile' || !session('activeTab') ? 'show active' : '' }}"
    id="customer-profile" role="tabpanel">
    <div class="bs-stepper-content">
        <div id="test-l-1" role="tabpanel" class="bs-stepper-pane" aria-labelledby="stepper1trigger1">

            {{-- Update Form --}}
            <form action="{{ route('customers.update', $record->id) }}" method="POST" id="myForm">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold theme-label-color">Business Name <span
                                class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="busname" maxlength="200"
                            value="{{ urldecode($record->busname ?? '') }}" required>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold theme-label-color">Contact Name <span
                                class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="contactname" maxlength="50"
                            value="{{ urldecode($record->contactname ?? '') }}" required>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold theme-label-color">Address Line 1 <span
                                class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="address1" maxlength="100"
                            value="{{ urldecode($record->address1 ?? '') }}" required>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold theme-label-color">Address Line 2</label>
                        <input type="text" class="form-control" name="address2" maxlength="100"
                            value="{{ urldecode($record->address2 ?? '') }}">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold theme-label-color">Town/City <span
                                class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="town" maxlength="100"
                            value="{{ urldecode($record->town ?? '') }}" required>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold theme-label-color">Country</label>
                        <input type="text" class="form-control" name="country" maxlength="100"
                            value="{{ urldecode($record->country ?? '') }}">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold theme-label-color">Post Code <span
                                class="text-danger">*</span></label>
                        {{-- Post codes are alphanumeric (UK e.g. "LE12 7PU"): allow letters,
                             digits and spaces. The old digit-only mask stripped the letters. --}}
                        <input type="text" class="form-control" name="pcode" maxlength="20"
                            value="{{ urldecode($record->pcode ?? '') }}" required
                            oninput="this.value = this.value.replace(/[^A-Za-z0-9 ]/g, '').toUpperCase()">

                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold theme-label-color">Mobile Number</label>
                        <input type="text" class="form-control" name="mobilenumber" maxlength="50"
                            value="{{ $record->mobilenumber ?? '' }}"
                            oninput="this.value = this.value.replace(/[^0-9+]/g, '')">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold theme-label-color">Phone Number <span
                                class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="phone" id="phone" maxlength="50"
                            value="{{ $record->phone ?? '' }}" required
                            oninput="this.value = this.value.replace(/[^0-9+]/g, '')">
                        <div id="phone-error" class="text-danger mt-1" style="display:none;">
                            Please enter a valid phone number — digits only, starting with
                            <strong>+</strong> for international (e.g. +91…, +1…, +44…) or
                            <strong>0</strong> for national (e.g. 07…, 01932…).
                           </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold theme-label-color">Email Address <span
                                class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="contactemail" maxlength="50" required
                            value="{{ $record->contactemail ?? '' }}">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold theme-label-color">WhatsApp Enabled</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" id="whatsapp_enabled_toggle"
                                {{ ($record->whatsapp_enabled ?? 'no') == 'yes' ? 'checked' : '' }}
                                onchange="toggleWhatsApp({{ $record->id }}, this.checked)">
                            <label class="form-check-label" for="whatsapp_enabled_toggle">
                                <span
                                    id="whatsapp_status_text">{{ ($record->whatsapp_enabled ?? 'no') == 'yes' ? 'Enabled' : 'Disabled' }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold theme-label-color">WhatsApp Rate</label>
                        <input type="text" class="form-control" id="whatsapprate" name="whatsapprate"
                            maxlength="50" value="{{ $record->whatsapprate ?? '' }}"
                            {{ ($record->whatsapp_enabled ?? 'no') == 'yes' ? 'required' : '' }}>
                    </div>

                    <input type="hidden" name="changecusttype" value="y">
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold theme-label-color" for="customer_type">Customer
                            Type</label>
                        {{-- OLD-system rule (exact): a customer is POSTPAID only when customer_type is
                             'Postpaid'; EVERYTHING else — empty string (OLD's prepaid value), 'Prepaid',
                             'prepaid', 'admin', NULL — is PREPAID. Compare case-insensitively. This
                             matches how OLD stores it (Prepaid = '', Postpaid = 'Postpaid') and how the
                             customer list renders it, so old and new always agree. --}}
                        @php $isPostpaid = strtolower(trim($record->customer_type ?? '')) === 'postpaid'; @endphp
                        <select class="form-select" id="customer_type" name="customer_type">
                            <option value="Prepaid" {{ !$isPostpaid ? 'selected' : '' }}>Prepaid</option>
                            <option value="Postpaid" {{ $isPostpaid ? 'selected' : '' }}>Postpaid</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold theme-label-color">Lab2id</label>
                        <input type="hidden" name="changelab2id" value="y">
                        <input type="text" class="form-control" name="newlab2id" maxlength="20"
                            value="{{ urldecode($record->lab2id ?? '') }}">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold theme-label-color">Web (alt)</label>
                        {{-- OLD SYSTEM parity: "Web (alt)" is stored in users.website (not a 'webalt' column). --}}
                        <input type="text" class="form-control" name="webalt" maxlength="100"
                            value="{{ $record->website ?? '' }}">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold theme-label-color">Emails (extra)</label>
                        {{-- OLD SYSTEM parity: "Emails (extra)" is stored in users.contactemail2. --}}
                        <input type="text" class="form-control" name="contactemail2" maxlength="100"
                            value="{{ $record->contactemail2 ?? '' }}">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold theme-label-color">Anon Descr</label>
                        <input type="hidden" name="changeanondesc" value="y">
                        <input type="text" class="form-control" name="newanondesc" maxlength="20"
                            value="{{ urldecode($record->anondesc ?? '') }}">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold theme-label-color">Shopify Cust ID</label>
                        <input type="hidden" name="changeshopifycustid" value="y">
                        <input type="text" class="form-control" name="newshopifycustid" maxlength="20"
                            value="{{ urldecode($record->shopify_cust_id ?? '') }}">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold theme-label-color">Daemon Priority</label>
                        <input type="hidden" name="changeformdaemonpriority" value="y">
                        <input type="text" class="form-control" name="newvaluedaemonpriority" maxlength="25"
                            value="{{ urldecode($record->daemonpriority ?? '') }}">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold theme-label-color">
                            Dashboard Access (<span style="color:#cc0000;">M</span>ain: Dshbrd SMSec <span
                                style="color:#cc0000;">C</span>ampaigns / <span
                                style="color:#cc0000;">A</span>ccounts)
                            {{ $record->dashboardaccess ?? '' }}
                        </label>
                        <input type="hidden" name="changeformdashboardaccess" value="y">
                        <input type="text" class="form-control" name="newvaluedashboardaccess" maxlength="3"
                            value="{{ $record->dashboardaccess ?? '' }}" pattern="^(m|c|a|mc|ca|mca)$"
                            title="Allowed values: m, c, a, mc, ca, mca" required>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form><br>

            {{-- Outstanding Invoices --}}
            @include('admin.user.partials.sections.invoices-section')

            {{-- User Rate Section --}}
            {{-- @include('admin.user.partials.sections.user-rates-section') --}}

            {{-- Profile Sections --}}
            @include('admin.user.partials.sections.profile-sections')

            {{-- IP Address Restriction --}}
            @include('admin.user.partials.sections.ip-restriction-section')

            {{-- Blocked Users --}}
            @include('admin.user.partials.sections.blocked-users-section')

            {{-- Keyword Details --}}
            @include('admin.user.partials.sections.keyword-details-section')

        </div>
    </div>
</div>
@push('js')
    <script>
        // NO format validation on the phone field — accept ANY value the admin enters.
        // A previous hard-coded UK/India regex here rejected valid international numbers (+91, +1, …)
        // and silently blocked account create/edit. Never block submission on phone format.
        function validatePhone(input) {
            const errorDiv = document.getElementById('phone-error');
            if (errorDiv) errorDiv.style.display = 'none';
            return true; // always valid
        }

        // const ukPattern = /^(?:\+44\d{10}|44\d{10}|07\d{9})$/;
        // const indiaPattern = /^\+?91\d{10}$/;

        // function validatePhone(input) {
        //     const phone = input.value.trim();
        //     const errorDiv = document.getElementById('phone-error');

        //     if (phone === '' || ukPattern.test(phone) || indiaPattern.test(phone)) {
        //         errorDiv.style.display = 'none';
        //         return true;
        //     } else {
        //         errorDiv.style.display = 'block';
        //         return false;
        //     }
        // }

        // Form submit validation
        document.getElementById('myForm').addEventListener('submit', function(e) {
            const phoneInput = document.getElementById('phone');
            if (!validatePhone(phoneInput)) {
                e.preventDefault();
                phoneInput.focus();
            }
        });

        // WhatsApp Toggle Function
        function toggleWhatsApp(userId, isChecked) {
            const statusText = document.getElementById('whatsapp_status_text');
            const toggle = document.getElementById('whatsapp_enabled_toggle');
            const whatsappRateInput = document.getElementById('whatsapprate');

            // Enable/disable required attribute
            if (isChecked) {
                whatsappRateInput.setAttribute('required', 'required');

                // Validate rate before proceeding
                if (!whatsappRateInput.value.trim()) {
                    showToast('warning', 'Please enter a WhatsApp rate before enabling.');
                    toggle.checked = false; // revert toggle
                    return false; // stop execution
                }
            } else {
                whatsappRateInput.removeAttribute('required');
            }
            // Disable toggle during request
            toggle.disabled = true;

            // Prepare payload
            const payload = {
                whatsapp_enabled: isChecked ? 'yes' : 'no'
            };

            // If enabled, also send the WhatsApp rate value
            if (isChecked) {
                payload.whatsapprate = whatsappRateInput.value || null;
            }

            fetch(`/admin/user/${userId}/toggle-whatsapp`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(payload)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        statusText.textContent = isChecked ? 'Enabled' : 'Disabled';
                        showToast('success', data.message ||
                            `WhatsApp ${isChecked ? 'enabled' : 'disabled'} successfully`);
                    } else {
                        toggle.checked = !isChecked;
                        showToast('error', data.message || 'Failed to update WhatsApp status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    toggle.checked = !isChecked;
                    showToast('error', 'An error occurred while updating WhatsApp status');
                })
                .finally(() => {
                    toggle.disabled = false;
                });
        }


        // Toast notification function
        function showToast(type, message) {
            // Check if toastr is available
            if (typeof toastr !== 'undefined') {
                toastr.options = {
                    "closeButton": true,
                    "progressBar": true,
                    "positionClass": "toast-top-right",
                    "timeOut": "3000"
                };

                if (type === 'success') {
                    toastr.success(message);
                } else {
                    toastr.error(message);
                }
            } else {
                // Fallback to alert if toastr is not available
                alert(message);
            }
        }
    </script>
@endpush
