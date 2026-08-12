<?php

namespace App\Http\Controllers\Admin\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Provides the OLD SYSTEM customer-listing query.
 * Used by every admin dropdown / customer page / email menu
 * so the customer set is identical across the admin UI.
 */
trait LegacyCustomerList
{
    /**
     * Run the legacy customer-listing SQL and return the result as a collection
     * of stdClass rows (one row per user, grouped by un.users_bigid).
     *
     * The SQL keeps its original CRM-priority ORDER BY for compatibility, but
     * we re-sort in PHP into plain alphabetical order — that's what every
     * admin dropdown / customer page / email menu in the new system wants.
     * Sort key falls through busname → contactname → uname, urldecoded and
     * case-insensitive, with empty keys pushed to the bottom.
     *
     * If the legacy query returns zero rows (typical on dev environments
     * where users_notes / useroption / lab2id aren't populated, or after a
     * fresh install), fall back to a plain customers SELECT so the admin
     * UI is never empty. The fallback returns the same column shape as the
     * legacy query so views keep working.
     */
    protected function getLegacyCustomers()
    {
        $rows = [];

        try {
            $rows = DB::select($this->legacyCustomerListSql());
        } catch (\Throwable $e) {
            // Legacy SQL can throw on environments where users_notes /
            // useroption / users.lab2id / users.migration_flag / users.status
            // don't exist (older legacy schema, missed migration, dev box).
            // Log the real reason then fall through to the basic SELECT
            // instead of bubbling a 500 to every admin dropdown.
            Log::warning('LegacyCustomerList: legacy SQL failed, falling back', [
                'error' => $e->getMessage(),
            ]);
        }

        if (empty($rows)) {
            try {
                $rows = DB::select($this->fallbackCustomerListSql());
            } catch (\Throwable $e) {
                // If even the fallback SELECT throws (e.g. users.login_type
                // missing), return an empty collection so callers see "no
                // customers" instead of a 500. The log line will tell us
                // what the box is actually missing.
                Log::error('LegacyCustomerList: fallback SQL also failed', [
                    'error' => $e->getMessage(),
                ]);
                $rows = [];
            }
        }

        return collect($rows)
            ->sortBy(function ($c) {
                $key = $c->busname ?? ($c->contactname ?? ($c->uname ?? ''));
                $key = mb_strtolower(trim(urldecode((string) $key)));
                // Bottom-sort empties so "Acme Ltd" beats "" in the list.
                return $key === '' ? '~' : $key;
            }, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Fallback SQL — basic customer listing without the legacy CRM joins.
     * Used when the strict legacy query returns nothing (dev envs, missing
     * users_notes/useroption, lab2id not set).
     *
     * Returns the same columns as legacyCustomerListSql() so consumer code
     * doesn't have to branch — missing legacy columns (usernotes,
     * usernotesid, nextcontactdate-derived fields) are NULL/blank.
     */
    protected function fallbackCustomerListSql(): string
    {
        // Only SELECT columns that exist on every customer-table schema
        // (legacy iTAGG + new Laravel install). Columns that may be missing
        // on a fresh / dev DB (profileupdate_lockout, clientcommfail,
        // do_ip_check, user_type, mobilenumber, datejoined) are aliased as
        // NULL so the column shape stays consistent for any view code that
        // expects them.
        return <<<'SQL'
SELECT uname, contactname, busname, contactemail, bigid,
       id AS id, bit_disabled, migration_flag, customer_type,
       masteruname,
       NULL AS mobilenumber,
       NULL AS do_ip_check,
       NULL AS user_type,
       NULL AS profileupdate_lockout,
       NULL AS clientcommfail,
       NULL AS nextcontactdate,
       NULL AS datejoined,
       NULL AS phone,
       NULL AS timelength,
       NULL AS usernotes,
       NULL AS usernotesid,
       NULL AS thenextcontactdate,
       NULL AS crmcategory
FROM users
WHERE (login_type IS NULL OR login_type = 'customer' OR login_type = '')
  AND COALESCE(bit_disabled, 0) = 0
ORDER BY busname ASC
SQL;
    }

    /**
     * The raw SQL preserved verbatim from the OLD SYSTEM customer listing.
     * Do not "tidy up" — the right(nextcontactdate,4) mappings and
     * lab2id LIKE '%itagg%' filter are load-bearing for CRM ordering.
     */
    protected function legacyCustomerListSql(): string
    {
        return <<<'SQL'
SELECT uname, contactname, busname, contactemail, mobilenumber, bigid,
       users.masteruname as masteruname,
       users.id as id, bit_disabled, do_ip_check, user_type,
       profileupdate_lockout, clientcommfail, nextcontactdate, datejoined,
       phone, timelength, users.migration_flag, users.customer_type,
       un.notes as usernotes, un.id as usernotesid,
       left(nextcontactdate, 8) as thenextcontactdate,
       if(right(nextcontactdate, 4) = '0600' and un.notes <> 'CONTACT (VERY URGENT)', '0601',
       if(right(nextcontactdate, 4) = '0559', '0445',
       if(right(nextcontactdate, 4) = '2025', '0447',
       if(right(nextcontactdate, 4) = '0500', '2122',
       if(right(nextcontactdate, 4) = '2140', '2120',
       if(right(nextcontactdate, 4) = '2130', '0446',
       if(right(nextcontactdate, 4) = '1900', '0600',
       if(right(nextcontactdate, 4) = '1935', '2124',
       if(right(nextcontactdate, 4) = '1937', '0603',
       if(right(nextcontactdate, 4) = '1939', '0602',
       if(right(nextcontactdate, 4) = '1945', '2127',
       right(nextcontactdate, 4)))))))))))) as crmcategory
FROM users_notes as un, users, useroption
WHERE users.lab2id like '%itagg%'
  AND users.bigid = useroption.userref
  AND users.bigid = un.users_bigid
  AND users.status = '1'
  AND un.nextcontactdate <= '202712310000'
  AND un.nextcontactdate <> '202601012100'
  AND un.nextcontactdate <> ''
  AND (
    (users.bigid NOT IN (SELECT distinct u.bigid FROM users as u LEFT JOIN users_notes as un ON u.bigid = un.users_bigid WHERE u.status ='1' and un.notes in ('usr8', 'nousr8') or un.notes like 'ust%' or un.notes like 'noust%'))
    OR
    (right(nextcontactdate, 4) NOT IN ('2000','2100','1938','0600'))
  )
GROUP BY un.users_bigid
ORDER BY thenextcontactdate asc, crmcategory asc
SQL;
    }
}
