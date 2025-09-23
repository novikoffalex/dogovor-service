<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SetupContractCounter extends Command
{
    protected $signature = 'contract:setup-counter';
    protected $description = 'Setup contract counter table if not exists';

    public function handle()
    {
        $this->info('Setting up contract counter...');

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
        } else {
            $this->info('Table already exists.');
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
        } else {
            $this->info("Counter for {$today} already exists with value {$counter->counter}");
        }

        $this->info('Contract counter setup completed!');
    }
}
