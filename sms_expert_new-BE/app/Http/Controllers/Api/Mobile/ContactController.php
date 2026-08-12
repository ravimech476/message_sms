<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * Mobile App Contact Controller
 * 
 * Handles contacts/address book for the mobile application
 * Uses the same database structure as the web app (cp_users_addressbook + itagg_mobiledetail)
 */
class ContactController extends Controller
{
    /**
     * Get all contacts
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;

            // Pagination
            $page = (int) $request->get('page', 1);
            $perPage = min((int) $request->get('per_page', 50), 100);
            $offset = ($page - 1) * $perPage;

            // Search
            $search = $request->get('search');

            // Build query using the same tables as web app
            // Table names: cp_users_addressbook, itagg_mobiledetail, mobnetworks
            $query = DB::table('cp_users_addressbook')
                ->leftJoin('itagg_mobiledetail', 'cp_users_addressbook.itagg_mobiledetail_id', '=', 'itagg_mobiledetail.id')
                ->leftJoin('mobnetworks', 'itagg_mobiledetail.net_id', '=', 'mobnetworks.id')
                ->where('cp_users_addressbook.user_bigid', $bigid)
                ->select(
                    'cp_users_addressbook.id',
                    'cp_users_addressbook.name',
                    'itagg_mobiledetail.msisdn as phone',
                    'mobnetworks.Name as network',
                    'cp_users_addressbook.is_favourite'
                );

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('cp_users_addressbook.name', 'like', "%{$search}%")
                      ->orWhere('itagg_mobiledetail.msisdn', 'like', "%{$search}%");
                });
            }

            // Get total count
            $total = $query->count();

            // Get paginated results
            $contacts = $query
                ->orderBy('cp_users_addressbook.name', 'asc')
                ->offset($offset)
                ->limit($perPage)
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

            $totalPages = $total > 0 ? ceil($total / $perPage) : 1;

            return response()->json([
                'status' => true,
                'message' => 'Contacts retrieved successfully',
                'data' => [
                    'items' => $contacts,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                        'total_pages' => $totalPages,
                        'has_more' => $page < $totalPages,
                    ],
                ],
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Contacts Error: ' . $ex->getMessage() . ' | File: ' . $ex->getFile() . ' | Line: ' . $ex->getLine());

            return response()->json([
                'status' => false,
                'message' => 'Failed to load contacts',
                'debug' => config('app.debug') ? $ex->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get single contact
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

            $contact = DB::table('cp_users_addressbook')
                ->leftJoin('itagg_mobiledetail', 'cp_users_addressbook.itagg_mobiledetail_id', '=', 'itagg_mobiledetail.id')
                ->leftJoin('mobnetworks', 'itagg_mobiledetail.net_id', '=', 'mobnetworks.id')
                ->where('cp_users_addressbook.id', $id)
                ->where('cp_users_addressbook.user_bigid', $bigid)
                ->select(
                    'cp_users_addressbook.id',
                    'cp_users_addressbook.name',
                    'itagg_mobiledetail.msisdn as phone',
                    'mobnetworks.Name as network',
                    'cp_users_addressbook.is_favourite'
                )
                ->first();

            if (!$contact) {
                return response()->json([
                    'status' => false,
                    'message' => 'Contact not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Contact retrieved successfully',
                'data' => [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'phone' => $contact->phone,
                    'network' => $contact->network ?? 'Unknown',
                    'is_favourite' => $contact->is_favourite === 'y',
                ],
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Contact Show Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to load contact'
            ], 500);
        }
    }

    /**
     * Create new contact
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
                'is_favourite' => 'nullable|boolean',
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

            // Format phone number - remove non-numeric characters
            $phone = preg_replace('/[^0-9]/', '', $request->phone);
            $name = strtolower(trim($request->name));

            // Check if mobile detail exists
            $existingMobileDetail = DB::table('itagg_mobiledetail')
                ->where('msisdn', $phone)
                ->first();

            if ($existingMobileDetail) {
                // Check for duplicate in user's addressbook
                $existingEntry = DB::table('cp_users_addressbook')
                    ->where('user_bigid', $bigid)
                    ->where('itagg_mobiledetail_id', $existingMobileDetail->id)
                    ->where('name', $name)
                    ->exists();

                if ($existingEntry) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Contact already exists with this name and phone number',
                        'errors' => [
                            'phone' => ['A contact with this phone number and name already exists.']
                        ]
                    ], 422);
                }

                $mobileDetailId = $existingMobileDetail->id;
            } else {
                // Create new mobile detail
                $mobileDetailId = DB::table('itagg_mobiledetail')->insertGetId([
                    'msisdn' => $phone,
                    'net_id' => 1, // Default to first network, can be updated
                    'confirmed' => 0,
                    'lastchanged' => Carbon::now('Europe/London')->format('YmdHis'),
                    'mbloxDeliverer' => null,
                ]);
            }

            $isFavourite = $request->is_favourite ? 'y' : 'n';

            // Create contact in addressbook
            $contactId = DB::table('cp_users_addressbook')->insertGetId([
                'name' => $name,
                'itagg_mobiledetail_id' => $mobileDetailId,
                'user_bigid' => $bigid,
                'is_favourite' => $isFavourite,
            ]);

            // Fetch the created contact with network info
            $contact = DB::table('cp_users_addressbook')
                ->leftJoin('itagg_mobiledetail', 'cp_users_addressbook.itagg_mobiledetail_id', '=', 'itagg_mobiledetail.id')
                ->leftJoin('mobnetworks', 'itagg_mobiledetail.net_id', '=', 'mobnetworks.id')
                ->where('cp_users_addressbook.id', $contactId)
                ->select(
                    'cp_users_addressbook.id',
                    'cp_users_addressbook.name',
                    'itagg_mobiledetail.msisdn as phone',
                    'mobnetworks.Name as network',
                    'cp_users_addressbook.is_favourite'
                )
                ->first();

            return response()->json([
                'status' => true,
                'message' => 'Contact created successfully',
                'data' => [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'phone' => $contact->phone,
                    'network' => $contact->network ?? 'Unknown',
                    'is_favourite' => $contact->is_favourite === 'y',
                ],
            ], 201);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Contact Store Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to create contact'
            ], 500);
        }
    }

    /**
     * Update contact
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'is_favourite' => 'nullable|boolean',
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

            // Check contact exists
            $contact = DB::table('cp_users_addressbook')
                ->where('id', $id)
                ->where('user_bigid', $bigid)
                ->first();

            if (!$contact) {
                return response()->json([
                    'status' => false,
                    'message' => 'Contact not found'
                ], 404);
            }

            // Prepare update data
            $updateData = [];

            if ($request->has('name')) {
                $newName = strtolower(trim($request->name));
                
                // Check for duplicate name with same phone
                $existingEntry = DB::table('cp_users_addressbook')
                    ->where('user_bigid', $bigid)
                    ->where('itagg_mobiledetail_id', $contact->itagg_mobiledetail_id)
                    ->where('name', $newName)
                    ->where('id', '!=', $id)
                    ->exists();

                if ($existingEntry) {
                    return response()->json([
                        'status' => false,
                        'message' => 'The name already exists for this phone number',
                        'errors' => [
                            'name' => ['Another contact with this name already exists for this phone number.']
                        ]
                    ], 422);
                }

                $updateData['name'] = $newName;
            }

            if ($request->has('is_favourite')) {
                $updateData['is_favourite'] = $request->is_favourite ? 'y' : 'n';
            }

            if (!empty($updateData)) {
                DB::table('cp_users_addressbook')
                    ->where('id', $id)
                    ->update($updateData);
            }

            // Fetch updated contact
            $updatedContact = DB::table('cp_users_addressbook')
                ->leftJoin('itagg_mobiledetail', 'cp_users_addressbook.itagg_mobiledetail_id', '=', 'itagg_mobiledetail.id')
                ->leftJoin('mobnetworks', 'itagg_mobiledetail.net_id', '=', 'mobnetworks.id')
                ->where('cp_users_addressbook.id', $id)
                ->select(
                    'cp_users_addressbook.id',
                    'cp_users_addressbook.name',
                    'itagg_mobiledetail.msisdn as phone',
                    'mobnetworks.Name as network',
                    'cp_users_addressbook.is_favourite'
                )
                ->first();

            return response()->json([
                'status' => true,
                'message' => 'Contact updated successfully',
                'data' => [
                    'id' => $updatedContact->id,
                    'name' => $updatedContact->name,
                    'phone' => $updatedContact->phone,
                    'network' => $updatedContact->network ?? 'Unknown',
                    'is_favourite' => $updatedContact->is_favourite === 'y',
                ],
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Contact Update Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to update contact'
            ], 500);
        }
    }

    /**
     * Delete contact
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

            // Check contact exists
            $contact = DB::table('cp_users_addressbook')
                ->where('id', $id)
                ->where('user_bigid', $bigid)
                ->first();

            if (!$contact) {
                return response()->json([
                    'status' => false,
                    'message' => 'Contact not found'
                ], 404);
            }

            // Delete from group addressbook first (foreign key constraint)
            DB::table('cp_group_addressbook')
                ->where('addressbook_id', $id)
                ->delete();

            // Delete contact
            DB::table('cp_users_addressbook')
                ->where('id', $id)
                ->delete();

            return response()->json([
                'status' => true,
                'message' => 'Contact deleted successfully'
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Contact Delete Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete contact'
            ], 500);
        }
    }

    /**
     * Delete all contacts
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyAll(Request $request)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;

            DB::transaction(function () use ($bigid) {
                // Get all addressbook IDs for this user
                $addressbookIds = DB::table('cp_users_addressbook')
                    ->where('user_bigid', $bigid)
                    ->pluck('id');

                // Delete from group addressbook first
                DB::table('cp_group_addressbook')
                    ->whereIn('addressbook_id', $addressbookIds)
                    ->delete();

                // Delete all contacts
                DB::table('cp_users_addressbook')
                    ->where('user_bigid', $bigid)
                    ->delete();
            });

            return response()->json([
                'status' => true,
                'message' => 'All contacts deleted successfully'
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Contact Delete All Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete contacts'
            ], 500);
        }
    }
}
