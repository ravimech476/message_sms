<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Models\UserOption;

class StopCommandController extends Controller
{
    public function index()
    {
        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $bigid = Session::get('user_info')['bigid'];
            $userOption = UserOption::where('userref', $bigid)->first();

            return view('customer.stop_command.index', compact('userOption'));
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
    }

    public function update(Request $request)
    {
        $validatedData = $request->validate([
            'txtURL'   => 'nullable|string|max:200',
            'txtEmail' => 'nullable|email|max:50',
            'txtName'  => 'nullable|string|max:50',
        ]);

        $userref = Session::get('user_info')['bigid'];

        UserOption::updateOrCreate(
            ['userref' => $userref],
            [
                'stop_command_url'       => $validatedData['txtURL'],
                'stopcommand_contactemail' => $validatedData['txtEmail'],
                'stopcommand_contactname'  => $validatedData['txtName'],
            ]
        );

        return redirect()->back()->with('success', 'Command updated successfully');
    }
}
