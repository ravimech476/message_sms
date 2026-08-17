<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MessageSendRequest;
use App\Http\Requests\GetMessageRequest;
use App\Http\Resources\MessageResource;
use App\Jobs\ProcessMessage;
use App\Models\Message;

/**
 * @author Anand Karthik (modified — logs each send request to api/requests.log)
 */
class MessagesController extends Controller
{
    /**
     * Stores message and dispatches for sending.
     *
     * @param MessageSendRequest $messageRequest
     * @return \Illuminate\Http\JsonResponse|MessageResource
     */
    public function send(MessageSendRequest $messageRequest)
    {
        $practice = $messageRequest->get('practice');
        $provider = $practice->domain->defaultProvider;

        if (!$provider) {
            $error = "Practice {$practice->practice_name} doesn't have a default provider.";
            return response()->json(['message' => $error], 404);
        }

        $message = Message::create([
            'provider_id' => $provider->id,
            'thread_id' => $messageRequest->get('thread_id'),
            'thread_item_id' => $messageRequest->get('thread_item_id'),
            'message_data' => [
                'to' => $messageRequest->get('to'),
                'message' => $messageRequest->get('message'),
                'fallback' => $messageRequest->get('fallback'),
            ]
        ]);

        ProcessMessage::dispatch($practice, $message, $messageRequest->validated());

        \App\Services\Logging\ComponentLogger::api()->info('POST /messages — dispatched', [
            'domain'     => $messageRequest->get('domain'),
            'to'         => $messageRequest->get('to'),
            'message_id' => $message->id,
            'provider'   => $provider->getOriginal('provider'),
        ]);

        return (new MessageResource($message))->additional(['message' => 'Message has been dispatched.']);
    }

    /**
     * Returns back with messages by domain or a specific message by thread item ID.
     *
     * @param GetMessageRequest $messageRequest
     * @param int $id
     * @return \Illuminate\Http\JsonResponse|MessageResource
     */
    public function get(GetMessageRequest $messageRequest, int $id)
    {
        $practice = $messageRequest->get('practice');
        $provider = $practice->domain->defaultProvider;

        if (!$provider) {
            $error = "Practice {$practice->practice_name} doesn't have a default provider.";
            return response()->json(['message' => $error], 404);
        }

        if ($messageRequest->get('filter_type') === 'thread_id') {
            $messages = Message::where([
                'provider_id' => $provider->id,
                'thread_id' => $id
            ])->get();

            if ($messages->count() > 0) {
                return new MessageResource($messages);
            }

            return response()->json(['message' => "Message not found"], 404);
        } elseif ($messageRequest->get('filter_type') === 'thread_item_id') {
            $message = Message::where([
                'provider_id' => $provider->id,
                'thread_item_id' => $id
            ])->first();
        } else {
            $message = Message::find($id);
        }

        if ($message) {
            return new MessageResource($message);
        }

        return response()->json(['message' => "Message not found"], 404);
    }
}
