<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserReminder;
use App\Models\UserOption;
use Illuminate\Support\Facades\Session;

class UserController extends Controller
{
    public function update(Request $request)
    {

        $request->validate([
            'reminderon' => 'required',
            'numonremind' => 'nullable|numeric',
            'reminderperiod' => 'required|integer',
            'immediateEmailReminderon' => 'required',
        ]);
    
       $bigid = Session::get('user_info')['bigid'];

        if (isset($bigid)) {
            $reminder = UserReminder::where('usersbigidref', $bigid)->first();
            // echo "<pre>";print_r($reminder);exit;
            if ($reminder) {
                $reminder->reminderon = $request->reminderon;
                if(empty($request->numonremind)){
                 $reminder->numonremind = 0.00;
                }
                else{
                $reminder->numonremind =  $request->numonremind;
                }
                $reminder->reminderperiod = $request->reminderperiod;
                $reminder->save();
            } else {
                return redirect()->back()->withErrors('Reminder not found.');
            }

            $option = UserOption::where('userref', $bigid)->first();
            // echo "<pre>";print_r($request->all());exit;
            if ($option) {
                $option->immediateEmailReminderon = $request->immediateEmailReminderon;
                if(empty($request->immediateOutOfFundsNotificationEmail)){
                    $option->immediateOutOfFundsNotificationEmail = '' ;
                }
                else{
                    $option->immediateOutOfFundsNotificationEmail = $request->immediateOutOfFundsNotificationEmail;
                }
                $option->save();
            } else {
                return redirect()->back()->withErrors('Option not found.');
            }
        
            session()->flash('success', 'Settings updated successfully!');
            return redirect()->route('sms_wallet.index');
            
        } else {
          return redirect('/');
        }

        
    }
}

