@extends('admin.layouts.app')
@section('title')
    {{ __('CRM') }}
@endsection

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
    /* Modern Admin Dashboard Styles */
    :root {
        --primary-color: #ea6118;
        --primary-light: #ff8a50;
        --primary-dark: #d1520e;
        --secondary-color: #293B50;
        --secondary-light: #3a4d66;
        --accent-color: #1e2a3a;
        --success-color: #10b981;
        --warning-color: #f59e0b;
        --danger-color: #ef4444;
        --info-color: #3b82f6;
        --light-bg: #f8fafc;
        --card-bg: #ffffff;
        --text-primary: #1f2937;
        --text-secondary: #6b7280;
        --border-color: #e5e7eb;
        --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
        --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
    }

    body {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }

    .main-content {
        padding: 1.5rem;
    }

    /* Modern Breadcrumb */
    .modern-breadcrumb {
        background: linear-gradient(135deg, var(--card-bg) 0%, #ffffff 100%);
        border-radius: 16px;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        position: relative;
        overflow: hidden;
    }

    .modern-breadcrumb::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
    }

    .breadcrumb-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .breadcrumb-subtitle {
        color: var(--text-secondary);
        font-size: 0.95rem;
        margin-top: 0.5rem;
    }

    /* Modern Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .modern-stat-card {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border-color);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .modern-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-xl);
    }

    .modern-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient);
    }

    .modern-stat-card.primary { --gradient: linear-gradient(90deg, var(--primary-color), var(--primary-light)); }
    .modern-stat-card.success { --gradient: linear-gradient(90deg, var(--success-color), #34d399); }
    .modern-stat-card.warning { --gradient: linear-gradient(90deg, var(--warning-color), #fbbf24); }
    .modern-stat-card.info { --gradient: linear-gradient(90deg, var(--info-color), #60a5fa); }

    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1.5rem;
    }

    .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--icon-bg);
        color: var(--icon-color);
        font-size: 1.5rem;
    }

    .modern-stat-card.primary .stat-icon { 
        --icon-bg: rgba(234, 97, 24, 0.1); 
        --icon-color: var(--primary-color); 
    }
    .modern-stat-card.success .stat-icon { 
        --icon-bg: rgba(16, 185, 129, 0.1); 
        --icon-color: var(--success-color); 
    }
    .modern-stat-card.warning .stat-icon { 
        --icon-bg: rgba(245, 158, 11, 0.1); 
        --icon-color: var(--warning-color); 
    }
    .modern-stat-card.info .stat-icon { 
        --icon-bg: rgba(59, 130, 246, 0.1); 
        --icon-color: var(--info-color); 
    }

    .stat-trend {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 0.75rem;
        background: var(--trend-bg);
        color: var(--trend-color);
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .trend-up { 
        --trend-bg: rgba(16, 185, 129, 0.1); 
        --trend-color: var(--success-color); 
    }
    .trend-down { 
        --trend-bg: rgba(239, 68, 68, 0.1); 
        --trend-color: var(--danger-color); 
    }

    .stat-value {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--text-primary);
        line-height: 1;
        margin-bottom: 0.5rem;
    }

    .stat-label {
        color: var(--text-secondary);
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 1rem;
    }

    .stat-description {
        color: var(--text-secondary);
        font-size: 0.85rem;
        line-height: 1.4;
    }

    /* Charts and Analytics Section */
    .analytics-section {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        margin-top: 2rem;
    }

    .chart-card {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border-color);
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .chart-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .chart-subtitle {
        color: var(--text-secondary);
        font-size: 0.9rem;
        margin-top: 0.25rem;
    }

    /* Quick Actions */
    .quick-actions {
        display: grid;
        gap: 1rem;
    }

    .action-card {
        background: var(--card-bg);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
    }

    .action-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        text-decoration: none;
        color: inherit;
    }

    .action-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .action-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--primary-color);
        color: white;
        font-size: 1.1rem;
    }

    .action-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .action-description {
        color: var(--text-secondary);
        font-size: 0.85rem;
        line-height: 1.4;
    }

    /* Welcome Banner */
    .welcome-banner {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
        border-radius: 20px;
        padding: 2rem;
        color: white;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }

    .welcome-banner::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 200px;
        height: 200px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }

    .welcome-banner::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -10%;
        width: 150px;
        height: 150px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
    }

    .welcome-content {
        position: relative;
        z-index: 2;
    }

    .welcome-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .welcome-subtitle {
        opacity: 0.9;
        font-size: 1rem;
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .analytics-section {
            grid-template-columns: 1fr;
        }
        
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 1rem;
        }
        
        .modern-breadcrumb {
            padding: 1rem 1.5rem;
        }
        
        .breadcrumb-title {
            font-size: 1.5rem;
        }
        
        .modern-stat-card {
            padding: 1.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Loading Animation */
    .stat-value.loading {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: loading 1.5s infinite;
        border-radius: 4px;
        height: 3rem;
        width: 8rem;
    }

    @keyframes loading {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    /* Hover Effects */
    .modern-stat-card:hover .stat-icon {
        transform: scale(1.1);
        transition: transform 0.3s ease;
    }

    .modern-stat-card:hover .stat-value {
        color: var(--primary-color);
        transition: color 0.3s ease;
    }
</style>
@endpush

@section('content')
<main class="main-wrapper">
    <div class="main-content">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="welcome-content">
                <h1 class="welcome-title">Welcome back, {{ urldecode($user_contactname ?? 'Admin') }}! 👋</h1>
                <p class="welcome-subtitle">Here's what's happening with your SMS Expert platform today.</p>
            </div>
            <!--end breadcrumb-->

            <div class="row">
                {{-- <div class="col-12 col-xl-4">
                    <div class="card rounded-4">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="">
                                    <h2 class="mb-0">$120</h2>
                                </div>
                                <div class="">
                                    <p
                                        class="dash-lable d-flex align-items-center gap-1 rounded mb-0 bg-danger text-danger bg-opacity-10">
                                        <span class="material-icons-outlined fs-6">arrow_downward</span>8.6%
                                    </p>
                                </div>
                            </div>
                            <p class="mb-0">Send SMS</p>
                            <div id="chart1"></div>
                        </div>
                    </div>
                    <div class="stat-trend trend-down">
                        <i class="material-icons-outlined" style="font-size: 0.9rem;">trending_down</i>
                        -3.1%
                    </div>
                </div>
                <div class="stat-value">£{{ number_format($allQuery->total_costprice ?? 0, 2) }}</div>
                <div class="stat-label">Operating Costs</div>
                <div class="stat-description">Total operational expenses for SMS delivery</div>
            </div>

            <!-- User Revenue Card -->
            <div class="modern-stat-card info">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="material-icons-outlined">payments</i>
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="material-icons-outlined" style="font-size: 0.9rem;">trending_up</i>
                        +15.7%
                    </div>
                </div>
                <div class="stat-value">£{{ number_format($allQuery->total_userprice ?? 0, 2) }}</div>
                <div class="stat-label">Customer Revenue</div>
                <div class="stat-description">Total amount charged to customers this month</div>
            </div>
        </div>

        <!-- Analytics and Quick Actions -->
        <div class="analytics-section">
            <!-- Charts Section -->
            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <h3 class="chart-title">Performance Analytics</h3>
                        <p class="chart-subtitle">SMS delivery trends and performance metrics</p>
                    </div>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-outline-primary active">7D</button>
                        <button type="button" class="btn btn-sm btn-outline-primary">30D</button>
                        <button type="button" class="btn btn-sm btn-outline-primary">90D</button>
                    </div>
                </div>
                <div id="performanceChart" style="height: 300px; background: #f8fafc; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #6b7280;">
                    <div class="text-center">
                        <i class="material-icons-outlined" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;">insert_chart</i>
                        <p>Performance chart will be displayed here</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3 style="color: var(--text-primary); font-weight: 700; margin-bottom: 1rem;">Quick Actions</h3>
                
                <a href="{{ route('admin.users') }}" class="action-card">
                    <div class="action-header">
                        <div class="action-icon">
                            <i class="material-icons-outlined">people</i>
                        </div>
                        <h4 class="action-title">Manage Users</h4>
                    </div>
                    <p class="action-description">View and manage all customer accounts, permissions, and settings</p>
                </a>

                <a href="{{ route('admin.client.emails') }}" class="action-card">
                    <div class="action-header">
                        <div class="action-icon" style="background: var(--success-color);">
                            <i class="material-icons-outlined">email</i>
                        </div>
                        <h4 class="action-title">Send Notifications</h4>
                    </div>
                    <p class="action-description">Send bulk emails and notifications to customers</p>
                </a>

                <a href="{{ route('admin.monthly-report') }}" class="action-card">
                    <div class="action-header">
                        <div class="action-icon" style="background: var(--info-color);">
                            <i class="material-icons-outlined">assessment</i>
                        </div>
                        <h4 class="action-title">View Reports</h4>
                    </div>
                    <p class="action-description">Access detailed analytics and performance reports</p>
                </a>

                <a href="{{ route('admin.customer.create') }}" class="action-card">
                    <div class="action-header">
                        <div class="action-icon" style="background: var(--warning-color);">
                            <i class="material-icons-outlined">person_add</i>
                        </div>
                        <h4 class="action-title">Add Customer</h4>
                    </div>
                    <p class="action-description">Create new customer accounts and configure settings</p>
                </a>

                <div class="action-card" style="background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); border: 2px dashed var(--border-color);">
                    <div class="action-header">
                        <div class="action-icon" style="background: var(--text-secondary);">
                            <i class="material-icons-outlined">settings</i>
                        </div>
                        <h4 class="action-title">System Settings</h4>
                    </div>
                    <p class="action-description">Configure platform settings, integrations, and preferences</p>
                </div>



                {{-- Month Wise Daily Count Chart 2 --}}
                <div class="col-12 col-xl-6">
                    <div class="card">
                        <div class="card-header py-3">
                            <div class="d-flex align-items-center justify-content-between flex-wrap">
                                <h5 class="mb-0 fw-bold theme-dependent">SMS Send Counts</h5>
                                <div class="d-flex">
                                    <div class="me-3">
                                        <label for="month-select-year"
                                            class="form-label mb-0 fw-semibold theme-label-color">Select Year</label>
                                        <select id="month-select-year" class="form-select form-select-sm">
                                            {{-- <option value="2020">2020</option>
                                            <option value="2021">2021</option>
                                            <option value="2022">2022</option>
                                            <option value="2023">2023</option> --}}
                                            <option value="2024">2024</option>
                                            <option value="2025" selected>2025</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label for="month-select"
                                            class="form-label mb-0 fw-semibold theme-label-color">Select Month</label>
                                        <select id="month-select" class="form-select form-select-sm">
                                            <option value="01">January</option>
                                            <option value="02">February</option>
                                            <option value="03">March</option>
                                            <option value="04">April</option>
                                            <option value="05">May</option>
                                            <option value="06">June</option>
                                            <option value="07">July</option>
                                            <option value="08">August</option>
                                            <option value="09">September</option>
                                            <option value="10">October</option>
                                            <option value="11">November</option>
                                            <option value="12">December</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="month_wise_daily_count"></div>
                        </div>
                    </div>
                </div>

                {{-- Month Wise Daily Profit Total Chart 3 --}}
                <div class="col-12 col-xl-6">
                    <div class="card">
                        <div class="card-header py-3">
                            <div class="d-flex align-items-center justify-content-between flex-wrap">
                                <h5 class="mb-0 fw-bold theme-dependent">SMS Profit Total</h5>
                                <div class="d-flex">
                                    <div class="me-3">
                                        <label for="profit-select-year"
                                            class="form-label mb-0 fw-semibold theme-label-color">Select Year</label>
                                        <select id="profit-select-year" class="form-select form-select-sm">
                                            <option value="2024">2024</option>
                                            <option value="2025" selected>2025</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label for="profit-month-select"
                                            class="form-label mb-0 fw-semibold theme-label-color">Select Month</label>
                                        <select id="profit-month-select" class="form-select form-select-sm">
                                            <option value="01">January</option>
                                            <option value="02">February</option>
                                            <option value="03">March</option>
                                            <option value="04">April</option>
                                            <option value="05">May</option>
                                            <option value="06">June</option>
                                            <option value="07">July</option>
                                            <option value="08">August</option>
                                            <option value="09">September</option>
                                            <option value="10">October</option>
                                            <option value="11">November</option>
                                            <option value="12">December</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="month_wise_profit_total"></div>
                        </div>
                    </div>
                </div>

                {{-- Month Wise Daily Cost Total Chart 4 --}}
                <div class="col-12 col-xl-6">
                    <div class="card">
                        <div class="card-header py-3">
                            <div class="d-flex align-items-center justify-content-between flex-wrap">
                                <h5 class="mb-0 fw-bold theme-dependent">SMS Cost Total</h5>
                                <div class="d-flex">
                                    <div class="me-3">
                                        <label for="cost-select-year"
                                            class="form-label mb-0 fw-semibold theme-label-color">Select Year</label>
                                        <select id="cost-select-year" class="form-select form-select-sm">
                                            <option value="2024">2024</option>
                                            <option value="2025" selected>2025</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label for="cost-month-select"
                                            class="form-label mb-0 fw-semibold theme-label-color">Select Month</label>
                                        <select id="cost-month-select" class="form-select form-select-sm">
                                            <option value="01">January</option>
                                            <option value="02">February</option>
                                            <option value="03">March</option>
                                            <option value="04">April</option>
                                            <option value="05">May</option>
                                            <option value="06">June</option>
                                            <option value="07">July</option>
                                            <option value="08">August</option>
                                            <option value="09">September</option>
                                            <option value="10">October</option>
                                            <option value="11">November</option>
                                            <option value="12">December</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="month_wise_cost_total"></div>
                        </div>
                    </div>
                </div>

                {{-- Month Wise Daily User Price Total Chart 5 --}}
                <div class="col-12 col-xl-6">
                    <div class="card">
                        <div class="card-header py-3">
                            <div class="d-flex align-items-center justify-content-between flex-wrap">
                                <h5 class="mb-0 fw-bold theme-dependent">SMS User Price Total</h5>
                                <div class="d-flex">
                                    <div class="me-3">
                                        <label for="userprice-select-year"
                                            class="form-label mb-0 fw-semibold theme-label-color">Select Year</label>
                                        <select id="userprice-select-year" class="form-select form-select-sm">
                                            <option value="2024">2024</option>
                                            <option value="2025" selected>2025</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label for="userprice-month-select"
                                            class="form-label mb-0 fw-semibold theme-label-color">Select Month</label>
                                        <select id="userprice-month-select" class="form-select form-select-sm">
                                            <option value="01">January</option>
                                            <option value="02">February</option>
                                            <option value="03">March</option>
                                            <option value="04">April</option>
                                            <option value="05">May</option>
                                            <option value="06">June</option>
                                            <option value="07">July</option>
                                            <option value="08">August</option>
                                            <option value="09">September</option>
                                            <option value="10">October</option>
                                            <option value="11">November</option>
                                            <option value="12">December</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="month_wise_userprice_total"></div>
                        </div>
                    </div>
                </div>

                {{-- Year Based Monthly Count Chart 1 --}}
                <div class="col-12 col-xl-12">
                    <div class="card w-100 rounded-4 shadow">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap">
                                <h5 class="mb-0 fw-bold theme-dependent">Send SMS Monthly Counts</h5>
                                <div class="d-flex align-items-center">
                                    <label for="year-select" class="form-label mb-0 me-2 fw-semibold theme-label-color">
                                        Year</label>
                                    <select id="year-select" class="form-select form-select-sm">
                                        <option value="2024">2024</option>
                                        <option value="2025" selected>2025</option>
                                    </select>
                                </div>
                            </div>

                            <div id="smsmonthcount" class="mb-4">

                            </div>

                        </div>
                    </div>
                </div>



            </div><!--end row-->

        </div><!--end row-->

            </div>
        </div>

        <!-- Modern Breadcrumb -->
        <div class="modern-breadcrumb">
            <h1 class="breadcrumb-title">
                <i class="material-icons-outlined" style="color: var(--primary-color);">dashboard</i>
                Admin Dashboard
            </h1>
            <p class="breadcrumb-subtitle">Monitor your SMS platform performance and manage operations</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <!-- SMS Sent Card -->
            <div class="modern-stat-card primary">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="material-icons-outlined">send</i>
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="material-icons-outlined" style="font-size: 0.9rem;">trending_up</i>
                        +12.5%
                    </div>
                </div>
                <div class="stat-value">{{ number_format($allQuery->total_count ?? 0) }}</div>
                <div class="stat-label">SMS Messages Sent</div>
                <div class="stat-description">Total messages delivered this month across all channels</div>
            </div>

            <!-- Total Profit Card -->
            <div class="modern-stat-card success">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="material-icons-outlined">trending_up</i>
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="material-icons-outlined" style="font-size: 0.9rem;">trending_up</i>
                        +8.2%
                    </div>
                </div>
                <div class="stat-value">£{{ number_format($allQuery->total_profit ?? 0, 2) }}</div>
                <div class="stat-label">Total Profit</div>
                <div class="stat-description">Revenue generated from SMS services this month</div>
            </div>

            <!-- Operating Costs Card -->
            <div class="modern-stat-card warning">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="material-icons-outlined">account_balance</i>
                    </div>
                    <div class="stat-trend trend-down">
                        <i class="material-icons-outlined" style="font-size: 0.9rem;">trending_down</i>
                        -3.1%
                    </div>
                </div>
                <div class="stat-value">£{{ number_format($allQuery->total_costprice ?? 0, 2) }}</div>
                <div class="stat-label">Operating Costs</div>
                <div class="stat-description">Total operational expenses for SMS delivery</div>
            </div>

            <!-- User Revenue Card -->
            <div class="modern-stat-card info">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="material-icons-outlined">payments</i>
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="material-icons-outlined" style="font-size: 0.9rem;">trending_up</i>
                        +15.7%
                    </div>
                </div>
                <div class="stat-value">£{{ number_format($allQuery->total_userprice ?? 0, 2) }}</div>
                <div class="stat-label">Customer Revenue</div>
                <div class="stat-description">Total amount charged to customers this month</div>
            </div>
        </div>

        <!-- Analytics and Quick Actions -->
        <div class="analytics-section">
            <!-- Charts Section -->
            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <h3 class="chart-title">Performance Analytics</h3>
                        <p class="chart-subtitle">SMS delivery trends and performance metrics</p>
                    </div>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-outline-primary active">7D</button>
                        <button type="button" class="btn btn-sm btn-outline-primary">30D</button>
                        <button type="button" class="btn btn-sm btn-outline-primary">90D</button>
                    </div>
                </div>
                <div id="performanceChart" style="height: 300px; background: #f8fafc; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #6b7280;">
                    <div class="text-center">
                        <i class="material-icons-outlined" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;">insert_chart</i>
                        <p>Performance chart will be displayed here</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3 style="color: var(--text-primary); font-weight: 700; margin-bottom: 1rem;">Quick Actions</h3>
                
                <a href="{{ route('admin.users') }}" class="action-card">
                    <div class="action-header">
                        <div class="action-icon">
                            <i class="material-icons-outlined">people</i>
                        </div>
                        <h4 class="action-title">Manage Users</h4>
                    </div>
                    <p class="action-description">View and manage all customer accounts, permissions, and settings</p>
                </a>

                <a href="{{ route('admin.client.emails') }}" class="action-card">
                    <div class="action-header">
                        <div class="action-icon" style="background: var(--success-color);">
                            <i class="material-icons-outlined">email</i>
                        </div>
                        <h4 class="action-title">Send Notifications</h4>
                    </div>
                    <p class="action-description">Send bulk emails and notifications to customers</p>
                </a>

                <a href="{{ route('admin.monthly-report') }}" class="action-card">
                    <div class="action-header">
                        <div class="action-icon" style="background: var(--info-color);">
                            <i class="material-icons-outlined">assessment</i>
                        </div>
                        <h4 class="action-title">View Reports</h4>
                    </div>
                    <p class="action-description">Access detailed analytics and performance reports</p>
                </a>

                <a href="{{ route('admin.customer.create') }}" class="action-card">
                    <div class="action-header">
                        <div class="action-icon" style="background: var(--warning-color);">
                            <i class="material-icons-outlined">person_add</i>
                        </div>
                        <h4 class="action-title">Add Customer</h4>
                    </div>
                    <p class="action-description">Create new customer accounts and configure settings</p>
                </a>

                <div class="action-card" style="background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); border: 2px dashed var(--border-color);">
                    <div class="action-header">
                        <div class="action-icon" style="background: var(--text-secondary);">
                            <i class="material-icons-outlined">settings</i>
                        </div>
                        <h4 class="action-title">System Settings</h4>
                    </div>
                    <p class="action-description">Configure platform settings, integrations, and preferences</p>
                </div>
            </div>
        </div>
    </div>
</main>

@include('admin.layouts.footer')
@endsection

@push('js')
    {{-- <script>
        // Get the current year and month
        const currentYear = new Date().getFullYear();
        const currentMonth = String(new Date().getMonth() + 1).padStart(2, '0'); // Add leading zero for single-digit months

        // Set the selected value for the year dropdown
        const yearSelect = document.getElementById('month-select-year');
        yearSelect.value = currentYear;

        // Set the selected value for the month dropdown
        const monthSelect = document.getElementById('month-select');
        monthSelect.value = currentMonth;
    </script> --}}
    <script>
        // Year Based Monthly Count Chart 1
        document.addEventListener("DOMContentLoaded", () => {
            const yearSelect = document.getElementById('year-select');
            const chartContainer1 = document.querySelector("#smsmonthcount");
            let chart1; // Instance for the first chart

            function fetchAndRenderChart1(year) {
                fetch(`/admin/get-monthly-counts?year=${year}`)
                    .then(response => response.json())
                    .then(data => {
                        if (chart1) {
                            chart1.updateSeries([{
                                name: "Sent Messages",
                                data: data
                            }]);
                        } else {
                            const options = {
                                series: [{
                                    name: "Sent Messages",
                                    data: data
                                }],
                                chart: {
                                    foreColor: "#9ba7b2",
                                    height: 260,
                                    type: 'bar',
                                    toolbar: {
                                        show: false
                                    }
                                },
                                dataLabels: {
                                    enabled: false
                                },
                                stroke: {
                                    width: 4,
                                    curve: 'smooth',
                                    colors: ['transparent']
                                },
                                fill: {
                                    type: 'gradient',
                                    gradient: {
                                        shade: 'dark',
                                        gradientToColors: ['#293B50', 'rgba(13, 109, 253, 0.35);'],
                                        shadeIntensity: 1,
                                        type: 'vertical',
                                        stops: [0, 100, 100, 100]
                                    }
                                },
                                colors: ['#293B50', "rgba(13, 109, 253, 0.35);"],
                                plotOptions: {
                                    bar: {
                                        horizontal: false,
                                        borderRadius: 4,
                                        columnWidth: '55%',
                                    }
                                },
                                grid: {
                                    show: false,
                                    borderColor: 'rgba(0, 0, 0, 0.15)',
                                    strokeDashArray: 4,
                                },
                                tooltip: {
                                    theme: "dark",
                                    fixed: {
                                        enabled: true
                                    },
                                    x: {
                                        show: true
                                    },
                                    y: {
                                        title: {
                                            formatter: () => ""
                                        }
                                    },
                                    marker: {
                                        show: false
                                    }
                                },
                                xaxis: {
                                    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug',
                                        'Sep', 'Oct', 'Nov', 'Dec'
                                    ],
                                    // labels: {
                                    //     style: {
                                    //         colors: '#000', // Dark color (black)
                                    //         fontSize: '12px'
                                    //     }
                                    // }
                                }

                            };
                            chart1 = new ApexCharts(chartContainer1, options);
                            chart1.render();
                        }
                    })
                    .catch(error => console.error('Error fetching data for chart 1:', error));
            }

            fetchAndRenderChart1(yearSelect.value);

            yearSelect.addEventListener('change', () => {
                fetchAndRenderChart1(yearSelect.value);
            });
        });
    </script>
    <script>
        //Month Wise Daily Count Chart 2
        document.addEventListener("DOMContentLoaded", () => {
            const chartContainer2 = document.querySelector("#month_wise_daily_count");
            const monthSelect = document.getElementById('month-select');
            const yearSelect = document.getElementById('month-select-year');

            let chart2 = null; // Track chart instance to destroy before re-rendering

            // Function to fetch daily counts based on selected year and month
            function fetchDailyCounts(year, month) {
                const formattedMonth = month.padStart(2, '0');

                fetch(`/admin/get-daily-counts?year=${year}&month=${formattedMonth}`)
                    .then(response => response.json())
                    .then(data => {
                        const daysInMonth = new Date(year, formattedMonth, 0)
                            .getDate(); // Total days in selected month
                        const categories = Array.from({
                            length: daysInMonth
                        }, (_, i) => (i + 1).toString()); // 1 to daysInMonth

                        // Fill missing days with 0 if no data exists
                        const counts = categories.map(day => data[day] || 0);

                        // Destroy the existing chart if it exists
                        if (chart2) {
                            chart2.destroy();
                        }

                        const options = {
                            series: [{
                                name: "Send SMS Count",
                                data: counts
                            }],
                            chart: {
                                foreColor: "#9ba7b2",
                                height: 300,
                                type: 'area',
                                zoom: {
                                    enabled: false
                                },
                                toolbar: {
                                    show: false
                                }
                            },
                            dataLabels: {
                                enabled: false
                            },
                            stroke: {
                                width: 3,
                                curve: 'smooth'
                            },
                            fill: {
                                type: 'gradient',
                                gradient: {
                                    shade: 'dark',
                                    gradientToColors: ['#293B50'],
                                    shadeIntensity: 1,
                                    type: 'vertical',
                                    opacityFrom: 0.8,
                                    opacityTo: 0.1,
                                    stops: [0, 100, 100, 100]
                                }
                            },
                            colors: ["#293B50"],
                            grid: {
                                show: true,
                                borderColor: 'rgba(0, 0, 0, 0.15)',
                                strokeDashArray: 4
                            },
                            tooltip: {
                                theme: "dark"
                            },
                            xaxis: {
                                categories: categories,
                                // labels: {
                                //     style: {
                                //         colors: '#000', // Dark color (black)
                                //         fontSize: '12px'
                                //     }
                                // }
                            }
                        };

                        chart2 = new ApexCharts(chartContainer2, options);
                        chart2.render();
                    })
                    .catch(error => console.error('Error fetching data:', error));
            }

            // Event listener for changes in month or year
            monthSelect.addEventListener('change', () => {
                const selectedMonth = monthSelect.value;
                const selectedYear = yearSelect.value;
                fetchDailyCounts(selectedYear, selectedMonth);
            });

            yearSelect.addEventListener('change', () => {
                const selectedMonth = monthSelect.value;
                const selectedYear = yearSelect.value;
                fetchDailyCounts(selectedYear, selectedMonth);
            });

            // Initial chart render with the selected year and month
            fetchDailyCounts(yearSelect.value, monthSelect.value);
        });
    </script>
    <script>
        //Month Wise Daily Profit Chart 3
        // Function to get the number of days in a month
        // function getDaysInMonth(year, month) {
        //     return new Date(year, month, 0).getDate();
        // }

        document.addEventListener("DOMContentLoaded", () => {
            const yearSelect = document.getElementById('profit-select-year');
            const monthSelect = document.getElementById('profit-month-select');
            let chart3;


            async function profitChart() {
                const selectedYear = yearSelect.value;
                const selectedMonth = monthSelect.value;

                try {
                    const response = await fetch(
                        `/admin/monthly-sms-profit?year=${selectedYear}&month=${selectedMonth}`);
                    const result = await response.json();

                    const options = {
                        series: [{
                            name: "SMS Profit Total",
                            data: result.data
                        }],
                        chart: {
                            foreColor: "#9ba7b2",
                            height: 300,
                            type: 'line',
                            zoom: {
                                enabled: false
                            },
                            toolbar: {
                                show: false
                            },
                            dropShadow: {
                                enabled: true,
                                top: 3,
                                left: 14,
                                blur: 4,
                                opacity: 0.12,
                                color: "#fff"
                            },
                        },
                        dataLabels: {
                            enabled: false
                        },
                        stroke: {
                            width: 3,
                            curve: 'smooth'
                        },
                        fill: {
                            type: 'gradient',
                            gradient: {
                                shade: 'dark',
                                gradientToColors: ['#293B50'],
                                shadeIntensity: 1,
                                type: 'vertical',
                                opacityFrom: 1,
                                opacityTo: 1,
                                stops: [0, 100, 100, 100]
                            },
                        },
                        colors: ["#293B50"],
                        grid: {
                            show: true,
                            borderColor: 'rgba(0, 0, 0, 0.15)',
                            strokeDashArray: 4
                        },
                        tooltip: {
                            theme: "dark"
                        },
                        xaxis: {
                            categories: result.categories,
                            // labels: {
                            //     style: {
                            //         colors: '#000', // Dark color (black)
                            //         fontSize: '12px'
                            //     }
                            // }
                        }
                    };

                    // Destroy the existing chart if it exists
                    if (chart3) {
                        chart3.destroy();
                    }

                    // Initialize the new chart
                    chart3 = new ApexCharts(document.querySelector("#month_wise_profit_total"), options);
                    chart3.render();
                } catch (error) {
                    console.error("Error fetching data:", error);
                }
            }

            // Event listener for changes in year or month
            yearSelect.addEventListener('change', profitChart);
            monthSelect.addEventListener('change', profitChart);

            // Initial chart render
            profitChart();
        });
    </script>
    <script>
        //Month Wise Daily Cost Chart 4
        // Function to get the number of days in a month
        // function getDaysInMonth(year, month) {
        //     return new Date(year, month, 0).getDate();
        // }

        document.addEventListener("DOMContentLoaded", () => {
            const yearSelect = document.getElementById('cost-select-year');
            const monthSelect = document.getElementById('cost-month-select');
            let chart4;


            async function costChart() {
                const selectedYear = yearSelect.value;
                const selectedMonth = monthSelect.value;

                try {
                    const response = await fetch(
                        `/admin/monthly-sms-cost?year=${selectedYear}&month=${selectedMonth}`);
                    const result = await response.json();

                    const options = {
                        series: [{
                            name: "SMS Cost Total",
                            data: result.data
                        }],
                        chart: {
                            foreColor: "#9ba7b2",
                            height: 300,
                            type: 'line',
                            zoom: {
                                enabled: false
                            },
                            toolbar: {
                                show: false
                            },
                            dropShadow: {
                                enabled: true,
                                top: 3,
                                left: 14,
                                blur: 4,
                                opacity: 0.12,
                                color: "#fff"
                            },
                        },
                        dataLabels: {
                            enabled: false
                        },
                        stroke: {
                            width: 3,
                            curve: 'smooth'
                        },
                        fill: {
                            type: 'gradient',
                            gradient: {
                                shade: 'dark',
                                gradientToColors: ['#293B50'],
                                shadeIntensity: 1,
                                type: 'vertical',
                                opacityFrom: 1,
                                opacityTo: 1,
                                stops: [0, 100, 100, 100]
                            },
                        },
                        colors: ["#293B50"],
                        grid: {
                            show: true,
                            borderColor: 'rgba(0, 0, 0, 0.15)',
                            strokeDashArray: 4
                        },
                        tooltip: {
                            theme: "dark"
                        },
                        xaxis: {
                            categories: result.categories,
                            // labels: {
                            //     style: {
                            //         colors: '#000', // Dark color (black)
                            //         fontSize: '12px'
                            //     }
                            // }
                        }
                    };

                    // Destroy the existing chart if it exists
                    if (chart4) {
                        chart4.destroy();
                    }

                    // Initialize the new chart
                    chart4 = new ApexCharts(document.querySelector("#month_wise_cost_total"), options);
                    chart4.render();
                } catch (error) {
                    console.error("Error fetching data:", error);
                }
            }

            // Event listener for changes in year or month
            yearSelect.addEventListener('change', costChart);
            monthSelect.addEventListener('change', costChart);

            // Initial chart render
            costChart();
        });
    </script>
    <script>
        //Month Wise Daily User Price Chart 5
        // Function to get the number of days in a month
        // function getDaysInMonth(year, month) {
        //     return new Date(year, month, 0).getDate();
        // }

        document.addEventListener("DOMContentLoaded", () => {
            const yearSelect = document.getElementById('userprice-select-year');
            const monthSelect = document.getElementById('userprice-month-select');
            let chart5;


            async function userPriceChart() {
                const selectedYear = yearSelect.value;
                const selectedMonth = monthSelect.value;

                try {
                    const response = await fetch(
                        `/admin/monthly-sms-userprice?year=${selectedYear}&month=${selectedMonth}`);
                    const result = await response.json();

                    const options = {
                        series: [{
                            name: "SMS User Price Total",
                            data: result.data
                        }],
                        chart: {
                            foreColor: "#9ba7b2",
                            height: 300,
                            type: 'line',
                            zoom: {
                                enabled: false
                            },
                            toolbar: {
                                show: false
                            },
                            dropShadow: {
                                enabled: true,
                                top: 3,
                                left: 14,
                                blur: 4,
                                opacity: 0.12,
                                color: "#fff"
                            },
                        },
                        dataLabels: {
                            enabled: false
                        },
                        stroke: {
                            width: 3,
                            curve: 'smooth'
                        },
                        fill: {
                            type: 'gradient',
                            gradient: {
                                shade: 'dark',
                                gradientToColors: ['#293B50'],
                                shadeIntensity: 1,
                                type: 'vertical',
                                opacityFrom: 1,
                                opacityTo: 1,
                                stops: [0, 100, 100, 100]
                            },
                        },
                        colors: ["#293B50"],
                        grid: {
                            show: true,
                            borderColor: 'rgba(0, 0, 0, 0.15)',
                            strokeDashArray: 4
                        },
                        tooltip: {
                            theme: "dark"
                        },
                        xaxis: {
                            categories: result.categories,
                            // labels: {
                            //     style: {
                            //         colors: '#000', // Dark color (black)
                            //         fontSize: '12px'
                            //     }
                            // }
                        }
                    };

                    // Destroy the existing chart if it exists
                    if (chart5) {
                        chart5.destroy();
                    }

                    // Initialize the new chart
                    chart5 = new ApexCharts(document.querySelector("#month_wise_userprice_total"), options);
                    chart5.render();
                } catch (error) {
                    console.error("Error fetching data:", error);
                }
            }

            // Event listener for changes in year or month
            yearSelect.addEventListener('change', userPriceChart);
            monthSelect.addEventListener('change', userPriceChart);

            // Initial chart render
            userPriceChart();
        });
    </script>
    <script>
        // Get the current month in "MM" format
        const currentMonth = new Date().toISOString().slice(5, 7);
        // Set the selected option
        document.getElementById('month-select').value = currentMonth;
        document.getElementById('profit-month-select').value = currentMonth;
        document.getElementById('cost-month-select').value = currentMonth;
        document.getElementById('userprice-month-select').value = currentMonth;
    </script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add number counting animation
    const statValues = document.querySelectorAll('.stat-value');
    
    statValues.forEach(element => {
        const finalValue = element.textContent;
        const numericValue = finalValue.replace(/[^\d.-]/g, '');
        
        if (numericValue && !isNaN(numericValue)) {
            let currentValue = 0;
            const increment = Math.ceil(numericValue / 100);
            const timer = setInterval(() => {
                currentValue += increment;
                if (currentValue >= numericValue) {
                    currentValue = numericValue;
                    clearInterval(timer);
                }
                
                if (finalValue.includes('£')) {
                    element.textContent = '£' + new Intl.NumberFormat().format(currentValue);
                } else {
                    element.textContent = new Intl.NumberFormat().format(currentValue);
                }
            }, 20);
        }
    });

    // Add hover effects for stat cards
    const statCards = document.querySelectorAll('.modern-stat-card');
    
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    console.log('Modern SMS Expert Admin Dashboard loaded successfully! 🚀');
});
</script>
@endpush
