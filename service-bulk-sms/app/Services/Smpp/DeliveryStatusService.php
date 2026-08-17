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

        $fields = [
            'delivered_at' => now(),
            'status_note'  => $isDelivered ? "Delivered ({$stat})" : "Non Delivered ({$stat})",
        ];
        // Only downgrade to failed on a real non-delivery; keep 'sent' when delivered
        // (the UI renders "Delivered" from delivered_at being set).
        $fields['status'] = $isDelivered ? 'sent' : 'failed';

        return MessageUpdate::where('supplier_message_id', $msgId)->update($fields) > 0;
    }
}
