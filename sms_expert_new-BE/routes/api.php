<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\PlatGetKeysController;
use App\Http\Controllers\Api\PlatKeyRenewController;
use App\Http\Controllers\Api\PlatKeyDeleteController;
use App\Http\Controllers\Api\PlatKeyReplaceController;
use App\Http\Controllers\Api\PlatWhoIsController;
use App\Http\Controllers\Api\PlatKeyRegisterController;
use App\Http\Controllers\Api\PlatKeySetForwardController;
use App\Http\Controllers\Api\PlatWalletController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\Admin\VirtualNumberController;
use App\Http\Controllers\Admin\UserRateController;
use App\Http\Controllers\SinchSmsController;



Route::post('register', [ApiController::class, 'register']);
Route::post('login', [ApiController::class, 'login']);
Route::group(['middleware' => ["auth:sanctum"]], function () {
    Route::post('userProfile', [ApiController::class, 'getUserProfileDetails']);
    Route::post('logout', [ApiController::class, 'logout']);
});

### Wallet API ###
Route::post('/plat_wallet', [PlatWalletController::class, 'getWallet']);

### Keyword Whois API ###
Route::post('/keyword_whois', [PlatWhoIsController::class, 'whoisKeyword']);

### Keyword Registration API ###
Route::post('/plat_keyreg', [PlatKeyRegisterController::class, 'registerKeyword']);

### Keyword Forwarding API ###
Route::post('/plat_keysetfwd', [PlatKeySetForwardController::class, 'setKeywordForwardings']);

### Keyword List API ###
Route::post('/plat_keylist', [PlatGetKeysController::class, 'getKeywords']);

### Keyword Renewval API ###
Route::post('/plat_keyrenew', [PlatKeyRenewController::class, 'renewKeyword']);

### Keyword Deletion API ###
Route::post('/plat_keydel', [PlatKeyDeleteController::class, 'deleteKeyword']);

### Keyword Replacement API ###
Route::post('/plat_keyreplace', [PlatKeyReplaceController::class, 'replaceKeyword']);

// Route::get('/delivery-receipt-webhook', [WebhookController::class, 'deliveryReceipt']);

use App\Http\Controllers\SmppQueueController;

// SMPP Queue API Routes
Route::prefix('smpp')->group(function () {
    Route::post('/send', [SmppQueueController::class, 'sendSms']);
    Route::post('/send-bulk', [SmppQueueController::class, 'sendBulkSms']);
    Route::get('/stats', [SmppQueueController::class, 'getQueueStats']);
    Route::get('/connections', [SmppQueueController::class, 'getConnectionStatus']);
    Route::get('/message/{queueId}', [SmppQueueController::class, 'getMessageStatus']);
    Route::post('/retry-failed', [SmppQueueController::class, 'retryFailedMessages']);
    Route::delete('/purge-failed', [SmppQueueController::class, 'purgeFailedMessages']);
});

Route::match(['get', 'post'], '/delivery-receipt-webhook', [WebhookController::class, 'deliveryReceipt']);

// Virtual Number API Routes
Route::prefix('admin/virtual-numbers')->group(function () {
    Route::get('/user/{userId}', [VirtualNumberController::class, 'getVirtualNumbers']);
    Route::post('/add-12-months-today', [VirtualNumberController::class, 'add12MonthsFromToday']);
    Route::post('/add-12-months-expiry', [VirtualNumberController::class, 'add12MonthsFromExpiry']);
    Route::post('/force-expiry', [VirtualNumberController::class, 'forceExpiry']);
    Route::post('/force-unexpiry', [VirtualNumberController::class, 'forceUnexpiry']);
    Route::post('/toggle-suspension', [VirtualNumberController::class, 'toggleSuspension']);
    Route::post('/update-expiry-date', [VirtualNumberController::class, 'updateExpiryDate']);
    Route::post('/add-nexmo-uk-pool', [VirtualNumberController::class, 'addNexmoUkToPool']);
});

