<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\MoneyTransferredExport;
use Maatwebsite\Excel\Facades\Excel;

class MoneyTransferredReportController extends Controller
{
    public function export()
    {
        return Excel::download(new MoneyTransferredExport, 'Money_Transferred_Report.xlsx');
    }
}
