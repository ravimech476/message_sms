<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>429 - Too Many Requests | SMS Expert</title>
    <link rel="icon" href="{{ asset('assets/images/auth/smsexpert_favion.png') }}" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <style>
        :root {
            --primary-color: #ea6118;
            --secondary-color: #293b50;
            --purple-color: #8b5cf6;
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
            background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
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
            box-shadow: 0 20px 60px rgba(139, 92, 246, 0.15);
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
            background: linear-gradient(90deg, var(--purple-color), #7c3aed);
        }

        .error-icon {
            font-size: 80px;
            color: var(--purple-color);
            margin-bottom: 20px;
            animation: swing 1s ease-in-out infinite;
        }

        @keyframes swing {
            0%, 100% { transform: rotate(-10deg); }
            50% { transform: rotate(10deg); }
        }

        .error-code {
            font-size: 120px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--purple-color), #7c3aed);
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
            color: var(--purple-color);
            z-index: 0;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .content {
            position: relative;
            z-index: 1;
        }

        .wait-timer {
            margin-top: 20px;
            padding: 15px 25px;
            background: #f5f3ff;
            border-radius: 12px;
            display: inline-block;
        }

        .wait-timer span {
            color: var(--purple-color);
            font-weight: 700;
            font-size: 24px;
        }

        .wait-timer p {
            margin: 0;
            font-size: 13px;
            color: var(--text-secondary);
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
        <span class="decoration">429</span>
        <div class="content">
            <span class="material-icons-outlined error-icon">speed</span>
            <div class="error-code">429</div>
            <h1 class="error-title">Too Many Requests</h1>
            <p class="error-message">
                You've made too many requests in a short period.
                Please wait a moment before trying again.
            </p>
            <div class="wait-timer">
                <span id="timer">60</span>
                <p>seconds remaining</p>
            </div>
            <div class="btn-group" style="margin-top: 25px;">
                <a href="{{ url('/') }}" class="btn btn-primary">
                    <span class="material-icons-outlined">home</span>
                    Go Home
                </a>
                <button id="retryBtn" class="btn btn-secondary" disabled>
                    <span class="material-icons-outlined">refresh</span>
                    Retry
                </button>
            </div>
        </div>
    </div>

    <script>
        let seconds = 60;
        const timerEl = document.getElementById('timer');
        const retryBtn = document.getElementById('retryBtn');

        const interval = setInterval(() => {
            seconds--;
            timerEl.textContent = seconds;

            if (seconds <= 0) {
                clearInterval(interval);
                retryBtn.disabled = false;
                retryBtn.onclick = () => location.reload();
                timerEl.parentElement.innerHTML = '<span style="color: #10b981;">Ready!</span><p>You can retry now</p>';
            }
        }, 1000);
    </script>
</body>
</html>
