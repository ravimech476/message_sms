<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Site Under Maintenance</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* Animated background elements */
        .bg-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }

        .shape {
            position: absolute;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }

        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            background: #ea6118;
            border-radius: 50%;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            background: #3498db;
            border-radius: 30%;
            top: 60%;
            left: 80%;
            animation-delay: 1s;
        }

        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            background: #2ecc71;
            border-radius: 50%;
            top: 80%;
            left: 20%;
            animation-delay: 2s;
        }

        .shape:nth-child(4) {
            width: 100px;
            height: 100px;
            background: #9b59b6;
            border-radius: 20%;
            top: 20%;
            left: 70%;
            animation-delay: 3s;
        }

        .shape:nth-child(5) {
            width: 50px;
            height: 50px;
            background: #f39c12;
            border-radius: 50%;
            top: 50%;
            left: 5%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(10deg);
            }
        }

        .container {
            text-align: center;
            z-index: 10;
            padding: 20px;
            max-width: 600px;
        }

        .maintenance-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            padding: 50px 40px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .icon-wrapper {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #ea6118 0%, #d35400 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: pulse 2s ease-in-out infinite;
            box-shadow: 0 10px 40px rgba(234, 97, 24, 0.4);
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 10px 40px rgba(234, 97, 24, 0.4);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 15px 50px rgba(234, 97, 24, 0.5);
            }
        }

        .icon-wrapper .material-icons-outlined {
            font-size: 60px;
            color: white;
        }

        h1 {
            color: #1a1a2e;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .subtitle {
            color: #ea6118;
            font-size: 18px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 25px;
        }

        .message {
            color: #555;
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            border-left: 4px solid #ea6118;
        }

        .countdown-section {
            margin-bottom: 30px;
        }

        .countdown-label {
            color: #777;
            font-size: 14px;
            margin-bottom: 15px;
            font-weight: 500;
        }

        .countdown {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .countdown-item {
            background: linear-gradient(135deg, #293b50, #1f2c3d);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            min-width: 70px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .countdown-value {
            font-size: 28px;
            font-weight: 700;
            display: block;
        }

        .countdown-unit {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.8;
        }

        .progress-bar {
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .progress-bar-inner {
            height: 100%;
            background: linear-gradient(90deg, #ea6118, #f39c12, #ea6118);
            background-size: 200% 100%;
            animation: progressAnimation 2s linear infinite;
            width: 30%;
        }

        @keyframes progressAnimation {
            0% {
                background-position: 100% 0;
            }
            100% {
                background-position: -100% 0;
            }
        }

        .gear-animation {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            margin-bottom: 25px;
        }

        .gear {
            color: #ea6118;
            animation: rotate 4s linear infinite;
        }

        .gear:nth-child(2) {
            animation-direction: reverse;
            font-size: 30px;
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 28px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ea6118 0%, #d35400 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(234, 97, 24, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(234, 97, 24, 0.4);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #293b50;
            border: 2px solid #e9ecef;
        }

        .btn-secondary:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .btn .material-icons-outlined {
            font-size: 18px;
        }

        .contact-info {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #e9ecef;
            color: #777;
            font-size: 14px;
        }

        .contact-info a {
            color: #ea6118;
            text-decoration: none;
            font-weight: 500;
        }

        .contact-info a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .maintenance-card {
                padding: 35px 25px;
            }

            h1 {
                font-size: 26px;
            }

            .countdown-item {
                min-width: 55px;
                padding: 12px 15px;
            }

            .countdown-value {
                font-size: 22px;
            }

            .actions {
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
    <!-- Background Shapes -->
    <div class="bg-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="container">
        <div class="maintenance-card">
            <!-- Icon -->
            <div class="icon-wrapper">
                <span class="material-icons-outlined">build</span>
            </div>

            <!-- Subtitle -->
            <div class="subtitle">Under Maintenance</div>

            <!-- Title -->
            <h1>We'll Be Back Soon!</h1>

            <!-- Gear Animation -->
            <div class="gear-animation">
                <span class="material-icons-outlined gear" style="font-size: 24px;">settings</span>
                <span class="material-icons-outlined gear">settings</span>
                <span class="material-icons-outlined gear" style="font-size: 24px;">settings</span>
            </div>

            <!-- Progress Bar -->
            <div class="progress-bar">
                <div class="progress-bar-inner"></div>
            </div>

            <!-- Message -->
            <div class="message">
                {{ $message }}
            </div>

            @if($endTime)
            <!-- Countdown -->
            <div class="countdown-section">
                <div class="countdown-label">Estimated time remaining</div>
                <div class="countdown" id="countdown">
                    <div class="countdown-item">
                        <span class="countdown-value" id="days">00</span>
                        <span class="countdown-unit">Days</span>
                    </div>
                    <div class="countdown-item">
                        <span class="countdown-value" id="hours">00</span>
                        <span class="countdown-unit">Hours</span>
                    </div>
                    <div class="countdown-item">
                        <span class="countdown-value" id="minutes">00</span>
                        <span class="countdown-unit">Mins</span>
                    </div>
                    <div class="countdown-item">
                        <span class="countdown-value" id="seconds">00</span>
                        <span class="countdown-unit">Secs</span>
                    </div>
                </div>
            </div>
            @endif

            <!-- Actions -->
            <div class="actions">
                <button class="btn btn-primary" onclick="checkStatus()">
                    <span class="material-icons-outlined">refresh</span>
                    Check Status
                </button>
                <a href="{{ route('logout') }}" class="btn btn-secondary" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <span class="material-icons-outlined">logout</span>
                    Logout
                </a>
            </div>

            <!-- Hidden Logout Form -->
            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                @csrf
            </form>

            <!-- Contact Info -->
            <div class="contact-info">
                Need help? Contact us at <a href="mailto:{{ config('app.support_email', 'support@example.com') }}">{{ config('app.support_email', 'support@example.com') }}</a>
            </div>
        </div>
    </div>

    <script>
        @if($endTime)
        // Countdown Timer
        const endTime = new Date("{{ \Carbon\Carbon::parse($endTime)->toIso8601String() }}").getTime();

        function updateCountdown() {
            const now = new Date().getTime();
            const distance = endTime - now;

            if (distance < 0) {
                // Maintenance period ended, check status
                checkStatus();
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            document.getElementById('days').textContent = String(days).padStart(2, '0');
            document.getElementById('hours').textContent = String(hours).padStart(2, '0');
            document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
            document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
        }

        updateCountdown();
        setInterval(updateCountdown, 1000);
        @endif

        // Check maintenance status
        function checkStatus() {
            fetch('{{ route("customer.maintenance.check") }}')
                .then(response => response.json())
                .then(data => {
                    if (!data.is_maintenance) {
                        window.location.href = '{{ route("dashboard") }}';
                    } else {
                        alert('Site is still under maintenance. Please try again later.');
                    }
                })
                .catch(error => {
                    // If error, it might have redirected, reload page
                    window.location.reload();
                });
        }

        // Auto-check every 60 seconds
        setInterval(checkStatus, 60000);
    </script>
</body>
</html>
