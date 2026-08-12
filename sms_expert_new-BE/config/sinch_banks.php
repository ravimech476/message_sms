<?php

/*
|------------------------------------------------------------------------------
| Sinch (mBlox legacy) SMPP Multi-Bank Bind Configuration
|------------------------------------------------------------------------------
|
| Mirrors the OLD SYSTEM 10-bank model (smsexpert_oldsystem/sites/
| thisserver_details.inc:92-184): 10 banks (a..j) under ONE account, each owning
| a 2M-wide slice of the SMPP sequence_number space so DLRs route back to the
| right bind (Sinch returns a DLR on the bind whose seq_id was used for the
| original submit_sm — overlapping ranges would lose DLR routing).
|
| Credentials come from the single Sinch account already in .env:
|   SINCH_SMPP_HOST=eu.smpp.api.sinch.com
|   SINCH_SMPP_PORT=3601
|   SINCH_SMPP_SYSTEM_ID=SMSEx_RA
|   SINCH_SMPP_PASSWORD=AyefPMkE
|   SINCH_SMPP_SYSTEM_TYPE=smppProd1
|   SINCH_SMPP_PROFILE=21839
| All 10 banks share these. Per-bank overrides SINCH_BANK_<KEY>_HOST /
| _SYSTEM_ID / _PASSWORD / _SYSTEM_TYPE / _PROFILE / _PORT exist for when Sinch
| issues distinct credentials/hosts/system_types per bank (e.g. the old
| smppProd1..5 layout, or extra system_ids to get past the bind cap below).
|
| ============================  HARD LIMIT  ============================
| The modern Sinch gateway allows only **2 parallel connections per
| host/system_id**. Because all 10 banks share SINCH_SMPP_SYSTEM_ID + host,
| only the first 2 binds succeed; banks c..j return ESME_RALYBND (status 5).
| The OLD SYSTEM got 10 binds only by spreading across legacy mBlox hosts
| (smpp3/smpp4.mblox.com) — the modern gateway is one host per region.
| To run more than 2: ask Sinch to raise the per-host limit on SMSEx_RA, OR
| have them issue extra system_ids/hosts and set them via SINCH_BANK_<KEY>_*.
| =====================================================================
|
| When SINCH_BANKS_ENABLED=false (default) SinchSmppService stays single-bind,
| driven directly by SINCH_SMPP_HOST / SINCH_SMPP_SYSTEM_ID / etc.
|
*/

