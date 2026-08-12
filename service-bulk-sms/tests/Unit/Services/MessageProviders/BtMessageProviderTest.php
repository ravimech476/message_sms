<?php

namespace Tests\Unit\Services\MessageProviders;

use App\Services\MessageProviders\BtMessageProvider;
use ReflectionClass;
use Tests\TestCase;

class BtMessageProviderTest extends TestCase
{
    /**
     * @throws \App\Exceptions\InvalidCredentials
     */
    public function testPhoneNumberConvertedToValidDestinationFormat()
    {
        $provider = new BtMessageProvider([], []);
        $reflection = new ReflectionClass($provider);
        $reflectedProvider = $reflection->getMethod('to');
        $reflectedProvider->setAccessible(true);

        $this->assertEquals('447123456789', $reflectedProvider->invokeArgs($provider, ['447123456789']));
        $this->assertEquals('447123456789', $reflectedProvider->invokeArgs($provider, ['07123456789']));
        $this->assertEquals('7123456789', $reflectedProvider->invokeArgs($provider, ['7123456789']));
    }
}
