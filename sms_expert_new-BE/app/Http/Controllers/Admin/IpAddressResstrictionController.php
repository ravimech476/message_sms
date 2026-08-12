<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\IpAddressResstriction;
use Carbon\Carbon;
use App\Models\User;


class IpAddressResstrictionController extends Controller
{

    public function ipStore(Request $request)
    {
        $request->validate([
            'ip_address' => 'required|ip',
            'bigid' => 'required'
        ]);

        $ip = $request->ip_address;
        $bigid = $request->bigid;
        $id = $request->record_id;

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid IP address format.'
            ], 422);
        }

        $exists = IpAddressResstriction::where('ip_address', $ip)
            ->where('bigid', $bigid)
            ->where('status', 1)
            ->when($id, function ($query, $id) use ($ip) {
                $existing = IpAddressResstriction::find($id);
                if ($existing && $existing->ip_address === $ip) {
                    return $query->whereRaw('0=1');
                }
                return $query->where('id', '!=', $id);
            })
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'IP Address already exists.'
            ], 409);
        }

        $timestamp = Carbon::now('Europe/London')->format('YmdHis');


        if ($id) {
            // Update
            IpAddressResstriction::where('id', $id)->update([
                'ip_address'  => $ip,
                'modified_by' => 'DevTeam',
                'modified'    => $timestamp,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'IP Address has been updated successfully.'
            ]);
        } else {
            // Create
            IpAddressResstriction::create([
                'ip_address'     => $ip,
                'created_by'     => 'DevTeam',
                'created_by_ip'  => $request->ip(),
                'created'        => $timestamp,
                'status'         => 1,
                'bigid'          => $bigid
            ]);

            return response()->json([
                'success' => true,
                'message' => 'IP Address has been added successfully.'
            ]);
        }
    }

    // public function ipStore(Request $request)
    // {
    //     // Laravel-level validation
    //     $request->validate([
    //         'ip_address' => ['required'],
    //         'bigid' => ['required'],
    //     ]);

    //     $ip = $request->ip_address;
    //     $bigid = $request->bigid;

    //     // Manual IP format validation
    //     if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    //         return redirect()->back()->with('error', 'Invalid IP address format.');
    //     }

    //     // Check for duplicate IP address for same bigid
    //     $exists = IpAddressResstriction::where('ip_address', $ip)
    //         ->where('bigid', $bigid)
    //         ->where('status', 1)
    //         ->exists();

    //     if ($exists) {
    //         return redirect()->back()->with('error', 'IP Address already exists.');
    //     }

    //     // UK timestamp
    //     $ukTimestamp = Carbon::now('Europe/London')->format('YmdHis');

    //     // Insert new IP record
    //     IpAddressResstriction::create([
    //         'ip_address'     => $ip,
    //         'created_by'     => 'DevTeam',
    //         'created_by_ip'  => $request->ip(),
    //         'created'        => $ukTimestamp,
    //         'status'         => 1,
    //         'bigid'          => $bigid
    //     ]);

    //     return redirect()->back()->with('success', 'IP Address has been added successfully.');
    // }

    public function ipDestroy($id)
    {
        $ip = IpAddressResstriction::findOrFail($id);

        $ip->update([
            'status' => 2,
            'modified' => Carbon::now('Europe/London')->format('YmdHis'),
            'modified_by' => '0',
        ]);

        return redirect()->back()->with('success', 'IP Address has been removed successfully.');
    }

    public function ipAddressRestriction(Request $request)
    {

        $user = User::where('bigid', $request->userbigid)->first();

        if (!$user) {
            return redirect()
            ->back()
            ->with('error_profile', 'User not found!')
            ->with('scroll_target', 'profile-section');
        }


        $user->ip_address_restriction = $request->ip_address_restriction;


        $user->save();

        // return redirect()->back()->with('success', 'Status Updated.');
        return redirect()
        ->back()
        ->with('success_profile', 'Status updated successfully!')
        ->with('scroll_target', 'profile-section');
    }
}
