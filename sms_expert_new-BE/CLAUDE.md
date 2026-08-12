# CLAUDE.md - SMS Expert Backend

## Project Overview

SMS Expert is a Laravel 11 SMS management and delivery platform providing SMS sending, tracking, wallet management, and admin dashboard functionality.

## Tech Stack

- **Framework**: Laravel 11.9 (PHP 8.2+)
- **Database**: MySQL (database: `sms_expert`)
- **Authentication**: Laravel Sanctum (token-based API)
- **Queue**: RabbitMQ with AMQP
- **SMS Providers**: Vonage/Nexmo (SMPP), Sinch
- **Push Notifications**: Firebase Cloud Messaging (FCM)
- **Frontend Build**: Vite + TailwindCSS + Alpine.js

## Common Commands

```bash
# Development server
php artisan serve

# Run migrations
php artisan migrate

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Queue workers
php artisan rabbitmq:consume-emails
php artisan queue:work

# Run tests
php artisan test
vendor/bin/phpunit

# Generate IDE helpers (if installed)
php artisan ide-helper:generate
```

## Project Structure

```
app/
├── Http/Controllers/
│   ├── SMSController.php          # Core SMS logic (main controller)
│   ├── Admin/                     # Admin panel controllers
│   ├── Api/Mobile/                # Mobile app API controllers
│   └── Campaign/                  # Campaign management
├── Services/
│   ├── SMSService.php             # SMS business logic
│   ├── SMPP/                      # SMPP protocol handling
│   ├── Queue/                     # Queue services
│   └── Alerts/                    # Alert system
├── Models/                        # Eloquent models (40+)
├── Jobs/                          # Async jobs (email, SMS, notifications)
└── Mail/                          # Email classes

routes/
├── api.php                        # API routes (main API definitions)
├── web.php                        # Web routes
└── console.php                    # Artisan commands

config/                            # Laravel configuration files
database/migrations/               # Database schema
```

## Key Files

- `app/Http/Controllers/SMSController.php` - Core SMS sending/receiving logic
- `app/Services/SMSService.php` - SMS business logic service
- `app/Services/SMPP/SmsQueueService.php` - SMPP queue management
- `app/Services/Queue/RabbitMQService.php` - RabbitMQ integration
- `routes/api.php` - All API route definitions

## API Structure

### Authentication
All mobile API endpoints require Bearer token:
```
Authorization: Bearer {token}
```

### Main Route Groups
- `POST /api/login` - Authentication
- `POST /api/register` - User registration
- `/api/mobile/*` - Mobile app endpoints (Sanctum protected)
- `/api/smsg/*` - Legacy SMS API (backward compatible)
- `/api/webhook/*` - Webhook handlers (inbound SMS, DLR)
- `/api/smpp/*` - SMPP queue operations
- `/api/admin/*` - Admin endpoints

## Database

### Key Tables
- `users` - Customer accounts with wallet balance
- `admin_users` - Staff accounts
- `smsg_log` - SMS transaction logs
- `invoices` - Billing records
- `virtual_numbers` - Phone number pool
- `user_notifications` - Notification queue
- `contracts` - User agreements

### Key Models
- `User` - Customer with wallet_balance field
- `SMSgLog` - SMS delivery tracking
- `Invoice` - Billing
- `AdminUser` - Staff with role-based access
- `VirtualNumber` - Phone number management

## Code Patterns

### SMS Sending Flow
1. Validate wallet balance via `WalletValidationService`
2. Calculate SMS parts and cost
3. Determine operator based on sender ID:
   - Query `smsshortcodes` table by sender number
   - Join with `itagg_instance` by user reference
   - Check `whichoperator` field (e.g., `mBlox/Vodafone`, `Nexmo/all`)
4. Route to SMPP provider via RabbitMQ queue:
   - `nexmo*` → Vonage SMPP via `SmsQueueService`
   - `mblox*`/`sinch*` → Sinch SMPP via `SinchQueueService`
5. After sending: Calculate pricing (cost + user margin) and deduct from wallet
6. Track delivery via `SMSgLog` and handle DLR callbacks

### SMPP Queue Processing
- **Nexmo Queue:** `php artisan queue:work` or dedicated SMPP consumer
- **Sinch Queue:** `php artisan sinch:process-queue --daemon`
- Both support scheduled SMS via RabbitMQ delayed messages

### Wallet Deduction
- Price from SMPP response (Nexmo TLV) or country table
- User margin from `user_margin` table (percentage-based)
- Final price: `costprice + (costprice × margin%)`
- Deducted from `users.smsg_server1_sent` field

### Authentication
- Web: Session-based (CustomerMiddleware, AdminMiddleware)
- API: Sanctum tokens (`auth:sanctum` middleware)

### Queue Jobs
- Use RabbitMQ for production
- Jobs in `app/Jobs/`: `SendEmailJob`, `SendNotificationJob`, `SendPushNotificationJob`

## Testing

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/ExampleTest.php

# Load testing (Python script)
python load_test_sms.py
```

## Environment Variables

Key `.env` configurations:
- `DB_*` - MySQL connection
- `VONAGE_*` / `NEXMO_*` - SMS provider credentials
- `RABBITMQ_*` - Queue server
- `FCM_*` - Firebase push notifications
- `MAIL_*` - SMTP settings

## Documentation

- `docs/postman/` - Postman collection and environments
- `docs/API_ERROR_MONITORING.md` - Error monitoring guide
- `supervisor/` - Background job configurations

---

## Recent Fixes (May 2026)

### 1. UK Costprice Fix
**File:** `app/Services/CostPriceService.php`

**Problem:** UK SMS costprice was showing 0.014800 instead of 0.016500 (OLD SYSTEM value)

**Solution:** Simplified UK costprice logic to always return `UK_MBLOX_DEFAULT` (0.0165) since the database had incorrect values.

```php
public function getUkCostPrice($routenum)
{
    return self::UK_MBLOX_DEFAULT; // £0.0165 per SMS
}
```

### 2. International Costprice Fix
**File:** `app/Services/CostPriceService.php`

**Problem:** India costprice was showing 0.007139 (hardcoded) instead of 0.007100 (from smsg_route table)

**Solution:** Modified `getInternationalCostPrice()` to check `smsg_route` table first before falling back to hardcoded Nexmo rates.

### 3. Timezone/Date Display Fix
**Files:**
- `app/Helpers/helpers.php` - `isSummerTime()` function
- `app/Http/Controllers/SMSController.php` - `showSmsDetails()` method

**Problem:** "Sent at Time" was showing 1 hour earlier than other timestamps

**Root Causes:**
1. `isSummerTime()` function only had BST dates from 2004-2010
2. SQL query used `INTERVAL 1 MINUTE` instead of `INTERVAL 1 HOUR`

**Solution:**
1. Updated `isSummerTime()` to use Carbon's `isDST()` method
2. Changed SQL to use `INTERVAL 1 HOUR` for proper BST conversion

### 4. DLR (Delivery Receipt) Message ID Fix
**Files:**
- `app/Services/SMPP/SMPPService.php`
- `app/Services/DeliveryStatusService.php`
- `app/Console/Commands/ProcessDlrQueue.php`
- `app/Http/Controllers/WebhookController.php`

**Problem:** DLR not matched because Vonage sends message_id as hex in submit_sm_resp but decimal in DLR

**Solution:** Added hex-to-decimal conversion using GMP extension:
```php
if (ctype_xdigit($messageId) && strlen($messageId) > 15) {
    $messageIdDecimal = gmp_strval(gmp_init($messageId, 16), 10);
}
```

### 5. Admin Dashboard SMS Cost Display Fix
**File:** `app/Http/Controllers/Admin/AdminController.php`

**Problem:** Dashboard showing £0.00 for SMS costs

**Solution:** Changed query filter from `deliverystatus2 IN ('Delivered')` to `sentstatus = 'ok'`

### 6. Nexmo DLR Status Capitalization Fix
**File:** `app/Services/Queue/NexmoDeliveryQueueService.php`

**Problem:** Nexmo DLR was updating `smsg_log.deliverystatus2='delivered'` (lowercase) instead of `'Delivered'` (capital D). This caused inconsistency as Sinch correctly updates with `'Delivered'`.

**Root Cause:** The `$statusMap` array in `NexmoDeliveryQueueService.php` was mapping lowercase to lowercase:
```php
// OLD (incorrect)
protected $statusMap = [
    'delivered' => 'delivered',  // lowercase - WRONG
    ...
];
```

**Solution:** Fixed the status mapping to use OLD SYSTEM format with capital letters:
```php
// NEW (correct)
protected $statusMap = [
    'delivered' => 'Delivered',           // OLD SYSTEM format with capital D
    'expired' => 'Non Delivered',         // OLD SYSTEM format
    'deleted' => 'Non Delivered',         // OLD SYSTEM format
    'undelivered' => 'Non Delivered',     // OLD SYSTEM format
    'accepted' => 'accepted',             // Intermediate status
    'unknown' => 'Lost Notification',     // OLD SYSTEM format
    'rejected' => 'Non Delivered',        // OLD SYSTEM format
    'skipped' => 'Non Delivered',         // OLD SYSTEM format
    'failed' => 'Non Delivered',          // OLD SYSTEM format
    'buffered' => 'buffered',             // Intermediate status
];
```

**Commands affected:**
- `php artisan nexmo:fetch-delivery-reports` - Fetches reports from Nexmo API
- `php artisan nexmo:process-delivery-queue` - Processes DLR queue

### 7. SMPP DLR/Inbound WITHOUT RabbitMQ (Direct Database)
**Files:**
- `app/Console/Commands/SmppInboundReceiver.php` - Handles DLR + Inbound SMS
- `app/Console/Commands/SmppDlrReceiver.php` - Handles DLR only
- `app/Console/Commands/SmppUnifiedReceiver.php` - All-in-one (Send + DLR + Inbound)
- `app/Console/Commands/TestSmppSendAndReceiveDlr.php` - Test command

**Architecture:**
```
SMS Sending:     sms:process-queue (uses RabbitMQ) → Existing flow unchanged
DLR Receiving:   smpp:inbound-receiver → Direct database update (NO RabbitMQ)
Inbound SMS:     smpp:inbound-receiver → Direct database insert (NO RabbitMQ)
```

**Commands:**
```bash
# For DLR and Inbound SMS (recommended - NO RabbitMQ)
php artisan smpp:inbound-receiver

# For DLR only (NO RabbitMQ)
php artisan smpp:dlr-receiver

# Test: Send SMS and wait for DLR on same connection
php artisan smpp:test-send-dlr --mobile=919003096885

