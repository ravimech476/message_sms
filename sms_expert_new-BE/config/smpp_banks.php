<?php

/*
|------------------------------------------------------------------------------
| SMPP Multi-Bank Bind Configuration (Vonage)
|------------------------------------------------------------------------------
|
| Mirrors the OLD SYSTEM multi-bind architecture from
| smsexpert_oldsystem/sites/thisserver_details.inc:363-553 — updated for
| the modern Vonage SMPP gateway.
|
| Vonage exposes THREE EU SMPP hosts (verified via DNS 2026-06-05):
|
|     smpp-eu.vonage.com       → 216.147.33.1
|     smpp-eu-1.vonage.com     → 216.147.33.2
|     smpp-eu-2.vonage.com     → 216.147.33.3
|
| (smpp-eu-3 and smpp-eu-4 DO NOT EXIST in DNS — don't put them in config.)
|
| Each "bank" is a separate SMPP transceiver bind to Vonage using the SAME
| system_id, but partitioning the SMPP sequence_number space so DLRs route
| back to the bind that originally sent the submit_sm.
|
| 10 banks spread across 3 hosts so no single Vonage POP carries all the
| traffic — same load-distribution pattern OLD SYSTEM used with 2 hosts,
| now using all 3 available EU hosts:
|
|     Host 1 (smpp-eu):    a0, b0, c0, d0   — 4 banks
|     Host 2 (smpp-eu-1):  e0, f0, g0       — 3 banks
|     Host 3 (smpp-eu-2):  h0, i0, j0       — 3 banks
|
| REQUIREMENT: Vonage must have multi-bind / concurrent-session mode enabled
| on the SMPP account for this to work. Standard single-bind accounts will
| reject the second bind with ESME_RALYBND (0x05) "Already in bound state".
| Confirm with Vonage before enabling more than one bank in supervisor.
|
| When SMPP_BANKS_ENABLED=false (default) the system falls back to the
| original single-bind path driven by SMPP_HOST / SMPP_SYSTEM_ID etc.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Master Switch
    |--------------------------------------------------------------------------
    |
    | Set SMPP_BANKS_ENABLED=true in .env once Vonage has provisioned multi-bind
    | on the account AND you have the supervisor entries running. Until then,
    | leave it off and the SMPPService stays on the single-bind code path.
    |
    */

    'enabled' => env('SMPP_BANKS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Default bank
    |--------------------------------------------------------------------------
    |
    | Used when `smpp:dlr-receiver` is invoked without --bank=<key>. Mostly
    | useful for ad-hoc testing; production should always pass --bank.
    |
    */

    'default' => env('SMPP_BANK_DEFAULT', 'a0'),

    /*
    |--------------------------------------------------------------------------
    | Bank definitions
    |--------------------------------------------------------------------------
    |
    | seq_id_range partitions the SMPP sequence_number space across the bound
    | sessions. With 200 PDUs/sec and a 2,000,000-wide range, each bank wraps
    | every ~3 hours — comfortably longer than any reasonable DLR turnaround.
    |
    | system_type is what the bind PDU sends as the third NULL-terminated
    | string. Vonage routes the bind to the matching route (e.g. 'smppBK1P3' =
    | bank 1 priority 3). Confirm the correct value with Vonage support before
    | changing the defaults.
    |
    | Host distribution falls back through this chain per bank:
    |   SMPP_BANK_<key>_HOST → SMPP_HOST_<N> → SMPP_HOST → hardcoded default
    | so you can override at any level. The hardcoded defaults match Vonage's
    | actually-resolvable EU SMPP hostnames.
    |
    | All banks below default to the same systemId/password (read from .env);
    | override per-bank by setting SMPP_BANK_<key>_SYSTEM_ID etc. if your
    | Vonage provisioning splits credentials across banks.
    |
    */

    'banks' => [
        // Host 1: smpp-eu.vonage.com (216.147.33.1)
        'a0' => [
            'host'         => env('SMPP_BANK_A0_HOST', env('SMPP_HOST_1', env('SMPP_HOST', 'smpp-eu.vonage.com'))),
            'port'         => (int) env('SMPP_BANK_A0_PORT', env('SMPP_PORT', 8000)),
            'system_id'    => env('SMPP_BANK_A0_SYSTEM_ID', env('SMPP_SYSTEM_ID')),
            'password'     => env('SMPP_BANK_A0_PASSWORD', env('SMPP_PASSWORD')),
            'system_type'  => env('SMPP_BANK_A0_SYSTEM_TYPE', env('SMPP_TYPE', 'smppBK1P3')),
            'seq_id_range' => [1, 2_000_000],
        ],
        'b0' => [
            'host'         => env('SMPP_BANK_B0_HOST', env('SMPP_HOST_1', env('SMPP_HOST', 'smpp-eu.vonage.com'))),
            'port'         => (int) env('SMPP_BANK_B0_PORT', env('SMPP_PORT', 8000)),
            'system_id'    => env('SMPP_BANK_B0_SYSTEM_ID', env('SMPP_SYSTEM_ID')),
            'password'     => env('SMPP_BANK_B0_PASSWORD', env('SMPP_PASSWORD')),
            'system_type'  => env('SMPP_BANK_B0_SYSTEM_TYPE', env('SMPP_TYPE', 'smppBK1P3')),
            'seq_id_range' => [2_000_001, 4_000_000],
        ],
        'c0' => [
            'host'         => env('SMPP_BANK_C0_HOST', env('SMPP_HOST_1', env('SMPP_HOST', 'smpp-eu.vonage.com'))),
            'port'         => (int) env('SMPP_BANK_C0_PORT', env('SMPP_PORT', 8000)),
            'system_id'    => env('SMPP_BANK_C0_SYSTEM_ID', env('SMPP_SYSTEM_ID')),
            'password'     => env('SMPP_BANK_C0_PASSWORD', env('SMPP_PASSWORD')),
            'system_type'  => env('SMPP_BANK_C0_SYSTEM_TYPE', env('SMPP_TYPE', 'smppBK1P3')),
            'seq_id_range' => [4_000_001, 6_000_000],
        ],
        'd0' => [
            'host'         => env('SMPP_BANK_D0_HOST', env('SMPP_HOST_1', env('SMPP_HOST', 'smpp-eu.vonage.com'))),
            'port'         => (int) env('SMPP_BANK_D0_PORT', env('SMPP_PORT', 8000)),
            'system_id'    => env('SMPP_BANK_D0_SYSTEM_ID', env('SMPP_SYSTEM_ID')),
            'password'     => env('SMPP_BANK_D0_PASSWORD', env('SMPP_PASSWORD')),
            'system_type'  => env('SMPP_BANK_D0_SYSTEM_TYPE', env('SMPP_TYPE', 'smppBK1P3')),
            'seq_id_range' => [6_000_001, 8_000_000],
        ],

        // Host 2: smpp-eu-1.vonage.com (216.147.33.2)
        'e0' => [
            'host'         => env('SMPP_BANK_E0_HOST', env('SMPP_HOST_2', 'smpp-eu-1.vonage.com')),
            'port'         => (int) env('SMPP_BANK_E0_PORT', env('SMPP_PORT', 8000)),
            'system_id'    => env('SMPP_BANK_E0_SYSTEM_ID', env('SMPP_SYSTEM_ID')),
            'password'     => env('SMPP_BANK_E0_PASSWORD', env('SMPP_PASSWORD')),
            'system_type'  => env('SMPP_BANK_E0_SYSTEM_TYPE', env('SMPP_TYPE', 'smppBK1P3')),
            'seq_id_range' => [8_000_001, 10_000_000],
        ],
        'f0' => [
            'host'         => env('SMPP_BANK_F0_HOST', env('SMPP_HOST_2', 'smpp-eu-1.vonage.com')),
            'port'         => (int) env('SMPP_BANK_F0_PORT', env('SMPP_PORT', 8000)),
            'system_id'    => env('SMPP_BANK_F0_SYSTEM_ID', env('SMPP_SYSTEM_ID')),
            'password'     => env('SMPP_BANK_F0_PASSWORD', env('SMPP_PASSWORD')),
            'system_type'  => env('SMPP_BANK_F0_SYSTEM_TYPE', env('SMPP_TYPE', 'smppBK1P3')),
            'seq_id_range' => [10_000_001, 12_000_000],
        ],
        'g0' => [
            'host'         => env('SMPP_BANK_G0_HOST', env('SMPP_HOST_2', 'smpp-eu-1.vonage.com')),
            'port'         => (int) env('SMPP_BANK_G0_PORT', env('SMPP_PORT', 8000)),
            'system_id'    => env('SMPP_BANK_G0_SYSTEM_ID', env('SMPP_SYSTEM_ID')),
            'password'     => env('SMPP_BANK_G0_PASSWORD', env('SMPP_PASSWORD')),
            'system_type'  => env('SMPP_BANK_G0_SYSTEM_TYPE', env('SMPP_TYPE', 'smppBK1P3')),
            'seq_id_range' => [12_000_001, 14_000_000],
        ],

        // Host 3: smpp-eu-2.vonage.com (216.147.33.3)
        'h0' => [
            'host'         => env('SMPP_BANK_H0_HOST', env('SMPP_HOST_3', 'smpp-eu-2.vonage.com')),
            'port'         => (int) env('SMPP_BANK_H0_PORT', env('SMPP_PORT', 8000)),
            'system_id'    => env('SMPP_BANK_H0_SYSTEM_ID', env('SMPP_SYSTEM_ID')),
            'password'     => env('SMPP_BANK_H0_PASSWORD', env('SMPP_PASSWORD')),
            'system_type'  => env('SMPP_BANK_H0_SYSTEM_TYPE', env('SMPP_TYPE', 'smppBK1P3')),
            'seq_id_range' => [14_000_001, 16_000_000],
        ],
        'i0' => [
            'host'         => env('SMPP_BANK_I0_HOST', env('SMPP_HOST_3', 'smpp-eu-2.vonage.com')),
            'port'         => (int) env('SMPP_BANK_I0_PORT', env('SMPP_PORT', 8000)),
            'system_id'    => env('SMPP_BANK_I0_SYSTEM_ID', env('SMPP_SYSTEM_ID')),
            'password'     => env('SMPP_BANK_I0_PASSWORD', env('SMPP_PASSWORD')),
            'system_type'  => env('SMPP_BANK_I0_SYSTEM_TYPE', env('SMPP_TYPE', 'smppBK1P3')),
            'seq_id_range' => [16_000_001, 18_000_000],
        ],
        'j0' => [
            'host'         => env('SMPP_BANK_J0_HOST', env('SMPP_HOST_3', 'smpp-eu-2.vonage.com')),
            'port'         => (int) env('SMPP_BANK_J0_PORT', env('SMPP_PORT', 8000)),
            'system_id'    => env('SMPP_BANK_J0_SYSTEM_ID', env('SMPP_SYSTEM_ID')),
            'password'     => env('SMPP_BANK_J0_PASSWORD', env('SMPP_PASSWORD')),
            'system_type'  => env('SMPP_BANK_J0_SYSTEM_TYPE', env('SMPP_TYPE', 'smppBK1P3')),
            'seq_id_range' => [18_000_001, 20_000_000],
        ],
    ],

];
