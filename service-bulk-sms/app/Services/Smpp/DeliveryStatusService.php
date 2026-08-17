<?php

namespace App\Services\Smpp;

use App\Models\MessageUpdate;

/**
 * Applies a parsed DLR to message_updates. Matches by supplier_message_id (the SMPP
 * message-id stamped at send time) and writes delivered_at + status.
 *
 * The status enum stays pending|sent|failed (no schema change): a delivered receipt
 * sets delivered_at (the UI shows "Delivered" from that), a non-delivered one sets
 * status = failed. Ported status map from sms_expert's DeliveryStatusService.
 */
class DeliveryStatusService
{
    /** SMPP DLR status word → is-it-a-successful-delivery. */
    private array $delivered = [
        'DELIVRD' => true,
        'ACCEPTD' => true,   // accepted by network — treated as delivered for display
    ];

    /**
     * Match the DLR to its message_updates row and write delivery status.
     * Returns true if a row was updated.
     */
    public function apply(array $dlr): bool
    {
        $msgId = $dlr['message_id'] ?? null;
        if (empty($msgId)) {
            return false;
        }

        $stat        = strtoupper((string) ($dlr['status'] ?? ''));
        $isDelivered = $this->delivered[$stat] ?? false;

        if ($isDelivered) {
            // Actually delivered → keep status 'sent', stamp delivered_at (UI shows "Delivered").
            $fields = [
                'status'       => 'sent',
                'status_note'  => "Delivered ({$stat})",
                'delivered_at' => now(),
            ];
        } else {
            // Non-delivered → status 'failed', leave delivered_at NULL so it never
            // shows as delivered. The note carries the exact reason.
            $fields = [
                'status'      => 'failed',
                'status_note' => "Non Delivered ({$stat})",
            ];
        }

        return MessageUpdate::where('supplier_message_id', $msgId)->update($fields) > 0;
    }
}
