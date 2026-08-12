<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class KeywordsController extends Controller
{
    /**
     * Get keywords list for mobile app
     * GET /api/mobile/keywords
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $userBigId = $user->bigid;
            $date = date('Y-m-d');

            // Get user's keyword wallet info
            $userData = DB::table('users')
                ->selectRaw('(platkeywordwallet / NULLIF(platkeywordcost, 0)) as keywordsleft, platinumaccess')
                ->where('bigid', $userBigId)
                ->first();

            $keywordsLeft = $userData ? floor($userData->keywordsleft ?? 0) : 0;
            $hasPlatinumAccess = $userData && $userData->platinumaccess === 'y';

            // Get all keywords (itagg_instance) for this user
            $keywords = DB::table('itagg_instance')
                ->select(
                    'itagg_instance.id',
                    'itagg_instance.keyword',
                    'smsshortcodes.number',
                    'itagg_type.description',
                    'itagg_instance.expiry',
                    'itagg_instance.purchased',
                    'itagg_instance.response_sender_id',
                    'itagg_instance.response_content',
                    'itagg_instance.forwarding_email',
                    'itagg_instance.forwarding_url',
                    'itagg_instance.modules_enabled',
                    'itagg_instance.module_restrict',
                    'smsshortcodes.module_restrict as smsshortcodes_restrict',
                    'smsshortcodes.show_cp_subkeyword_management as cp_sk_management'
                )
                ->join('itagg_type', 'itagg_instance.itagg_type_id', '=', 'itagg_type.id')
                ->leftJoin('smsshortcodes', 'itagg_instance.smsshortcodes_id', '=', 'smsshortcodes.id')
                ->where('itagg_instance.users_bigid', $userBigId)
                ->where('itagg_instance.status', 1)
                ->orderBy('itagg_instance.keyword')
                ->get();

            // Format keywords for response
            $formattedKeywords = $keywords->map(function ($keyword) use ($date) {
                $expiryDate = $keyword->expiry;
                $daysUntilExpiry = ceil((strtotime($expiryDate) - time()) / (60 * 60 * 24));

                // Determine status
                $status = 'active';
                $statusText = 'Active until ' . date('d M Y', strtotime($expiryDate));
                
                if ($daysUntilExpiry < 0) {
                    $status = 'expired';
                    $statusText = 'Expired';
                } elseif ($daysUntilExpiry < 30) {
                    $status = 'expiring_soon';
                    $statusText = $daysUntilExpiry . ' days left';
                }

                // Determine keyword type
                $keywordType = $keyword->keyword === '*' ? 'dedicated' : 'keyword';

                return [
                    'id' => $keyword->id,
                    'keyword' => $keyword->keyword,
                    'virtual_number' => $keyword->number,
                    'type' => $keywordType,
                    'description' => $keyword->description,
                    'expiry_date' => $expiryDate,
                    'expiry_formatted' => date('d M Y', strtotime($expiryDate)),
                    'purchased_date' => $keyword->purchased,
                    'days_until_expiry' => $daysUntilExpiry,
                    'status' => $status,
                    'status_text' => $statusText,
                    'response_sender_id' => $keyword->response_sender_id,
                    'response_content' => urldecode($keyword->response_content ?? ''),
                    'forwarding_email' => $keyword->forwarding_email,
                    'forwarding_url' => $keyword->forwarding_url,
                    'modules_enabled' => $keyword->modules_enabled,
                    'module_restrict' => $keyword->module_restrict,
                    'show_subkeyword_management' => $keyword->cp_sk_management == 1,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'keywords' => $formattedKeywords,
                    'total_keywords' => $formattedKeywords->count(),
                    'keywords_left' => $keywordsLeft,
                    'has_platinum_access' => $hasPlatinumAccess,
                    'shortcode' => '60300',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching keywords for mobile', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch keywords: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single keyword details with configuration
     * GET /api/mobile/keywords/{id}
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $userBigId = $user->bigid;

            // Get keyword details
            $keyword = DB::table('itagg_instance')
                ->select(
                    'itagg_instance.id',
                    'itagg_instance.keyword',
                    'itagg_instance.smsshortcodes_id',
                    'smsshortcodes.number',
                    'itagg_type.description',
                    'itagg_instance.expiry',
                    'itagg_instance.purchased',
                    'itagg_instance.response_sender_id',
                    'itagg_instance.response_content',
                    'itagg_instance.response_smsshortcodes_id',
                    'itagg_instance.forwarding_email',
                    'itagg_instance.forwarding_url',
                    'itagg_instance.advertise',
                    'smsshortcodes.must_respond',
                    'itagg_instance.modules_enabled',
                    'itagg_instance.module_restrict as itagg_restrict',
                    'smsshortcodes.module_restrict as smsshortcodes_restrict',
                    'smsshortcodes.show_cp_subkeyword_management as cp_sk_management',
                    'itagg_instance.allowed_mobile_update_numbers',
                    'itagg_instance.allow_mobile_update_across_subkeys'
                )
                ->join('itagg_type', 'itagg_instance.itagg_type_id', '=', 'itagg_type.id')
                ->leftJoin('smsshortcodes', 'itagg_instance.smsshortcodes_id', '=', 'smsshortcodes.id')
                ->where('itagg_instance.id', $id)
                ->where('itagg_instance.users_bigid', $userBigId)
                ->where('itagg_instance.status', 1)
                ->first();

            if (!$keyword) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keyword not found',
                ], 404);
            }

            // Get available SMS short codes for response route
            $smsShortcodes = DB::table('smsshortcodes')
                ->select('smsshortcodes.id', 'smsshortcodes.number')
                ->join('itagg_instance', 'smsshortcodes.id', '=', 'itagg_instance.smsshortcodes_id')
                ->where('itagg_instance.users_bigid', $userBigId)
                ->where('itagg_instance.status', 1)
                ->where('itagg_instance.expiry', '>=', date('Y-m-d'))
                ->groupBy('smsshortcodes.id', 'smsshortcodes.number')
                ->orderBy('smsshortcodes.number')
                ->get();

            // Get subkeywords
            $subkeywords = DB::table('itagg_subkeyword')
                ->select(
                    'keyword',
                    'response_sender_id',
                    'response_content',
                    'response_smsshortcodes_id',
                    'forwarding_email',
                    'forwarding_url'
                )
                ->where('itagg_instance_id', $id)
                ->get()
                ->map(function ($sk) {
                    return [
                        'keyword' => $sk->keyword,
                        'response_sender_id' => $sk->response_sender_id,
                        'response_content' => urldecode($sk->response_content ?? ''),
                        'response_smsshortcodes_id' => $sk->response_smsshortcodes_id,
                        'forwarding_email' => $sk->forwarding_email,
                        'forwarding_url' => $sk->forwarding_url,
                    ];
                });

            // Calculate module restrictions
            $codeRestrict = intval($keyword->smsshortcodes_restrict);
            $itaggRestrict = intval($keyword->itagg_restrict);
            $moduleRestrict = $codeRestrict & $itaggRestrict;

            // Module bit definitions
            $moduleBits = [
                'smsResponder' => 1,
                'Forwarder' => 2,
                'SMSForwarder' => 4,
                'BusinessCard' => 8,
                'Subscription' => 16,
                'WAPPushResponder' => 32,
                'Voting' => 256,
                'EmailForwarder' => 2048,
            ];

            $isStarKeyword = $keyword->keyword === '*';
            $enabledModules = [];

            if ($isStarKeyword) {
                foreach ($moduleBits as $name => $bit) {
                    $enabledModules[$name] = ($name === 'Forwarder' || $name === 'EmailForwarder');
                }
            } else {
                foreach ($moduleBits as $name => $bit) {
                    $enabledModules[$name] = ($moduleRestrict & $bit) === $bit;
                }
                
                // If no modules enabled, enable all standard modules
                $hasAnyModule = false;
                foreach ($enabledModules as $enabled) {
                    if ($enabled) {
                        $hasAnyModule = true;
                        break;
                    }
                }
                
                if (!$hasAnyModule) {
                    foreach ($moduleBits as $name => $bit) {
                        $enabledModules[$name] = true;
                    }
                }
            }

            // Get module configuration status (which are switched on)
            $modulesEnabled = $keyword->modules_enabled ?? 0;
            $activeModules = [];
            foreach ($moduleBits as $name => $bit) {
                $activeModules[$name] = ($modulesEnabled & $bit) === $bit;
            }

            // Calculate expiry status
            $daysUntilExpiry = ceil((strtotime($keyword->expiry) - time()) / (60 * 60 * 24));
            $status = 'active';
            $statusText = 'Active until ' . date('d M Y', strtotime($keyword->expiry));
            
            if ($daysUntilExpiry < 0) {
                $status = 'expired';
                $statusText = 'Expired';
            } elseif ($daysUntilExpiry < 30) {
                $status = 'expiring_soon';
                $statusText = $daysUntilExpiry . ' days left';
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'keyword' => [
                        'id' => $keyword->id,
                        'keyword' => $keyword->keyword,
                        'virtual_number' => $keyword->number,
                        'smsshortcodes_id' => $keyword->smsshortcodes_id,
                        'type' => $isStarKeyword ? 'dedicated' : 'keyword',
                        'description' => $keyword->description,
                        'expiry_date' => $keyword->expiry,
                        'expiry_formatted' => date('d M Y', strtotime($keyword->expiry)),
                        'purchased_date' => $keyword->purchased,
                        'days_until_expiry' => $daysUntilExpiry,
                        'status' => $status,
                        'status_text' => $statusText,
                        'response_sender_id' => $keyword->response_sender_id,
                        'response_content' => urldecode($keyword->response_content ?? ''),
                        'response_smsshortcodes_id' => $keyword->response_smsshortcodes_id,
                        'forwarding_email' => $keyword->forwarding_email,
                        'forwarding_url' => $keyword->forwarding_url,
                        'advertise' => $keyword->advertise,
                        'allowed_mobile_update_numbers' => $keyword->allowed_mobile_update_numbers,
                        'allow_mobile_update_across_subkeys' => $keyword->allow_mobile_update_across_subkeys,
                    ],
                    'sms_shortcodes' => $smsShortcodes,
                    'subkeywords' => $subkeywords,
                    'enabled_modules' => $enabledModules,
                    'active_modules' => $activeModules,
                    'show_subkeyword_management' => $keyword->cp_sk_management == 1,
                    'is_star_keyword' => $isStarKeyword,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching keyword details', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch keyword details: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update SMS Responder settings
     * POST /api/mobile/keywords/{id}/sms-responder
     */
    public function updateSmsResponder(Request $request, $id)
    {
        try {
            $request->validate([
                'sender_id' => 'required|string|max:11',
                'response_text' => 'required|string',
                'response_route' => 'required|integer',
                'allowed_update_numbers' => 'nullable|string',
                'allow_subkeys' => 'required|in:0,1',
                'subkeyword' => 'nullable|string',
                'advertise' => 'nullable|in:0,1',
            ]);

            $user = $request->user();
            $userBigId = $user->bigid;

            // Verify ownership
            $keyword = DB::table('itagg_instance')
                ->where('id', $id)
                ->where('users_bigid', $userBigId)
                ->first();

            if (!$keyword) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keyword not found or unauthorized',
                ], 404);
            }

            $subkeyword = $request->input('subkeyword', '');

            if ($subkeyword === '') {
                // Update main keyword
                DB::table('itagg_instance')
                    ->where('id', $id)
                    ->update([
                        'response_sender_id' => $request->sender_id,
                        'response_content' => urlencode($request->response_text),
                        'response_smsshortcodes_id' => $request->response_route,
                        'allowed_mobile_update_numbers' => $request->allowed_update_numbers ?? '',
                        'allow_mobile_update_across_subkeys' => $request->allow_subkeys,
                    ]);
            } else {
                // Update subkeyword
                DB::table('itagg_subkeyword')
                    ->where('itagg_instance_id', $id)
                    ->where('keyword', $subkeyword)
                    ->update([
                        'response_sender_id' => $request->sender_id,
                        'response_content' => urlencode($request->response_text),
                        'response_smsshortcodes_id' => $request->response_route,
                    ]);
            }

            // Update advertise flag at itagg level
            if ($request->has('advertise')) {
                DB::table('itagg_instance')
                    ->where('id', $id)
                    ->update(['advertise' => $request->advertise]);
            }

            Log::info('SMS Responder updated via mobile', [
                'keyword_id' => $id,
                'subkeyword' => $subkeyword,
                'user' => $userBigId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'SMS Responder settings updated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating SMS Responder', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update SMS Responder: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update Email Forwarder settings
     * POST /api/mobile/keywords/{id}/email-forwarder
     */
    public function updateEmailForwarder(Request $request, $id)
    {
        try {
            $request->validate([
                'email_address' => 'nullable|string',
                'url_address' => 'nullable|string|max:2000',
                'subkeyword' => 'nullable|string',
            ]);

            $user = $request->user();
            $userBigId = $user->bigid;

            // Verify ownership
            $keyword = DB::table('itagg_instance')
                ->where('id', $id)
                ->where('users_bigid', $userBigId)
                ->first();

            if (!$keyword) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keyword not found or unauthorized',
                ], 404);
            }

            $subkeyword = $request->input('subkeyword', '');

            if ($subkeyword === '') {
                // Update main keyword
                DB::table('itagg_instance')
                    ->where('id', $id)
                    ->update([
                        'forwarding_email' => $request->email_address ?? '',
                        'forwarding_url' => $request->url_address ?? '',
                    ]);
            } else {
                // Update subkeyword
                DB::table('itagg_subkeyword')
                    ->where('itagg_instance_id', $id)
                    ->where('keyword', $subkeyword)
                    ->update([
                        'forwarding_email' => $request->email_address ?? '',
                        'forwarding_url' => $request->url_address ?? '',
                    ]);
            }

            Log::info('Email Forwarder updated via mobile', [
                'keyword_id' => $id,
                'subkeyword' => $subkeyword,
                'user' => $userBigId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email Forwarder settings updated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating Email Forwarder', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update Email Forwarder: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle module on/off
     * POST /api/mobile/keywords/{id}/toggle-module
     */
    public function toggleModule(Request $request, $id)
    {
        try {
            $request->validate([
                'module' => 'required|string',
                'action' => 'required|in:on,off',
                'subkeyword' => 'nullable|string',
            ]);

            $user = $request->user();
            $userBigId = $user->bigid;

            // Verify ownership
            $keyword = DB::table('itagg_instance')
                ->where('id', $id)
                ->where('users_bigid', $userBigId)
                ->first();

            if (!$keyword) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keyword not found or unauthorized',
                ], 404);
            }

            // Module bit definitions
            $moduleBits = [
                'smsResponder' => 1,
                'Forwarder' => 2,
                'SMSForwarder' => 4,
                'BusinessCard' => 8,
                'Subscription' => 16,
                'WAPPushResponder' => 32,
                'Voting' => 256,
                'EmailForwarder' => 2048,
            ];

            $module = $request->module;
            if (!isset($moduleBits[$module])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unknown module: ' . $module,
                ], 400);
            }

            $moduleId = $moduleBits[$module];
            $subkeyword = $request->input('subkeyword', '');

            if ($subkeyword === '') {
                $record = DB::table('itagg_instance')->where('id', $id)->first();
                $tableName = 'itagg_instance';
                $whereClause = ['id' => $id];
            } else {
                $record = DB::table('itagg_subkeyword')
                    ->where('itagg_instance_id', $id)
                    ->where('keyword', $subkeyword)
                    ->first();
                $tableName = 'itagg_subkeyword';
                $whereClause = ['itagg_instance_id' => $id, 'keyword' => $subkeyword];
            }

            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found',
                ], 404);
            }

            $currentBitfield = $record->modules_enabled ?? 0;
            $isCurrentlyEnabled = ($currentBitfield & $moduleId) === $moduleId;

            if ($request->action === 'on') {
                $newBitfield = $isCurrentlyEnabled ? $currentBitfield : $currentBitfield + $moduleId;
            } else {
                $newBitfield = $isCurrentlyEnabled ? $currentBitfield - $moduleId : $currentBitfield;
            }

            DB::table($tableName)
                ->where($whereClause)
                ->update(['modules_enabled' => $newBitfield]);

            Log::info('Module toggled via mobile', [
                'keyword_id' => $id,
                'module' => $module,
                'action' => $request->action,
                'user' => $userBigId,
            ]);

            return response()->json([
                'success' => true,
                'message' => $module . ' module ' . ($request->action === 'on' ? 'enabled' : 'disabled') . ' successfully',
                'status' => $request->action,
            ]);

        } catch (\Exception $e) {
            Log::error('Error toggling module', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle module: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get subkeywords for a keyword
     * GET /api/mobile/keywords/{id}/subkeywords
     */
    public function getSubkeywords(Request $request, $id)
    {
        try {
            $user = $request->user();
            $userBigId = $user->bigid;

            // Verify ownership
            $keyword = DB::table('itagg_instance')
                ->where('id', $id)
                ->where('users_bigid', $userBigId)
                ->first();

            if (!$keyword) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keyword not found or unauthorized',
                ], 404);
            }

            $subkeywords = DB::table('itagg_subkeyword')
                ->select(
                    'keyword',
                    'response_sender_id',
                    'response_content',
                    'response_smsshortcodes_id',
                    'forwarding_email',
                    'forwarding_url',
                    'modules_enabled'
                )
                ->where('itagg_instance_id', $id)
                ->get()
                ->map(function ($sk) {
                    return [
                        'keyword' => $sk->keyword,
                        'response_sender_id' => $sk->response_sender_id,
                        'response_content' => urldecode($sk->response_content ?? ''),
                        'response_smsshortcodes_id' => $sk->response_smsshortcodes_id,
                        'forwarding_email' => $sk->forwarding_email,
                        'forwarding_url' => $sk->forwarding_url,
                        'modules_enabled' => $sk->modules_enabled,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'subkeywords' => $subkeywords,
                    'total' => $subkeywords->count(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching subkeywords', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subkeywords: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add a new subkeyword
     * POST /api/mobile/keywords/{id}/subkeywords
     */
    public function addSubkeyword(Request $request, $id)
    {
        try {
            $request->validate([
                'subkeyword' => 'required|string|max:50|regex:/^[A-Za-z0-9]+$/',
            ]);

            $user = $request->user();
            $userBigId = $user->bigid;

            // Verify ownership
            $keyword = DB::table('itagg_instance')
                ->where('id', $id)
                ->where('users_bigid', $userBigId)
                ->first();

            if (!$keyword) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keyword not found or unauthorized',
                ], 404);
            }

            $subkeyword = strtoupper(trim($request->subkeyword));

            // Check if subkeyword already exists
            $exists = DB::table('itagg_subkeyword')
                ->where('itagg_instance_id', $id)
                ->where('keyword', $subkeyword)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subkeyword already exists',
                ], 400);
            }

            // Insert new subkeyword
            DB::table('itagg_subkeyword')->insert([
                'itagg_instance_id' => $id,
                'keyword' => $subkeyword,
            ]);

            Log::info('Subkeyword added via mobile', [
                'keyword_id' => $id,
                'subkeyword' => $subkeyword,
                'user' => $userBigId,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Subkeyword '{$subkeyword}' added successfully",
            ]);

        } catch (\Exception $e) {
            Log::error('Error adding subkeyword', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add subkeyword: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a subkeyword
     * DELETE /api/mobile/keywords/{id}/subkeywords/{subkeyword}
     */
    public function deleteSubkeyword(Request $request, $id, $subkeyword)
    {
        try {
            $user = $request->user();
            $userBigId = $user->bigid;

            // Verify ownership
            $keyword = DB::table('itagg_instance')
                ->where('id', $id)
                ->where('users_bigid', $userBigId)
                ->first();

            if (!$keyword) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keyword not found or unauthorized',
                ], 404);
            }

            $subkeyword = strtoupper(trim($subkeyword));

            $deleted = DB::table('itagg_subkeyword')
                ->where('itagg_instance_id', $id)
                ->where('keyword', $subkeyword)
                ->delete();

            if ($deleted) {
                Log::info('Subkeyword deleted via mobile', [
                    'keyword_id' => $id,
                    'subkeyword' => $subkeyword,
                    'user' => $userBigId,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Subkeyword '{$subkeyword}' deleted successfully",
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Subkeyword not found',
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error('Error deleting subkeyword', [
                'id' => $id,
                'subkeyword' => $subkeyword,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete subkeyword: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check keyword availability
     * POST /api/mobile/keywords/check-availability
     */
    public function checkAvailability(Request $request)
    {
        try {
            $request->validate([
                'keyword' => 'required|string|max:20|regex:/^[A-Za-z0-9]+$/',
            ]);

            $keyword = strtolower(trim($request->keyword));
            $shortcode = '60300';

            $shortcodeId = DB::table('smsshortcodes')
                ->where('number', $shortcode)
                ->value('id');

            if (!$shortcodeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shortcode not found',
                ], 400);
            }

            $exists = DB::table('itagg_instance')
                ->where('keyword', $keyword)
                ->where('smsshortcodes_id', $shortcodeId)
                ->where('status', 1)
                ->exists();

            return response()->json([
                'success' => true,
                'available' => !$exists,
                'keyword' => strtoupper($keyword),
                'shortcode' => $shortcode,
                'message' => $exists ? 'This keyword is already registered' : 'Keyword is available',
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking keyword availability', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check availability: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get SMS Forwarder configuration
     * GET /api/mobile/keywords/{id}/sms-forwarder
     */
    public function getSmsForwarder(Request $request, $id)
    {
        try {
            $user = $request->user();
            $userBigId = $user->bigid;

            // Verify ownership
            $keyword = DB::table('itagg_instance')
                ->select(
                    'itagg_instance.id',
                    'itagg_instance.keyword',
                    'smsshortcodes.number as shortcode_number'
                )
                ->leftJoin('smsshortcodes', 'itagg_instance.smsshortcodes_id', '=', 'smsshortcodes.id')
                ->where('itagg_instance.id', $id)
                ->where('itagg_instance.users_bigid', $userBigId)
                ->first();

            if (!$keyword) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keyword not found or unauthorized',
                ], 404);
            }

            // Get SMS forwarder data
            $smsForwarder = DB::table('itagg_smsforwarder')
                ->where('itagg_instance_id', $id)
                ->first();

            // Get wallet balance
            $walletBalance = DB::table('users')
                ->where('bigid', $userBigId)
                ->value('wallet');

            return response()->json([
                'success' => true,
                'data' => [
                    'keyword' => $keyword->keyword,
                    'shortcode_number' => $keyword->shortcode_number,
                    'fwd_mobile' => $smsForwarder->fwd_mobile ?? '',
                    'wallet_balance' => number_format($walletBalance ?? 0, 2),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching SMS Forwarder config', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch SMS Forwarder: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update SMS Forwarder settings
     * POST /api/mobile/keywords/{id}/sms-forwarder
     */
    public function updateSmsForwarder(Request $request, $id)
    {
        try {
            $request->validate([
                'fwd_mobile' => 'required|string',
            ]);

            $user = $request->user();
            $userBigId = $user->bigid;

            // Verify ownership
            $keyword = DB::table('itagg_instance')
                ->where('id', $id)
                ->where('users_bigid', $userBigId)
                ->first();

            if (!$keyword) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keyword not found or unauthorized',
                ], 404);
            }

            // Check if record exists
            $exists = DB::table('itagg_smsforwarder')
                ->where('itagg_instance_id', $id)
                ->exists();

            if ($exists) {
                DB::table('itagg_smsforwarder')
                    ->where('itagg_instance_id', $id)
                    ->update([
                        'fwd_mobile' => $request->fwd_mobile,
                    ]);
            } else {
                DB::table('itagg_smsforwarder')->insert([
                    'itagg_instance_id' => $id,
                    'fwd_mobile' => $request->fwd_mobile,
                ]);
            }

            Log::info('SMS Forwarder updated via mobile', [
                'keyword_id' => $id,
                'user' => $userBigId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'SMS Forwarder settings updated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating SMS Forwarder', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update SMS Forwarder: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Subscription configuration
     * GET /api/mobile/keywords/{id}/subscription
     */
    public function getSubscription(Request $request, $id)
    {
        try {
            $user = $request->user();
            $userBigId = $user->bigid;

            // Verify ownership and get keyword details
            $keyword = DB::table('itagg_instance')
                ->select(
                    'itagg_instance.id',
                    'itagg_instance.keyword',
                    'itagg_instance.users_bigid',
                    'smsshortcodes.number as shortcode_number'
                )
                ->leftJoin('smsshortcodes', 'itagg_instance.smsshortcodes_id', '=', 'smsshortcodes.id')
                ->where('itagg_instance.id', $id)
                ->where('itagg_instance.users_bigid', $userBigId)
                ->first();

            if (!$keyword) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keyword not found or unauthorized',
                ], 404);
            }

            // Get subscription data
            $subscription = DB::table('itagg_subscription')
                ->where('itagg_instance_id', $id)
                ->first();

            // Default messages
            $defaultSubscribeResponse = "You have been successfully subscribed to {$keyword->keyword} on {$keyword->shortcode_number}. To unsubscribe send stop {$keyword->keyword} to {$keyword->shortcode_number}.";
            $defaultUnsubscribeResponse = "You have been unsubscribed from {$keyword->keyword}.";
            $defaultFailResponse = "Subscription failed - you could not be subscribed to {$keyword->keyword}.";

            return response()->json([
                'success' => true,
                'data' => [
                    'keyword' => $keyword->keyword,
                    'shortcode_number' => $keyword->shortcode_number,
                    'subscribe_response' => $subscription ? urldecode($subscription->subscribe_response ?? '') : $defaultSubscribeResponse,
                    'unsubscribe_response' => $subscription ? urldecode($subscription->unsubscribe_response ?? '') : $defaultUnsubscribeResponse,
                    'fail_response' => $subscription ? urldecode($subscription->fail_response ?? '') : $defaultFailResponse,
                    'max_subscribers' => $subscription->max_subscribers ?? '',
                    'send_mobiles' => $subscription->send_mobiles ?? '',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching Subscription config', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch Subscription: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update Subscription settings
     * POST /api/mobile/keywords/{id}/subscription
     */
    public function updateSubscription(Request $request, $id)
    {
        try {
            $request->validate([
                'subscribe_response' => 'required|string',
                'unsubscribe_response' => 'required|string',
                'fail_response' => 'required|string',
                'max_subscribers' => 'nullable|integer|min:0',
                'send_mobiles' => 'nullable|string',
            ]);

            $user = $request->user();
            $userBigId = $user->bigid;

            // Verify ownership
            $keyword = DB::table('itagg_instance')
                ->where('id', $id)
                ->where('users_bigid', $userBigId)
                ->first();

            if (!$keyword) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keyword not found or unauthorized',
                ], 404);
            }

            $data = [
                'subscribe_response' => urlencode($request->subscribe_response),
                'unsubscribe_response' => urlencode($request->unsubscribe_response),
                'fail_response' => urlencode($request->fail_response),
                'max_subscribers' => $request->max_subscribers,
                'send_mobiles' => $request->send_mobiles ?? '',
            ];

            // Check if record exists
            $exists = DB::table('itagg_subscription')
                ->where('itagg_instance_id', $id)
                ->exists();

            if ($exists) {
                DB::table('itagg_subscription')
                    ->where('itagg_instance_id', $id)
                    ->update($data);
            } else {
                $data['itagg_instance_id'] = $id;
                DB::table('itagg_subscription')->insert($data);
            }

            Log::info('Subscription updated via mobile', [
                'keyword_id' => $id,
                'user' => $userBigId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription settings updated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating Subscription', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update Subscription: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get WAP Push Responder configuration
     * GET /api/mobile/keywords/{id}/wap-push-responder
     */
    public function getWapPushResponder(Request $request, $id)
    {
        try {
            $user = $request->user();
            $userBigId = $user->bigid;

            // Verify ownership and get keyword details
            $keyword = DB::table('itagg_instance')
                ->select(
                    'itagg_instance.id',
                    'itagg_instance.keyword',
                    'itagg_instance.users_bigid',
                    'itagg_instance.smsshortcodes_id',
                    'smsshortcodes.number as shortcode_number'
                )
                ->leftJoin('smsshortcodes', 'itagg_instance.smsshortcodes_id', '=', 'smsshortcodes.id')
                ->where('itagg_instance.id', $id)
                ->where('itagg_instance.users_bigid', $userBigId)
                ->first();

            if (!$keyword) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keyword not found or unauthorized',
                ], 404);
            }

            // Get WAP Push responder data
            $wapPush = DB::table('itagg_wappush')
                ->where('itagg_instance_id', $id)
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'keyword' => $keyword->keyword,
                    'shortcode_number' => $keyword->shortcode_number,
                    'title' => $wapPush->title ?? '',
                    'url' => $wapPush->url ?? '',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching WAP Push Responder config', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch WAP Push Responder: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update WAP Push Responder settings
     * POST /api/mobile/keywords/{id}/wap-push-responder
     */
    public function updateWapPushResponder(Request $request, $id)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:90',
                'url' => 'required|string|max:2000|url',
            ]);

            $user = $request->user();
            $userBigId = $user->bigid;

            // Verify ownership
            $keyword = DB::table('itagg_instance')
                ->where('id', $id)
                ->where('users_bigid', $userBigId)
                ->first();

            if (!$keyword) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keyword not found or unauthorized',
                ], 404);
            }

            $data = [
                'title' => $request->title,
                'url' => $request->url,
            ];

            // Check if record exists
            $exists = DB::table('itagg_wappush')
                ->where('itagg_instance_id', $id)
                ->exists();

            if ($exists) {
                DB::table('itagg_wappush')
                    ->where('itagg_instance_id', $id)
                    ->update($data);
            } else {
                $data['itagg_instance_id'] = $id;
                DB::table('itagg_wappush')->insert($data);
            }

            Log::info('WAP Push Responder updated via mobile', [
                'keyword_id' => $id,
                'user' => $userBigId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'WAP Push Responder settings updated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating WAP Push Responder', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update WAP Push Responder: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get module restriction info (Business Card, Voting)
     * GET /api/mobile/keywords/{id}/module-info/{module}
     */
    public function getModuleInfo(Request $request, $id, $module)
    {
        try {
            $user = $request->user();
            $userBigId = $user->bigid;

            // Verify ownership
            $keyword = DB::table('itagg_instance')
                ->select(
                    'itagg_instance.id',
                    'itagg_instance.keyword',
                    'itagg_instance.modules_enabled',
                    'smsshortcodes.number as shortcode_number'
                )
                ->leftJoin('smsshortcodes', 'itagg_instance.smsshortcodes_id', '=', 'smsshortcodes.id')
                ->where('itagg_instance.id', $id)
                ->where('itagg_instance.users_bigid', $userBigId)
                ->first();

            if (!$keyword) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keyword not found or unauthorized',
                ], 404);
            }

            // Module bit definitions
            $moduleBits = [
                'smsResponder' => 1,
                'Forwarder' => 2,
                'SMSForwarder' => 4,
                'BusinessCard' => 8,
                'Subscription' => 16,
                'WAPPushResponder' => 32,
                'Voting' => 256,
                'EmailForwarder' => 2048,
            ];

            // Check which modules that conflict are currently enabled
            $conflictingModules = ['smsResponder', 'WAPPushResponder', 'Subscription'];
            $enabledConflicting = [];
            $modulesEnabled = $keyword->modules_enabled ?? 0;

            foreach ($conflictingModules as $conflictModule) {
                if (isset($moduleBits[$conflictModule]) && ($modulesEnabled & $moduleBits[$conflictModule])) {
                    $enabledConflicting[] = $conflictModule;
                }
            }

            $canEnable = empty($enabledConflicting);
            $message = $canEnable 
                ? 'Module can be enabled' 
                : 'This module cannot be enabled while other modules that send outbound SMS are active (such as WAP Push Responder, SMS Auto-Responder). Please disable those modules first.';

            return response()->json([
                'success' => true,
                'data' => [
                    'keyword' => $keyword->keyword,
                    'shortcode_number' => $keyword->shortcode_number,
                    'module' => $module,
                    'can_enable' => $canEnable,
                    'conflicting_modules' => $enabledConflicting,
                    'message' => $message,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching module info', [
                'id' => $id,
                'module' => $module,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch module info: ' . $e->getMessage(),
            ], 500);
        }
    }
}
