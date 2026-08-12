<?php

namespace App\Services\MessageProviders;

use App\Exceptions\InvalidCredentials;
use App\Interfaces\MessageProvider as ProviderInterface;

abstract class MessageProvider implements ProviderInterface
{
    /**
     * @var array $credentials
     */
    protected $credentials;

    /**
     * @var $client
     */
    protected $client;

    /**
     * AbstractMessageProvider constructor.
     * @param array $credentials
     * @param array $required
     * @throws InvalidCredentials
     */
    public function __construct(array $credentials, array $required)
    {
        $this->validate($credentials, $required);

        $this->credentials = $credentials;
    }

    /**
     * Validates that the required credential attributes are passed to the Provider.
     * @param $credentials
     * @param $required
     * @throws InvalidCredentials
     */
    public function validate($credentials, $required): void
    {
        $validation = array_diff_key(array_flip($required), $credentials);

        if (count($validation) > 0) {
            $message = sprintf('The following credentials are missing: %s', implode(', ', array_keys($validation)));
            throw new InvalidCredentials($message);
        }
    }
}
