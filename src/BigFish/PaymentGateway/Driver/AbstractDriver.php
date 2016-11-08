<?php

namespace BigFish\PaymentGateway\Driver;

use Bigfish\PaymentGateway\Driver\DriverInterface;

abstract class AbstractDriver implements DriverInterface
{
    /** URL to use on live payment requests */
    const GATEWAY_URL_PRODUCTION = 'https://www.paymentgateway.hu';

    /** URL to use on test payment requests */
    const GATEWAY_URL_TEST = 'https://test.paymentgateway.hu';

    /** @var bool */
    protected $testMode = true;

    /**
     * @return string
     */
    public function getApiUrl()
    {
        return $this->isTestMode() ? static::GATEWAY_URL_PRODUCTION : static::GATEWAY_URL_TEST;
    }

    /**
     * @return bool
     */
    public function isTestMode()
    {
        return $this->testMode;
    }

    /**
     * @param bool $testMode
     * @return $this
     */
    public function setTestMode($testMode = true)
    {
        $this->testMode = (bool) $testMode;

        return $this;
    }
}
