<?php

namespace App\Jobs;

use App\Models\VirtualNumber;
use App\Services\NexmoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncVirtualNumbersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting Virtual Numbers Sync Job');

            $nexmoService = new NexmoService();
            $nexmoNumbers = $nexmoService->getAllNumbers();

            if (empty($nexmoNumbers)) {
                Log::warning('No numbers retrieved from Nexmo API');
                return;
            }

            $syncedCount = 0;
            $insertedCount = 0;
            $updatedCount = 0;

            foreach ($nexmoNumbers as $nexmoNumber) {
                $msisdn = $nexmoNumber['msisdn'] ?? null;
                
                if (!$msisdn) {
                    continue;
                }

                $numberData = [
                    'msisdn' => $msisdn,
                    'operator' => 'Nexmo',
                    'country' => $nexmoNumber['country'] ?? null,
                    'type' => $nexmoNumber['type'] ?? null,
                    'features' => $nexmoNumber['features'] ?? [],
                    'mo_http_url' => $nexmoNumber['moHttpUrl'] ?? null,
                    'messages_webhooks' => [
                        'inbound_url' => $nexmoNumber['messagesCallbackValue'] ?? null,
                        'inbound_type' => $nexmoNumber['messagesCallbackType'] ?? null,
                    ],
                    'voice_webhooks' => [
                        'answer_url' => $nexmoNumber['voiceCallbackValue'] ?? null,
                        'answer_type' => $nexmoNumber['voiceCallbackType'] ?? null,
                        'status_url' => $nexmoNumber['voiceStatusCallback'] ?? null,
                    ],
                    'limitations' => $nexmoNumber['limitations'] ?? [],
                    'is_active' => true,
                    'last_synced_at' => now(),
                ];

                // Check if number exists
                $existingNumber = VirtualNumber::where('msisdn', $msisdn)->first();

                if ($existingNumber) {
                    // Compare data to see if update is needed
                    $needsUpdate = false;
                    
                    foreach (['country', 'type', 'features', 'mo_http_url', 'messages_webhooks', 'voice_webhooks', 'limitations'] as $field) {
                        if (json_encode($existingNumber->$field) !== json_encode($numberData[$field])) {
                            $needsUpdate = true;
                            break;
                        }
                    }

                    if ($needsUpdate) {
                        $existingNumber->update($numberData);
                        $updatedCount++;
                    }
                } else {
                    // Insert new number
                    VirtualNumber::create($numberData);
                    $insertedCount++;
                }

                $syncedCount++;
            }

            // Mark numbers not in Nexmo as inactive
            $nexmoMsisdns = collect($nexmoNumbers)->pluck('msisdn')->toArray();
            VirtualNumber::whereNotIn('msisdn', $nexmoMsisdns)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            Log::info("Virtual Numbers Sync Completed - Synced: {$syncedCount}, Inserted: {$insertedCount}, Updated: {$updatedCount}");

        } catch (\Exception $e) {
            Log::error('Virtual Numbers Sync Job Failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
