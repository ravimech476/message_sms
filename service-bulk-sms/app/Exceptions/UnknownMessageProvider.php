<?php

namespace App\Exceptions;

use Exception;

class UnknownMessageProvider extends Exception
{
    protected $message = 'Unknown message provider.';
}
