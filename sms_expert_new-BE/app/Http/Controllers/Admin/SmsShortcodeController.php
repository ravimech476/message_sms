<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SmsShortcode;
use Carbon\Carbon;
use App\Models\ItaggInstance;
use App\Models\User;


class SmsShortcodeController extends Controller
{
    public function index()
    {
        $shortcodes = SmsShortcode::all();

        // Store active tab in session
        session()->flash('activeTab', 'customer-virtual-number');

        return view('admin.shortcode.index', compact('shortcodes'));
    }

    public function createShortCode($userid)
    {
        return view('admin.shortcode.create', compact('userid'));
    }

    public function store(Request $request)
    {
        // Validate input
        // $request->validate([
        //     'vnumber' => 'required|unique:smsshortcodes,number',
        // ]);

        $record = new SmsShortcode();
        $record->number = $request->vnumber;
        $record->orderdate = $record->orderdate = Carbon::now()->format('YmdHis');
        $record->whichoperator = $request->userid ?? '';
        $record->save();

        session()->flash('activeTab', 'customer-virtual-number');

        return redirect()->route('admin.user.show', ['id' => $request->userid])
            ->with('success', 'Virtual number created successfully.');
    }


    public function editShortCode(Request $request, $id)
    {
        $shortcode = SmsShortcode::findOrFail($id);
        $userid = $request->query('userid');

        // Store active tab in session
        session()->flash('activeTab', 'customer-virtual-number');

        return view('admin.shortcode.show', compact('shortcode', 'userid'));
    }

    public function updateShortCode(Request $request, $id)
    {
        $shortcode = SmsShortcode::findOrFail($id);

        $shortcode->number = $request->vnumber;
        $shortcode->save();

        // Store active tab in session
        session()->flash('activeTab', 'customer-virtual-number');

        return redirect()->route('admin.user.show', ['id' => $request->userid])
            ->with('success', 'Virtual number updated successfully.');
    }


    public function destroyShortCode($id)
    {

        SmsShortcode::destroy($id);

        // Store active tab in session
        session()->flash('activeTab', 'customer-virtual-number');

        return redirect()->back()->with('success', 'Virtual number deleted successfully.');
    }

    public function mappingKeyword($virtual_id, $userid)
    {
        $user = User::findOrFail($userid);

        $keywords = ItaggInstance::where('users_bigid', $user->bigid)->where('status',1)
            ->pluck('keyword', 'id');

        return view('admin.shortcode.map', compact('keywords', 'virtual_id', 'userid'));
    }

    public function mappingKeywordUpdate(Request $request, $virtual_id, $userid)
    {
        // $request->validate([
        //     'keyword' => 'required|exists:itagg_instance,id',
        //     'smsshortcodes_id' => 'required|integer',
        // ]);

        $users_bigid = User::findOrFail($userid);

        // Find the record by ID
        $itaggInstance = ItaggInstance::where('id', $request->keyword)
            ->where('users_bigid', $users_bigid->bigid)
            ->first();

        if ($itaggInstance) {
            $itaggInstance->smsshortcodes_id = $virtual_id;
            $itaggInstance->save();

            // Store active tab in session
            session()->flash('activeTab', 'customer-virtual-number');

            return redirect()->route('admin.user.show', ['id' => $userid])
                ->with('success', 'Keyword mapped successfully.');
        }

        return redirect()->back()->with('error', 'Record not found.');
    }
}
