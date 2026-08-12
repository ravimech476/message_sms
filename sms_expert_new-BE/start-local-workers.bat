@echo off
REM ============================================================================
REM Start all SMS Expert background workers on local Windows.
REM
REM Mirrors the production supervisor.conf programs — but each one runs in its
REM own visible CMD window so you can see logs live and close individual workers.
REM
REM Usage:
REM   1. Double-click start-local-workers.bat (or run from PowerShell)
REM   2. Each worker opens its own window with a descriptive title
REM   3. Close a window or press Ctrl+C in it to stop that one worker
REM   4. Run stop-local-workers.bat (or close all CMD windows) to stop everything
REM
REM Prerequisites:
REM   - PHP on PATH (php -v should work)
REM   - composer install already run
REM   - .env points at a reachable MySQL + RabbitMQ
REM   - storage\logs\ exists (Laravel creates it on first artisan call)
REM ============================================================================

cd /d %~dp0

REM ----------------------------------------------------------------------------
REM PHP executable. Defaults to XAMPP at C:\xampp\php\php.exe.
REM Change this if your PHP lives somewhere else (e.g. Laragon, manual install).
REM ----------------------------------------------------------------------------
set PHP_EXE=C:\xampp\php\php.exe

echo.
echo === SMS Expert Local Worker Startup ===
echo Working dir: %CD%
echo PHP exe:     %PHP_EXE%
echo.

REM --- Sanity checks --------------------------------------------------------

if not exist "%PHP_EXE%" (
    echo [ERROR] PHP not found at %PHP_EXE%
    echo         Edit start-local-workers.bat and update the PHP_EXE line at the top.
    pause
    exit /b 1
)

if not exist artisan (
    echo [ERROR] No artisan file in %CD%
    echo         This script must live in the project root next to artisan.
    pause
    exit /b 1
)

if not exist .env (
    echo [ERROR] No .env file. Copy .env.example to .env and configure it.
    pause
    exit /b 1
)

if not exist storage\logs (
    mkdir storage\logs
    echo Created storage\logs
)

echo PHP version:
"%PHP_EXE%" -v | findstr /B "PHP"
echo.

REM --- Clear caches so .env changes take effect ----------------------------

echo Clearing config cache...
"%PHP_EXE%" artisan config:clear
echo.

REM --- Start each worker in its own labeled CMD window ---------------------

echo Starting workers (one window each)...
echo.

REM ---- DLR Receivers — one window per bank (a0..j0) -----------------------
REM REQUIREMENT: SMPP_BANKS_ENABLED=true in .env AND Vonage must have
REM multi-bind / concurrent-session mode enabled on the SMPP account.
REM Without that, only bank a0 will bind; b0..j0 will be rejected with
REM ESME_RALYBND (0x05 "Already in bound state") and their windows will die
REM in restart loops. If that's the case, leave only the a0 line uncommented.
REM For legacy single-bind mode (no banks at all), comment all 10 below and
REM uncomment the fallback line at the bottom of this block.
start "DLR Receiver bank=a0" cmd /k "title DLR Receiver bank=a0 && "%PHP_EXE%" artisan smpp:dlr-receiver --bank=a0"
start "DLR Receiver bank=b0" cmd /k "title DLR Receiver bank=b0 && "%PHP_EXE%" artisan smpp:dlr-receiver --bank=b0"
start "DLR Receiver bank=c0" cmd /k "title DLR Receiver bank=c0 && "%PHP_EXE%" artisan smpp:dlr-receiver --bank=c0"
start "DLR Receiver bank=d0" cmd /k "title DLR Receiver bank=d0 && "%PHP_EXE%" artisan smpp:dlr-receiver --bank=d0"
start "DLR Receiver bank=e0" cmd /k "title DLR Receiver bank=e0 && "%PHP_EXE%" artisan smpp:dlr-receiver --bank=e0"
start "DLR Receiver bank=f0" cmd /k "title DLR Receiver bank=f0 && "%PHP_EXE%" artisan smpp:dlr-receiver --bank=f0"
start "DLR Receiver bank=g0" cmd /k "title DLR Receiver bank=g0 && "%PHP_EXE%" artisan smpp:dlr-receiver --bank=g0"
start "DLR Receiver bank=h0" cmd /k "title DLR Receiver bank=h0 && "%PHP_EXE%" artisan smpp:dlr-receiver --bank=h0"
start "DLR Receiver bank=i0" cmd /k "title DLR Receiver bank=i0 && "%PHP_EXE%" artisan smpp:dlr-receiver --bank=i0"
start "DLR Receiver bank=j0" cmd /k "title DLR Receiver bank=j0 && "%PHP_EXE%" artisan smpp:dlr-receiver --bank=j0"
REM Legacy single-bind fallback (uncomment ONLY if SMPP_BANKS_ENABLED=false):
REM start "DLR Receiver (single-bind)" cmd /k "title DLR Receiver (single-bind) && "%PHP_EXE%" artisan smpp:dlr-receiver"

