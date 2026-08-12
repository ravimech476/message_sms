<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Profile Lock</title>
  <!--favicon-->	
  <link rel="icon" href="{{ asset('assets/images/auth/smsexpert_favion.png') }}" type="image/png">

  <!--plugins-->
  <link href="{{ asset('assets/plugins/perfect-scrollbar/css/perfect-scrollbar.css') }}" rel="stylesheet">
  <link rel="stylesheet" type="text/css" href="{{ asset('assets/plugins/metismenu/metisMenu.min.css') }}">
  <link rel="stylesheet" type="text/css" href="{{ asset('assets/plugins/metismenu/mm-vertical.css') }}">
  <!--bootstrap css-->
  <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Material+Icons+Outlined" rel="stylesheet">
  <!--main css-->
  <link href="{{ asset('assets/css/bootstrap-extended.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/sass/main.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/sass/dark-theme.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/sass/semi-dark.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/sass/bordered-theme.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/sass/responsive.css') }}" rel="stylesheet">
  <style>
    .selected-label {
    background-color: #292B50; 
    color: #ffffff; 
    border-radius: 18px;
  }
  /* General navbar styles */
.navbar {
    background-color: #292B50 !important;
    height: 70px;
    position: fixed;
    left: 0 !important;
    right: 0;
    top: 0;
    padding: 0 1.5rem;
    z-index: 10;
    box-shadow: 0 0.125rem 0.25rem rgb(0 0 0 / 8%);
    display: flex;
    align-items: center;
}

/* Container for logo and other content */
.navbar-content {
    display: flex;
    align-items: center;
    width: 100%;
}

/* Styling for logo */
.navbar-logo {
    height: 50px; /* Adjust size as needed */
    margin-right: 1rem; /* Space between logo and other content */
}

/* Optional: Adjust navbar-brand link styling */
.navbar-brand {
    display: flex;
    align-items: center;
    text-decoration: none;
}
.main-wrapper {
    margin-top: 70px !important;
    margin-left: auto;
}

/* Logout css */
/* General styling for nav-item */
.nav-item {
    list-style: none;
}

/* Styling for the link inside the nav item */
.rounded-hover {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px; /* Adjust width as needed */
    height: 40px; /* Adjust height as needed */
    border-radius: 50%; /* Makes the element round */
    background-color: #f0f0f0; /* Light background color */
    transition: background-color 0.3s, box-shadow 0.3s; /* Smooth transition */
}

/* Hover effect */
.rounded-hover:hover {
    background-color: #007bff; /* Change to your desired hover color */
    color: #fff; /* Change text color on hover */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Add shadow on hover */
}

/* Ensure icons are centered */
.rounded-hover i {
    font-size: 20px; /* Adjust icon size if needed */
}


  </style>

</head>

