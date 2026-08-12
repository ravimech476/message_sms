<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Models\ItaggOutboundBlacklist;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;



class ActiveBlacklistController extends Controller
{
    public function index()
    {
        $userInfo = Session::get('user_info');

        if (isset($userInfo['bigid'])) {
            $userref = $userInfo['bigid'];

            $userData = ItaggOutboundBlacklist::where('users_bigid', $userref)
                ->orderBy('date_blocked', 'desc')
                ->get();
        }

        return view('customer.blacklist.index', compact('userData'));
    }
    public function downloadBlacklistCsv()
    {
        $userInfo = Session::get('user_info');

        if (!isset($userInfo['bigid'])) {
            return redirect()->route('numbers.index')->with('error', 'User reference not found in session');
        }

        $userref = $userInfo['bigid'];

        $userData = ItaggOutboundBlacklist::where('users_bigid', $userref)->get();

        if ($userData->isEmpty()) {
            return redirect()->route('blacklist.index')->with('error', 'No data available');
        }

        $fileName = 'blacklist_report.csv';

        $headers = [
            "Content-Type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=\"$fileName\"",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0",
        ];

        $callback = function () use ($userData) {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM for Excel
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // CSV headers
            fputcsv($file, ['S.No', 'Mobile Number', 'Date Blocked'], ",");

            $counter = 1;
            foreach ($userData as $user) {
                fputcsv($file, [
                    $counter++,
                    $user->msisdn ?? '',
                    \Carbon\Carbon::parse($user->date_blocked)->format('d-m-Y'),
                ], ",");
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function destroy($id)
    {
        $record = ItaggOutboundBlacklist::findOrFail($id);
        $record->delete();

        return redirect()->back()->with('success', 'Status updated successfully.');
    }



    // public function downloadBlacklistCsv()
    // {
    //     $userInfo = Session::get('user_info');

    //     if (!isset($userInfo['bigid'])) {
    //         return redirect()->route('numbers.index')->with('error', 'User reference not found in session');
    //     }

    //     $userref = $userInfo['bigid'];

    //     $userData = ItaggOutboundBlacklist::where('users_bigid', $userref)->get();

    //     if ($userData->isEmpty()) {
    //         return redirect()->route('blacklist.index')->with('error', 'No data available');
    //         // return response()->json(['message' => 'No data available for the report.'], 404);
    //     }

    //     $fileName = 'Black_List.txt';
    //     $headers = [
    //         "Content-Type" => "text.csv",
    //         "Content-Disposition" => "attachment; filename=$fileName",
    //         "Pragma" => "no-cache",
    //         "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
    //         "Expires" => "0",
    //     ];

    //     $callback = function () use ($userData) {
    //         $file = fopen('php://output', 'w');

    //         // Add CSV header row
    //         // fputcsv($file, ['Name', 'Mobile Number', 'Network']); // Column headers

    //         foreach ($userData as $user) {
    //             fputcsv($file, [
    //                 $user->msisdn,
    //                 $user->date_blocked->format('d-m-Y'),
    //             ]);
    //         }

    //         fclose($file);
    //     };

    //     return Response::stream($callback, 200, $headers);
    // }
}
