<script>
    // ==========================================
    // TAB STATE MANAGEMENT
    // ==========================================

    // Store active tab in localStorage when clicked
    document.querySelectorAll('.nav-link[data-bs-toggle="tab"], .nav-link[data-bs-toggle="pill"]').forEach(function(
    tab) {
        tab.addEventListener('shown.bs.tab', function(e) {
            const targetTab = e.target.getAttribute('href');
            localStorage.setItem('activeTab', targetTab);
            // Also update URL hash without scrolling
            history.replaceState(null, null, targetTab);
        });
    });

    // Restore active tab on page load
    document.addEventListener('DOMContentLoaded', function() {

        @if (session('migration_redirect'))

            var migrationUrl = "{{ session('migration_url') }}";

            var modalElement = document.getElementById('migrationModal');
            var modal = new bootstrap.Modal(modalElement);
            modal.show();

            document.getElementById('confirmMigration').addEventListener('click', function() {
                window.location.href = migrationUrl;
            });
        @endif
        // Priority 1: Check for server-side session activeTab
        const serverActiveTab = '{{ session('activeTab') }}';

        // Priority 2: Check URL hash
        const urlHash = window.location.hash;

        // Priority 3: Check localStorage
        const storedTab = localStorage.getItem('activeTab');

        let targetTab = null;

        // Determine which tab to activate
        if (serverActiveTab) {
            targetTab = '#' + serverActiveTab;
        } else if (urlHash) {
            targetTab = urlHash;
        } else if (storedTab) {
            targetTab = storedTab;
        }

        // Activate the determined tab
        if (targetTab) {
            const tabTrigger = document.querySelector(`.nav-link[href="${targetTab}"]`);
            if (tabTrigger) {
                const tab = new bootstrap.Tab(tabTrigger);
                tab.show();
            }
        }
    });

    // Handle browser back/forward buttons
    window.addEventListener('hashchange', function() {
        const hash = window.location.hash;
        if (hash) {
            const tabTrigger = document.querySelector(`.nav-link[href="${hash}"]`);
            if (tabTrigger) {
                const tab = new bootstrap.Tab(tabTrigger);
                tab.show();
            }
        }
    });

    // ==========================================
    // FLASH MESSAGES
    // ==========================================

    setTimeout(function() {
        let flashMessage = document.getElementById('flash-message');
        if (flashMessage) flashMessage.style.display = 'none';

        let flashError = document.getElementById('flash-error-message');
        if (flashError) flashError.style.display = 'none';
    }, 2000);

    // ==========================================
    // SCROLL TO TARGET
    // ==========================================

    document.addEventListener("DOMContentLoaded", function() {
        @if (session('scroll_target'))
            setTimeout(function() {
                const el = document.getElementById("{{ session('scroll_target') }}");
                if (el) {
                    el.scrollIntoView({
                        behavior: "smooth",
                        block: "start"
                    });
                }
            }, 300);
        @endif
    });

    // ==========================================
    // DATATABLES INITIALIZATION
    // ==========================================

    $(document).ready(function() {
        const dataTableOptions = {
            dom: "<'row mb-3'<'col-md-6'f><'col-md-6 text-end'l>>" +
                "<'row'<'col-12'tr>>" +
                "<'row mt-3'<'col-md-6'i><'col-md-6 text-end'p>>",
            responsive: true,
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                zeroRecords: "No matching records found",
            }
        };

        if ($('#keyword_all_view').length) {
            $('#keyword_all_view').DataTable(dataTableOptions);
        }

        if ($('#customer-virtual-view').length) {
            $('#customer-virtual-view').DataTable(dataTableOptions);
        }

        if ($('#customer-client-note-view').length) {
            $('#customer-client-note-view').DataTable({
                ...dataTableOptions,
                ordering: false
            });
        }
    });

    // ==========================================
    // IP FORM AJAX SUBMISSION
    // ==========================================

    const ipForm = document.getElementById('ip-form');
    if (ipForm) {
        ipForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const submitBtn = document.getElementById('submit-btn');
            const errorBox = document.getElementById('ip-error');
            const msgBox = document.getElementById('ajax-msg');

            submitBtn.disabled = true;
            errorBox.textContent = '';
            msgBox.innerHTML = '';

            fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    submitBtn.disabled = false;
                    if (data.success) {
                        msgBox.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
                        form.reset();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        errorBox.textContent = data.message || 'Validation failed.';
                    }
                })
                .catch(() => {
                    submitBtn.disabled = false;
                    msgBox.innerHTML = `<div class="alert alert-danger">Invalid IP address format.</div>`;
                });
        });
    }

    // ==========================================
    // EDIT IP BUTTON
    // ==========================================

    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const ipInput = document.getElementById('ip_address');
            const recordIdInput = document.getElementById('record_id');
            const submitBtn = document.getElementById('submit-btn');

            if (ipInput && recordIdInput && submitBtn) {
                ipInput.value = btn.dataset.ip;
                recordIdInput.value = btn.dataset.id;
                submitBtn.textContent = 'Update';
            }
        });
    });

    // ==========================================
    // KEYWORD DETAILS AJAX
    // ==========================================

    $('#keywordForm').on('submit', function(e) {
        e.preventDefault();
        let keywordId = $('#seliTAGG').val();
        let token = $('input[name="_token"]').val();

        if (!keywordId) {
            alert("Please select a keyword.");
            return;
        }

        $.ajax({
            url: "{{ route('admin.keyword.details.ajax') }}",
            method: "POST",
            data: {
                _token: token,
                keyword_id: keywordId
            },
            success: function(response) {
                $('#keywordDetails').html(response);
            },
            error: function(xhr) {
                $('#keywordDetails').html(
                    '<div class="alert alert-danger">Unable to load details.</div>');
            }
        });
    });

    // ==========================================
    // SELECT2 INITIALIZATION
    // ==========================================

    $(document).ready(function() {
        if ($('#country_select').length) {
            $('#country_select').select2({
                theme: 'bootstrap-5',
                placeholder: 'Search and select a country',
                allowClear: true,
                width: '100%'
            });

            $('#country_select').on('change', function() {
                var selectedOption = $(this).find('option:selected');
                var baseCost = selectedOption.data('cost');

                if (baseCost !== undefined && baseCost !== null) {
                    $('#selected_base_cost').text('£' + parseFloat(baseCost).toFixed(4));
                    var suggestedRate = parseFloat(baseCost) * 1.2;
                    $('#rate').attr('placeholder', suggestedRate.toFixed(4));
                } else {
                    $('#selected_base_cost').text('-');
                    $('#rate').attr('placeholder', '0.0000');
                }
            });
        }
    });

    // ==========================================
    // BACK BUTTON
    // ==========================================

    // document.getElementById('backButton')?.addEventListener('click', function() {
    //     window.history.back();
    // });
</script>
