@echo off
REM ============================================================================
REM Stop ONLY the worker CMD windows that start-local-workers.bat opened.
REM
REM Matches by window title (set via the `title` command inside each cmd /k).
REM /T flag kills the cmd's child processes too (the php.exe inside each
REM window) so nothing is left running in the background.
REM
REM Other PHP processes (e.g. `php artisan serve`, composer, your IDE's
REM intelephense / language server, debug sessions) are LEFT ALONE.
REM ============================================================================

setlocal enabledelayedexpansion

echo Stopping SMS Expert local workers...
echo.

set killed=0

REM Each entry below matches a title set in start-local-workers.bat.
REM Add or remove lines here when you add/remove workers in the start script.
for %%T in (
    "DLR Receiver bank=a0"
    "DLR Receiver bank=b0"
    "DLR Receiver bank=c0"
    "DLR Receiver bank=d0"
    "DLR Receiver bank=e0"
    "DLR Receiver bank=f0"
    "DLR Receiver bank=g0"
    "DLR Receiver bank=h0"
    "DLR Receiver bank=i0"
    "DLR Receiver bank=j0"
    "DLR Receiver (single-bind)"
    "Sinch DLR bank=a"
    "Sinch DLR bank=b"
    "Sinch DLR bank=c"
    "Sinch DLR bank=d"
    "Sinch DLR bank=e"
    "Sinch DLR bank=f"
    "Sinch DLR bank=g"
    "Sinch DLR bank=h"
    "Sinch DLR bank=i"
    "Sinch DLR bank=j"
    "Sinch DLR (single-bind)"
    "Sinch DLR Receiver"
    "Inbound SMS Consumer"
    "Nexmo DLR (HTTPS)"
    "Webhook DLR Consumer"
    "Webhook Inbound"
    "Campaign Consumer"
    "Email Consumer"
    "Push Notifications"
    "SMPP Monitor"
    "Campaign File Mig."
    "Webhook URL Update"
    "DLR Callback Push"
    "Reports Consumer"
    "Scheduled SMS Loop"
) do (
    REM /T = also kill child processes (the php.exe inside each cmd)
    REM /FI WINDOWTITLE = filter by window title. Use leading+trailing wildcards
    REM (*title*) so we still match when the title has a trailing space (the inner
    REM `title X && ...` in the start script adds one) or an "Administrator:  "
    REM prefix (when the windows were launched elevated). Exact-match missed both,
    REM which is why the windows weren't closing.
    REM Output redirected so only our own summary line prints
    taskkill /F /T /FI "WINDOWTITLE eq *%%~T*" >nul 2>&1
    if not errorlevel 1 (
        echo   stopped: %%~T
        set /a killed+=1
    )
)

echo.
if !killed! EQU 0 (
    echo No worker windows were running.
) else (
    echo Done — stopped !killed! worker window^(s^).
)

endlocal
pause
