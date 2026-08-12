<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMPP Configuration
    |--------------------------------------------------------------------------
    | This config follows the OLD SYSTEM pattern from thisserver_details.inc
    | Key concepts:
    |   - Multiple credential sets (a-j) for parallel daemon operation
    |   - seq_id_range: Unique message ID ranges per daemon to avoid collisions
    |   - systemType: Identifies daemon for DLR error code mapping
    |   - arrServers: Primary/secondary server arrays for failover
    |   - Provider banking: BKnPm where n=block, m=provider
    |     (P1=mblox, P2=mbird, P3=nexmo, P4=onesixtysim, P5=ice, P6=tek, P7=routem)
    */

    'default_connection' => env('SMPP_CONNECTION', 'vonage'),

    /*
    |--------------------------------------------------------------------------
    | Persistent send (transceiver) mode — reliable Vonage SMPP DLRs
    |--------------------------------------------------------------------------
    | Vonage delivers a message's DLR back to the SMPP SESSION that submitted it.
    | The default "direct" send opens a throw-away transceiver, submits, and
    | unbinds within ~1s — so the DLR has nowhere to land and the persistent
    | receiver binds (which never submitted) don't get it. Result: DLRs update
    | only intermittently.
    |
    | When 'persistent_send' is true:
    |   - SMPPPoolManager::sendSMS PUBLISHES the submit to 'outbound_queue'
    |     instead of doing an ephemeral connect/submit/disconnect.
    |   - The persistent transceiver daemons (smpp:dlr-receiver --send, one per
    |     bank) drain that queue and submit on their OWN long-lived bound session,
    |     then receive the DLR for it on the SAME session — exactly the OLD SYSTEM
    |     model. Each bank only gets DLRs for what it sent, so matching is reliable.
    |
    | Leave false to keep the current direct-send behaviour unchanged.
    */
    'persistent_send' => env('SMPP_PERSISTENT_SEND', false),
    // Reuse the already-declared outbound queue (RabbitMQService::declareQueues).
    'outbound_queue'  => env('RABBITMQ_SMS_QUEUE', 'sms.outbound'),
    // How many queued sends a transceiver drains per loop iteration.
    'outbound_batch'  => (int) env('SMPP_OUTBOUND_BATCH', 20),

    /*
    |--------------------------------------------------------------------------
    | Provider Codes (for DLR mapping)
    |--------------------------------------------------------------------------
    | These codes are used in systemType (e.g., smppBK1P3 = Block1, Provider3=Nexmo)
    */
    'providers' => [
        1 => 'mblox',      // Sinch/mBlox
        2 => 'mbird',      // MessageBird
        3 => 'nexmo',      // Vonage/Nexmo
        4 => 'onesixtysim', // OneSixty SIM
        5 => 'ice',        // ICE Messaging
        6 => 'tek',        // TEK
        7 => 'routem',     // Route Mobile
    ],

    /*
    |--------------------------------------------------------------------------
    | SMPP Connections
    |--------------------------------------------------------------------------
    */

    'connections' => [
        // Vonage/Nexmo - Global Account
        'vonage_global' => [
            'system_id' => env('SMPP_VONAGE_GLOBAL_SYSTEM_ID', ''),
            'password' => env('SMPP_VONAGE_GLOBAL_PASSWORD', ''),
            'port' => env('SMPP_VONAGE_PORT', 8000),
            'interface_version' => 0x34,
            'source_ton' => 0x01,
            'source_npi' => 0x01,
            'dest_ton' => 0x01,
            'dest_npi' => 0x01,
            'bind_type' => 'transceiver',
            'profile' => '',
            // Multiple credential sets for parallel daemon operation (OLD SYSTEM pattern)
            'credentials' => [
                'a0' => [
                    'servers' => ['smpp1.nexmo.com', 'smpp2.nexmo.com'], // Primary, Secondary
                    'system_type' => 'smppBK1P3', // Block 1, Provider 3 (Nexmo)
                    'seq_id_range' => [1, 2000000],
                ],
                'b0' => [
                    'servers' => ['smpp1.nexmo.com', 'smpp2.nexmo.com'],
                    'system_type' => 'smppBK1P3',
                    'seq_id_range' => [2000001, 4000000],
                ],
                'c0' => [
                    'servers' => ['smpp1.nexmo.com', 'smpp2.nexmo.com'],
                    'system_type' => 'smppBK1P3',
                    'seq_id_range' => [4000001, 6000000],
                ],
                'd0' => [
                    'servers' => ['smpp1.nexmo.com', 'smpp2.nexmo.com'],
                    'system_type' => 'smppBK1P3',
                    'seq_id_range' => [6000001, 8000000],
                ],
                'e0' => [
                    'servers' => ['smpp1.nexmo.com', 'smpp2.nexmo.com'],
                    'system_type' => 'smppBK1P3',
                    'seq_id_range' => [8000001, 10000000],
                ],
                'f0' => [
                    'servers' => ['smpp2.nexmo.com', 'smpp1.nexmo.com'],
                    'system_type' => 'smppBK1P3',
                    'seq_id_range' => [10000001, 12000000],
                ],
                'g0' => [
                    'servers' => ['smpp2.nexmo.com', 'smpp1.nexmo.com'],
                    'system_type' => 'smppBK1P3',
                    'seq_id_range' => [12000001, 14000000],
                ],
                'h0' => [
                    'servers' => ['smpp2.nexmo.com', 'smpp1.nexmo.com'],
                    'system_type' => 'smppBK1P3',
                    'seq_id_range' => [14000001, 16000000],
                ],
                'i0' => [
                    'servers' => ['smpp2.nexmo.com', 'smpp1.nexmo.com'],
                    'system_type' => 'smppBK1P3',
                    'seq_id_range' => [16000001, 18000000],
                ],
                'j0' => [
                    'servers' => ['smpp2.nexmo.com', 'smpp1.nexmo.com'],
                    'system_type' => 'smppBK1P3',
                    'seq_id_range' => [18000001, 20000000],
                ],
            ],
        ],

        // Vonage/Nexmo - Direct Account (UK+Ausy)
        'vonage_direct' => [
            'system_id' => env('SMPP_VONAGE_DIRECT_SYSTEM_ID', ''),
            'password' => env('SMPP_VONAGE_DIRECT_PASSWORD', ''),
            'port' => env('SMPP_VONAGE_PORT', 8000),
            'interface_version' => 0x34,
            'source_ton' => 0x01,
            'source_npi' => 0x01,
            'dest_ton' => 0x01,
            'dest_npi' => 0x01,
            'bind_type' => 'transceiver',
            'profile' => '',
            'credentials' => [
                'a3' => [
                    'servers' => ['smpp1.nexmo.com', 'smpp2.nexmo.com'],
                    'system_type' => 'smppBK4P3', // Block 4, Provider 3 (Nexmo)
                    'seq_id_range' => [60000001, 62000000],
                ],
                'b3' => [
                    'servers' => ['smpp1.nexmo.com', 'smpp2.nexmo.com'],
                    'system_type' => 'smppBK4P3',
                    'seq_id_range' => [62000001, 64000000],
                ],
                'c3' => [
                    'servers' => ['smpp1.nexmo.com', 'smpp2.nexmo.com'],
                    'system_type' => 'smppBK4P3',
                    'seq_id_range' => [64000001, 66000000],
                ],
                'd3' => [
                    'servers' => ['smpp1.nexmo.com', 'smpp2.nexmo.com'],
                    'system_type' => 'smppBK4P3',
                    'seq_id_range' => [66000001, 68000000],
                ],
                'e3' => [
                    'servers' => ['smpp1.nexmo.com', 'smpp2.nexmo.com'],
                    'system_type' => 'smppBK4P3',
                    'seq_id_range' => [68000001, 70000000],
                ],
                'f3' => [
                    'servers' => ['smpp2.nexmo.com', 'smpp1.nexmo.com'],
                    'system_type' => 'smppBK4P3',
                    'seq_id_range' => [70000001, 72000000],
                ],
                'g3' => [
                    'servers' => ['smpp2.nexmo.com', 'smpp1.nexmo.com'],
                    'system_type' => 'smppBK4P3',
                    'seq_id_range' => [72000001, 74000000],
                ],
                'h3' => [
                    'servers' => ['smpp2.nexmo.com', 'smpp1.nexmo.com'],
                    'system_type' => 'smppBK4P3',
                    'seq_id_range' => [74000001, 76000000],
                ],
                'i3' => [
                    'servers' => ['smpp2.nexmo.com', 'smpp1.nexmo.com'],
                    'system_type' => 'smppBK4P3',
                    'seq_id_range' => [76000001, 78000000],
                ],
                'j3' => [
                    'servers' => ['smpp2.nexmo.com', 'smpp1.nexmo.com'],
                    'system_type' => 'smppBK4P3',
                    'seq_id_range' => [78000001, 80000000],
                ],
            ],
        ],

        // Legacy single connection (for backward compatibility)
        'vonage' => [
            'hosts' => explode(',', env('SMPP_HOSTS', 'smpp1.nexmo.com,smpp2.nexmo.com,smpp3.nexmo.com,smpp4.nexmo.com,smpp0.nexmo.com')),
            'port' => env('SMPP_PORT', 8000),
            'system_id' => env('SMPP_SYSTEM_ID', ''),
            'password' => env('SMPP_PASSWORD', ''),
            'system_type' => env('SMPP_TYPE', 'smpp'),
            'interface_version' => 0x34,
            'source_ton' => 0x01,
            'source_npi' => 0x01,
            'dest_ton' => 0x01,
            'dest_npi' => 0x01,
            'bind_type' => env('SMPP_BIND_TYPE', 'transceiver'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sinch/mBlox SMPP Connections (OLD SYSTEM pattern)
    |--------------------------------------------------------------------------
    */
    'sinch' => [
        'system_id' => env('SINCH_SMPP_SYSTEM_ID', ''),
        'password' => env('SINCH_SMPP_PASSWORD', ''),
        'port' => env('SINCH_SMPP_PORT', 3200),
        'profile' => env('SINCH_SMPP_PROFILE', '21839'),
        'servers' => [
            'primary' => ['smpp4.mblox.com'],
            'secondary' => ['smpp4.mblox.com'],
        ],
        'credentials' => [
            'a' => [
                'servers' => ['smpp4.mblox.com', 'smpp4.mblox.com'], // Primary, Secondary
                'system_type' => 'smppProd1',
                'seq_id_range' => [1, 2000000],
            ],
            'b' => [
                'servers' => ['smpp4.mblox.com', 'smpp4.mblox.com'],
                'system_type' => 'smppProd1',
                'seq_id_range' => [2000001, 4000000],
            ],
            'c' => [
                'servers' => ['smpp4.mblox.com', 'smpp4.mblox.com'],
                'system_type' => 'smppProd2',
                'seq_id_range' => [4000001, 6000000],
            ],
            'd' => [
                'servers' => ['smpp4.mblox.com', 'smpp4.mblox.com'],
                'system_type' => 'smppProd2',
                'seq_id_range' => [6000001, 8000000],
            ],
            'e' => [
                'servers' => ['smpp4.mblox.com', 'smpp4.mblox.com'],
                'system_type' => 'smppProd3',
                'seq_id_range' => [8000001, 10000000],
            ],
            'f' => [
                'servers' => ['smpp4.mblox.com', 'smpp4.mblox.com'],
                'system_type' => 'smppProd3',
                'seq_id_range' => [10000001, 12000000],
            ],
            'g' => [
                'servers' => ['smpp4.mblox.com', 'smpp4.mblox.com'],
                'system_type' => 'smppProd4',
                'seq_id_range' => [12000001, 14000000],
            ],
            'h' => [
                'servers' => ['smpp4.mblox.com', 'smpp4.mblox.com'],
                'system_type' => 'smppProd4',
                'seq_id_range' => [14000001, 16000000],
            ],
            'i' => [
                'servers' => ['smpp4.mblox.com', 'smpp4.mblox.com'],
                'system_type' => 'smppProd5',
                'seq_id_range' => [16000001, 18000000],
            ],
            'j' => [
                'servers' => ['smpp4.mblox.com', 'smpp4.mblox.com'],
                'system_type' => 'smppProd5',
                'seq_id_range' => [18000001, 20000000],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Mobile SMPP
    |--------------------------------------------------------------------------
    */
    'routemobile' => [
        'system_id' => env('ROUTEMOBILE_SMPP_SYSTEM_ID', ''),
        'password' => env('ROUTEMOBILE_SMPP_PASSWORD', ''),
        'servers' => ['sms-eu.rmlconnect.net'],
        'port' => env('ROUTEMOBILE_SMPP_PORT', 2346),
        'system_type' => 'routem',
    ],

    /*
    |--------------------------------------------------------------------------
    | ICE Messaging SMPP
    |--------------------------------------------------------------------------
    */
    'ice' => [
        'system_id' => env('ICE_SMPP_SYSTEM_ID', ''),
        'password' => env('ICE_SMPP_PASSWORD', ''),
        'servers' => ['esmegw01.icemessaging.com'],
        'port' => env('ICE_SMPP_PORT', 21022),
        'port_dlr' => env('ICE_SMPP_PORT_DLR', 21033),
        'system_type' => 'acc1',
    ],

    /*
    |--------------------------------------------------------------------------
    | TEK SMPP
    |--------------------------------------------------------------------------
    */
    'tek' => [
        'system_id' => env('TEK_SMPP_SYSTEM_ID', ''),
        'password' => env('TEK_SMPP_PASSWORD', ''),
        'servers' => ['41.185.95.21'],
        'port' => env('TEK_SMPP_PORT', 9500),
        'system_type' => 'tek',
    ],

    /*
    |--------------------------------------------------------------------------
    | SMPP Settings
    |--------------------------------------------------------------------------
    */

    'settings' => [
        'timeout' => env('SMPP_TIMEOUT', 30),
        'keepalive' => env('SMPP_KEEPALIVE', 60),
        'tps_limit' => env('SMPP_TPS_LIMIT', 50),
        'max_connections_per_host' => 2,
        'reconnect_delay' => 5, // seconds
        'max_reconnect_attempts' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Throttle Settings (per daemon, OLD SYSTEM pattern)
    |--------------------------------------------------------------------------
    | Each daemon can have different TPS limits
    */
    'throttle' => [
        'mblox' => env('SMPP_THROTTLE_MBLOX', 50),
        'vonage_global' => env('SMPP_THROTTLE_VONAGE_GLOBAL', 100),
        'vonage_direct' => env('SMPP_THROTTLE_VONAGE_DIRECT', 100),
        'routemobile' => env('SMPP_THROTTLE_ROUTEMOBILE', 100),
        'ice' => env('SMPP_THROTTLE_ICE', 100),
        'tek' => env('SMPP_THROTTLE_TEK', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Sender
    |--------------------------------------------------------------------------
    */

    'default_sender' => env('SMPP_DEFAULT_SENDER', 'MYBRANDNAME'),

    /*
    |--------------------------------------------------------------------------
    | DLR Settings
    |--------------------------------------------------------------------------
    */

    'dlr' => [
        'enabled' => true,
        'webhook_url' => env('DLR_WEBHOOK_URL', null),
        'store_raw' => true,
        'alert_email' => env('SMPP_ALERT_EMAIL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Settings
    |--------------------------------------------------------------------------
    */

    'message' => [
        'max_length' => 1600,
        'concatenation' => true,
        'validity_period' => null, // null for network default
        'priority_flag' => 0,
        'registered_delivery' => 1, // Request delivery receipts
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Coding Schemes
    |--------------------------------------------------------------------------
    */

    'data_coding' => [
        'default' => 0x00, // SMSC Default Alphabet
        'latin1' => 0x03, // Latin 1 (ISO-8859-1)
        'binary' => 0x04, // 8-bit binary
        'ucs2' => 0x08, // UCS2 (ISO/IEC-10646)
    ],

    /*
    |--------------------------------------------------------------------------
    | Sequence ID Manager
    |--------------------------------------------------------------------------
    | Manages unique message IDs within assigned ranges per daemon
    */
    'sequence_id' => [
        'storage' => env('SMPP_SEQ_ID_STORAGE', 'redis'), // 'redis', 'database', 'file'
        'key_prefix' => 'smpp_seq_id_',
    ],
];
