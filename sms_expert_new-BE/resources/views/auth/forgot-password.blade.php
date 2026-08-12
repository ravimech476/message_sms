@extends('layouts.auth')
@section('title', 'Forgot Password - SMS Expert')

@push('style')
<style>
.back-btn {
    display: flex;
    align-items: center;
    font-size: 0.85rem;
    padding: 6px 12px;
    border-radius: 6px;
    transition: all 0.2s ease;
}

/* Reset and base styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
    background: linear-gradient(135deg, #293B50 0%, #1e2a3a 100%) !important;
    min-height: 100vh;
    overflow-x: hidden;
}

/* Main container */
.auth-wrapper {
    min-height: 100vh;
    max-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    position: relative;
    overflow: hidden;
}

/* Background pattern */
.auth-wrapper::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="0.5"/></pattern></defs><rect width="100%" height="100%" fill="url(%23grid)"/></svg>');
    pointer-events: none;
}

/* Auth card */
.auth-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.2);
    width: 100%;
    max-width: 900px;
    height: calc(100vh - 2rem);
    max-height: 580px;
    overflow: hidden;
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    position: relative;
    z-index: 10;
}

/* Left side branding */
.auth-brand {
    background: linear-gradient(135deg, #293B50 0%, #1e2a3a 100%);
    padding: 2rem 1.5rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    color: white;
    position: relative;
    overflow: hidden;
}

.auth-brand::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="25" cy="25" r="20" fill="none" stroke="rgba(234,97,24,0.1)" stroke-width="0.5"/><circle cx="75" cy="75" r="15" fill="none" stroke="rgba(234,97,24,0.1)" stroke-width="0.3"/><circle cx="50" cy="10" r="5" fill="rgba(234,97,24,0.1)"/><circle cx="10" cy="80" r="3" fill="rgba(234,97,24,0.1)"/></svg>') repeat;
    animation: float 30s linear infinite;
    pointer-events: none;
}

@keyframes float {
    0% { transform: translateY(0px) rotate(0deg); }
    100% { transform: translateY(-20px) rotate(360deg); }
}

/* SMS Expert Logo Styling */
.brand-logo-container {
    margin-bottom: 1.5rem;
    position: relative;
    z-index: 2;
}

.brand-logo-text {
    font-size: 2.8rem;
    font-weight: 800;
    letter-spacing: -2px;
    line-height: 1;
    margin-bottom: 0.5rem;
}

.brand-logo-text .sms-text {
    color: #ea6118; /* Orange color for SMS */
}

.brand-logo-text .expert-text {
    color: white; /* White color for Expert */
}

.brand-underline {
    width: 80px;
    height: 3px;
    background: linear-gradient(90deg, #ea6118, #ff8a50);
    border-radius: 2px;
    margin: 0 auto 1rem;
}

.brand-subtitle {
    font-size: 1rem;
    opacity: 0.9;
    line-height: 1.4;
    margin-bottom: 1.5rem;
    color: rgba(255, 255, 255, 0.9);
    font-weight: 400;
    position: relative;
    z-index: 2;
}

.feature-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    width: 100%;
    max-width: 300px;
    position: relative;
    z-index: 2;
}

.feature-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
}

.feature-item:hover {
    transform: translateY(-3px);
    background: rgba(255, 255, 255, 0.15);
}

.feature-icon {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #ea6118, #ff8a50);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.5rem;
}

.feature-icon i {
    font-size: 1.2rem;
    color: white;
}

.feature-text {
    font-size: 0.8rem;
    font-weight: 500;
    color: white;
    opacity: 0.9;
}

/* Right side form */
.auth-form {
    background: white;
    padding: 2rem 1.5rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.form-header {
    text-align: center;
    margin-bottom: 1.5rem;
}

.form-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: #293B50;
    margin-bottom: 0.3rem;
}

.form-subtitle {
    color: #6c757d;
    font-size: 0.95rem;
    font-weight: 400;
}

/* Form styling */
.form-group {
    margin-bottom: 1.2rem;
}

.form-label {
    font-weight: 600;
    color: #293B50;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-control {
    border: 2px solid #e9ecef !important;
    border-radius: 12px !important;
    padding: 0.8rem 1rem !important;
    font-size: 0.95rem !important;
    transition: all 0.3s ease !important;
    background: #f8f9fa !important;
    color: #293B50 !important;
    height: auto !important;
}

.form-control::placeholder {
    color: #adb5bd !important;
}

.form-control:focus {
    border-color: #ea6118 !important;
    box-shadow: 0 0 0 0.25rem rgba(234, 97, 24, 0.15) !important;
    transform: translateY(-2px) !important;
    background: white !important;
}

