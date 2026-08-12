<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Contract;
use App\Models\ContractSignature;

class VerifyContractSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:verify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify contract system installation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('==============================================');
        $this->info('  Contract Management System - Verification');
        $this->info('==============================================');
        $this->newLine();

        $allGood = true;

        // Check contracts table
        $this->line('Checking contracts table...');
        if (Schema::hasTable('contracts')) {
            $this->info('  ✓ contracts table exists');
            $columns = Schema::getColumnListing('contracts');
            $this->line('  Columns: ' . implode(', ', $columns));
            
            $count = Contract::count();
            $this->line("  Records: {$count} contract(s)");
        } else {
            $this->error('  ✗ contracts table NOT FOUND');
            $allGood = false;
        }

        $this->newLine();

        // Check contract_signatures table
        $this->line('Checking contract_signatures table...');
        if (Schema::hasTable('contract_signatures')) {
            $this->info('  ✓ contract_signatures table exists');
            $columns = Schema::getColumnListing('contract_signatures');
            $this->line('  Columns: ' . implode(', ', $columns));
            
            $count = ContractSignature::count();
            $this->line("  Records: {$count} signature(s)");
        } else {
            $this->error('  ✗ contract_signatures table NOT FOUND');
            $allGood = false;
        }

        $this->newLine();

        // Check foreign keys
        $this->line('Checking foreign key constraints...');
        try {
            $foreignKeys = DB::select("
                SELECT 
                    CONSTRAINT_NAME,
                    TABLE_NAME,
                    COLUMN_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'contract_signatures'
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            if (count($foreignKeys) >= 2) {
                $this->info('  ✓ Foreign key constraints exist');
                foreach ($foreignKeys as $fk) {
                    $this->line("    - {$fk->COLUMN_NAME} → {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME}");
                }
            } else {
                $this->warn('  ! Foreign key constraints might be missing');
            }
        } catch (\Exception $e) {
            $this->warn('  ! Could not check foreign keys: ' . $e->getMessage());
        }

        $this->newLine();

        // Check routes file
        $this->line('Checking routes file...');
        $routesFile = base_path('routes/contracts_routes.php');
        if (file_exists($routesFile)) {
            $this->info('  ✓ contracts_routes.php exists');
            
            // Check if it's included in web.php
            $webRoutes = file_get_contents(base_path('routes/web.php'));
            if (strpos($webRoutes, 'contracts_routes.php') !== false) {
                $this->info('  ✓ Routes included in web.php');
            } else {
                $this->warn('  ! Routes NOT included in web.php');
                $this->line('    Add this line to routes/web.php:');
                $this->line('    require __DIR__.\'/contracts_routes.php\';');
                $allGood = false;
            }
        } else {
            $this->error('  ✗ contracts_routes.php NOT FOUND');
            $allGood = false;
        }

        $this->newLine();

        // Check models
        $this->line('Checking models...');
        if (class_exists('App\Models\Contract')) {
            $this->info('  ✓ Contract model exists');
        } else {
            $this->error('  ✗ Contract model NOT FOUND');
            $allGood = false;
        }

        if (class_exists('App\Models\ContractSignature')) {
            $this->info('  ✓ ContractSignature model exists');
        } else {
            $this->error('  ✗ ContractSignature model NOT FOUND');
            $allGood = false;
        }

        $this->newLine();

        // Check controllers
        $this->line('Checking controllers...');
        if (class_exists('App\Http\Controllers\Admin\ContractController')) {
            $this->info('  ✓ Admin ContractController exists');
        } else {
            $this->error('  ✗ Admin ContractController NOT FOUND');
            $allGood = false;
        }

        if (class_exists('App\Http\Controllers\Customer\ContractController')) {
            $this->info('  ✓ Customer ContractController exists');
        } else {
            $this->error('  ✗ Customer ContractController NOT FOUND');
            $allGood = false;
        }

        $this->newLine();

        // Check views
        $this->line('Checking views...');
        $views = [
            'admin/contracts/index.blade.php',
            'admin/contracts/create.blade.php',
            'admin/contracts/edit.blade.php',
            'admin/contracts/signatures.blade.php',
            'customer/contracts/index.blade.php',
            'customer/contracts/show.blade.php',
        ];

        $viewsExist = 0;
        foreach ($views as $view) {
            $viewPath = resource_path('views/' . $view);
            if (file_exists($viewPath)) {
                $viewsExist++;
            }
        }

        if ($viewsExist === count($views)) {
            $this->info("  ✓ All {$viewsExist} view files exist");
        } else {
            $this->warn("  ! Only {$viewsExist} of " . count($views) . " view files found");
            $allGood = false;
        }

        $this->newLine();
        $this->info('==============================================');
        
        if ($allGood) {
            $this->info('  ✓✓✓ ALL CHECKS PASSED! ✓✓✓');
            $this->newLine();
            $this->line('Your contract system is ready to use!');
            $this->newLine();
            $this->line('Admin URL: <fg=cyan>' . url('/admin/contracts') . '</>');
            $this->line('Customer URL: <fg=cyan>' . url('/customer/contracts') . '</>');
        } else {
            $this->error('  ✗ SOME CHECKS FAILED');
            $this->newLine();
            $this->line('Please fix the issues above and run this command again.');
            $this->line('For help, check: DATABASE_FIX_GUIDE.md');
        }

        $this->newLine();
        $this->info('==============================================');

        return $allGood ? 0 : 1;
    }
}