REM ---- Sinch DLR Receivers — DISABLED LOCALLY -----------------------------
REM Sinch modern SMPP gateway documented limit:
REM   "Default limit of two parallel connections per host/system_id."
REM   (developers.sinch.com — SMS SMPP Connectivity)
REM
REM Production owns BOTH Sinch SMPP connection slots on system_id SMSEx_RA
REM (typically: one transceiver for sms-expert-smpp-consumer sending, plus
REM whatever else is bound). So local cannot bind ANY Sinch SMPP session —
REM not a DLR receiver, not a transceiver for sending — without colliding
REM with production. Status 5 ESME_RALYBND on every attempt.
REM
REM Local Sinch DLR is therefore taken via the HTTPS webhook path only:
REM   - inbound webhook hits /api/webhook/inbound-sms and /webhook/sinch-dlr
REM   - the "Webhook DLR Consumer" window below processes the queue
REM
REM ALL Sinch SMPP receivers commented out below. Uncomment ONLY when:
REM   (a) production Sinch processes are stopped, OR
REM   (b) Sinch issues a separate dev system_id (e.g. SMSEx_RA_dev), OR
REM   (c) Sinch raises the per-host connection limit on the account.
REM
REM start "Sinch DLR bank=a" cmd /k "title Sinch DLR bank=a && "%PHP_EXE%" artisan sinch:dlr-receiver --bank=a"
REM start "Sinch DLR bank=b" cmd /k "title Sinch DLR bank=b && "%PHP_EXE%" artisan sinch:dlr-receiver --bank=b"
REM start "Sinch DLR bank=c" cmd /k "title Sinch DLR bank=c && "%PHP_EXE%" artisan sinch:dlr-receiver --bank=c"
REM start "Sinch DLR bank=d" cmd /k "title Sinch DLR bank=d && "%PHP_EXE%" artisan sinch:dlr-receiver --bank=d"
REM start "Sinch DLR bank=e" cmd /k "title Sinch DLR bank=e && "%PHP_EXE%" artisan sinch:dlr-receiver --bank=e"
REM start "Sinch DLR bank=f" cmd /k "title Sinch DLR bank=f && "%PHP_EXE%" artisan sinch:dlr-receiver --bank=f"
REM start "Sinch DLR bank=g" cmd /k "title Sinch DLR bank=g && "%PHP_EXE%" artisan sinch:dlr-receiver --bank=g"
REM start "Sinch DLR bank=h" cmd /k "title Sinch DLR bank=h && "%PHP_EXE%" artisan sinch:dlr-receiver --bank=h"
REM start "Sinch DLR bank=i" cmd /k "title Sinch DLR bank=i && "%PHP_EXE%" artisan sinch:dlr-receiver --bank=i"
REM start "Sinch DLR bank=j" cmd /k "title Sinch DLR bank=j && "%PHP_EXE%" artisan sinch:dlr-receiver --bank=j"
REM start "Sinch DLR (single-bind)" cmd /k "title Sinch DLR (single-bind) && "%PHP_EXE%" artisan sinch:dlr-receiver"

REM Sinch DLR Receiver — single bind_receiver that listens for DLRs over SMPP.
REM Run ONLY ONE (Sinch caps at 2 connections per host/system_id). Keep this on
REM while testing Sinch so 'pending' deliveries flip to Delivered.
start "Sinch DLR Receiver"     cmd /k "title Sinch DLR Receiver && %PHP_EXE% artisan sinch:dlr-receiver"

REM ---- DLR Buffer Processor — OLD-SYSTEM table model (no RabbitMQ) ---------------
REM Drains the smsg_receipt_buffer_new TABLE: matches the INDEXED onesixty_suppliermsgref
REM and updates smsg_log.deliverystatus2 — the exact daemon_dreceipt_inbound_buffer.php
REM flow. The DLR receivers above buffer each DLR (fast insert); THIS worker does the
REM match + update. Run ONE instance only.
REM REQUIRES in .env:  DLR_USE_BUFFER=true  (so receivers write to the buffer table).
start "DLR Buffer Processor"   cmd /k "title DLR Buffer Processor && %PHP_EXE% artisan dlr:process-buffer --continuous --sleep=2"

