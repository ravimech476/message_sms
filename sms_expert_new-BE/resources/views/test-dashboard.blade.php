@extends('layouts.app')
@section('title', 'SMS Expert Dashboard - Test')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="material-icons-outlined me-2">dashboard</i>
                        SMS Expert Dashboard - Working!
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="material-icons-outlined display-4 text-primary">account_balance_wallet</i>
                                    <h5 class="card-title">Wallet Balance</h5>
                                    <h3 class="text-success">£0.00</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="material-icons-outlined display-4 text-info">send</i>
                                    <h5 class="card-title">SMS Sent</h5>
                                    <h3 class="text-info">0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="material-icons-outlined display-4 text-success">check_circle</i>
                                    <h5 class="card-title">Delivered</h5>
                                    <h3 class="text-success">0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="material-icons-outlined display-4 text-warning">schedule</i>
                                    <h5 class="card-title">Pending</h5>
                                    <h3 class="text-warning">0</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <h5>Quick Actions</h5>
                            <div class="btn-group" role="group">
                                <a href="{{ route('sendsms') }}" class="btn btn-primary">
                                    <i class="material-icons-outlined me-1">send</i>
                                    Send SMS
                                </a>
                                <a href="{{ route('sms_wallet.index') }}" class="btn btn-success">
                                    <i class="material-icons-outlined me-1">account_balance_wallet</i>
                                    SMS Wallet
                                </a>
                                <a href="{{ route('sentsms') }}" class="btn btn-info">
                                    <i class="material-icons-outlined me-1">history</i>
                                    SMS History
                                </a>
                                <a href="{{ route('profile') }}" class="btn btn-secondary">
                                    <i class="material-icons-outlined me-1">account_circle</i>
                                    Profile
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-success mt-4">
                        <h6><i class="material-icons-outlined me-2">check_circle</i>Layout Test Successful!</h6>
                        <p class="mb-0">If you can see this message, your sidebar and layout are working correctly!</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection