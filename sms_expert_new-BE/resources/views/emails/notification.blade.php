@extends('emails.layouts.master')

@section('title', $title . ' - SMS Expert')
@section('header_title', $title)

@section('content')
    <div style="text-align: center; margin-bottom: 20px;">
        <span style="display: inline-block; padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase;
            @switch($type)
                @case('info')
                    background: rgba(13, 110, 253, 0.15); color: #0d6efd;
                    @break
                @case('warning')
                    background: rgba(255, 193, 7, 0.15); color: #856404;
                    @break
                @case('success')
                    background: rgba(25, 135, 84, 0.15); color: #198754;
                    @break
                @case('danger')
                    background: rgba(220, 53, 69, 0.15); color: #dc3545;
                    @break
                @case('announcement')
                    background: rgba(13, 202, 240, 0.15); color: #0dcaf0;
                    @break
                @default
                    background: rgba(13, 110, 253, 0.15); color: #0d6efd;
            @endswitch
        ">
            @switch($type)
                @case('info')
                    Information
                    @break
                @case('warning')
                    Warning
                    @break
                @case('success')
                    Success
                    @break
                @case('danger')
                    Important
                    @break
                @case('announcement')
                    Announcement
                    @break
                @default
                    Notification
            @endswitch
        </span>
    </div>

    <p style="margin-bottom: 20px;">Hello {{ $userName }},</p>

    <div class="info-card" style="border-left: 4px solid #ea6118;">
        <p style="margin: 0; white-space: pre-wrap;">{!! nl2br(e($notificationMessage ?? '')) !!}</p>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.url') }}/dashboard" class="btn btn-primary">
            Go to Dashboard
        </a>
    </div>

    <p class="text-muted" style="font-size: 14px;">
        This notification was sent from SMS Expert. If you have any questions, please contact our support team.
    </p>
@endsection
