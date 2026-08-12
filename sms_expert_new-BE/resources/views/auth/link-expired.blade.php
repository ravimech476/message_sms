<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Link Expired</title>
    <link rel="icon" href="{{ asset('assets/images/auth/smsexpert_favion.png') }}" type="image/png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header text-center bg-primary text-white" style="background-color: #293B50 !important">
                <h4>Password Reset Link Expired</h4>
            </div>
            <div class="card-body">
                <div class="text-center">
                    <h4>Your password reset link has expired or already been used.</h4>
                    <p>Please request a new reset link if needed.</p>
                    <a href="{{ route('showloginform') }}" class="btn btn-primary" style="background-color: #293B50 !important">Go to Login</a>
                </div>
            </div>
            <div class="card-footer text-center">
                <p>For further assistance, please contact SMS Expert at <strong>01509 606 305</strong>.</p>
            </div>
        </div>
    </div>
</body>
</html>




