<?php

use Illuminate\Support\Facades\DB;

if (!function_exists('get_userroutes')) {

    function getRouteDescriptiveName($routenum)
    {

        switch ($routenum) {

            case (2):
                return 'Standard';
                break; // no longer used (Aug 2004)
            case (3):
                return 'Fixed Sender ID';
                break;
            case (4):
                return 'UK Bulk Marketing';
                break;
            case (6):
                return 'UK High Grade';
                break; // no longer used (Aug 2004)
            case (7):
                return 'Direct';
                break;
            case (8):
                return 'Direct';
                break;

            case (9):
                return 'Personalised Route';
                break;
            case (10):
                return 'Personalised Route';
                break;
            case (11):
                return 'Personalised Route';
                break;
            case (12):
                return 'Personalised Route';
                break;
            case (13):
                return 'Personalised Route';
                break;
            case (14):
                return 'Personalised Route';
                break;
            case (15):
                return 'Personalised Route';
                break;
            case (16):
                return 'Personalised Route';
                break;
            case (17):
                return 'Personalised Route';
                break;
            case (18):
                return 'Personalised Route';
                break;
            case (19):
                return 'Personalised Route';
                break;
            case (20):
                return 'Personalised Route';
                break;
            case (21):
                return 'Personalised Route';
                break;
            case (22):
                return 'Personalised Route';
                break;
            case (23):
                return 'Personalised Route';
                break;
            case (24):
                return 'Personalised Route';
                break;
            case (25):
                return 'Personalised Route';
                break;
            case (26):
                return 'Personalised Route';
                break;
            case (27):
                return 'Personalised Route';
                break;
            case (28):
                return 'Personalised Route';
                break;
            case (29):
                return 'Personalised Route';
                break;
        }

        if ($routenum >= 3000 and $routenum <= 99999) {
            return 'Direct';
        } else {
            return 'No Description';
        }
    }
}
if (!function_exists('generateCustomString')) {

    function generateCustomString($length = 32)
    {
        $binaryData = random_bytes($length / 2);
        $hexString = bin2hex($binaryData);
        $randomChar = chr(rand(97, 122));  // Random lowercase letter
        $customString = $hexString;
        return $customString;
    }
}
if (!function_exists('getUserName')) {

    function getUserName($string, $char)
    {
        return substr($string, 0, $char);
    }
}
if (!function_exists('getPassword')) {

    function getPassword($string, $char)
    {
        return substr($string, 8, $char);
    }
}
if (!function_exists('getsmsExpertDefaultWalletPrice')) {

    function getsmsExpertDefaultWalletPrice()
    {
        return '2.500000';
    }
}
if (!function_exists('getUserType')) {

    function getUserType()
    {
        return 'freekey';
    }
}
if (!function_exists('getCustomerType')) {

    function getCustomerType()
    {
        // OLD-system storage convention: Prepaid accounts store customer_type
        // as EMPTY STRING '' (only 'Postpaid' is ever written as a word).
        return '';
    }
}
if (!function_exists('getSmsgLogTables')) {

    function getSmsgLogTables()
    {



        $arr_ret = [];

        // Execute SHOW TABLES
        $tables = DB::select('SHOW TABLES');

        foreach ($tables as $table) {
            $tableName = array_values((array) $table)[0]; // Extract the table name

            if (preg_match('/smsg_log_/', $tableName)) {
                // ** If you want to restrict to the last few months, modify the condition below
                // if (substr($tableName, -4) == $thisMonth || substr($tableName, -4) == $lastMonth1) {
                $arr_ret[] = $tableName;
                // }
            }
        }

        $arr_ret[] = 'smsg_log';  // Add the main current month's table

        return $arr_ret;
    }
}
if (!function_exists('sanitiseStringForUserDisplay')) {
    function sanitiseStringForUserDisplay($str)
    {

        $arrRemove = array(
            'smsg_err:',
            'Failure to calculate best route (possibly no route definitions set up for this user for this sms spec).',
            'mBlox',
            'Failure due to insufficient funds for this batch.',
            'MMC send failed.',
            'acked',
            'MMC',
            'incured',
            'ExternalEmailAPI',
            'ControlPanel',
            'ExternalAPI',
            'mobyclip',

            'csn',
            'cs-networks',
            'cs networks',
            'cs-network',
            'cs network',

            'infobip',
            'onesixty',
            'cbf',
            'cardboardfish',

            'messagebird',
            'message bird',
            'message-bird',
            'mbird',
            'tulip'
        );

        $arrInsert = array(
            '',
            'Problem with the route. Please check and try again.',
            '',
            'Failed due to insufficient funds.',
            '',
            'acknowledged',
            '',
            'incurred',
            'Email XML-&gt;SMS',
            'Control Panel',
            'API',
            'Mobyclip',

            '',
            '',
            '',
            '',
            '',

            '',
            '',
            '',
            '',

            '',
            '',
            '',
            '',
            ''
        );

        return str_ireplace($arrRemove, $arrInsert, $str);
    }
}
if (!function_exists('decodeSmsTextForDisplay')) {
    /**
     * Decode a message body stored in smsg_log.text for on-screen display.
     *
     * The send path stores the body URL-encoded for OLD-system parity
     * (SmsSendingService::prepareMessage). While doing so it strips the %C2 UTF-8 lead
     * byte, so the Windows-1252 range U+0080–U+00BF — which is where most money and
     * symbol characters live (£ %A3, ¥ %A5, ¢ %A2, © %A9, ® %AE, ° %B0, « %AB, » %BB …)
     * — ends up stored as a single latin1 byte. urldecode() alone therefore yields
     * invalid UTF-8, which renders as "?" or the � box on our UTF-8 pages.
     *
     * OLD served these on latin1 (ISO-8859-1) pages, so those bytes rendered correctly.
     * We do the equivalent for UTF-8 pages: walk the decoded bytes and
     *   - keep any run that is already a valid UTF-8 sequence (€, ₹, emoji, accented
     *     letters, etc. are stored intact and must NOT be touched), and
     *   - convert only the genuinely invalid lone bytes from Windows-1252 to UTF-8.
     *
     * This preserves mixed content such as "Café £5" or "😀 £2" (valid UTF-8 kept, the
     * lone £ byte fixed) instead of mojibaking the whole string.
     *
     * @param string|null $stored Raw smsg_log.text value (URL-encoded)
     * @return string Display-ready UTF-8 string
     */
    function decodeSmsTextForDisplay($stored)
    {
        if ($stored === null || $stored === '') {
            return '';
        }

        $decoded = urldecode($stored);

        // Fast path: already valid UTF-8 (pure ASCII or clean multi-byte) — nothing to fix.
        if (mb_check_encoding($decoded, 'UTF-8')) {
            return $decoded;
        }

        // Greedy pass: preserve valid UTF-8 sequences, Windows-1252-decode only bad bytes.
        $out = '';
        $len = strlen($decoded);
        for ($i = 0; $i < $len; ) {
            $byte = ord($decoded[$i]);

            if ($byte < 0x80) {           // plain ASCII
                $out .= $decoded[$i];
                $i++;
                continue;
            }

            // Expected length of a UTF-8 sequence starting with this lead byte.
            $seqLen = $byte >= 0xF0 ? 4 : ($byte >= 0xE0 ? 3 : ($byte >= 0xC0 ? 2 : 0));

            if ($seqLen && $i + $seqLen <= $len) {
                $seq = substr($decoded, $i, $seqLen);
                if (mb_check_encoding($seq, 'UTF-8')) {   // genuine multi-byte char — keep it
                    $out .= $seq;
                    $i += $seqLen;
                    continue;
                }
            }

            // Lone / invalid byte: treat as Windows-1252 (matches OLD's latin1 rendering).
            $out .= mb_convert_encoding($decoded[$i], 'UTF-8', 'Windows-1252');
            $i++;
        }

        return $out;
    }
}
if (!function_exists('getDeliveryStatus')) {
    function getDeliveryStatus($row, &$statusIndicator_byref)
    {


        $sentstatus      = $row['sentstatus']; // shows to be "no", "ok", "fail", or "doing"
        $sentstatustext  = $row['sentstatustext']; // If sentstatus is "fail", this contains a description of the failure
        $deliverystatus1 = $row['deliverystatus1']; // shows to be "acked", "buffered smsc", "buffered phone"
        $deliverystatus2 = $row['deliverystatus2']; // shows to be "Delivered", "Lost Notification", "Non Delivered"
        $requestedroute  = $row['requested_route']; // requested route

        // Start off by assuming it's currently in transit
        $result = "In Transit";
        $statusIndicator_byref = "In Transit";

        // OLD SYSTEM: a scheduled ("send at time") message waits with sentstatus='tomorrowonward'
        // (smssend.inc:1107). Show it as "Scheduled", not "In Transit", until it actually sends.
        if ($sentstatus == 'tomorrowonward') {
            $result = "Scheduled";
            $statusIndicator_byref = "Scheduled";
            return $result;
        }

        // Was it a prem rate message that has been sent?
        if (($requestedroute >= 10000) && ($sentstatus == 'ok')) {

            $result = "Sent";
            $statusIndicator_byref = "Sent";
        }
        // due to us now getting delivery receipts, this might be modified by the code below

        // Look to see if we can determine that it's a failure
        // OLD SYSTEM uses 'Non Delivered', also check lowercase for backward compatibility
        if ($sentstatus == "fail" || $deliverystatus2 == 'Non Delivered' || strtolower($deliverystatus2) == 'non delivered') {

            $s_del  = ($deliverystatus1 == '' ? $deliverystatus2 : $deliverystatus1 . ($deliverystatus2 == '' ? '' : '; ' . $deliverystatus2));
            $s_text = ($s_del == '' ? '' : '<br>') . ($sentstatustext  == '' ? '' : $sentstatustext);
            $result = "Failed: " . sanitiseStringForUserDisplay($s_del) . sanitiseStringForUserDisplay($s_text);
            $statusIndicator_byref = "Failed";
        }

        // Look to see if we can determine that it's been a success
        // OLD SYSTEM uses 'Delivered' (capital D), also check lowercase for backward compatibility
        if ($deliverystatus2 == "Delivered" || strtolower($deliverystatus2) == 'delivered') {

            $result = "Delivered";
            $statusIndicator_byref = "Delivered";
        }

        // Is it a lost notification?
        // OLD SYSTEM uses 'Lost Notification' (capital letters)
        if ($deliverystatus2 == "Lost Notification" || strtolower($deliverystatus2) == 'lost notification') {

            $result = 'Unknown Delivery - notification was unavailable.';
            $statusIndicator_byref = 'Failed';
        }

        return $result;
    }
}

