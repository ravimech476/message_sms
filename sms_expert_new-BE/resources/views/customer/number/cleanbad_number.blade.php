@extends('layouts.app')
@section('title')
    {{ __('Clean Bad Numbers - SMS Expert') }}
@endsection

@push('style')
<style>
    .clean-numbers-container {
        background: #f8fafc;
        min-height: 100vh;
        margin: -2rem;
        padding: 2rem;
    }

     .back-btn {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

    .main-card {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        overflow: hidden;
        position: relative;
    }

    .main-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #ea6118, #293b50);
    }

    .main-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .breadcrumb-container {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        padding: 1rem 1.5rem;
        margin-bottom: 2rem;
    }

    .breadcrumb-title {
        color: #293b50;
        font-weight: 700;
        font-size: 1.2rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .breadcrumb {
        margin: 0;
        background: transparent;
    }

    .breadcrumb-item a {
        color: #ea6118;
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .breadcrumb-item a:hover {
        color: #293b50;
    }

    .breadcrumb-item.active {
        color: #64748b;
    }

    /* .back-btn {
        display: flex;
        align-items: center;
        font-size: 0.85rem;
        padding: 6px 12px;
        border-radius: 6px;
        transition: all 0.2s ease;
        background: linear-gradient(135deg, #64748b, #475569);
        border: none;
        color: white;
        font-weight: 500;
    } */
/* 
    .back-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3);
        color: white;
    } */

    .page-header {
        background: linear-gradient(135deg, #ea6118, #293b50);
        color: white;
        padding: 2rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .page-header::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: 
            radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%),
            radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
        animation: float 6s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translate(0, 0) rotate(0deg); }
        50% { transform: translate(-10px, -10px) rotate(1deg); }
    }

    .page-title {
        font-size: 2rem;
        font-weight: 700;
        margin: 0;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        position: relative;
        z-index: 2;
    }

    .page-subtitle {
        color: rgba(255, 255, 255, 0.9);
        font-size: 1.1rem;
        margin: 0.5rem 0 0 0;
        position: relative;
        z-index: 2;
    }

    .content-section {
        padding: 2rem;
    }

    .section-title {
        color: #293b50;
        font-weight: 700;
        font-size: 1.3rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .info-text {
        color: #64748b;
        line-height: 1.6;
        margin-bottom: 1.25rem;
        font-size: 1rem;
    }

    .highlight-text {
        color: #293b50;
        font-weight: 600;
        background: linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.1));
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
    }

    .icon-primary {
        color: #ea6118;
        font-size: 1.4rem;
    }

    .feature-card {
        background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .feature-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(180deg, #ea6118, #293b50);
    }

    .feature-card:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    }

    .feature-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: linear-gradient(135deg, #ea6118, #293b50);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
        margin-bottom: 1rem;
        box-shadow: 0 4px 16px rgba(234, 97, 24, 0.3);
    }

    .feature-title {
        color: #293b50;
        font-weight: 600;
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
    }

    .feature-description {
        color: #64748b;
        line-height: 1.5;
        font-size: 0.95rem;
    }

    .status-card {
        background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
        border: 2px solid #0891b2;
        border-radius: 15px;
        padding: 2rem;
        text-align: center;
        margin-top: 2rem;
    }

    .status-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #0891b2, #0e7490);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2rem;
        margin: 0 auto 1rem;
        box-shadow: 0 8px 30px rgba(8, 145, 178, 0.3);
    }

    .status-title {
        color: #0891b2;
        font-weight: 700;
        font-size: 1.4rem;
        margin-bottom: 1rem;
    }

    .status-text {
        color: #64748b;
        font-size: 1rem;
        line-height: 1.6;
    }

    .route-highlight {
        background: linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.1));
        border: 1px solid rgba(234, 97, 24, 0.3);
        border-radius: 6px;
        padding: 0.25rem 0.5rem;
        font-weight: 600;
        color: #ea6118;
        display: inline-block;
        margin: 0 0.2rem;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
    }

    /* Responsive design */
    @media (max-width: 768px) {
        .clean-numbers-container {
            padding: 1rem;
        }
        
        .page-title {
            font-size: 1.5rem;
        }
        
        .content-section {
            padding: 1.5rem;
        }
        
        .breadcrumb-container {
            flex-direction: column;
            text-align: center;
            gap: 1rem;
        }
        
        /* .back-btn {
            width: 100%;
            justify-content: center;
        } */
        
        .info-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
    }

    /* Animation classes */
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

    .slide-in-left {
        animation: slideInLeft 0.8s ease-out;
    }

    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
</style>
@endpush