# All-in-one: Send + DLR + Inbound (NO RabbitMQ)
php artisan smpp:unified-receiver --process-outbound
```

**DLR Processing:**
1. Receives DLR from SMPP connection (DELIVER_SM with ESM_CLASS=0x04)
2. Parses DLR content: `id:XXX stat:DELIVRD err:000`
3. Maps status to OLD SYSTEM format: `DELIVRD` → `Delivered`
4. Updates `smsg_log.deliverystatus2` directly via DeliveryStatusService
5. Fallback: Direct SQL update if DeliveryStatusService fails

**Inbound SMS Processing:**
1. Receives MO from SMPP connection (DELIVER_SM without receipt flag)
2. Parses keyword/subkeyword from message
3. Finds user from `smsshortcodes` table
4. Inserts directly into `itagg_incominglog` table (OLD SYSTEM format)

**Database Updates:**
- DLR: `smsg_log.deliverystatus2`, `deliverytime2`, `deliveryreceipt2`
- Inbound: `itagg_incominglog` with fields: recieved, source, dest, keyword, msg, user_bigid

---

## Important Database Notes

### smsg_log Timestamps
- `timesubmitted`, `dosendtime`, `timesent` - Stored in Europe/London
- `deliverytime2` - Stored in UTC (needs +1 hour for BST display)

### smsg_route Costprice
- International: Use `smsg_route.costprice` for country-specific rates
- UK: Fixed rate 0.0165 (hardcoded, database values unreliable)

### OLD SYSTEM Reference
Legacy PHP files location: `D:\cladue\smsexpert_oldsystem\sites\site7\itagg.com\includes\library\`

---

## Recent Fixes (June 2026)

### 0. OLD SYSTEM Parity — Multi-Number Send, Per-Row DLR, `smsg_log.text` Encoding
**Files:** `app/Services/SmsSendingService.php`, `app/Services/SMPP/SMPPService.php`, `app/Services/SMPP/SinchSmppService.php`, `app/Services/SMPP/SMPPPoolManager.php`, `app/Services/Queue/SmsQueueService.php`, `app/Console/Commands/ProcessSmsQueue.php`, `app/Console/Commands/ProcessSinchSmsQueue.php`

Aligned the API send/DLR lifecycle with OLD SYSTEM (`smssend.inc` + `smsg_2send_csn_smpp_fire.inc` + `daemon_dreceipt_inbound_buffer.php`):
- **Multiple comma-separated numbers** (incl. duplicates) each get their OWN `smsg_log` row, one `submit_sm`, one message-id, one DLR — NO dedup (OLD `itagg_send_normal_sms` behaviour). The unique `smsg_log.id` is now threaded queue→`SmsQueueService`→`SMPPPoolManager`→`SMPPService`/`SinchSmppService` so the duplicate guard and the post-send message-id UPDATE both scope to the exact row id (was `bigid+mobnum`, which collapsed repeats and clobbered message-ids).
- **`smsg_log.text` is stored URL-ENCODED** (`= $params['txt_to_save']`), matching OLD `smssend.inc:823 urlencode($txt)`. The display/report layer urldecodes it; archives are urlencoded too. (Reverted a brief experiment that stored decoded text.)
- **DLR matches per-row by message-id** (`deliveryreceipt1` hex → `onesixty_suppliermsgref` dec → `suppliermsgref`), each row carrying its own id — mirrors OLD `onesixty_suppliermsgref + mobnum` match.
- **`deliverytime2` reverted to OLD SYSTEM parity (GMT):** stored as the provider's DLR `done_date` in GMT/UTC (12-digit `YYYYMMDDHHMM`, = `deliverytime1`'s value), NOT UK-local receive-time. Every display site converts UTC→Europe/London DST-safely: `SMSController::showSmsDetails`/`sentSmsDetails` + `Api/Mobile/SentSmsController` re-add `+ INTERVAL 1 HOUR` in BST (via `isSummerTime`), no offset in GMT; `Api/Mobile/SmsController::formatDeliveryTimestamp` + `CampaignDashboardController` use Carbon `createFromFormat(...,'UTC')->setTimezone('Europe/London')`. This supersedes the 2026-06-08 UK-local rule (client now wants OLD parity).
- **Deliberately NOT mirrored:** the OLD `firing`/`doing` DB-poll send mechanism — the new system is RabbitMQ/direct-SMPP by design; only the observable `smsg_log` column values are matched, not the transport.

### 8. Postpay Report — Default Dates to Today
**Files:**
- `resources/views/admin/reports/partials/postpay-tab.blade.php`
- `resources/views/admin/reports/index.blade.php`

Date From / Date To now default to `now()->format('Y-m-d')`. Reset button restores today, not blank.

### 9. Cost Menu Visibility
**Files:** `resources/views/admin/layouts/modern-sidebar.blade.php`, `resources/views/admin/layouts/app.blade.php`

Hidden, then un-hidden. Both layouts gated by `can_manage_cost` permission.

### 10. Process Monitor — Stripped to Cron Toggle Only
**File:** `resources/views/admin/settings/partials/monitor.blade.php`

Removed Supervisor Processes card, Scheduled Cron Status card, Process Logs section, cron-log modal, 30-second auto-refresh. Kept Cron Jobs Management table + Enabled/Disabled stats only. Page was timing out due to heavy log/process queries.

### 11. SMS Stats Query — Filter by Time-Range
**File:** `app/Http/Controllers/Admin/SettingsController.php`

Added `filterTablesByRange($tables, $start, $end)` that keeps live `smsg_log` plus only archive tables (`smsg_log_YYMM`) whose YYYYMM overlaps the query window. Previously every COUNT fanned across all 14 archives (~100M rows scanned). Hourly window now hits 1 table, not 14.

### 12. Shared Legacy Customer List Trait
**File:** `app/Http/Controllers/Admin/Traits/LegacyCustomerList.php` (new)

Single source of truth for the OLD SYSTEM customer-listing SQL (`users_notes` + `users` + `useroption`, filtered by `lab2id LIKE '%itagg%'`). Wired into:
- `ReportsController` (postpay/daily-sms/money-transfer dropdowns)
- `NotificationController` (recipient picker, 3 places)
- `SettingsController` (customer settings + customer search)
- `ClientEmailController` (email list + `getEmailsData` DataTables)
- `VirtualNumberController` (`getAllUsers` + `getVirtualNumberCustomerList`)
- `AdminUserController` (`/admin/customers` listing)
- `ContractController` (create/edit dropdowns)

Trait behaviour:
- Runs legacy SQL first (CRM-priority ORDER BY).
- Falls back to a simple `SELECT FROM users WHERE login_type IN (...)` if legacy SQL **throws** OR returns **zero rows** (dev DBs, missing `users_notes` / `useroption` / columns).
- Re-sorts result alphabetically by busname → contactname → uname (case-insensitive, urldecoded, empties bottom-sorted).
- Logs the exception via `Log::warning` so missing columns are diagnosable from `storage/logs/laravel.log`.

### 13. AdminUserController Customers Menu — Tab-Aware Pagination
**File:** `app/Http/Controllers/Admin/AdminUserController.php`

Listing now uses the legacy trait + PHP search + `LengthAwarePaginator`. `?filter=migrated` / `?filter=not_migrated` re-applied on the collection (was previously always showing all). Counts derived from the legacy collection so badges match the rows.

### 14. Migrated_at + Old-API-Usage Alert Logic
**Migration:** `2026_06_02_100000_add_migrated_at_to_users_table.php`
**Files:** `AdminUserController::bulkMigrate`, `DetectOldApiUsage`

- `users.migrated_at` (DATETIME, NULL) added — stamped on every bulk migrate.
- Backfill: existing `migration_flag='new'` users get `migrated_at = now()` on migration run so historical `'old'` smsg_log rows stop counting.
- `DetectOldApiUsage` SQL now filters `s.timesent > DATE_FORMAT(u.migrated_at, '%Y%m%d%H%i%s')` — only post-migration old-API sends raise the dashboard banner.

### 15. Dashboard Stats — Monthly Safety-Net Cron
**Files:** `routes/console.php`, `database/migrations/2026_05_31_100000_add_dashboard_stats_full_cron_job.php`

Added `Schedule::command('dashboard:build-stats --all')->monthlyOn(1, '02:00')`. Cron key `dashboard:build-stats-full`. Registered in `cron_job_settings` so it appears in the Process Monitor UI. First install still needs a one-time `php artisan dashboard:build-stats --all` to backfill all months.

### 16. Nexmo Virtual-Number Webhook Auto-Update on Migration
**New files:**
- `app/Services/Queue/WebhookUpdateQueueService.php` — RabbitMQ publisher (queue: `nexmo.webhook.update`)
- `app/Console/Commands/ConsumeWebhookUpdateCommand.php` — consumer (`php artisan rabbitmq:consume-webhook-update`)
- `supervisor.conf` entry `sms-expert-webhook-update-consumer`

**Flow:** `AdminUserController::bulkMigrate` publishes one RabbitMQ message per migrated customer → consumer resolves the customer's Nexmo virtual numbers via `virtual_numbers ↔ smsshortcodes ↔ itagg_instance ↔ users.bigid` → calls `NexmoService::updateNumber($country, $msisdn, $newMoHttpUrl)` per number → mirrors new URL into `virtual_numbers.mo_http_url`. Sinch numbers self-skip (only Nexmo exposes moHttpUrl). New URL = `route('sms.webhook.nexmo')`.

### 17. Virtual Numbers — Duplicate-Row Fix
**File:** `app/Http/Controllers/Admin/VirtualNumberController.php` (`index` + `export`)
**Migration:** `2026_06_02_120000_dedupe_itagg_instance_for_specific_numbers.php`

Numbers `447937946920` and `447507332441` appeared duplicated on `/admin/virtual-numbers` and in CSV exports because they had multiple historical `itagg_instance` rows per shortcode. Both `index` and `export` queries now use a second `leftJoinSub` selecting `MAX(id) as latest_inst_id per smsshortcodes_id` before joining out to `users`. Migration optionally prunes older `itagg_instance` rows for those two MSISDNs (kept latest assignment).

### 18. Rate-Tab UX — Custom Confirm + Removed Success Toasts
**File:** `resources/views/admin/user/partials/tabs/rate-tab.blade.php`

- Replaced native `confirm()` with `rateConfirm()` overlay so Chrome's "Don't allow this page to create more dialogs" can't silently break repeat deletes. Includes "Don't ask me again this session" checkbox stored in `sessionStorage` under `skipConfirm:rate-delete`.
- Removed both success toasts (Add rate + Del rate) and their 1-second `setTimeout`-before-reload delays. Errors still toast. Saves ~2 seconds per price change × bulk operators editing 200+ sub-accounts.

### 19. Daily SMS Tab Font Mismatch
**File:** `resources/views/admin/reports/partials/daily-sms-tab.blade.php`

Customer dropdown was missing the Bootstrap `form-select` class — fell back to browser default styling (smaller font). Added the class to match Post Pay / Money Transfer tabs.

### 20. AdminUsersController — Unique Validation Scoped to Admin Pool
**File:** `app/Http/Controllers/Admin/AdminUsersController.php` (`store` + `update`)

Email / username uniqueness now scopes with `where(fn ($q) => $q->where('login_type', 'admin'))`. Previously updates failed with "The email has already been taken" if any **customer** in the same `users` table shared the email — even though admins and customers are different pools.

### 21. CostController — Null-Safe Exchange Rate
**File:** `app/Http/Controllers/Admin/CostController.php`

`Country::whereNotNull('exchange_rate_eur_to_gbp')->first()` returns null on fresh DBs. `$exchangeRateData->exchange_rate_eur_to_gbp ?? 0.85` doesn't help because PHP 8 throws on the property access first. Changed to nullsafe `?->` so the page renders with the default rate when no row exists.

### 22. Scheduled Campaign SMS Fix
**File:** `app/Console/Commands/ProcessScheduledSms.php`

Worker WHERE clause was looking for `deliverystatus2='tomorrowonward' AND sentstatus='pending'`, but `CampaignQueueService` writes scheduled rows with `sentstatus='tomorrowonward' AND deliverystatus2='scheduled'`. Columns were swapped — cron found 0 rows forever. Scheduled campaigns sat in `smsg_log` with `timesent='00000000000000'` permanently. Fixed query, scheduled rows now process at `dosendtime`.

### 23. Campaign Report Download — UTC→London for `deliverytime2`
**File:** `app/Http/Controllers/Campaign/CampaignDashboardController.php` (`downloadCampaignReport`)

Was selecting `STR_TO_DATE(deliverytime2)` which returned the raw UTC value. CSV showed "Time Delivered" 1 hour earlier than "Time Message Sent" during BST. Now keeps the raw 14-digit string and converts via `Carbon::createFromFormat('YmdHis', $raw, 'UTC')->setTimezone('Europe/London')`. DST handled automatically.

### 24. Campaign Report — Delivery Status Label Mapping
**File:** `app/Http/Controllers/Campaign/CampaignDashboardController.php` (`downloadCampaignReport`)

Added translation table for OLD SYSTEM display compatibility:
```
'acked'     => 'Delivered'      (submitted to network, OLD SYSTEM rendered as Delivered)
'pending'   => 'Pending'
'scheduled' => 'Scheduled'
'Delivered', 'Non Delivered', 'Lost Notification' => unchanged
```
Unknown values pass through unchanged so new states don't get silently hidden.

### 25. Virtual Number Expiry Report — New Admin URL
**File:** `app/Console/Commands/VirtualNumberExpiryReportCommand.php`

Email link replaced — was hardcoded to `secure.itagg.com/wdjhytbvsadjfhaksjdhgyuaewashdgf/gajkdhgfajdhgfasjgfasjdhfgahdgfa.php?...`. Now `route('admin.user.show', ['id' => $expiry->id])` → `https://admin.smsexpert.co.uk/admin/user/{id}`. Command only emails for `migration_flag='new'` users, so the new admin is correct.

