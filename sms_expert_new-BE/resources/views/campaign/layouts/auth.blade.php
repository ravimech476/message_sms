<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - SMS Expert</title>
    <!--favicon-->
    <link rel="icon" href="{{ asset('assets/images/auth/smsexpert_favion.png') }}" type="image/png">

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/css?family=Material+Icons+Outlined&display=block" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons&display=block" rel="stylesheet">

    <!--plugins-->
    <link href="{{ asset('assets/plugins/perfect-scrollbar/css/perfect-scrollbar.css') }}" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/plugins/metismenu/metisMenu.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/plugins/metismenu/mm-vertical.css') }}">
    <!--bootstrap css-->
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!--main css-->
    <link href="{{ asset('assets/css/bootstrap-extended.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/main.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/dark-theme.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/responsive.css') }}" rel="stylesheet">

    <style>
        :root {
            --primary-color: #ea6118;
            --secondary-color: #293b50;
            --background-color: #f8fafc;
            --card-bg: white;
            --border-color: #e2e8f0;
            --text-primary: #293b50;
            --text-secondary: #64748b;
        }

        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            font-family: 'Inter', sans-serif;
            color: var(--text-primary);
            min-height: 100vh;
        }

        .section-authentication-cover {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }

        .auth-cover-left {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            position: relative;
            overflow: hidden;
        }

        .auth-cover-left::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 70% 70%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
        }

        .auth-img-cover-login {
            max-width: 100%;
            height: auto;
            filter: brightness(1.1) drop-shadow(0 10px 30px rgba(0, 0, 0, 0.2));
            position: relative;
            z-index: 2;
        }

        .auth-cover-right {
            background: var(--card-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            position: relative;
        }

        .auth-cover-right::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 20%, rgba(234, 97, 24, 0.02) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(41, 59, 80, 0.02) 0%, transparent 50%);
        }

        .auth-card {
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.1),
                0 4px 16px rgba(0, 0, 0, 0.05);
            transition: all 0.4s ease;
            position: relative;
            z-index: 2;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .auth-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .auth-card:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 30px 80px rgba(0, 0, 0, 0.15),
                0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-top: 1rem;
        }

        .auth-header h4 {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .auth-header p {
            color: var(--text-secondary);
            font-size: 1rem;
            font-weight: 400;
            margin-bottom: 0;
        }

        .theme-label-color,
        .form-label {
            color: var(--text-primary) !important;
            font-weight: 600;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 0.875rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: var(--card-bg);
            color: var(--text-primary);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(234, 97, 24, 0.25);
            outline: none;
            transform: translateY(-1px);
        }

        .form-control::placeholder {
            color: #94a3b8;
            font-weight: 400;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            color: white;
            box-shadow: 0 4px 16px rgba(234, 97, 24, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(234, 97, 24, 0.4);
            color: white;
        }

        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 4px 16px rgba(234, 97, 24, 0.3);
        }

        .btn-light {
            background: #f8fafc;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--text-primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .btn-light:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(234, 97, 24, 0.2);
        }

        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
        }

        .alert-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .alert-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .alert::before {
            font-family: 'Material Icons Outlined';
            font-size: 1.2rem;
        }

        .alert-success::before {
            content: 'check_circle';
        }

        .alert-danger::before {
            content: 'error';
        }

        .input-icon {
            position: relative;
        }

        .input-icon .form-control {
            padding-left: 3rem;
        }

        .input-icon .icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            z-index: 10;
            font-size: 1.2rem;
        }

        .d-grid .btn {
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        /* Loading animation */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Fade in animation */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .section-authentication-cover {
                padding: 1rem;
            }
            
            .auth-card {
                border-radius: 16px;
                margin: 1rem;
            }
            
            .auth-header h4 {
                font-size: 1.5rem;
            }
            
            .form-control {
                padding: 0.75rem;
            }
            
            .btn-primary,
            .btn-light {
                padding: 0.75rem 1.5rem;
                font-size: 0.9rem;
            }
        }

        /* Enhanced visual effects */
        .auth-card-body {
            position: relative;
            z-index: 2;
        }

        /* Floating label effect */
        .floating-label {
            position: relative;
        }

        .floating-label input:focus + label,
        .floating-label input:not(:placeholder-shown) + label {
            transform: translateY(-1.5rem) scale(0.85);
            color: var(--primary-color);
        }

        .floating-label label {
            position: absolute;
            left: 1rem;
            top: 0.875rem;
            transition: all 0.3s ease;
            pointer-events: none;
            background: var(--card-bg);
            padding: 0 0.5rem;
        }

        /* Improved button states */
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Better focus styles */
        .btn:focus,
        .form-control:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }
    </style>

    @stack('style')
</head>

<body>
    <div class="fade-in">
        @yield('content')
    </div>

    <!--plugins-->
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading spinner to form submissions
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const submitButton = form.querySelector('button[type="submit"]');
                    if (submitButton) {
                        const originalText = submitButton.innerHTML;
                        submitButton.innerHTML = '<span class="loading-spinner me-2"></span>Processing...';
                        submitButton.disabled = true;
                        
                        // Re-enable after 3 seconds in case of errors
                        setTimeout(() => {
                            submitButton.innerHTML = originalText;
                            submitButton.disabled = false;
                        }, 3000);
                    }
                });
            });

            // Enhanced input focus effects
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });

            // Auto-hide flash messages with fade effect
            setTimeout(function() {
                const flashMessages = document.querySelectorAll('#flash-message, #flash-error-message');
                flashMessages.forEach(message => {
                    if (message) {
                        message.style.transition = 'all 0.5s ease';
                        message.style.opacity = '0';
                        message.style.transform = 'translateY(-20px)';
                        setTimeout(() => {
                            message.style.display = 'none';
                        }, 500);
                    }
                });
            }, 4000);

            console.log('Modern SMS Expert auth layout loaded successfully!');
        });
    </script>

    @stack('js')
</body>

</html>