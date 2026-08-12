<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CpUsersAddressBook;
use App\Models\CpUsersGroup;
use Illuminate\Support\Facades\Session;

class AvailableGroupsController extends Controller
{

    public function getAvailableContacts(Request $request)
    {
        $userInfo = $request->session()->get('user_info');
        $userId = $userInfo['bigid'] ?? null;

        if (!$userId) {
            return response()->json(['error' => 'User not authenticated.'], 401);
        }

        $listType = $request->input('list_type');
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 50);
        $search = $request->input('search', '');
        $contacts = [];

        if ($listType === 'favourites') {
            // Build query for favourites
            $query = CpUsersAddressBook::where('user_bigid', $userId)
                ->where('is_favourite', 'Y')
                ->with('mobileDetail:id,msisdn');

            // Apply search filter
            if (!empty($search)) {
                $query->where('name', 'like', "%{$search}%");
            }

            // Get total count for pagination
            $total = $query->count();

            // Apply pagination
            $favourites = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get(['id', 'itagg_mobiledetail_id', 'name'])
                ->map(function ($contact) {
                    return [
                        'id' => $contact->id,
                        'msisdn' => $contact->mobileDetail->msisdn ?? null,
                        'name' => $contact->name,
                    ];
                });

            return response()->json([
                'data' => $favourites,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage),
                    'has_prev' => $page > 1,
                    'has_next' => ($page * $perPage) < $total,
                ]
            ]);
        }

        if ($listType === 'groups') {
            // Build query for groups
            $query = CpUsersGroup::where('user_bigid', $userId);

            // Apply search filter
            if (!empty($search)) {
                $query->where('name', 'like', "%{$search}%");
            }

            // Get total count for pagination
            $total = $query->count();

            // Fetch the groups with pagination
            $groups = $query->with([
                    'groupAddressBooks.addressBook.mobileDetail:id,msisdn',
                ])
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get(['id', 'name']);

            // Transform the data
            $contacts = $groups->map(function ($group) {
                $mobileNumbers = $group->groupAddressBooks->map(function ($groupAddressBook) {
                    return $groupAddressBook->addressBook->mobileDetail->msisdn ?? null;
                })->filter();

                return [
                    'name' => $group->name,
                    'msisdn' => $mobileNumbers->toArray(),
                ];
            });

            return response()->json([
                'data' => $contacts,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage),
                    'has_prev' => $page > 1,
                    'has_next' => ($page * $perPage) < $total,
                ]
            ]);
        }

        return response()->json([
            'data' => [],
            'pagination' => [
                'current_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'total_pages' => 0,
                'has_prev' => false,
                'has_next' => false,
            ]
        ]);
    }

    // public function getAvailableContacts(Request $request)
    // {
    //     $userInfo = Session::get('user_info');
    //     if (isset($userInfo['bigid'])) {
    //         $userId = $userInfo['bigid'];
    //     }

    //     if (!$userId) {
    //         return response()->json(['error' => 'User not authenticated.'], 401);
    //     }

    //     // Determine list type and fetch data from the appropriate model
    //     $listType = $request->input('list_type');
    //     $contacts = [];

    //     if ($listType === 'favourites') {
    //         $contacts = CpUsersAddressBook::where('user_bigid', $userId)
    //             ->where('is_favourite', 'Y')
    //             ->get(['id', 'name']);
    //     } elseif ($listType === 'groups') {
    //         $contacts = CpUsersGroup::where('user_bigid', $userId)
    //             ->get(['id', 'name']);
    //     }

    //     if (empty($contacts)) {
    //         return response()->json(['message' => 'No contacts found.'], 404);
    //     }

    //     return response()->json($contacts);
    // }
}
