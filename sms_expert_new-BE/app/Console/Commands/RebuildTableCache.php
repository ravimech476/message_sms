<?php

namespace App\Console\Commands;

use App\Services\TableCache;
use Illuminate\Console\Command;

/**
 * Rebuild (forget) the TableCache reference caches so the next read reloads fresh data.
 *
 * Run daily by the scheduler as a safety net for rows edited DIRECTLY in the database
 * (which bypass the in-app rebuild hooks), and available manually after such edits:
 *
 *   php artisan cache:rebuild-tables            # everything
 *   php artisan cache:rebuild-tables country    # one table
 *   php artisan cache:rebuild-tables ofcom      # ofcom prefix keys
 */
class RebuildTableCache extends Command
{
    protected $signature = 'cache:rebuild-tables {table? : country|smsg_route|ofcom|useroption (omit for all)}';
    protected $description = 'Forget the no-TTL TableCache caches (country, smsg_route, ofcom, useroption) so they reload fresh';

    public function handle(TableCache $cache): int
    {
        $table = $this->argument('table');

        if ($table === null || $table === 'country') {
            $cache->rebuildCountries();
            $this->info('Rebuilt: country (' . TableCache::KEY_COUNTRY . ')');
        }

        if ($table === null || $table === 'smsg_route') {
            $cache->rebuildRoutes();
            $this->info('Rebuilt: smsg_route (' . TableCache::KEY_SMSG_ROUTE . ')');
        }

        if ($table === null || $table === 'ofcom') {
            $cache->rebuildOfcom();
            $this->info('Rebuilt: ofcom (all prefix keys cleared)');
        }

        if ($table === null || $table === 'useroption') {
            $cache->rebuildAllUseroptions();
            $this->info('Rebuilt: useroption (all per-account keys cleared)');
        }

        return self::SUCCESS;
    }
}
