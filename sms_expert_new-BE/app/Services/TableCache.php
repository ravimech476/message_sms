<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * TableCache — event-driven (no-TTL) Redis cache for hot, rarely-changing tables.
 *
 * Strategy: each table is cached with NO expiry (Cache::rememberForever). The cached copy is
 * authoritative until a row is inserted / updated / deleted, at which point the caller MUST call
 * the matching rebuild*() method (which forgets the key so the next read reloads fresh data).
 * A daily `cache:rebuild-tables` cron also forgets everything as a safety net for edits made
 * directly in the database (bypassing the app write hooks).
 *
 * Key naming (per project convention): "table_<name>" for whole-table caches and
 * "table_<name>_<bigid>" for per-account slices. Laravel prepends CACHE_PREFIX (SMS_EXPERT_CACHE),
 * so the physical Redis key is e.g. "SMS_EXPERT_CACHE:table_country".
 *
 * Fail-safe: if Redis is down, rememberForever falls through to the DB — slower, never an outage.
 *
 * Phase 1 covers the per-SMS reference lookups: country, smsg_route, ofcom.
 */
class TableCache
{
    // ---- Key names (single source of truth) ----
    public const KEY_COUNTRY     = 'table_country';
    public const KEY_SMSG_ROUTE  = 'table_smsg_route';
    public const OFCOM_PREFIX    = 'table_ofcom_';     // + 4-digit prefix
    public const OFCOM_TAG       = 'table_ofcom';      // tag grouping all ofcom prefix keys
    public const USEROPTION_PREFIX = 'table_useroption_'; // + user bigid (Phase 2)
    public const USEROPTION_TAG    = 'table_useroption';  // tag grouping all per-account rows

    /** Whole-table reference keys refreshed by the daily cron / rebuild command. */
    public const REFERENCE_KEYS = [
        self::KEY_COUNTRY,
        self::KEY_SMSG_ROUTE,
    ];

    // =====================================================================
    // country  — read on every SMS (extractCountryCode)
    // =====================================================================

    /**
     * All country rows keyed by dialcode. Cached forever; rebuilt on any country write.
     *
     * @return \Illuminate\Support\Collection<string,object>
     */
    public function countries()
    {
        return Cache::rememberForever(self::KEY_COUNTRY, function () {
            return DB::table('country')->get()->keyBy('dialcode');
        });
    }

    /**
     * Longest-prefix country match for a phone number — replicates extractCountryCode's
     * "orderBy LENGTH(dialcode) DESC" behaviour against the cached map (checks 4→1 digit prefixes).
     *
     * @return object|null  the country row, or null if no dialcode matches
     */
    public function countryForNumber(string $phoneNumber)
    {
        $map = $this->countries();
        for ($len = 4; $len >= 1; $len--) {
            $prefix = substr($phoneNumber, 0, $len);
            if ($prefix !== '' && $map->has($prefix)) {
                return $map->get($prefix);
            }
        }
        return null;
    }

    public function rebuildCountries(): void
    {
        Cache::forget(self::KEY_COUNTRY);
    }

    // =====================================================================
    // smsg_route  — cheapest live cost price per country dialcode (CostPriceService)
    // =====================================================================

    /**
     * Map of countrydialcode => cheapest live costprice. Precomputed with the SAME filters
     * CostPriceService used (live, 0.001 < costprice < 0.5, cheapest first).
     *
     * @return \Illuminate\Support\Collection<string,float>
     */
    public function routeCheapestCostByDialcode()
    {
        return Cache::rememberForever(self::KEY_SMSG_ROUTE, function () {
            return DB::table('smsg_route')
                ->where('routestatus', 'live')
                ->whereNotNull('costprice')
                ->where('costprice', '>', 0.001)
                ->where('costprice', '<', 0.5)
                ->orderBy('costprice', 'asc')
                ->get(['countrydialcode', 'costprice'])
                ->groupBy('countrydialcode')
                ->map(fn ($rows) => (float) $rows->first()->costprice); // cheapest (already asc)
        });
    }

    /** Cheapest live cost price for a dialcode, or null if none. */
    public function cheapestRouteCost(string $countryCode): ?float
    {
        $map = $this->routeCheapestCostByDialcode();
        return $map->has($countryCode) ? $map->get($countryCode) : null;
    }

    public function rebuildRoutes(): void
    {
        Cache::forget(self::KEY_SMSG_ROUTE);
    }

    // =====================================================================
    // ofcom  — netid per 4-digit UK prefix (file-based, not a DB table)
    // =====================================================================

    /**
     * Ofcom netid for a MSISDN, cached forever per 4-digit prefix in Redis (cross-request),
     * layered on top of Ofcom's own in-process static cache. Source data is the static
     * ofcomranges.php file, so this only needs rebuilding when that file is updated.
     */
    public function ofcomNetId(string $msisdn): string
    {
        if (!preg_match('/^(?:0|\+?44)(7[0-9]{3})[0-9]{6,8}$/', trim($msisdn), $m)) {
            return \App\Support\Ofcom::netId($msisdn); // non-UK / invalid → let Ofcom return '99'
        }
        $prefix = $m[1];
        // Tagged so every ofcom prefix key can be flushed as one group (rebuildOfcom).
        return Cache::tags([self::OFCOM_TAG])->rememberForever(self::OFCOM_PREFIX . $prefix, function () use ($msisdn) {
            return \App\Support\Ofcom::netId($msisdn);
        });
    }

    /** Flush every ofcom prefix key in one call (source is the static ofcomranges.php file). */
    public function rebuildOfcom(): void
    {
        Cache::tags([self::OFCOM_TAG])->flush();
    }

    // =====================================================================
    // useroption  — per-account config, read on every send AND every DLR (Phase 2)
    // =====================================================================

    /**
     * The full useroption row for an account, cached forever per bigid. Config-only table
     * (sender flags, DLR push settings, shortcodes, notification prefs) — no wallet/counter —
     * and written only rarely (profile/DLR-config edits, out-of-funds), so cache-forever is safe.
     * Callers that only need a few columns still get the full row (a superset).
     *
     * @return object|null
     */
    public function useroption(string $bigid)
    {
        // Tagged so every account's row can be flushed as one group (daily safety net).
        return Cache::tags([self::USEROPTION_TAG])->rememberForever(self::USEROPTION_PREFIX . $bigid, function () use ($bigid) {
            return DB::table('useroption')->where('userref', $bigid)->first();
        });
    }

    /** Rebuild one account's useroption cache (call after any write to that account's row). */
    public function rebuildUseroption(string $bigid): void
    {
        Cache::tags([self::USEROPTION_TAG])->forget(self::USEROPTION_PREFIX . $bigid);
    }

    /** Flush every account's useroption cache — daily safety net for direct-DB edits. */
    public function rebuildAllUseroptions(): void
    {
        Cache::tags([self::USEROPTION_TAG])->flush();
    }

    // =====================================================================
    // Bulk rebuild (daily cron + manual command)
    // =====================================================================

    /** Forget the whole-table reference caches (country, smsg_route). Ofcom handled separately. */
    public function rebuildReferenceTables(): void
    {
        foreach (self::REFERENCE_KEYS as $key) {
            Cache::forget($key);
        }
    }
}