return [

    'enabled' => env('SINCH_BANKS_ENABLED', false),

    // Used when sinch:dlr-receiver is invoked without --bank=<key>.
    'default' => env('SINCH_BANK_DEFAULT', 'a'),

    'banks' => [
        'a' => [
            'host'         => env('SINCH_BANK_A_HOST',        env('SINCH_SMPP_HOST', 'eu.smpp.api.sinch.com')),
            'port'         => (int) env('SINCH_BANK_A_PORT',  env('SINCH_SMPP_PORT', 3601)),
            'system_id'    => env('SINCH_BANK_A_SYSTEM_ID',   env('SINCH_SMPP_SYSTEM_ID')),
            'password'     => env('SINCH_BANK_A_PASSWORD',    env('SINCH_SMPP_PASSWORD')),
            'system_type'  => env('SINCH_BANK_A_SYSTEM_TYPE', env('SINCH_SMPP_SYSTEM_TYPE', 'smppProd1')),
            'profile'      => env('SINCH_BANK_A_PROFILE',     env('SINCH_SMPP_PROFILE', '21839')),
            'seq_id_range' => [1, 2_000_000],
        ],
        'b' => [
            'host'         => env('SINCH_BANK_B_HOST',        env('SINCH_SMPP_HOST', 'eu.smpp.api.sinch.com')),
            'port'         => (int) env('SINCH_BANK_B_PORT',  env('SINCH_SMPP_PORT', 3601)),
            'system_id'    => env('SINCH_BANK_B_SYSTEM_ID',   env('SINCH_SMPP_SYSTEM_ID')),
            'password'     => env('SINCH_BANK_B_PASSWORD',    env('SINCH_SMPP_PASSWORD')),
            'system_type'  => env('SINCH_BANK_B_SYSTEM_TYPE', env('SINCH_SMPP_SYSTEM_TYPE', 'smppProd1')),
            'profile'      => env('SINCH_BANK_B_PROFILE',     env('SINCH_SMPP_PROFILE', '21839')),
            'seq_id_range' => [2_000_001, 4_000_000],
        ],
        'c' => [
            'host'         => env('SINCH_BANK_C_HOST',        env('SINCH_SMPP_HOST', 'eu.smpp.api.sinch.com')),
            'port'         => (int) env('SINCH_BANK_C_PORT',  env('SINCH_SMPP_PORT', 3601)),
            'system_id'    => env('SINCH_BANK_C_SYSTEM_ID',   env('SINCH_SMPP_SYSTEM_ID')),
            'password'     => env('SINCH_BANK_C_PASSWORD',    env('SINCH_SMPP_PASSWORD')),
            'system_type'  => env('SINCH_BANK_C_SYSTEM_TYPE', env('SINCH_SMPP_SYSTEM_TYPE', 'smppProd1')),
            'profile'      => env('SINCH_BANK_C_PROFILE',     env('SINCH_SMPP_PROFILE', '21839')),
            'seq_id_range' => [4_000_001, 6_000_000],
        ],
        'd' => [
            'host'         => env('SINCH_BANK_D_HOST',        env('SINCH_SMPP_HOST', 'eu.smpp.api.sinch.com')),
            'port'         => (int) env('SINCH_BANK_D_PORT',  env('SINCH_SMPP_PORT', 3601)),
            'system_id'    => env('SINCH_BANK_D_SYSTEM_ID',   env('SINCH_SMPP_SYSTEM_ID')),
            'password'     => env('SINCH_BANK_D_PASSWORD',    env('SINCH_SMPP_PASSWORD')),
            'system_type'  => env('SINCH_BANK_D_SYSTEM_TYPE', env('SINCH_SMPP_SYSTEM_TYPE', 'smppProd1')),
            'profile'      => env('SINCH_BANK_D_PROFILE',     env('SINCH_SMPP_PROFILE', '21839')),
            'seq_id_range' => [6_000_001, 8_000_000],
        ],
        'e' => [
            'host'         => env('SINCH_BANK_E_HOST',        env('SINCH_SMPP_HOST', 'eu.smpp.api.sinch.com')),
            'port'         => (int) env('SINCH_BANK_E_PORT',  env('SINCH_SMPP_PORT', 3601)),
            'system_id'    => env('SINCH_BANK_E_SYSTEM_ID',   env('SINCH_SMPP_SYSTEM_ID')),
            'password'     => env('SINCH_BANK_E_PASSWORD',    env('SINCH_SMPP_PASSWORD')),
            'system_type'  => env('SINCH_BANK_E_SYSTEM_TYPE', env('SINCH_SMPP_SYSTEM_TYPE', 'smppProd1')),
            'profile'      => env('SINCH_BANK_E_PROFILE',     env('SINCH_SMPP_PROFILE', '21839')),
            'seq_id_range' => [8_000_001, 10_000_000],
        ],
        'f' => [
            'host'         => env('SINCH_BANK_F_HOST',        env('SINCH_SMPP_HOST', 'eu.smpp.api.sinch.com')),
            'port'         => (int) env('SINCH_BANK_F_PORT',  env('SINCH_SMPP_PORT', 3601)),
            'system_id'    => env('SINCH_BANK_F_SYSTEM_ID',   env('SINCH_SMPP_SYSTEM_ID')),
            'password'     => env('SINCH_BANK_F_PASSWORD',    env('SINCH_SMPP_PASSWORD')),
            'system_type'  => env('SINCH_BANK_F_SYSTEM_TYPE', env('SINCH_SMPP_SYSTEM_TYPE', 'smppProd1')),
            'profile'      => env('SINCH_BANK_F_PROFILE',     env('SINCH_SMPP_PROFILE', '21839')),
            'seq_id_range' => [10_000_001, 12_000_000],
        ],
        'g' => [
            'host'         => env('SINCH_BANK_G_HOST',        env('SINCH_SMPP_HOST', 'eu.smpp.api.sinch.com')),
            'port'         => (int) env('SINCH_BANK_G_PORT',  env('SINCH_SMPP_PORT', 3601)),
            'system_id'    => env('SINCH_BANK_G_SYSTEM_ID',   env('SINCH_SMPP_SYSTEM_ID')),
            'password'     => env('SINCH_BANK_G_PASSWORD',    env('SINCH_SMPP_PASSWORD')),
            'system_type'  => env('SINCH_BANK_G_SYSTEM_TYPE', env('SINCH_SMPP_SYSTEM_TYPE', 'smppProd1')),
            'profile'      => env('SINCH_BANK_G_PROFILE',     env('SINCH_SMPP_PROFILE', '21839')),
            'seq_id_range' => [12_000_001, 14_000_000],
        ],
        'h' => [
            'host'         => env('SINCH_BANK_H_HOST',        env('SINCH_SMPP_HOST', 'eu.smpp.api.sinch.com')),
            'port'         => (int) env('SINCH_BANK_H_PORT',  env('SINCH_SMPP_PORT', 3601)),
            'system_id'    => env('SINCH_BANK_H_SYSTEM_ID',   env('SINCH_SMPP_SYSTEM_ID')),
            'password'     => env('SINCH_BANK_H_PASSWORD',    env('SINCH_SMPP_PASSWORD')),
            'system_type'  => env('SINCH_BANK_H_SYSTEM_TYPE', env('SINCH_SMPP_SYSTEM_TYPE', 'smppProd1')),
            'profile'      => env('SINCH_BANK_H_PROFILE',     env('SINCH_SMPP_PROFILE', '21839')),
            'seq_id_range' => [14_000_001, 16_000_000],
        ],
        'i' => [
            'host'         => env('SINCH_BANK_I_HOST',        env('SINCH_SMPP_HOST', 'eu.smpp.api.sinch.com')),
            'port'         => (int) env('SINCH_BANK_I_PORT',  env('SINCH_SMPP_PORT', 3601)),
            'system_id'    => env('SINCH_BANK_I_SYSTEM_ID',   env('SINCH_SMPP_SYSTEM_ID')),
            'password'     => env('SINCH_BANK_I_PASSWORD',    env('SINCH_SMPP_PASSWORD')),
            'system_type'  => env('SINCH_BANK_I_SYSTEM_TYPE', env('SINCH_SMPP_SYSTEM_TYPE', 'smppProd1')),
            'profile'      => env('SINCH_BANK_I_PROFILE',     env('SINCH_SMPP_PROFILE', '21839')),
            'seq_id_range' => [16_000_001, 18_000_000],
        ],
        'j' => [
            'host'         => env('SINCH_BANK_J_HOST',        env('SINCH_SMPP_HOST', 'eu.smpp.api.sinch.com')),
            'port'         => (int) env('SINCH_BANK_J_PORT',  env('SINCH_SMPP_PORT', 3601)),
            'system_id'    => env('SINCH_BANK_J_SYSTEM_ID',   env('SINCH_SMPP_SYSTEM_ID')),
            'password'     => env('SINCH_BANK_J_PASSWORD',    env('SINCH_SMPP_PASSWORD')),
            'system_type'  => env('SINCH_BANK_J_SYSTEM_TYPE', env('SINCH_SMPP_SYSTEM_TYPE', 'smppProd1')),
            'profile'      => env('SINCH_BANK_J_PROFILE',     env('SINCH_SMPP_PROFILE', '21839')),
            'seq_id_range' => [18_000_001, 20_000_000],
        ],
    ],

];
