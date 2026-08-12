<?php

namespace App\Interfaces;

interface MessageProvider
{
    /**
     * Sets up client.
     *
     * @return mixed
     */
    public function client();

    /**
     * Sends message and returns back with the external ID.
     *
     * @param $to
     * @param $from
     * @param $message
     * @return mixed
     */
    public function send($to, $from, $message): string;
}
