<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard</title>
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

</head>

<body>
  <!--start header-->
  <header class="top-header">
    <nav class="navbar navbar-expand align-items-center justify-content-between gap-3">
      <div class="btn-toggle">
        <a href="#offcanvasPrimaryMenu" data-bs-toggle="offcanvas"><i class="material-icons-outlined">menu</i></a>
      </div>
      <div class="search-bar w-50">
        <div class="position-relative">
          <input class="form-control rounded-5 px-5 search-control d-lg-block d-none" type="text" placeholder="Search">
          <span class="material-icons-outlined position-absolute d-lg-block d-none ms-3 translate-middle-y start-0 top-50">search</span>
          <span class="material-icons-outlined position-absolute me-3 translate-middle-y end-0 top-50 search-close">close</span>
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
        <li class="nav-item dropdown dropdown-laungauge">
          <a class="nav-link dropdown-toggle dropdown-toggle-nocaret" href="javascript:;" data-bs-toggle="dropdown"><img src="assets/images/county/02.png" width="22" alt="">
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item d-flex align-items-center py-2" href="javascript:;"><img src="assets/images/county/02.png" width="20" alt=""><span class="ms-2">English</span></a>
            </li>
          </ul>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle dropdown-toggle-nocaret position-relative" href="javascript:;"><i class="material-icons-outlined">notifications</i>
            <span class="badge-notify">5</span>
          </a>
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
        <li class="nav-item dropdown">
          <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
            class="list-group-item list-group-item-action d-flex align-items-center gap-2"><i
              class="material-icons-outlined">power_settings_new</i></a>
          <form id="logout-form" action="{{ route('logout') }}" method="POST" class="none">
                @csrf
          </form>
        </li>
      </ul>
    </nav>
  </header>
  <!--end top header-->

  <!--start mini sidebar-->
  <aside class="mini-sidebar d-flex align-items-center flex-column justify-content-between">
    <div class="user">
      <a href="#offcanvasUserDetails" data-bs-toggle="offcanvas" class="user-icon">
        <i class="material-icons-outlined">account_circle</i>
      </a>
    </div>
    <div class="quick-menu">
      <nav class="nav flex-column">
        <a class="nav-link" href="{{ route('dashboard') }}"><i class="material-icons-outlined">home</i></a>
        <a class="nav-link" href="{{ route('wallet.index') }}"><i class="material-icons-outlined">account_balance_wallet</i></a>
        <a class="nav-link" href="javascript:;"><i class="material-icons-outlined">backup</i></a>
        <a class="nav-link" href="javascript:;"><i class="material-icons-outlined">cloud_download</i></a>
        <a class="nav-link" href="javascript:;"><i class="material-icons-outlined">forum</i></a>
        <a class="nav-link" href="javascript:;"><i class="material-icons-outlined">format_list_bulleted</i></a>
        <a class="nav-link" href="javascript:;"><i class="material-icons-outlined">group</i></a>
        <a class="nav-link" href="javascript:;"><i class="material-icons-outlined">account_circle</i></a>
        <a class="nav-link" href="javascript:;"><i class="material-icons-outlined">border_color</i></a>
        <a class="nav-link" href="javascript:;"><i class="material-icons-outlined">description</i></a>
      </nav>
    </div>
    <div class="mini-footer dark-mode">
      <a href="javascript:;" class="footer-icon dark-mode-icon">
        <i class="material-icons-outlined">dark_mode</i>  
      </a>
    </div>
  </aside>
  <!--end mini sidebar-->


  <!--start main wrapper-->
  <main class="main-wrapper">
    <div class="main-content">
      <!--breadcrumb-->
				   <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
					<div class="breadcrumb-title pe-3" style="border-right: aqua;">Dashboard</div>
					{{-- <div class="ps-3">
						<nav aria-label="breadcrumb">
							<ol class="breadcrumb mb-0 p-0">
								<li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a>
								</li>
								<li class="breadcrumb-item active" aria-current="page">eCommerce</li>
							</ol>
						</nav>
					</div> --}}
					{{-- <div class="ms-auto">
						<div class="btn-group">
							<button type="button" class="btn btn-primary">Settings</button>
							<button type="button" class="btn btn-primary split-bg-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">	<span class="visually-hidden">Toggle Dropdown</span>
							</button>
							<div class="dropdown-menu dropdown-menu-right dropdown-menu-lg-end">	<a class="dropdown-item" href="javascript:;">Action</a>
								<a class="dropdown-item" href="javascript:;">Another action</a>
								<a class="dropdown-item" href="javascript:;">Something else here</a>
								<div class="dropdown-divider"></div>	<a class="dropdown-item" href="javascript:;">Separated link</a>
							</div>
						</div>
					</div> --}}
				</div>
				<!--end breadcrumb-->


        <div class="row">
          <div class="col-12 col-xl-12 col-xxl-3 d-flex">
            <div class="card w-100 rounded-4">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <h5 class="mb-0 fw-bold" style="color: #327fab;">SMS Wallet Balance...</h5>
                </div>
                <div class="d-flex flex-column justify-content-between gap-4">
                  <div class="d-flex align-items-center gap-4">
                    <div class="align-items-center gap-3 flex-grow-1">
                      <p class="mb-0"> Your SMS wallet balance is £56.24.<a href=""> Click here to buy more SMS.</a></p>
                    </div>
                  </div>
                </div> 
                <br>
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <h5 class="mb-0 fw-bold" style="color: #327fab;">Register Keywords...</h5>
                </div>
                <div class="d-flex flex-column justify-content-between gap-4">
                  <div class="d-flex align-items-center gap-4">
                    <div class="align-items-center gap-3 flex-grow-1">
                    <p class="mb-0"> You can't currently register any more keywords. Please contact us to discuss setting up additional keywords.</p>
                    </div>
                  </div>
                </div>
                <br>
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <h5 class="mb-0 fw-bold" style="color: #327fab;">Register Dedicated Virtual Mobile Number...</h5>
                </div>
                <div class="d-flex flex-column justify-content-between gap-4">
                  <div class="d-flex align-items-center gap-4">
                    <div class="align-items-center gap-3 flex-grow-1">
                    <p class="mb-0"> Please contact us to discuss setting up dedicated virtual numbers.</p>
                    </div>
                  </div>
                </div>
                <br>
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <h5 class="mb-0 fw-bold" style="color: #327fab;">Contractual Reminder...</h5>
                </div>
                <div class="d-flex flex-column justify-content-between gap-4">
                  <div class="d-flex align-items-center gap-4">
                    <div class="align-items-center gap-3 flex-grow-1">
                    <p class="mb-0">  By continuing to use the SMS Expert services you agree to the latest <a href="">contract </a> and to abide by all applicable laws and regulations.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>  
          </div>

        </div><!--end row-->

        
        <div class="row">
          <div class="col-12 col-xl-12 col-xxl-3 d-flex">
            <div class="card w-100 rounded-4">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <h5 class="mb-0 fw-bold theme-dependent"> Daily Limit For Sending SMS Messages	</h5>
                </div>
                <div class="d-flex flex-column justify-content-between gap-4">
                  <div class="d-flex align-items-center gap-4">
                    <div class="align-items-center gap-3 flex-grow-1">
                      <p class="mb-0"> Each day you are currently allowed to send up to 100000 SMS messages.</p>
                      <p class="mb-0"> To increase your limit please email care@smsexpert.co.uk </p>
                    </div>
                  </div>
                </div> 
              </div>
            </div>  
          </div>
        </div><!--end row-->

        <div class="row">
          <div class="col-12 col-xl-12 col-xxl-3 d-flex">
            <div class="card w-100 rounded-4">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <h5 class="mb-0 fw-bold theme-dependent"> SMS Campaign Manager </h5>
                </div>
                <div class="d-flex flex-column justify-content-between gap-4">
                  <div class="d-flex align-items-center gap-4">
                    <div class="align-items-center gap-3 flex-grow-1">
                      <p class="mb-0"> <a href ="">Click </a> here to use our alternative Campaign Manager to send and manage large volumes of SMS, view your STOP blacklist and HLR clean your data.</p>
                    </div>
                  </div>
                </div> 
              </div>
            </div>  
          </div>
        </div><!--end row-->

        <div class="row">
          <div class="col-12 col-xl-12 col-xxl-3 d-flex">
            <div class="card w-100 rounded-4">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <h5 class="mb-0 fw-bold theme-dependent">  Ensure Important Emails From SMS Expert Reach You	</h5>
                </div>
                <div class="d-flex flex-column justify-content-between gap-4">
                  <div class="d-flex align-items-center gap-4">
                    <div class="align-items-center gap-3 flex-grow-1">
                      <p class="mb-0"> Please add @smsexpert.co.uk to your email safe sender list & whitelist and check that our emails aren't going in to your spam folder.</p>
                    </div>
                  </div>
                </div> 
              </div>
            </div>  
          </div>
        </div><!--end row-->

        <div class="row">
          <div class="col-12 col-xl-12 col-xxl-3 d-flex">
            <div class="card w-100 rounded-4">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <h5 class="mb-0 fw-bold theme-dependent"> Contact Us	</h5>
                </div>
                <div class="d-flex flex-column justify-content-between gap-4">
                  <div class="d-flex align-items-center gap-4">
                    <div class="align-items-center gap-3 flex-grow-1">
                      <p class="mb-0"> <a href="mailto:care@smsexpert.co.uk" target="_blank">For all support queries please email care@smsexpert.co.uk</a></p>
                    </div>
                  </div>
                </div> 
              </div>
            </div>  
          </div>
        </div><!--end row-->
      </div><!--end row-->

    </div>
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
            <a href="{{ route('wallet.index') }}">
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
              <div class="menu-title">Group</div>
            </a>
          </li>
          <li>
            <a href="javascript:;">
              <div class="parent-icon"><i class="material-icons-outlined">account_circle</i>
              </div>
              <div class="menu-title">Client Profile</div>
            </a>
          </li>
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
    <div class="offcanvas-footer p-3 border-top h-70">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="DarkMode">
        <label class="form-check-label" for="DarkMode">Dark Mode</label>
      </div>
    </div>
  </div>
  <!--end primary menu offcanvas-->


  <!--start user details offcanvas-->
  <div class="offcanvas offcanvas-start w-260" data-bs-scroll="true" tabindex="-1" id="offcanvasUserDetails">
    <div class="offcanvas-body">
      <div class="user-wrapper">
        <div class="text-center p-3 bg-light rounded">
          <img src="https://placehold.co/110x110" class="rounded-circle p-1 shadow mb-3" width="120" height="120"
            alt="">
          <h5 class="user-name mb-0 fw-bold">Jhon</h5>
          <p class="mb-0">Customer</p>
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
  <button class="btn btn-primary position-fixed bottom-0 end-0 m-3 d-flex align-items-center gap-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#staticBackdrop">
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
  </div>
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


</body>

</html>