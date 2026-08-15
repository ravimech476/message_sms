@echo off
REM ===========================================================================
REM  Stop all FootFall SMS workers started by start-sms-workers.bat
REM ===========================================================================
set C=silicon-sms-php

echo Stopping SMPP senders + receiver banks...
docker exec %C% pkill -f "artisan smpp:"

echo Stopping DLR buffer processor...
docker exec %C% pkill -f "artisan dlr:process-buffer"

echo.
echo Done. Remaining SMS worker processes (should be 0):
docker exec %C% sh -c "ps aux | grep -c '[a]rtisan smpp:'"
