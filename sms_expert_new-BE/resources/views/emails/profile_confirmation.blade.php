@extends('emails.layouts.master')

@section('title', 'SMS Expert Profile Confirmation')
@section('header_title', 'Profile Changes Confirmation')

@section('content')
    <p style="margin-bottom: 20px;">Hi {{ urldecode($formData['contactname'] ?? 'Customer') }},</p>

    <p>Thank you for your SMS Expert Client Profile changes. You must confirm these changes before we can update your account.</p>

    <div class="alert alert-warning">
        <strong>Important:</strong> If you did not request these changes then please contact us and do not click on the confirmation link.
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $confirmationUrl }}" class="btn btn-primary">
            Confirm Profile Changes
        </a>
    </div>

    <div class="divider"></div>

    @if(!empty($formData['service_description']))
        <div class="info-card">
            <div class="info-card-header">New Description of Service</div>
            <p style="margin: 0;">{{ $formData['service_description'] }}</p>
        </div>
    @endif

    <div class="info-card">
        <div class="info-card-header">New Account Details</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 8px 0;"><strong>Business Name:</strong></td>
                <td style="padding: 8px 0; text-align: right;">{{ urldecode($formData['busname'] ?? '') }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0;"><strong>Contact Name:</strong></td>
                <td style="padding: 8px 0; text-align: right;">{{ urldecode($formData['contactname'] ?? '') }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0;"><strong>Address Line 1:</strong></td>
                <td style="padding: 8px 0; text-align: right;">{{ $formData['address1'] ?? '' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0;"><strong>Address Line 2:</strong></td>
                <td style="padding: 8px 0; text-align: right;">{{ $formData['address2'] ?? '' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0;"><strong>Town/City:</strong></td>
                <td style="padding: 8px 0; text-align: right;">{{ $formData['town'] ?? '' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0;"><strong>Postcode:</strong></td>
                <td style="padding: 8px 0; text-align: right;">{{ $formData['pcode'] ?? '' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0;"><strong>Country:</strong></td>
                <td style="padding: 8px 0; text-align: right;">{{ $formData['country'] ?? '' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0;"><strong>Mobile Number:</strong></td>
                <td style="padding: 8px 0; text-align: right;">{{ $formData['mobilenumber'] ?? '' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0;"><strong>Other Number:</strong></td>
                <td style="padding: 8px 0; text-align: right;">{{ $formData['phone'] ?? '' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0;"><strong>Email Address:</strong></td>
                <td style="padding: 8px 0; text-align: right;">{{ $formData['contactemail'] ?? '' }}</td>
            </tr>
        </table>

        @if(!empty($formData['newpassword1']))
            <div class="divider"></div>
            <p style="margin: 0;"><strong>Password:</strong> Your password has been successfully changed</p>
        @endif

        @if(!empty($formData['iplist']))
            @php
                $ipString = implode(', ', $formData['iplist']);
            @endphp
            <div class="divider"></div>
            <p style="margin: 0;"><strong>IP Access List:</strong> {{ $ipString }}</p>
        @endif
    </div>

    <p style="margin-top: 25px;">
        Best regards,<br>
        <strong style="color: #ea6118;">The SMS Expert Team</strong>
    </p>
@endsection
