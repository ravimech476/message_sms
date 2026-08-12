{{-- Customer Dashboard Tour Partial --}}
{{-- Include this in the customer dashboard layout --}}

{{-- Driver.js CSS --}}
<link href="{{ asset('assets/css/tour/driver.min.css') }}?v=1.3" rel="stylesheet">
<link href="{{ asset('assets/css/tour/sms-tour.css') }}?v=1.3" rel="stylesheet">

{{-- Driver.js JavaScript --}}
<script src="{{ asset('assets/js/tour/driver.min.js') }}?v=1.3"></script>
<script src="{{ asset('assets/js/tour/customer-tour-steps.js') }}?v=1.3"></script>
<script src="{{ asset('assets/js/tour/sms-tour.js') }}?v=1.3"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the tour system
    @php
        $autoStart = isset($showCustomerTour) && $showCustomerTour ? 'true' : 'false';
    @endphp
    const shouldAutoStart = {{ $autoStart }};

    // Check if SMSTour is available
    if (typeof SMSTour !== 'undefined') {
        // Initialize tour
        SMSTour.init('customer', shouldAutoStart);
    } else {
        console.warn('SMSTour not loaded');
    }
});
</script>
