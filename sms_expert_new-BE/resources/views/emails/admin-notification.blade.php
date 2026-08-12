@extends('emails.layouts.master')

@section('title', $notification->title)
@section('header_title', 'Notification')

@section('content')
    <div class="alert alert-{{ $notification->type == 'info' ? 'info' : ($notification->type == 'warning' ? 'warning' : ($notification->type == 'success' ? 'success' : 'danger')) }}">
        <span style="display: inline-block; padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 10px; background: {{ $notification->type == 'info' ? '#0ea5e9' : ($notification->type == 'warning' ? '#f59e0b' : ($notification->type == 'success' ? '#10b981' : '#ef4444')) }}; color: {{ $notification->type == 'warning' ? '#000' : '#fff' }};">
            {{ ucfirst($notification->type) }}
        </span>
    </div>

    <h2 style="margin-top: 0; color: #293B50;">{{ $notification->title }}</h2>

    <div class="info-card">
        <p style="margin: 0;">{!! nl2br(e($notification->message)) !!}</p>
    </div>

    <p>Dear {{ urldecode($customer->busname ?: $customer->contactname ?: 'Customer') }},</p>

    <p>This is an important notification from SMS Expert. Please log in to your dashboard for more details.</p>

    @if($notification->requires_acknowledgement)
        <div class="alert alert-warning">
            <strong>Action Required:</strong> This notification requires your acknowledgement. Please log in to your dashboard and acknowledge this message.
        </div>
    @endif

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/login" class="btn btn-primary">
            Go to Dashboard
        </a>
    </div>
@endsection
