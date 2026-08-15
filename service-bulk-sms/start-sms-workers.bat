@echo off
REM ===========================================================================
REM  FootFall SMS workers — LOCAL Docker launcher (15 Vonage binds)
REM  5 senders + 10 DLR receiver banks + 1 DLR buffer processor.
REM  Production uses supervisor (docker/supervisor/sms-workers.conf); this is the
REM  quick local equivalent using `docker exec -d` (detached, NOT auto-restarted).
REM
REM  Stop them all with: stop-sms-workers.bat
REM ===========================================================================
set C=silicon-sms-php

echo Starting DLR buffer processor (no bind)...
docker exec -d %C% php artisan dlr:process-buffer --continuous --limit=200 --sleep=2

echo Starting 5 senders (transmitter binds)...
for /L %%i in (1,1,5) do docker exec -d %C% php artisan smpp:consume

echo Starting 10 DLR receiver banks (receiver binds)...
for %%b in (a0 b0 c0 d0 e0 f0 g0 h0 i0 j0) do docker exec -d %C% php artisan smpp:dlr-receiver --bank=%%b

echo.
echo All 15 binds launched (5 send + 10 receive) plus the buffer processor.
echo Check binds:   docker exec %C% sh -c "ps aux | grep -c '[a]rtisan smpp:'"
echo Watch a bank:  docker exec %C% tail -f storage/logs/smpp-dlr-bank-a0.log
