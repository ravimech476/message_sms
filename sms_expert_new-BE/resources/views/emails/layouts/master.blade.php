<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title', 'SMS Expert')</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style type="text/css">
        /* Reset styles */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }
        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            background-color: #f8fafc;
        }

        /* SMS Expert Theme Colors */
        :root {
            --primary-color: #ea6118;
            --primary-light: #ff8a50;
            --secondary-color: #293B50;
            --secondary-light: #3a4d66;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
        }

        .email-wrapper {
            width: 100%;
            background-color: #f8fafc;
            padding: 20px 0;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .email-header {
            background: linear-gradient(135deg, #293B50 0%, #3a4d66 100%);
            padding: 30px 40px;
            text-align: center;
        }

        .email-header::after {
            content: '';
            display: block;
            height: 4px;
            background: linear-gradient(90deg, #ea6118, #ff8a50);
            margin-top: 20px;
            margin-left: -40px;
            margin-right: -40px;
        }

        .email-logo {
            max-width: 180px;
            height: auto;
        }

        .email-title {
            color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 24px;
            font-weight: 600;
            margin: 15px 0 0 0;
        }

        .email-body {
            padding: 40px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 15px;
            line-height: 1.6;
            color: #1f2937;
            text-align: left; /* don't inherit the container's align=center (Outlook) */
        }

        .email-footer {
            background-color: #293B50;
            padding: 30px 40px;
            text-align: center;
        }

        .email-footer p {
            color: rgba(255, 255, 255, 0.8);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 13px;
            margin: 5px 0;
        }

        .email-footer a {
            color: #ff8a50;
            text-decoration: none;
        }

        .email-footer .company-name {
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
        }

        /* Button styles */
        .btn {
            display: inline-block;
            padding: 14px 28px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 8px;
            margin: 10px 0;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ea6118, #ff8a50);
            color: #ffffff !important;
        }

        .btn-secondary {
            background: #293B50;
            color: #ffffff !important;
        }

        .btn-success {
            background: #10b981;
            color: #ffffff !important;
        }

        /* Alert boxes */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
        }

        .alert-info {
            background-color: #e0f2fe;
            border-left: 4px solid #0ea5e9;
            color: #0c4a6e;
        }

        .alert-success {
            background-color: #dcfce7;
            border-left: 4px solid #10b981;
            color: #166534;
        }

        .alert-warning {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }

        .alert-danger {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }

        /* Card styles */
        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .info-card-header {
            background: linear-gradient(135deg, #ea6118, #ff8a50);
            color: #ffffff;
            padding: 12px 20px;
            border-radius: 8px 8px 0 0;
            margin: -20px -20px 15px -20px;
            font-weight: 600;
        }

        /* Table styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .data-table th {
            background-color: #293B50;
            color: #ffffff;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
        }

        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }

        .data-table tr:nth-child(even) {
            background-color: #f9fafb;
        }

        /* Highlight text */
        .highlight {
            color: #ea6118;
            font-weight: 600;
        }

        .text-muted {
            color: #6b7280;
        }

        .text-center {
            text-align: center;
        }

        /* Divider */
        .divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 25px 0;
        }

        /* Responsive */
        @media screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                margin: 0 !important;
                border-radius: 0 !important;
            }
            .email-header, .email-body, .email-footer {
                padding: 20px !important;
            }
            .email-title {
                font-size: 20px !important;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td align="center" style="padding: 20px 10px;">
                    <div class="email-container">
                        <!-- Header -->
                        <div class="email-header">
                            <img src="{{ config('app.url') }}/assets/images/auth/smsexpert_cover.png"
                                 alt="SMS Expert"
                                 width="180"
                                 class="email-logo"
                                 style="width:180px; max-width:180px; height:auto; display:block; margin:0 auto; border:0; outline:none; text-decoration:none; font-size:20px; line-height:1.3; color:#ffffff; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; font-weight:600;"
                                 onerror="this.style.display='none'">
                            @hasSection('header_title')
                                <h1 class="email-title" style="color:#ffffff; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; font-size:24px; line-height:1.3; font-weight:600; margin:15px 0 0 0;">@yield('header_title')</h1>
                            @endif
                        </div>

                        <!-- Body -->
                        <div class="email-body" align="left" style="text-align: left;">
                            @yield('content')
                        </div>

                        <!-- Footer -->
                        <div class="email-footer">
                            <p class="company-name">SMS Expert Limited</p>
                            <p>79-93 Ratcliffe Road, Sileby, Leicestershire, LE12 7PU</p>
                            <p>
                                <a href="mailto:care@smsexpert.co.uk">care@smsexpert.co.uk</a> |
                                <a href="tel:01509606305">01509 606305</a>
                            </p>
                            <p style="margin-top: 15px;">
                                VAT: GB332497592 | Registered in England: 12106151
                            </p>
                            <p style="margin-top: 15px; font-size: 11px; color: rgba(255,255,255,0.6);">
                                &copy; {{ date('Y') }} SMS Expert Ltd. All rights reserved.
                            </p>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
