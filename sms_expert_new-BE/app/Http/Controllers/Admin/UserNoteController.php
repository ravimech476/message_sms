<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserNote;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Facades\Session;



class UserNoteController extends Controller
{
    public function createClientNote($userid)
    {
        return view('admin.client_note.create', compact('userid'));
    }

    public function store(Request $request)
    {
       
        $user = User::findOrFail($request->userid);

        $userInfo = Session::get('user_info');

        $record = new UserNote();
        $record->users_bigid = $user->bigid;
        $record->staffinitials = $userInfo['contactname'];
        $record->nextcontactdate = $request->newnotenextcontactdate ? Carbon::parse($request->newnotenextcontactdate)->format('YmdHis') : '';    
        $record->notes = $request->notes;
        $record->timelength = $request->newnotetimelength ?? '';
        $record->insertdate = Carbon::now('Europe/London');
        $record->settonousrprenfc = $request->newnotenextcontacttime ?? '';
        $record->myinsertdate = Carbon::now('Europe/London')->format('YmdHis');
        $record->save();


        session()->flash('activeTab', 'customer-client-note');

        return redirect()->route('admin.user.show', ['id' => $request->userid])
            ->with('success', 'Client note created successfully.');
    }

    public function destroyClientNote($id)
    {

        UserNote::destroy($id);

        // Store active tab in session
        session()->flash('activeTab', 'customer-client-note');

        return redirect()->back()->with('success', 'Client note deleted successfully.');
    }
}
