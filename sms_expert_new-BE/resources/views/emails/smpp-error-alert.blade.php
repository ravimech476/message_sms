@extends('emails.layouts.master')

@section('title', 'SMS Expert - SMPP Error Alert')
@section('header_title', 'SMPP Error Alert')

@section('content')
    <div class="alert alert-danger">
        <strong>Alert:</strong> An SMPP error has been detected and the system needs your attention.
    </div>

    <div class="info-card">
        <div class="info-card-header">Failure Details</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0; width: 40%;"><strong>Subject:</strong></td>
                <td style="padding: 10px 0;" class="highlight">{{ $data['subject_line'] ?? '(no subject)' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Time:</strong></td>
                <td style="padding: 10px 0;">{{ $data['sent_at'] ?? now()->toDateTimeString() }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Environment:</strong></td>
                <td style="padding: 10px 0;">{{ $data['env'] ?? 'unknown' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Server:</strong></td>
                <td style="padding: 10px 0;">{{ $data['host'] ?? 'unknown' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Next Email:</strong></td>
                <td style="padding: 10px 0;">{{ $data['throttled_for'] ?? '—' }}</td>
            </tr>
        </table>
    </div>

    <div class="info-card">
        <div class="info-card-header">What Happened</div>
        <p style="margin: 0; padding: 10px 0;">{{ $data['body'] ?? '' }}</p>
    </div>

    @if (!empty($data['context']) && is_array($data['context']))
        <div class="info-card">
            <div class="info-card-header">Context</div>
            <table style="width: 100%;">
                @foreach ($data['context'] as $key => $value)
                    <tr>
                        <td style="padding: 8px 0; width: 35%;"><strong>{{ $key }}:</strong></td>
                        <td style="padding: 8px 0; word-break: break-all;">
                            @if (is_array($value) || is_object($value))
                                <pre style="background: #f8fafc; padding: 10px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 12px; margin: 0; white-space: pre-wrap; word-wrap: break-word;">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            @else
                                {{ $value }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    <div class="divider"></div>

    <p class="text-muted" style="font-size: 12px; text-align: center;">
        This is an automated SMPP monitor notification from SMS Expert.<br>
        No further emails for this subject will be sent until SMPP recovers, or the throttle window expires.<br>
        If the issue persists, please investigate the SMPP daemon logs and provider account status.
    </p>
@endsection