.btn-submit {
    background: linear-gradient(135deg, #ea6118, #ff8a50) !important;
    border: none !important;
    border-radius: 12px !important;
    padding: 0.8rem 1.5rem !important;
    font-weight: 600 !important;
    color: white !important;
    font-size: 1rem !important;
    width: 100% !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 6px 20px rgba(234, 97, 24, 0.3) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.5rem !important;
    height: 48px !important;
}

.btn-submit:hover {
    transform: translateY(-3px) !important;
    box-shadow: 0 12px 35px rgba(234, 97, 24, 0.4) !important;
    color: white !important;
}

.btn-submit:active {
    transform: translateY(-1px) !important;
}

.btn-back {
    background: linear-gradient(135deg, #6c757d, #5a6268) !important;
    border: none !important;
    border-radius: 12px !important;
    padding: 0.8rem 1.5rem !important;
    font-weight: 600 !important;
    color: white !important;
    font-size: 1rem !important;
    width: 100% !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 6px 20px rgba(108, 117, 125, 0.3) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.5rem !important;
    height: 48px !important;
    margin-top: 0.75rem !important;
}

.btn-back:hover {
    transform: translateY(-3px) !important;
    box-shadow: 0 12px 35px rgba(108, 117, 125, 0.4) !important;
    color: white !important;
}

.btn-back:active {
    transform: translateY(-1px) !important;
}

/* Alerts */
.alert-modern {
    border: none !important;
    border-radius: 12px !important;
    padding: 0.8rem 1rem !important;
    margin-bottom: 1rem !important;
    font-size: 0.9rem !important;
    display: flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
}

.alert-success.alert-modern {
    background: linear-gradient(135deg, #d4edda, #c3e6cb) !important;
    color: #155724 !important;
    border-left: 4px solid #28a745 !important;
}

.alert-danger.alert-modern {
    background: linear-gradient(135deg, #f8d7da, #f5c6cb) !important;
    color: #721c24 !important;
    border-left: 4px solid #dc3545 !important;
}

.form-help-text {
    color: #6c757d;
    font-size: 0.85rem;
    margin-top: 0.5rem;
    line-height: 1.4;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Loading animation */
.loading-spinner {
    display: inline-block;
    width: 18px;
    height: 18px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Help section */
.help-section {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e9ecef;
    text-align: center;
}

.help-title {
    color: #293B50;
    font-weight: 600;
    font-size: 0.95rem;
    margin-bottom: 0.75rem;
}

.help-text {
    color: #6c757d;
    font-size: 0.85rem;
    margin-bottom: 1rem;
    line-height: 1.4;
}

.help-links {
    display: flex;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.help-link {
    color: #ea6118;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    transition: color 0.3s ease;
}

.help-link:hover {
    color: #d1520e;
    text-decoration: underline;
}

/* Responsive design */
@media (max-width: 1024px) {
    .auth-card {
        max-width: 800px;
    }
    
    .brand-logo-text {
        font-size: 3.5rem;
    }
}

@media (max-width: 768px) {
    .auth-card {
        grid-template-columns: 1fr;
        max-width: 450px;
    }
    
    .auth-brand {
        order: 1;
        padding: 3rem 2rem;
        min-height: 400px;
    }
    
    .auth-form {
        order: 2;
        padding: 3rem 2rem;
    }
    
    .brand-logo-text {
        font-size: 3rem;
    }
    
    .form-title {
        font-size: 2rem;
    }
    
    .feature-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .help-links {
        flex-direction: column;
        gap: 0.5rem;
    }
}

@media (max-width: 480px) {
    .auth-wrapper {
        padding: 1rem;
    }
    
    .auth-form {
        padding: 2rem 1.5rem;
    }
    
    .auth-brand {
        padding: 2rem 1.5rem;
    }
    
    .auth-card {
        border-radius: 16px;
    }
    
    .brand-logo-text {
        font-size: 2.5rem;
    }
}
</style>
@endpush

@section('content')
<div class="auth-wrapper">
    <div class="auth-card">
        <!-- Left Side - Branding -->
        <div class="auth-brand">
            <div class="brand-logo-container">
                <div class="brand-logo-text">
                    <span class="sms-text">SMS</span><span class="expert-text">Expert</span>
                </div>
                <div class="brand-underline"></div>
            </div>
            
            <p class="brand-subtitle">Secure Password Recovery for Your SMS Expert Account</p>
            
            <div class="feature-grid">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="material-icons-outlined">security</i>
                    </div>
                    <div class="feature-text">Secure Recovery</div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="material-icons-outlined">email</i>
                    </div>
                    <div class="feature-text">Email Verification</div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="material-icons-outlined">speed</i>
                    </div>
                    <div class="feature-text">Quick Process</div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="material-icons-outlined">support_agent</i>
                    </div>
                    <div class="feature-text">Expert Support</div>
                </div>
            </div>
        </div>

        <!-- Right Side - Forgot Password Form -->
        <div class="auth-form">
            <div class="form-header">
                <h2 class="form-title">Forgot Password?</h2>
                <p class="form-subtitle">Enter your username to reset your password</p>
            </div>

            <!-- Flash Messages -->
            @if (session('success'))
                <div id="flash-message" class="alert alert-success alert-modern">
                    <i class="material-icons-outlined" style="font-size: 1.1rem;">check_circle</i>
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div id="flash-error-message" class="alert alert-danger alert-modern">
                    {{-- <i class="material-icons-outlined" style="font-size: 1.1rem;">error</i> --}}
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('forgot.password') }}" id="forgotPasswordForm">
                @csrf
                
                <!-- Username Field -->
                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="material-icons-outlined" style="font-size: 1.1rem; color: #ea6118;">person</i>
                        Username
                    </label>
                    <input type="text" class="form-control" id="username" name="username" 
                           placeholder="Enter your username" required autocomplete="username" autofocus>
                    <div class="form-help-text">
                        <i class="material-icons-outlined" style="font-size: 14px;">info</i>
                        Enter the username you use to log into SMS Expert
                    </div>
                    @error('username')
                        <div class="error-text">
                            <i class="material-icons-outlined" style="font-size: 0.9rem;">error</i>
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary btn-submit" id="submitBtn">
                    <i class="material-icons-outlined" style="font-size: 1.1rem;">send</i>
                    Send Reset Link
                </button>

                <!-- Back to Login Button -->
                <a href="/" class="btn btn-secondary btn-back">
                    <i class="material-icons-outlined" style="font-size: 1.1rem;">arrow_back</i>
                    Back to Login
                </a>
            </form>

            <!-- Help Section -->
            <div class="help-section">
                <div class="help-title">Need Additional Help?</div>
                <div class="help-text">
                    If you're having trouble accessing your account, our support team is here to help.
                </div>
                <div class="help-links">
                    <a href="mailto:care@smsexpert.co.uk" class="help-link">
                        <i class="material-icons-outlined" style="font-size: 14px;">email</i>
                        care@smsexpert.co.uk
                    </a>
                    <a href="tel:01509606305" class="help-link">
                        <i class="material-icons-outlined" style="font-size: 14px;">phone</i>
                        01509 606305
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide flash messages
    setTimeout(function() {
        const flashMessages = document.querySelectorAll('#flash-message, #flash-error-message');
        flashMessages.forEach(msg => {
            if (msg) {
                msg.style.opacity = '0';
                msg.style.transition = 'opacity 0.5s ease';
                setTimeout(() => msg.style.display = 'none', 500);
            }
        });
    }, 5000);

    // Enhanced form validation
    const usernameInput = document.getElementById('username');
    const form = document.getElementById('forgotPasswordForm');
    const submitButton = document.getElementById('submitBtn');
    
    if (usernameInput) {
        // Real-time validation
        usernameInput.addEventListener('input', function() {
            const value = this.value.trim();
            const isValid = value.length >= 3;
            
            if (isValid) {
                this.style.borderColor = '#28a745';
                this.style.boxShadow = '0 0 0 0.25rem rgba(40, 167, 69, 0.15)';
            } else if (value.length > 0) {
                this.style.borderColor = '#dc3545';
                this.style.boxShadow = '0 0 0 0.25rem rgba(220, 53, 69, 0.15)';
            } else {
                this.style.borderColor = '#e9ecef';
                this.style.boxShadow = 'none';
            }
        });

        // Enhanced focus effect
        usernameInput.addEventListener('focus', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.transition = 'all 0.3s ease';
        });

        usernameInput.addEventListener('blur', function() {
            this.style.transform = 'translateY(0)';
        });
    }

    // Form submission handling
    if (form && submitButton) {
        form.addEventListener('submit', function(e) {
            const username = usernameInput.value.trim();
            
            if (username.length < 3) {
                e.preventDefault();
                
                // Show inline error
                let errorDiv = usernameInput.parentElement.querySelector('.validation-error');
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'validation-error alert alert-danger alert-modern mt-2 py-2';
                    errorDiv.innerHTML = '<i class="material-icons-outlined me-1">error</i>Username must be at least 3 characters long';
                    usernameInput.parentElement.appendChild(errorDiv);
                }
                
                // Focus and shake effect
                usernameInput.focus();
                usernameInput.parentElement.style.animation = 'shake 0.5s ease-in-out';
                
                setTimeout(() => {
                    usernameInput.parentElement.style.animation = '';
                }, 500);
                
                return false;
            }

            // Show loading state
            submitButton.innerHTML = '<div class="loading-spinner"></div>Sending Reset Link...';
            submitButton.disabled = true;
            
            // Re-enable after timeout in case of errors
            setTimeout(() => {
                submitButton.innerHTML = '<i class="material-icons-outlined" style="font-size: 1.1rem;">send</i>Send Reset Link';
                submitButton.disabled = false;
            }, 5000);
        });
    }

    // Add subtle animations on load
    const card = document.querySelector('.auth-card');
    if (card) {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100);
    }

    // Add shake animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    `;
    document.head.appendChild(style);

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.location.href = '/';
        }
    });

    // Clear navigation storage
    sessionStorage.removeItem("navStack");
    sessionStorage.removeItem("navStackIndex");

    console.log('SMS Expert branded forgot password page loaded successfully!');
});
</script>
@endpush