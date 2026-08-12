<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 - Service Unavailable | SMS Expert</title>
    <link rel="icon" href="{{ asset('assets/images/auth/smsexpert_favion.png') }}" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <style>
        :root {
            --primary-color: #ea6118;
            --secondary-color: #293b50;
            --teal-color: #14b8a6;
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
            background: linear-gradient(135deg, #f0fdfa 0%, #ccfbf1 100%);
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
            box-shadow: 0 20px 60px rgba(20, 184, 166, 0.15);
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
            background: linear-gradient(90deg, var(--teal-color), #0d9488);
        }

        .error-icon {
            font-size: 80px;
            color: var(--teal-color);
            margin-bottom: 20px;
            animation: wrench 2.5s ease infinite;
        }

        @keyframes wrench {
            0% { transform: rotate(-12deg); }
            8% { transform: rotate(12deg); }
            10% { transform: rotate(24deg); }
            18% { transform: rotate(-24deg); }
            20% { transform: rotate(0deg); }
            100% { transform: rotate(0deg); }
        }

        .error-code {
            font-size: 120px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--teal-color), #0d9488);
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

        .maintenance-info {
            background: linear-gradient(135deg, #f0fdfa, #ccfbf1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .maintenance-info h3 {
            color: var(--teal-color);
            font-size: 16px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .maintenance-info p {
            color: var(--text-secondary);
            font-size: 14px;
            margin: 0;
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
            background: var(--teal-color);
            color: white;
            border-color: var(--teal-color);
        }

        .decoration {
            position: absolute;
            opacity: 0.03;
            font-size: 300px;
            font-weight: 700;
            color: var(--teal-color);
            z-index: 0;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .content {
            position: relative;
            z-index: 1;
        }

        .status-check {
            margin-top: 20px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .status-check a {
            color: var(--teal-color);
            text-decoration: none;
            font-weight: 600;
        }

        .status-check a:hover {
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
        <span class="decoration">503</span>
        <div class="content">
            <span class="material-icons-outlined error-icon">build</span>
            <div class="error-code">503</div>
            <h1 class="error-title">Under Maintenance</h1>
            <p class="error-message">
                We're currently performing scheduled maintenance to improve your experience.
                We'll be back online shortly.
            </p>
            <div class="maintenance-info">
                <h3>
                    <span class="material-icons-outlined">schedule</span>
                    Expected Duration
                </h3>
                <p>Usually just a few minutes. Thank you for your patience!</p>
            </div>
            <div class="btn-group">
                <a href="javascript:location.reload()" class="btn btn-primary">
                    <span class="material-icons-outlined">refresh</span>
                    Try Again
                </a>
                <a href="mailto:support@smsexpert.com" class="btn btn-secondary">
                    <span class="material-icons-outlined">email</span>
                    Contact Support
                </a>
            </div>
            <div class="status-check">
                Check our <a href="#">status page</a> for updates
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
