@extends('emails.layouts.master')

@section('title', 'SMS Expert Contract Agreement')
@section('header_title', 'Contract Agreement')

@section('content')
    <div class="alert alert-info">
        <strong>Contract Agreement</strong><br>
        Please review the following contract agreement.
    </div>

    @if(!empty($data['email_content']))
        <div class="info-card" style="border-left: 4px solid #ea6118;">
            {!! $data['email_content'] !!}
        </div>
    @endif

    <div class="divider"></div>

    <p style="margin-top: 25px;">
        Best regards,<br>
        <strong style="color: #ea6118;">The SMS Expert Team</strong>
    </p>
@endsection
