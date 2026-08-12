<?php

namespace App\Support;

/**
 * Ofcom UK mobile-number → network id lookup.
 *
 * Thin wrapper around the OLD SYSTEM ofcomranges.inc (ported verbatim in this folder) so
 * smsg_log.ofcomnetid is populated exactly like the old system: regex-extract the 7XXX prefix,
 * look it up in the Ofcom range table, and map the operator to a netid
 * (10=O2, 15=Vodafone, 20=3G UK, 30=T Mobile, 33=Orange, 98=Other, 99=unknown).
 *
 * The old ofcom() re-parses a ~1,160-line heredoc on every call; since the same 4-digit prefix
 * repeats across a batch, we cache the result per prefix so bulk sends parse each prefix once.
 */
class Ofcom
{
    /** @var array<string, array{0:string,1:string}> prefix => [netid, netname] */
    private static array $cache = [];

    /**
     * Return the Ofcom netid for a MSISDN (e.g. '10', '15', … '99'). Matches OLD SYSTEM ofcom().
     */
    public static function netId(string $msisdn): string
    {
        return self::lookup($msisdn)[0];
    }

    /**
     * @return array{0:string,1:string} [netid, netname]
     */
    public static function lookup(string $msisdn): array
    {
        $msisdn = trim($msisdn);

        // Same shape as the old regex; extract the 4-digit prefix for cache keying.
        if (!preg_match('/^(?:0|\+?44)(7[0-9]{3})[0-9]{6,8}$/', $msisdn, $m)) {
            return ['99', 'unknown'];
        }

        $prefix = $m[1];
        if (isset(self::$cache[$prefix])) {
            return self::$cache[$prefix];
        }

        try {
            require_once __DIR__ . '/ofcomranges.php';
            if (function_exists('ofcom')) {
                $result = \ofcom($msisdn); // [netid, netname] — old-system logic verbatim
                if (is_array($result) && count($result) === 2) {
                    return self::$cache[$prefix] = [(string) $result[0], (string) $result[1]];
                }
            }
        } catch (\Throwable $e) {
            // fall through to unknown
        }

        return self::$cache[$prefix] = ['99', 'unknown'];
    }
}
