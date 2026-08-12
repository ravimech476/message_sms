@extends('admin.layouts.app')
@section('title')
    {{ __('CRM') }}
@endsection
@push('style')
    <style>
        :root {
            --bs-pink-rgb: 236, 29, 99;
            --bs-war-rgb: 1, 188, 214;
            --bs-secon-rgb: 136, 196, 74;
            --bs-succ-rgb: 255, 152, 0;
        }

        .text-pink {
            --bs-text-opacity: 1;
            color: rgba(var(--bs-pink-rgb), var(--bs-text-opacity)) !important;
        }

        .text-warning {
            --bs-text-opacity: 1;
            color: rgba(var(--bs-war-rgb), var(--bs-text-opacity)) !important;
        }

        .text-secondary {
            --bs-text-opacity: 1;
            color: rgba(var(--bs-secon-rgb), var(--bs-text-opacity)) !important;
        }

        .text-success {
            --bs-text-opacity: 1;
            color: rgba(var(--bs-succ-rgb), var(--bs-text-opacity)) !important;
        }
    </style>
@endpush
@section('content')
    <!--start main wrapper-->
     <main class="main-wrapper" id="main-wrapper">
        <div class="main-content">
            <!--breadcrumb-->
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
                <div class="breadcrumb-title pe-3 title-name" style="border-right: aqua;">Dashboard</div>
                <!-- Back Button -->
                <div class="me-2 back-button-container"
                    style="position: absolute; right: 0; top: 50%; transform: translateY(-50%);">
                    {{-- <button id="backButton" class="btn btn-primary btn-sm">
                        <i class="bx bx-arrow-back"></i> Back
                    </button> --}}
                </div>
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
                </div> --}}

                {{-- <div class="col-12 col-xl-8"> --}}
                {{-- <div class="col-12 col-xl-8">
                    <div class="card rounded-4">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-around flex-wrap gap-4 p-4">
                                <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                                    <a href="javascript:;"
                                        class="mb-2 wh-48 bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="material-icons-outlined">forum</i>
                                    </a>
                                    <h3 class="mb-0">100</h3>
                                    <p class="mb-0">SMS</p>
                                </div>
                                <div class="vr"></div>
                                <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                                    <a href="javascript:;"
                                        class="mb-2 wh-48 bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="material-icons-outlined">print</i>
                                    </a>
                                    <h3 class="mb-0"> £6,147</h3>
                                    <p class="mb-0">Income</p>
                                </div>
                                <div class="vr"></div>
                                <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                                    <a href="javascript:;"
                                        class="mb-2 wh-48 bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="material-icons-outlined">notifications</i>
                                    </a>
                                    <h3 class="mb-0">846</h3>
                                    <p class="mb-0">Notifications</p>
                                </div>
                                <div class="vr"></div>

                                <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                                    <a href="javascript:;"
                                        class="mb-2 wh-48 bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="material-icons-outlined">payment</i>
                                    </a>
                                    <h3 class="mb-0"> £{{ sprintf('%.2f', $remaining_wallet ?? 0) }} </h3>
                                    <p class="mb-0">Wallet</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>  --}}

                <!-- Month/Year Filter -->
                <div class="col-12 mb-3">
                    <div class="card">
                        <div class="card-body py-2">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="material-icons-outlined text-primary">filter_alt</i>
                                    <span class="fw-semibold">Filter Dashboard:</span>
                                    <span class="badge bg-light text-secondary border fw-normal" title="Dashboard figures are refreshed daily and reflect data up to {{ $dataTillDate ?? \Carbon\Carbon::yesterday()->format('jS M Y') }}">
                                        <i class="material-icons-outlined" style="font-size: 13px; vertical-align: middle;">schedule</i>
                                        Data till {{ $dataTillDate ?? \Carbon\Carbon::yesterday()->format('jS M Y') }}
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <select class="form-select form-select-sm" id="dashboard-month-filter" style="width: 140px;">
                                        <option value="01" {{ ($selectedMonth ?? now()->format('m')) == '01' ? 'selected' : '' }}>January</option>
                                        <option value="02" {{ ($selectedMonth ?? now()->format('m')) == '02' ? 'selected' : '' }}>February</option>
                                        <option value="03" {{ ($selectedMonth ?? now()->format('m')) == '03' ? 'selected' : '' }}>March</option>
                                        <option value="04" {{ ($selectedMonth ?? now()->format('m')) == '04' ? 'selected' : '' }}>April</option>
                                        <option value="05" {{ ($selectedMonth ?? now()->format('m')) == '05' ? 'selected' : '' }}>May</option>
                                        <option value="06" {{ ($selectedMonth ?? now()->format('m')) == '06' ? 'selected' : '' }}>June</option>
                                        <option value="07" {{ ($selectedMonth ?? now()->format('m')) == '07' ? 'selected' : '' }}>July</option>
                                        <option value="08" {{ ($selectedMonth ?? now()->format('m')) == '08' ? 'selected' : '' }}>August</option>
                                        <option value="09" {{ ($selectedMonth ?? now()->format('m')) == '09' ? 'selected' : '' }}>September</option>
                                        <option value="10" {{ ($selectedMonth ?? now()->format('m')) == '10' ? 'selected' : '' }}>October</option>
                                        <option value="11" {{ ($selectedMonth ?? now()->format('m')) == '11' ? 'selected' : '' }}>November</option>
                                        <option value="12" {{ ($selectedMonth ?? now()->format('m')) == '12' ? 'selected' : '' }}>December</option>
                                    </select>
                                    <select class="form-select form-select-sm" id="dashboard-year-filter" style="width: 100px;">
                                        @foreach($availableYears ?? [now()->year, now()->year - 1, now()->year - 2] as $year)
                                            <option value="{{ $year }}" {{ ($selectedYear ?? now()->year) == $year ? 'selected' : '' }}>{{ $year }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-primary btn-sm" id="apply-dashboard-filter">
                                        <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">search</i>
                                        Apply
                                    </button>
                                    <span id="filter-loading" class="d-none">
                                        <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 d-flex">
                    <div class="card rounded-4 w-100 shadow-none bg-transparent">
                        <div class="card-body p-0">
                            <div class="row g-3">
                                <!-- Total Sent SMS Count (all SMS) -->
                                <div class="col">
                                    <div class="card card-box h-100" style="background-color: #673ab7;">
                                        <div class="card-header border-0 py-3 d-flex align-items-center justify-content-between">
                                            <div class="chart-num-days">
                                                <h2 class="count-num text-white fs-2 mb-1" id="card-sent-count">{{ number_format($totalSentCount ?? 0) }}</h2>
                                                <p class="text-white mb-0 small">Total Sent SMS</p>
                                            </div>
                                            <a href="javascript:;" class="wh-42 bg-white text-purple rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="material-icons-outlined fs-5">send</i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <!-- SMS Profit -->
                                <div class="col">
                                    <div class="card card-box h-100" style="background-color: #01bcd6;">
                                        <div class="card-header border-0 py-3 d-flex align-items-center justify-content-between">
                                            <div class="chart-num-days">
                                                <h2 class="count-num text-white fs-2 mb-1" id="card-profit">£{{ number_format($allQuery->total_profit ?? 0, 4) }}</h2>
                                                <p class="text-white mb-0 small">SMS Profit (GBP)</p>
                                            </div>
                                            <a href="javascript:;" class="wh-42 bg-white text-warning rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="material-icons-outlined fs-5">fitbit</i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <!-- SMS Cost -->
                                <div class="col">
                                    <div class="card card-box h-100" style="background-color: #88c44a;">
                                        <div class="card-header border-0 py-3 d-flex align-items-center justify-content-between">
                                            <div class="chart-num-days">
                                                <h2 class="count-num text-white fs-2 mb-1" id="card-cost">£{{ number_format($allQuery->total_costprice ?? 0, 4) }}</h2>
                                                <p class="text-white mb-0 small">SMS Cost (GBP)</p>
                                            </div>
                                            <a href="javascript:;" class="wh-42 bg-white text-secondary rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="material-icons-outlined fs-5">payments</i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <!-- SMS User Price -->
                                <div class="col">
                                    <div class="card card-box h-100" style="background-color: #ff9800;">
                                        <div class="card-header border-0 py-3 d-flex align-items-center justify-content-between">
                                            <div class="chart-num-days">
                                                <h2 class="count-num text-white fs-2 mb-1" id="card-userprice">£{{ number_format($allQuery->total_userprice ?? 0, 4) }}</h2>
                                                <p class="text-white mb-0 small">SMS User Price (GBP)</p>
                                            </div>
                                            <a href="javascript:;" class="wh-42 bg-white text-success rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="material-icons-outlined fs-5">sell</i>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                            </div><!--end row-->
                        </div>
                    </div>
                </div>



                {{-- Month Wise Daily Count Chart 2 --}}
                <div class="col-12 col-xl-6">
                    <div class="card">
                        <div class="card-header py-3">
                            <div class="d-flex align-items-center justify-content-between flex-wrap">
                                <h5 class="mb-0 fw-bold theme-dependent">SMS Delivered Counts</h5>
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
                                            <option value="2025">2025</option>
                                            <option value="2026" selected>2026</option>
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
                                <h5 class="mb-0 fw-bold theme-dependent">SMS Profit Total (GBP)</h5>
                                <div class="d-flex">
                                    <div class="me-3">
                                        <label for="profit-select-year"
                                            class="form-label mb-0 fw-semibold theme-label-color">Select Year</label>
                                        <select id="profit-select-year" class="form-select form-select-sm">
                                            <option value="2024">2024</option>
                                            <option value="2025">2025</option>
                                            <option value="2026" selected>2026</option>
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
                                <h5 class="mb-0 fw-bold theme-dependent">SMS Cost Total (GBP)</h5>
                                <div class="d-flex">
                                    <div class="me-3">
                                        <label for="cost-select-year"
                                            class="form-label mb-0 fw-semibold theme-label-color">Select Year</label>
                                        <select id="cost-select-year" class="form-select form-select-sm">
                                            <option value="2024">2024</option>
                                            <option value="2025">2025</option>
                                            <option value="2026" selected>2026</option>
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
                                <h5 class="mb-0 fw-bold theme-dependent">SMS User Price Total (GBP)</h5>
                                <div class="d-flex">
                                    <div class="me-3">
                                        <label for="userprice-select-year"
                                            class="form-label mb-0 fw-semibold theme-label-color">Select Year</label>
                                        <select id="userprice-select-year" class="form-select form-select-sm">
                                            <option value="2024">2024</option>
                                            <option value="2025">2025</option>
                                            <option value="2026" selected>2026</option>
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
                                <h5 class="mb-0 fw-bold theme-dependent">SMS Delivered Monthly Counts</h5>
                                <div class="d-flex align-items-center">
                                    <label for="year-select" class="form-label mb-0 me-2 fw-semibold theme-label-color">
                                        Year</label>
                                    <select id="year-select" class="form-select form-select-sm">
                                        <option value="2024">2024</option>
                                        <option value="2025">2025</option>
                                        <option value="2026" selected>2026</option>
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

    </main>
    <!--end main wrapper-->>
    <!-- Footer -->
    @include('admin.layouts.footer')
    <!-- End Footer -->
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
                                name: "SMS Delivered Count",
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
                            theme: "dark",
                            y: {
                                formatter: function(value) {
                                    return '£' + value.toFixed(4);
                                }
                            }
                        },
                        yaxis: {
                            labels: {
                                formatter: function(value) {
                                    return '£' + value.toFixed(4);
                                }
                            }
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
                            theme: "dark",
                            y: {
                                formatter: function(value) {
                                    return '£' + value.toFixed(4);
                                }
                            }
                        },
                        yaxis: {
                            labels: {
                                formatter: function(value) {
                                    return '£' + value.toFixed(4);
                                }
                            }
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
                            theme: "dark",
                            y: {
                                formatter: function(value) {
                                    return '£' + value.toFixed(4);
                                }
                            }
                        },
                        yaxis: {
                            labels: {
                                formatter: function(value) {
                                    return '£' + value.toFixed(4);
                                }
                            }
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
        // Dashboard Filter - Update Cards via AJAX
        document.addEventListener("DOMContentLoaded", () => {
            const applyBtn = document.getElementById('apply-dashboard-filter');
            const monthFilter = document.getElementById('dashboard-month-filter');
            const yearFilter = document.getElementById('dashboard-year-filter');
            const filterLoading = document.getElementById('filter-loading');

            applyBtn.addEventListener('click', async () => {
                const selectedMonth = monthFilter.value;
                const selectedYear = yearFilter.value;

                // Show loading spinner
                applyBtn.disabled = true;
                filterLoading.classList.remove('d-none');

                try {
                    const response = await fetch(`/admin/sms-summary?year=${selectedYear}&month=${selectedMonth}`);
                    const result = await response.json();

                    if (result.success) {
                        // Update card values
                        document.getElementById('card-sent-count').textContent = result.data.sent_count;
                        document.getElementById('card-profit').textContent = '£' + result.data.total_profit;
                        document.getElementById('card-cost').textContent = '£' + result.data.total_costprice;
                        document.getElementById('card-userprice').textContent = '£' + result.data.total_userprice;

                        // Sync the individual chart filters with the main filter
                        document.getElementById('month-select').value = selectedMonth;
                        document.getElementById('month-select-year').value = selectedYear;
                        document.getElementById('profit-month-select').value = selectedMonth;
                        document.getElementById('profit-select-year').value = selectedYear;
                        document.getElementById('cost-month-select').value = selectedMonth;
                        document.getElementById('cost-select-year').value = selectedYear;
                        document.getElementById('userprice-month-select').value = selectedMonth;
                        document.getElementById('userprice-select-year').value = selectedYear;

                        // Trigger change events to refresh charts
                        document.getElementById('month-select').dispatchEvent(new Event('change'));
                        document.getElementById('profit-month-select').dispatchEvent(new Event('change'));
                        document.getElementById('cost-month-select').dispatchEvent(new Event('change'));
                        document.getElementById('userprice-month-select').dispatchEvent(new Event('change'));
                    }
                } catch (error) {
                    console.error('Error fetching dashboard summary:', error);
                } finally {
                    // Hide loading spinner
                    applyBtn.disabled = false;
                    filterLoading.classList.add('d-none');
                }
            });
        });
    </script>
@endpush