<body>
  <!--start header-->
  <header class="top-header">
    {{-- <nav class="navbar navbar-expand align-items-center justify-content-between gap-3">
      <div class="navbar-content">
          <a href="#" class="navbar-brand">
              <img src="{{ asset('assets/images/auth/smsexpert_cover.png') }}" alt="Logo" class="navbar-logo">
          </a>
          <!-- Add other navbar content here -->
      </div> 
 </nav>--}}
    <nav class="navbar navbar-expand align-items-center justify-content-between gap-3">
      <div class="btn-toggle">
        <img src="{{ asset('assets/images/auth/smsexpert_cover.png') }}" alt="Logo" class="navbar-logo">
      </div> 
      <div class="search-bar w-50">
        <div class="position-relative">
          {{-- <input class="form-control rounded-5 px-5 search-control d-lg-block d-none" type="text" placeholder="Search">
          <span class="material-icons-outlined position-absolute d-lg-block d-none ms-3 translate-middle-y start-0 top-50">search</span>
          <span class="material-icons-outlined position-absolute me-3 translate-middle-y end-0 top-50 search-close">close</span> --}}
          <div class="search-popup p-3">
            <div class="card rounded-4 overflow-hidden">
              <div class="card-header d-lg-none">
                <div class="position-relative">
                  <input class="form-control rounded-5 px-5 mobile-search-control" type="text" placeholder="Search">
                  <span class="material-icons-outlined position-absolute ms-3 translate-middle-y start-0 top-50">search</span>
                  <span class="material-icons-outlined position-absolute me-3 translate-middle-y end-0 top-50 mobile-search-close">close</span>
                 </div>
              </div>
              <div class="card-body search-content">
                
              </div>
              {{-- <div class="card-footer text-center bg-transparent">
                <a href="javascript:;" class="btn w-100">See All Search Results</a>
              </div> --}}
            </div>
          </div>
        </div>
      </div>
      <ul class="navbar-nav gap-1 nav-right-links align-items-center">
        <li class="nav-item d-lg-none mobile-search-btn">
          <a class="nav-link" href="javascript:;"><i class="material-icons-outlined">search</i></a>
        </li>
        {{-- <li class="nav-item dropdown dropdown-laungauge">
          <a class="nav-link dropdown-toggle dropdown-toggle-nocaret" href="javascript:;" data-bs-toggle="dropdown"><img src="assets/images/county/02.png" width="22" alt="">
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item d-flex align-items-center py-2" href="javascript:;"><img src="assets/images/county/02.png" width="20" alt=""><span class="ms-2">English</span></a>
            </li>
          </ul>
        </li> --}}

        {{-- <li class="nav-item dropdown dropdown-language">
          <a class="nav-link dropdown-toggle dropdown-toggle-nocaret" href="javascript:;" data-bs-toggle="dropdown">
            <i class="material-icons-outlined">tune</i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
              <!-- Theme Customization Options -->
              <li class="dropdown-header">Customise Theme</li>
              <li>
                  <input type="radio" class="btn-check" name="theme-options" id="LightTheme" checked>
                  <label class="dropdown-item d-flex align-items-center py-2" for="LightTheme">
                      <span class="material-icons-outlined">light_mode</span>
                      <span class="ms-2">Light</span>
                  </label>
              </li>
              <li>
                  <input type="radio" class="btn-check" name="theme-options" id="DarkTheme">
                  <label class="dropdown-item d-flex align-items-center py-2" for="DarkTheme">
                      <span class="material-icons-outlined">dark_mode</span>
                      <span class="ms-2">Dark</span>
                  </label>
              </li>
              <li>
                  <input type="radio" class="btn-check" name="theme-options" id="SemiDarkTheme">
                  <label class="dropdown-item d-flex align-items-center py-2" for="SemiDarkTheme">
                      <span class="material-icons-outlined">contrast</span>
                      <span class="ms-2">Semi Dark</span>
                  </label>
              </li>
              <li>
                <input type="radio" class="btn-check" name="theme-options" id="BoderedTheme">
                <label class="dropdown-item d-flex align-items-center py-2" for="BoderedTheme">
                    <span class="material-icons-outlined">border_style</span>
                    <span class="ms-2">Bordered</span>
                </label>
            </li>
          </ul>
        </li> --}}
        {{-- <li class="nav-item dropdown dropdown-language">
          <a class="nav-link dropdown-toggle dropdown-toggle-nocaret" href="javascript:;" data-bs-toggle="dropdown">
              <i class="material-icons-outlined">tune</i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
              <!-- Theme Customization Options -->
              <li class="dropdown-header">Customise Theme</li>
              <li>
                  <input type="radio" class="btn-check" name="theme-options" id="LightTheme" checked>
                  <label class="dropdown-item d-flex align-items-center py-2" for="LightTheme">
                      <span class="material-icons-outlined">light_mode</span>
                      <span class="ms-2">Light</span>
                  </label>
              </li>
              <li>
                  <input type="radio" class="btn-check" name="theme-options" id="DarkTheme">
                  <label class="dropdown-item d-flex align-items-center py-2" for="DarkTheme">
                      <span class="material-icons-outlined">dark_mode</span>
                      <span class="ms-2">Dark</span>
                  </label>
              </li>
              <li>
                  <input type="radio" class="btn-check" name="theme-options" id="SemiDarkTheme">
                  <label class="dropdown-item d-flex align-items-center py-2" for="SemiDarkTheme">
                      <span class="material-icons-outlined">contrast</span>
                      <span class="ms-2">Semi Dark</span>
                  </label>
              </li>
              <li>
                  <input type="radio" class="btn-check" name="theme-options" id="BoderedTheme">
                  <label class="dropdown-item d-flex align-items-center py-2" for="BoderedTheme" id="label-bordered-theme">
                      <span class="material-icons-outlined">border_style</span>
                      <span class="ms-2">Bordered</span>
                  </label>
              </li>
          </ul>
      </li> --}}
      
      
        <li class="nav-item dropdown">
          {{-- <a class="nav-link dropdown-toggle dropdown-toggle-nocaret position-relative" href="javascript:;"><i class="material-icons-outlined">notifications</i>
            <span class="badge-notify">5</span>
          </a> --}}
          <div class="dropdown-menu dropdown-notify dropdown-menu-end shadow">
            <div class="px-3 py-1 d-flex align-items-center justify-content-between border-bottom">
              <h5 class="notiy-title mb-0">Notifications</h5>
              <div class="dropdown">
                <button class="btn btn-secondary dropdown-toggle dropdown-toggle-nocaret option" type="button"
                  data-bs-toggle="dropdown" aria-expanded="false">
                  <span class="material-icons-outlined">
                    more_vert
                  </span>
                </button>
                <div class="dropdown-menu dropdown-option dropdown-menu-end shadow">
                  <div><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:;"><i
                        class="material-icons-outlined fs-6">inventory_2</i>Archive All</a></div>
                  <div><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:;"><i
                        class="material-icons-outlined fs-6">done_all</i>Mark all as read</a></div>
                  <div><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:;"><i
                        class="material-icons-outlined fs-6">mic_off</i>Disable Notifications</a></div>
                  <div><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:;"><i
                        class="material-icons-outlined fs-6">grade</i>What's new ?</a></div>
                  <div>
                    <hr class="dropdown-divider">
                  </div>
                  <div><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:;"><i
                        class="material-icons-outlined fs-6">leaderboard</i>Reports</a></div>
                </div>
              </div>
            </div>
            <div class="notify-list">
              <div>
                <a class="dropdown-item border-bottom py-2" href="javascript:;">
                  <div class="d-flex align-items-center gap-3">
                    <div class="">
                      <img src="https://placehold.co/110x110" class="rounded-circle" width="45" height="45" alt="">
                    </div>
                    <div class="">
                      <h5 class="notify-title">Congratulations Jhon</h5>
                      <p class="mb-0 notify-desc">Many congtars jhon. You have won the gifts.</p>
                      <p class="mb-0 notify-time">Today</p>
                    </div>
                    <div class="notify-close position-absolute end-0 me-3">
                      <i class="material-icons-outlined fs-6">close</i>
                    </div>
                  </div>
                </a>
              </div>
              <div>
                <a class="dropdown-item border-bottom py-2" href="javascript:;">
                  <div class="d-flex align-items-center gap-3">
                    <div class="user-wrapper bg-primary text-primary bg-opacity-10">
                      <span>RS</span>
                    </div>
                    <div class="">
                      <h5 class="notify-title">New Account Created</h5>
                      <p class="mb-0 notify-desc">From USA an user has registered.</p>
                      <p class="mb-0 notify-time">Yesterday</p>
                    </div>
                    <div class="notify-close position-absolute end-0 me-3">
                      <i class="material-icons-outlined fs-6">close</i>
                    </div>
                  </div>
                </a>
              </div>
              <div>
                <a class="dropdown-item border-bottom py-2" href="javascript:;">
                  <div class="d-flex align-items-center gap-3">
                    <div class="">
                      <img src="assets/images/apps/13.png" class="rounded-circle" width="45" height="45" alt="">
                    </div>
                    <div class="">
                      <h5 class="notify-title">Payment Recived</h5>
                      <p class="mb-0 notify-desc">New payment recived successfully</p>
                      <p class="mb-0 notify-time">1d ago</p>
                    </div>
                    <div class="notify-close position-absolute end-0 me-3">
                      <i class="material-icons-outlined fs-6">close</i>
                    </div>
                  </div>
                </a>
              </div>
              <div>
                <a class="dropdown-item border-bottom py-2" href="javascript:;">
                  <div class="d-flex align-items-center gap-3">
                    <div class="">
                      <img src="assets/images/apps/14.png" class="rounded-circle" width="45" height="45" alt="">
                    </div>
                    <div class="">
                      <h5 class="notify-title">New Order Recived</h5>
                      <p class="mb-0 notify-desc">Recived new order from michle</p>
                      <p class="mb-0 notify-time">2:15 AM</p>
                    </div>
                    <div class="notify-close position-absolute end-0 me-3">
                      <i class="material-icons-outlined fs-6">close</i>
                    </div>
                  </div>
                </a>
              </div>
              <div>
                <a class="dropdown-item border-bottom py-2" href="javascript:;">
                  <div class="d-flex align-items-center gap-3">
                    <div class="">
                      <img src="https://placehold.co/110x110" class="rounded-circle" width="45" height="45" alt="">
                    </div>
                    <div class="">
                      <h5 class="notify-title">Congratulations Jhon</h5>
                      <p class="mb-0 notify-desc">Many congtars jhon. You have won the gifts.</p>
                      <p class="mb-0 notify-time">Today</p>
                    </div>
                    <div class="notify-close position-absolute end-0 me-3">
                      <i class="material-icons-outlined fs-6">close</i>
                    </div>
                  </div>
                </a>
              </div>
              <div>
                <a class="dropdown-item py-2" href="javascript:;">
                  <div class="d-flex align-items-center gap-3">
                    <div class="user-wrapper bg-danger text-danger bg-opacity-10">
                      <span>PK</span>
                    </div>
                    <div class="">
                      <h5 class="notify-title">New Account Created</h5>
                      <p class="mb-0 notify-desc">From USA an user has registered.</p>
                      <p class="mb-0 notify-time">Yesterday</p>
                    </div>
                    <div class="notify-close position-absolute end-0 me-3">
                      <i class="material-icons-outlined fs-6">close</i>
                    </div>
                  </div>
                </a>
              </div>
            </div>
          </div>
        </li>
        {{-- <li class="nav-item dropdown">
          <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
            class="list-group-item list-group-item-action d-flex align-items-center gap-2"><i
              class="material-icons-outlined">power_settings_new</i></a>
          <form id="logout-form" action="{{ route('logout') }}" method="POST" class="none">
                @csrf
          </form>
        </li> --}}
        <li class="nav-item dropdown">
          <a href="{{ route('logout') }}" 
             onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
             class="list-group-item list-group-item-action d-flex align-items-center gap-2 rounded-hover">
              <i class="material-icons-outlined">power_settings_new</i>
          </a>
          <form id="logout-form" action="{{ route('logout') }}" method="POST" class="none">
              @csrf
          </form>
        </li>
      
      </ul>
    </nav>
  </header>
  <!--end top header-->
 <!--start main wrapper-->
 <main class="main-wrapper">

  <div class="main-content">
      <!--breadcrumb-->
      <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor">
          <div class="ps-3">
              <nav aria-label="breadcrumb">
                  <ol class="breadcrumb mb-0 p-0">
                      <li class="breadcrumb-item active" aria-current="page">Dashboard Locked - Awaiting Profile Changes To Be Confirmed</li>
                  </ol>
              </nav>
          </div>    
      </div>
      <div class="row">
          <div class="col-12 col-xl-12 col-xxl-3 d-flex">
              <div class="card w-100 rounded-4">
                <div class="card-body">
                  <div class="d-flex align-items-start justify-content-between mb-3">
                      <h5 class="mb-0 fw-bold theme-dependent">Welcome, {{ $user->uname ?? ''}} !</h5>
                  </div>
                  <div class="d-flex flex-column justify-content-between gap-4">
                      <div class="d-flex align-items-center gap-4">
                          <div class="align-items-center gap-3 flex-grow-1">
                              <p class="mb-1">Your Dashboard has been locked.</p>
                          </div>
                      </div>
                  </div><br>
                  <p>
                      This is because you recently made changes to your Client Profile. In order to confirm those changes we have sent an email to you at <strong>{{ $user->contactemail ?? '' }}</strong>. When you receive the confirmation email, please click the link shown in it. Once you have done that, we will re-open the Dashboard.
                  </p>
                  <p>
                    If the email hasn't arrived within 10 minutes of changing your Client profile, then please check your spam folder and ensure all emails from 
                    iTAGG is able to reach you (please add 
                    <strong>@smsexpert.co.uk</strong> to your email safe sender list and whitelist).
                </p>
                  <p>
                      Note that your services are still working. A locked Dashboard simply means you cannot access the Dashboard.
                  </p>
                  <p>
                    Please email <strong><a href="mailto:care@smsexpert.co.uk">care@smsexpert.co.uk</a></strong> or call your Account Manager for assistance.
                  </p>
              </div>
              
              </div>
          </div>

      </div>
  </div><!--end row-->