REM ---- Outbound SMS worker — consumes sms.outbound and SENDS via SMPP ----------
REM REQUIRED when SMS_ASYNC_PUBLISH=true: the API/dashboard publishes each SMS to
REM sms.outbound and returns immediately; THIS worker does the actual SMPP send.
REM Without it, queued sends never leave and dead-letter into sms.dead after 24h.
REM --provider=nexmo -> bind ONLY Vonage/Nexmo (no Sinch bind), so it never collides
REM with the "Sinch DLR Receiver" above (Sinch caps at 2 per system_id -> ESME_RALYBND).
REM Set SMPP_SEND_TRANSMITTER_ONLY=true in .env so this worker binds send-only (fast,
REM no DLR starvation) — DLRs are handled by the receivers above.
start "SMS Outbound Worker"    cmd /k "title SMS Outbound Worker && %PHP_EXE% artisan sms:process-queue --queue=sms.outbound --provider=nexmo"

REM ---- Part 3: horizontal scaling — extra outbound workers -----------------------
REM RabbitMQ auto-balances sms.outbound across all consumers, so you just run more.
REM EACH extra worker opens its OWN Vonage bind — enable these ONLY after confirming
REM your Vonage account allows that many concurrent binds, or they die on ESME_RALYBND.
REM start "SMS Outbound Worker 2"  cmd /k "title SMS Outbound Worker 2 && %PHP_EXE% artisan sms:process-queue --queue=sms.outbound --provider=nexmo"
REM start "SMS Outbound Worker 3"  cmd /k "title SMS Outbound Worker 3 && %PHP_EXE% artisan sms:process-queue --queue=sms.outbound --provider=nexmo"

REM Inbound SMS queue consumer (RabbitMQ -> InboundSmsProcessor -> itagg_incominglog)
start "Inbound SMS Consumer"   cmd /k "title Inbound SMS Consumer && %PHP_EXE% artisan queue:inbound-sms"

REM Nexmo HTTPS DLR continuous puller
start "Nexmo DLR (HTTPS)"      cmd /k "title Nexmo DLR (HTTPS) && %PHP_EXE% artisan nexmo:process-delivery-queue --continuous"

REM Webhook DLR consumer (Vonage / Sinch HTTPS DLR callback -> RabbitMQ -> DB)
start "Webhook DLR Consumer"   cmd /k "title Webhook DLR Consumer && %PHP_EXE% artisan queue:webhook --type=dlr"

REM Webhook inbound consumer (HTTPS MO -> RabbitMQ -> DB)
start "Webhook Inbound"        cmd /k "title Webhook Inbound && %PHP_EXE% artisan queue:webhook --type=inbound"

REM Campaign file consumer
start "Campaign Consumer"      cmd /k "title Campaign Consumer && %PHP_EXE% artisan campaign:consume"

REM Email queue consumer
start "Email Consumer"         cmd /k "title Email Consumer && %PHP_EXE% artisan rabbitmq:consume-emails"

REM Push notification queue
start "Push Notifications"     cmd /k "title Push Notifications && %PHP_EXE% artisan queue:push-notifications"

REM SMPP monitor (enquire-link health checks)
start "SMPP Monitor"           cmd /k "title SMPP Monitor && %PHP_EXE% artisan smpp:monitor"

REM Campaign file migration consumer
start "Campaign File Mig."     cmd /k "title Campaign File Mig. && %PHP_EXE% artisan rabbitmq:consume-campaign-file-migration"

REM Webhook URL update consumer (used by bulk migrate)
start "Webhook URL Update"     cmd /k "title Webhook URL Update && %PHP_EXE% artisan rabbitmq:consume-webhook-update --timeout=60"

REM DLR Callback Push consumer — drains dlr.callback.push queue and POSTs
REM delivery receipts to customer URLs. Replaces the legacy 5-min cron.
start "DLR Callback Push"      cmd /k "title DLR Callback Push && %PHP_EXE% artisan dlr-callback:consume"

REM Reports consumer — builds admin reports queued on reports.generate, then emails the link
start "Reports Consumer"       cmd /k "title Reports Consumer && %PHP_EXE% artisan reports:consume"

REM Scheduled SMS processor — runs every 60 seconds in a loop
start "Scheduled SMS Loop"     cmd /k "title Scheduled SMS Loop && :loop && %PHP_EXE% artisan sms:process-scheduled --limit=100 && timeout /t 60 /nobreak && goto loop"

echo.
echo ============================================================================
echo  All workers started — one window per worker.
echo  Close a window or press Ctrl+C in it to stop that worker.
echo  Run stop-local-workers.bat to kill them all at once.
echo ============================================================================
echo.

pause