// Admin User Rate Management API Routes (OLD SYSTEM pricing logic using smsg_userroute table)
Route::prefix('admin/user-rates')->group(function () {
    // Get all rates for a user
    // GET /api/admin/user-rates/user/{userId}
    Route::get('/user/{userId}', [UserRateController::class, 'index']);

    // Add a new rate for a user
    // POST /api/admin/user-rates/user/{userId}
    Route::post('/user/{userId}', [UserRateController::class, 'store']);

    // Bulk add rates for a user
    // POST /api/admin/user-rates/user/{userId}/bulk
    Route::post('/user/{userId}/bulk', [UserRateController::class, 'bulkStore']);

    // Copy default rates to a user
    // POST /api/admin/user-rates/user/{userId}/copy-defaults
    Route::post('/user/{userId}/copy-defaults', [UserRateController::class, 'copyDefaults']);

    // Update an existing rate for a user
    // PUT /api/admin/user-rates/user/{userId}/{routenum}/{countrydialcode}
    Route::put('/user/{userId}/{routenum}/{countrydialcode}', [UserRateController::class, 'update']);

    // Delete a rate for a user
    // DELETE /api/admin/user-rates/user/{userId}/{routenum}/{countrydialcode}
    Route::delete('/user/{userId}/{routenum}/{countrydialcode}', [UserRateController::class, 'destroy']);

    // Get pricing preview for a user and phone number
    // GET /api/admin/user-rates/user/{userId}/pricing-preview?phone=447912345678&routenum=7002
    Route::get('/user/{userId}/pricing-preview', [UserRateController::class, 'pricingPreview']);

    // Get available routes for dropdown/selection
    // GET /api/admin/user-rates/routes
    Route::get('/routes', [UserRateController::class, 'getAvailableRoutes']);
});

// Inbound SMS Webhook Routes
Route::prefix('webhook')->group(function () {
    Route::match(['get', 'post'], '/inbound-sms', [App\Http\Controllers\InboundSmsWebhookController::class, 'handle'])
        ->name('webhook.inbound-sms');
    Route::get('/inbound-sms/test', [App\Http\Controllers\InboundSmsWebhookController::class, 'test'])
        ->name('webhook.inbound-sms.test');
    Route::get('/inbound-sms/stats', [App\Http\Controllers\InboundSmsWebhookController::class, 'stats'])
        ->name('webhook.inbound-sms.stats');

    // Sinch DLR (Delivery Receipt) webhook
    Route::match(['get', 'post'], '/sinch-dlr', [App\Http\Controllers\WebhookController::class, 'sinchDeliveryReceipt'])
        ->name('webhook.sinch-dlr');
});

// Unified SMS Webhook Routes (handles BOTH DLR and Inbound SMS for Nexmo/Sinch)
// Configure these URLs in your provider's dashboard:
// - Nexmo/Vonage: https://yourdomain.com/api/sms/webhook OR /api/sms/webhook/nexmo
// - Sinch: https://yourdomain.com/api/sms/webhook OR /api/sms/webhook/sinch
Route::prefix('sms')->group(function () {
    // Main unified webhook - auto-detects provider and message type
    Route::match(['get', 'post'], '/webhook', [App\Http\Controllers\UnifiedSmsWebhookController::class, 'handle'])
        ->name('sms.webhook');

    // Provider-specific aliases (both route to the same handler)
    Route::match(['get', 'post'], '/webhook/nexmo', [App\Http\Controllers\UnifiedSmsWebhookController::class, 'nexmo'])
        ->name('sms.webhook.nexmo');
    Route::match(['get', 'post'], '/webhook/sinch', [App\Http\Controllers\UnifiedSmsWebhookController::class, 'sinch'])
        ->name('sms.webhook.sinch');

    // Test endpoint to verify webhook is active
    Route::get('/webhook/test', [App\Http\Controllers\UnifiedSmsWebhookController::class, 'test'])
        ->name('sms.webhook.test');
});

// Legacy SMS API Routes (backwards compatible with old PHP system)
// URL: /api/smsg/sms.mes?usr=XXX&pwd=YYY&from=myname&to=447912345678&type=text&route=d&txt=helloworld
use App\Http\Controllers\Api\LegacySmsApiController;

Route::prefix('smsg')->group(function () {
    // Legacy format - plain text response
    Route::match(['get', 'post'], '/sms.mes', [LegacySmsApiController::class, 'sendSms']);
    Route::match(['get', 'post'], '/sms_send', [LegacySmsApiController::class, 'sendSms']); // Alternative endpoint
    Route::match(['get', 'post'], '/si.mes', [LegacySmsApiController::class, 'sendSi']); // Service Indicator (WAP Push)
    
    // JSON format responses
    Route::match(['get', 'post'], '/sms.json', [LegacySmsApiController::class, 'sendSmsJson']);
    
    // Utility endpoints
    Route::match(['get', 'post'], '/check', [LegacySmsApiController::class, 'checkCredentials']);
    Route::match(['get', 'post'], '/status', [LegacySmsApiController::class, 'getDeliveryStatus']);
});

// GSM Character Conversion API Routes
use App\Http\Controllers\Api\GsmCharacterController;

Route::prefix('gsm')->group(function () {
    Route::post('/analyze', [GsmCharacterController::class, 'analyze']);
    Route::post('/convert', [GsmCharacterController::class, 'convert']);
    Route::post('/check', [GsmCharacterController::class, 'check']);
    Route::post('/calculate-parts', [GsmCharacterController::class, 'calculateParts']);
    Route::get('/conversion-map', [GsmCharacterController::class, 'getConversionMap']);
});

