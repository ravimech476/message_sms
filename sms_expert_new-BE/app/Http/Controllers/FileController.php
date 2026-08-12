<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class FileController extends Controller
{

    // Process the uploaded file
    public function processFileUpload(Request $request)
    {
        // Validate the file input
        $request->validate([
            'fileUpload' => 'required|mimes:txt|max:2048', // File size limit 2MB
        ]);

        // Get the uploaded file
        $file = $request->file('fileUpload');
        // $filePath = file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $filePath = $file->getRealPath(); // Path to the file

        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $bigid = Session::get('user_info')['bigid'];
            $user = Session::get('user_info')['username'];
        }
        $userref = $bigid ?? '';
        $user_name = $user ?? 'null';

        $output = $this->processBlacklistFile($filePath, $userref, $user_name);

        return back()->with('message', $output);
    }

    // Process the content of the file
    private function processBlacklistFile($filePath, $userref, $user_name)
    {
        $i = 1;
        $outputstr = '';
        $results = [];

        // Open the file
        $fh = fopen($filePath, "r");

        if ($fh) {
            while (($therow = fgets($fh)) !== false) {
                $therow = trim($therow);

                // Skip lines that are empty, or start with "END" or "#"
                if (substr($therow, 0, 3) == "END" || empty($therow) || substr($therow, 0, 1) == "#") {
                    continue;
                }

                // Clean and split the phone numbers by comma or space
                $therow = trim($therow, '"');
                $numbers = preg_split('/[\s,]+/', $therow); // Split by spaces or commas

                // Process each phone number separately
                foreach ($numbers as $thenum) {
                    $thenum = str_replace(['.', '&'], '', $thenum); // Remove invalid characters like '.', '&'

                    // Normalize the phone number format
                    if (substr($thenum, 0, 2) == '07') $thenum = '44' . substr($thenum, 1); // UK number starting with '07'
                    if (substr($thenum, 0, 4) == '+447') $thenum = substr($thenum, 1);     // UK number starting with '+447'
                    if (substr($thenum, 0, 4) == '0044') $thenum = substr($thenum, 2);     // UK number starting with '0044'
                    if (substr($thenum, 0, 1) == '7') $thenum = '44' . $thenum;            // UK number starting with '7'

                    // Check if the number is a valid UK or Indian number
                    if (substr($thenum, 0, 3) == '447' || substr($thenum, 0, 2) == '91') {
                        // Check if the number is already in the mobiledetail table
                        $existingMobile = DB::table('itagg_mobiledetail')->where('msisdn', $thenum)->first();
                        if ($existingMobile) {
                            $mobdetailid = $existingMobile->id;
                            $mobdetailstr = 'msisdn: already known';
                        } else {
                            // Insert new mobile detail
                            $mobdetailid = DB::table('itagg_mobiledetail')->insertGetId([
                                'msisdn' => $thenum,
                                'net_id' => 99,
                                'confirmed' => 1,
                                'mbloxDeliverer' => '99',
                            ]);
                            $mobdetailstr = 'msisdn: new';
                        }

                        // Check if the number is already blacklisted for the user
                        $existingBlacklist = DB::table('itagg_outbound_blacklist')
                            ->where('msisdn', $thenum)
                            ->where('users_bigid', $userref)
                            ->first();

                        if ($existingBlacklist) {
                            $blackliststr = 'status: already blocked';
                        } else {
                            // Insert into blacklist
                            DB::table('itagg_outbound_blacklist')->insert([
                                'users_bigid' => $userref,
                                'msisdn' => $thenum,
                                'date_blocked' => now(),
                                'stop_or_stopall' => 'stop',
                                'itagg_mobile_detail_id' => $mobdetailid,
                                'users_uname' => $user_name,
                            ]);
                            $blackliststr = 'status: newly blocked';
                        }

                        $results[] = " $i: $thenum :: $mobdetailstr / $blackliststr ";
                        $i++;
                    }
                }
            }

            fclose($fh);
        }

        return nl2br(implode("<br/>", $results));
    }

    // Process the content of the file
    // private function processBlacklistFile($filePath, $userref, $user_name)
    // {
    //     $i = 1;
    //     $outputstr = '';
    //     $results = [];

    //     // Open the file
    //     $fh = fopen($filePath, "r");

    //     if ($fh) {
    //         while (($therow = fgets($fh)) !== false) {
    //             $therow = trim($therow);

    //             // Skip lines that are empty, or start with "END" or "#"
    //             if (substr($therow, 0, 3) == "END" || empty($therow) || substr($therow, 0, 1) == "#") {
    //                 continue;
    //             }

    //             // Clean and split the phone numbers by comma or space
    //             $therow = trim($therow, '"');
    //             $numbers = preg_split('/[\s,]+/', $therow); // Split by spaces or commas

    //             // Process each phone number separately
    //             foreach ($numbers as $thenum) {
    //                 $thenum = str_replace(['.', '&'], '', $thenum); // Remove invalid characters like '.', '&'

    //                 // Normalize the phone number format
    //                 if (substr($thenum, 0, 2) == '07') $thenum = '44' . substr($thenum, 1);
    //                 if (substr($thenum, 0, 4) == '+447') $thenum = substr($thenum, 1);
    //                 if (substr($thenum, 0, 4) == '0044') $thenum = substr($thenum, 2);
    //                 if (substr($thenum, 0, 1) == '7') $thenum = '44' . $thenum;

    //                 // Only process UK numbers starting with '447'
    //                 if (substr($thenum, 0, 3) != '447') {
    //                     continue;
    //                 }

    //                 // Check if the number is already in the mobiledetail table
    //                 $existingMobile = DB::table('itagg_mobiledetail')->where('msisdn', $thenum)->first();
    //                 if ($existingMobile) {
    //                     $mobdetailid = $existingMobile->id;
    //                     $mobdetailstr = 'msisdn: already known';
    //                 } else {
    //                     // Insert new mobile detail
    //                     $mobdetailid = DB::table('itagg_mobiledetail')->insertGetId([
    //                         'msisdn' => $thenum,
    //                         'net_id' => 99,
    //                         'confirmed' => 1,
    //                         'mbloxDeliverer' => '99',
    //                     ]);
    //                     $mobdetailstr = 'msisdn: new';
    //                 }

    //                 // Check if the number is already blacklisted for the user
    //                 $existingBlacklist = DB::table('itagg_outbound_blacklist')
    //                     ->where('msisdn', $thenum)
    //                     ->where('users_bigid', $userref)
    //                     ->first();

    //                 if ($existingBlacklist) {
    //                     $blackliststr = 'status: already blocked';
    //                 } else {
    //                     // Insert into blacklist
    //                     DB::table('itagg_outbound_blacklist')->insert([
    //                         'users_bigid' => $userref,
    //                         'msisdn' => $thenum,
    //                         'date_blocked' => now(),
    //                         'stop_or_stopall' => 'stop',
    //                         'itagg_mobile_detail_id' => $mobdetailid,
    //                         'users_uname' => $user_name,
    //                     ]);
    //                     $blackliststr = 'status: newly blocked';
    //                 }

    //                 $results[] = "$i: '$thenum':: $mobdetailstr / $blackliststr";
    //                 $i++;
    //             }
    //         }

    //         fclose($fh);
    //     }

    //     return nl2br(implode("<br/>", $results));
    // }
}