### 26. Help Menu — Subdomain-Specific Guides
**Files:** `resources/views/admin/layouts/app.blade.php`, `resources/views/layouts/app.blade.php`, `resources/views/campaign/layouts/app.blade.php`

- Admin top nav: new Help dropdown with User Guide / Admin Guide / Migration Guide → `admin.smsexpert.co.uk/userguide/*.html`.
- Customer + Campaign layouts: 2 customer guides added to existing Help dropdown → `dashboard.smsexpert.co.uk/userguide/*.html`.

### 27. AdminProfileController — Self-Service Password Change
**New files:**
- `app/Http/Controllers/Admin/AdminProfileController.php`
- `resources/views/admin/profile/change-password.blade.php`
**Updated:** `routes/web.php` (routes `admin.profile.change-password` + `.update`), `resources/views/admin/layouts/app.blade.php` (account-circle dropdown in top nav).

Logged-in admin can change their own password without super-admin involvement. Verifies current password (supports both legacy plain-text and md5 hash matching `AdminAuthController::login`), validates new password (`min:8`, `confirmed`), rejects same-as-current, writes as md5. Audit log line on success.

### 28. VirtualNumberController — `destroy()` is Destructive (KNOWN ISSUE, NOT YET FIXED)
**File:** `app/Http/Controllers/Admin/VirtualNumberController.php:1289` (`destroy`)

OLD SYSTEM convention: expiry never deletes — the row stays in `itagg_instance` with the past date. NEW SYSTEM's `destroy()` action (Cancel button on `/admin/virtual-numbers`) **hard-deletes** from `itagg_instance`, `smsshortcodes`, AND `virtual_numbers`. Customer history of having that number is wiped.

`removeNumber()` and `forceExpiry()` are the **soft** paths that match OLD SYSTEM behaviour. If/when fixing `destroy()`, mirror those: set `is_active=0`, `expiry='1999-05-19'`, `active=0`, don't delete rows.

### 29. Reports Tabs — Alphabetical Customer Dropdown
**File:** `app/Http/Controllers/Admin/Traits/LegacyCustomerList.php`

Trait's `getLegacyCustomers()` now sorts collection by `busname → contactname → uname` (case-insensitive, urldecoded). Applied universally so every admin customer dropdown is A→Z, not CRM-priority order.

---

## Important Architectural Notes (June 2026)

### Customer Dropdown Single Source of Truth
Every admin customer dropdown / listing / email recipient picker MUST go through `Traits\LegacyCustomerList::getLegacyCustomers()`. Do NOT write a new `User::where(...)->get()` query for customer lists — it bypasses the legacy CRM filter, the alphabetical sort, the fallback for dev DBs, and the exception handling.

### Admin User Pool Separation
`users` table holds BOTH customers (`login_type='customer'` or NULL) and admins (`login_type='admin'`). Any uniqueness check on `users.contactemail` / `users.uname` for admin operations must scope to `where('login_type', 'admin')` or it will conflict with customer rows.

### OLD SYSTEM Display Convention for Status
DB stores technical states (`acked`, `pending`, `scheduled`). User-facing CSVs / reports must translate to OLD SYSTEM display labels (`Delivered`, `Pending`, `Scheduled`) — see `CampaignDashboardController::downloadCampaignReport` for the canonical mapping.

### Cron Health Audit
The Process Monitor UI at `/admin/settings#monitor` only enables/disables crons. Run details, last-run timestamps, and per-cron log access were removed in fix #10 because the queries were too slow. To inspect actual cron health, tail `storage/logs/YYYY-MM-DD/<command>.log` or query `cron_job_logs`.

### Admin Layout `main-wrapper`
`resources/views/admin/layouts/app.blade.php` has its `<main class="main-wrapper">` wrapper **commented out**. Every admin Blade view MUST open `@section('content')` with `<main class="main-wrapper" id="main-wrapper"><div class="main-content">` or the page renders behind the fixed header. Copy from `admin-users/edit.blade.php` when creating new admin views.

### SMPP Multi-Bank DLR (mirrors OLD SYSTEM, NOT YET ENABLED IN PRODUCTION)
**New file:** `config/smpp_banks.php` — 10 bank definitions (`a0`..`j0`), each with its own seq_id range, distributed across **3 Vonage EU hosts** so no single POP carries all the DLR traffic.

Vonage exposes 3 EU SMPP hosts (verified via DNS 2026-06-05) — `smpp-eu-3` and `smpp-eu-4` DO NOT resolve, don't put them in config:
- `smpp-eu.vonage.com` → 216.147.33.1
- `smpp-eu-1.vonage.com` → 216.147.33.2
- `smpp-eu-2.vonage.com` → 216.147.33.3

Distribution:
- `a0, b0, c0, d0` → `smpp-eu.vonage.com` (SMPP_HOST_1, 4 banks)
- `e0, f0, g0` → `smpp-eu-1.vonage.com` (SMPP_HOST_2, 3 banks)
- `h0, i0, j0` → `smpp-eu-2.vonage.com` (SMPP_HOST_3, 3 banks)

Host fallback chain per bank: `SMPP_BANK_<key>_HOST → SMPP_HOST_<N> → SMPP_HOST → hardcoded Vonage default`. Override at any level. OLD SYSTEM used 2 hosts (`smpp1/smpp2.nexmo.com`); the modern Vonage gateway exposes 3 EU hosts and we spread across all 3 for the same load-distribution effect.

**Files edited:**
- `app/Services/SMPP/SMPPService.php` — constructor accepts an optional `$bankKey`. When supplied AND `config('smpp_banks.enabled')` is true, the service binds with bank-specific credentials, system_type, and a partitioned seq_id range. Single-bind env mode is preserved as default. Fixed hardcoded `system_type='smpp'` in bind PDU at line 357 — now reads `$this->systemType` so `SMPP_TYPE=smppBK1P3` actually takes effect. Added `nextSequenceNumber()` that wraps within the bank's range; all callsites updated to use it.
- `app/Console/Commands/SmppDlrReceiver.php` — added `--bank=<key>` option. Passes the bank into SMPPService.
- `supervisor.conf` — added 10 `sms-expert-smpp-dlr-bank-{a0..j0}` programs (`autostart=false`) plus group `sms-expert-smpp-dlr-banks`.
- `.env.example` — documented `SMPP_BANKS_ENABLED`, `SMPP_BANK_DEFAULT`, `SMPP_HOST_2`, and per-bank override pattern.

**Architecture:**
Each of 10 processes binds with the SAME Vonage system_id but a DIFFERENT seq_id range (1-2M, 2M-4M, …, 18M-20M). Vonage routes DLR back to the bind whose seq_id was used for the original submit_sm. With 10 parallel binds vs 1, DLR receive capacity scales ~10×. Two binds with overlapping ranges would lose DLR routing — the partitioning is load-bearing.

**Deploy sequence (DO NOT skip the Vonage step):**
1. **Confirm with Vonage** that multi-bind / concurrent-session mode is enabled on the SMPP account. Without it, every bank past the first hits `ESME_RALYBND` (`0x05` "Already in bound state") and supervisor restart-loops.
2. Set `SMPP_BANKS_ENABLED=true` in production `.env`.
3. `php artisan config:clear`.
4. `sudo supervisorctl reread && sudo supervisorctl update`.
5. `sudo supervisorctl start sms-expert-smpp-dlr-banks:*` — starts all 10 banks.
6. Stop the legacy single-bind receiver (`sudo supervisorctl stop smpp_dlr_receiver` or whatever the live name is) so it doesn't compete for binds.

**Rollback:** `SMPP_BANKS_ENABLED=false` in `.env`, `supervisorctl stop sms-expert-smpp-dlr-banks:*`, restart the single-bind receiver. Service immediately reverts to single-bind env-driven config.

### DLR Customer Webhook — Event-Driven Push (replaces 5-min cron)
Previously: DLR arrives → `DeliveryStatusService::processDlrPushCallback` inserts into `delivery_receipt_push_log` (status='new') → `delivery-receipt:push` cron polls the table every 5 minutes → POSTs to customer URL. The cron was disabled 2025-12-02 leaving a backlog of stuck 'new' rows.

**New event-driven path:**
- `DeliveryStatusService::processDlrPushCallback` now also **publishes the inserted row id to RabbitMQ queue `dlr.callback.push`** right after the INSERT (non-fatal — if publish fails the row sits as 'new' for the cron fallback).
- New consumer `dlr-callback:consume` (long-running) drains the queue, loads each row, calls `DlrCallbackPusher::processRow($rowId)` which **atomically claims** the row (`UPDATE WHERE status='new'`) — prevents cron/consumer races — POSTs to `url`, updates `status` to `processed` / `new` (with `retries_left--` + `dosendtime+wait_minutes`) / `fail`.
- On retry-able failure the consumer republishes with delay = `wait_minutes` via `RabbitMQService::publishDelayedMessage`. DB row remains the source of truth for retry state — the queue message is just a wakeup signal.

