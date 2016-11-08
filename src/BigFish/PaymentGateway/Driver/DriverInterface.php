<?php

namespace Bigfish\PaymentGateway\Driver;

interface DriverInterface
{
    public function isTestMode();

    public function setTestMode($testMode = true);

    public function getApiUrl();

    public function sendRequest(RequestInterface $request);
}
