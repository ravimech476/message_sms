<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\SmsWalletController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ClientProfileController;
use App\Http\Controllers\SendSmsController;
use App\Http\Controllers\SMSController;
use App\Http\Controllers\SmsSmppController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\NumberController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\TechnicalDocumentController;
use App\Http\Controllers\StopCommandController;
use App\Http\Controllers\AvailableGroupsController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Middleware\CustomerMiddleware;
use App\Http\Controllers\CustomerGuideController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardApiController;
use App\Http\Controllers\ActiveBlacklistController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AddCustomerController;
use App\Http\Controllers\Admin\SmsShortcodeController;
use App\Http\Controllers\Admin\CookieController;
use App\Http\Controllers\Admin\UserGuideController;
use App\Http\Controllers\Admin\ClientEmailController;
use App\Http\Controllers\Admin\PostPayReportController;
use App\Http\Controllers\Admin\DailySmsReportController;
use App\Http\Controllers\Admin\MoneyTransferredReportController;
use App\Http\Controllers\Admin\UserNoteController;
use App\Http\Controllers\Admin\AdminEmailController;
use App\Http\Controllers\Admin\AutoLoginController;
use App\Http\Controllers\Admin\MonthlyReportController;
use App\Http\Controllers\Admin\WalletUserController;
use App\Http\Controllers\Admin\OutstandingInvoiceController;
use App\Http\Controllers\DLRWebhookController;
use App\Http\Controllers\Admin\ProfileLockController;
use App\Http\Controllers\Admin\IpAddressResstrictionController;
use App\Http\Controllers\Admin\InstanceController;
use App\Http\Controllers\Admin\VirtualNumberController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Campaign\CampaignDashboardController;
use App\Http\Middleware\CampaignMiddleware;
use App\Http\Controllers\Campaign\CampaignAuthController;
use App\Http\Controllers\SinchSmsController;




// Customer Authentication Routes
Route::get('/', [LoginController::class, 'showLoginForm'])->name('showloginform');
Route::post('login', [LoginController::class, 'login'])->name('login');
Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

