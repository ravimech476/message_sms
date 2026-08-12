<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LogController extends Controller
{
    /**
     * Display the logs index page with date-wise filtering
     */
    public function index(Request $request)
    {
        try {
            $logPath = storage_path('logs');
            $selectedDate = $request->get('date', date('Y-m-d'));
            $selectedLevel = $request->get('level', 'all');
            $search = $request->get('search', '');
            
            // Get all available log dates from log files
            $availableDates = $this->getAvailableLogDates($logPath);
            
            // Get log content for selected date - check both formats
            $logFile = $this->findLogFile($logPath, $selectedDate);
            $logs = [];
            $totalLogs = 0;
            
            if ($logFile && File::exists($logFile)) {
                $logs = $this->parseLogFile($logFile, $selectedLevel, $search);
                $totalLogs = count($logs);
                
                // Paginate manually
                $perPage = 50;
                $currentPage = $request->get('page', 1);
                $offset = ($currentPage - 1) * $perPage;
                $logs = array_slice($logs, $offset, $perPage);
            }
            
            return view('admin.logs.index', [
                'logs' => $logs,
                'availableDates' => $availableDates,
                'selectedDate' => $selectedDate,
                'selectedLevel' => $selectedLevel,
                'search' => $search,
                'totalLogs' => $totalLogs,
                'currentPage' => $request->get('page', 1),
                'perPage' => 50
            ]);
        } catch (\Exception $e) {
            Log::error('Error viewing logs: ' . $e->getMessage());
            return view('admin.logs.index', [
                'logs' => [],
                'availableDates' => [],
                'selectedDate' => date('Y-m-d'),
                'selectedLevel' => 'all',
                'search' => '',
                'totalLogs' => 0,
                'currentPage' => 1,
                'perPage' => 50,
                'error' => 'Failed to load logs: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Find log file for a specific date - supports both directory and flat file structure
     */
    private function findLogFile($logPath, $date)
    {
        // Format 1: storage/logs/YYYY-MM-DD/laravel.log (directory-based)
        $directoryBasedLog = $logPath . '/' . $date . '/laravel.log';
        if (File::exists($directoryBasedLog)) {
            return $directoryBasedLog;
        }
        
        // Format 2: storage/logs/laravel-YYYY-MM-DD.log (flat file)
        $flatFileLog = $logPath . '/laravel-' . $date . '.log';
        if (File::exists($flatFileLog)) {
            return $flatFileLog;
        }
        
        return null;
    }
    
    /**
     * Get all available log dates from log files
     */
    private function getAvailableLogDates($logPath)
    {
        $dates = [];
        
        if (!File::isDirectory($logPath)) {
            return $dates;
        }
        
        // Check for flat file format: laravel-YYYY-MM-DD.log
        $files = File::files($logPath);
        foreach ($files as $file) {
            $filename = $file->getFilename();
            
            if (preg_match('/^laravel-(\d{4}-\d{2}-\d{2})\.log$/', $filename, $matches)) {
                $dates[] = $matches[1];
            }
        }
        
        // Check for directory-based format: YYYY-MM-DD/laravel.log
        $directories = File::directories($logPath);
        foreach ($directories as $directory) {
            $dirName = basename($directory);
            
            // Check if directory name is a valid date format YYYY-MM-DD
            if (preg_match('/^(\d{4}-\d{2}-\d{2})$/', $dirName, $matches)) {
                // Check if laravel.log exists in this directory
                if (File::exists($directory . '/laravel.log')) {
                    $dates[] = $matches[1];
                }
            }
        }
        
        // Remove duplicates and sort dates in descending order (newest first)
        $dates = array_unique($dates);
        rsort($dates);
        
        return $dates;
    }
    
    /**
     * Parse log file and extract log entries
     */
    private function parseLogFile($logFile, $levelFilter = 'all', $searchTerm = '')
    {
        $logs = [];
        
        if (!File::exists($logFile)) {
            return $logs;
        }
        
        $content = File::get($logFile);
        
        // Pattern to match Laravel log entries
        // Format: [YYYY-MM-DD HH:MM:SS] environment.LEVEL: message
        $pattern = '/\[(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})\]\s+(\w+)\.(\w+):\s+(.*?)(?=\[\d{4}-\d{2}-\d{2}|\z)/s';
        
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $timestamp = $match[1];
            $environment = $match[2];
            $level = strtoupper($match[3]);
            $message = trim($match[4]);
            
            // Apply level filter
            if ($levelFilter !== 'all' && strtoupper($levelFilter) !== $level) {
                continue;
            }
            
            // Apply search filter
            if (!empty($searchTerm) && stripos($message, $searchTerm) === false) {
                continue;
            }
            
            $logs[] = [
                'timestamp' => $timestamp,
                'environment' => $environment,
                'level' => $level,
                'message' => $message,
                'raw' => $match[0]
            ];
        }
        
        // Reverse to show newest first
        return array_reverse($logs);
    }
    
    /**
     * Download log file for a specific date
     */
    public function download(Request $request)
    {
        try {
            $date = $request->get('date', date('Y-m-d'));
            $logPath = storage_path('logs');
            $logFile = $this->findLogFile($logPath, $date);
            
            if (!$logFile || !File::exists($logFile)) {
                return redirect()->back()->with('error', 'Log file not found for date: ' . $date);
            }
            
            // Set a friendly filename for download
            $downloadName = 'laravel-' . $date . '.log';
            
            return response()->download($logFile, $downloadName);
        } catch (\Exception $e) {
            Log::error('Error downloading log: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to download log file.');
        }
    }
    
    /**
     * Delete log file for a specific date
     */
    public function delete(Request $request)
    {
        try {
            $date = $request->get('date', date('Y-m-d'));
            $logPath = storage_path('logs');
            $logFile = $this->findLogFile($logPath, $date);
            
            if (!$logFile || !File::exists($logFile)) {
                return redirect()->back()->with('error', 'Log file not found for date: ' . $date);
            }
            
            // If it's a directory-based log, delete the entire directory
            if (strpos($logFile, $date . '/laravel.log') !== false) {
                $directory = dirname($logFile);
                File::deleteDirectory($directory);
            } else {
                // If it's a flat file, just delete the file
                File::delete($logFile);
            }
            
            // return redirect()->route('admin.logs.index')
             return redirect()->route('admin.settings')
                ->with('success', 'Log file deleted successfully for date: ' . $date);
        } catch (\Exception $e) {
            Log::error('Error deleting log: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete log file.');
        }
    }
    
    // ---------------------------------------------------------------------
    //  Date -> Folder -> File browser (AJAX) + per-file download
    //  Lets admins drill into logs/{date}/{folder}/{file}.log and download
    //  any single component log (smpp/, rabbitmq/, cron/, or root laravel.log).
    // ---------------------------------------------------------------------

    /** Virtual folder key for files sitting directly under logs/{date} (e.g. laravel.log). */
    private const ROOT_FOLDER_KEY = '__root__';

    private function isValidDate(?string $d): bool
    {
        return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1;
    }

    /** A single path segment (folder or filename): letters, digits, dot, underscore, hyphen only. */
    private function isValidSegment(?string $s): bool
    {
        return is_string($s) && $s !== '' && !str_contains($s, '..')
            && preg_match('/^[A-Za-z0-9._-]+$/', $s) === 1;
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $pow = min($pow, count($units) - 1);
        $bytes /= (1024 ** $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * JSON: all date folders under storage/logs (newest first).
     */
    public function dates()
    {
        $root = storage_path('logs');
        $dates = [];
        if (File::isDirectory($root)) {
            foreach (File::directories($root) as $dir) {
                $name = basename($dir);
                if ($this->isValidDate($name)) {
                    $dates[] = $name;
                }
            }
            rsort($dates);
        }
        return response()->json(['dates' => $dates]);
    }

    /**
     * JSON: folders inside logs/{date} plus a virtual "(root)" entry for
     * files that sit directly under the date dir (e.g. laravel.log).
     */
    public function folders(Request $request)
    {
        $date = $request->get('date');
        if (!$this->isValidDate($date)) {
            return response()->json(['error' => 'Invalid date'], 422);
        }

        $base = storage_path('logs/' . $date);
        if (!File::isDirectory($base)) {
            return response()->json(['folders' => []]);
        }

        $folders = [];

        // Root-level files (laravel.log, heartbeat touchfiles, etc.)
        $rootFiles = File::files($base);
        if (count($rootFiles) > 0) {
            $folders[] = [
                'key'   => self::ROOT_FOLDER_KEY,
                'label' => '(root files)',
                'count' => count($rootFiles),
            ];
        }

        foreach (File::directories($base) as $dir) {
            $name = basename($dir);
            $folders[] = [
                'key'   => $name,
                'label' => $name,
                'count' => count(File::files($dir)),
            ];
        }

        return response()->json(['folders' => $folders]);
    }

    /**
     * JSON: files inside logs/{date}/{folder} (or root files when folder is the virtual key).
     */
    public function files(Request $request)
    {
        $date = $request->get('date');
        $folder = $request->get('folder');

        if (!$this->isValidDate($date)) {
            return response()->json(['error' => 'Invalid date'], 422);
        }

        if ($folder === self::ROOT_FOLDER_KEY) {
            $dir = storage_path('logs/' . $date);
        } else {
            if (!$this->isValidSegment($folder)) {
                return response()->json(['error' => 'Invalid folder'], 422);
            }
            $dir = storage_path('logs/' . $date . '/' . $folder);
        }

        if (!File::isDirectory($dir)) {
            return response()->json(['files' => []]);
        }

        $files = [];
        foreach (File::files($dir) as $f) {
            $files[] = [
                'name'     => $f->getFilename(),
                'size'     => $this->humanSize($f->getSize()),
                'modified' => date('Y-m-d H:i:s', $f->getMTime()),
            ];
        }
        usort($files, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return response()->json(['files' => $files]);
    }

    /**
     * Download a single file from logs/{date}/{folder}/{file}.
     * Hardened against path traversal: segments are regex-validated AND the
     * resolved realpath must stay inside storage/logs.
     */
    public function downloadFile(Request $request)
    {
        $date = $request->get('date');
        $folder = $request->get('folder');
        $file = $request->get('file');

        if (!$this->isValidDate($date) || !$this->isValidSegment($file)) {
            abort(404, 'Log file not found');
        }

        if ($folder === self::ROOT_FOLDER_KEY) {
            $path = storage_path('logs/' . $date . '/' . $file);
            $prefix = $date;
        } else {
            if (!$this->isValidSegment($folder)) {
                abort(404, 'Log file not found');
            }
            $path = storage_path('logs/' . $date . '/' . $folder . '/' . $file);
            $prefix = $date . '-' . $folder;
        }

        $base = realpath(storage_path('logs'));
        $real = realpath($path);
        if ($base === false || $real === false || strpos($real, $base) !== 0 || !is_file($real)) {
            abort(404, 'Log file not found');
        }

        return response()->download($real, $prefix . '-' . $file);
    }

    /**
     * Download logs as a ZIP archive.
     *
     * When a valid ?date=YYYY-MM-DD is given (the usual case — the button passes
     * the selected date), this zips ONLY that date's log folder,
     * storage/logs/{date}/ (recursively, preserving subfolders like smpp/,
     * rabbitmq/, plus all supervisor worker logs) AND the flat
     * storage/logs/laravel-{date}.log if it exists. File name: logs-{date}.zip.
     *
     * With no/invalid date it falls back to zipping the ENTIRE storage/logs dir.
     */
    public function downloadAllZip(Request $request)
    {
        $tmpZip = null;
        try {
            $logRoot = storage_path('logs');
            $date = $request->get('date');

            if (!File::isDirectory($logRoot)) {
                return redirect()->back()->with('error', 'Logs directory not found.');
            }

            if (!class_exists(\ZipArchive::class)) {
                return redirect()->back()->with('error', 'ZIP support (php-zip extension) is not available on the server.');
            }

            // Decide what to include.
            $dirsToZip  = []; // zipEntryPrefix => absoluteBaseDir (walked recursively)
            $filesToZip = []; // zipEntryName   => absoluteFilePath

            if ($this->isValidDate($date)) {
                // Date-scoped: the date folder + optional flat file for that date.
                $dateDir = $logRoot . DIRECTORY_SEPARATOR . $date;
                if (File::isDirectory($dateDir)) {
                    $dirsToZip[$date] = realpath($dateDir); // entries -> {date}/...
                }
                $flat = $logRoot . DIRECTORY_SEPARATOR . 'laravel-' . $date . '.log';
                if (File::exists($flat)) {
                    $filesToZip['laravel-' . $date . '.log'] = realpath($flat);
                }

                if (empty($dirsToZip) && empty($filesToZip)) {
                    return redirect()->back()->with('error', "No logs found for date: {$date}");
                }
                $zipName = 'logs-' . $date . '.zip';
            } else {
                // Fallback: whole logs directory.
                $dirsToZip[''] = realpath($logRoot);
                $zipName = 'logs-all-' . date('Y-m-d_His') . '.zip';
            }

            // Build the zip in the system temp dir so we never nest it inside logs/.
            $tmpZip = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . uniqid('logszip_', true) . '.zip';
            $zip = new \ZipArchive();
            if ($zip->open($tmpZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                return redirect()->back()->with('error', 'Could not create ZIP archive.');
            }

            $fileCount = 0;

            // Standalone files (e.g. the flat laravel-{date}.log).
            foreach ($filesToZip as $entryName => $abs) {
                if ($abs && is_file($abs)) {
                    $zip->addFile($abs, $entryName);
                    $fileCount++;
                }
            }

            // Directories, walked recursively (preserves subfolder structure).
            foreach ($dirsToZip as $prefix => $baseAbs) {
                if (!$baseAbs) {
                    continue;
                }
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($baseAbs, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($iterator as $item) {
                    $realPath = $item->getRealPath();
                    if ($realPath === false) {
                        continue;
                    }
                    $rel = ltrim(str_replace($baseAbs, '', $realPath), '/\\');
                    $rel = str_replace('\\', '/', $rel);
                    $entry = $prefix !== '' ? ($prefix . '/' . $rel) : $rel;

                    if ($item->isDir()) {
                        $zip->addEmptyDir($entry);
                    } else {
                        if ($realPath === realpath($tmpZip)) {
                            continue; // never zip the archive-in-progress
                        }
                        $zip->addFile($realPath, $entry);
                        $fileCount++;
                    }
                }
            }

            $zip->close();

            if ($fileCount === 0) {
                @unlink($tmpZip);
                return redirect()->back()->with('error', 'No log files found to download.');
            }

            return response()->download($tmpZip, $zipName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Error zipping logs: ' . $e->getMessage());
            if (!empty($tmpZip) && file_exists($tmpZip)) {
                @unlink($tmpZip);
            }
            return redirect()->back()->with('error', 'Failed to build logs ZIP: ' . $e->getMessage());
        }
    }

    /**
     * Clear all logs
     */
    public function clearAll(Request $request)
    {
        try {
            $logPath = storage_path('logs');
            $deletedCount = 0;
            
            // Delete flat file format logs
            $files = File::files($logPath);
            foreach ($files as $file) {
                if (preg_match('/^laravel-\d{4}-\d{2}-\d{2}\.log$/', $file->getFilename())) {
                    File::delete($file->getPathname());
                    $deletedCount++;
                }
            }
            
            // Delete directory-based logs
            $directories = File::directories($logPath);
            foreach ($directories as $directory) {
                $dirName = basename($directory);
                
                // Check if directory name is a valid date format YYYY-MM-DD
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dirName)) {
                    if (File::exists($directory . '/laravel.log')) {
                        File::deleteDirectory($directory);
                        $deletedCount++;
                    }
                }
            }
            
            return redirect()->route('admin.settings')
                ->with('success', "Successfully deleted {$deletedCount} log file(s).");
        } catch (\Exception $e) {
            Log::error('Error clearing all logs: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to clear log files.');
        }
    }
}
