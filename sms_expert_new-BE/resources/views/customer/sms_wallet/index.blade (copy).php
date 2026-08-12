@extends('layouts.app')
@section('title')
    {{ __('SMS Wallet') }}
@endsection
@section('content')
<!--start main wrapper-->
<main class="main-wrapper">
    <div class="main-content">
        <!--breadcrumb-->
           <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
            <div class="breadcrumb-title pe-3 title-name" style="border-right: aqua;">SMS Wallet</div>
        </div>
        <!--end breadcrumb-->
        @if (session('success'))
        <div id="flash-message" class="alert alert-success">
            {{ session('success') }}
        </div>
       @endif
        <div class="row">
            <div class="col-12 col-xl-12 col-xxl-3 d-flex">
                <div class="card w-100 rounded-4">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <h5 class="mb-0 fw-bold theme-dependent">{{ ucfirst($user_contactname) ?? '' }}</h5>
                        </div>
                        <div class="d-flex flex-column justify-content-between gap-4">
                            <div class="d-flex align-items-center gap-4">
                                <div class="align-items-center gap-3 flex-grow-1">
                                    <p class="mb-0"> You have £56.24 remaining of pre-purchased SMS text messages
                                        messages.
                                    </p>
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
                            <h5 class="mb-0 fw-bold theme-dependent"> How to pre-purchase more SMS...</h5>
                        </div>
                        <div class="d-flex flex-column justify-content-between gap-4">
                            <div class="d-flex align-items-center gap-4">
                                <div class="align-items-center gap-3 flex-grow-1">
                                    <p class="mb-0"> To pre-purchase more SMS messages please <a href="javascript:;">
                                            buy online </a>or contact us.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div><!--end row-->
        {{-- <div class="row">
            <div class="col-12 col-xl-12 col-xxl-3 d-flex">
                <div class="card w-100 rounded-4">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <h5 class="mb-0 fw-bold theme-dependent">Daily notification via email</h5>
                        </div>
                        <div class="d-flex flex-column justify-content-between gap-4">
                            <div class="d-flex align-items-center gap-4">
                                <div class="flex-grow-1">
                                    <p class="mb-0">Do you wish to be reminded by email when you are running low on pre-purchased SMS?</p>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="notification" id="notifyYes" value="yes" checked>
                                            <label class="form-check-label" for="notifyYes">
                                                Yes
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="notification" id="notifyNo" value="no">
                                            <label class="form-check-label" for="notifyNo">
                                                No
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div> <br>
                        <div class="d-flex flex-column justify-content-between gap-4">
                            <div class="d-flex align-items-center gap-4">
                                <div class="flex-grow-1">
                                    <p class="mb-0">What monetary amount (in £ sterling) do you want set as a minimum to trigger your low SMS reminder?</p>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input type="text" id="customText" class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div> <br>
                        <div class="d-flex flex-column justify-content-between gap-4">
                            <div class="d-flex align-items-center gap-4">
                                <div class="flex-grow-1">
                                    <p class="mb-0">How many days do you wish between follow-up reminders being sent to you?</p>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <select class="form-select">
                                                <option value="1">1 day</option>
                                                <option value="2">2 days</option>
                                                <option value="3">3 days</option>
                                                <option value="4">4 days</option>
                                                <option value="5">5 days</option>
                                                <option value="6">6 days</option>
                                                <option value="7">7 days</option>
                                                <option value="8">8 days</option>
                                                <option value="9">9 days</option>
                                                <option value="10">10 days</option>
                                                <option value="11">11 days</option>
                                                <option value="12">12 days</option>
                                                <option value="13">13 days</option>
                                                <option value="14">14 days</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        
                    </div>
                </div>
            </div>
        </div><!--end row--> --}}
        <form action="{{ route('update.settings') }}" method="POST">
        @csrf
        @foreach($user->reminders as $reminder)
        <div class="row">
            <div class="col-12 col-xl-12 col-xxl-3 d-flex">
                <div class="card w-100 rounded-4">
                    <div class="card-body">
                        <!-- Daily notification header -->
                        <div class="d-flex align-items-start justify-content-between mb-4">
                            <h5 class="mb-0 fw-bold theme-dependent">Daily notification via email</h5>
                        </div>

                        <!-- Email reminder -->
                      <div class="d-flex flex-column mb-4">
                            <div class="d-flex align-items-center gap-4 mb-3">
                                <div class="flex-grow-1">
                                    <p class="mb-0">Do you wish to be reminded by email when you are running low on
                                        pre-purchased SMS?</p>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="reminderon"
                                                   id="reminderonYes" value="y" {{ $reminder->reminderon == 'y' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="reminderonYes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="reminderon"
                                                   id="reminderonNo" value="n" {{ $reminder->reminderon == 'n' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="reminderonNo">No</label>
                                        </div>
                                        
                                    </div>
                                </div>
                            </div>
                        </div> 

                        <!-- Monetary amount -->
                       <div class="d-flex flex-column mb-4">
                            <div class="d-flex align-items-center gap-4 mb-3">
                                <div class="flex-grow-1">
                                    <p class="mb-0">What monetary amount (in £ sterling) do you want set as a minimum
                                        to trigger your low SMS reminder?</p>
                                    <div class="d-flex gap-3">
                                        <input type="text" id="numonremind"  name="numonremind" class="form-control" value="{{ $reminder->numonremind ?? '' }}">

                                    </div>
                                </div>
                            </div>
                        </div> 

                        <!-- Follow-up reminders -->
                        <div class="d-flex flex-column mb-4">
                            <div class="d-flex align-items-center gap-4 mb-3">
                                <div class="flex-grow-1">
                                    <p class="mb-0">How many days do you wish between follow-up reminders being sent
                                        to you?</p>
                                    <div class="d-flex gap-3">
                                        <select class="form-select" name="reminderperiod">
                                            {{-- <option value="1">1 day</option>
                                            <option value="2">2 days</option>
                                            <option value="3">3 days</option>
                                            <option value="4">4 days</option>
                                            <option value="5">5 days</option>
                                            <option value="6">6 days</option>
                                            <option value="7">7 days</option>
                                            <option value="8">8 days</option>
                                            <option value="9">9 days</option>
                                            <option value="10">10 days</option>
                                            <option value="11">11 days</option>
                                            <option value="12">12 days</option>
                                            <option value="13">13 days</option>
                                            <option value="14">14 days</option> --}}
                                           @for ($i = 1; $i <= 14; $i++)
                                            <option value="{{ $i }}" {{ $reminder->reminderperiod == $i ? 'selected' : '' }}>{{ $i }} day{{ $i > 1 ? 's' : '' }}</option>
                                            @endfor
                                        </select>
                                        
                                    </div>
                                </div>
                            </div>
                        </div> 

                    </div>
                </div>
            </div>
        </div>
        @endforeach
        @foreach($user->options as $option)
        <div class="row">
            <div class="col-12 col-xl-12 col-xxl-3 d-flex">
                <div class="card w-100 rounded-4">
                    <div class="card-body">
                        <!-- Daily notification header -->
                        <div class="d-flex align-items-start justify-content-between mb-4">
                            <h5 class="mb-0 fw-bold theme-dependent">Immediate notifications</h5>
                        </div>

                        <!-- Email reminder -->
                        <div class="d-flex flex-column mb-4">
                            <div class="d-flex align-items-center gap-4 mb-3">
                                <div class="flex-grow-1">
                                    <p class="mb-0">Do you wish to be immediately contacted via Email in the event of
                                        an SMS send failure due to insufficient funds?</p>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="immediateEmailReminderon"
                                                id="immediateEmailReminderon_yes" value="y" {{ $option->immediateEmailReminderon == 'y' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="immediateEmailReminderon_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="immediateEmailReminderon"
                                                id="immediateEmailReminderonl_no" value="n" {{ $option->immediateEmailReminderon == 'n' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="immediateEmailReminderon_no">No</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-column mb-4">
                            <div class="d-flex align-items-center gap-4 mb-3">
                                <div class="flex-grow-1">
                                    <p class="mb-0">Email address</p>
                                    <div class="d-flex gap-3">
                                        <input type="email" id="immediateOutOfFundsNotificationEmail" name="immediateOutOfFundsNotificationEmail" class="form-control" value="{{ $option->immediateOutOfFundsNotificationEmail ?? '' }}">
                                    </div>
                                </div>
                            </div>
                        </div>


                    </div>
                </div>
            </div>
        </div>
        @endforeach
        <div class="row">
            <div class="col-12 col-xl-12 col-xxl-3 d-flex">
                <div class="card w-100 rounded-4">
                    <div class="card-body">
                        <!-- Email reminder -->
                        <div class="d-flex align-items-center gap-4">
                            <p class="mb-0">Immediate notifications will be sent a maximum of once per hour in the
                                event of an ongoing failure due to insufficient funds.</p>
                        </div>



                    </div>
                </div>
            </div>
        </div>


        <div class="d-flex justify-content-start mt-4">
            <button type="submit" class="btn btn-primary">Save</button>
        </div>

    </div><!--end row-->

    </div>
</main>
   <!--end main wrapper-->
    <!-- Footer -->
        @include('layouts.footer')
    <!-- End Footer -->
@endsection
@push('js')
<script>
    // Set a timeout to hide the flash message after 3 seconds (3000 milliseconds)
    setTimeout(function() {
        let flashMessage = document.getElementById('flash-message');
        if (flashMessage) {
            flashMessage.style.display = 'none';
        }
    }, 2000); // 3 seconds
</script>
@endpush
