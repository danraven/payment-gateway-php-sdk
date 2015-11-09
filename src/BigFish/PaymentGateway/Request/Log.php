<?php

namespace BigFish\PaymentGateway\Request;

class Log extends RequestAbstract
{
	/**
	 * @param string $transactionId Transaction ID received from Payment Gateway
	 */
	public function __construct(\string $transactionId)
	{
		$this->data['transactionId'] = $transactionId;
	}

	/**
	 * @return string
	 */
	public function getMethod(): \string
	{
		return RequestAbstract::REQUEST_LOG;
	}
}