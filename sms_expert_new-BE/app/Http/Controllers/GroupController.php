<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CpUsersGroup;
use Illuminate\Support\Facades\Session;
use App\Models\CpUsersAddressBook;
use App\Models\CpGroupAddressBook;


class GroupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $userref = $userInfo['bigid'];
        }

        $groups = CpUsersGroup::where('user_bigid', $userref)->get();

        $groupCounts = [];
        foreach ($groups as $group) {
            $groupCounts[$group->id] = CpGroupAddressbook::where('group_id', $group->id)->count();
        }


        return view('customer.group.index', compact('groups', 'groupCounts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('customer.group.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'group_name' => 'required|string|max:255',
        ]);

        $group = new CpUsersGroup();

        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $userref = $userInfo['bigid'];
        }

        $group->name = $request->group_name;
        $group->user_bigid =  $userref;


        $group->save();

        return redirect()->route('groups.index')->with('success', 'Group created successfully!');
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
        $group = CpUsersGroup::findOrFail($id);

        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $userref = $userInfo['bigid'];
        }

        $addressBook = CpUsersAddressBook::with('mobileDetail')
            ->where('user_bigid', $userref)
            ->paginate(10);

        $inGroupIds = CpGroupAddressbook::where('group_id', $group->id)
            ->pluck('addressbook_id')
            ->toArray();

        return view('customer.group.edit', compact('group', 'addressBook', 'inGroupIds'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'group_name' => 'required|string|max:255',
        ]);

        $group = CpUsersGroup::findOrFail($id);
        $group->update([
            'name' => $request->group_name,
        ]);

        return redirect()->back()->with('success', 'Group name updated successfully');


        // return redirect()->route('groups.index')->with('success', 'Group updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {

        $group = CpUsersGroup::findOrFail($id);

        $group->delete();

        return redirect()->route('groups.index')->with('success', 'Group deleted successfully!');
    }


    public function single_group(Request $request, string $id)
    {
        $group = CpUsersGroup::findOrFail($id);


        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $userref = $userInfo['bigid'];
        }

        $addressBook = CpUsersAddressBook::with('mobileDetail')
            ->where('user_bigid', $userref)
            ->paginate(10);

        $inGroupIds = CpGroupAddressbook::where('group_id', $group->id)
            ->pluck('addressbook_id')
            ->toArray();

        // \Log::info('In Group IDs:', $inGroupIds);

        return view('customer.group.add_mobile_number', compact('group', 'addressBook', 'inGroupIds'));
        // return view('customer.group.add_mobile_number', ['group' => $group,'addressBook' => $addressBook]);
    }

    public function saveAddressBook(Request $request)
    {
        $groupId = $request->group_id;

        $submittedIds = [];
        foreach ($request->all() as $key => $value) {
            if (str_starts_with($key, 'row_id_')) {
                $addressBookId = str_replace('row_id_', '', $key);
                $submittedIds[] = $addressBookId;
            }
        }

        // Separate IDs that are checked and unchecked
        $checkedIds = [];
        foreach ($request->all() as $key => $value) {
            if (str_starts_with($key, 'check_') && $value === 'yes') {
                $checkedIds[] = str_replace('check_', '', $key);
            }
        }

        $changesMade = false; // track if anything was inserted/removed

        // Insert or keep only the checked IDs in the group
        foreach ($checkedIds as $addressBookId) {
            $exists = CpGroupAddressBook::where('group_id', $groupId)
                ->where('addressbook_id', $addressBookId)
                ->exists();

            if (!$exists) {
                CpGroupAddressBook::create([
                    'group_id'       => $groupId,
                    'addressbook_id' => $addressBookId,
                ]);
                $changesMade = true;
            }
        }

        // Remove any unchecked IDs from the group
        $uncheckedIds = array_diff($submittedIds, $checkedIds);
        if (!empty($uncheckedIds)) {
            $deleted = CpGroupAddressBook::where('group_id', $groupId)
                ->whereIn('addressbook_id', $uncheckedIds)
                ->delete();

            if ($deleted > 0) {
                $changesMade = true;
            }
        }

        if ($changesMade) {
            return redirect()->back()->with('success', 'Successfully updated this group');
        } else {
            return redirect()->back()->with('info', 'No changes were made');
        }
    }


    public function updateAddressBook(Request $request)
    {
        $groupId = $request->group_id;

        $submittedIds = [];
        foreach ($request->all() as $key => $value) {
            if (str_starts_with($key, 'row_id_')) {
                $addressBookId = str_replace('row_id_', '', $key);
                $submittedIds[] = $addressBookId;
            }
        }

        // Separate IDs that are checked and unchecked
        $checkedIds = [];
        foreach ($request->all() as $key => $value) {
            if (str_starts_with($key, 'check_') && $value === 'yes') {
                $checkedIds[] = str_replace('check_', '', $key);
            }
        }

        $changesMade = false; // track if anything was inserted/removed

        // Insert or keep only the checked IDs in the group
        foreach ($checkedIds as $addressBookId) {
            $exists = CpGroupAddressBook::where('group_id', $groupId)
                ->where('addressbook_id', $addressBookId)
                ->exists();

            if (!$exists) {
                CpGroupAddressBook::create([
                    'group_id'       => $groupId,
                    'addressbook_id' => $addressBookId,
                ]);
                $changesMade = true;
            }
        }

        // Remove any unchecked IDs from the group
        $uncheckedIds = array_diff($submittedIds, $checkedIds);
        if (!empty($uncheckedIds)) {
            $deleted = CpGroupAddressBook::where('group_id', $groupId)
                ->whereIn('addressbook_id', $uncheckedIds)
                ->delete();

            if ($deleted > 0) {
                $changesMade = true;
            }
        }

        if ($changesMade) {
            return redirect()->back()->with('success', 'Group number updated successfully');
        } else {
            return redirect()->back()->with('info', 'No changes were made');
        }
    }



    // public function saveAddressBook(Request $request)
    // {
    //     $groupId = $request->group_id;

    //     $submittedIds = [];
    //     foreach ($request->all() as $key => $value) {
    //         if (str_starts_with($key, 'row_id_')) {
    //             $addressBookId = str_replace('row_id_', '', $key);
    //             $submittedIds[] = $addressBookId;
    //         }
    //     }

    //     // Separate IDs that are checked and unchecked
    //     $checkedIds = [];
    //     foreach ($request->all() as $key => $value) {
    //         if (str_starts_with($key, 'check_') && $value === 'yes') {
    //             $checkedIds[] = str_replace('check_', '', $key);
    //         }
    //     }

    //     // Insert or keep only the checked IDs in the group
    //     foreach ($checkedIds as $addressBookId) {
    //         CpGroupAddressBook::firstOrCreate([
    //             'group_id' => $groupId,
    //             'addressbook_id' => $addressBookId,
    //         ]);
    //     }

    //     // Remove any unchecked IDs from the group
    //     $uncheckedIds = array_diff($submittedIds, $checkedIds);
    //     if (!empty($uncheckedIds)) {
    //         CpGroupAddressBook::where('group_id', $groupId)
    //             ->whereIn('addressbook_id', $uncheckedIds)
    //             ->delete();
    //     }

    //     return redirect()->back()->with('success', 'Successfully updated this group');
    // }



    // public function saveAddressBook(Request $request)
    // {

    //     foreach ($request->all() as $key => $value) {
    //         if (str_starts_with($key, 'check_') && $value === 'yes') {
    //             $addressBookId = str_replace('check_', '', $key);

    //             CpGroupAddressBook::firstOrCreate([
    //                 'group_id' => $request->group_id,
    //                 'addressbook_id' => $addressBookId,
    //             ]);
    //         }
    //     }

    //     return redirect()->back()->with('success', 'Successfully updated this group');
    // }
}
