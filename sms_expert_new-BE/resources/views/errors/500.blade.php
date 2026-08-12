<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Server Error | SMS Expert</title>
    <link rel="icon" href="{{ asset('assets/images/auth/smsexpert_favion.png') }}" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <style>
        :root {
            --primary-color: #ea6118;
            --secondary-color: #293b50;
            --error-color: #ef4444;
            --background-color: #f8fafc;
            --text-primary: #293b50;
            --text-secondary: #64748b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .error-container {
            text-align: center;
            max-width: 500px;
            padding: 60px 40px;
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(239, 68, 68, 0.15);
            position: relative;
            overflow: hidden;
        }

        .error-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--error-color), #dc2626);
        }

        .error-icon {
            font-size: 80px;
            color: var(--error-color);
            margin-bottom: 20px;
            animation: shake 0.5s ease-in-out infinite;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .error-code {
            font-size: 120px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--error-color), #dc2626);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 10px;
        }

        .error-title {
            font-size: 28px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 15px;
        }

        .error-message {
            font-size: 16px;
            color: var(--text-secondary);
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: 0 4px 16px rgba(234, 97, 24, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(234, 97, 24, 0.4);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: var(--text-primary);
            border: 2px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .decoration {
            position: absolute;
            opacity: 0.03;
            font-size: 300px;
            font-weight: 700;
            color: var(--error-color);
            z-index: 0;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .content {
            position: relative;
            z-index: 1;
        }

        .support-text {
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e2e8f0;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .support-text a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }

        .support-text a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .error-container {
                padding: 40px 25px;
            }
            .error-code {
                font-size: 80px;
            }
            .error-title {
                font-size: 22px;
            }
            .btn-group {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <span class="decoration">500</span>
        <div class="content">
            <span class="material-icons-outlined error-icon">error_outline</span>
            <div class="error-code">500</div>
            <h1 class="error-title">Server Error</h1>
            <p class="error-message">
                Something went wrong on our end. Our team has been notified and we're working to fix it.
                Please try again in a few moments.
            </p>
            <div class="btn-group">
                <a href="{{ url('/') }}" class="btn btn-primary">
                    <span class="material-icons-outlined">home</span>
                    Go Home
                </a>
                <a href="javascript:location.reload()" class="btn btn-secondary">
                    <span class="material-icons-outlined">refresh</span>
                    Try Again
                </a>
            </div>
            <div class="support-text">
                If the problem persists, please <a href="mailto:support@smsexpert.com">contact support</a>
            </div>
        </div>
    </div>
</body>
</html>