// Customer Notification Routes
use App\Http\Controllers\CustomerNotificationController;

Route::prefix('customer/notifications')->middleware('auth:sanctum')->group(function () {
    Route::get('/unread', [CustomerNotificationController::class, 'getUnreadNotifications']);
    Route::get('/all', [CustomerNotificationController::class, 'getAllNotifications']);
    Route::post('/{id}/read', [CustomerNotificationController::class, 'markAsRead']);
    Route::post('/mark-all-read', [CustomerNotificationController::class, 'markAllAsRead']);
    Route::post('/{id}/acknowledge', [CustomerNotificationController::class, 'acknowledge']);
});

// Customer Notification Routes (Session-based for web interface)
use App\Http\Controllers\Api\CustomerNotificationController as ApiCustomerNotificationController;

Route::prefix('notifications')->group(function () {
    Route::get('/', [ApiCustomerNotificationController::class, 'getNotifications']);
    Route::get('/unread-count', [ApiCustomerNotificationController::class, 'getUnreadCount']);
    Route::get('/pending-acknowledgement', [ApiCustomerNotificationController::class, 'getPendingAcknowledgement']);
    Route::post('/{id}/read', [ApiCustomerNotificationController::class, 'markAsRead']);
    Route::post('/{id}/acknowledge', [ApiCustomerNotificationController::class, 'acknowledge']);
    Route::post('/mark-all-read', [ApiCustomerNotificationController::class, 'markAllAsRead']);
});


// ============================================
// MOBILE APP API ROUTES
// ============================================
use App\Http\Controllers\Api\Mobile\AuthController as MobileAuthController;

