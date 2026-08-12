<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ServerSetting;
use App\Services\ServerConnectionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class ServerSettingsController extends Controller
{
    /**
     * Show server settings page
     */
    public function index()
    {
        $oldServer = ServerSetting::getOldServer() ?? new ServerSetting(['server_type' => 'old_server']);
        $newServer = ServerSetting::getNewServer() ?? new ServerSetting(['server_type' => 'new_server']);

        return view('admin.settings.server-settings', [
            'oldServer' => $oldServer,
            'newServer' => $newServer,
        ]);
    }

    /**
     * Save server settings
     */
    public function save(Request $request)
    {
        $request->validate([
            'server_type' => 'required|in:old_server,new_server',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'campaign_file_path' => 'required|string|max:500',
            'connection_type' => 'required|in:sftp,ftp,local',
        ]);

        $serverType = $request->input('server_type');

        $data = [
            'server_type' => $serverType,
            'host' => $request->input('host'),
            'port' => $request->input('port'),
            'username' => $request->input('username'),
            'campaign_file_path' => $request->input('campaign_file_path'),
            'connection_type' => $request->input('connection_type'),
            'is_active' => true,
        ];

        // Only update password if provided
        if ($request->filled('password')) {
            $data['password'] = $request->input('password');
        }

        $server = ServerSetting::updateOrCreate(
            ['server_type' => $serverType],
            $data
        );

        $adminUser = Session::get('admin_user');
        Log::info('Server settings updated', [
            'admin_id' => $adminUser['id'] ?? null,
            'server_type' => $serverType,
        ]);

        // Check if we should redirect to tab or separate page
        if ($request->input('redirect_to_tab')) {
            return redirect()->route('admin.settings', ['tab' => 'server-settings'])
                ->with('success', ucfirst(str_replace('_', ' ', $serverType)) . ' settings saved successfully.');
        }

        return redirect()->route('admin.settings.server')
            ->with('success', ucfirst(str_replace('_', ' ', $serverType)) . ' settings saved successfully.');
    }

    /**
     * Test server connection
     */
    public function testConnection(Request $request)
    {
        $request->validate([
            'server_type' => 'required|in:old_server,new_server',
        ]);

        $serverType = $request->input('server_type');
        $server = ServerSetting::where('server_type', $serverType)->first();

        if (!$server) {
            return response()->json([
                'success' => false,
                'message' => 'Server settings not found. Please save settings first.',
            ]);
        }

        $connectionService = new ServerConnectionService();
        $result = $connectionService->testConnection($server);

        return response()->json($result);
    }

    /**
     * Get server status
     */
    public function getStatus(Request $request)
    {
        $serverType = $request->input('server_type');

        if ($serverType) {
            $server = ServerSetting::where('server_type', $serverType)->first();
            if ($server) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'last_tested_at' => $server->last_tested_at?->format('Y-m-d H:i:s'),
                        'last_test_status' => $server->last_test_status,
                        'last_test_message' => $server->last_test_message,
                    ],
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Server not found',
        ]);
    }
}
