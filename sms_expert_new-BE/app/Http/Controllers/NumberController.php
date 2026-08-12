<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MobNetwork;
use App\Models\ItaggMobileDetail;
use App\Models\CpUsersAddressBook;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\CpGroupAddressBook;
use Illuminate\Support\Facades\Response;
use PHPUnit\Framework\TestSize\Unknown;

class NumberController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $userInfo = Session::get('user_info');

        if (isset($userInfo['bigid'])) {
            $userref = $userInfo['bigid'];

            $userData = CpUsersAddressbook::with(['mobileDetail.network'])
                ->where('user_bigid', $userref)
                ->get();
        }

        return view('customer.number.index', compact('userData'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $mobnetworks = MobNetwork::all();

        return view('customer.number.create', compact('mobnetworks'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        try {
            $existingMobileDetail = ItaggMobileDetail::where('msisdn', $request->number)->first();

            $get_name = strtolower($request->name);
            if ($existingMobileDetail) {
                $mobileDetailId = $existingMobileDetail->id;

                $userInfo = Session::get('user_info');
                if (isset($userInfo['bigid'])) {
                    $userref = $userInfo['bigid'];

                    $existingAddressBookEntries = DB::table('cp_users_addressbook')
                        ->where('user_bigid', $userref)
                        ->where('itagg_mobiledetail_id', $mobileDetailId)
                        ->get();

                    foreach ($existingAddressBookEntries as $entry) {
                        if ($entry->name == $get_name && $existingMobileDetail->msisdn == $request->number) {
                            return redirect()->route('numbers.index')->with('error', "  Phone number already known by iTAGG. Will use existing record.
                            No need to add number {$request->number} to your address book as it already exists.");
                        }
                    }

                    $isFavourite = $request->has('favourite') ? 'y' : 'n';

                    DB::table('cp_users_addressbook')->insert([
                        'name' => $get_name,
                        'itagg_mobiledetail_id' => $mobileDetailId,
                        'user_bigid' => $userref,
                        'is_favourite' => $isFavourite,
                    ]);
                }
            } else {
                $mobileDetail = new ItaggMobileDetail();
                $mobileDetail->msisdn = $request->number;
                $mobileDetail->net_id = $request->net_id;
                $mobileDetail->confirmed = 0;
                $mobileDetail->lastchanged = Carbon::now('Europe/London')->format('YmdHis');
                $mobileDetail->mbloxDeliverer = null;
                $mobileDetail->save();

                $mobileDetailId = $mobileDetail->id;

                $userInfo = Session::get('user_info');
                if (isset($userInfo['bigid'])) {
                    $userref = $userInfo['bigid'];
                }

                $isFavourite = $request->has('favourite') ? 'y' : 'n';

                DB::table('cp_users_addressbook')->insert([
                    'name' => $get_name,
                    'itagg_mobiledetail_id' => $mobileDetailId,
                    'user_bigid' => $userref,
                    'is_favourite' => $isFavourite,
                ]);

                return redirect()->route('numbers.index')->with('success', 'Number created successfully');
            }
        } catch (\Exception $e) {
            Log::error('Error inserting mobile details: ' . $e->getMessage());

            return redirect()->route('numbers.index')->with('error', "An error occurred while processing your request. Please try again later.");
        }



        // try {

        //     $existingMobileDetail = ItaggMobileDetail::where('msisdn', $request->number)->first();

        //     if (isset($existingMobileDetail)) {
        //         $mobileDetailId = $existingMobileDetail->id;
        //     } else {
        //         $mobileDetail = new ItaggMobileDetail();
        //         $mobileDetail->msisdn = $request->number;
        //         $mobileDetail->net_id = $request->net_id;
        //         $mobileDetail->confirmed = 0;
        //         $mobileDetail->lastchanged = Carbon::now('Europe/London')->format('YmdHis');
        //         $mobileDetail->mbloxDeliverer = null;

        //         $mobileDetail->save();

        //         $mobileDetailId = $mobileDetail->id;
        //     }

        //     $userInfo = Session::get('user_info');
        //     if (isset($userInfo['bigid'])) {
        //         $userref = $userInfo['bigid'];
        //     }

        //     $isFavourite = $request->has('favourite') ? 'y' : 'n';

        //     DB::table('cp_users_addressbook')->insert([
        //         'name' => $request->name,
        //         'itagg_mobiledetail_id' => $mobileDetailId,
        //         'user_bigid' => $userref,
        //         'is_favourite' => $isFavourite,
        //     ]);

        //     // $addressbook = new CpUsersAddressBook();
        //     // $addressbook->name = $request->name;
        //     // $addressbook->itagg_mobiledetail_id = $$mobileDetailId;
        //     // $addressbook->user_bigid = $userref;
        //     // $addressbook->is_favourite = $isFavourite;
        //     // $addressbook->save();
        //     // Log::info($mobileDetail->toArray());

        // } catch (\Exception $e) {
        //     Log::error('Error inserting mobile details: ' . $e->getMessage());

        //     return redirect()->route('numbers.index')->with('error', "Phone number already known by iTAGG. Will use existing record. No need to add number {$request->number} to your address book as it already exists.");

        //     // return response()->json([
        //     //     'status' => 'error',
        //     //     'message' => 'An error occurred while processing your request. Please try again later.'
        //     // ], 500);
        // }


        return redirect()->route('numbers.index')->with('success', 'Number created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $number = CpUsersAddressbook::findOrFail($id);

        return view('customer.number.edit', compact('number'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $number = CpUsersAddressbook::findOrFail($id);
        $get_name = strtolower($request->name);

        $existingEntry = CpUsersAddressbook::where('name', $get_name)
            ->where('itagg_mobiledetail_id', $number->itagg_mobiledetail_id)
            ->where('id', '!=', $id) // Exclude the current record
            ->first();

        if ($existingEntry) {
            return redirect()->route('numbers.index')->with('error', 'The name already exists for this phone number');
        }

        $isFavourite = $request->has('favourite') ? 'y' : 'n';

        $number->update([
            'name' => $get_name,
            'is_favourite' => $isFavourite,
        ]);

        return redirect()->route('numbers.index')->with('success', 'Name updated successfully');
    }

    // public function update(Request $request, string $id)
    // {

    //     $number = CpUsersAddressbook::findOrFail($id);

    //     $get_name = strtolower($request->name);
    //     $existingEntry = CpUsersAddressbook::where('name', $get_name)
    //         ->where('itagg_mobiledetail_id', $number->itagg_mobiledetail_id)
    //         ->first();

    //     if (isset($existingEntry)) {
    //         if ($existingEntry->name == $get_name) {
    //             return redirect()->route('numbers.index')->with('error', 'The name already exists for this phone number');
    //         }
    //     } else {
    //         $isFavourite = $request->has('favourite') ? 'y' : 'n';

    //         $number->update([
    //             'name' =>  $get_name,
    //             'is_favourite' => $isFavourite,
    //         ]);

    //         return redirect()->route('numbers.index')->with('success', 'Name updated successfully');
    //     }


    //     // $number = CpUsersAddressbook::findOrFail($id);

    //     // $isFavourite = $request->has('favourite') ? 'y' : 'n';

    //     // $number->update([
    //     //     'name' => $request->name,
    //     //     'is_favourite' => $isFavourite,
    //     // ]);

    //     // return redirect()->route('numbers.index')->with('success', 'Name updated successfully!');
    // }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $name = CpUsersAddressbook::findOrFail($id);

        DB::transaction(function () use ($name) {
            DB::table('cp_group_addressbook')
                ->where('addressbook_id', $name->id)
                ->delete();

            $name->delete();
        });

        return redirect()->route('numbers.index')->with('success', 'Number deleted successfully');
    }

    public function cleanBadNumbers()
    {
        return view('customer.number.cleanbad_number');
    }

    public function uploadIndex()
    {

        $mobnetworks = MobNetwork::all();

        return view('customer.number.number_upload', compact('mobnetworks'));
    }

    public function uploadFile(Request $request)
    {
        // Accept .csv
        // $request->validate([
        //     'userfile' => 'required|file|mimetypes:text/plain,text/csv|max:2048',
        // ]);

        // Accept only .csv files
        $request->validate([
            'userfile' => 'required|file|mimetypes:text/plain,text/csv|max:2048',
        ], [
            'userfile.required' => 'Please select a file to upload.',
            'userfile.mimetypes' => 'Only .csv files are allowed.',
            'userfile.max' => 'The file size must not exceed 2MB.',
        ]);


        $file = $request->file('userfile');
        $fileContents = file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $lineNumber   = 0;
        $successCount = 0;
        $errorMessages = [];

        // Get user reference
        $userInfo = Session::get('user_info');
        $userref = $userInfo['bigid'] ?? null;

        if (!$userref) {
            return back()->with('error', 'User reference not found in session.');
        }

        foreach ($fileContents as $line) {
            $lineNumber++;
            $line = trim($line);
            $columns = array_map('trim', explode(',', $line));

            // Must be 3 to 5 columns
            if (count($columns) < 3 || count($columns) > 5) {
                $errorMessages[] = "Invalid format on line $lineNumber: $line";
                continue;
            }

            // Extract values
            $name       = $columns[0] ?? null;
            $msisdn     = $columns[1] ?? null;
            $networkId  = $columns[2] ?? null;
            $isFavourite = strtolower($columns[3] ?? 'n'); // default "n" if not given
            $groupName  = $columns[4] ?? null;

            // ✅ If empty string, default to "n"
            if ($isFavourite === '' || $isFavourite === null) {
                $isFavourite = 'n';
            }

            // Validate name
            if (!preg_match('/^[a-zA-Z0-9\s]+$/', $name)) {
                $errorMessages[] = "Invalid name on line $lineNumber: $name";
                continue;
            }

            // Validate msisdn
            if (!preg_match('/^\d+$/', $msisdn)) {
                $errorMessages[] = "Invalid mobile number on line $lineNumber: $msisdn";
                continue;
            }

            // $ukPattern = '/^(?:\+44\d{10}|44\d{10}|07\d{9}|01932\d{6})$/';
            // $indiaPattern = '/^\+?91\d{10}$/';

            // if (
            //     !preg_match($ukPattern, $msisdn) &&
            //     !preg_match($indiaPattern, $msisdn)
            // ) {
            //     $errorMessages[] = "Invalid mobile number on line $lineNumber: $msisdn";
            //     continue;
            // }


            // Validate network ID
            if (!is_numeric($networkId)) {
                $errorMessages[] = "Invalid network ID on line $lineNumber: $networkId";
                continue;
            }

            // Validate favourite flag (only y/n)
            if (!in_array($isFavourite, ['y', 'n'])) {
                $errorMessages[] = "Invalid is_favourite on line $lineNumber: $isFavourite (Allowed: y/n)";
                continue;
            }

            // Check duplicates
            $existingEntry = DB::table('cp_users_addressbook')
                ->join('itagg_mobiledetail', 'cp_users_addressbook.itagg_mobiledetail_id', '=', 'itagg_mobiledetail.id')
                ->where('user_bigid', $userref)
                ->where('cp_users_addressbook.name', $name)
                ->where('itagg_mobiledetail.msisdn', $msisdn)
                ->exists();

            if ($existingEntry) {
                $errorMessages[] = "Duplicate entry on line $lineNumber: Name '$name' & Mobile '$msisdn' already exist.";
                continue;
            }

            // Insert / create mobile detail
            $existingMobileDetail = ItaggMobileDetail::where('msisdn', $msisdn)->first();

            if ($existingMobileDetail) {
                $addressbookId = DB::table('cp_users_addressbook')->insertGetId([
                    'name' => $name,
                    'itagg_mobiledetail_id' => $existingMobileDetail->id,
                    'user_bigid' => $userref,
                    'is_favourite' => $isFavourite,
                ]);
            } else {
                $mobileDetail = new ItaggMobileDetail();
                $mobileDetail->msisdn = $msisdn;
                $mobileDetail->net_id = $networkId;
                $mobileDetail->confirmed = 0;
                $mobileDetail->lastchanged = Carbon::now('Europe/London')->format('YmdHis');
                $mobileDetail->mbloxDeliverer = null;
                $mobileDetail->save();

                $addressbookId = DB::table('cp_users_addressbook')->insertGetId([
                    'name' => $name,
                    'itagg_mobiledetail_id' => $mobileDetail->id,
                    'user_bigid' => $userref,
                    'is_favourite' => $isFavourite,
                ]);
            }

            // ✅ Handle group if provided
            if ($groupName) {
                $groupId = DB::table('cp_users_groups')
                    ->where('user_bigid', $userref)
                    ->where('name', $groupName)
                    ->value('id');

                if (!$groupId) {
                    $groupId = DB::table('cp_users_groups')->insertGetId([
                        'name' => $groupName,
                        'user_bigid' => $userref,
                    ]);
                }

                DB::table('cp_group_addressbook')->updateOrInsert(
                    [
                        'group_id' => $groupId,
                        'addressbook_id' => $addressbookId,
                    ],
                    []
                );
            }

            $successCount++;
        }

        $successMessage = $successCount > 0 ? "$successCount records processed successfully." : null;
        $errorMessage   = !empty($errorMessages) ? implode('<br/>', $errorMessages) : null;

        return back()->with([
            'success' => $successMessage,
            'error'   => $errorMessage,
        ]);
    }



    // public function uploadFile(Request $request)
    // {

    //     // Accept both .txt and .csv
    //     $request->validate([
    //         'userfile' => 'required|file|mimetypes:text/plain,text/csv|max:2048',
    //     ]);

    //     $file = $request->file('userfile');
    //     $fileContents = file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    //     $lineNumber = 0;
    //     $successCount = 0;
    //     $errorMessages = [];

    //     // Get user reference from session
    //     $userInfo = Session::get('user_info');
    //     $userref = $userInfo['bigid'] ?? null;

    //     if (!$userref) {
    //         return back()->with('error', 'User reference not found in session.');
    //     }

    //     foreach ($fileContents as $line) {
    //         $lineNumber++;

    //         // Clean and split line
    //         $line = trim($line);
    //         $columns = array_map('trim', explode(',', $line));

    //         // Must have 4 columns
    //         if (count($columns) !== 4) {
    //             $errorMessages[] = "Invalid format on line $lineNumber: $line";
    //             continue;
    //         }

    //         [$name, $msisdn, $networkId, $isFavourite] = $columns;

    //         // Validate name (letters, numbers, spaces)
    //         if (!preg_match('/^[a-zA-Z0-9\s]+$/', $name)) {
    //             $errorMessages[] = "Invalid name on line $lineNumber: $name (Only letters, numbers and spaces allowed)";
    //             continue;
    //         }

    //         // Validate mobile number
    //         if (!preg_match('/^\d+$/', $msisdn)) {
    //             $errorMessages[] = "Invalid mobile number on line $lineNumber: $msisdn";
    //             continue;
    //         }

    //         // Validate networkId
    //         if (!is_numeric($networkId)) {
    //             $errorMessages[] = "Invalid network ID on line $lineNumber: $networkId";
    //             continue;
    //         }

    //         // Validate favourite flag
    //         if (!in_array(strtolower($isFavourite), ['y', 'n'])) {
    //             $errorMessages[] = "Invalid is_favourite on line $lineNumber: $isFavourite (Allowed: y/n)";
    //             continue;
    //         }

    //         // Check for duplicates
    //         $existingEntry = DB::table('cp_users_addressbook')
    //             ->join('itagg_mobiledetail', 'cp_users_addressbook.itagg_mobiledetail_id', '=', 'itagg_mobiledetail.id')
    //             ->where('user_bigid', $userref)
    //             ->where('cp_users_addressbook.name', $name)
    //             ->where('itagg_mobiledetail.msisdn', $msisdn)
    //             ->exists();

    //         if ($existingEntry) {
    //             $errorMessages[] = "Duplicate entry on line $lineNumber: Name '$name' & Mobile '$msisdn' already exist.";
    //             continue;
    //         }

    //         // Insert or create mobile detail
    //         $existingMobileDetail = ItaggMobileDetail::where('msisdn', $msisdn)->first();

    //         if ($existingMobileDetail) {
    //             DB::table('cp_users_addressbook')->insert([
    //                 'name' => $name,
    //                 'itagg_mobiledetail_id' => $existingMobileDetail->id,
    //                 'user_bigid' => $userref,
    //                 'is_favourite' => strtolower($isFavourite),
    //             ]);
    //         } else {
    //             $mobileDetail = new ItaggMobileDetail();
    //             $mobileDetail->msisdn = $msisdn;
    //             $mobileDetail->net_id = $networkId;
    //             $mobileDetail->confirmed = 0;
    //             $mobileDetail->lastchanged = Carbon::now('Europe/London')->format('YmdHis');
    //             $mobileDetail->mbloxDeliverer = null;
    //             $mobileDetail->save();

    //             DB::table('cp_users_addressbook')->insert([
    //                 'name' => $name,
    //                 'itagg_mobiledetail_id' => $mobileDetail->id,
    //                 'user_bigid' => $userref,
    //                 'is_favourite' => strtolower($isFavourite),
    //             ]);
    //         }

    //         $successCount++;
    //     }

    //     // Response messages
    //     $successMessage = $successCount > 0 ? "$successCount records processed successfully." : null;
    //     $errorMessage   = !empty($errorMessages) ? implode('<br/>', $errorMessages) : null;

    //     return back()->with([
    //         'success' => $successMessage,
    //         'error'   => $errorMessage,
    //     ]);
    // }


    // public function uploadFile(Request $request)
    // {
    //     // Validate the uploaded file
    //     $request->validate([
    //         'userfile' => 'required|file|mimes:txt|max:2048',
    //     ]);

    //     $file = $request->file('userfile');
    //     $fileContents = file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    //     $lineNumber = 0;
    //     $successCount = 0;
    //     $errorMessages = [];

    //     // Get user reference from session
    //     $userInfo = Session::get('user_info');
    //     $userref = $userInfo['bigid'] ?? null;

    //     if (!$userref) {
    //         return back()->with(['error' => 'User reference not found in session.']);
    //     }

    //     foreach ($fileContents as $line) {
    //         $lineNumber++;

    //         // Clean and split the line into columns
    //         $line = trim($line);
    //         $columns = array_map('trim', explode(',', $line));

    //         // Validate the line format
    //         if (count($columns) !== 4) {
    //             $errorMessages[] = "Invalid format on line $lineNumber: $line";
    //             continue;
    //         }

    //         [$name, $msisdn, $networkId, $isFavourite] = $columns;
    //         // Validate the name (only alphabets and spaces allowed)
    //         if (!preg_match('/^[a-zA-Z\s]+$/', $name)) {
    //             // $errorMessages[] = "Invalid name format on line $lineNumber: $name. Only alphabets and spaces are allowed.";
    //             $errorMessages[] = "Invalid name format on line $lineNumber: $name";
    //             continue;
    //         }

    //         // Additional validation for `msisdn` and `networkId`
    //         if (!preg_match('/^\d+$/', $msisdn)) {
    //             $errorMessages[] = "Invalid Mobile number on line $lineNumber: $msisdn";
    //             continue;
    //         }

    //         if (!is_numeric($networkId)) {
    //             $errorMessages[] = "Invalid Network ID on line $lineNumber: $networkId";
    //             continue;
    //         }

    //         if (!in_array(strtolower($isFavourite), ['y', 'n'])) {
    //             $errorMessages[] = "Invalid is_favourite value on line $lineNumber: $isFavourite";
    //             continue;
    //         }

    //         // Check if a record with the same MSISDN and Name exists for the user
    //         $existingEntry = DB::table('cp_users_addressbook')
    //             ->join('itagg_mobiledetail', 'cp_users_addressbook.itagg_mobiledetail_id', '=', 'itagg_mobiledetail.id')
    //             ->where('user_bigid', $userref)
    //             ->where('cp_users_addressbook.name', $name)
    //             ->where('itagg_mobiledetail.msisdn', $msisdn)
    //             ->exists();

    //         if ($existingEntry) {
    //             $errorMessages[] = "Duplicate entry found on line $lineNumber: Mobile Number '$msisdn' & Name '$name' already exist.";
    //             continue;
    //         }

    //         // Check if the MSISDN already exists in `itagg_mobile_details`
    //         $existingMobileDetail = ItaggMobileDetail::where('msisdn', $msisdn)->first();

    //         if ($existingMobileDetail) {
    //             // Use the existing `itagg_mobile_details` ID for insertion into `cp_users_addressbook`
    //             DB::table('cp_users_addressbook')->insert([
    //                 'name' => $name,
    //                 'itagg_mobiledetail_id' => $existingMobileDetail->id,
    //                 'user_bigid' => $userref,
    //                 'is_favourite' => strtolower($isFavourite) === 'y' ? 'y' : 'n',
    //             ]);
    //         } else {
    //             // Create a new record in `itagg_mobile_details`
    //             $mobileDetail = new ItaggMobileDetail();
    //             $mobileDetail->msisdn = $msisdn;
    //             $mobileDetail->net_id = $networkId;
    //             $mobileDetail->confirmed = 0;
    //             $mobileDetail->lastchanged = Carbon::now('Europe/London')->format('YmdHis');
    //             $mobileDetail->mbloxDeliverer = null;
    //             $mobileDetail->save();

    //             // Use the new `itagg_mobile_details` ID for insertion into `cp_users_addressbook`
    //             DB::table('cp_users_addressbook')->insert([
    //                 'name' => $name,
    //                 'itagg_mobiledetail_id' => $mobileDetail->id,
    //                 'user_bigid' => $userref,
    //                 'is_favourite' => strtolower($isFavourite) === 'y' ? 'y' : 'n',
    //             ]);
    //         }

    //         $successCount++;
    //     }

    //     // Prepare response messages
    //     $successMessage = $successCount > 0 ? "$successCount records processed successfully." : null;
    //     $errorMessage = !empty($errorMessages) ? nl2br(implode('<br/>', $errorMessages)) : null;

    //     return back()->with([
    //         'success' => $successMessage,
    //         'error' => $errorMessage,
    //     ]);
    // }


    // public function uploadFile(Request $request)
    // {
    //     // Validate the uploaded file
    //     $request->validate([
    //         'userfile' => 'required|file|mimes:txt|max:2048',
    //     ]);

    //     $file = $request->file('userfile');
    //     $fileContents = file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    //     $lineNumber = 0;
    //     $successCount = 0;
    //     $errorMessages = [];

    //     // Get user reference from session
    //     $userInfo = Session::get('user_info');
    //     $userref = $userInfo['bigid'] ?? null;

    //     if (!$userref) {
    //         return back()->with(['error' => 'User reference not found in session.']);
    //     }

    //     foreach ($fileContents as $line) {
    //         $lineNumber++;

    //         // Clean and split the line into columns
    //         $line = trim($line);
    //         $columns = array_map('trim', explode(',', $line));

    //         // Validate the line format
    //         if (count($columns) !== 4) {
    //             $errorMessages[] = "Invalid format on line $lineNumber: $line";
    //             continue;
    //         }

    //         [$name, $msisdn, $networkId, $isFavourite] = $columns;

    //         // Additional validation for `msisdn` and `networkId`
    //         if (!preg_match('/^\d+$/', $msisdn)) {
    //             $errorMessages[] = "Invalid MSISDN on line $lineNumber: $msisdn";
    //             continue;
    //         }

    //         if (!is_numeric($networkId)) {
    //             $errorMessages[] = "Invalid Network ID on line $lineNumber: $networkId";
    //             continue;
    //         }

    //         if (!in_array(strtolower($isFavourite), ['y', 'n'])) {
    //             $errorMessages[] = "Invalid is_favourite value on line $lineNumber: $isFavourite";
    //             continue;
    //         }

    //         // Check for duplicates in the database
    //         $existingEntry = DB::table('cp_users_addressbook')
    //             ->where('user_bigid', $userref)
    //             ->where('name', $name)
    //             ->join('itagg_mobiledetail', 'cp_users_addressbook.itagg_mobiledetail_id', '=', 'itagg_mobiledetail.id')
    //             ->where('itagg_mobiledetail.msisdn', $msisdn)
    //             ->exists();

    //         if ($existingEntry) {
    //             $errorMessages[] = "Duplicate entry found on line $lineNumber: MSISDN $msisdn and Name $name already exist.";
    //             continue;
    //         }

    //         // Save to `itagg_mobile_details` table
    //         $mobileDetail = new ItaggMobileDetail();
    //         $mobileDetail->msisdn = $msisdn;
    //         $mobileDetail->net_id = $networkId;
    //         $mobileDetail->confirmed = 0;
    //         $mobileDetail->lastchanged = Carbon::now('Europe/London')->format('YmdHis');
    //         $mobileDetail->mbloxDeliverer = null;
    //         $mobileDetail->save();

    //         // Save to `cp_users_addressbook` table
    //         DB::table('cp_users_addressbook')->insert([
    //             'name' => $name,
    //             'itagg_mobiledetail_id' => $mobileDetail->id,
    //             'user_bigid' => $userref,
    //             'is_favourite' => strtolower($isFavourite) === 'y' ? 'y' : 'n',
    //         ]);

    //         $successCount++;
    //     }

    //     // Prepare response messages
    //     $successMessage = $successCount > 0 ? "$successCount records processed successfully." : null;
    //     $errorMessage = !empty($errorMessages) ? nl2br(implode('<br/>', $errorMessages)) : null;

    //     return back()->with([
    //         'success' => $successMessage,
    //         'error' => $errorMessage,
    //     ]);

    //     // return nl2br(implode("<br/>", $results));
    // }


    public function downloadCsvReport()
    {
        $userInfo = Session::get('user_info');

        if (!isset($userInfo['bigid'])) {
            return redirect()->route('numbers.index')->with('error', 'User reference not found in session');
        }

        $userref = $userInfo['bigid'];

        $userData = CpUsersAddressbook::with([
            'mobileDetail.network'
        ])->where('user_bigid', $userref)->get();

        if ($userData->isEmpty()) {
            return redirect()->route('numbers.index')->with('error', 'No data available');
        }

        $fileName = 'numbers_report.csv';

        $headers = [
            "Content-Type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=\"$fileName\"",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0",
        ];

        $callback = function () use ($userData) {
            $file = fopen('php://output', 'w');

            // Add UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Add CSV header row
            fputcsv($file, ['S.No', 'Name', 'Mobile Number', 'Network', 'Favourite'], ",");

            $counter = 1;
            foreach ($userData as $user) {
                fputcsv($file, [
                    $counter++,
                    $user->name ?? '',
                    optional($user->mobileDetail)->msisdn,
                    // optional($user->mobileDetail->network)->Name ?? 'Unknown',
                    optional(optional($user->mobileDetail)->network)->Name ?? 'Unknown',
                    $user->is_favourite === 'y' ? 'Yes' : 'No',
                ], ",");
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }


    // public function downloadCsvReport()
    // {
    //     $userInfo = Session::get('user_info');

    //     if (!isset($userInfo['bigid'])) {
    //         return redirect()->route('numbers.index')->with('error', 'User reference not found in session');
    //     }

    //     $userref = $userInfo['bigid'];

    //     $userData = CpUsersAddressbook::with([
    //         'mobileDetail.network' // Load related mobile detail and network
    //     ])
    //         ->where('user_bigid', $userref)
    //         ->get();

    //     if ($userData->isEmpty()) {
    //         return redirect()->route('numbers.index')->with('error', 'No data available');
    //         // return response()->json(['message' => 'No data available for the report.'], 404);
    //     }

    //     $fileName = 'numbers_report.csv';

    //     $headers = [
    //         "Content-Type" => "text/csv",
    //         "Content-Disposition" => "attachment; filename=$fileName",
    //         "Pragma" => "no-cache",
    //         "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
    //         "Expires" => "0",
    //     ];

    //     $callback = function () use ($userData) {
    //         $file = fopen('php://output', 'w');

    //         // Add CSV header row
    //         fputcsv($file, ['S.No','Name', 'Mobile Number', 'Network', 'Favourite']); // Column headers
    //         $counter = 1;
    //         foreach ($userData as $user) {
    //             fputcsv($file, [
    //                 $counter++,
    //                 $user->name ?? '',
    //                 optional($user->mobileDetail)->msisdn,
    //                 optional(optional($user->mobileDetail)->network)->Name ?? 'Unknown',
    //                 // optional($user->mobileDetail->network)->Name ?? 'Unknown',
    //                 $user->is_favourite === 'y' ? 'Yes' : 'No',
    //             ]);
    //         }

    //         fclose($file);
    //     };

    //     return Response::stream($callback, 200, $headers);
    // }

    public function destroyAll()
    {
        $userref = session('user_info')['bigid'] ?? null;

        if ($userref) {
            DB::transaction(function () use ($userref) {
                $addressbookIds = DB::table('cp_users_addressbook')
                    ->where('user_bigid', $userref)
                    ->pluck('id');

                DB::table('cp_group_addressbook')
                    ->whereIn('addressbook_id', $addressbookIds)
                    ->delete();

                DB::table('cp_users_addressbook')
                    ->where('user_bigid', $userref)
                    ->delete();
            });

            return redirect()->route('numbers.index')->with('success', 'All contacts and groups have been deleted successfully.');
        } else {
            return redirect()->route('numbers.index')->with('error', 'User reference not found in session');
        }


        // if ($userref) {
        //     DB::table('cp_users_addressbook')
        //         ->where('user_bigid', $userref)
        //         ->delete();

        //     return redirect()->route('numbers.index')->with('success', 'All contacts and groups have been deleted successfully.');
        // } else {
        //     return redirect()->route('numbers.index')->with('error', 'User reference not found in session');
        // }
    }
}
