<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-4">

    <a class="btn btn-sm btn-dark mb-3" href="javascript:window.close();">
        Close
    </a>

    <br><br>

    {!! $thecountriestxt !!}

    <br><br>

    <table class="table table-bordered table-striped w-100">
        <thead class="table-dark">
            <tr>
                <th>Month</th>
                <th>Client Cost</th>
                <th>Volume Submitted</th>
                <th>Percentage Delivered (<b>1.65p</b>)</th>
                <th>iTagg Cost</th>
                <th>iTagg Profit (per £0.001/sms)</th>
                <th>Client Cost per SMS</th>
                <th>iTagg Cost per SMS</th>
                <th>iTagg Profit per SMS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $row)
                <tr>
                    <td>{{ $row->month }}</td>
                    <td>{{ $row->client_cost }}</td>
                    <td>{{ $row->volume_submitted }}</td>
                    <td>{{ $row->percentage_delivered }}</td>
                    <td>{{ $row->itagg_cost }}</td>
                    <td>{{ $row->itagg_profit }}</td>
                    <td>{{ $row->client_cost_per_sms }}</td>
                    <td>{{ $row->itagg_cost_per_sms }}</td>
                    <td>{{ $row->itagg_profit_per_sms }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

</div>

<!-- Bootstrap JS (optional) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