@section('content')
<div class="clean-numbers-container">
    <!-- Breadcrumb -->
    <div class="breadcrumb-container d-flex align-items-center justify-content-between fade-in">
        <div class="d-flex align-items-center">
            <div class="breadcrumb-title pe-3">
                <i class="material-icons-outlined icon-primary">cleaning_services</i>
                Clean Bad Numbers
            </div>&nbsp;
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route('dashboard') }}">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active">Clean Bad Numbers</li>
                </ol>
            </nav>
        </div>
         <button id="backButton" class="btn btn-outline-secondary back-btn">
                <i class="material-icons-outlined me-1">arrow_back</i> Back
            </button>
    </div>

    <!-- Main Content Card -->
    <div class="main-card slide-in-left">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <i class="material-icons-outlined me-2">cleaning_services</i>
                Remove Bad Numbers From Your Groups
            </div>
            <div class="page-subtitle">
                Maintain a clean and effective contact database for better SMS delivery rates
            </div>
        </div>

        <!-- Content Section -->
        <div class="content-section">
            <div class="section-title">
                <i class="material-icons-outlined icon-primary">info</i>
                How the Clean-up Process Works
            </div>
            
            <p class="info-text">
                If you have a large number of mobile numbers in your SMS Expert groups or address book, you might
                have some that are no longer valid or were not even valid in the first place.
                This page allows you to look back over recently sent messages to identify any phones that have
                failed to receive every message you have sent on routes
                <span class="route-highlight">7 (UK National)</span> or <span class="route-highlight">8 (Global)</span> 
                and then completely remove them from your groups.
            </p>

            <div class="info-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="material-icons-outlined">search</i>
                    </div>
                    <div class="feature-title">Smart Detection</div>
                    <div class="feature-description">
                        Our system analyzes your message history to identify numbers that consistently fail to receive messages on premium routes.
                    </div>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="material-icons-outlined">security</i>
                    </div>
                    <div class="feature-title">Safe Cleaning</div>
                    <div class="feature-description">
                        Numbers that have both successful and failed deliveries are preserved, as failures may be due to temporary network issues.
                    </div>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="material-icons-outlined">trending_up</i>
                    </div>
                    <div class="feature-title">Improved Delivery</div>
                    <div class="feature-description">
                        Removing bad numbers improves your overall delivery rates and reduces costs on premium messaging routes.
                    </div>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="material-icons-outlined">history</i>
                    </div>
                    <div class="feature-title">Historical Analysis</div>
                    <div class="feature-description">
                        The system analyzes your recent message history to ensure accurate identification of problematic numbers.
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <h6 class="highlight-text mb-3">Important Safety Notice:</h6>
                <p class="info-text">
                    Phones that have both successfully received a message and also failed for some reason to receive a
                    message will <strong>not</strong> be deleted, as the successful receipt indicates that the number is valid, and the
                    failure is assumed to be due to some network problem rather than an invalid number.
                </p>
            </div>

            <!-- Status Card -->
            <div class="status-card">
                <div class="status-icon">
                    <i class="material-icons-outlined">check_circle</i>
                </div>
                <div class="status-title">All Clear!</div>
                <div class="status-text">
                    You currently have no numbers in your database that completely failed to receive messages 
                    sent on routes <strong>7 (UK National)</strong> or <strong>8 (Global)</strong> in your recent message history.
                    <br><br>
                    Your contact database is clean and optimized for effective SMS delivery.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
@include('layouts.footer')
<!-- End Footer -->
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Smooth reveal animations
    const cards = document.querySelectorAll('.main-card, .breadcrumb-container');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 200);
    });

    // Feature cards hover effects
    const featureCards = document.querySelectorAll('.feature-card');
    featureCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px) translateY(-2px)';
            this.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.15)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0) translateY(0)';
            this.style.boxShadow = '0 4px 16px rgba(0, 0, 0, 0.1)';
        });
    });

    // Parallax effect for page header
    const pageHeader = document.querySelector('.page-header');
    if (pageHeader) {
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const rate = scrolled * -0.5;
            
            if (pageHeader.querySelector('::before')) {
                pageHeader.style.transform = `translateY(${rate}px)`;
            }
        });
    }

    // Intersection Observer for animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animation = 'fadeIn 0.8s ease-out forwards';
            }
        });
    }, observerOptions);

    // Observe all feature cards
    featureCards.forEach(card => {
        card.style.opacity = '0';
        observer.observe(card);
    });

    // Add floating animation to icons
    const icons = document.querySelectorAll('.feature-icon, .status-icon');
    icons.forEach(icon => {
        icon.addEventListener('mouseenter', function() {
            this.style.animation = 'float 2s ease-in-out infinite';
        });
        
        icon.addEventListener('mouseleave', function() {
            this.style.animation = '';
        });
    });

    console.log('Modern Clean Bad Numbers page loaded successfully!');
});
</script>
@endpush