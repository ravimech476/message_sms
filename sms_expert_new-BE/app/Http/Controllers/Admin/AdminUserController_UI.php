<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ItaggInstance;
use App\Models\SmsShortcode;
use Illuminate\Support\Facades\Session;
use App\Models\UserNote;
use App\Models\UserOption;
use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;
use App\Models\IpAddressResstriction;
use App\Models\BlockedUser;
use App\Models\UsersSessionLog;




class AdminUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $userInfo = Session::get('user_info');

        if (isset($userInfo['bigid'])) {
            $userref = $userInfo['bigid'];

            // Get paginated users for modern dashboard
            $users = User::where(function ($query) {
                $query->whereNull('login_type')
                    ->orWhere('login_type', 'customer');
            })
                ->where('bit_disabled', 0)
                ->orderBy('created_at', 'desc')
                ->paginate(15);
                
            // Additional statistics for modern dashboard
            $totalUsers = User::where(function ($query) {
                $query->whereNull('login_type')
                    ->orWhere('login_type', 'customer');
            })->where('bit_disabled', 0)->count();
            
            $activeUsers = User::where(function ($query) {
                $query->whereNull('login_type')
                    ->orWhere('login_type', 'customer');
            })->where('bit_disabled', 0)
              ->where('status', 'active')
              ->count();
              
            $newUsersThisMonth = User::where(function ($query) {
                $query->whereNull('login_type')
                    ->orWhere('login_type', 'customer');
            })->where('bit_disabled', 0)
              ->whereMonth('created_at', now()->month)
              ->whereYear('created_at', now()->year)
              ->count();
              
            $blockedUsers = BlockedUser::where('status', 0)->count();
            
            // For backward compatibility, also include userData
            $userData = $users->items();
        }

        return view('admin.users.modern-index', compact(
            'users', 
            'userData', 
            'totalUsers', 
            'activeUsers', 
            'newUsersThisMonth', 
            'blockedUsers'
        ));
    }

    public function show(Request $request, $id)
    {

        $record = User::findOrFail($id);

        $keywords = ItaggInstance::where('users_bigid', $record->bigid)
            ->where('status', 1)
            ->get();

        $shortcodes = SmsShortcode::all();


        $client_notes = UserNote::where('users_bigid', $record->bigid)
            ->paginate(10);

            $invoices = Invoice::select('id', 'userref', 'paiddate', 'easilyamount', 'invoicedate')
            ->with(['orderItems' => function ($query) {
                $query->select('id', 'invoiceref', 'status')
                      ->where('status', 'order');
            }])
            ->where('userref', $record->bigid)
            ->where('paiddate', 0)
            ->whereHas('orderItems', function ($query) {
                $query->where('status', 'order');
            })
            ->orderBy('id', 'desc')
            ->paginate(10);

        $user_options = UserOption::where('userref', $record->bigid)->first();
        
        $ipAddresses = IpAddressResstriction::where('status', 1)
                    ->where('bigid', $record->bigid)
                    ->orderBy('created', 'desc')
                    ->get();

        $blockedUsers = BlockedUser::where('status', 0)
                    ->where('big_id', $record->bigid)
                    ->orderByDesc('id')
                    ->get();
                    
        $usersSessionLogs = UsersSessionLog::where('status', 0)
                    ->where('big_id', $record->bigid)
                    ->orderByDesc('id')
                    ->get();            

        return response()->view('admin.user.show', compact('record', 'keywords', 'shortcodes', 'client_notes', 'invoices','user_options','ipAddresses','blockedUsers','usersSessionLogs'))
            ->header('X-Record-ID', $id);
    }
    
    /**
     * Get user dashboard data for AJAX
     */
    public function getUserDashboard($id)
    {
        $user = User::findOrFail($id);
        
        // Get user statistics
        $smsStats = [
            'total_sent' => ($user->smsg_server1_sent ?? 0) + ($user->smsg_server2_sent ?? 0),
            'wallet_balance' => $user->smsg_wallet ?? 0,
            'total_spent' => 0, // Calculate from transactions
            'last_active' => $user->last_login ?? 'Never'
        ];
        
        return response()->json([
            'name' => $user->contactname,
            'email' => $user->email,
            'status' => $user->status ?? 'active',
            'stats' => $smsStats
        ]);
    }
    
    /**
     * Toggle user status
     */
    public function toggleStatus($id)
    {
        try {
            $user = User::findOrFail($id);
            $currentStatus = $user->status ?? 'active';
            $newStatus = $currentStatus === 'active' ? 'suspended' : 'active';
            
            $user->status = $newStatus;
            $user->save();
            
            return response()->json([
                'success' => true,
                'message' => 'User status updated successfully',
                'new_status' => $newStatus
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status'
            ], 500);
        }
    }
    
    /**
     * Export users to CSV
     */
    public function export()
    {
        $users = User::where(function ($query) {
            $query->whereNull('login_type')
                ->orWhere('login_type', 'customer');
        })->where('bit_disabled', 0)->get();
        
        $filename = 'users_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        $callback = function() use ($users) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'ID', 'Name', 'Email', 'Phone', 'Account Type', 
                'SMS Sent', 'Wallet Balance', 'Status', 'Created At'
            ]);
            
            // CSV data
            foreach ($users as $user) {
                fputcsv($file, [
                    $user->bigid,
                    $user->contactname,
                    $user->email,
                    $user->telephone,
                    $user->payment_type ?? 'prepaid',
                    ($user->smsg_server1_sent ?? 0) + ($user->smsg_server2_sent ?? 0),
                    $user->smsg_wallet ?? 0,
                    $user->status ?? 'active',
                    $user->created_at
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);


    }
}
