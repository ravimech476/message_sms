@extends('emails.layouts.master')

@section('title', $subjectLine)
@section('header_title', 'Message from SMS Expert')

@section('content')
    <div class="info-card" align="left" style="border-left: 4px solid #ea6118; text-align: left;">
        {{-- Rich HTML from the admin editor: render as-is so the sender's own
             formatting/alignment (left/center/right, bold, lists…) is preserved. --}}
        <div style="margin: 0; text-align: left;">{!! $messageContent !!}</div>
    </div>

    <div class="divider"></div>

    <p style="margin-top: 25px;">
        Best regards,<br>
        <strong style="color: #ea6118;">The SMS Expert Team</strong>
    </p>
@endsection