**New files:**
- `app/Services/DlrCallbackPusher.php` — shared per-row processor (atomic claim + POST + status update). Both the consumer and the legacy cron can call it; identical behavior.
- `app/Console/Commands/ConsumeDlrCallbacks.php` — `dlr-callback:consume` worker. Reads queue, dispatches to pusher, requeues with delay on retry, ACKs always (DB owns retry state).
- `app/Console/Commands/BackfillDlrCallbacks.php` — `dlr-callback:backfill --limit=N` one-shot to enqueue existing 'new' rows.

**Edited files:**
- `app/Services/DeliveryStatusService.php` — switched `DB::table(...)->insert` to `insertGetId`, added try-catch RabbitMQ publish.
- `.env.example` — added `RABBITMQ_DLR_CALLBACK_QUEUE=dlr.callback.push`.
- `laravel-rabbitmq.conf` — added `dlr-callback-consume` supervisor program (autostart=true).
- `start-local-workers.bat` / `stop-local-workers.bat` — added "DLR Callback Push" window.

**Deploy steps:**
1. `php artisan config:clear`
2. Local: stop+start workers. Production: `supervisorctl reread && supervisorctl update && supervisorctl start dlr-callback-consume`.
3. `php artisan dlr-callback:backfill --dry-run` to see what's pending; then run without `--dry-run` to drain the backlog. Safe to re-run — atomic claim guards against double-sends.
4. Leave the `delivery-receipt:push` cron disabled. It still works (uses the same `DlrCallbackPusher` if you refactor it later) but is no longer needed for normal operation.

**Health monitoring (mirrors OLD SYSTEM cron pattern):**

| OLD SYSTEM | NEW SYSTEM |
|---|---|
| `itagg_daemon_dreceipt_push_multi.php` long-running daemon | `dlr-callback:consume` worker (supervisor `autorestart=true`) |
| Writes `/tmp/dlrPushDaemon-<name>.touch` on every iteration | Writes `storage/app/dlr-callback-heartbeat.touch` on every message |
| `v3_itagg_daemon_dreceipt_push_monitor.php` every minute via cron | `dlr-callback:watchdog` every minute via Laravel scheduler |
| Watchdog: if touch > 120s old → kill + restart | Watchdog: if touch > 120s old → `SmppErrorAlertService::notify` (supervisor handles the restart) |
| Daily pkill at 02:25 & 03:30 → monitor restarts within 1 min | `Schedule::exec('supervisorctl restart dlr-callback-consume')` at 02:25 & 03:30 |
| Multiple daemons per `dlr_daemon_id` (per-customer partitioning) | Single consumer (sufficient for current volume; add `--daemon-name` filter + supervisor copies if scale requires) |

**Cron registry rows (`cron_job_settings`):** `dlr-callback:watchdog` and `dlr-callback:restart` both registered + enabled. Show up in admin Process Monitor → Cron Settings for on/off control.

**Why supervisor for restart instead of pkill:** OLD SYSTEM pkill works because daemon is bash-spawned and monitor re-spawns it. Our daemon is supervisor-managed, so we restart via `supervisorctl` which respects supervisor's process tracking, group membership, and log rotation. On Linux only — Windows local dev relies on manual stop/start.

**Files added/edited for monitoring:**
- `app/Console/Commands/ConsumeDlrCallbacks.php` — added `touchHeartbeat()` + public static `heartbeatPath()`.
- `app/Console/Commands/WatchDlrCallbackConsumer.php` — `dlr-callback:watchdog` command. Reads heartbeat, alerts via existing `SmppErrorAlertService::notify` if stale.
- `routes/console.php` — scheduled watchdog every minute + two supervisor restart entries.

