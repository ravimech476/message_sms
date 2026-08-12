<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\PostPayInvoiceExport;
use Maatwebsite\Excel\Facades\Excel;

class PostPayReportController extends Controller
{
    public function export()
    {
        $filename = 'Post_Pay_Customer_Report_' . now()->format('d-m-Y') . '.xlsx';
        return Excel::download(new PostPayInvoiceExport, $filename);
    }
}
