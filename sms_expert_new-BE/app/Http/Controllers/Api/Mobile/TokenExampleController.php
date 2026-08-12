<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

/**
 * Token Example Controller
 * 
 * Example endpoints demonstrating token validation
 */
class TokenExampleController extends Controller
{
    /**
     * Get authenticated user from token
     */
    public function getAuthenticatedUser(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'status' => true,
            'message' => 'Authenticated user retrieved',
            'data' => [
                'id' => $user->id,
                'bigid' => $user->bigid,
                'username' => $user->uname,
                'email' => $user->contactemail,
            ],
        ]);
    }

    /**
     * Get user by bigid
     */
    public function getUserByBigid(Request $request)
    {
        $user = $request->user();
        
        $userDetails = User::where('bigid', $user->bigid)->first();

        return response()->json([
            'status' => true,
            'message' => 'User details retrieved by bigid',
            'data' => [
                'id' => $userDetails->id ?? null,
                'bigid' => $userDetails->bigid ?? null,
                'username' => $userDetails->uname ?? null,
            ],
        ]);
    }

    /**
     * Get current token info
     */
    public function getCurrentToken(Request $request)
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        return response()->json([
            'status' => true,
            'message' => 'Current token info',
            'data' => [
                'token_name' => $token->name ?? 'unknown',
                'abilities' => $token->abilities ?? [],
                'created_at' => $token->created_at ?? null,
                'expires_at' => $token->expires_at ?? null,
            ],
        ]);
    }

    /**
     * Validate token with permissions check
     */
    public function validateWithPermissions(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'status' => true,
            'message' => 'Token is valid',
            'data' => [
                'user_id' => $user->id,
                'bigid' => $user->bigid,
                'is_authenticated' => true,
            ],
        ]);
    }

    /**
     * Manual token validation (without middleware)
     */
    public function manualTokenValidation(Request $request)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'status' => false,
                'message' => 'No token provided',
            ], 401);
        }

        // Try to authenticate with token
        try {
            $user = Auth::guard('sanctum')->user();
            
            if ($user) {
                return response()->json([
                    'status' => true,
                    'message' => 'Token is valid',
                    'data' => [
                        'user_id' => $user->id,
                        'bigid' => $user->bigid,
                        'username' => $user->uname,
                    ],
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => 'Invalid token',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Token validation failed: ' . $e->getMessage(),
            ], 401);
        }
    }
}
