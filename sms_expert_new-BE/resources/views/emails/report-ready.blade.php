<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, Helvetica, sans-serif; color:#293b50; background:#f5f6f8; margin:0; padding:24px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; margin:0 auto; background:#ffffff; border-radius:8px; overflow:hidden;">
        <tr>
            <td style="background:#ea6118; padding:18px 24px; color:#ffffff; font-size:18px; font-weight:bold;">
                SMS Expert — Report Ready
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                <p style="margin:0 0 12px;">Hi{{ $job->admin_name ? ' ' . e($job->admin_name) : '' }},</p>
                <p style="margin:0 0 16px;">Your requested report has finished generating and is ready to download.</p>

                <table cellpadding="6" cellspacing="0" style="border-collapse:collapse; font-size:14px; margin-bottom:20px;">
                    <tr><td style="color:#6c757d;">Report name</td><td><strong>{{ $job->report_name }}</strong></td></tr>
                    <tr><td style="color:#6c757d;">Type</td><td>{{ \App\Models\ReportJob::typeLabel($job->report_type) }}</td></tr>
                    <tr><td style="color:#6c757d;">Date range</td><td>{{ optional($job->date_from)->format('d M Y') }} &mdash; {{ optional($job->date_to)->format('d M Y') }}</td></tr>
                    @if(!is_null($job->row_count))
                    <tr><td style="color:#6c757d;">Rows</td><td>{{ number_format($job->row_count) }}</td></tr>
                    @endif
                </table>

                <p style="margin:0 0 24px;">
                    <a href="{{ $downloadUrl }}"
                       style="background:#293b50; color:#ffffff; text-decoration:none; padding:12px 22px; border-radius:6px; display:inline-block; font-weight:bold;">
                        Download report
                    </a>
                </p>

                <p style="margin:0; font-size:12px; color:#6c757d;">
                    If the button doesn't work, copy this link into your browser:<br>
                    <a href="{{ $downloadUrl }}" style="color:#ea6118; word-break:break-all;">{{ $downloadUrl }}</a>
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:14px 24px; background:#f0f1f3; font-size:11px; color:#6c757d; text-align:center;">
                This is an automated message from SMS Expert. You can also find this report under Admin &rarr; Reports &rarr; History.
            </td>
        </tr>
    </table>
</body>
</html>
