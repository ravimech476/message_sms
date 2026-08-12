<?php

use App\Models\Account;
use App\Models\MessageUpdate;
use App\Models\Provider;
use App\Models\Message;
use App\Models\Practice;
use Illuminate\Database\Seeder;

class TestPracticeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (!env('PRACTICE_ODS') || !env('VONAGE_API_KEY') || !env('VONAGE_API_SECRET')) {
            abort(422, 'Check your environment variables are not empty: PRACTICE_ODS, VONAGE_API_KEY and VONAGE_API_SECRET');
        }

        $practice = Practice::whereODS(env('PRACTICE_ODS'))->first();

        $domainProvider = $practice->domain->providers()->save(
            new Provider([
                'provider' => 'vonage',
                'is_default' => 1,
                'sender_identifier' => 'TestPract',
            ])
        );

        $domainProvider->credentials()->saveMany(
            [
                new App\Models\Credential(['provider_id' => $domainProvider->id, 'key' => 'api_key', 'value' => env('VONAGE_API_KEY')]),
                new App\Models\Credential(['provider_id' => $domainProvider->id, 'key' => 'api_secret', 'value' => env('VONAGE_API_SECRET')]),
            ]
        );

        $message = Message::create([
            'provider_id' => $domainProvider->id,
            'thread_id' => 1,
            'thread_item_id' => 1,
            'message_data' => [
                'to' => '0123456789',
                'message' => 'This is a test message',
                'fallback' => [
                    'email' => 'test@localhost',
                    'subject' => 'This is a test',
                    'body' => '<html><body><strong>This is a test email</strong></body></html>',
                ]
            ],
        ]);

        $message->updates()->save(new MessageUpdate([
            'delivery_type' => 'sms',
            'status' => 'sent',
            'status_note' => 'Message ID: 1234',
        ]));
    }
}
