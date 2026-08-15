<?php

/*
|------------------------------------------------------------------------------
| SMPP (Vonage) — base / single-bind configuration
|------------------------------------------------------------------------------
| Ported from sms_expert. The multi-bank definitions live in config/smpp_banks.php;
| this file holds the account + PDU defaults shared by every bind.
|
| Credentials (system_id / password) come from .env and are the SAME Vonage SMPP
| account used by sms_expert. Vonage exposes 3 EU hosts (smpp-eu, -1, -2) on port 8000.
*/

return [

    // Primary host/port (single-bind path + fallback for banks)
    'host' => env('SMPP_HOST', 'smpp-eu.vonage.com'),
    'port' => (int) env('SMPP_PORT', 8000),

    // 3 Vonage EU hosts used by the multi-bank setup
    'hosts' => [
        env('SMPP_HOST_1', 'smpp-eu.vonage.com'),
        env('SMPP_HOST_2', 'smpp-eu-1.vonage.com'),
        env('SMPP_HOST_3', 'smpp-eu-2.vonage.com'),
    ],

    // Account credentials (Vonage SMPP) — set in .env, copied from sms_expert
    'system_id'   => env('SMPP_SYSTEM_ID'),
    'password'    => env('SMPP_PASSWORD'),
    'system_type' => env('SMPP_TYPE', ''),

    // Bind mode: transmitter-only sends but does NOT receive DLR, so the send
    // socket is never starved by the inbound DLR flood (DLR handled by receivers).
    'send_transmitter_only' => (bool) env('SMPP_SEND_TRANSMITTER_ONLY', true),

    // Socket / protocol timeouts (ms)
    'timeout'        => (int) env('SMPP_TIMEOUT', 10000),
    'enquire_link'   => (int) env('SMPP_ENQUIRE_LINK', 30000),

    // submit_sm PDU defaults
    'source_ton' => (int) env('SMPP_SOURCE_TON', 5),  // 5 = alphanumeric
    'source_npi' => (int) env('SMPP_SOURCE_NPI', 0),  // 0 = unknown
    'dest_ton'   => (int) env('SMPP_DEST_TON', 1),    // 1 = international
    'dest_npi'   => (int) env('SMPP_DEST_NPI', 1),    // 1 = E.164
    'registered_delivery' => (int) env('SMPP_REGISTERED_DELIVERY', 1), // 1 = request DLR

];