// Mobile API routes with error monitoring
Route::prefix('mobile')->middleware('api.error.monitor')->group(function () {
    
    // ===== Public Routes (No Authentication Required) =====
    Route::prefix('auth')->group(function () {
        // Login endpoint
        // POST /api/mobile/auth/login
        // Body: { userName, password, device_name?, device_id?, fcm_token? }
        Route::post('/login', [MobileAuthController::class, 'login']);
    });

    // ===== Protected Routes (Authentication Required) =====
    Route::middleware('auth:sanctum')->group(function () {
        
        // ===== Auth Routes =====
        Route::prefix('auth')->group(function () {
            // Logout from current device
            // POST /api/mobile/auth/logout
            Route::post('/logout', [MobileAuthController::class, 'logout']);
            
            // Logout from all devices
            // POST /api/mobile/auth/logout-all
            Route::post('/logout-all', [MobileAuthController::class, 'logoutAll']);
            
            // Refresh token
            // POST /api/mobile/auth/refresh-token
            Route::post('/refresh-token', [MobileAuthController::class, 'refreshToken']);
            
            // Check authentication status
            // GET /api/mobile/auth/check
            Route::get('/check', [MobileAuthController::class, 'checkAuth']);
            
            // Update FCM token for push notifications
            // POST /api/mobile/auth/push-token
            Route::post('/push-token', [MobileAuthController::class, 'updatePushToken']);
            
            // Change password
            // POST /api/mobile/auth/change-password
            Route::post('/change-password', [MobileAuthController::class, 'changePassword']);
        });

        // ===== User Profile Routes =====
        Route::prefix('user')->group(function () {
            // Get current user profile
            // GET /api/mobile/user/profile
            Route::get('/profile', [MobileAuthController::class, 'profile']);
        });

        // ===== Dashboard Routes =====
        Route::prefix('dashboard')->group(function () {
            // Get dashboard data
            // GET /api/mobile/dashboard
            Route::get('/', [App\Http\Controllers\Api\Mobile\DashboardController::class, 'index']);
            
            // Get wallet balance
            // GET /api/mobile/dashboard/wallet
            Route::get('/wallet', [App\Http\Controllers\Api\Mobile\DashboardController::class, 'wallet']);
            
            // Get SMS statistics
            // GET /api/mobile/dashboard/stats
            Route::get('/stats', [App\Http\Controllers\Api\Mobile\DashboardController::class, 'stats']);
            
            // Get monthly SMS counts
            // GET /api/mobile/dashboard/monthly-counts?year=2024
            Route::get('/monthly-counts', [App\Http\Controllers\Api\Mobile\DashboardController::class, 'monthlyCounts']);
            
            // Get daily SMS counts
            // GET /api/mobile/dashboard/daily-counts?year=2024&month=12
            Route::get('/daily-counts', [App\Http\Controllers\Api\Mobile\DashboardController::class, 'dailyCounts']);
        });

        // ===== Wallet Routes =====
        Route::prefix('wallet')->group(function () {
            // Get wallet data and settings
            // GET /api/mobile/wallet
            Route::get('/', [App\Http\Controllers\Api\Mobile\WalletController::class, 'index']);
            
            // Update wallet notification settings
            // POST /api/mobile/wallet/settings
            Route::post('/settings', [App\Http\Controllers\Api\Mobile\WalletController::class, 'updateSettings']);
        });

        // ===== Buy SMS Routes =====
        Route::prefix('buy-sms')->group(function () {
            // Get buy SMS page data
            // GET /api/mobile/buy-sms
            Route::get('/', [App\Http\Controllers\Api\Mobile\BuySmsController::class, 'index']);
            
            // Create invoice for SMS purchase
            // POST /api/mobile/buy-sms
            Route::post('/', [App\Http\Controllers\Api\Mobile\BuySmsController::class, 'store']);
        });

        // ===== Invoices Routes =====
        Route::prefix('invoices')->group(function () {
            // Get all invoices
            // GET /api/mobile/invoices
            Route::get('/', [App\Http\Controllers\Api\Mobile\InvoiceController::class, 'index']);
            
            // Get invoices by type (paid or proforma)
            // GET /api/mobile/invoices/type/{type}
            Route::get('/type/{type}', [App\Http\Controllers\Api\Mobile\InvoiceController::class, 'byType']);
            
            // Get single invoice details
            // GET /api/mobile/invoices/{id}
            Route::get('/{id}', [App\Http\Controllers\Api\Mobile\InvoiceController::class, 'show']);
        });

        // ===== SMS Routes =====
        Route::prefix('sms')->group(function () {
            // Send SMS
            // POST /api/mobile/sms/send
            Route::post('/send', [App\Http\Controllers\Api\Mobile\SmsController::class, 'send']);
            
            // Get SMS history
            // GET /api/mobile/sms/history?page=1&per_page=20
            Route::get('/history', [App\Http\Controllers\Api\Mobile\SmsController::class, 'history']);
            
            // Get single SMS details
            // GET /api/mobile/sms/{id}
            Route::get('/{id}', [App\Http\Controllers\Api\Mobile\SmsController::class, 'show']);
        });

        // ===== Contacts Routes =====
        Route::prefix('contacts')->group(function () {
            // Get all contacts
            // GET /api/mobile/contacts
            Route::get('/', [App\Http\Controllers\Api\Mobile\ContactController::class, 'index']);
            
            // Delete all contacts (must be before {id} route)
            // DELETE /api/mobile/contacts/delete-all
            Route::delete('/delete-all', [App\Http\Controllers\Api\Mobile\ContactController::class, 'destroyAll']);
            
            // Get single contact
            // GET /api/mobile/contacts/{id}
            Route::get('/{id}', [App\Http\Controllers\Api\Mobile\ContactController::class, 'show']);
            
            // Create contact
            // POST /api/mobile/contacts
            Route::post('/', [App\Http\Controllers\Api\Mobile\ContactController::class, 'store']);
            
            // Update contact
            // PUT /api/mobile/contacts/{id}
            Route::put('/{id}', [App\Http\Controllers\Api\Mobile\ContactController::class, 'update']);
            
            // Delete contact
            // DELETE /api/mobile/contacts/{id}
            Route::delete('/{id}', [App\Http\Controllers\Api\Mobile\ContactController::class, 'destroy']);
        });

        // ===== Maintenance Status Route =====
        // GET /api/mobile/maintenance-status
        Route::get('/maintenance-status', [App\Http\Controllers\Api\Mobile\MaintenanceController::class, 'status']);

        // ===== Notifications Routes =====
        Route::prefix('notifications')->group(function () {
            // Get all notifications
            // GET /api/mobile/notifications
            Route::get('/', [App\Http\Controllers\Api\Mobile\NotificationController::class, 'index']);
            
            // Get unread count
            // GET /api/mobile/notifications/unread-count
            Route::get('/unread-count', [App\Http\Controllers\Api\Mobile\NotificationController::class, 'unreadCount']);
            
            // Test FCM connection
            // GET /api/mobile/notifications/test-fcm
            Route::get('/test-fcm', [App\Http\Controllers\Api\Mobile\NotificationController::class, 'testFcm']);
            
            // Send test notification to current user
            // POST /api/mobile/notifications/send-test
            Route::post('/send-test', [App\Http\Controllers\Api\Mobile\NotificationController::class, 'sendTestNotification']);
            
            // Register FCM token for push notifications
            // POST /api/mobile/notifications/fcm-token
            Route::post('/fcm-token', [App\Http\Controllers\Api\Mobile\NotificationController::class, 'registerFcmToken']);
            
            // Unregister FCM token (on logout)
            // DELETE /api/mobile/notifications/fcm-token
            Route::delete('/fcm-token', [App\Http\Controllers\Api\Mobile\NotificationController::class, 'unregisterFcmToken']);
            
            // Unregister FCM token (alternative POST method)
            // POST /api/mobile/notifications/fcm-token/unregister
            Route::post('/fcm-token/unregister', [App\Http\Controllers\Api\Mobile\NotificationController::class, 'unregisterFcmToken']);
            
            // Mark all as read
            // POST /api/mobile/notifications/mark-all-read
            Route::post('/mark-all-read', [App\Http\Controllers\Api\Mobile\NotificationController::class, 'markAllAsRead']);
            
            // Get single notification details
            // GET /api/mobile/notifications/{id}
            Route::get('/{id}', [App\Http\Controllers\Api\Mobile\NotificationController::class, 'show']);
            
            // Mark notification as read
            // POST /api/mobile/notifications/{id}/read
            Route::post('/{id}/read', [App\Http\Controllers\Api\Mobile\NotificationController::class, 'markAsRead']);
            
            // Acknowledge notification (admin notifications)
            // POST /api/mobile/notifications/{id}/acknowledge
            Route::post('/{id}/acknowledge', [App\Http\Controllers\Api\Mobile\NotificationController::class, 'acknowledge']);
            
            // Delete push notification
            // DELETE /api/mobile/notifications/{id}
            Route::delete('/{id}', [App\Http\Controllers\Api\Mobile\NotificationController::class, 'destroy']);
        });

        // ===== Send SMS Routes =====
        Route::prefix('send-sms')->group(function () {
            // Get send SMS page data (wallet, sender IDs, etc.)
            // GET /api/mobile/send-sms
            Route::get('/', [App\Http\Controllers\Api\Mobile\SendSmsController::class, 'index']);
            
            // Get contacts (favourites or groups)
            // GET /api/mobile/send-sms/contacts?type=favourites
            Route::get('/contacts', [App\Http\Controllers\Api\Mobile\SendSmsController::class, 'getContacts']);
            
            // Calculate SMS cost
            // POST /api/mobile/send-sms/calculate
            Route::post('/calculate', [App\Http\Controllers\Api\Mobile\SendSmsController::class, 'calculateCost']);
            
            // Send SMS immediately
            // POST /api/mobile/send-sms
            Route::post('/', [App\Http\Controllers\Api\Mobile\SendSmsController::class, 'send']);
            
            // Schedule SMS for later
            // POST /api/mobile/send-sms/schedule
            Route::post('/schedule', [App\Http\Controllers\Api\Mobile\SendSmsController::class, 'schedule']);
        });

        // ===== Received SMS Routes =====
        Route::prefix('received-sms')->group(function () {
            // Get received SMS page data (filter options, keywords)
            // GET /api/mobile/received-sms
            Route::get('/', [App\Http\Controllers\Api\Mobile\ReceivedSmsController::class, 'index']);
            
            // Get received SMS messages with filters and pagination
            // GET /api/mobile/received-sms/messages?filter=all&page=1&per_page=20
            Route::get('/messages', [App\Http\Controllers\Api\Mobile\ReceivedSmsController::class, 'messages']);
            
            // Get received SMS statistics
            // GET /api/mobile/received-sms/stats
            Route::get('/stats', [App\Http\Controllers\Api\Mobile\ReceivedSmsController::class, 'stats']);
            
            // Export received SMS to CSV
            // GET /api/mobile/received-sms/export
            Route::get('/export', [App\Http\Controllers\Api\Mobile\ReceivedSmsController::class, 'export']);
            
            // Get single message details (must be last due to dynamic {id})
            // GET /api/mobile/received-sms/{id}
            Route::get('/{id}', [App\Http\Controllers\Api\Mobile\ReceivedSmsController::class, 'show']);
        });

        // ===== Sent SMS Routes =====
        Route::prefix('sent-sms')->group(function () {
            // Get sent SMS page data (filter options)
            // GET /api/mobile/sent-sms
            Route::get('/', [App\Http\Controllers\Api\Mobile\SentSmsController::class, 'index']);
            
            // Get sent SMS messages with filters and pagination
            // GET /api/mobile/sent-sms/messages?route=all&delivery_status=all&page=1&per_page=20
            Route::get('/messages', [App\Http\Controllers\Api\Mobile\SentSmsController::class, 'messages']);
            
            // Get sent SMS statistics
            // GET /api/mobile/sent-sms/stats
            Route::get('/stats', [App\Http\Controllers\Api\Mobile\SentSmsController::class, 'stats']);
            
            // Get single message details
            // GET /api/mobile/sent-sms/{tableName}/{id}
            Route::get('/{tableName}/{id}', [App\Http\Controllers\Api\Mobile\SentSmsController::class, 'show']);
        });

              // ===== Keywords Routes =====
        Route::prefix('keywords')->group(function () {
            // Get all keywords
            // GET /api/mobile/keywords
            Route::get('/', [App\Http\Controllers\Api\Mobile\KeywordsController::class, 'index']);
            
            // Check keyword availability
            // POST /api/mobile/keywords/check-availability
            Route::post('/check-availability', [App\Http\Controllers\Api\Mobile\KeywordsController::class, 'checkAvailability']);
            
            // Get single keyword details
            // GET /api/mobile/keywords/{id}
            Route::get('/{id}', [App\Http\Controllers\Api\Mobile\KeywordsController::class, 'show']);
            
            // Update SMS Responder settings
            // POST /api/mobile/keywords/{id}/sms-responder
            Route::post('/{id}/sms-responder', [App\Http\Controllers\Api\Mobile\KeywordsController::class, 'updateSmsResponder']);
            
            // Update Email Forwarder settings
            // POST /api/mobile/keywords/{id}/email-forwarder
            Route::post('/{id}/email-forwarder', [App\Http\Controllers\Api\Mobile\KeywordsController::class, 'updateEmailForwarder']);
            
            // Toggle module on/off
            // POST /api/mobile/keywords/{id}/toggle-module
            Route::post('/{id}/toggle-module', [App\Http\Controllers\Api\Mobile\KeywordsController::class, 'toggleModule']);
            
            // Get subkeywords
            // GET /api/mobile/keywords/{id}/subkeywords
            Route::get('/{id}/subkeywords', [App\Http\Controllers\Api\Mobile\KeywordsController::class, 'getSubkeywords']);
            
            // Add subkeyword
            // POST /api/mobile/keywords/{id}/subkeywords
            Route::post('/{id}/subkeywords', [App\Http\Controllers\Api\Mobile\KeywordsController::class, 'addSubkeyword']);
            
            // Delete subkeyword
            // DELETE /api/mobile/keywords/{id}/subkeywords/{subkeyword}
            Route::delete('/{id}/subkeywords/{subkeyword}', [App\Http\Controllers\Api\Mobile\KeywordsController::class, 'deleteSubkeyword']);
            
            // Get SMS Forwarder settings
            // GET /api/mobile/keywords/{id}/sms-forwarder
            Route::get('/{id}/sms-forwarder', [App\Http\Controllers\Api\Mobile\KeywordsController::class, 'getSmsForwarder']);
            
            // Update SMS Forwarder settings
            // POST /api/mobile/keywords/{id}/sms-forwarder
            Route::post('/{id}/sms-forwarder', [App\Http\Controllers\Api\Mobile\KeywordsController::class, 'updateSmsForwarder']);
            
            // Get Subscription settings
            // GET /api/mobile/keywords/{id}/subscription
            Route::get('/{id}/subscription', [App\Http\Controllers\Api\Mobile\KeywordsController::class, 'getSubscription']);
            
            // Update Subscription settings
            // POST /api/mobile/keywords/{id}/subscription
            Route::post('/{id}/subscription', [App\Http\Controllers\Api\Mobile\KeywordsController::class, 'updateSubscription']);
            
            // Get WAP Push Responder settings
            // GET /api/mobile/keywords/{id}/wap-push-responder
            Route::get('/{id}/wap-push-responder', [App\Http\Controllers\Api\Mobile\KeywordsController::class, 'getWapPushResponder']);
            
            // Update WAP Push Responder settings
            // POST /api/mobile/keywords/{id}/wap-push-responder
            Route::post('/{id}/wap-push-responder', [App\Http\Controllers\Api\Mobile\KeywordsController::class, 'updateWapPushResponder']);
            
            // Get module info (Business Card, Voting restrictions)
            // GET /api/mobile/keywords/{id}/module-info/{module}
            Route::get('/{id}/module-info/{module}', [App\Http\Controllers\Api\Mobile\KeywordsController::class, 'getModuleInfo']);
        });

        // ===== Contracts Routes =====
        Route::prefix('contracts')->group(function () {
            // Get all contracts
            // GET /api/mobile/contracts
            Route::get('/', [App\Http\Controllers\Api\Mobile\ContractController::class, 'index']);
            
            // Get single contract details
            // GET /api/mobile/contracts/{id}
            Route::get('/{id}', [App\Http\Controllers\Api\Mobile\ContractController::class, 'show']);
            
            // Sign a contract
            // POST /api/mobile/contracts/{id}/sign
            Route::post('/{id}/sign', [App\Http\Controllers\Api\Mobile\ContractController::class, 'sign']);
            
            // Get download URL for contract
            // GET /api/mobile/contracts/{id}/download
            Route::get('/{id}/download', [App\Http\Controllers\Api\Mobile\ContractController::class, 'download']);
        });

        // ===== Delivery Receipt Routes =====
        Route::prefix('delivery-receipt')->group(function () {
            // Get delivery receipt settings
            // GET /api/mobile/delivery-receipt
            Route::get('/', [App\Http\Controllers\Api\Mobile\DeliveryReceiptController::class, 'index']);
            
            // Update delivery receipt URL
            // POST /api/mobile/delivery-receipt/url
            Route::post('/url', [App\Http\Controllers\Api\Mobile\DeliveryReceiptController::class, 'updateUrl']);
            
            // Test delivery receipt mechanism
            // POST /api/mobile/delivery-receipt/test
            Route::post('/test', [App\Http\Controllers\Api\Mobile\DeliveryReceiptController::class, 'test']);
        });

        // ===== Quick Campaign Routes =====
        Route::prefix('campaign')->group(function () {
            // Get available sender IDs
            // GET /api/mobile/campaign/sender-ids
            Route::get('/sender-ids', [App\Http\Controllers\Api\Mobile\QuickCampaignController::class, 'getSenderIds']);
            
            // Submit quick campaign
            // POST /api/mobile/campaign/quick
            Route::post('/quick', [App\Http\Controllers\Api\Mobile\QuickCampaignController::class, 'submit']);
            
            // Upload bulk campaign file
            // POST /api/mobile/campaign/bulk-upload
            Route::post('/bulk-upload', [App\Http\Controllers\Api\Mobile\QuickCampaignController::class, 'uploadBulkCampaign']);
            
            // Download sample CSV
            // GET /api/mobile/campaign/sample-csv
            Route::get('/sample-csv', [App\Http\Controllers\Api\Mobile\QuickCampaignController::class, 'downloadSampleCsv']);
            
            // Get CSV format guide
            // GET /api/mobile/campaign/csv-guide
            Route::get('/csv-guide', [App\Http\Controllers\Api\Mobile\QuickCampaignController::class, 'getCsvFormatGuide']);
            
            // Get campaign history
            // GET /api/mobile/campaign/history
            Route::get('/history', [App\Http\Controllers\Api\Mobile\QuickCampaignController::class, 'history']);
            
            // Get single campaign details
            // GET /api/mobile/campaign/{campaignId}
            Route::get('/{campaignId}', [App\Http\Controllers\Api\Mobile\QuickCampaignController::class, 'show']);
            
            // Pause a campaign
            // POST /api/mobile/campaign/{campaignId}/pause
            Route::post('/{campaignId}/pause', [App\Http\Controllers\Api\Mobile\QuickCampaignController::class, 'pause']);
            
            // Resume a campaign
            // POST /api/mobile/campaign/{campaignId}/resume
            Route::post('/{campaignId}/resume', [App\Http\Controllers\Api\Mobile\QuickCampaignController::class, 'resume']);
            
            // Delete a campaign
            // DELETE /api/mobile/campaign/{campaignId}
            Route::delete('/{campaignId}', [App\Http\Controllers\Api\Mobile\QuickCampaignController::class, 'delete']);
        });

        // ===== Blacklist Routes =====
        // Prefix: /api/mobile/blacklist
        Route::prefix('blacklist')->group(function () {
            // Get blacklist with statistics
            // GET /api/mobile/blacklist
            Route::get('/', [App\Http\Controllers\Api\Mobile\BlacklistController::class, 'index']);
            
            // Download blacklist as CSV
            // GET /api/mobile/blacklist/download
            Route::get('/download', [App\Http\Controllers\Api\Mobile\BlacklistController::class, 'download']);
            
            // Unblock a number (remove from blacklist)
            // DELETE /api/mobile/blacklist/{id}
            Route::delete('/{id}', [App\Http\Controllers\Api\Mobile\BlacklistController::class, 'unblock']);
        });

        // ===== Accounts Routes =====
        // Prefix: /api/mobile/accounts
        Route::prefix('accounts')->group(function () {
            // Get all accounts (master and sub-accounts)
            // GET /api/mobile/accounts
            Route::get('/', [App\Http\Controllers\Api\Mobile\AccountsController::class, 'index']);
            
            // Check if user can add sub-accounts
            // GET /api/mobile/accounts/can-add
            Route::get('/can-add', [App\Http\Controllers\Api\Mobile\AccountsController::class, 'canAddSubAccount']);
            
            // Transfer wallet funds between accounts
            // POST /api/mobile/accounts/transfer
            Route::post('/transfer', [App\Http\Controllers\Api\Mobile\AccountsController::class, 'transfer']);
            
            // Add new sub-account
            // POST /api/mobile/accounts
            Route::post('/', [App\Http\Controllers\Api\Mobile\AccountsController::class, 'store']);
        });

        // ===== STOP Commands Routes =====
        Route::prefix('stop-commands')->group(function () {
            // Get STOP command settings
            // GET /api/mobile/stop-commands
            Route::get('/', [App\Http\Controllers\Api\Mobile\StopCommandController::class, 'index']);
            
            // Update STOP command settings
            // POST /api/mobile/stop-commands
            Route::post('/', [App\Http\Controllers\Api\Mobile\StopCommandController::class, 'update']);
            
            // Get STOP command statistics
            // GET /api/mobile/stop-commands/stats
            Route::get('/stats', [App\Http\Controllers\Api\Mobile\StopCommandController::class, 'stats']);
        });

        // ===== Profile Routes =====
        Route::prefix('profile')->group(function () {
            // Get user profile
            // GET /api/mobile/profile
            Route::get('/', [App\Http\Controllers\Api\Mobile\ProfileController::class, 'index']);
            
            // Update user profile
            // PUT /api/mobile/profile
            Route::put('/', [App\Http\Controllers\Api\Mobile\ProfileController::class, 'update']);
            
            // Change password
            // POST /api/mobile/profile/change-password
            Route::post('/change-password', [App\Http\Controllers\Api\Mobile\ProfileController::class, 'changePassword']);
            
            // Add IP to whitelist
            // POST /api/mobile/profile/ip
            Route::post('/ip', [App\Http\Controllers\Api\Mobile\ProfileController::class, 'addIp']);
            
            // Remove IP from whitelist
            // DELETE /api/mobile/profile/ip
            Route::delete('/ip', [App\Http\Controllers\Api\Mobile\ProfileController::class, 'removeIp']);
        });

        // ===== Groups Routes =====
        Route::prefix('groups')->group(function () {
            // Get all groups with member counts
            // GET /api/mobile/groups
            Route::get('/', [App\Http\Controllers\Api\Mobile\GroupController::class, 'index']);
            
            // Get single group with members
            // GET /api/mobile/groups/{id}
            Route::get('/{id}', [App\Http\Controllers\Api\Mobile\GroupController::class, 'show']);
            
            // Create new group
            // POST /api/mobile/groups
            Route::post('/', [App\Http\Controllers\Api\Mobile\GroupController::class, 'store']);
            
            // Update group
            // PUT /api/mobile/groups/{id}
            Route::put('/{id}', [App\Http\Controllers\Api\Mobile\GroupController::class, 'update']);
            
            // Delete group
            // DELETE /api/mobile/groups/{id}
            Route::delete('/{id}', [App\Http\Controllers\Api\Mobile\GroupController::class, 'destroy']);
            
            // Get available contacts to add to group
            // GET /api/mobile/groups/{id}/available-contacts
            Route::get('/{id}/available-contacts', [App\Http\Controllers\Api\Mobile\GroupController::class, 'getAvailableContacts']);
            
            // Add member to group
            // POST /api/mobile/groups/{id}/members
            Route::post('/{id}/members', [App\Http\Controllers\Api\Mobile\GroupController::class, 'addMember']);
            
            // Remove member from group
            // DELETE /api/mobile/groups/{id}/members/{contactId}
            Route::delete('/{id}/members/{contactId}', [App\Http\Controllers\Api\Mobile\GroupController::class, 'removeMember']);
        });

        // ===== Token Validation Test Routes =====
        // These endpoints demonstrate how token validation works
        Route::prefix('token-test')->group(function () {
            // Get authenticated user from token
            // GET /api/mobile/token-test/user
            Route::get('/user', [App\Http\Controllers\Api\Mobile\TokenExampleController::class, 'getAuthenticatedUser']);
            
            // Get user by bigid
            // GET /api/mobile/token-test/user-by-bigid
            Route::get('/user-by-bigid', [App\Http\Controllers\Api\Mobile\TokenExampleController::class, 'getUserByBigid']);
            
            // Get current token info
            // GET /api/mobile/token-test/current-token
            Route::get('/current-token', [App\Http\Controllers\Api\Mobile\TokenExampleController::class, 'getCurrentToken']);
            
            // Validate token with permissions check
            // GET /api/mobile/token-test/validate
            Route::get('/validate', [App\Http\Controllers\Api\Mobile\TokenExampleController::class, 'validateWithPermissions']);
        });
    });

    // ===== Manual Token Validation (No Middleware) =====
    // This endpoint validates token manually without middleware
    Route::post('/validate-token', [App\Http\Controllers\Api\Mobile\TokenExampleController::class, 'manualTokenValidation']);

   
});
