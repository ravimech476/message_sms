@extends('emails.layouts.master')

@section('title', 'SMS Expert - Webhook Data')
@section('header_title', 'Webhook Payload Received')

@section('content')
    <div class="alert alert-info">
        <strong>Debug Information:</strong> Webhook payload data received.
    </div>

    <div class="info-card">
        <div class="info-card-header">Webhook Payload</div>
        <pre style="background: #f8fafc; padding: 15px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 12px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; margin: 0;">{{ print_r($data, true) }}</pre>
    </div>
@endsection
