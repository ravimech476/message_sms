@extends('layouts.app')
@section('title', 'Simple Dashboard Test')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="alert alert-success">
                <h4><i class="material-icons-outlined">check_circle</i> Layout Test Successful!</h4>
                <p>Your sidebar and layout are working correctly! If you can see this, everything is properly set up.</p>
                <hr>
                <p><strong>Date/Time:</strong> {{ date('Y-m-d H:i:s') }}</p>
                <p><strong>User:</strong> {{ urldecode(Session::get('user_info.contactname', 'Guest')) }}</p>
                <p><strong>User ID:</strong> {{ Session::get('user_info.bigid', 'N/A') }}</p>
            </div>
            
            <div class="row">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body text-center">
                            <i class="material-icons-outlined display-4">home</i>
                            <h5>Dashboard</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body text-center">
                            <i class="material-icons-outlined display-4">send</i>
                            <h5>Send SMS</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body text-center">
                            <i class="material-icons-outlined display-4">account_balance_wallet</i>
                            <h5>SMS Wallet</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body text-center">
                            <i class="material-icons-outlined display-4">history</i>
                            <h5>SMS History</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection