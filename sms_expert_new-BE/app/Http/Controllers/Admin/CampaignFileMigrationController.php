<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ServerSetting;
use App\Models\CampaignFileMigration;
use App\Models\User;
use App\Services\Queue\CampaignFileMigrationQueueService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class CampaignFileMigrationController extends Controller
{
    /**
     * Show campaign file migration page
     */
    public function index()
    {
        $oldServer = ServerSetting::getOldServer();
        $newServer = ServerSetting::getNewServer();

        // Get recent migration batches
        $recentBatches = CampaignFileMigration::select('migration_batch_id', 'direction')
            ->selectRaw('MIN(created_at) as started_at')
            ->selectRaw('MAX(completed_at) as completed_at')
            ->selectRaw('COUNT(*) as total_files')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count")
            ->selectRaw("SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped_count")
            ->selectRaw("SUM(CASE WHEN status IN ('pending', 'processing') THEN 1 ELSE 0 END) as pending_count")
            ->groupBy('migration_batch_id', 'direction')
            ->orderByDesc('started_at')
            ->limit(20)
            ->get();

        // Get users eligible for migration
        $usersReadyForOldToNew = User::where('migration_flag', 'pending')
            ->orWhere('migration_flag', 'new')
            ->count();

        $usersReadyForNewToOld = User::where('migration_flag', 'new')
            ->count();

        return view('admin.migration.campaign-files', [
            'oldServer' => $oldServer,
            'newServer' => $newServer,
            'recentBatches' => $recentBatches,
            'usersReadyForOldToNew' => $usersReadyForOldToNew,
            'usersReadyForNewToOld' => $usersReadyForNewToOld,
        ]);
    }

    /**
     * Start migration process
     */
    public function startMigration(Request $request)
    {
        $request->validate([
            'direction' => 'required|in:old_to_new,new_to_old',
            'user_selection' => 'required|in:all,selected',
            'user_bigids' => 'required_if:user_selection,selected|array',
        ]);

        $direction = $request->input('direction');
        $userSelection = $request->input('user_selection');

        // Check server settings
        $sourceServer = $direction === 'old_to_new'
            ? ServerSetting::getOldServer()
            : ServerSetting::getNewServer();

        if (!$sourceServer) {
            return response()->json([
                'success' => false,
                'message' => 'Source server settings not configured. Please configure server settings first.',
            ]);
        }

        // Get users to migrate
        if ($userSelection === 'all') {
            if ($direction === 'old_to_new') {
                $userBigids = User::whereIn('migration_flag', ['pending', 'new'])
                    ->pluck('bigid')
                    ->toArray();
            } else {
                $userBigids = User::where('migration_flag', 'new')
                    ->pluck('bigid')
                    ->toArray();
            }
        } else {
            $userBigids = $request->input('user_bigids', []);
        }

        if (empty($userBigids)) {
            return response()->json([
                'success' => false,
                'message' => 'No users selected for migration.',
            ]);
        }

        // Generate batch ID
        $batchId = CampaignFileMigration::generateBatchId();

        try {
            // Queue the migration job
            $queueService = new CampaignFileMigrationQueueService();
            $result = $queueService->queueBatchMigration([
                'batch_id' => $batchId,
                'direction' => $direction,
                'user_bigids' => $userBigids,
            ]);

            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'Failed to queue migration job');
            }

            $adminUser = Session::get('admin_user');
            Log::info('Campaign file migration started', [
                'admin_id' => $adminUser['id'] ?? null,
                'batch_id' => $batchId,
                'direction' => $direction,
                'user_count' => count($userBigids),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Migration job queued successfully.',
                'data' => [
                    'batch_id' => $batchId,
                    'user_count' => count($userBigids),
                    'direction' => $direction,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Campaign file migration start failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start migration: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Get migration batch status
     */
    public function getBatchStatus(Request $request)
    {
        $batchId = $request->input('batch_id');

        if (!$batchId) {
            return response()->json([
                'success' => false,
                'message' => 'Batch ID required',
            ]);
        }

        $stats = CampaignFileMigration::getBatchStats($batchId);

        // Get sample of recent files
        $recentFiles = CampaignFileMigration::forBatch($batchId)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['filename', 'status', 'status_message', 'file_size', 'updated_at']);

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_files' => $recentFiles,
                'is_complete' => $stats['pending'] === 0 && $stats['processing'] === 0,
            ],
        ]);
    }

    /**
     * Get migration history for a batch
     */
    public function getBatchHistory(string $batchId)
    {
        $migrations = CampaignFileMigration::forBatch($batchId)
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('admin.migration.batch-history', [
            'batchId' => $batchId,
            'migrations' => $migrations,
            'stats' => CampaignFileMigration::getBatchStats($batchId),
        ]);
    }

    /**
     * Search users for migration
     */
    public function searchUsers(Request $request)
    {
        $search = $request->input('search', '');
        $direction = $request->input('direction', 'old_to_new');

        $query = User::query();

        if ($direction === 'old_to_new') {
            $query->whereIn('migration_flag', ['pending', 'new']);
        } else {
            $query->where('migration_flag', 'new');
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('uname', 'like', "%{$search}%")
                    ->orWhere('contactname', 'like', "%{$search}%")
                    ->orWhere('busname', 'like', "%{$search}%")
                    ->orWhere('contactemail', 'like', "%{$search}%")
                    ->orWhere('bigid', 'like', "%{$search}%");
            });
        }

        $users = $query->select('bigid', 'uname', 'contactname', 'busname', 'migration_flag')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Get queue status
     */
    public function getQueueStatus()
    {
        try {
            $queueService = new CampaignFileMigrationQueueService();
            $depth = $queueService->getQueueDepth();

            return response()->json([
                'success' => true,
                'data' => [
                    'queue_depth' => $depth,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
