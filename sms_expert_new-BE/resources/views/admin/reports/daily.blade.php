<!DOCTYPE html>
<html>
<head>
    <title>Daily Report</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 6px;
            border: 1px solid #999;
            text-align: center;
        }
        th {
            background-color: #f4f4f4;
        }
    </style>
</head>
<body>
    <center>
        <a href="javascript:window.close();">Close</a><br><br>
        {{ $countryText }}
    </center>

    <br><br>

    <table>
        <tr>
            <th>Day</th>
            <th>Client Cost</th>
            <th>Volume Submitted</th>
            <th>Percentage Delivered (<b>1.6p</b>)</th>
            <th>iTagg Cost</th>
            <th>iTagg Profit (per £0.001/sms)</th>
            <th>Client Cost per SMS</th>
            <th>iTagg Cost per SMS</th>
            <th>iTagg Profit per SMS</th>
        </tr>
        @foreach ($results as $row)
            <tr>
                <td>{{ $row->Date_Sent }}</td>
                <td>£{{ number_format($row->Client_Cost, 2) }}</td>
                <td>{{ $row->Client_Volume_Sent }}</td>
                <td>{{ number_format($row->Percentage_Delivered, 2) }}%</td>
                <td>£{{ number_format($row->iTagg_Cost, 2) }}</td>
                <td>£{{ number_format($row->iTagg_Profit, 2) }} ({{ number_format($row->iTagg_Profit_per_SMS_per001, 2) }})</td>
                <td>£{{ number_format($row->Client_Cost_per_SMS, 4) }}</td>
                <td>£{{ number_format($row->iTagg_Cost_per_SMS, 4) }}</td>
                <td>£{{ number_format($row->iTagg_Profit_per_SMS, 4) }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
