<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMargin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserMarginController extends Controller
{
    /**
     * Update user margin percentage
     *
     * @param Request $request
     * @param int $userId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $userId)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'margin_percentage' => 'required|numeric|min:0|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Validation failed. Please check your input.');
        }

        try {
            // Find the user
            $user = User::findOrFail($userId);

            // Update or create margin
            $margin = UserMargin::updateOrCreate(
                ['user_id' => $userId],
                [
                    'margin_percentage' => $request->margin_percentage,
                    'is_active' => $request->has('is_active') ? 1 : 0,
                ]
            );

            return redirect()->back()->with('success', 'User margin updated successfully!');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to update margin: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Get user margin with calculated rates
     *
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($userId)
    {
        try {
            $margin = UserMargin::where('user_id', $userId)->first();

            if (!$margin) {
                return response()->json([
                    'success' => false,
                    'message' => 'No margin found for this user',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'margin_percentage' => $margin->margin_percentage,
                    'is_active' => $margin->is_active,
                    'updated_at' => $margin->updated_at,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching margin: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle margin active status
     *
     * @param int $userId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function toggle($userId)
    {
        try {
            $margin = UserMargin::where('user_id', $userId)->firstOrFail();
            $margin->is_active = !$margin->is_active;
            $margin->save();

            $status = $margin->is_active ? 'activated' : 'deactivated';

            return redirect()->back()->with('success', "User margin {$status} successfully!");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to toggle margin: ' . $e->getMessage());
        }
    }

    /**
     * Delete user margin (reset to default)
     *
     * @param int $userId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($userId)
    {
        try {
            UserMargin::where('user_id', $userId)->delete();

            return redirect()->back()->with('success', 'User margin reset to default!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete margin: ' . $e->getMessage());
        }
    }
}