if (!function_exists('deliveryStatus2DisplayLabel')) {
    /**
     * Map a raw smsg_log.deliverystatus2 value to the label shown to customers — OLD SYSTEM parity.
     *
     * The intermediate / NON-FINAL delivery states are NOT outcomes and OLD keeps showing the
     * message as "In Transit" for them until a real final DLR arrives:
     *   - 'Unknown'  = reason code 6 ("the final status of the message is unknown")
     *   - 'acked'    = accepted by the network, awaiting the handset DLR
     *   - 'buffered' / 'buffered phone' / 'buffered smsc' / 'Buffered' = queued at the network
     * Previously the NEW sent-log showed the raw value ("Unknown"), which contradicted OLD (which
     * shows "In Transit"). Final states (Delivered / Non Delivered / Lost Notification / any other
     * genuinely-final value) are returned unchanged.
     *
     * @param  string|null $deliverystatus2
     * @return string  'In Transit' for non-final states, otherwise the value as-is.
     */
    function deliveryStatus2DisplayLabel($deliverystatus2): string
    {
        $v = strtolower(trim((string) $deliverystatus2));

        $inTransit = [
            'unknown', 'acked', 'ack', 'buffered', 'buffered phone', 'buffered smsc',
            'accepted', 'enroute', 'en route',
        ];

        if (in_array($v, $inTransit, true)) {
            return 'In Transit';
        }

        return (string) $deliverystatus2;
    }
}

