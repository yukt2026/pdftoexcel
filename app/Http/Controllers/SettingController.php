<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSettingsRequest;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettingController extends Controller
{
    public function index()
    {
        // 1. All settings mapped by key for quick lookup
        $rawSettings = Setting::all();
        $settings = [];
        foreach ($rawSettings as $s) {
            $settings[$s->key] = $s->value;
        }

        // 2. Real-time System Status Metrics
        $dbConnected = false;
        $dbVersion = 'Unknown';
        try {
            DB::connection()->getPdo();
            $dbConnected = true;
            $driver = DB::connection()->getDriverName();
            if ($driver === 'mysql') {
                $v = DB::select('SELECT VERSION() as version');
                $dbVersion = $v[0]->version ?? 'MySQL';
            } else {
                $dbVersion = 'SQLite ' . ($driver);
            }
        } catch (Exception $e) {
            $dbConnected = false;
        }

        $pendingJobs = 0;
        $failedJobs = 0;
        try {
            if (Schema::hasTable('jobs')) {
                $pendingJobs = DB::table('jobs')->count();
            }
            if (Schema::hasTable('failed_jobs')) {
                $failedJobs = DB::table('failed_jobs')->count();
            }
        } catch (Exception) {
            // Ignore if tables not present
        }

        $storagePath = storage_path('app');
        $isStorageWritable = is_writable($storagePath);

        $systemStatus = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database_connected' => $dbConnected,
            'database_driver' => DB::connection()->getDriverName(),
            'database_version' => $dbVersion,
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
            'storage_writable' => $isStorageWritable,
            'os' => PHP_OS_FAMILY . ' (' . php_uname('s') . ')',
            'environment' => app()->environment(),
        ];

        // 3. User Metrics
        $userMetrics = [
            'total_users' => User::count(),
            'admins' => User::where('role', User::ROLE_ADMIN)->count(),
            'active_users' => User::where('status', 'active')->count(),
        ];

        return view('settings.index', compact('settings', 'systemStatus', 'userMetrics'));
    }

    public function update(SaveSettingsRequest $request)
    {
        $groupMappings = [
            'gemini_api_key' => 'ai',
            'gemini_model' => 'ai',
            'gemini_temperature' => 'ai',
            'ocr_engine' => 'ocr',
            'ocr_credentials_path' => 'ocr',
            'max_upload_size_mb' => 'upload',
            'max_files_per_batch' => 'upload',
            'queue_connection' => 'queue',
            'queue_timeout' => 'queue',
            'queue_max_retries' => 'queue',
            'export_filename_prefix' => 'export',
            'export_default_format' => 'export',
            'export_auto_freeze_header' => 'export',
            'export_include_summary_sheet' => 'export',
            'app_name' => 'general',
            'brand_primary_color' => 'general',
            'company_name' => 'general',
            'matching_tolerance_percent' => 'matching',
        ];

        foreach ($request->settings as $key => $value) {
            $group = $groupMappings[$key] ?? 'general';

            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => (string) $value,
                    'group' => $group,
                ]
            );
        }

        ActivityLog::record('settings_updated', 'System application and configuration settings were updated by ' . auth()->user()->name);

        return back()->with('success', 'Application settings saved successfully.');
    }
}
