<?php
/**
 * TEST SEED (throwaway): insert 100 smsg_log rows keyed to real message_ids from the
 * DLR CSV so we can verify the "DLR Status Update" upload actually updates deliverystatus2.
 *
 * Fixed defaults per request:
 *   userref      = 73419c0c137c96c84a4490545e731838
 *   mobnum       = 919003096885
 *   dreceipt_url = '' (empty)
 *
 * Match keys set = deliveryreceipt1 AND onesixty_suppliermsgref = CSV message_id.
 * deliverystatus2 starts 'pending' / deliverytime2 empty so we can watch it change.
 *
 * Run:  php scripts/seed_dlr_test.php public/report_SMS_test100.csv
 * Clean: DELETE FROM smsg_log WHERE bigid LIKE 'dlrtest%';
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$csv = $argv[1] ?? 'public/report_SMS_test100.csv';
$path = __DIR__ . '/../' . $csv;
if (!is_file($path)) { fwrite(STDERR, "CSV not found: $path\n"); exit(1); }

// Idempotent: clear any previous test rows first.
$deleted = DB::table('smsg_log')->where('bigid', 'like', 'dlrtest%')->delete();
echo "Cleared $deleted previous test rows.\n";

$fh = fopen($path, 'r');
$header = fgetcsv($fh);
$map = array_flip(array_map(fn($h) => strtolower(trim((string)$h)), $header));
$col = fn(array $r, string $n) => isset($map[$n]) ? trim((string)($r[$map[$n]] ?? '')) : '';

$nowUtcMin = Carbon::now('UTC')->format('YmdHi');       // 12-digit
$nowLondon = Carbon::now('Europe/London')->format('YmdHis'); // 14-digit
$today     = Carbon::now('Europe/London')->format('Ymd');

$inserted = 0; $seen = [];
while (($row = fgetcsv($fh)) !== false) {
    $mid = $col($row, 'message_id');
    if ($mid === '' || strtolower($mid) === 'message_id' || isset($seen[$mid])) continue;
    $seen[$mid] = true;

    DB::table('smsg_log')->insert([
        'sms_type'                => 'sms',
        'bigid'                   => 'dlrtest' . substr(md5($mid), 0, 25),
        'mobnum'                  => '919003096885',
        'text'                    => 'DLR+import+test',
        'originator'              => 'TEST',
        'numbits'                 => 7,
        'numparts'                => 1,
        'timesubmitted'           => $nowLondon,
        'userref'                 => '73419c0c137c96c84a4490545e731838',
        'affiliateref'            => '0',
        'dosendtime'              => $nowLondon,
        'timesent'                => $nowLondon,
        'sentstatus'              => 'ok',
        'sentstatustmp'           => 'ok',
        'sentstatustext'          => 'Message sent successfully',
        // sent to Nexmo, awaiting final DLR:
        'deliverystatus1'         => 'acked',
        'deliverytime1'           => $nowUtcMin,
        'deliveryreceipt1'        => $mid,   // primary DLR match key (hex)
        'onesixty_suppliermsgref' => $mid,   // idempotency gate / CSV import pre-check
        'suppliermsgref'          => 0,
        'deliverystatus2'         => 'pending',
        'deliverytime2'           => '',     // empty => not finalised => will be updated
        'deliveryreceipt2'        => '',
        'costprice'               => 0.000000,
        'userprice'               => 0.000000,
        'profit'                  => 0.000000,
        'countrydialcode'         => '91',
        'suppliername'            => 'Vonage SMPP',
        'initiator'               => 'ExternalAPI',
        'requested_route'         => '7002',
        'dreceipt_url'            => '',     // empty per request
        'sendpriority'            => 300,
        'smsgdaemonid'            => 300,
        'ofcomnetid'              => 99,
        'dayofyear'               => $today,
        'chargetype'              => 'pps',
        'requested_routetag'      => '7002',
        'migration_flag'          => 'new',
    ]);
    $inserted++;
}
fclose($fh);

echo "Inserted $inserted test rows (userref=73419c0c137c96c84a4490545e731838, mobnum=919003096885, dreceipt_url='', deliverystatus2='pending').\n";