</main>
   <!--end main wrapper-->
    <!-- Footer -->
        @include('layouts.footer')
    <!-- End Footer -->

  <!--start cart-->
  {{-- <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasCart">
    <div class="offcanvas-header border-bottom h-70">
      <h5 class="mb-0" id="offcanvasRightLabel">8 New Orders</h5>
      <a href="javascript:;" class="primaery-menu-close" data-bs-dismiss="offcanvas">
        <i class="material-icons-outlined">close</i>
      </a>
    </div>
    <div class="offcanvas-body p-0">
      <div class="order-list">
        <div class="order-item d-flex align-items-center gap-3 p-3 border-bottom">
          <div class="order-img">
            <img src="https://placehold.co/75x50" class="img-fluid rounded-3" width="75" alt="">
          </div>
          <div class="order-info flex-grow-1">
            <h5 class="mb-1 order-title">White Men Shoes</h5>
            <p class="mb-0 order-price">$289</p>
          </div>
          <div class="d-flex">
            <a class="order-delete"><span class="material-icons-outlined">delete</span></a>
            <a class="order-delete"><span class="material-icons-outlined">visibility</span></a>
          </div>
        </div>

        <div class="order-item d-flex align-items-center gap-3 p-3 border-bottom">
          <div class="order-img">
            <img src="https://placehold.co/75x50" class="img-fluid rounded-3" width="75" alt="">
          </div>
          <div class="order-info flex-grow-1">
            <h5 class="mb-1 order-title">Red Airpods</h5>
            <p class="mb-0 order-price">$149</p>
          </div>
          <div class="d-flex">
            <a class="order-delete"><span class="material-icons-outlined">delete</span></a>
            <a class="order-delete"><span class="material-icons-outlined">visibility</span></a>
          </div>
        </div>

        <div class="order-item d-flex align-items-center gap-3 p-3 border-bottom">
          <div class="order-img">
            <img src="https://placehold.co/75x50" class="img-fluid rounded-3" width="75" alt="">
          </div>
          <div class="order-info flex-grow-1">
            <h5 class="mb-1 order-title">Men Polo Tshirt</h5>
            <p class="mb-0 order-price">$139</p>
          </div>
          <div class="d-flex">
            <a class="order-delete"><span class="material-icons-outlined">delete</span></a>
            <a class="order-delete"><span class="material-icons-outlined">visibility</span></a>
          </div>
        </div>

        <div class="order-item d-flex align-items-center gap-3 p-3 border-bottom">
          <div class="order-img">
            <img src="https://placehold.co/75x50" class="img-fluid rounded-3" width="75" alt="">
          </div>
          <div class="order-info flex-grow-1">
            <h5 class="mb-1 order-title">Blue Jeans Casual</h5>
            <p class="mb-0 order-price">$485</p>
          </div>
          <div class="d-flex">
            <a class="order-delete"><span class="material-icons-outlined">delete</span></a>
            <a class="order-delete"><span class="material-icons-outlined">visibility</span></a>
          </div>
        </div>

        <div class="order-item d-flex align-items-center gap-3 p-3 border-bottom">
          <div class="order-img">
            <img src="https://placehold.co/75x50" class="img-fluid rounded-3" width="75" alt="">
          </div>
          <div class="order-info flex-grow-1">
            <h5 class="mb-1 order-title">Fancy Shirts</h5>
            <p class="mb-0 order-price">$758</p>
          </div>
          <div class="d-flex">
            <a class="order-delete"><span class="material-icons-outlined">delete</span></a>
            <a class="order-delete"><span class="material-icons-outlined">visibility</span></a>
          </div>
        </div>

        <div class="order-item d-flex align-items-center gap-3 p-3 border-bottom">
          <div class="order-img">
            <img src="https://placehold.co/75x50" class="img-fluid rounded-3" width="75" alt="">
          </div>
          <div class="order-info flex-grow-1">
            <h5 class="mb-1 order-title">Home Sofa Set </h5>
            <p class="mb-0 order-price">$546</p>
          </div>
          <div class="d-flex">
            <a class="order-delete"><span class="material-icons-outlined">delete</span></a>
            <a class="order-delete"><span class="material-icons-outlined">visibility</span></a>
          </div>
        </div>

        <div class="order-item d-flex align-items-center gap-3 p-3 border-bottom">
          <div class="order-img">
            <img src="https://placehold.co/75x50" class="img-fluid rounded-3" width="75" alt="">
          </div>
          <div class="order-info flex-grow-1">
            <h5 class="mb-1 order-title">Black iPhone</h5>
            <p class="mb-0 order-price">$1049</p>
          </div>
          <div class="d-flex">
            <a class="order-delete"><span class="material-icons-outlined">delete</span></a>
            <a class="order-delete"><span class="material-icons-outlined">visibility</span></a>
          </div>
        </div>

        <div class="order-item d-flex align-items-center gap-3 p-3 border-bottom">
          <div class="order-img">
            <img src="https://placehold.co/75x50" class="img-fluid rounded-3" width="75" alt="">
          </div>
          <div class="order-info flex-grow-1">
            <h5 class="mb-1 order-title">Goldan Watch</h5>
            <p class="mb-0 order-price">$689</p>
          </div>
          <div class="d-flex">
            <a class="order-delete"><span class="material-icons-outlined">delete</span></a>
            <a class="order-delete"><span class="material-icons-outlined">visibility</span></a>
          </div>
        </div>
      </div>
    </div>
    <div class="offcanvas-footer h-70 p-3 border-top">
      <div class="d-grid">
        <button type="button" class="btn btn-dark" data-bs-dismiss="offcanvas">View Products</button>
      </div>
    </div>
  </div> --}}
  <!--end cart-->

  <!--start primary menu offcanvas-->
  <div class="offcanvas offcanvas-start w-260" data-bs-scroll="true" tabindex="-1" id="offcanvasPrimaryMenu">
    <div class="offcanvas-header border-bottom h-70">
      <img src="{{ asset('assets/images/auth/smsexpert_cover.png') }}" style="background-color: #293B50;border-radius: 7px;padding: 13px 15px 10px;" width="220" alt="">
      <a href="javascript:;" class="primaery-menu-close" data-bs-dismiss="offcanvas">
        <i class="material-icons-outlined">close</i>
      </a>
    </div>
    <div class="offcanvas-body">
      <nav class="sidebar-nav">
        <ul class="metismenu" id="sidenav">
          <li>
            <a href="{{ route('dashboard') }}">
              <div class="parent-icon"><i class="material-icons-outlined">home</i>
              </div>
              <div class="menu-title">Home</div>
            </a>
          </li>
          <li>
            <a href="{{ route('sms_wallet.index') }}">
              <div class="parent-icon"><i class="material-icons-outlined">account_balance_wallet</i>
              </div>
              <div class="menu-title">SMS Wallet</div>
            </a>
          </li>
          <li>
            <a href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">backup</i>
              </div>
              <div class="menu-title">Send New SMS</div>
            </a>
          </li>
          <li>
            <a href="{{ route('profile')}}">
              <div class="parent-icon"><i class="material-icons-outlined">account_circle</i>
              </div>
              <div class="menu-title">Client Profile</div>
            </a>
          </li>
          <li>
            <a href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">cloud_download</i>
              </div>
              <div class="menu-title">Received SMS</div>
            </a>
          </li>
          <li>
            <a href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">forum</i>
              </div>
              <div class="menu-title">Sent SMS</div>
            </a>
          </li>
          <li>
            <a href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">format_list_bulleted</i>
              </div>
              <div class="menu-title">Numbers</div>
            </a>
          </li>
          <li>
            <a href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">group</i>
              </div>
              <div class="menu-title">Groups</div>
            </a>
          </li>
          {{-- <li>
            <a href="{{ route('profile')}}">
              <div class="parent-icon"><i class="material-icons-outlined">account_circle</i>
              </div>
              <div class="menu-title">Client Profile</div>
            </a>
          </li> --}}
          <li>
            <a href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">border_color</i>
              </div>
              <div class="menu-title">Contract</div>
            </a>
          </li>
          <li>
            <a href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">description</i>
              </div>
              <div class="menu-title">Invoices</div>
            </a>
          </li>
          <li>
            <a href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">brightness_7</i>
              </div>
              <div class="menu-title">Technical Docs</div>
            </a>
          </li>
          <li>
            <a href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">import_contacts</i>
              </div>
              <div class="menu-title">Delivery Receipt</div>
            </a>
          </li>
          <li>
            <a href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">support</i>
              </div>
              <div class="menu-title">STOPs/OptoutS</div>
            </a>
          </li>
        </ul>
      </nav>
    </div>
    {{-- <div class="offcanvas-footer p-3 border-top h-70">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="DarkMode">
        <label class="form-check-label" for="DarkMode">Dark Mode</label>
      </div>
    </div> --}}
  </div>
  <!--end primary menu offcanvas-->
  <?php
  $userInfo = Session::get('user_info');
  if (isset($userInfo['bigid'])) {
  $user_contactname = urldecode(Session::get('user_info')['contactname'] ?? '');
  } ?>

  <!--start user details offcanvas-->
  <div class="offcanvas offcanvas-start w-260" data-bs-scroll="true" tabindex="-1" id="offcanvasUserDetails">
    <div class="offcanvas-body">
      <div class="user-wrapper">
        <div class="text-center p-3 bg-light rounded">
          <img src="{{ asset('assets/images/auth/smsexpertlogotwittersquareblueback.png') }}"  style="border-radius: 50% !important;" width="120" height="100" alt="">
          <h5 class="user-name mb-0 fw-bold" style="word-wrap: break-word;">{{ ucfirst($user_contactname) ?? '' }}</h5>
        </div>
        <div class="list-group list-group-flush mt-3 profil-menu fw-bold">
          <a href="{{ route('dashboard') }}"
            class="list-group-item list-group-item-action d-flex align-items-center gap-2 border-top"><i
              class="material-icons-outlined">person_outline</i>Dashboard</a>
          <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
            class="list-group-item list-group-item-action d-flex align-items-center gap-2 border-bottom"><i
              class="material-icons-outlined">power_settings_new</i>Logout</a>
          <form id="logout-form" action="{{ route('logout') }}" method="POST" class="none">
                @csrf
          </form>
        </div>
      </div>

    </div>
    <div class="offcanvas-footer p-3 border-top">
      <div class="text-center">
        <button type="button" class="btn d-flex align-items-center gap-2" data-bs-dismiss="offcanvas"><i
            class="material-icons-outlined">close</i><span>Close Sidebar</span></button>
      </div>
    </div>
  </div>
  <!--end user details offcanvas-->


  <!--start switcher-->
  {{-- <button class="btn btn-primary position-fixed bottom-0 end-0 m-3 d-flex align-items-center gap-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#staticBackdrop">
    <i class="material-icons-outlined">tune</i>Customize
  </button>
  
  <div class="offcanvas offcanvas-end" data-bs-scroll="true" tabindex="-1" id="staticBackdrop">
    <div class="offcanvas-header border-bottom h-70">
      <div class="">
        <h5 class="mb-0">Theme Customizer</h5>
        <p class="mb-0">Customize your theme</p>
      </div>
      <a href="javascript:;" class="primaery-menu-close" data-bs-dismiss="offcanvas">
        <i class="material-icons-outlined">close</i>
      </a>
    </div>
    <div class="offcanvas-body">
      <div>
        <p>Theme variation</p>

        <div class="row g-3">
          <div class="col-12 col-xl-6">
            <input type="radio" class="btn-check" name="theme-options" id="LightTheme" checked>
            <label class="btn btn-outline-secondary d-flex flex-column gap-1 align-items-center justify-content-center p-4" for="LightTheme">
              <span class="material-icons-outlined">light_mode</span>
              <span>Light</span>
            </label>
          </div>
          <div class="col-12 col-xl-6">
            <input type="radio" class="btn-check" name="theme-options" id="DarkTheme">
            <label class="btn btn-outline-secondary d-flex flex-column gap-1 align-items-center justify-content-center p-4" for="DarkTheme">
              <span class="material-icons-outlined">dark_mode</span>
              <span>Dark</span>
            </label>
          </div>
          <div class="col-12 col-xl-6">
            <input type="radio" class="btn-check" name="theme-options" id="SemiDarkTheme">
            <label class="btn btn-outline-secondary d-flex flex-column gap-1 align-items-center justify-content-center p-4" for="SemiDarkTheme">
              <span class="material-icons-outlined">contrast</span>
              <span>Semi Dark</span>
            </label>
          </div>
          <div class="col-12 col-xl-6">
            <input type="radio" class="btn-check" name="theme-options" id="BoderedTheme">
            <label class="btn btn-outline-secondary d-flex flex-column gap-1 align-items-center justify-content-center p-4" for="BoderedTheme">
              <span class="material-icons-outlined">border_style</span>
              <span>Bordered</span>
            </label>
          </div>
        </div><!--end row-->

      </div>
    </div>
  </div> --}}
  <!--start switcher-->


  <!--bootstrap js-->
  <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>

  <!--plugins-->
  <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
  <!--plugins-->
  <script src="{{ asset('assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js') }}"></script>
  <script src="{{ asset('assets/plugins/metismenu/metisMenu.min.js') }}"></script>
  <script src="{{ asset('assets/plugins/apexchart/apexcharts.min.js') }}"></script>
  <script src="{{ asset('assets/js/index.js') }}"></script>
  <script src="{{ asset('assets/plugins/peity/jquery.peity.min.js') }}"></script>
  <script>
    $(".data-attributes span").peity("donut")
  </script>
  <script src="{{ asset('assets/js/main.js') }}"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    const themeOptions = document.querySelectorAll('input[name="theme-options"]');
    const htmlElement = document.documentElement; // The <html> element
    const defaultTheme = 'LightTheme'; // Set the ID of your default theme here

    // Function to update the label styles and the HTML element's data-bs-theme attribute
    function updateTheme(themeId) {
        // Remove selected-label class from all labels
        themeOptions.forEach(function (radio) {
            const label = document.querySelector(`label[for="${radio.id}"]`);
            label.classList.remove('selected-label');
        });

        // Apply selected-label class to the selected radio button's label
        const selectedLabel = document.querySelector(`label[for="${themeId}"]`);
        if (selectedLabel) {
            selectedLabel.classList.add('selected-label');
        }

        // Update the HTML element's data-bs-theme attribute based on the selected theme
        switch (themeId) {
            case 'LightTheme':
                htmlElement.setAttribute('data-bs-theme', 'light');
                break;
            case 'DarkTheme':
                htmlElement.setAttribute('data-bs-theme', 'dark');
                break;
            case 'SemiDarkTheme':
                htmlElement.setAttribute('data-bs-theme', 'semi-dark');
                break;
            case 'BoderedTheme':
                htmlElement.setAttribute('data-bs-theme', 'bodered-theme');
                break;
            default:
                htmlElement.setAttribute('data-bs-theme', 'light');
        }
    }

    // Load the selected theme from localStorage (if any) and apply it
    const savedTheme = localStorage.getItem('selectedTheme');
    if (savedTheme) {
        const radioToSelect = document.getElementById(savedTheme);
        if (radioToSelect) {
            radioToSelect.checked = true;
            updateTheme(savedTheme); // Apply the saved theme
        }
    } else {
        // If no theme is saved, fallback to the default light theme
        const defaultRadio = document.getElementById(defaultTheme);
        if (defaultRadio) {
            defaultRadio.checked = true;
            updateTheme(defaultTheme); // Apply the default theme
        }
    }

    // Listen for changes in theme selection and update the theme accordingly
    themeOptions.forEach(function (radio) {
        radio.addEventListener('change', function () {
            updateTheme(radio.id);
            localStorage.setItem('selectedTheme', radio.id); // Save the selected theme
        });
    });

  });

  </script>
  @stack('js')


</body>

</html>