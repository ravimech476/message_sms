@extends('emails.layouts.master')

@section('title', 'SMS Expert Profile Update Confirmation')
@section('header_title', 'Profile Update Confirmation')

@section('content')
    <p style="margin-bottom: 20px;">Hi {{ urldecode($newProfile->contactname ?? 'Customer') }},</p>

    <p>Thank you for your SMS Expert Client Profile changes. You must confirm these changes before we can update your account.</p>

    <div class="divider"></div>

    <div class="info-card">
        <div class="info-card-header">New Description of Service</div>
        <p style="margin: 0;">{{ $newProfile->explanation }}</p>
    </div>

    @if ($newProfile->changed_basicdetails)
        <div class="info-card">
            <div class="info-card-header">New Account Details</div>
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 8px 0;"><strong>Business Name:</strong></td>
                    <td style="padding: 8px 0; text-align: right;">{{ urldecode($newProfile->busname ?? '') }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Contact Name:</strong></td>
                    <td style="padding: 8px 0; text-align: right;">{{ urldecode($newProfile->contactname ?? '') }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Address Line 1:</strong></td>
                    <td style="padding: 8px 0; text-align: right;">{{ $newProfile->address1 }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Address Line 2:</strong></td>
                    <td style="padding: 8px 0; text-align: right;">{{ $newProfile->address2 }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Town/City:</strong></td>
                    <td style="padding: 8px 0; text-align: right;">{{ $newProfile->town }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Postcode:</strong></td>
                    <td style="padding: 8px 0; text-align: right;">{{ $newProfile->pcode }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Country:</strong></td>
                    <td style="padding: 8px 0; text-align: right;">{{ $newProfile->country }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Mobile Number:</strong></td>
                    <td style="padding: 8px 0; text-align: right;">{{ $newProfile->mobilenumber }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Other Number:</strong></td>
                    <td style="padding: 8px 0; text-align: right;">{{ $newProfile->phone }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Email Address:</strong></td>
                    <td style="padding: 8px 0; text-align: right;">{{ $newProfile->contactemail }}</td>
                </tr>
            </table>
        </div>
    @endif

    @if ($newProfile->changed_password)
        <div class="alert alert-success">
            <strong>Password:</strong> Your password has been successfully changed.
        </div>
    @endif

    @if ($newProfile->changed_ips)
        <div class="info-card">
            <p style="margin: 0;"><strong>IP Access List:</strong> {{ $newProfile->ips }}</p>
        </div>
    @endif

    <p style="margin-top: 25px;">
        Best regards,<br>
        <strong style="color: #ea6118;">The SMS Expert Team</strong>
    </p>
@endsection
