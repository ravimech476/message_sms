<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset</title>
    <link rel="icon" href="{{ asset('assets/images/auth/smsexpert_favion.png') }}" type="image/png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- <link href="https://cdnjs.cloudflare.com/ajax/libs/pwstrength-bootstrap/3.0.9/pwstrength-bootstrap.min.css" rel="stylesheet"> --}}
    <style>
        #strength-message {
            /* margin-top: 5px; */
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header text-center bg-primary text-white" style="background-color: #293B50 !important">
                <h4>Password Reset</h4>
            </div>
            <div class="card-body">
                {{-- <p class="text-center text-danger" style="font-size: 12px;">
                    Please be advised if you use an API with the SMS Expert software and you change your password, this may affect your API.
                </p> --}}
                <form action{{ route('update.password', ['userId' => $userId]) }} method="POST">
                    @csrf
                    <input type="hidden" name="id" value="{{ $userId ?? '' }}">

                    <div class="mb-3 row">
                        <label for="password" class="col-sm-4 col-form-label text-end">New Password</label>
                        <div class="col-sm-6">
                            <input type="password" class="form-control" id="password" name="password" maxlength=50
                                required>
                        </div>
                    </div>

                    <div class="mb-3 row">
                        <label for="cpassword" class="col-sm-4 col-form-label text-end">Confirm New Password</label>
                        <div class="col-sm-6">
                            <input type="password" class="form-control" id="cpassword" name="cpassword" maxlength=50
                                required>
                        </div>
                    </div>

                    <div class="mb-3 row">
                        <div class="col-sm-4"></div>
                        <div class="col-sm-6">
                            <div class="progress">
                                <div class="progress-bar" id="jak_pstrength" role="progressbar" style="width: 0%;">
                                    <div id="strength-message" class="text-danger"></div>
                                </div>
                            </div>
                            {{-- <div id="strength-message" class="text-danger"></div> --}}
                        </div>
                    </div>

                    {{-- <div class="mb-3 row">
                        <div class="col-sm-4"></div>
                        <div class="col-sm-6">
                            <div id="pwd-container">
                                <div class="pwstrength_viewport_progress"></div>
                            </div>
                        </div>
                    </div> --}}

                    <div class="text-center">
                        <button type="submit" class="btn btn-primary"
                            style="background-color: #293B50 !important">Submit</button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <p>For further assistance, please contact SMS Expert at <strong>01509 606 305</strong>.</p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    {{-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script> --}}
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    {{-- <script src="https://cdnjs.cloudflare.com/ajax/libs/pwstrength-bootstrap/3.0.9/pwstrength-bootstrap.min.js"></script> --}}
    <script>
        function passwordStrength(password) {
            var desc = [{
                    'width': '20%'
                }, // Weak
                {
                    'width': '40%'
                }, // Average
                {
                    'width': '60%'
                }, // Medium Password
                {
                    'width': '80%'
                }, // Strong Password
                {
                    'width': '100%'
                } // Very Strong Password
            ];
            var descClass = ['bg-danger', 'bg-warning', 'bg-info', 'bg-primary', 'bg-success'];
            // var message = ['Too Weak', 'Weak', 'Fair', 'Good', 'Excellent'];
            var message = ['Weak', 'Average', 'Medium Password', 'Strong Password', 'Very Strong Password'];

            var score = 0;

            // Scoring logic
            if (password.length >= 6) score++; // At least 6 characters
            if (password.length >= 8) score++; // At least 8 characters (better strength)
            if (password.match(/[A-Z]/) && password.match(/[a-z]/)) score++; // Both upper and lower case
            if (password.match(/\d+/)) score++; // At least one number
            if (password.match(/[@$!%*?&#]/)) score++; // At least one special character

            // Update progress bar
            $("#jak_pstrength").css('width', desc[score].width);
            $("#jak_pstrength").removeClass().addClass('progress-bar ' + descClass[score]);

            // Update strength message
            $("#strength-message").text(message[score]);

            // Change text color for strength 
            $("#strength-message").removeClass().addClass('text-black');
            // if (score <= 10) {
            //     $("#strength-message").removeClass('text-success').addClass('text-black');
            // } else {
            //     $("#strength-message").removeClass('text-danger').addClass('text-success');
            // }
        }

        $(document).ready(function() {
            $("#password").on('input', function() {
                var password = $(this).val();
                passwordStrength(password);
            });

            $("form").on('submit', function(e) {
                var password = $('#password').val();
                var cpassword = $('#cpassword').val();

                if (password !== cpassword) {
                    alert("Passwords do not match!");
                    e.preventDefault();
                    $('#cpassword').focus();
                } else if (password.length < 6) {
                    alert("Password must be at least 6 characters long.");
                    e.preventDefault();
                    $('#password').focus();
                }
            });
        });
    </script>
</body>

</html>
