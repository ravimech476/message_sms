<?php

/*
|------------------------------------------------------------------------------
| SMPP Multi-Bank Bind Configuration (Vonage) — ported from sms_expert
|------------------------------------------------------------------------------
| 10 parallel SMPP binds (a0..j0) to the SAME Vonage system_id, each owning a
| non-overlapping SMPP sequence_number range so DLRs route back to the bind that
| sent the original submit_sm. Spread across Vonage's 3 EU hosts:
|
|     Host 1 (smpp-eu):    a0, b0, c0, d0
|     Host 2 (smpp-eu-1):  e0, f0, g0
|     Host 3 (smpp-eu-2):  h0, i0, j0
|
| REQUIREMENT: the Vonage account must allow concurrent binds (confirmed enabled).
| Master switch SMPP_BANKS_ENABLED; when false, the single-bind path (config/smpp.php)
| is used instead.
*/

return [

    'enabled' => env('SMPP_BANKS_ENABLED', false),

    'default' => env('SMPP_BANK_DEFAULT', 'a0'),

    'banks' => [

        // Host 1: smpp-eu.vonage.com
        'a0' => [
            'host'         => env('SMPP_BANK_A0_HOST', env('SMPP_HOST_1', env('SMPP_HOST', 'smpp-eu.vonage.com'))),
            'port'         => (int) env('SMPP_BANK_A0_PORT', env('SMPP_PORT', 8000)),
            'system_id'    => env('SMPP_BANK_A0_SYSTEM_ID', env('SMPP_SYSTEM_ID')),
            'password'     => env('SMPP_BANK_A0_PASSWORD', env('SMPP_PASSWORD')),
            'system_type'  => env('SMPP_BANK_A0_SYSTEM_TYPE', env('SMPP_TYPE', 'smppBK1P3')),
            'seq_id_range' => [1, 2000000],
        ],
        'b0' => [
            'host'         => env('SMPP_BANK_B0_HOST', env('SMPP_HOST_1', env('SMPP_HOST', 'smpp-eu.vonage.com'))),
            'port'         => (int) env('SMPP_BANK_B0_PORT', env('SMPP_PORT', 8000)),
            'system_id'    => env('SMPP_BANK_B0_SYSTEM_ID', env('SMPP_SYSTEM_ID')),
            'password'     => env('SMPP_BANK_B0_PASSWORD', env('SMPP_PASSWORD')),
            'system_type'  => env('SMPP_BANK_B0_SYSTEM_TYPE', env('SMPP_TYPE', 'smppBK1P3')),
            'seq_id_range' => [2000001, 4000000],
        ],
        'c0' => [
            'host'         => env('SMPP_BANK_C0_HOST', env('SMPP_HOST_1', env('SMPP_HOST', 'smpp-eu.vonage.com'))),
            'port'         => (int) env('SMPP_BANK_C0_PORT', env('SMPP_PORT', 8000)),
            'system_id'    => env('SMPP_BANK_C0_SYSTEM_ID', env('SMPP_SYSTEM_ID')),
            'password'     => env('SMPP_BANK_C0_PASSWORD', env('SMPP_PASSWORD')),
            'system_type'  => env('SMPP_BANK_C0_SYSTEM_TYPE', env('SMPP_TYPE', 'smppBK1P3')),
            'seq_id_range' => [4000001, 6000000],
        ],
        'd0' => [
            'host'         => env('SMPP_BANK_D0_HOST', env('SMPP_HOST_1', env('SMPP_HOST', 'smpp-eu.vonage.com'))),
            'port'         => (int) env('SMPP_BANK_D0_PORT', env('SMPP_PORT', 8000)),
            'system_id'    => env('SMPP_BANK_D0_SYSTEM_ID', env('SMPP_SYSTEM_ID')),
            'password'     => env('SMPP_BANK_D0_PASSWORD', env('SMPP_PASSWORD')),
            'system_type'  => env('SMPP_BANK_D0_SYSTEM_TYPE', env('SMPP_TYPE', 'smppBK1P3')),
            'seq_id_range' => [6000001, 8000000],
        ],

        // Host 2: smpp-eu-1.vonage.com
        'e0' => [
            'host'         => env('SMPP_BANK_E0_HOST', env('SMPP_HOST_2', 'smpp-eu-1.vonage.com')),
            'port'         => (int) env('SMPP_BANK_E0_PORT', env('SMPP_PORT', 8000)),
            'system_id'    => env('SMPP_BANK_E0_SYSTEM_ID', env('SMPP_SYSTEM_ID')),
            'password'     => env('SMPP_BANK_E0_PASSWORD', env('SMPP_PASSWORD')),
            'system_type'  => env('SMPP_BANK_E0_SYSTEM_TYPE', env('SMPP_TYPE', 'smppBK1P3')),
            'seq_id_range' => [8000001, 10000000],
        ],
        'f0' => [
            'host'         => env('SMPP_BANK_F0_HOST', env('SMPP_HOST_2', 'smpp-eu-1.vonage.com')),
            'port'         => (int) env('SMPP_BANK_F0_PORT', env('SMPP_PORT', 8000)),
            'system_id'    => env('SMPP_BANK_F0_SYSTEM_ID', env('SMPP_SYSTEM_ID')),
            'password'     => env('SMPP_BANK_F0_PASSWORD', env('SMPP_PASSWORD')),
            'system_type'  => env('SMPP_BANK_F0_SYSTEM_TYPE', env('SMPP_TYPE', 'smppBK1P3')),
            'seq_id_range' => [10000001, 12000000],
        ],
        'g0' => [
            'host'         => env('SMPP_BANK_G0_HOST', env('SMPP_HOST_2', 'smpp-eu-1.vonage.com')),
            'port'         => (int) env('SMPP_BANK_G0_PORT', env('SMPP_PORT', 8000)),
            'system_id'    => env('SMPP_BANK_G0_SYSTEM_ID', env('SMPP_SYSTEM_ID')),
            'password'     => env('SMPP_BANK_G0_PASSWORD', env('SMPP_PASSWORD')),
            'system_type'  => env('SMPP_BANK_G0_SYSTEM_TYPE', env('SMPP_TYPE', 'smppBK1P3')),
            'seq_id_range' => [12000001, 14000000],
        ],

        // Host 3: smpp-eu-2.vonage.com
        'h0' => [
            'host'         => env('SMPP_BANK_H0_HOST', env('SMPP_HOST_3', 'smpp-eu-2.vonage.com')),
            'port'         => (int) env('SMPP_BANK_H0_PORT', env('SMPP_PORT', 8000)),
            'system_id'    => env('SMPP_BANK_H0_SYSTEM_ID', env('SMPP_SYSTEM_ID')),
            'password'     => env('SMPP_BANK_H0_PASSWORD', env('SMPP_PASSWORD')),
            'system_type'  => env('SMPP_BANK_H0_SYSTEM_TYPE', env('SMPP_TYPE', 'smppBK1P3')),
            'seq_id_range' => [14000001, 16000000],
        ],
        'i0' => [
            'host'         => env('SMPP_BANK_I0_HOST', env('SMPP_HOST_3', 'smpp-eu-2.vonage.com')),
            'port'         => (int) env('SMPP_BANK_I0_PORT', env('SMPP_PORT', 8000)),
            'system_id'    => env('SMPP_BANK_I0_SYSTEM_ID', env('SMPP_SYSTEM_ID')),
            'password'     => env('SMPP_BANK_I0_PASSWORD', env('SMPP_PASSWORD')),
            'system_type'  => env('SMPP_BANK_I0_SYSTEM_TYPE', env('SMPP_TYPE', 'smppBK1P3')),
            'seq_id_range' => [16000001, 18000000],
        ],
        'j0' => [
            'host'         => env('SMPP_BANK_J0_HOST', env('SMPP_HOST_3', 'smpp-eu-2.vonage.com')),
            'port'         => (int) env('SMPP_BANK_J0_PORT', env('SMPP_PORT', 8000)),
            'system_id'    => env('SMPP_BANK_J0_SYSTEM_ID', env('SMPP_SYSTEM_ID')),
            'password'     => env('SMPP_BANK_J0_PASSWORD', env('SMPP_PASSWORD')),
            'system_type'  => env('SMPP_BANK_J0_SYSTEM_TYPE', env('SMPP_TYPE', 'smppBK1P3')),
            'seq_id_range' => [18000001, 20000000],
        ],
    ],

];