// Admin Authentication Routes
Route::get('/', [AdminAuthController::class, 'adminShowLoginForm'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.post');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

// Campaign Authentication Routes
Route::get('/', [CampaignAuthController::class, 'campaignLoginForm'])->name('campaign.login');
Route::post('/campaign/login', [CampaignAuthController::class, 'campaignLogin'])->name('campaign.login.post');
Route::post('/campaign/logout', [CampaignAuthController::class, 'campaignLogout'])->name('campaign.logout');


Route::get('forgot_password', [LoginController::class, 'forgot_password'])->name('forgot_password');
Route::post('/forgot-password', [ForgotPasswordController::class, 'requestReset'])->name('forgot.password');

Route::get('/reset-password/{userId}', [ForgotPasswordController::class, 'showResetPasswordForm'])->name('reset.password');

Route::post('/reset-password/{userId}', [ForgotPasswordController::class, 'updatePassword'])->name('update.password');

Route::get('/link-expired', [ForgotPasswordController::class, 'expiredLink'])->name('link.expired');

// Customer Maintenance Routes
Route::get('/maintenance', [LoginController::class, 'showMaintenancePage'])->name('customer.maintenance');
Route::get('/maintenance/check', [LoginController::class, 'checkMaintenanceStatus'])->name('customer.maintenance.check');

// Test route to debug
Route::get('/admin-test', function () {
    return 'Admin route is working!';
});

// Test route for error email notification
Route::get('/test-error-email', function () {
    // Only allow in non-production or with secret key
    if (app()->environment('production') && request('key') !== 'smsexpert2024') {
        abort(404);
    }

    throw new \Exception('This is a test exception to verify error email notifications are working correctly.');
});

// Route::get('/smsg/sms.mes', [SmsController::class, 'sendSmsApi']);

// Invoice Download Route
Route::get('/admin/invoice/{invoice}/download', [OutstandingInvoiceController::class, 'downloadInvoice'])->name('admin.invoice.download');

Route::post('/dlr-webhook', [DLRWebhookController::class, 'handle']);
Route::get('/dlr-webhook/test', [DLRWebhookController::class, 'test']);

// Inbound SMS Webhook Routes (public - no auth required)
// Route::post('/webhook/inbound-sms', [App\Http\Controllers\InboundSmsWebhookController::class, 'handle'])->name('webhook.inbound-sms');
Route::get('/webhook/inbound-sms/test', [App\Http\Controllers\InboundSmsWebhookController::class, 'test'])->name('webhook.inbound-sms.test');

// SMPP Queue Routes
Route::middleware(['api'])->group(function () {
    Route::post('/api/sms/send', [App\Http\Controllers\SmppSmsController::class, 'sendSms']);
    Route::get('/api/sms/status/{queueId}', [App\Http\Controllers\SmppSmsController::class, 'getSmsStatus']);
    Route::get('/api/queue/status', [App\Http\Controllers\SmppSmsController::class, 'queueStatus']);
    Route::get('/api/queue/depth', [App\Http\Controllers\SmppSmsController::class, 'queueDepth']);
    Route::post('/api/queue/retry-failed', [App\Http\Controllers\SmppSmsController::class, 'retryFailed']);
    Route::post('/api/queue/purge-failed', [App\Http\Controllers\SmppSmsController::class, 'purgeFailedQueue']);
});

// Debug Nexmo Pricing API (public - no auth required for testing)
Route::get('/api/debug/nexmo-pricing', [App\Http\Controllers\Admin\CountryPricingController::class, 'debugNexmoPricing'])->name('api.debug.nexmo-pricing');

Route::get('/profile/confirm/{token}', [ClientProfileController::class, 'confirmProfile'])->name('profile.confirm');

### Link Expire route ###
Route::get('/profile/update/expired', [ClientProfileController::class, 'linkExpired'])->name('profile.linkExpired');

### Link Expire route ###
Route::get('/profile/update/confirm', [ClientProfileController::class, 'confirmProfileUpdate'])->name('profile.update.confirm');

### Admin To Dashboard route ###
Route::get('/autologin', [AutoLoginController::class, 'loginWithToken'])->name('autologin');

### Campaign To Dashboard route ###
Route::get('/autologindash', [CampaignDashboardController::class, 'loginDashboardToken'])->name('autologin.dashboard');

### Admin To Campaign route ###
Route::get('/autologincampaign', [AutoLoginController::class, 'loginWithTokenCampaign'])->name('autologin.campaign');

### Old System to New System - Auto-login with credentials ###
Route::get('/autologin-credentials', [AutoLoginController::class, 'loginWithCredentials'])->name('autologin.credentials');
Route::get('/autologin-campaign-credentials', [AutoLoginController::class, 'loginCampaignWithCredentials'])->name('autologin.campaign.credentials');

### Customer To Campaign route ###
Route::get('/autologincustomer', [SMSController::class, 'loginWithTokenCampaignCustomer'])->name('autologin.campaign.customer');


Route::middleware([CustomerMiddleware::class])->group(function () {

    // Customer-facing user guides — served from outside public/, so a valid customer session is required.
    Route::get('/customer/userguide/{guide}', [CustomerGuideController::class, 'show'])->name('customer.userguide.show');
    Route::get('/customer/userguide-screenshots/{file}', [CustomerGuideController::class, 'screenshot'])->name('customer.userguide.screenshot');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/stats', [DashboardController::class, 'getDashboardStats'])->name('dashboard.stats');
    Route::get('dashboard/charts', [DashboardController::class, 'getDashboardCharts'])->name('dashboard.charts');

    // Enhanced Dashboard API Routes
    Route::get('api/dashboard/recent-activity', [DashboardApiController::class, 'getRecentActivity'])->name('api.dashboard.recent-activity');
    Route::get('api/dashboard/live-stats', [DashboardApiController::class, 'getLiveStats'])->name('api.dashboard.live-stats');
    Route::get('api/dashboard/hourly-trends', [DashboardApiController::class, 'getHourlyTrends'])->name('api.dashboard.hourly-trends');
    Route::get('api/dashboard/top-destinations', [DashboardApiController::class, 'getTopDestinations'])->name('api.dashboard.top-destinations');

    // Throughput Status Route
    Route::get('api/throughput/status', [SMSController::class, 'getThroughputStatus'])->name('api.throughput.status');

    // Wallet Status Route
    Route::get('api/wallet/status', [SMSController::class, 'getWalletStatus'])->name('api.wallet.status');
    // Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::resource('sms_wallet', SmsWalletController::class);
    Route::get('buysms', [SmsWalletController::class, 'buysms'])->name('buysms');
    Route::get('contract', [SmsWalletController::class, 'contract'])->name('contract');
    Route::post('/update-settings', [UserController::class, 'update'])->name('update.settings');
    Route::get('profile', [ClientProfileController::class, 'index'])->name('profile');
    Route::post('/profile/submit', [ClientProfileController::class, 'submitProfile'])->name('profile.submit');

    Route::get('/profile_lock', [ClientProfileController::class, 'ProfileLock'])->name('profile.lock');



    ### Send SMS route ###
    Route::get('sendsms', [SMSController::class, 'index'])->name('sendsms');
    Route::match(['get', 'post'], 'received-sms', [SMSController::class, 'receivedsms'])->name('received-sms');
    Route::match(['get', 'post'], 'sentsms', [SMSController::class, 'sentSms'])->name('sentsms');
    Route::get('keywords', [SMSController::class, 'keywords'])->name('keywords');
    Route::match(['get', 'post'], 'delivery-receipt', [SMSController::class, 'deliveryReceipt'])->name('delivery-receipt');
    Route::get('keyword-config/{itaggId}/{keyword}', [SMSController::class, 'config'])->name('keywords.config');


    ### Customer To Campaign route ###
    Route::get('/customer/campaign/{username}', [SMSController::class, 'CustomerTokenAndRedirect'])->name('customer.link.redirect');

    // sms-responder Routes
    Route::get('keyword-config/{itaggId}/{keyword}/sms-responder', [SMSController::class, 'smsResponder'])->name('keywords.sms-responder');
    Route::post('/keyword-config/saveresponder', [SMSController::class, 'savesmsResponder'])->name('keywords.sms-responder.save');

    // sms-email forwarder Routes
    Route::get('keyword-config/{itaggId}/{keyword}/forwarder', [SMSController::class, 'emailForwarder'])->name('keywords.email-forwarder');
    Route::post('/keyword-config/saveforwarder', [SMSController::class, 'saveEmailForwarder'])->name('keywords.email-forwarder.save');

    // sms forwarder Routes
    Route::get('keyword-config/{itaggId}/{keyword}/SMSforwarder', [SMSController::class, 'SMSForwarder'])->name('keywords.sms-forwarder');
    Route::post('/keyword-config/savesmsforwarder', [SMSController::class, 'saveSMSForwarder'])->name('keywords.sms-forwarder.save');

    // Business Card Routes
    Route::get('keyword-config/{itaggId}/{keyword}/businesscard', [SMSController::class, 'BusinessCard'])->name('keywords.business-card');
    Route::post('/keyword-config/savebusinesscard', [SMSController::class, 'saveBusinessCard'])->name('keywords.business-card.save');

    // Subscription Routes
    Route::get('keyword-config/{itaggId}/{keyword}/subscription', [SMSController::class, 'Subscription'])->name('keywords.subscription');
    Route::post('/keyword-config/savesubscription', [SMSController::class, 'saveSubscription'])->name('keywords.subscription.save');

    // WAPPushResponder Routes
    Route::get('keyword-config/{itaggId}/{keyword}/WAPpushresponder', [SMSController::class, 'WAPPushResponder'])->name('keywords.WAPpushresponder');
    Route::post('/keyword-config/savewappushresponder', [SMSController::class, 'saveWAPPushResponder'])->name('keywords.WAPpushresponder.save');

    // Voting Routes
    Route::get('keyword-config/{itaggId}/{keyword}/voting', [SMSController::class, 'Voting'])->name('keywords.voting');
    Route::post('/keyword-config/savevoting', [SMSController::class, 'saveVoting'])->name('keywords.voting.save');

    // Subkeyword Management Routes
    Route::get('/keyword/subkeywords/{itagg_id}', [SMSController::class, 'getSubkeywords'])->name('keywords.subkeywords.list');
    Route::post('/keyword/subkeyword/add', [SMSController::class, 'addSubkeyword'])->name('keywords.subkeyword.add');
    Route::post('/keyword/subkeyword/delete', [SMSController::class, 'deleteSubkeyword'])->name('keywords.subkeyword.delete');

    // Module Toggle Routes
    Route::post('/keyword/module/toggle', [SMSController::class, 'toggleModule'])->name('keywords.module.toggle');
    Route::get('/keyword/module/status', [SMSController::class, 'getModuleStatus'])->name('keywords.module.status');

    // Subkeyword Config Route (for configuring individual subkeywords)
    Route::get('/keyword/{itaggId}/{subkeyword}/config', [SMSController::class, 'subkeywordConfig'])->name('keywords.subkeyword.config');

    // Keyword Registration Routes
    Route::get('/keyword/register', [SMSController::class, 'registerKeywordPage'])->name('keywords.register');
    Route::post('/keyword/check', [SMSController::class, 'checkKeywordAvailability'])->name('keywords.check');
    Route::post('/keyword/register', [SMSController::class, 'registerKeyword'])->name('keywords.register.submit');

    Route::post('sent_sms_details', [SMSController::class, 'sentSmsDetails'])->name('sentSmsDetails');
    Route::get('sentsms/{tableName}/{id}', [SMSController::class, 'showSmsDetails'])->name('sentsms.show');

    Route::post('/send-sms-client', [SMSController::class, 'sendMessage'])->name('send.sms.client');

    Route::get('/send-sinch-sms', [SMSController::class, 'sendSinch']);


    Route::post('/calculate-sms-cost', [SMSController::class, 'calculateCost'])->name('calculate.sms.cost');

    Route::post('/schedule-sms-client', [SMSController::class, 'scheduleSendMessage'])->name('schedule.sms.client');

    // SMPP Queue Status Routes
    Route::get('/smpp/status', [SMSController::class, 'checkSmppStatus'])->name('smpp.status');
    Route::get('/smpp/queue-stats', [SMSController::class, 'getQueueStats'])->name('smpp.queue.stats');

    // Route::post('/send-sms', [SmsSmppController::class, 'sendSms'])->name('smpp.client');

    Route::post('blacklist-upload', [FileController::class, 'processFileUpload'])->name('blacklist.upload');


    ### Group Controller ###
    Route::resource('groups', GroupController::class);

    Route::get('/group/single_group/{id}', [GroupController::class, 'single_group'])->name('group.single.mobile');

    Route::post('/save-addressbook', [GroupController::class, 'saveAddressBook'])->name('save.addressbook');

    Route::post('/update-addressbook', [GroupController::class, 'updateAddressBook'])->name('update.addressbook');

    ### Number Controller ###
    Route::resource('numbers', NumberController::class);

    Route::get('/upload', [NumberController::class, 'uploadIndex'])->name('number.upload');

    Route::post('/upload-file', [NumberController::class, 'uploadFile'])->name('upload.file');

    Route::get('/number-download-csv', [NumberController::class, 'downloadCsvReport'])->name('number.download.csv');

    Route::post('numbers/destroyAll', [NumberController::class, 'destroyAll'])->name('numbers.destroyAll');

    Route::get('clean_bad_numbers', [NumberController::class, 'cleanBadNumbers'])->name('clean.bad.numbers');

    #### Buy SMS Invoice
    Route::post('/buysms-invoice', [SmsWalletController::class, 'buysmsInvoice'])->name('buysms.invoice');

    Route::get('platforme.net', [SmsWalletController::class, 'buysmsInvoiceView'])->name('buysms.invoice.view');

    ### Invoice View
    Route::get('view_invoices', [InvoiceController::class, 'index'])->name('view.invoices');

    Route::get('/invoice/{id}', [InvoiceController::class, 'show'])->name('invoice.show');

    Route::get('/invoice/{id}/download', [InvoiceController::class, 'download'])->name('invoice.download');

    Route::post('/send-invoice', [InvoiceController::class, 'sendInvoice'])->name('send.invoice');

    #### Technical Document
    Route::get('support', [TechnicalDocumentController::class, 'index'])->name('technical.support');

    Route::get('wholesalewalletcheck', [TechnicalDocumentController::class, 'wholeSaleWalletCheck'])->name('technical.wholesalewalletcheck');

    Route::get('sendingsms', [TechnicalDocumentController::class, 'sendingSMS'])->name('technical.sendingsms');

    Route::get('receivingdlrs', [TechnicalDocumentController::class, 'receivingdLRS'])->name('technical.receivingdlrs');

    Route::get('receivingsms', [TechnicalDocumentController::class, 'receivingSMS'])->name('technical.receivingsms');

    Route::get('keywordwhois', [TechnicalDocumentController::class, 'keywordwhoIs'])->name('technical.keywordwhois');

    Route::get('keywordregistration', [TechnicalDocumentController::class, 'keywordRegistration'])->name('technical.keywordregistration');

    Route::get('keywordsetforwardings', [TechnicalDocumentController::class, 'keywordsetForwardings'])->name('technical.keywordsetforwardings');

    Route::get('listkeywords', [TechnicalDocumentController::class, 'listkeyWords'])->name('technical.listkeywords');

    Route::get('keywordrenewal', [TechnicalDocumentController::class, 'keywordRenewal'])->name('technical.keywordrenewal');

    Route::get('keyworddeletion', [TechnicalDocumentController::class, 'keywordDeletion'])->name('technical.keyworddeletion');

    Route::get('keywordreplacement', [TechnicalDocumentController::class, 'keywordReplacement'])->name('technical.keywordreplacement');

    ### Keyword Response Code ###
    Route::get('wholesaleapiresponsecodes', [TechnicalDocumentController::class, 'wholesaleapiResponseCodes'])->name('technical.wholesaleapiresponsecodes');

    #### New Chart Dashboard
    Route::get('new_dashboard', [LoginController::class, 'new_dashboard'])->name('new.dashboard');

    Route::get('/get-monthly-counts', [LoginController::class, 'getMonthlyCounts']);

    Route::get('/get-daily-counts', [LoginController::class, 'getDailyCounts']);

    Route::get('/monthly-sms-profit', [LoginController::class, 'getMonthlySmsProfit']);

    Route::get('/monthly-sms-cost', [LoginController::class, 'getMonthlySmsCost']);

    Route::get('/monthly-sms-userprice', [LoginController::class, 'getMonthlySmsUserPrice']);

    ##### StopCommandController #####
    Route::get('stop', [StopCommandController::class, 'index'])->name('stopcommand.index');

    Route::post('/stop_command_update', [StopCommandController::class, 'update'])->name('stopcommand.update');

    Route::post('/get-available-contacts', [AvailableGroupsController::class, 'getAvailableContacts']);

    Route::get('/blacklist', [ActiveBlacklistController::class, 'index'])->name('blacklist.index');

    Route::get('/download-blacklist', [ActiveBlacklistController::class, 'downloadBlacklistCsv'])->name('blacklist.download');
    Route::delete('/blacklist/{id}', [ActiveBlacklistController::class, 'destroy'])->name('blacklist.destroy');
    // Route::get('/autologin', [AutoLoginController::class, 'loginWithToken'])->name('autologin');

    // Customer Notifications API Routes
    Route::get('/api/notifications', [App\Http\Controllers\CustomerNotificationController::class, 'getNotifications'])->name('customer.notifications');
    Route::get('/api/notifications/unread-count', [App\Http\Controllers\CustomerNotificationController::class, 'getUnreadCount'])->name('customer.notifications.unread');
    Route::post('/api/notifications/{id}/read', [App\Http\Controllers\CustomerNotificationController::class, 'markAsRead'])->name('customer.notifications.read');
    Route::post('/api/notifications/{id}/acknowledge', [App\Http\Controllers\CustomerNotificationController::class, 'acknowledge'])->name('customer.notifications.acknowledge');
    Route::get('/api/notifications/pending-acknowledgement', [App\Http\Controllers\CustomerNotificationController::class, 'getPendingAcknowledgement'])->name('customer.notifications.pending');

    // Contracts Routes
    Route::get('/contracts', [App\Http\Controllers\ContractController::class, 'index'])->name('contracts.index');
    Route::get('/contracts/{id}', [App\Http\Controllers\ContractController::class, 'show'])->name('contracts.show');
    Route::post('/contracts/{id}/sign', [App\Http\Controllers\ContractController::class, 'sign'])->name('contracts.sign');
    Route::get('/contracts/{id}/download', [App\Http\Controllers\ContractController::class, 'downloadFile'])->name('contracts.download');
    Route::get('/contracts/{id}/view-file', [App\Http\Controllers\ContractController::class, 'viewFile'])->name('contracts.view-file');
    Route::post('/contracts/agree', [App\Http\Controllers\ContractController::class, 'agree'])->name('contracts.agree');

    // Tour Guide Routes
    Route::get('/tour/status', [App\Http\Controllers\TourController::class, 'status'])->name('tour.status');
    Route::post('/tour/complete', [App\Http\Controllers\TourController::class, 'completeCustomerTour'])->name('tour.complete');
    Route::post('/tour/reset', [App\Http\Controllers\TourController::class, 'resetCustomerTour'])->name('tour.reset');

});

// Admin Routes
Route::middleware([AdminMiddleware::class])->group(function () {
    // Staff-only user guides — served from outside public/, so a valid admin session is required.
    Route::get('/admin/userguide/{guide}', [UserGuideController::class, 'show'])->name('admin.userguide.show');
    Route::get('/admin/userguide-screenshots/{file}', [UserGuideController::class, 'screenshot'])->name('admin.userguide.screenshot');

    Route::get('/admin/dashboard', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/sms-summary', [AdminController::class, 'getSmsSummary'])->name('admin.sms-summary');
    Route::get('/admin/get-daily-counts', [AdminController::class, 'getDailyCountsAdmin']);
    Route::get('/admin/monthly-sms-profit', [AdminController::class, 'getMonthlySmsProfitAdmin']);
    Route::get('/admin/monthly-sms-cost', [AdminController::class, 'getMonthlySmsCostAdmin']);
    Route::get('/admin/monthly-sms-userprice', [AdminController::class, 'getMonthlySmsUserPriceAdmin']);
    Route::get('/admin/get-monthly-counts', [AdminController::class, 'getMonthlyCountsAdmin']);
    Route::get('admin/customers', [AdminUserController::class, 'index'])->name('admin.users');
    Route::post('admin/customers/bulk-migrate', [AdminUserController::class, 'bulkMigrate'])->name('admin.users.bulk-migrate');
    Route::get('admin/user/{id}', [AdminUserController::class, 'show'])->name('admin.user.show');
    Route::get('/admin/customer/create', [AddCustomerController::class, 'create'])->name('admin.customer.create');
    Route::post('/save-client-lead', [AddCustomerController::class, 'store'])->name('save.client.lead');
    Route::put('/customers/update/{id}', [AddCustomerController::class, 'update'])->name('customers.update');
    Route::get('/create/keyword/{id}', [AddCustomerController::class, 'createKeyword'])->name('create.keyword');
    Route::post('/save-keyword', [AddCustomerController::class, 'storeKeyword'])->name('keyword.store');
    Route::get('/admin/view-keywords/{bigid}', [AddCustomerController::class, 'viewKeyword'])->name('keywords.view');
    Route::get('/keyword/edit/{id}', [AddCustomerController::class, 'editKeyword'])->name('keyword.edit');
    Route::post('/keyword/update/{id}', [AddCustomerController::class, 'updateKeyword'])->name('keyword.update');
    Route::delete('/keyword/delete/{id}', [AddCustomerController::class, 'destroyKeyword'])->name('keyword.delete');
    Route::put('/customers/flag/update/{id}', [AddCustomerController::class, 'flagUpdate'])->name('customers.flag.update');
    Route::get('/virtualnumber', [SmsShortcodeController::class, 'index'])->name('smsshortcodes.index');
    Route::get('/virtualnumber/create/{userid}', [SmsShortcodeController::class, 'createShortCode'])->name('smsshortcodes.create');
    Route::post('virtualnumber/save', [SmsShortcodeController::class, 'store'])->name('smsshortcodes.store');
    Route::get('/virtualnumber/edit/{id}', [SmsShortcodeController::class, 'editShortCode'])->name('smsshortcodes.edit');
    Route::post('/virtualnumber/{id}', [SmsShortcodeController::class, 'updateShortCode'])->name('smsshortcodes.update');
    Route::delete('/virtualnumber/{id}', [SmsShortcodeController::class, 'destroyShortCode'])->name('smsshortcodes.destroy');
    Route::get('/mapping-keyword/{virtual_id}/{userid}', [SmsShortcodeController::class, 'mappingKeyword'])->name('mapping.keyword');
    Route::post('/mapping-keyword/update/{virtual_id}/{userid}', [SmsShortcodeController::class, 'mappingKeywordUpdate'])->name('mapping.keyword.update');
    Route::get('/admin/clear-cookies', [CookieController::class, 'clearCookiesAndSession']);
    Route::get('customer/emails', [ClientEmailController::class, 'index'])->name('admin.client.emails');
    Route::get('customer/emails/data', [ClientEmailController::class, 'getEmailsData'])->name('admin.client.emails.data');
    Route::get('/email/send', [ClientEmailController::class, 'emailform'])->name('email.form');
    Route::post('/email/upload-image', [ClientEmailController::class, 'uploadImage'])->name('admin.email.upload.image');
    Route::post('/send-bulk-email', [ClientEmailController::class, 'sendBulkEmail'])->name('send.bulk.email');
    Route::post('/schedule-bulk-email', [ClientEmailController::class, 'scheduleBulkEmail'])->name('schedule.bulk.email');
    Route::get('/admin/download-postpay-report', [PostPayReportController::class, 'export'])->name('admin.export.postpay');
    Route::get('/admin/download-daily-sms-report', [DailySmsReportController::class, 'dailySmsExport'])->name('admin.export.daily_sms');
    Route::get('/admin/download-money-transferred-report', [MoneyTransferredReportController::class, 'export'])->name('admin.export.money_transferred');
    Route::get('/clientnote/create/{userid}', [UserNoteController::class, 'createClientNote'])->name('client.note.create');
    Route::post('clientnote/save', [UserNoteController::class, 'store'])->name('client.note.store');
    Route::delete('/clientnote/{id}', [UserNoteController::class, 'destroyClientNote'])->name('client.note.destroy');
    Route::post('/admin/send-login/{id}', [AdminEmailController::class, 'sendLogin'])->name('admin.send.login');
    Route::get('resetpassword', [AdminEmailController::class, 'resetpassword'])->name('resetpassword');
    Route::get('/user/kill', [AdminEmailController::class, 'killUser'])->name('user.kill');
    Route::get('/user/kill-close', [AdminEmailController::class, 'killClose'])->name('user.killclose');
    Route::post('/user/track-wallet', [AdminEmailController::class, 'trackWallet'])->name('user.trackwallet');
    Route::get('/user/accountmanager/toggle', [AdminEmailController::class, 'toggleAccountManager'])->name('admin.acntmgr.toggle');
    ### Admin To Dashboard route ###
    Route::get('/user/dashboard/{username}', [AutoLoginController::class, 'generateTokenAndRedirect'])->name('user.dashboard.link');
    ### Admin To Campaign route ###
    Route::get('/user/campaign/{username}', [AutoLoginController::class, 'CampaignTokenAndRedirect'])->name('campain.link.redirect');
    // Route::get('/autologin', [AutoLoginController::class, 'loginWithToken'])->name('autologin');
    Route::post('/set-report-session', [MonthlyReportController::class, 'setSessionMonth'])->name('set.report.session.month');
    Route::get('monthly-report', [MonthlyReportController::class, 'index'])->name('admin.monthly-report');
    Route::post('/set-report-session-day', [MonthlyReportController::class, 'setSessionDay'])->name('set.report.session.day');
    Route::get('daily-report', [MonthlyReportController::class, 'dailyReport'])->name('admin.daily-report');
    Route::put('/admin/wallet/{id}', [WalletUserController::class, 'updateWallet'])->name('admin.wallet.update');
    Route::put('/admin/wallet/loan/{id}', [WalletUserController::class, 'updateWalletLoan'])->name('admin.wallet.loan');
    Route::put('/admin/wallet/plat/{id}', [WalletUserController::class, 'updateWalletPlat'])->name('admin.wallet.plat');

    // Platinum Keyword Wallet Update Route
    Route::put('/admin/user/{id}/plat-wallet', [AdminUserController::class, 'updatePlatKeywordWallet'])->name('admin.user.update-plat-wallet');
    Route::put('/admin/user/{id}/pricing', [AdminUserController::class, 'updateCustomerPricing'])->name('admin.user.update-pricing');
    Route::put('/admin/user/{id}/routing', [AdminUserController::class, 'updateRoutingConfig'])->name('admin.user.update-routing');
    Route::put('/admin/user/{id}/sub-account', [AdminUserController::class, 'updateSubAccountConfig'])->name('admin.user.update-sub-account');
    Route::post('/admin/user/{id}/quick-send', [AdminUserController::class, 'quickSend'])->name('admin.user.quick-send');
    Route::post('/admin/user/{id}/keyword-whois', [AdminUserController::class, 'keywordWhois'])->name('admin.user.keyword-whois');
    Route::post('/admin/user/{id}/keyword-register', [AdminUserController::class, 'keywordRegister'])->name('admin.user.keyword-register');
    Route::post('/admin/user/{id}/keyword-renew', [AdminUserController::class, 'keywordRenew'])->name('admin.user.keyword-renew');
    Route::post('/invoice/login/{id}', [OutstandingInvoiceController::class, 'loginAndRedirect'])->name('admin.invoice.login');
    Route::get('/outstanding-invoices', [OutstandingInvoiceController::class, 'outstandingInvoices'])->name('admin.outstanding.invoices');
    Route::get('/outstanding-invoice-view', [OutstandingInvoiceController::class, 'outstandingInvoiceView'])->name('outstanding.invoice.view');
    Route::post('/virtual/login/{id}', [OutstandingInvoiceController::class, 'VirtualloginAndRedirect'])->name('admin.virtual.login');
    Route::post('/keyword/login/{id}', [OutstandingInvoiceController::class, 'keywordLoginAndRedirect'])->name('admin.keyword.login');
    Route::get('/keyword-register', [OutstandingInvoiceController::class, 'keywordInvoices'])->name('admin.keyword.invoices');
    Route::post('/keyword-register/store', [OutstandingInvoiceController::class, 'storeKeywordRegistration'])->name('admin.keyword.register.store');
    Route::post('/upgrade/login/{id}', [OutstandingInvoiceController::class, 'UpgradeloginAndRedirect'])->name('admin.upgrade.login');
    Route::get('/upgrade-invoices', [OutstandingInvoiceController::class, 'upgradeInvoices'])->name('admin.upgrade.invoices');
    Route::post('/upgrade-buy', [OutstandingInvoiceController::class, 'outstandingbuyupgradeInvoice'])->name('admin.upgrade.buy');
    Route::post('/nfc/login/{id}', [OutstandingInvoiceController::class, 'NfcloginAndRedirect'])->name('admin.nfc.login');
    Route::get('/nfc-invoices', [OutstandingInvoiceController::class, 'nfcInvoices'])->name('admin.nfc.invoices');
    Route::post('/nfc-buy', [OutstandingInvoiceController::class, 'outstandingbuynfcInvoice'])->name('admin.nfc.buy');
    Route::get('/buyothers', [OutstandingInvoiceController::class, 'vitualoutstandingInvoices'])->name('admin.virtual.invoices');
    Route::post('/buyother-service-invoice', [OutstandingInvoiceController::class, 'outstandingbuyserviceInvoice'])->name('buyother.service.invoice');
    Route::get('/buyservice-invoice-view', [OutstandingInvoiceController::class, 'outstandingSeviceInvoiceView'])->name('outstanding.service.invoice.view');
    Route::post('/outstanding-buysms-invoice', [OutstandingInvoiceController::class, 'outstandingbuysmsInvoice'])->name('outstanding.buysms.invoice');
    Route::post('/admin/invoice/{invoice}/cancel', [OutstandingInvoiceController::class, 'cancelInvoice'])->name('admin.invoice.cancel');
    Route::post('/admin/invoice/{invoice}/paypal', [OutstandingInvoiceController::class, 'processPayPalPayment'])->name('admin.invoice.paypal');
    Route::post('/admin/invoice/{invoice}/bacs', [OutstandingInvoiceController::class, 'processBACSPayment'])->name('admin.invoice.bacs');
    // Route::get('/admin/invoice/{invoice}/download', [OutstandingInvoiceController::class, 'downloadInvoice'])->name('admin.invoice.download');
    Route::put('/admin/user/{id}/maxcard', [AdminUserController::class, 'updateMaxCardPurchase'])->name('admin.update.maxcard');
    // WhatsApp Toggle Route
    Route::post('/admin/user/{id}/toggle-whatsapp', [AdminUserController::class, 'toggleWhatsApp'])->name('admin.user.toggle.whatsapp');
    // User Rate Management Routes
    Route::post('/admin/user/{id}/rate', [AdminUserController::class, 'storeUserRate'])->name('admin.user.rate.store');
    Route::put('/admin/user/{userId}/rate/{rateId}', [AdminUserController::class, 'updateUserRate'])->name('admin.user.rate.update');
    Route::patch('/admin/user/{userId}/rate/{rateId}/toggle', [AdminUserController::class, 'toggleUserRate'])->name('admin.user.rate.toggle');
    Route::delete('/admin/user/{userId}/rate/{rateId}', [AdminUserController::class, 'destroyUserRate'])->name('admin.user.rate.destroy');
    Route::put('/admin/user/{id}/default-rate', [AdminUserController::class, 'updateDefaultRate'])->name('admin.user.rate.default');

    // User Margin Management Routes
    Route::put('/admin/user/{user}/margin', [App\Http\Controllers\Admin\UserMarginController::class, 'update'])->name('admin.user.margin.update');
    Route::get('/admin/user/{user}/margin', [App\Http\Controllers\Admin\UserMarginController::class, 'show'])->name('admin.user.margin.show');
    Route::patch('/admin/user/{user}/margin/toggle', [App\Http\Controllers\Admin\UserMarginController::class, 'toggle'])->name('admin.user.margin.toggle');
    Route::delete('/admin/user/{user}/margin', [App\Http\Controllers\Admin\UserMarginController::class, 'destroy'])->name('admin.user.margin.destroy');

    // Country Pricing Management Routes
    Route::put('/admin/country/{country}/pricing', [App\Http\Controllers\Admin\CountryPricingController::class, 'update'])->name('admin.country.pricing.update');
    Route::post('/admin/country/pricing/toggle-api', [App\Http\Controllers\Admin\CountryPricingController::class, 'toggleApiUpdate'])->name('admin.country.pricing.toggle-api');
    Route::post('/admin/country/pricing/bulk-toggle', [App\Http\Controllers\Admin\CountryPricingController::class, 'bulkToggleApiUpdate'])->name('admin.country.pricing.bulk-toggle');
    Route::post('/admin/country/{country}/refresh-price', [App\Http\Controllers\Admin\CountryPricingController::class, 'refreshPrice'])->name('admin.country.pricing.refresh');
    Route::post('/admin/country/pricing/update-exchange-rate', [App\Http\Controllers\Admin\CountryPricingController::class, 'updateExchangeRate'])->name('admin.country.pricing.update-exchange-rate');
    Route::get('/admin/country/pricing/settings', [App\Http\Controllers\Admin\CountryPricingController::class, 'getSettings'])->name('admin.country.pricing.settings');

    // Sinch pricing routes
    Route::post('/admin/country/pricing/copy-nexmo-to-sinch', [App\Http\Controllers\Admin\CountryPricingController::class, 'copyNexmoToSinch'])->name('admin.country.pricing.copy-nexmo-to-sinch');
    Route::post('/admin/country/pricing/copy-single-nexmo-to-sinch', [App\Http\Controllers\Admin\CountryPricingController::class, 'copySingleNexmoToSinch'])->name('admin.country.pricing.copy-single-nexmo-to-sinch');
    Route::put('/admin/country/{country}/sinch-pricing', [App\Http\Controllers\Admin\CountryPricingController::class, 'updateSinchPrice'])->name('admin.country.sinch-pricing.update');

    // Debug Nexmo Pricing API endpoint
    Route::get('/admin/debug/nexmo-pricing', [App\Http\Controllers\Admin\CountryPricingController::class, 'debugNexmoPricing'])->name('admin.debug.nexmo-pricing');

    Route::get('/admin/disable-toggle', [ProfileLockController::class, 'disableAccountManager'])->name('admin.disable.toggle');
    Route::get('/admin/account-lock', [ProfileLockController::class, 'toggleAccountLock'])->name('admin.account.lock');
    Route::get('/admin/profile-lock', [ProfileLockController::class, 'toggleProfileLock'])->name('admin.profile.lock');
    Route::get('/admin/api-type', [ProfileLockController::class, 'toggleApiType'])->name('admin.api.type');
    Route::get('/admin/force-logout', [ProfileLockController::class, 'forceLogout'])->name('admin.force.logout');
    Route::get('/admin/force-logout/current/{userbigid}', [ProfileLockController::class, 'forceLogoutCurrent'])->name('admin.current.force.logout');
    Route::post('/admin/force-logout-block', [ProfileLockController::class, 'forceLogoutAndBlock'])->name('admin.force.logout.block');
    Route::patch('/admin/blocked-users/unblock/{id}', [ProfileLockController::class, 'unblockUser'])->name('admin.blocked-users.unblock');
    Route::get('/admin/profile-update', [ProfileLockController::class, 'profileUpdate'])->name('admin.profile.update');
    Route::get('/send-profile-update-mail/{id}', [ProfileLockController::class, 'sendProfileUpdate'])->name('send.profile.update.email');
    Route::post('/admin/ip', [IpAddressResstrictionController::class, 'ipStore'])->name('admin.ip.store');
    Route::post('/ip/{id}/delete', [IpAddressResstrictionController::class, 'ipDestroy'])->name('admin.ip.delete');
    Route::get('/admin/ip-address-restriction', [IpAddressResstrictionController::class, 'ipAddressRestriction'])->name('admin.ip.address.restriction');
    Route::post('/admin/keyword-details', [InstanceController::class, 'fetchKeywordDetails'])->name('admin.keyword.details.ajax');

    Route::get('/get-daily-counts', [LoginController::class, 'getDailyCounts']);

    // Modern Dashboard API Routes
    Route::get('/admin/api/notifications', function () {
        return response()->json([
            'unread_count' => 0,
            'notifications' => []
        ]);
    })->name('admin.api.notifications');

    Route::get('/admin/api/system-status', function () {
        return response()->json([
            'status' => 'online',
            'message' => 'All systems operational'
        ]);
    })->name('admin.api.system-status');

    // Enhanced User Management Routes
    Route::get('admin/user/{id}/dashboard', [AdminUserController::class, 'getUserDashboard'])->name('admin.user.dashboard');
    Route::post('admin/user/{id}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('admin.user.toggle-status');
    Route::get('admin/users/export', [AdminUserController::class, 'export'])->name('admin.users.export');

    // Client Notes Routes
    Route::post('/admin/client-note/store', [AdminUserController::class, 'storeClientNote'])->name('client.note.store');
    Route::delete('/admin/client-note/{id}', [AdminUserController::class, 'destroyClientNote'])->name('client.note.destroy');

    // Virtual Number Management API Routes
    Route::get('/admin/user/{userId}/virtual-numbers', [\App\Http\Controllers\Admin\VirtualNumberController::class, 'getVirtualNumbers'])->name('admin.virtual.numbers.get');
    Route::post('/admin/virtual-numbers/add-12months-today', [\App\Http\Controllers\Admin\VirtualNumberController::class, 'add12MonthsFromToday'])->name('admin.virtual.numbers.add12today');
    Route::post('/admin/virtual-numbers/add-12months-expiry', [\App\Http\Controllers\Admin\VirtualNumberController::class, 'add12MonthsFromExpiry'])->name('admin.virtual.numbers.add12expiry');
    Route::post('/admin/virtual-numbers/force-expiry', [\App\Http\Controllers\Admin\VirtualNumberController::class, 'forceExpiry'])->name('admin.virtual.numbers.force.expiry');
    Route::post('/admin/virtual-numbers/force-unexpiry', [\App\Http\Controllers\Admin\VirtualNumberController::class, 'forceUnexpiry'])->name('admin.virtual.numbers.force.unexpiry');
    Route::post('/admin/virtual-numbers/toggle-suspension', [\App\Http\Controllers\Admin\VirtualNumberController::class, 'toggleSuspension'])->name('admin.virtual.numbers.toggle.suspension');
    Route::post('/admin/virtual-numbers/update-expiry', [\App\Http\Controllers\Admin\VirtualNumberController::class, 'updateExpiryDate'])->name('admin.virtual.numbers.update.expiry');
    Route::post('/admin/virtual-numbers/remove', [\App\Http\Controllers\Admin\VirtualNumberController::class, 'removeNumber'])->name('admin.virtual.numbers.remove');
    Route::post('/admin/virtual-numbers/add-dedicated', [\App\Http\Controllers\Admin\VirtualNumberController::class, 'addVirtualNumbers'])->name('admin.virtual.numbers.add.dedicated');

    // Virtual Numbers List and Sync Routes
    Route::get('/admin/virtual-numbers', [VirtualNumberController::class, 'index'])->name('admin.virtual-numbers.index');
    Route::post('/admin/virtual-numbers/sync', [VirtualNumberController::class, 'syncFromNexmo'])->name('admin.virtual-numbers.sync');
    Route::get('/admin/virtual-numbers/export', [VirtualNumberController::class, 'export'])->name('admin.virtual-numbers.export');
    Route::get('/admin/virtual-numbers/create', [VirtualNumberController::class, 'create'])->name('admin.virtual-numbers.create');
    Route::post('/admin/virtual-numbers/search', [VirtualNumberController::class, 'searchAvailableNumbers'])->name('admin.virtual-numbers.search');
    Route::post('/admin/virtual-numbers/store', [VirtualNumberController::class, 'store'])->name('admin.virtual-numbers.store');
    Route::post('/admin/virtual-numbers/store-manual', [VirtualNumberController::class, 'storeManual'])->name('admin.virtual-numbers.store-manual');
    Route::delete('/admin/virtual-numbers/{id}', [VirtualNumberController::class, 'destroy'])->name('admin.virtual-numbers.destroy');
    Route::put('/admin/virtual-numbers/{id}/webhook', [VirtualNumberController::class, 'updateWebhook'])->name('admin.virtual-numbers.webhook');

    // Assign/Reassign Virtual Numbers
    Route::get('/admin/virtual-numbers/get-users', [VirtualNumberController::class, 'getAllUsers'])->name('admin.virtual-numbers.get-users');
    Route::post('/admin/virtual-numbers/assign', [VirtualNumberController::class, 'assignNumber'])->name('admin.virtual-numbers.assign');
    Route::post('/admin/virtual-numbers/reassign', [VirtualNumberController::class, 'reassignNumber'])->name('admin.virtual-numbers.reassign');

    // System Logs Routes
    Route::get('/admin/logs', [LogController::class, 'index'])->name('admin.logs.index');
    Route::get('/admin/logs/download', [LogController::class, 'download'])->name('admin.logs.download');
    Route::get('/admin/logs/download-all', [LogController::class, 'downloadAllZip'])->name('admin.logs.download-all');
    Route::delete('/admin/logs/delete', [LogController::class, 'delete'])->name('admin.logs.delete');
    Route::delete('/admin/logs/clear-all', [LogController::class, 'clearAll'])->name('admin.logs.clear-all');
    // Date -> folder -> file browser (AJAX) + per-file download
    Route::get('/admin/logs/dates', [LogController::class, 'dates'])->name('admin.logs.dates');
    Route::get('/admin/logs/folders', [LogController::class, 'folders'])->name('admin.logs.folders');
    Route::get('/admin/logs/files', [LogController::class, 'files'])->name('admin.logs.files');
    Route::get('/admin/logs/download-file', [LogController::class, 'downloadFile'])->name('admin.logs.download-file');

    // Settings Routes
    Route::get('/admin/settings', [App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('admin.settings');
    Route::get('/admin/settings/realtime-sms-stats', [App\Http\Controllers\Admin\SettingsController::class, 'getRealtimeSmsStats'])->name('admin.settings.realtime-sms-stats');
    Route::get('/admin/settings/queue-status', [App\Http\Controllers\Admin\SettingsController::class, 'queueStatus'])->name('admin.settings.queue-status');
    Route::get('/admin/settings/queue-messages', [App\Http\Controllers\Admin\SettingsController::class, 'queueMessages'])->name('admin.settings.queue-messages');
    Route::get('/admin/settings/queue-view', [App\Http\Controllers\Admin\SettingsController::class, 'queueView'])->name('admin.settings.queue-view');
    Route::post('/admin/settings/query', [App\Http\Controllers\Admin\SettingsController::class, 'runQuery'])->name('admin.settings.query.run');
    Route::post('/admin/settings/dlr/import', [App\Http\Controllers\Admin\SettingsController::class, 'importDlrCsv'])->name('admin.settings.dlr.import');

    // Global Pricing — own menu (not a Settings tab). Gated by the same permission the
    // sidebar checks (can_manage_global_pricing, via hasPermission on the DB) so menu
    // visibility and page access always agree — no stale-session 403s.
    Route::get('/admin/global-pricing', [App\Http\Controllers\Admin\GlobalPricingController::class, 'index'])
        ->middleware('admin.permission:can_manage_global_pricing')
        ->name('admin.global-pricing.index');
    Route::post('/admin/global-pricing', [App\Http\Controllers\Admin\GlobalPricingController::class, 'save'])
        ->middleware('admin.permission:can_manage_global_pricing')
        ->name('admin.global-pricing.save');

    // SMSG Daemon Report (Livebeat) — daemon-name breakdown of sent SMS
    Route::get('/admin/reports/daemon', [App\Http\Controllers\Admin\DaemonReportController::class, 'index'])
        ->middleware('admin.permission:can_view_daemon_report')
        ->name('admin.reports.daemon');

    // Reports Routes
    Route::get('/admin/reports', [App\Http\Controllers\Admin\ReportsController::class, 'index'])->name('admin.reports.index');
    Route::get('/admin/reports/postpay/data', [App\Http\Controllers\Admin\ReportsController::class, 'getPostPayData'])->name('admin.reports.postpay.data');
    Route::get('/admin/reports/daily-sms/data', [App\Http\Controllers\Admin\ReportsController::class, 'getDailySmsData'])->name('admin.reports.daily-sms.data');
    Route::get('/admin/reports/money-transfer/data', [App\Http\Controllers\Admin\ReportsController::class, 'getMoneyTransferredData'])->name('admin.reports.money-transfer.data');
    Route::get('/admin/reports/monthly-sales/data', [App\Http\Controllers\Admin\ReportsController::class, 'getMonthlySalesData'])->name('admin.reports.monthly-sales.data');
    Route::get('/admin/reports/postpay/export', [App\Http\Controllers\Admin\ReportsController::class, 'exportPostPay'])->name('admin.reports.postpay.export');
    Route::get('/admin/reports/daily-sms/export', [App\Http\Controllers\Admin\ReportsController::class, 'exportDailySms'])->name('admin.reports.daily-sms.export');
    Route::get('/admin/reports/money-transfer/export', [App\Http\Controllers\Admin\ReportsController::class, 'exportMoneyTransferred'])->name('admin.reports.money-transfer.export');

    // Email Report Download Routes (token-based for email links)
    Route::get('/admin/reports/download-invoices', [App\Http\Controllers\Admin\ReportsController::class, 'downloadDailyInvoices'])->name('admin.reports.download-invoices');
    Route::get('/admin/reports/download-wallets', [App\Http\Controllers\Admin\ReportsController::class, 'downloadClientWallets'])->name('admin.reports.download-wallets');

    // Async (queued) report generation + history + download
    Route::post('/admin/reports/request', [App\Http\Controllers\Admin\ReportsController::class, 'requestReport'])->name('admin.reports.request');
    Route::get('/admin/reports/history', [App\Http\Controllers\Admin\ReportsController::class, 'reportHistory'])->name('admin.reports.history');
    Route::get('/admin/reports/download/{id}', [App\Http\Controllers\Admin\ReportsController::class, 'downloadReport'])->name('admin.reports.download');

    // User Activity Log Routes
    Route::get('/admin/activity-logs', [App\Http\Controllers\Admin\UserActivityController::class, 'index'])->name('admin.activity-logs.index');
    Route::get('/admin/activity-logs/data', [App\Http\Controllers\Admin\UserActivityController::class, 'getData'])->name('admin.activity-logs.data');
    Route::get('/admin/activity-logs/statistics', [App\Http\Controllers\Admin\UserActivityController::class, 'getStatistics'])->name('admin.activity-logs.statistics');
    Route::get('/admin/activity-logs/actions', [App\Http\Controllers\Admin\UserActivityController::class, 'getActions'])->name('admin.activity-logs.actions');
    Route::get('/admin/activity-logs/users', [App\Http\Controllers\Admin\UserActivityController::class, 'getUsers'])->name('admin.activity-logs.users');
    Route::get('/admin/activity-logs/{id}', [App\Http\Controllers\Admin\UserActivityController::class, 'show'])->name('admin.activity-logs.show');
    Route::get('/admin/activity-logs/export', [App\Http\Controllers\Admin\UserActivityController::class, 'export'])->name('admin.activity-logs.export');
    Route::post('/admin/activity-logs/clean', [App\Http\Controllers\Admin\UserActivityController::class, 'cleanOldLogs'])->name('admin.activity-logs.clean');

    // Monitor Routes
    Route::get('/admin/monitor/supervisor', [App\Http\Controllers\Admin\MonitorController::class, 'getSupervisorStatus'])->name('admin.monitor.supervisor');
    Route::get('/admin/monitor/cron', [App\Http\Controllers\Admin\MonitorController::class, 'getCronStatus'])->name('admin.monitor.cron');
    Route::post('/admin/monitor/control', [App\Http\Controllers\Admin\MonitorController::class, 'controlProcess'])->name('admin.monitor.control');
    Route::post('/admin/monitor/run-cron', [App\Http\Controllers\Admin\MonitorController::class, 'runCronJob'])->name('admin.monitor.run-cron');
    Route::get('/admin/monitor/process-log', [App\Http\Controllers\Admin\MonitorController::class, 'getProcessLog'])->name('admin.monitor.process-log');

    // Cron Management Routes
    Route::get('/admin/monitor/cron-list', [App\Http\Controllers\Admin\MonitorController::class, 'getCronList'])->name('admin.monitor.cron-list');
    Route::post('/admin/monitor/cron-toggle', [App\Http\Controllers\Admin\MonitorController::class, 'toggleCronById'])->name('admin.monitor.cron-toggle');
    Route::post('/admin/monitor/cron-toggle-all', [App\Http\Controllers\Admin\MonitorController::class, 'toggleAllCrons'])->name('admin.monitor.cron-toggle-all');

    // Cron Logs Date-wise Routes
    Route::get('/admin/monitor/cron-logs-summary', [App\Http\Controllers\Admin\MonitorController::class, 'getCronLogsSummary'])->name('admin.monitor.cron-logs-summary');
    Route::get('/admin/monitor/cron-logs', [App\Http\Controllers\Admin\MonitorController::class, 'getCronLogs'])->name('admin.monitor.cron-logs');
    Route::get('/admin/monitor/cron-logs/download', [App\Http\Controllers\Admin\MonitorController::class, 'downloadCronLogs'])->name('admin.monitor.cron-logs.download');
    Route::get('/admin/monitor/cron-log/{id}', [App\Http\Controllers\Admin\MonitorController::class, 'getCronLogDetail'])->name('admin.monitor.cron-log.detail');
    Route::get('/admin/monitor/cron-log/{id}/download-file', [App\Http\Controllers\Admin\MonitorController::class, 'downloadCronLogFile'])->name('admin.monitor.cron-log.download-file');

    // Cron Logs Routes
    // Route::get('/admin/cron-logs', [App\Http\Controllers\Admin\CronLogsController::class, 'index'])->name('admin.cron-logs.index');
    // Route::get('/admin/cron-logs/{id}/view', [App\Http\Controllers\Admin\CronLogsController::class, 'view'])->name('admin.cron-logs.view');
    // Route::get('/admin/cron-logs/{id}/download', [App\Http\Controllers\Admin\CronLogsController::class, 'download'])->name('admin.cron-logs.download');
    // Route::get('/admin/cron-logs/files', [App\Http\Controllers\Admin\CronLogsController::class, 'getLogFiles'])->name('admin.cron-logs.files');
    // Route::post('/admin/cron-logs/cleanup', [App\Http\Controllers\Admin\CronLogsController::class, 'cleanup'])->name('admin.cron-logs.cleanup');

    // Environment Variable Management Routes
    Route::post('/admin/settings/env', [App\Http\Controllers\Admin\SettingsController::class, 'storeEnv'])->name('admin.settings.env.store');
    Route::put('/admin/settings/env', [App\Http\Controllers\Admin\SettingsController::class, 'updateEnv'])->name('admin.settings.env.update');
    Route::delete('/admin/settings/env', [App\Http\Controllers\Admin\SettingsController::class, 'deleteEnv'])->name('admin.settings.env.delete');

    // Cache Management Routes
    Route::post('/admin/cache/clear-all', [App\Http\Controllers\Admin\SettingsController::class, 'clearAllCaches'])->name('admin.cache.clear-all');
    Route::post('/admin/cache/clear-app', [App\Http\Controllers\Admin\SettingsController::class, 'clearApplicationCache'])->name('admin.cache.clear-app');
    Route::post('/admin/cache/clear-config', [App\Http\Controllers\Admin\SettingsController::class, 'clearConfigCache'])->name('admin.cache.clear-config');
    Route::post('/admin/cache/clear-route', [App\Http\Controllers\Admin\SettingsController::class, 'clearRouteCache'])->name('admin.cache.clear-route');
    Route::post('/admin/cache/clear-view', [App\Http\Controllers\Admin\SettingsController::class, 'clearViewCache'])->name('admin.cache.clear-view');
    Route::post('/admin/cache/clear-event', [App\Http\Controllers\Admin\SettingsController::class, 'clearEventCache'])->name('admin.cache.clear-event');
    Route::post('/admin/cache/clear-compiled', [App\Http\Controllers\Admin\SettingsController::class, 'clearCompiledCache'])->name('admin.cache.clear-compiled');
    Route::post('/admin/cache/optimize', [App\Http\Controllers\Admin\SettingsController::class, 'optimizeApplication'])->name('admin.cache.optimize');
    Route::post('/admin/cache/cache-config', [App\Http\Controllers\Admin\SettingsController::class, 'cacheConfig'])->name('admin.cache.cache-config');
    Route::post('/admin/cache/cache-route', [App\Http\Controllers\Admin\SettingsController::class, 'cacheRoutes'])->name('admin.cache.cache-route');
    Route::post('/admin/cache/cache-view', [App\Http\Controllers\Admin\SettingsController::class, 'cacheViews'])->name('admin.cache.cache-view');

    // Customer Settings Routes
    Route::post('/admin/settings/customer/save', [App\Http\Controllers\Admin\SettingsController::class, 'saveCustomerSettings'])->name('admin.settings.customer.save');
    Route::post('/admin/settings/customer/maintenance/add', [App\Http\Controllers\Admin\SettingsController::class, 'addCustomerMaintenance'])->name('admin.settings.customer.maintenance.add');
    Route::post('/admin/settings/customer/maintenance/remove', [App\Http\Controllers\Admin\SettingsController::class, 'removeCustomerMaintenance'])->name('admin.settings.customer.maintenance.remove');
    Route::delete('/admin/settings/customer/maintenance/delete', [App\Http\Controllers\Admin\SettingsController::class, 'deleteCustomerMaintenance'])->name('admin.settings.customer.maintenance.delete');
    Route::get('/admin/settings/customers/search', [App\Http\Controllers\Admin\SettingsController::class, 'searchCustomers'])->name('admin.settings.customers.search');

    // Admin Users Management Routes
    Route::get('/admin/admin-users', [App\Http\Controllers\Admin\AdminUsersController::class, 'index'])->name('admin.admin-users.index');
    Route::get('/admin/admin-users/create', [App\Http\Controllers\Admin\AdminUsersController::class, 'create'])->name('admin.admin-users.create');
    Route::post('/admin/admin-users', [App\Http\Controllers\Admin\AdminUsersController::class, 'store'])->name('admin.admin-users.store');
    Route::get('/admin/admin-users/{id}/edit', [App\Http\Controllers\Admin\AdminUsersController::class, 'edit'])->name('admin.admin-users.edit');
    Route::put('/admin/admin-users/{id}', [App\Http\Controllers\Admin\AdminUsersController::class, 'update'])->name('admin.admin-users.update');
    Route::delete('/admin/admin-users/{id}', [App\Http\Controllers\Admin\AdminUsersController::class, 'destroy'])->name('admin.admin-users.destroy');
    Route::post('/admin/admin-users/{id}/toggle-status', [App\Http\Controllers\Admin\AdminUsersController::class, 'toggleStatus'])->name('admin.admin-users.toggle-status');
    Route::post('/admin/admin-users/{id}/resend-credentials', [App\Http\Controllers\Admin\AdminUsersController::class, 'resendCredentials'])->name('admin.admin-users.resend-credentials');

    // Self-service: the logged-in admin changes their own password
    Route::get('/admin/profile/change-password', [App\Http\Controllers\Admin\AdminProfileController::class, 'showChangePassword'])->name('admin.profile.change-password');
    Route::post('/admin/profile/change-password', [App\Http\Controllers\Admin\AdminProfileController::class, 'changePassword'])->name('admin.profile.change-password.update');

    // Admin Roles Management Routes
    Route::post('/admin/admin-roles', [App\Http\Controllers\Admin\AdminUsersController::class, 'storeRole'])->name('admin.admin-users.store-role');
    Route::put('/admin/admin-roles/{id}', [App\Http\Controllers\Admin\AdminUsersController::class, 'updateRole'])->name('admin.admin-users.update-role');
    Route::delete('/admin/admin-roles/{id}', [App\Http\Controllers\Admin\AdminUsersController::class, 'destroyRole'])->name('admin.admin-users.destroy-role');
    Route::get('/admin/admin-roles/{id}/permissions', [App\Http\Controllers\Admin\AdminUsersController::class, 'getRolePermissions'])->name('admin.admin-users.role-permissions');

    // Notifications Management Routes
    Route::get('/admin/notifications', [App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('admin.notifications.index');
    Route::get('/admin/notifications/create', [App\Http\Controllers\Admin\NotificationController::class, 'create'])->name('admin.notifications.create');
    Route::post('/admin/notifications', [App\Http\Controllers\Admin\NotificationController::class, 'store'])->name('admin.notifications.store');
    Route::get('/admin/notifications/{id}', [App\Http\Controllers\Admin\NotificationController::class, 'show'])->name('admin.notifications.show');
    Route::get('/admin/notifications/{id}/edit', [App\Http\Controllers\Admin\NotificationController::class, 'edit'])->name('admin.notifications.edit');
    Route::put('/admin/notifications/{id}', [App\Http\Controllers\Admin\NotificationController::class, 'update'])->name('admin.notifications.update');
    Route::delete('/admin/notifications/{id}', [App\Http\Controllers\Admin\NotificationController::class, 'destroy'])->name('admin.notifications.destroy');
    Route::post('/admin/notifications/{id}/send-now', [App\Http\Controllers\Admin\NotificationController::class, 'sendNow'])->name('admin.notifications.send-now');
    Route::get('/admin/notifications/{id}/statistics', [App\Http\Controllers\Admin\NotificationController::class, 'statistics'])->name('admin.notifications.statistics');

    // Server Settings Routes (Campaign File Migration)
    Route::get('/admin/settings/server', [App\Http\Controllers\Admin\ServerSettingsController::class, 'index'])->name('admin.settings.server');
    Route::post('/admin/settings/server/save', [App\Http\Controllers\Admin\ServerSettingsController::class, 'save'])->name('admin.settings.server.save');
    Route::post('/admin/settings/server/test', [App\Http\Controllers\Admin\ServerSettingsController::class, 'testConnection'])->name('admin.settings.server.test');
    Route::get('/admin/settings/server/status', [App\Http\Controllers\Admin\ServerSettingsController::class, 'getStatus'])->name('admin.settings.server.status');

    // Campaign File Migration Routes
    Route::get('/admin/migration/campaign-files', [App\Http\Controllers\Admin\CampaignFileMigrationController::class, 'index'])->name('admin.migration.campaign-files');
    Route::post('/admin/migration/campaign-files/start', [App\Http\Controllers\Admin\CampaignFileMigrationController::class, 'startMigration'])->name('admin.migration.campaign-files.start');
    Route::get('/admin/migration/campaign-files/batch/{batchId}', [App\Http\Controllers\Admin\CampaignFileMigrationController::class, 'getBatchHistory'])->name('admin.migration.campaign-files.batch');
    Route::get('/admin/migration/campaign-files/batch-status', [App\Http\Controllers\Admin\CampaignFileMigrationController::class, 'getBatchStatus'])->name('admin.migration.campaign-files.batch-status');
    Route::get('/admin/migration/campaign-files/search-users', [App\Http\Controllers\Admin\CampaignFileMigrationController::class, 'searchUsers'])->name('admin.migration.campaign-files.search-users');
    Route::get('/admin/migration/campaign-files/queue-status', [App\Http\Controllers\Admin\CampaignFileMigrationController::class, 'getQueueStatus'])->name('admin.migration.campaign-files.queue-status');

    // Cost Management Routes
    Route::prefix('admin/cost')->name('admin.cost.')->group(function () {
        // Main Cost Page with Tabs
        Route::get('/', [App\Http\Controllers\Admin\CostController::class, 'index'])->name('index');

        // Vonage Cost Management
        Route::get('/vonage', [App\Http\Controllers\Admin\VonageCostController::class, 'index'])->name('vonage.index');
        Route::put('/vonage/country/{country}', [App\Http\Controllers\Admin\VonageCostController::class, 'updateCountryCost'])->name('vonage.country.update');
        Route::post('/vonage/country/{country}/reset-mode', [App\Http\Controllers\Admin\VonageCostController::class, 'resetPriceMode'])->name('vonage.country.reset-mode');
        Route::post('/vonage/fetch-nexmo/{countryCode}', [App\Http\Controllers\Admin\VonageCostController::class, 'fetchNexmoPricing'])->name('vonage.fetch-nexmo');
        Route::post('/vonage/fetch-nexmo-all', [App\Http\Controllers\Admin\VonageCostController::class, 'fetchAllNexmoPricing'])->name('vonage.fetch-nexmo-all');
        Route::get('/vonage/template/country', [App\Http\Controllers\Admin\VonageCostController::class, 'downloadCountryTemplate'])->name('vonage.template.country');
        Route::get('/vonage/export/country', [App\Http\Controllers\Admin\VonageCostController::class, 'exportCountryCosts'])->name('vonage.export.country');
        Route::post('/vonage/import/country', [App\Http\Controllers\Admin\VonageCostController::class, 'importCountryCosts'])->name('vonage.import.country');

        // Sinch Cost Management
        Route::get('/sinch', [App\Http\Controllers\Admin\SinchCostController::class, 'index'])->name('sinch.index');
        Route::put('/sinch/country/{country}', [App\Http\Controllers\Admin\SinchCostController::class, 'updateCost'])->name('sinch.country.update');
        Route::get('/sinch/template', [App\Http\Controllers\Admin\SinchCostController::class, 'downloadTemplate'])->name('sinch.template');
        Route::get('/sinch/export', [App\Http\Controllers\Admin\SinchCostController::class, 'exportCosts'])->name('sinch.export');
        Route::post('/sinch/import', [App\Http\Controllers\Admin\SinchCostController::class, 'importCosts'])->name('sinch.import');
    });
});

// Campaign Routes
Route::middleware([CampaignMiddleware::class])->prefix('campaign')->group(function () {

    // Dashboard
    Route::get('/dashboard', [CampaignDashboardController::class, 'index'])
        ->name('campaign.dashboard');

    // Main Dashboard redirect
    Route::get('/main-dashboard', [CampaignDashboardController::class, 'mainDashboard'])
        ->name('campaign.main.dashboard');

    // Submit new SMS campaign (quick)
    Route::get('/quick', [CampaignDashboardController::class, 'quickCampaign'])
        ->name('campaign.quick');
    Route::post('/quick', [CampaignDashboardController::class, 'submitQuickCampaign'])
        ->name('campaign.quick.submit');

    // Submit new SMS campaign (file upload)
    Route::get('/upload', [CampaignDashboardController::class, 'uploadCampaign'])
        ->name('campaign.upload');
    Route::post('/upload', [CampaignDashboardController::class, 'submitUploadCampaign'])
        ->name('campaign.upload.submit');

    // View previous SMS campaigns
    Route::get('/previous', [CampaignDashboardController::class, 'previousCampaigns'])
        ->name('campaign.previous.list');

    // Campaign actions
    Route::post('/pause', [CampaignDashboardController::class, 'pauseCampaign'])
        ->name('campaign.pause');
    Route::post('/resume', [CampaignDashboardController::class, 'resumeCampaign'])
        ->name('campaign.resume');
    Route::post('/delete', [CampaignDashboardController::class, 'deleteCampaign'])
        ->name('campaign.delete');

    // Download reports
    Route::get('/download/report', [CampaignDashboardController::class, 'downloadCampaignReport'])
        ->name('campaign.download.report');
    Route::get('/download/shorturl', [CampaignDashboardController::class, 'downloadShortUrlReport'])
        ->name('campaign.download.shorturl');
    Route::get('/download/sample-csv', [CampaignDashboardController::class, 'downloadSampleCsv'])
        ->name('campaign.download.sample.csv');
    Route::get('/download/sample-excel', [CampaignDashboardController::class, 'downloadSampleExcel'])
        ->name('campaign.download.sample.excel');
    Route::get('/download/instructions', [CampaignDashboardController::class, 'downloadInstructions'])
        ->name('campaign.download.instructions');

    // View STOP Blacklist
    Route::get('/blacklist', [CampaignDashboardController::class, 'viewBlacklist'])
        ->name('campaign.blacklist');
    Route::get('/blacklist/download', [CampaignDashboardController::class, 'downloadBlacklist'])
        ->name('campaign.blacklist.download');

    // View Accounts
    Route::get('/accounts', [CampaignDashboardController::class, 'viewAccounts'])
        ->name('campaign.accounts');

    // Add new sub-account
    Route::get('/accounts/add', [CampaignDashboardController::class, 'addAccount'])
        ->name('campaign.accounts.add');
    Route::post('/accounts/add', [CampaignDashboardController::class, 'storeAccount'])
        ->name('campaign.accounts.store');

    // Transfer wallet funds
    Route::post('/transfer-wallet', [CampaignDashboardController::class, 'transferWallet'])
        ->name('campaign.transfer.wallet');

    ### Campaign To Dashboard route ###
    Route::get('/users/dashboard/{username}', [CampaignDashboardController::class, 'generateTokenAndRedirectCampaign'])->name('campaign.dashboard.link');

    // Campaign Tour Guide Routes
    Route::post('/tour/complete', [App\Http\Controllers\TourController::class, 'completeCampaignTour'])->name('campaign.tour.complete');
    Route::post('/tour/reset', [App\Http\Controllers\TourController::class, 'resetCampaignTour'])->name('campaign.tour.reset');
});

 Route::post('sinch/send-sms', [SinchSmsController::class, 'send']);

// Contract Management Routes
require __DIR__.'/contracts_routes.php';
