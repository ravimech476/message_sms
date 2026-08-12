<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\DailySmsReportExport;
use Maatwebsite\Excel\Facades\Excel;

class DailySmsReportController extends Controller
{
    public function dailySmsExport()
    {
        $filename = 'Daily_SMS_Report_' . now()->format('d-m-Y') . '.xlsx';
        return Excel::download(new DailySmsReportExport, $filename);
    }
}
