<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile Updated</title>
    <!--favicon-->
    <link rel="icon" href="{{ asset('assets/images/auth/smsexpert_favion.png') }}" type="image/png">

    <!--plugins-->
    <link href="{{ asset('assets/plugins/perfect-scrollbar/css/perfect-scrollbar.css') }}" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/plugins/metismenu/metisMenu.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/plugins/metismenu/mm-vertical.css') }}">
    <!--bootstrap css-->
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons+Outlined" rel="stylesheet">
    <!--main css-->
    <link href="{{ asset('assets/css/bootstrap-extended.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/main.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/dark-theme.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/responsive.css') }}" rel="stylesheet">

    <style>
        .alert-success {
            --bs-alert-bg: #293B50;
            --bs-alert-color: #ffffff;
            --bs-alert-border-color: #293B50;
        }

        .alert-success h4 {
            color: #f5a623;
        }

        .alert-success a {
            color: #f5a623;
            text-decoration: underline;
        }

        .alert-success a:hover {
            color: #ffd700;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <div class="alert alert-success text-center">
            <h4>SMS Expert Profile Update Confirmation</h4>
            <p>Thank you. Your profile has been updated, and the changes you requested have taken effect.</p>
            <p><a href="{{ url('/') }}">Click here </a> to return to the SMS Expert Login and continue using your SMS services.</p>
        </div>
    </div>

    <!--plugins-->
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>

    @stack('js')
</body>

</html>
