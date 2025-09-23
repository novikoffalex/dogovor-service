<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckAndSetupCounter extends Command
{
    protected $signature = 'contract:check-setup';
    protected $description = 'Check and setup contract counter if needed';

    public function handle()
    {
        // Check if table exists
        if (!Schema::hasTable('contract_counters')) {
            $this->info('Creating contract_counters table...');
            
            // Create table manually
            DB::statement('CREATE TABLE IF NOT EXISTS contract_counters (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                date DATE UNIQUE NOT NULL,
                counter INTEGER DEFAULT 0,
                created_at DATETIME,
                updated_at DATETIME
            )');
            
            $this->info('Table created successfully!');
        }

        // Initialize today's counter
        $today = now()->toDateString();
        $counter = DB::table('contract_counters')
            ->where('date', $today)
            ->first();

        if (!$counter) {
            DB::table('contract_counters')->insert([
                'date' => $today,
                'counter' => 21, // Set to current server state
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $this->info("Initialized counter for {$today} with value 21");
        }

        $this->info('Contract counter check completed!');
    }
}