**Queue ack/nack semantics (one email per incident, no duplicate sends):**
| Outcome from `DlrCallbackPusher::processRow` | Consumer returns | RabbitMQ action | DB row state |
|---|---|---|---|
| `sent` (POST 2xx) | `true` (ack) | message removed | status=`processed` |
| `retry` (POST failed, retries_left > 0) | `true` (ack) + republish with delay = wait_minutes | original ack'd; delayed copy lands later | status=`new`, retries_left--, dosendtime forward |
| `fail` (POST failed, retries_left = 0) | `true` (ack) | message removed | status=`fail`, customer failure email queued |
| `skipped` (atomic claim missed — already doing/processed/fail) | `true` (ack) | message removed | unchanged |
| `missing` (row id doesn't exist) | `true` (ack) | message removed | n/a |
| **Unexpected exception** thrown anywhere in the try block | **`false` (nack)** | RabbitMQService republishes with exponential backoff (10/20/40/…/300s, up to `RABBITMQ_MAX_RETRIES`) | row's `doing` flag rolled back to `new` so re-delivery can claim again |

**Duplicate-send guard:** Atomic claim `UPDATE delivery_receipt_push_log SET status='doing' WHERE id=? AND status='new'` ensures only ONE worker can POST to the customer URL per row, regardless of how many times the queue redelivers the same message.

**Stuck-row sweeper:** `dlr-callback:sweep-stuck` (every 5 min via scheduler) catches rows stuck at `status='doing'` for >10 min (e.g. consumer SIGKILLed mid-claim — exception rollback can't fire). Releases them to `status='new'` and republishes to the queue. Registered as togglable cron `dlr-callback:sweep-stuck` in `cron_job_settings`.

**Customer failure email on retries-exhausted (mirrors OLD SYSTEM):**
When `DlrCallbackPusher::processRow` decrements `retries_left` to 0, the row transitions to `status='fail'` AND a "SMS Expert Delivery Receipt Forwarding Failed" email is queued via `EmailQueueService::queueEmail(DeliveryReceiptFailureMail::class, ...)`. Identical Mailable + view (`emails.delivery-receipt-failure`) as the legacy `DeliveryReceiptPushCommand::sendFailureNotification` — both code paths produce the same rendered email. Recipient resolution:
1. `config('delivery_receipt.special_recipients')[$userId]` — per-customer override
2. `users.contactemail` / `users.contactname` — primary lookup
3. `config('delivery_receipt.default_recipient')` — fallback

Per-customer opt-out via `config('delivery_receipt.excluded_notification_users')`. `config('delivery_receipt.cc_emails')` always CC'd. All email-side errors are caught + logged — never break the row's status update.

### Per-date / per-component log directory structure
Logs are sorted into date-bucketed component folders so operators can find one component's history without grepping the master `laravel.log`:

```
storage/logs/
└── {YYYY-MM-DD}/
    ├── laravel.log                          # application-wide (Laravel default)
    ├── smpp/                                # SmppLogger — per provider/component
    │   ├── vonage.log                       # SMPPService + Vonage SMPP commands (dlr/inbound/consumers/diagnostic)
    │   ├── sinch.log                        # SinchSmppService + SinchDlrReceiver
    │   ├── cluster.log                      # SMPPServiceCluster
    │   ├── pool.log                         # SMPPPoolManager
    │   ├── alerts.log                       # SmppErrorAlertService
    │   ├── dlr.log                          # combined DLR feed (SmppLogger::logDlr)
    │   └── errors.log                       # every SmppLogger->error() mirrored here
    ├── rabbitmq/                            # per queue
    │   ├── sms.outbound.log
    │   ├── sms.dlr.log
    │   ├── dlr.callback.push.log
    │   ├── webhook.dlr.log
    │   └── …                                # auto-created when a queue first sees traffic
    └── cron/                                # MOVED — was logs/cron/{date}, now logs/{date}/cron
        └── {CommandName}.log                # CronLogService
```

**SMPP logging migrated off `laravel.log` (2026-06-07).** Every `Log::` call in the SMPP services (`SMPPService`, `SinchSmppService`, `SMPPServiceCluster`, `SMPPPoolManager`, `SmppErrorAlertService`) and the SMPP/Sinch console receivers (`Smpp*` / `Sinch*` commands) was replaced with the existing `SmppLogger` static factories (`SmppLogger::vonage()`, `::sinch()`, `::forProvider('cluster'|'pool'|'alerts')`). They now write to `logs/{date}/smpp/{component}.log` instead of `laravel.log` — mirroring the RabbitMQ per-queue layout. `SmppLogger` is the single source of truth; do NOT add new `Log::` calls in SMPP code. The former `Log::channel('single')->info(...)` PDU-trace lines now go to `smpp/vonage.log` too. (Queue/scheduler commands that merely *use* an SMPP service — `ProcessDlrQueue`, `ProcessSmsQueue`, `ProcessSinchSmsQueue`, `SendScheduledMessages`, etc. — were left on `laravel.log`; only the long-running receiver daemons were in scope.)

**Cron log path moved (2026-06-07).** `CronLogService` now writes `logs/{date}/cron/{CronName}.log` (previously `logs/cron/{date}/{CronName}.log`) so cron lives under the same per-date folder as `smpp/`, `rabbitmq/`, and `laravel.log`. Updated callers: `CronLogService` (path + `getAvailableDates`/`cleanOldLogs` now scan `logs/{date}` for a `cron/` subfolder), `routes/console.php::getCronLogPath()`, `DatabaseTidy`. **`cleanOldLogs()` now deletes only the `cron/` subfolder of an expired date dir — NOT the whole date dir** — so it can't wipe the sibling `smpp/`/`rabbitmq/`/`laravel.log`.

**RabbitMQ logs:** Service `App\Services\Logging\RabbitMQLogService::for($queueName)` opens (or creates) the day's file at `logs/{date}/rabbitmq/{queue}.log` and exposes `info/warning/error/debug` levels. Auto-creates the date and rabbitmq folder on first write.

`RabbitMQService::processMessage`, `handleFailedMessage`, and `publishToQueue` now write structured entries to the queue-specific file IN ADDITION to the master `laravel.log` — so the master log still has everything, but each queue also has a focused timeline showing only its events:

```
[2026-06-07 06:12:01.859] INFO: PROCESSING — message received from queue {"message_id":"df902dde11","bigid":"9912345","mobile":"447911000111","status":"Delivered","payload_keys":"message_id,mobile,status,bigid,retry_count,max_retries","retry_count":0,"max_retries":3}
[2026-06-07 06:12:01.862] INFO: ACK — processed OK, removed from queue {"message_id":"df902dde11","bigid":"9912345","mobile":"447911000111","status":"Delivered","payload_keys":"…"}
[2026-06-07 06:12:02.001] WARNING: RETRY scheduled (attempt 1/3) in 20s {"row_id":"4471","url":"https://cust/dlr","user_id":"88","payload_keys":"…","error":"connect timeout"}
[2026-06-07 06:12:04.000] ERROR: Exception in consumer callback — message NOT acknowledged {"campaign_id":"77","file_id":"903","payload_keys":"…","error":"SQLSTATE…","error_at":"…/SmsController.php:1234"}
[2026-06-07 06:12:04.500] ERROR: DEAD-LETTER — message exhausted 3 retries {"message_id":"xyz","mobile":"4479…","payload_keys":"…","error":"SQLSTATE…"}
```

**Payload summary in every line (2026-06-07).** `RabbitMQService::summarizeForLog($data)` extracts a curated set of identifying fields from each message payload (`message_id`, `bigid`, `mobile`/`msisdn`/`to`, `status`, `mailable`, `campaign_id`, `row_id`, `url`, `user_id`, …) plus a `payload_keys` list of all top-level keys, and merges it into every per-queue log line (PUBLISH, PUBLISH-delayed, PROCESSING, ACK, RETRY, DEAD-LETTER, exception). It never logs the full SMS text / email body, and caps each value at 120 chars. So instead of `{"queue_id":null}` you now see exactly *what* each message was. Add a new identifying key to the `$keysOfInterest` whitelist if a new queue needs it surfaced.

Retention helpers on `RabbitMQLogService`:
- `getLogsForDate($date)` — list per-queue files for a date
- `getAvailableDates()` — date dirs that contain a rabbitmq subfolder
- `cleanOldLogs($days = 30)` — delete `logs/{date}/rabbitmq` older than N days

### Universal RabbitMQ consumer error alerts (NEW: applies to all 20+ consumers)
Every consumer that goes through `RabbitMQService::consumeFromQueue` automatically gets:

| Event | RabbitMQ action | Email sent? |
|---|---|---|
| Consumer callback returns `true` | `basic_ack` — message removed from queue | no |
| Consumer callback returns `false` | ack + republish with exponential backoff (10s/20s/40s/…/300s, up to `RABBITMQ_MAX_RETRIES`) | no (transient — handled by retry) |
| Consumer callback throws an exception | ack + republish with backoff | **YES** — `SmppErrorAlertService::notifyTransient("Queue processing error: <queue>")` |
| Message exhausts max retries | ack + dead-letter via `handlePermanentFailure` | **YES** — `notifyTransient("Queue message dead-lettered: <queue>")` |

Both alerts use the same branded `SmppErrorAlertMail` template and are throttled per-subject by `SMPP_ALERT_THROTTLE_MINUTES` (default 15 min). A 100-msg failure burst on the same queue = ONE email, not 100. Different subject for "started failing" vs "gave up" so operators can tell them apart.

**Coverage:** automatic for any consumer wired via `RabbitMQService::consumeFromQueue` — including `dlr-callback:consume`, `smpp:consume-sms`, `smpp:consume-dlr`, `campaign:consume`, `rabbitmq:consume-emails`, `queue:webhook --type=dlr`, `queue:webhook --type=inbound`, `queue:inbound-sms`, `queue:push-notifications`, `nexmo:process-delivery-queue`, `rabbitmq:consume-campaign-file-migration`, `rabbitmq:consume-webhook-update`, and any future consumer added to the same service. No per-consumer code change needed.

**Files for this feature:**
- `app/Services/SMPP/SmppErrorAlertService.php` — new `notifyTransient($subject, $body, $context)` method. Cooldown-only throttle (no active-until-recovery flag, since queue errors don't have a clean "recovered" signal).
- `app/Services/Queue/RabbitMQService.php::processMessage()` — `notifyTransient` call added to the exception catch block.
- `app/Services/Queue/RabbitMQService.php::handleFailedMessage()` — `notifyTransient` call added to the retries-exhausted branch.

### SMPP Error Email Alerts
**New service:** `app/Services/SMPP/SmppErrorAlertService.php` — single static `notify($subject, $body, $context)` method. Sends an email when SMPP fails: connect-fail on send path, transceiver/bind_receiver final-failure after retries, DLR receiver sustained connect failures (≥5 consecutive), DLR receiver top-level loop crashes. Wired into:
- Vonage: `SMPPService::bind()` final catch, `SmppDlrReceiver::connectAndBind()` after 5 attempts.
- Sinch: `SinchSmppService::bind()` post-retries, `SinchSmppService::bindReceiver()` post-retries, `SinchSmppService::sendSMS()` connect-fail path, `SinchDlrReceiver::handle()` top-level exception.

**Throttle (one email per incident):** Once an alert fires for a subject, it's marked ACTIVE in cache (`smpp-alert:active:<md5>`, 24h safety TTL). No further email for that subject until either (a) a successful bind on the matching service calls `SmppErrorAlertService::clear($subject)`, OR (b) 24h passes. Independently, a `SMPP_ALERT_THROTTLE_MINUTES` cooldown (default 15) blocks rapid fail/recover/fail flapping from re-emailing immediately even after a clear. Recovery `clear()` calls are wired into:
- `SMPPService::bind()` ESME_ROK branch — clears Vonage bind + DLR-receiver subjects
- `SinchSmppService::bind()` status-0 branch — clears Sinch transceiver + send-path connect subjects
- `SinchSmppService::bindReceiver()` status-0 branch — clears Sinch DLR-receiver + crash subjects

Net effect: a sustained outage = ONE email when it starts, ONE email when it starts again after recovery. Not one per 15-minute window.

**Config (.env):**
- `SMPP_ALERT_ENABLED=true|false` — master switch
- `SMPP_ALERT_EMAIL=a@x.com,b@x.com` — comma-separated recipients
- `SMPP_ALERT_THROTTLE_MINUTES=15` — per-subject window
- `SMPP_ALERT_FROM_NAME` — optional, falls back to `MAIL_FROM_NAME`

Delivery uses `Mail::raw` (no Mailable class — keeps the dependency surface tiny). Wrapped in try/catch so alert-side errors never break the SMPP path that triggered them.

### Sinch Multi-Bank DLR (mirrors OLD SYSTEM, NOT YET ENABLED IN PRODUCTION)
Same architecture as the Vonage multi-bank above, but for Sinch (formerly mBlox). OLD SYSTEM runs 10 Sinch SMPP binds (`a..j`) — see `sites/thisserver_details.inc:92-184` — with 5 different systemType values (`smppProd1`..`smppProd5`, 2 banks each), all on `profile=21839`, each owning a partitioned seq_id range. We were seeing intermittent missing/late Sinch DLRs with the single transceiver bind, matching the Vonage pain point. Mirroring OLD SYSTEM brings DLR receive capacity to ~10× per provider.

**New files:**
- `config/sinch_banks.php` — 10 bank definitions (`a..j`). Each bank carries `host`, `port`, `system_id`, `password`, `system_type` (one of `smppProd1..smppProd5`), `profile=21839`, and a non-overlapping `seq_id_range`. Master switch `SINCH_BANKS_ENABLED`; default bank when no `--bank` is passed is `SINCH_BANK_DEFAULT` (defaults to `a`).
- `app/Console/Commands/SinchDlrReceiver.php` — artisan command `sinch:dlr-receiver` with `--bank=<a..j>` option. Mirrors `SmppDlrReceiver` (reconnect loop, periodic enquire_link, pcntl signal handling via `onPcntlSignal()`). Instantiates `SinchSmppService($bankKey)`.

**Files edited:**
- `app/Services/SMPP/SinchSmppService.php` — constructor now `__construct(?string $bankKey = null)`. When supplied AND `config('sinch_banks.enabled')` is true, the service overrides host/port/system_id/password/system_type/profile and clamps sequence numbers to the bank's range. Added `$seqIdMin`, `$seqIdMax`, `$bankKey` properties + `nextSequenceNumber()` private helper. Single-env mode preserved when no bank passed (so existing `sinch:smpp-*` commands keep working).
- `supervisor.conf` — added 10 `sms-expert-sinch-dlr-bank-{a..j}` programs (`autostart=false`) plus group `sms-expert-sinch-dlr-banks`.
- `.env.example` — documented `SINCH_BANKS_ENABLED`, `SINCH_BANK_DEFAULT`, per-bank override pattern, and the multi-bind-mode warning.

**Architecture:**
Same partitioning model as Vonage: 10 transceiver binds, identical Sinch `system_id`, distinct `seq_id_range` per bank. Sinch routes DLR back via the seq_id of the original submit_sm, so each bank only receives DLR for its own submits. systemType rotates through `smppProd1..smppProd5` (2 banks each) — this is how OLD SYSTEM disambiguates which bank a DLR came in on for error-code mapping.

**Deploy sequence (DO NOT skip the Sinch step):**
1. **Confirm with Sinch support** that multi-bind / concurrent-session mode is enabled on the SMPP account. Without it, every bank past the first hits `ESME_RALYBND` (`0x05` "Already in bound state") and supervisor restart-loops. The OLD SYSTEM has had this enabled for years; the new account may need an explicit ticket.
2. Set `SINCH_BANKS_ENABLED=true` in production `.env`.
3. `php artisan config:clear`.
4. `sudo supervisorctl reread && sudo supervisorctl update`.
5. `sudo supervisorctl start sms-expert-sinch-dlr-banks:*` — starts all 10 banks.
6. Stop any existing single-bind Sinch DLR receiver so it doesn't compete for binds.

**Rollback:** `SINCH_BANKS_ENABLED=false` in `.env`, `supervisorctl stop sms-expert-sinch-dlr-banks:*`, fall back to single-bind. Service reverts to env-driven config on next start.

**Note (current production):** Sinch DLR is *also* received via HTTP webhook (`/webhook/sinch-dlr` style endpoint, similar to OLD SYSTEM `smsreply.mes`). The SMPP multi-bank path is intended as a parallel/redundant DLR channel — webhook stays active so we have belt-and-braces coverage. Both paths write into the same `dlr_status` updater so duplicates are idempotent on `bigid + mobnum`.

**HARD LIMIT — Sinch modern SMPP gateway: 2 connections per host/system_id.**
Per Sinch docs (developers.sinch.com → SMS SMPP Connectivity): *"Default limit of two parallel connections per host/system_id."* This is why our 10-bank attempt fails: bank `a` binds, bank `b` binds, banks `c–j` all return status 5 ESME_RALYBND. The OLD SYSTEM gets 10 parallel binds because it spreads across legacy mBlox hosts (`smpp3.mblox.com` + `smpp4.mblox.com`) — the modern Sinch gateway is one host per region, so we can't get the same effect through host distribution unless we also have traffic in multiple regions.

The 10-bank config in `config/sinch_banks.php` is left intact for when Sinch raises the limit, but `start-local-workers.bat` only starts banks `a` and `b` by default; `c–j` are commented out. Production supervisor entries for `c–j` exist but `autostart=false` and should stay manually-stopped until the account-manager request lands.

**To run more than 2 binds:** open a Sinch account-manager ticket asking for the per-host connection limit on `<account>/<system_id>` to be raised to N. Once raised, uncomment the relevant bank lines in `start-local-workers.bat` and `supervisorctl start sms-expert-sinch-dlr-bank-{c..j}` on production.

**Sinch DLR receiver uses bind_receiver, not bind_transceiver.** `SinchDlrReceiver` calls `SinchSmppService::bindReceiver()` (added in this same change, mirrors OLD SYSTEM `itagg_daemon_smpp_dlr_multi.php:296`). Receiver and transceiver bind roles count separately toward Sinch's 2-connection limit on some configurations, so using receiver here leaves the transceiver slot free for the sender. `registered_delivery=0x01` is set in `packSubmitSm()` so DLRs are actually requested — verified at SinchSmppService.php:1183 and the concatenated path at line 638.

---

## Session Changelog — July 2026

### Admin customers page — master/sub-account tree
`AdminUserController::index`, `resources/views/admin/user/index.blade.php`, `Traits/LegacyCustomerList`.
- New **Account Type** column (after Email): **Master** / **Sub Account**. A sub = `users.masteruname` points at another user's `uname` (same rule as the detail page's `$subAccounts`). `masteruname` added to both legacy + fallback list SQL.
- Sub-accounts render **nested under their master** as a collapsible tree (`▸`/`▾`). Subs whose master is in the same list are dropped from top-level (shown only nested); orphan subs kept. `busname`/`contactname`/`phone` URL-decoded for display.
- **Search now finds sub-accounts**: a supplementary `users`-table query (respecting the migration tab) is merged in, so a nested sub (e.g. `e4ab0498`) is findable — previously returned "No customers found".

### Migration — shared 5-option modal + refund guard
- Per-master **Migrate** button AND bulk **Migrate Selected** now use the SAME 5-option modal: parent only / selected sub(s) / all subs / parent+all / parent+selected. Scrollable checklist + Check/Uncheck-all for 100+ subs. **Migration Guide** modal + `docs/migration-guide.md`.
- `bulkMigrate`: explicit ID selection now migrates **disabled (`bit_disabled=1`)** accounts too (the `bit_disabled=0` guard applies only to "Migrate All") — fixed "No customers found to migrate" on selected disabled subs.
- **Refund-on-failure guard** (`CampaignQueueService` failure branch): refunds `smsg_server1_sent` ONLY if the row was already `sentstatus='ok'` (i.e. actually charged). Charge happens only in `storeMessageIdMapping` on submit-success (Vonage `SMPPService.php:1972`, Sinch `SinchSmppService.php:936`) — so submission failures are never charged and a blind refund would over-credit. Audit: the only real post-charge-then-fail gap is the **Sinch DLR handlers** (`SinchSmppService.php:990,1663`).

### Legacy SMS API — full OLD-SYSTEM param parity
`LegacySmsApiController::sendSms`, `SmsSendingService`. Added all missing `sms.mes` params: **`userdef`** (alias for `userdefined`), **`sendrelative`** (→ computes `send`), **`smppusr`**, **`subusrkey`** (passed through; pooledvirt resolution NOT yet ported), **`binaryflags`** (→ `smsg_log.binaryflags`), **`incoming_message_id`** (→ resolved to `itagg_incominglog.id`), **`msisdnAlias`** (→ resolved via `itagg_msisdn_alias`, overwrites `to`, tags userdefined, `301` on miss).

### SMPP fixes (`SMPPService`, `SinchSmppService`)
- **GSM 03.38 encoding fix** — `_` was received as `§` on India (UK lenient). For `data_coding=0x00` the message is now converted ASCII→GSM via `encodeGsm7bitDefault()` (`_`→0x11, `@`→0x00, `$`→0x02, `\x1B` escapes for `[ ] { } | ~ ^ \`). Mirrors OLD `gsmencoder::utf8_to_gsm0338` (unpacked). Applied to single + concat paths in both providers.
- **"Failed to send part 1/2" false-timeout fix** (`waitForSubmitSmResponse`) — the transceiver send-connection also receives DLRs, and inline `handleDeliverSm()` (slow DB work) starved the submit-response wait. Now **buffers `deliver_sm` PDUs** + **silence-based timeout** (resets on any PDU, 45s cap); buffered DLRs drained after send via `drainDeferredDeliverSm()`. Mirrors OLD `pdu_queue` + separate DLR-daemon design.

### Admin bulk email — rich editor
`ClientEmailController`, `admin/email/show.blade.php`, `admin/emails/bulk.blade.php`, `emails/layouts/master.blade.php`.
- Plain textarea → **TinyMCE** (font family/size, line-height, colours, **alignment**, lists, link, **image upload**, table). Image upload → `POST /email/upload-image` (`ClientEmailController::uploadImage`) → `storage/app/public/email-images`, hosted URL (not base64, which Gmail/Outlook strip).
- Fixed **forced-centre** alignment: email body now explicit `text-align:left` (Outlook inherited the container's `align=center`); bulk template renders `{!! $messageContent !!}` so the editor's formatting/alignment is preserved.

### Data import (local dev)
Imported production `qdx` dump into `sms_expert`: **all 24 `smsg_log` tables** (`smsg_log` + `_2407`…`_2605`, ~26M rows) + **`users`** (40,045 rows; old dev `users` backed up to `D:\qdx\users_backup_before_import_2026-07-07.sql`). Import needed relaxed `sql_mode=''` (zero-date default on `users.bulksms_tally_last_reset`). These tables import as **InnoDB** (source dump), ~1.5× the SQL size on disk.

## Recent Fixes (July 2026)

### Livebeat report (new-system port of OLD `steve/mynews/tools.php?func=livebeat`)
`DaemonReportController`, `resources/views/admin/reports/daemon.blade.php`, route `admin.reports.daemon` (`/admin/reports/daemon`).
- OLD Livebeat is an internal staff report in `web/steve/mynews/toolslib.php` `livebeat()` (L7471). The "SMSG Daemon name" column = `suppliername` + priority bucket, where bucket = `FLOOR(MOD(smsgdaemonid,1000)/100)*100` (0/100/…/900; OLD route map `toolslib.php:1942`: 3002=NexmoDirect, 8029=NexmoGlobal, 7002=mbloxDirect).
- New report groups `smsg_log` by **Client + Status + Route + SMSG-Daemon** (like OLD), columns: Client (`daemonpriority`+contact−business, urldecoded; priority `0` hidden), Volume, Delivered/Non-Del/Other (% of volume), Net Profit (£ + per-SMS pence), Route (`SUBSTRING(chargetype,3,1)`+route, `/nonUK-<dialcode>`), SMSG Daemon, Status (mapped `1. pending`…`7. fail`), **Age** (`NOW − MIN(dosendtime)` as `Xh Ym Zs`, un-sent statuses only), Gross/HLR/Cost. Plus a **Summary** card (Submitted, Delivered %, Revenue, Cost, Profit, per-submitted/per-delivered, status-count chips) and **auto-refresh** selector (Off/15s/30s/60s, default 15s = OLD's `$refreshrate`, localStorage-persisted, filter-preserving reload).
- Uses `admin.layouts.app` (NOT modern-app) so it shares the real metisMenu sidebar. Multi-select **User(s)** filter via select2 + `LegacyCustomerList::getLegacyCustomers()` (`user[]` → `WHERE userref IN (...)`).

### "Other Links" sidebar menu + `can_view_daemon_report` permission
`admin/layouts/app.blade.php` (metisMenu `has-arrow` submenu, CSS overrides so the open/`.mm-active`/`:focus` parent isn't the plugin's default blue and matches sibling 45px height).
- New permission `can_view_daemon_report` — migrations `2026_07_17_120000` (add col) + `2026_07_17_130000` (**default 1** + backfill all existing admins) so **every admin auto-gets it** (present and future; new-admin create form pre-checks it). Registered in `AdminPermission` (fillable/casts/`getPermissionFields`), `User::getPermissionsWithLabels()` under new group **`other_links_permissions` → "Livebeat"**, both session-loaders (`AdminAuthController`, `AdminUsersController`), and create/edit permission forms. Reusable middleware alias **`admin.permission`** → `CheckAdminPermission` added in `bootstrap/app.php`.

### `itagg_module_*` table-name CASE mismatch (prod-only "table doesn't exist")
OLD platform created module tables **CamelCase**; new code queried them lowercase. Invisible on Windows dev (case-insensitive MySQL) but fatal on Linux prod (case-sensitive). Fixed all refs in `SMSController`, `InboundSmsProcessor`, `Api/Mobile/SendSmsController` to the exact prod casing: **`itagg_module_SMSForwarder`, `itagg_module_Subscription`, `itagg_module_WAPPushResponder`, `itagg_module_BusinessCard`** (CamelCase works in both envs). Note: `itagg_module_restrict` is a column ALIAS not a table; `itagg_module_voting*` are genuinely lowercase in prod.

### Inbound SMS modules — audit + fixes (`InboundSmsProcessor`, bitfield `executeModule`)
Module bit map (`itagg_module` table): 1 smsResponder, 2 Forwarder(Email/URL), 4 SMSForwarder, 8 BusinessCard, 16 Subscription, 32 WAPPushResponder, **256 Voting**, 2048 EmailForwarder.
- **Voting (bit 256) — was completely unimplemented.** Built `executeVoting`/`compileVotingSbm`/`sendVotingResponse` + wired `case 256`. Mirrors OLD `VotingBase`: campaign load (strict subkeyword NULL/exact) → suspended check → empty-word SBM tally (top-3 `word : N vote(s)`) → `itagg_module_voting_word` match → success/failure `itagg_module_voting_response` (`%v`→word, urldecoded) → date-range gate → `frequency='once'` dedupe → `itagg_module_voting_record` insertOrIgnore → queued response.
- **Subscription (bit 16) — was targeting invented `itagg_module_Subscription_data`/`_members` (never existed).** Members actually live in the legacy CP join **`cp_users_groups → cp_group_addressbook → cp_users_addressbook → itagg_mobiledetail`** (link key: `itagg_module_Subscription.group_name` = `cp_users_groups.name` + `user_bigid`). Rewrote START/STOP to those tables (`resolveSubscriptionGroupId`, `ensureAddressbookEntry` with Ofcom `net_id`), strict subkeyword scoping, `fail_response` on max. Group-send (`SendSmsController` mobile contacts) rewritten to the same join. `saveSubscription` now writes `group_name`+`subkeyword` and creates the `cp_users_groups` row.
- **SMS Forwarder** — display query was filtering by shortcode ONLY (showed another keyword's number); now filters `keyword+subkeyword+smsshortcode_number`. Save validation relaxed UK/+91 → international (`\+?[0-9]{10,20}`), strips internal whitespace, stores cleaned list.
- **Email/URL Forwarder** — `forwardToUrl` sends the **real `$network`** (was hardcoded `99`) + `message_id` when present; `forwarded_email_timestamp` written; **module 2048 now also forwards to URL** (matches OLD `EmailForwarderBase`→`ForwarderBase` delegation).
- **KNOWN GAPS (not yet done — need decision):** all inbound handlers insert `smsg_log` with `requested_route=0`/`cost=0` (daemon fills) instead of OLD `itagg_send_normal_sms` billing; **Business Card** controller save/display are stubs AND the Blade view is a fieldless skeleton (inbound sender is a faithful port that works on existing prod config rows, but no config UI); SMS-Responder mobile-update/advertise/premium-route; WAP-Push binary SI encoding (currently plain `"Title: URL"`); per-client whitelabel email/URL param variants.

### URL-decode display fixes
Names stored URL-encoded (legacy). Fixed `str_replace('+',' ',…)` (didn't decode `%28`/`%2F`) → `urldecode()`: admin header/avatar (`app.blade.php` `$adminSession['name']`, `$user_contactname`) and customers list Contact/Business Name (`admin/user/index.blade.php`, main + sub rows). Display-only — DB stays encoded.

## Recent Fixes (July 2026 — resend / encoding / reporting)

### Re-queued pending SMS silently discarded — `£`/UTF-8 publish bug (ROOT CAUSE FOUND & FIXED)
**Symptom:** `sms:resend-pending` logged `published: N`, the worker consumed the messages, but they never reached Nexmo — DB rows stayed `timesent='00000000000000'`, and `storage/logs/<date>/rabbitmq/sms.outbound.log` showed `PROCESSING … {"payload":"unparseable"}` → `ACK — processed OK, removed from queue`. Plain-ASCII messages (ids 1–7) sent fine; anything containing `£` vanished. Same bug hit production's ~19k stuck backlog (mostly financial `£` SMS).
- **Cause:** legacy `smsg_log.text` stores `£` as **Latin-1 `%A3`**. Resend does `urldecode()` → raw byte `0xA3` = **invalid UTF-8**. `RabbitMQService::publishToQueue()` then did `json_encode($data)` which **returns `false` on malformed UTF-8** → an **empty body** (`""`) was published. The consumer (`ProcessSmsQueue::processSmsMessage`, guard `!is_array($data)` at ~L259) `json_decode("")` → `null` → not-array → logged `unparseable` and **ACKed/discarded WITHOUT sending**. SMPP being binary, the OLD system never hit this (no JSON hop).
- **Fix (`app/Services/Queue/RabbitMQService.php` `publishToQueue()`):** if `json_encode` returns `false`, run new `deepUtf8()` helper (recursive `mb_convert_encoding(..., 'UTF-8', 'Windows-1252')` on any string failing `mb_check_encoding`) and retry; and **refuse to publish an empty/`null` body** (log `publishToQueue: refusing to publish empty/invalid body` + return false) so a message is never again silently lost. `£` 0xA3 → UTF-8 `0xC2 0xA3`, which the new `app/Helpers/GsmCharacterConverter.php` (L29 maps `"\xC2\xA3"`→GSM `£`) and OLD `GsmEncoder::utf8_to_gsm0338` both expect. Verified: body 0 bytes → 595 bytes, valid JSON, `£` preserved.
- **How the OLD system handled it** (`smsg_2send_mblox_fire.inc` L311/325, `gsmencoder.class.php`, `smssend.inc` L858-892): store/expect text as **UTF-8** url-encoded (`£`=`%C2%A3`); on send `urldecode()` → `GsmEncoder::utf8_to_gsm0338()` → **GSM 03.38 bytes** straight into binary `submit_sm` (no JSON). Its real defense was **normalizing encoding at STORE time** (`smssend.inc` L888 `str_replace` of `%C2` etc.; documents the identical "Chris Sebire `£` not utf8" bug at L890). Recommended follow-up (not yet done): UTF-8-normalize `text` at write-time into `smsg_log` so the DB holds clean UTF-8; the publish-time `deepUtf8()` guard remains the safety net for already-migrated rows.
- **`ResendPendingSms` note:** must leave `sentstatus` as-is (`pending`/`''`) — only stamp `sentstatustext`. `'requeued'` is NOT a valid `sentstatus` ENUM value (non-strict MySQL silently stored `''`), and `'firing'` was explicitly rejected by the user. The `timesent<>'00000000000000'` filter (not `sentstatus`) is what guards against double-send. Local worker startup: `start-local-workers.bat` → "SMS Outbound Worker" window runs `sms:process-queue --queue=sms.outbound --provider=nexmo`; a live worker shows as `consumers:1` on the queue (2 MB `php.exe` = dead stub).

### Livebeat / daemon report showed 0 with a date filter — `dosendtime` → `dayofyear`
`DaemonReportController::index()`. Report defaulted to today but `?from_date=…&to_date=…` returned all-0 counts.
- **Cause:** filter used `l.dosendtime BETWEEN <from>000000 AND <to>235959`. `dosendtime` is the **scheduled** send time — `0`/blank for send-now messages — so it dropped almost every sent row.
- **Fix:** filter on **`l.dayofyear BETWEEN ? AND ?`** (YYYYMMDD) — exactly what OLD Livebeat used (`toolslib.php:7687`; it had **abandoned** the `dosendtime` filter, commented out at L7686). `dosendtime` is still correctly used for the *queued* status label (`dosendtime > now`) and the **Age** column (`MIN(dosendtime)`), just not for the date range.

### Reporting query reference (sent / delivered / rate) — for Vonage volume questions
Definitions confirmed from code: **sent** = `sentstatus='ok'` (`FetchNexmoDeliveryReportsDaily.php:86` "actually went out"); **delivered** (final DLR) = `deliverystatus2='Delivered'`; final DLR value set = `Delivered` / `Non Delivered` / `Unknown` / `Lost Notification`, empty/`NULL` = **awaiting DLR** (`DatabaseTidy.php`). "Delivered = any DLR received" variant = `deliverystatus2 IS NOT NULL AND deliverystatus2<>''`. **Customer name** = join `smsg_log.userref = users.bigid`, name in `users.busname`/`users.contactname` (URL-encoded — MySQL has no `urldecode`, use nested `REPLACE` for `+`/`%28`/`%29`/… or decode in PHP). API traffic = `initiator='ExternalAPI'`. **Rate:** `timesubmitted`/`timesent` are `YYYYMMDDHHMMSS` — `GROUP BY timesubmitted` = per-second (TPS, what Vonage throttles on, ESME_RTHROTTLED/err 88); `GROUP BY LEFT(timesubmitted,12)` = per-minute. Date partition column = `dayofyear` (`YYYYMMDD`), NOT `dosendtime`.

## Recent Fixes (August 2026 — chargetype parity + Arun-API investigation)

### Shared database, two codebases (IMPORTANT ARCHITECTURE)
OLD (core PHP, `D:\cladue\smsexpert_oldsystem` under `sites/site7/itagg.com`) and NEW (this Laravel app) run against the **same production database**. Only the code differs. Consequences: the OLD nightly crons still operate on the shared tables; billing/reconciliation must be a **single source of truth**, not duplicated per system.

### `smsg_log.chargetype` is a COMBINED code (user + route), not the raw user value
- OLD stores it **inline at send** in the "firing" UPDATE (`smsg_2send_body.inc:2399`), computed at `:2374-2388`:
  - `users.chargetype1/2/3` (per route slot; values only `pps`/`ppd`, admin-set, **default `pps`** in `accountmanager/index.php`) = the **USER** chargetype.
  - route chargetype is **hardcoded per aggregator** in the OLD send daemon: `csn3`/`mblox`/`onesixty` = `ppd`; `csn0`/`csn1`/`csn2` = `pps`.
  - Matrix → stored code: `pps+pps=pps`, `pps+ppd=ppsd`, `ppd+pps=ppds` (UK/`44` only; non-UK=`pps`, legacy SCP20160819), `ppd+ppd=ppd`.
- **Billing semantics** (nightly reconciler `steve/test/dbtidy.php`, cron **02:30**, which only READS chargetype): the **USER letter decides the CUSTOMER charge on non-delivery** — `pps`/`ppsd` = customer **charged regardless** (no refund); `ppds`/`ppd` = customer **refunded** (`smsg_server1_sent += (0 - userprice)`). The `d`/route half only affects **our supplier cost** (e.g. `ppsd`: on non-delivery `costprice→0`, customer still charged → extra profit). Balance formula (both systems): `smsg_wallet - smsg_server1_sent - smsg_server2_sent`.
- **dbtidy filters `migration_flag IN (NULL,'','old')`** → it SKIPS new-system rows. That's why new rows were never reconciled.

### NEW system: `chargetype` was stored RAW + never acted on — now stamped like OLD
- NEW send paths wrote the **raw** `users.chargetype1` (`'pps'`) into `smsg_log.chargetype` and **never read it for billing** (`DeliveryStatusService::handleWalletRefund()` intentionally no-ops; charge happens at send via `smsg_server1_sent` increment, no refund on DLR non-delivery).
- **Fix (this session):**
  - `app/Support/ChargeType.php` — `combined($userChargeType, $suppliername, $countryCode)`, byte-identical to OLD matrix. Route map: `sinch`/`mblox`/`csn3`/`onesixty`→`ppd`, else (`Vonage SMPP`/`nexmo`/`csn0-2`/empty)→`pps`.
  - `app/Console/Commands/StampChargeType.php` — `sms:stamp-chargetype {--date=YYYYMMDD}{--apply}{--limit=}`. **Dry-run by default.** Computes the combined code from each row's already-stored `suppliername` + `users.chargetype1` + `countrydialcode` and writes it back (covers ALL send paths from one place — new send paths write `suppliername` in ~15 scattered spots, so a central stamp is safer than patching each). Processes **yesterday+today** when no `--date`. Relabels `chargetype` ONLY — never touches wallet/cost/price.
  - Scheduled **daily 02:00** (`routes/console.php`, guarded by `isCronEnabled('sms:stamp-chargetype')`) — the OLD system has NO chargetype cron (it's inline at send); the new system stamps centrally once nightly right before the 02:30 reconciler, its only consumer. Registered in `Kernel.php`; monitor entry in `MonitorController.php`.
- **Still TODO (OLD side, one line):** to actually reconcile new rows, relax the `migration_flag` filter in `dbtidy.php` so it also processes `migration_flag='new'`. NOT done (OLD prod code, user's call). Do NOT build a second Laravel reconciler — same DB, one job.

### Arun Estates — why the NEW API delivers worse than OLD (root cause: carrier, not data)
- Provider is chosen by **sender ID**, not route: `SMSController::getOperatorForSender()` returns `'nexmo'` (=Vonage) by default for alphanumeric senders (Arun's `Wards`/`Cubitt West`/… aren't in `smsshortcodes`). **Route 7002 is never consulted for carrier.**
- OLD route 7002 → **`csn3` aggregator** (per-delivered billing); NEW → **direct Vonage** (per-sent billing). **No CSN/`csn3` sending service exists in NEW** — only Vonage (`SMPPService`) and Sinch (`SinchSmppService`, `eu.smpp.api.sinch.com:3601`, live). mBlox config is commented out; Sinch is its successor.
- Dump `28-07-2026` (14,264 rows; `migration_flag` old=13,500/new=764): OLD **95% Delivered / 3% Non-Delivered** vs NEW **57% Delivered / 42% Non-Delivered** (`delivery_reason 27` = Vonage `REJECTD` "Barred/Permanent Phone"). Vonage bills per-sent → NEW margin **negative** (`costprice 0.0338` vs `userprice 0.0311`); OLD `costprice=0` on the exact 608 non-delivered rows (csn3 per-delivered). Column diffs `requested_routetag` (7 vs 7002) and `sms_type` (NULL vs sms) are **cosmetic**, NOT the cause.
- Ports of stakeholder dumps into Excel corrupt long numerics (phone/date → sci-notation `2.02607E+13`) — re-export via `mysql --batch --raw`, don't open the raw CSV in Excel. Comparison workbook builder used PhpSpreadsheet (`vendor/phpoffice/phpspreadsheet`).

### UCS2/emoji multipart send — still failing IN PRODUCTION (fix not deployed)
Prod `smpp/vonage.log` (30 Jul) shows ~176 `Failed to send concatenated SMS part` `error_code:1` (ESME_RINVMSGLEN), mostly part 5/6 → 22 dead-letters. This is the **char-vs-octet split bug already fixed in code** (`SMPPService`/`SinchSmppService` UCS2 → `str_split($utf16be, 132)` octet split, GSM 7-bit → septet count ≤153). Logs prove the fix is **not yet deployed to prod**. Dead-letter alert shows `"error":null` because the consumer doesn't propagate the SMPP reason into the payload (offered fix, not yet done).

### `users.customer_type` parity — OLD stores 'Postpaid' or EMPTY '' (never 'Prepaid')
- **OLD convention (case-sensitive, `== "Postpaid"` checks at `gajkdhgfajdhgfasjgfasjdhfgahdgfa_live.php:5193/5681`):** Postpaid = exactly `'Postpaid'`; Prepaid = **empty string `''`** (convert-to-prepaid `:5830` sets `''`; the word 'Prepaid' is never stored). OLD's Add-Record Prepaid radio (`:8031`) has a `value=="Prepaid"` typo that posts garbage — left as-is (OLD untouched by user instruction); all readers treat non-'Postpaid' as prepaid anyway. `'admin'` also appears in this column for staff rows — never mass-normalize it.
- **BUG FIXED (root cause of "changed to Post pay in new admin, old admin shows Prepaid"):** `AddCustomerController@update` did an unguarded `$user->customer_type = $request->customer_type;` and **`showo.blade.php` posts to the same `customers.update` route WITHOUT the field** → every save from that page wiped the value to NULL. Now guarded with `$request->filled()` + normalized to `'Postpaid'`/`''`. Same normalization in `@store`; `helpers.php getCustomerType()` changed `'PrePaid'` → `''`.
- `bulkMigrate` intentionally does NOT touch customer_type (shared users table — value carries over).
- Displays already treat anything ≠ 'postpaid' (case-insensitive) as Prepaid: profile-tab select, user/index badge. Reports' `where('customer_type','Postpaid')` rely on MySQL's case-insensitive collation.
- **Pending one-time cleanup SQL (user to run):** normalize `LOWER(TRIM())='postpaid'` → 'Postpaid'; NULL/'prepaid' variants → '' — but ONLY known variants, never 'admin'.

### Claude Code dev-environment permissions (stop per-session prompts)
`.claude/settings.local.json` (personal, gitignored) holds the pre-approved permission allow-list. It previously listed only `Bash(...)` command patterns, so every reopened session **re-prompted for the file/search tools**. Added blanket allows for **`Edit`, `Write`, `Read`, `Grep`, `Glob`** plus common shell file utilities (`cat`/`head`/`tail`/`wc`/`sed`/`grep`/`stat`/`mkdir`/`cp`/`mv`/`sort`/`uniq`/`awk`/`cut`), `Bash(git:*)`, and `PowerShell(& 'C:\\xampp\\php\\php.exe':*)`. If prompts return, check this file is valid JSON (a syntax error makes Claude ignore the whole file).
(Aug 2026) The user also opens sessions from `D:\nse` — its `D:\nse\.claude\settings.local.json` now has the same blanket file-tool allows, generalized PowerShell prefix rules (`py *`, `$edge = *` for headless-Edge screenshots, `Get-* *`, php lint, etc.), and `additionalDirectories: ["D:\\"]` so cross-project file access (this repo included) doesn't prompt.
(10 Aug 2026) THIS repo's `.claude/settings.local.json` also got `additionalDirectories: ["D:\\"]`, so sessions opened here no longer prompt when reading the OLD system (`D:\cladue\smsexpert_oldsystem`), SQL dumps at `D:\`, or other project folders.
(Aug 2026, this repo) `.claude/settings.local.json` here was likewise given blanket **`Edit`/`Write`/`Read`/`Grep`/`Glob`** allows plus shell file utilities so reopened sessions stop re-prompting for file operations. If prompts return, first check the JSON is valid (a syntax error makes Claude silently ignore the whole file).

## Recent Fixes (August 2026 — DLR pipeline, SMPP submit_sm parity, Vonage Reports-API cost)

### Vonage DLR not updating over SMPP → paid Reports API was the ONLY working path (ROOT CAUSE)
Production sent fine but `deliverystatus2` only updated via the **paid Vonage Reports API** (`FetchNexmoDeliveryReports` → `https://api.nexmo.com/v2/reports/records`, billed on account **`c980a5f2`**), NOT over SMPP.
- **Why:** senders bind **transmitter-only** (`SMPP_SEND_TRANSMITTER_ONLY=true`) so they don't receive DLRs; and the **Vonage DLR receiver banks** (`sms-expert-smpp-dlr-bank-a0…j0` in `laravel-rabbitmq.conf`) were **all `autostart=false`** → nothing caught `deliver_sm`. Prod `smpp/vonage.log` confirmed: `bind_transmitter` only, **0 `deliver_sm`**. Sinch DLR receiver *was* running (Sinch fine).
- **The env is correct** (`smsexpert-env-production.txt`): `SMPP_BANKS_ENABLED=true`, `DLR_USE_BUFFER=true`, `SMS_ASYNC_PUBLISH=true`, `SMPP_SYSTEM_ID=c980a5f2`. So the gap was purely the supervisor `autostart=false`.
- **Fix applied:** `laravel-rabbitmq.conf` — set all 10 Vonage DLR banks `autostart=true`, with a comment block on rollout order + the bind-math warning. **Reports-API programs (`nexmo_process-delivery-queue`, `reports-consume`) left ON as backup** — disable them (STEP 2) only AFTER confirming SMPP DLRs land (`deliver_sm` in `smpp-dlr-bank-*.log`, `smsg_receipt_buffer_new` filling, `deliverystatus2` updating). Not deployed by me — user applies on server.
- **DLR path (buffer model):** SMPP receiver banks buffer each DLR into `smsg_receipt_buffer_new` (fast insert) → `dlr:process-buffer` (prod `dlr_process_buffer`, autostart=true) matches indexed `onesixty_suppliermsgref` + updates `smsg_log`. Mirrors OLD `daemon_dreceipt_inbound_buffer.php`.

### ⚠️ Bind-count math (Vonage per-system_id limit) — OLD vs NEW
Vonage's concurrent-bind cap is **per system_id**, and NEW stacks everything on ONE account `c980a5f2`.
- **NEW:** 5 senders (`sms_process_queue` numprocs=5) + 10 DLR banks = **15 binds on `c980a5f2`** → risks `ESME_RALYBND` if the cap is <15. **Local `start-local-workers.bat` proves 11 binds work** (1 sender + 10 banks) — DLRs update correctly locally, which is what validates enabling the banks.
- **OLD (mBlox, `thisserver_details.inc`):** ~**10 send** (transmitter, banks a–j, 2 per systemType, partitioned `seq_id_range`) + ~**5 DLR** (`itagg_daemon_smpp_dlr_multi.php`, one bind_receiver **per systemType** `smppProd1..5`) ≈ 15 binds, but **spread across 5 systemTypes (~3 each)** — never concentrated on one account. That's how OLD avoided the cap.
- **Guidance:** trim to an OLD-like shape (fewer senders via `numprocs`, and fewer than 10 DLR banks — OLD only ran 5; one receiver already suffices) OR get Vonage to raise the per-`c980a5f2` bind ceiling OR split across system_ids. Bank config: `config/smpp_banks.php` (a0..j0, all default to `SMPP_SYSTEM_ID`, partitioned seq ranges, 3 hosts).

### DLR status map — `FAILED` was unmapped → ~1200/day mislabeled `Unknown` (FIXED)
`DeliveryStatusService::mapToDeliveryStatus()`: the SMPP-status map was **missing `FAILED`** (Vonage sends `stat:FAILED`), and the fall-through default returns `'Unknown'`. So Vonage `FAILED` DLRs (real failures) were bucketed as `Unknown` (and displayed "In Transit"). OLD (`getXmlStatusForReasonCode`, keyed on numeric reason codes only, via csn3) produced `Unknown` solely for reason code 6 — hence ~0. **Fix:** added `'FAILED' => 'Non Delivered'` and a `Log::warning` at the default branch so any genuinely-unmapped Vonage status is now visible instead of silently `Unknown`.

### SMPP `submit_sm` OLD-parity fixes (`SMPPService.php`) — NOT yet deployed to prod
Field-by-field audit vs OLD `smppclient-csn.class.php` / `smsg_2send_csn_smpp_fire.inc`. Aligned three real differences to OLD:
- **`validity_period`** — was `now+24h`, now **empty** (`formatValidityPeriod()` returns `''`). OLD always sent empty → SMSC default (48-72h) retry window. The 24h cap was **expiring** messages to temporarily-unreachable handsets early (extra Non-Delivered). **Highest-impact change.**
- **numeric-sender `source_addr_npi`** — was `1` (E.164), now **`0`** (Unknown) to match OLD (both single + concat PDU builders). Alphanumeric senders were already identical (`5`/`0`).
- **concat reference** — was `sequenceNumber % 256` (collision-prone across parallel workers), now a **per-worker 6-value slice** of the 0-255 space keyed off `bankKey` (added props `$concatRefSlotBase`, `$concatRefCounter`), mirroring OLD `getCsmsReference` scp20160621. Verified disjoint slices per worker.
- Sinch (`SinchSmppService`): validity already null (OK); source TON/NPI is fixed-alphanumeric (different design); concat ref uses `mt_rand(1,255)` — offered to align, not yet done.
- **Deploy note:** these are wire-level `submit_sm` changes — take effect only once `SMPPService.php` is deployed (same as the still-undeployed UCS2 132-octet split fix).

### Vonage API cost inventory (which endpoints are metered)
- **PAID:** `/v2/reports/records` (Reports API — `nexmo:fetch-delivery-reports*`, polls **every minute** → main avoidable cost, redundant once SMPP DLRs work); `/v1/messages` POST (Messages API — **WhatsApp only**, legitimate per-message cost); `/v1/messages/{uuid}` GET (`FetchPendingDlrs`, per-message status lookup — **not scheduled**, manual only).
- **FREE:** `/account/get-pricing/outbound/sms` + `get-full-pricing` (daily pricing crons); SMPP DLRs over the socket. SMS *sending* is SMPP (no per-SMS REST cost).
- Reports-API auth = `smpp.connections.vonage.system_id`/`password` = the SMPP account (`c980a5f2`).