if (!function_exists('isSummerTime')) {

    /**
     * Check if a date is in British Summer Time (BST).
     *
     * EXACT COPY of the OLD SYSTEM function (britishsummertime.inc) — copied verbatim for
     * exact OLD-SYSTEM parity on the "Date Finalised" display, per client request.
     *
     * NOTE: the OLD system only defines BST ranges for 2004, 2005 and 2006. For ANY other
     * date (including all modern/post-2006 deliveries) it returns FALSE, so the delivery-time
     * display always takes the GMT branch (no +1 hour). This is OLD's actual behaviour and is
     * intentional here — do NOT "fix" it back to Carbon isDST() without a parity decision.
     *
     * @param string $delivered Date in Ymd format (e.g. '20260722')
     * @return bool
     */
    function isSummerTime($delivered)
    {
        $arrBst = array('20040328' => '20041031', '20050327' => '20051030', '20060326' => '20061029');
        foreach ($arrBst as $start => $stop) {
            if (($delivered >= $start) && ($delivered < $stop)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('get_userroutes')) {

    function get_userroutes($userBigId, $whichRoutes = 0)
    {

        if ($userBigId === '201197ba5bbe3babb9f3b7f5774d0d04') {
            return [
                [
                    '9',
                    '0.056',
                    '1111',
                    'Dutch non psms route',
                    'Dutch non psms route',
                    'Dutch non-psms'
                ]
            ];
        }

        $routes = [];
        $routesOut = [];

        // Define the query
        $query = DB::table('smsg_userroute as ur')
            ->join('smsg_route as r', 'ur.routenum', '=', 'r.routenum')
            ->where(function ($query) use ($userBigId) {
                $query->where('ur.userref', $userBigId)
                    ->orWhere('ur.userref', '11111111111111111111111111111111');
            })
            ->where(function ($query) {
                $query->where('ur.countrydialcode', '44')
                    ->orWhere('ur.countrydialcode', 'all');
            })
            ->where('r.countrydialcode', '44')
            ->where('ur.numbits', 7)
            ->where('r.routestatus', 'live')
            ->orderBy('priority', 'desc');

        // Execute query and process results
        $results = $query->get([
            'r.routenum',
            'ur.userprice',
            'ur.origtype',
            'ur.countrydialcode',
            'priority',
            'r.shortinfo',
            'r.longinfo'
        ]);

        foreach ($results as $rec) {
            if ($whichRoutes == 0 || ($whichRoutes >= 900 && $rec->routenum == $whichRoutes)) {
                // Skip route 4 for specific user
                if ($rec->routenum == 4 && $userBigId === '234a8749bdb3b785a26091e2ad978581') {
                    continue;
                }

                $routeTypeFlags = [
                    'msisdn' => $rec->origtype === 'msisdn',
                    'alpha' => $rec->origtype === 'alpha',
                    'shortcode' => $rec->origtype === 'shortcode'
                ];

                $routes[$rec->routenum] = [
                    'userprice' => $rec->userprice,
                    'country' => $rec->countrydialcode,
                    'priority' => $rec->priority,
                    'shortinfo' => $rec->shortinfo,
                    'longinfo' => $rec->longinfo,
                    'descriptiveRouteName' => getRouteDescriptiveName($rec->routenum),
                    'flags' => $routeTypeFlags
                ];
            }
        }

        if (empty($routes)) {
            return false;
        }

        foreach ($routes as $key => $route) {
            $flags = $route['flags'];
            $msisdn = $flags['msisdn'] ? '1' : '0';
            $alpha = $flags['alpha'] ? '1' : '0';
            $shortcode = $flags['shortcode'] ? '1' : '0';
            $country = ($route['country'] === 'all') ? '1' : '0';

            $routesOut[] = [
                $key,
                $route['userprice'],
                $msisdn . $alpha . $shortcode . $country,
                $route['shortinfo'],
                $route['longinfo'],
                $route['descriptiveRouteName']
            ];
        }

        return $routesOut;
    }

    //2 decimal function 05.00
    if (!function_exists('format_two_decimal')) {
        function format_two_decimal($amount)
        {
            return number_format((float)$amount, 2, '.', '');
        }
    }

    //4 decimal function 05.0000
    if (!function_exists('format_four_decimal')) {
        function format_four_decimal($amount)
        {
            return number_format((float)$amount, 4, '.', '');
        }
    }


    //calculate cost 4 decimal
    if (!function_exists('truncateDecimal')) {
        function truncateDecimal($number, $decimals = 4)
        {
            $factor = pow(10, $decimals);
            return floor($number * $factor) / $factor;
        }
    }
}
