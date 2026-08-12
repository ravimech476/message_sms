@extends('emails.layouts.master')

@section('title', 'SMS Expert Contract Signed')
@section('header_title', 'Contract Signed')

@section('content')
    <div class="alert alert-success">
        <strong>Contract Signed Successfully!</strong><br>
        The contract has been signed and is now active.
    </div>

    <div class="info-card">
        <div class="info-card-header">Contract Details</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0;"><strong>Contract Title:</strong></td>
                <td style="padding: 10px 0; text-align: right;">{{ $data['contract_title'] ?? '' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Signed By:</strong></td>
                <td style="padding: 10px 0; text-align: right;">{{ $data['signed_by'] ?? '' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Date Signed:</strong></td>
                <td style="padding: 10px 0; text-align: right;">{{ $data['date_signed'] ?? date('D j M Y') }}</td>
            </tr>
        </table>
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
