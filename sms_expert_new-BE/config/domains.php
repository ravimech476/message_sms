<?php

return [
    'customer' => explode(',', env('CUSTOMER_DOMAINS', '')),
    'admin'    => explode(',', env('ADMIN_DOMAINS', '')),
    'campaign'    => explode(',', env('CAMPAIGN_DOMAINS', '')),
    
    'customer_domains' => explode(',', env('CUSTOMER_DOMAIN', '')),
    'admin_domains'    => explode(',', env('ADMIN_DOMAIN', '')),
    'campaign_domains'    => explode(',', env('CAMPAIGN_DOMAIN', '')),
    // 'customer_domains' => env('CUSTOMER_DOMAIN'),
    // 'admin_domains'    => env('ADMIN_DOMAIN'),

    'old_sms_expert_dashboard_url' => env('OLD_SMS_EXPERT_DASHBOARD_URL'),
    'old_sms_expert_campaign_url' => env('OLD_SMS_EXPERT_CAMPAIGN_URL'),
];
