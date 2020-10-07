<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model;

use Magento\Sales\Api\Data\OrderInterface;
use Resursbank\Ordermanagement\Api\CallbackInterface;
use Resursbank\Ordermanagement\Exception\CallbackValidationException;
use Resursbank\Ordermanagement\Helper\Callback as CallbackHelper;

class Callback implements CallbackInterface
{
    /**
     * @var CallbackHelper
     */
    private $callbackHelper;

    /**
     * @var OrderInterface
     */
    private $orderInterface;

    /**
     * Callback constructor.
     *
     * @param CallbackHelper $callbackHelper
     */
    public function __construct(
        CallbackHelper $callbackHelper,
        OrderInterface $orderInterface
    ) {
        $this->callbackHelper = $callbackHelper;
        $this->orderInterface = $orderInterface;
    }

    /**
     * @inheritDoc
     */
    public function unfreeze(string $paymentId, string $digest)
    {
        $this->execute('unfreeze', $paymentId, $digest);
    }

    /**
     * @inheritDoc
     */
    public function booked(string $paymentId, string $digest)
    {
        return $this->callbackHelper->salt();
    }

    /**
     * @inheritDoc
     */
    public function update(string $paymentId, string $digest)
    {
        return 'Update';
    }

    /**
     * @inheritDoc
     */
    public function test(
        string $param1,
        string $param2,
        string $param3,
        string $param4,
        string $param5,
        string $digest
    ) {
        return 'Test';
    }

    private function execute(
        string $method,
        string $paymentId,
        string $digest
    ) {
        $this->validate($paymentId, $digest);

        $order = $this->orderInterface->loadByIncrementId($paymentId);

        if (!$order->getId()) {
            throw new Exception('Failed to locate order ' . $paymentId);
        }
    }

    /**
     * Validate the digest.
     *
     * @param string $paymentId
     * @param string $digest
     * @throws CallbackValidationException
     */
    private function validate(string $paymentId, string $digest): void
    {
        // Test salt: 87e15335719cc5ef94d9896b699d0dc1
        $ourDigest = strtoupper(
            sha1($paymentId . $this->callbackHelper->salt())
        );

        if ($ourDigest !== $digest) {
            throw new CallbackValidationException('Invalid callback digest.');
        }
    }
}
