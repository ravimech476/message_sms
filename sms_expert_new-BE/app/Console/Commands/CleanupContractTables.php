<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CleanupContractTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up contract tables (drops contract_signatures and contracts tables)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->confirm('This will drop the contracts and contract_signatures tables. Do you want to continue?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $this->info('Starting cleanup...');

        try {
            // Disable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            $this->line('Disabled foreign key checks');

            // Drop contract_signatures table
            if (Schema::hasTable('contract_signatures')) {
                Schema::drop('contract_signatures');
                $this->info('✓ Dropped contract_signatures table');
            } else {
                $this->line('- contract_signatures table does not exist');
            }

            // Drop contracts table
            if (Schema::hasTable('contracts')) {
                Schema::drop('contracts');
                $this->info('✓ Dropped contracts table');
            } else {
                $this->line('- contracts table does not exist');
            }

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->line('Re-enabled foreign key checks');

            $this->newLine();
            $this->info('✓ Cleanup complete!');
            $this->newLine();
            $this->line('Next step: Run <fg=green>php artisan migrate</> to create the tables again.');

            return 0;
        } catch (\Exception $e) {
            $this->error('Error during cleanup: ' . $e->getMessage());
            
            // Make sure to re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            
            return 1;
        }
    }
}
