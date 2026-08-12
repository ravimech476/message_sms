<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Link Expired</title>
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
    .alert-danger {
        --bs-alert-color: #ffffff;
        --bs-alert-bg: #293B50;
        --bs-alert-border-color: #293B50;
        --bs-alert-link-color: #f5a623;
        padding: 20px;
        font-family: 'Noto Sans', sans-serif;
    }

    .alert-danger h4 {
        font-weight: 600;
        font-size: 1.5rem;
        margin-bottom: 15px;
        color: #f5a623;
    }

    .alert-danger p {
        font-weight: 400;
        font-size: 1rem;
        color: #ffffff;
    }

    .alert-danger a {
        color: #f5a623;
        text-decoration: underline;
    }

    .alert-danger a:hover {
        color: #ffd700;
    }
  </style>
</head>

<body>

    <div class="container mt-5">
        <div class="alert alert-danger text-center">
            <h4>SMS Expert Profile Update Confirmation</h4>
            <p>This link is not valid. Please contact the iTagg support team at <a href="mailto:care@smsexpert.co.uk ">care@smsexpert.co.uk </a>.</p>
        </div>
    </div>
  
  <!--plugins-->
  <script src="{{ asset('assets/js/jquery.min.js') }}"></script>

  @stack('js')

</body>

</html>
