<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class ServerController extends Controller
{
    public function executeCommand(Request $request)
    {
        $request->validate([
            'command' => 'required|string|in:migrate,cache:clear,config:clear,route:clear,view:clear,tinker,contract:setup-counter',
            'params' => 'nullable|array'
        ]);

        $command = $request->command;
        $params = $request->params ?? [];

        try {
            $exitCode = Artisan::call($command, $params);
            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'command' => $command,
                'exit_code' => $exitCode,
                'output' => $output,
                'params' => $params
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'command' => $command
            ], 500);
        }
    }

    public function getServerStatus()
    {
        try {
            $status = [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'storage' => $this->checkStorage(),
                'contract_counter' => $this->checkContractCounter(),
                'zamzar_jobs' => $this->checkZamzarJobs()
            ];

            return response()->json([
                'success' => true,
                'status' => $status,
                'timestamp' => now()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function setupContractCounter()
    {
        try {
            // Check if table exists
            if (!Schema::hasTable('contract_counters')) {
                DB::statement('CREATE TABLE IF NOT EXISTS contract_counters (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    date DATE UNIQUE NOT NULL,
                    counter INTEGER DEFAULT 0,
                    created_at DATETIME,
                    updated_at DATETIME
                )');
            }

            // Initialize today's counter
            $today = now()->toDateString();
            $counter = DB::table('contract_counters')
                ->where('date', $today)
                ->first();

            if (!$counter) {
                DB::table('contract_counters')->insert([
                    'date' => $today,
                    'counter' => 21,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Contract counter setup completed',
                'today' => $today,
                'counter' => $counter ? $counter->counter : 21
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function checkDatabase()
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'connected', 'driver' => DB::connection()->getDriverName()];
        } catch (\Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    private function checkCache()
    {
        try {
            cache()->put('test_key', 'test_value', 1);
            $value = cache()->get('test_key');
            return ['status' => $value === 'test_value' ? 'working' : 'error'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    private function checkStorage()
    {
        try {
            $storagePath = storage_path();
            $isWritable = is_writable($storagePath);
            return [
                'status' => $isWritable ? 'writable' : 'readonly',
                'path' => $storagePath
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    private function checkContractCounter()
    {
        try {
            if (Schema::hasTable('contract_counters')) {
                $today = now()->toDateString();
                $counter = DB::table('contract_counters')
                    ->where('date', $today)
                    ->first();
                return [
                    'status' => 'database',
                    'today' => $today,
                    'counter' => $counter ? $counter->counter : 0
                ];
            } else {
                // Check file counter
                $today = now()->format('Ymd');
                $counterFile = storage_path("app/contract_counter_{$today}.txt");
                if (file_exists($counterFile)) {
                    $counter = (int)file_get_contents($counterFile);
                    return [
                        'status' => 'file',
                        'today' => $today,
                        'counter' => $counter
                    ];
                }
                return ['status' => 'none'];
            }
        } catch (\Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    private function checkZamzarJobs()
    {
        try {
            $jobsFile = storage_path('app/zamzar_jobs.json');
            if (file_exists($jobsFile)) {
                $jobs = json_decode(file_get_contents($jobsFile), true);
                $processing = count(array_filter($jobs['jobs'] ?? [], function($job) {
                    return $job['status'] === 'processing';
                }));
                return [
                    'status' => 'exists',
                    'total_jobs' => count($jobs['jobs'] ?? []),
                    'processing' => $processing
                ];
            }
            return ['status' => 'not_exists'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }
}
