<?php

namespace App\Services;

class PlatinumLib
{
    // public function apiReply($message)
    // {
    //     // Return the message as a plain text response
    //     return response($message, 200)->header('Content-Type', 'text/plain');
    // }

    public function apiReply($message)
    {
        // $prependText = "header-info|Additional information\n";
        $formattedMessage = "code|text\n" . $message;

        return response($formattedMessage, 200)->header('Content-Type', 'text/plain');
    }


    public function whoisKeyword($keyword, $shortcode)
    {
        return [
            'isAvailable' => true,
            'message' => "Keyword is available",
        ];
    }
}
