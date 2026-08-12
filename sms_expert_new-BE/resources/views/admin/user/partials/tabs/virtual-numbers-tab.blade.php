<div class="tab-pane fade {{ session('activeTab') == 'customer-virtual-number' ? 'show active' : '' }}"
    id="customer-virtual-number" role="tabpanel">
    @if (session('success_virtual'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill"></i>
        <strong>Success!</strong>
        <div class="mt-2">{!! session('success_virtual') !!}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if (session('error_virtual'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong>Failed!</strong>
        <div class="mt-2">{!! session('error_virtual') !!}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <div class="col-12">
        {{-- Add Dedicated Virtual Numbers Section --}}
        @include('admin.user.partials.sections.add-dedicated-virtual-numbers')
    </div>

    <br>

    {{-- Keywords/Virtual Numbers List --}}
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-hash"></i> Virtual Numbers + Shortcode Keywords
            </h5>
        </div>
        <div class="card-body">
            <div id="virtual-numbers-list">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading virtual numbers...</p>
                </div>
            </div>
        </div>
    </div>

    <br>

    
</div>

{{-- Add Virtual Number Modal --}}
@include('admin.user.partials.modals.add-virtual-number-modal')

<style>
    .keyword-section {
        margin-bottom: 30px;
        border-left: 4px solid #0d6efd;
        padding-left: 15px;
    }

    .keyword-item {
        background-color: #f8f9fa;
        padding: 15px;
        margin-bottom: 10px;
        border-radius: 5px;
        border: 1px solid #dee2e6;
    }

    .expired-badge {
        color: #dc3545;
        font-weight: bold;
    }

    .suspended-badge {
        color: #dc3545;
    }

    .action-links a {
        margin-right: 10px;
        font-size: 0.9em;
        text-decoration: none;
    }

    .action-links a:hover {
        text-decoration: underline;
    }

    /* Link Colors */
    .link-add-months {
        color: #0d6efd;
        font-weight: 500;
    }

    .link-add-months:hover {
        color: #0a58ca;
    }

    .link-calendar {
        color: #198754;
        font-weight: 500;
    }

    .link-calendar:hover {
        color: #146c43;
    }

    .link-force-expiry {
        color: #dc3545;
        font-weight: 500;
    }

    .link-force-expiry:hover {
        color: #bb2d3b;
    }

    .link-suspend {
        color: #fd7e14;
        font-weight: 500;
    }

    .link-suspend:hover {
        color: #e76a00;
    }

    .link-unexpire {
        color: #6f42c1;
        font-weight: 500;
    }

    .link-unexpire:hover {
        color: #5a32a3;
    }

    .datepicker-inline {
        margin-top: 10px;
        display: none;
    }

    .datepicker-inline.show {
        display: block;
    }

    .shortcode-header {
        background-color: #e9ecef;
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 5px;
        font-weight: bold;
    }

    .remove-btn {
        padding: 3px 8px;
        background: red;
        color: #fff;
        display: inline-block;
        text-align: center;
        border-radius: 10px;
        font-size: 11px;
        font-weight: bold;
        text-decoration: none;
    }

    .remove-btn:hover {
        background: darkred;
        color: #fff;
    }
</style>

@push('js')
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>

<script>
    console.log('Virtual Numbers Script Loading...');
    console.log('jQuery available:', typeof jQuery !== 'undefined');
    
    $(document).ready(function() {
        console.log('Document Ready!');
        const userId = {{ $record->id }};
        console.log('Loading virtual numbers for user ID:', userId);
        
        // Load virtual numbers with keywords
        loadVirtualNumbers();

        function loadVirtualNumbers() {
            const url = `/admin/user/${userId}/virtual-numbers`;
            console.log('Fetching virtual numbers from:', url);
            
            $.ajax({
                url: url,
                method: 'GET',
                dataType: 'json',
                beforeSend: function() {
                    console.log('Sending AJAX request...');
                },
                success: function(response) {
                    console.log('SUCCESS! Response received:', response);
                    if (response.success) {
                        renderVirtualNumbers(response.data, response.today);
                    } else {
                        showError('Failed to load virtual numbers: ' + (response.message || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        error: error
                    });
                    
                    let errorMessage = 'Error loading virtual numbers';
                    if (xhr.status === 404) {
                        errorMessage = 'Route not found. Please check if the route is registered.';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Server error. Please check the Laravel logs.';
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    
                    showError(errorMessage);
                    
                    // Show a more detailed error in the console
                    $('#virtual-numbers-list').html(`
                        <div class="alert alert-danger">
                            <h5>Debug Information:</h5>
                            <p><strong>URL:</strong> ${url}</p>
                            <p><strong>Status:</strong> ${xhr.status} ${xhr.statusText}</p>
                            <p><strong>Error:</strong> ${error}</p>
                            <p><strong>Response:</strong> ${xhr.responseText.substring(0, 500)}</p>
                            <hr>
                            <small>Check browser console (F12) for more details</small>
                        </div>
                    `);
                }
            });
        }

        function renderVirtualNumbers(data, today) {
            console.log('Rendering virtual numbers:', data);
            let html = '';
            
            if (!data || data.length === 0) {
                html = '<p class="text-muted">No virtual numbers or keywords found.</p>';
            } else {
                data.forEach(function(shortcode) {
                    html += `<div class="keyword-section">`;
                    html += `<div class="shortcode-header">
                        '${shortcode.number}' (ID: ${shortcode.shortcodeid}, Provider: ${shortcode.provider || 'N/A'}, Pooled: ${shortcode.pooled || 'N/A'})
                    </div>`;
                    
                    if (shortcode.keywords && shortcode.keywords.length > 0) {
                        shortcode.keywords.forEach(function(keyword, index) {
                            html += renderKeyword(keyword, index, today);
                        });
                    } else {
                        html += '<p class="text-muted">No keywords mapped to this virtual number.</p>';
                    }
                    
                    html += `</div>`;
                });
            }
            
            $('#virtual-numbers-list').html(html);
            
            // Initialize datepickers
            $('.datepicker').each(function() {
                const itaggId = $(this).data('itagg-id');
                $(this).datepicker({
                    dateFormat: 'yy-mm-dd',
                    minDate: 0,
                    onSelect: function(dateText) {
                        updateExpiryDate(itaggId, dateText);
                    }
                });
            });
        }

        function renderKeyword(keyword, index, today) {
            const isActive = keyword.active == 1;
            const isSuspended = keyword.active == 0;
            const expiryDate = new Date(keyword.expiry);
            const todayDate = new Date(today);
            const isExpired = expiryDate < todayDate || keyword.expiry === '1999-05-19';
            const isForcedExpired = keyword.expiry === '1999-05-19';
            
            let html = `<div class="keyword-item">`;
            html += `<div class="row">`;
            html += `<div class="col-md-8">`;
            html += `<strong>${keyword.keyword}</strong>`;
            
            // Expiry status
            if (isForcedExpired) {
                html += ` <span class="badge bg-danger">F-Expired</span>`;
            } else if (isExpired) {
                html += ` <span class="badge bg-danger">Expired</span>`;
            } else {
                
            }
            
            // Suspension status
            if (isSuspended) {
                html += ` <span class="badge bg-warning">Suspended</span>`;
            }
            
            html += `<br>`;
            
            // Expiry date
            if (!isForcedExpired) {
                const formattedDate = formatDate(keyword.expiry);
                if (isExpired) {
                    html += `<span class="expired-badge">Expired: ${formattedDate}</span>`;
                } else {
                    html += `<span>Expires: ${formattedDate}</span>`;
                }
            }
            
            // Forwarding email
            if (keyword.forwarding_email) {
                html += `<br><small class="text-muted"><i class="bi bi-envelope"></i> ${keyword.forwarding_email}</small>`;
            }
            
            html += `</div>`;
            html += `<div class="col-md-4 text-end">`;
            
            // Action buttons
            html += `<div class="action-links">`;

            html += `<br>`;
            html += `<small>Add 12 months: </small><br>`;
            html += `<a href="#" class="link-add-months" onclick="add12MonthsFromToday(${keyword.id}); return false;">from today</a> | `;
            html += `<a href="#" class="link-add-months" onclick="add12MonthsFromExpiry(${keyword.id}); return false;">from expiry</a>`;
            html += `<br>`;
            
            html += `<a href="#" class="link-calendar" onclick="myFunction(${keyword.id}, ${keyword.id}); return false;">
                <i class="fa fa-calendar"></i> Calendar
            </a>`;
            html += `<div id="datepicker${keyword.id}" class="datepicker datepicker-inline" data-itagg-id="${keyword.id}"></div>`;
            
            html += `<br>`;
            html += `<small>Remove number: </small><br>`;
            html += `<a href="#" class="remove-btn" onclick="removeNumber(${keyword.id}); return false;">remove (release to pool)</a><br>`;
            html += `<a href="#" class="link-force-expiry" onclick="forceExpiry(${keyword.id}); return false;">force expiry</a> and `;
            
            const suspensionAction = isActive ? 'suspend' : 'unsuspend';
            const newActiveState = isActive ? 0 : 1;
            html += `<a href="#" class="link-suspend" onclick="toggleSuspension(${keyword.id}, ${newActiveState}); return false;">${suspensionAction}</a>`;
            
            if (isForcedExpired) {
                html += `<br><a href="#" class="link-unexpire" onclick="forceUnexpiry(${keyword.id}); return false;">unexpire (BEWARE DUPLICATES)</a>`;
            }
            
            html += `</div>`;
            html += `</div>`;
            html += `</div>`;
            html += `</div>`;
            
            return html;
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
        }

        // Global functions for onclick handlers
        window.add12MonthsFromToday = function(itaggId) {
            if (confirm('Are you sure you want to add 12 months from today?')) {
                makeRequest('add-12months-today', {itagg_id: itaggId});
            }
        };

        window.add12MonthsFromExpiry = function(itaggId) {
            if (confirm('Are you sure you want to add 12 months from current expiry date?')) {
                makeRequest('add-12months-expiry', {itagg_id: itaggId});
            }
        };

        window.forceExpiry = function(itaggId) {
            if (confirm('Are you sure you want to force expire this keyword?')) {
                makeRequest('force-expiry', {itagg_id: itaggId});
            }
        };

        window.forceUnexpiry = function(itaggId) {
            if (confirm('Are you sure you want to unexpire this keyword? BEWARE OF DUPLICATES!')) {
                makeRequest('force-unexpiry', {itagg_id: itaggId});
            }
        };

        window.toggleSuspension = function(itaggId, newState) {
            const action = newState == 1 ? 'unsuspend' : 'suspend';
            if (confirm(`Are you sure you want to ${action} this keyword?`)) {
                makeRequest('toggle-suspension', {itagg_id: itaggId, active: newState});
            }
        };

        window.removeNumber = function(itaggId) {
            if (confirm('Release this virtual number back to the available pool? It will be removed from this customer and become available for re-assignment.')) {
                makeRequest('remove', {itagg_id: itaggId});
            }
        };

        window.toggleDatepicker = function(itaggId, index) {
            $(`#datepicker${index}`).toggleClass('show');
        };

        window.myFunction = function(itage_id, i) {
            $("#datepicker" + i).css("display", "block");
            
            var $dp = $("#datepicker" + i);
            
            if ($dp.hasClass('hasDatepicker')) {
                $dp.datepicker("destroy");
            } else {
                $dp.datepicker({
                    todayHighlight: true,
                    minDate: new Date(),
                    dateFormat: 'dd-mm-yy',
                    onSelect: function() {
                        var date = $("#datepicker" + i).val();
                        var result = confirm("You have selected the expiry date " + date + ". Please confirm to update the expiry date");
                        
                        if (result) {
                            updateExpiryDate(itage_id, date);
                        }
                        
                        $dp.datepicker("destroy");
                    }
                });
                
                // Show the datepicker
                $dp.datepicker("show");
            }
        };

        window.updateExpiryDate = function(itaggId, dateText) {
            makeRequest('update-expiry', {itagg_id: itaggId, expiry_date: dateText});
        };

        function makeRequest(action, data) {
            // Get CSRF token
            var csrfToken = $('meta[name="csrf-token"]').attr('content');
            if (!csrfToken) {
                csrfToken = '{{ csrf_token() }}';
            }
            
            console.log('Making request to:', action, 'with data:', data);
            console.log('CSRF Token:', csrfToken);
            
            $.ajax({
                url: `/admin/virtual-numbers/${action}`,
                method: 'POST',
                data: $.extend({}, data, {
                    _token: csrfToken
                }),
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
                success: function(response) {
                    if (response.success) {
                        showSuccess(response.message);
                        loadVirtualNumbers(); // Reload the list
                    } else {
                        showError(response.message || 'Operation failed');
                    }
                },
                error: function(xhr) {
                    console.error('Request error:', xhr);
                    let message = 'An error occurred';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    showError(message);
                }
            });
        }

        function showSuccess(message) {
            const alert = `
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill"></i>
                    <strong>Success!</strong>
                    <div class="mt-2">${message}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            $('#customer-virtual-number').prepend(alert);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $('.alert-success').fadeOut();
            }, 5000);
        }

        function showError(message) {
            const alert = `
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>Error!</strong>
                    <div class="mt-2">${message}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            $('#customer-virtual-number').prepend(alert);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $('.alert-danger').fadeOut();
            }, 5000);
        }
    });
</script>
@endpush
