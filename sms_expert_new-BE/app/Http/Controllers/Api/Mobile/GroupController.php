<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mobile App Group Controller
 * 
 * Handles contact groups for the mobile application
 */
class GroupController extends Controller
{
    /**
     * Get all groups with member counts
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            $bigid = $user->bigid;
            
            Log::info('Mobile Groups - User bigid: ' . $bigid);

            // Search
            $search = $request->get('search');

            // Get groups with member counts
            $query = DB::table('cp_users_groups')
                ->leftJoin('cp_group_addressbook', 'cp_users_groups.id', '=', 'cp_group_addressbook.group_id')
                ->where('cp_users_groups.user_bigid', $bigid)
                ->select(
                    'cp_users_groups.id',
                    'cp_users_groups.name',
                    DB::raw('COUNT(cp_group_addressbook.addressbook_id) as member_count')
                )
                ->groupBy('cp_users_groups.id', 'cp_users_groups.name');

            if ($search) {
                $query->where('cp_users_groups.name', 'like', "%{$search}%");
            }

            $groups = $query->orderBy('cp_users_groups.name', 'asc')->get();

            // Calculate statistics
            $totalGroups = $groups->count();
            $totalMembers = $groups->sum('member_count');
            $activeGroups = $groups->where('member_count', '>', 0)->count();

            return response()->json([
                'status' => true,
                'message' => 'Groups retrieved successfully',
                'data' => [
                    'groups' => $groups,
                    'statistics' => [
                        'total_groups' => $totalGroups,
                        'total_members' => $totalMembers,
                        'active_groups' => $activeGroups,
                    ],
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Groups Error: ' . $ex->getMessage());
            Log::error('Mobile Groups Trace: ' . $ex->getTraceAsString());

            return response()->json([
                'status' => false,
                'message' => 'Failed to load groups',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Get single group with members
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;

            // Get group
            $group = DB::table('cp_users_groups')
                ->where('id', $id)
                ->where('user_bigid', $bigid)
                ->first();

            if (!$group) {
                return response()->json([
                    'status' => false,
                    'message' => 'Group not found'
                ], 404);
            }

            // Get members
            $members = DB::table('cp_group_addressbook')
                ->join('cp_users_addressbook', 'cp_group_addressbook.addressbook_id', '=', 'cp_users_addressbook.id')
                ->leftJoin('itagg_mobiledetail', 'cp_users_addressbook.itagg_mobiledetail_id', '=', 'itagg_mobiledetail.id')
                ->leftJoin('mobnetworks', 'itagg_mobiledetail.net_id', '=', 'mobnetworks.id')
                ->where('cp_group_addressbook.group_id', $id)
                ->select(
                    'cp_users_addressbook.id',
                    'cp_users_addressbook.name',
                    'itagg_mobiledetail.msisdn as phone',
                    'mobnetworks.Name as network',
                    'cp_users_addressbook.is_favourite'
                )
                ->get()
                ->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'phone' => $member->phone,
                        'network' => $member->network ?? 'Unknown',
                        'is_favourite' => $member->is_favourite === 'y',
                    ];
                });

            return response()->json([
                'status' => true,
                'message' => 'Group retrieved successfully',
                'data' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'member_count' => $members->count(),
                    'members' => $members,
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Group Show Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to load group',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Create new group
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $bigid = $user->bigid;

            $name = trim($request->name);

            // Check for duplicate group name
            $existing = DB::table('cp_users_groups')
                ->where('user_bigid', $bigid)
                ->where('name', $name)
                ->exists();

            if ($existing) {
                return response()->json([
                    'status' => false,
                    'message' => 'A group with this name already exists',
                    'errors' => [
                        'name' => ['A group with this name already exists.']
                    ]
                ], 422);
            }

            // Create group
            $groupId = DB::table('cp_users_groups')->insertGetId([
                'name' => $name,
                'user_bigid' => $bigid,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Group created successfully',
                'data' => [
                    'id' => $groupId,
                    'name' => $name,
                    'member_count' => 0,
                ],
            ], 201);

        } catch (\Throwable $ex) {
            Log::error('Mobile Group Store Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to create group',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Update group
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $bigid = $user->bigid;

            // Check group exists
            $group = DB::table('cp_users_groups')
                ->where('id', $id)
                ->where('user_bigid', $bigid)
                ->first();

            if (!$group) {
                return response()->json([
                    'status' => false,
                    'message' => 'Group not found'
                ], 404);
            }

            $name = trim($request->name);

            // Check for duplicate group name (excluding current)
            $existing = DB::table('cp_users_groups')
                ->where('user_bigid', $bigid)
                ->where('name', $name)
                ->where('id', '!=', $id)
                ->exists();

            if ($existing) {
                return response()->json([
                    'status' => false,
                    'message' => 'A group with this name already exists',
                    'errors' => [
                        'name' => ['A group with this name already exists.']
                    ]
                ], 422);
            }

            // Update group
            DB::table('cp_users_groups')
                ->where('id', $id)
                ->update(['name' => $name]);

            // Get updated member count
            $memberCount = DB::table('cp_group_addressbook')
                ->where('group_id', $id)
                ->count();

            return response()->json([
                'status' => true,
                'message' => 'Group updated successfully',
                'data' => [
                    'id' => $id,
                    'name' => $name,
                    'member_count' => $memberCount,
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Group Update Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to update group',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Delete group
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;

            // Check group exists
            $group = DB::table('cp_users_groups')
                ->where('id', $id)
                ->where('user_bigid', $bigid)
                ->first();

            if (!$group) {
                return response()->json([
                    'status' => false,
                    'message' => 'Group not found'
                ], 404);
            }

            // Delete group members first
            DB::table('cp_group_addressbook')
                ->where('group_id', $id)
                ->delete();

            // Delete group
            DB::table('cp_users_groups')
                ->where('id', $id)
                ->delete();

            return response()->json([
                'status' => true,
                'message' => 'Group deleted successfully'
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Group Delete Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete group',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Add member to group
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function addMember(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'contact_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $bigid = $user->bigid;

            // Check group exists
            $group = DB::table('cp_users_groups')
                ->where('id', $id)
                ->where('user_bigid', $bigid)
                ->first();

            if (!$group) {
                return response()->json([
                    'status' => false,
                    'message' => 'Group not found'
                ], 404);
            }

            // Check contact exists and belongs to user
            $contact = DB::table('cp_users_addressbook')
                ->where('id', $request->contact_id)
                ->where('user_bigid', $bigid)
                ->first();

            if (!$contact) {
                return response()->json([
                    'status' => false,
                    'message' => 'Contact not found'
                ], 404);
            }

            // Check if already a member
            $exists = DB::table('cp_group_addressbook')
                ->where('group_id', $id)
                ->where('addressbook_id', $request->contact_id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => false,
                    'message' => 'Contact is already a member of this group'
                ], 422);
            }

            // Add member
            DB::table('cp_group_addressbook')->insert([
                'group_id' => $id,
                'addressbook_id' => $request->contact_id,
            ]);

            // Get updated member count
            $memberCount = DB::table('cp_group_addressbook')
                ->where('group_id', $id)
                ->count();

            return response()->json([
                'status' => true,
                'message' => 'Member added successfully',
                'data' => [
                    'group_id' => $id,
                    'member_count' => $memberCount,
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Group Add Member Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to add member',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Remove member from group
     * 
     * @param Request $request
     * @param int $id
     * @param int $contactId
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeMember(Request $request, $id, $contactId)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;

            // Check group exists
            $group = DB::table('cp_users_groups')
                ->where('id', $id)
                ->where('user_bigid', $bigid)
                ->first();

            if (!$group) {
                return response()->json([
                    'status' => false,
                    'message' => 'Group not found'
                ], 404);
            }

            // Remove member
            $deleted = DB::table('cp_group_addressbook')
                ->where('group_id', $id)
                ->where('addressbook_id', $contactId)
                ->delete();

            if (!$deleted) {
                return response()->json([
                    'status' => false,
                    'message' => 'Member not found in group'
                ], 404);
            }

            // Get updated member count
            $memberCount = DB::table('cp_group_addressbook')
                ->where('group_id', $id)
                ->count();

            return response()->json([
                'status' => true,
                'message' => 'Member removed successfully',
                'data' => [
                    'group_id' => $id,
                    'member_count' => $memberCount,
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Group Remove Member Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to remove member',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Get available contacts to add to group
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableContacts(Request $request, $id)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;

            // Check group exists
            $group = DB::table('cp_users_groups')
                ->where('id', $id)
                ->where('user_bigid', $bigid)
                ->first();

            if (!$group) {
                return response()->json([
                    'status' => false,
                    'message' => 'Group not found'
                ], 404);
            }

            // Get contacts not in this group
            $existingMemberIds = DB::table('cp_group_addressbook')
                ->where('group_id', $id)
                ->pluck('addressbook_id')
                ->toArray();

            $contacts = DB::table('cp_users_addressbook')
                ->leftJoin('itagg_mobiledetail', 'cp_users_addressbook.itagg_mobiledetail_id', '=', 'itagg_mobiledetail.id')
                ->leftJoin('mobnetworks', 'itagg_mobiledetail.net_id', '=', 'mobnetworks.id')
                ->where('cp_users_addressbook.user_bigid', $bigid)
                ->whereNotIn('cp_users_addressbook.id', $existingMemberIds)
                ->select(
                    'cp_users_addressbook.id',
                    'cp_users_addressbook.name',
                    'itagg_mobiledetail.msisdn as phone',
                    'mobnetworks.Name as network',
                    'cp_users_addressbook.is_favourite'
                )
                ->orderBy('cp_users_addressbook.name', 'asc')
                ->get()
                ->map(function ($contact) {
                    return [
                        'id' => $contact->id,
                        'name' => $contact->name,
                        'phone' => $contact->phone,
                        'network' => $contact->network ?? 'Unknown',
                        'is_favourite' => $contact->is_favourite === 'y',
                    ];
                });

            return response()->json([
                'status' => true,
                'message' => 'Available contacts retrieved successfully',
                'data' => $contacts,
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Group Available Contacts Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to load available contacts',
                'error' => $ex->getMessage()
            ], 500);
        }
    }
}
