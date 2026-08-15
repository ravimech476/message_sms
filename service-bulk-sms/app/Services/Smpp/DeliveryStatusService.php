<?php

namespace App\Services\Smpp;

use Illuminate\Support\Facades\DB;

/**
 * Applies a parsed DLR to smsg_log (Phase 4). Ported from sms_expert's
 * DeliveryStatusService::mapToDeliveryStatus — matches by the message-id stored
 * in onesixty_suppliermsgref (the receipted_message_id from the DLR TLV).
 */
class DeliveryStatusService
{
    /** SMPP DLR status word → OLD-SYSTEM display label. */
    private array $statusMap = [
        'DELIVRD' => 'Delivered',
        'ACCEPTD' => 'acked',
        'EXPIRED' => 'Non Delivered',
        'DELETED' => 'Non Delivered',
        'UNDELIV' => 'Non Delivered',
        'REJECTD' => 'Non Delivered',
        'FAILED'  => 'Non Delivered',
        'UNKNOWN' => 'Unknown',
    ];

    /**
     * Match the DLR to its smsg_log row and write delivery status.
     * Returns true if a row was updated.
     */
    public function apply(array $dlr): bool
    {
        $msgId = $dlr['message_id'] ?? null;
        if (empty($msgId)) {
            return false;
        }

        $status = $this->statusMap[strtoupper((string) ($dlr['status'] ?? ''))] ?? 'Non Delivered';

        $fields = [
            'deliverystatus2'  => $status,
            'deliverytime2'    => (string) ($dlr['done_date'] ?? ''),
            'deliveryreceipt2' => substr((string) $msgId, 0, 36),
            'delivery_reason'  => isset($dlr['err']) ? substr((string) $dlr['err'], 0, 10) : null,
        ];

        // Primary match: onesixty_suppliermsgref (the id we stored at send).
        $updated = DB::table('smsg_log')->where('onesixty_suppliermsgref', $msgId)->update($fields);
        if ($updated === 0) {
            $updated = DB::table('smsg_log')->where('deliveryreceipt1', $msgId)->update($fields);
        }

        return $updated > 0;
    }
}
