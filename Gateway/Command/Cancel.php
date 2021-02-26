<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Gateway\Command;

use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\PaymentException;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Helper\PaymentHistory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Cancel implements CommandInterface
{
    /**
     * @var Log
     */
    private $log;

    /**
     * @var ApiPayment
     */
    private $apiPayment;

    /**
     * @var PaymentHistory
     */
    private $paymentHistory;

    /**
     * @param Log $log
     * @param ApiPayment $apiPayment
     * @param PaymentHistory $paymentHistory
     */
    public function __construct(
        Log $log,
        ApiPayment $apiPayment,
        PaymentHistory $paymentHistory
    ) {
        $this->log = $log;
        $this->apiPayment = $apiPayment;
        $this->paymentHistory = $paymentHistory;
    }

    /**
     * @param array<mixed> $subject
     * @return ResultInterface|null
     * @throws PaymentException|AlreadyExistsException
     */
    public function execute(
        array $subject
    ): ?ResultInterface {
        $paymentData = SubjectReader::readPayment($subject);

        try {
            /** @noinspection BadExceptionsProcessingInspection */
            /** @noinspection PhpUndefinedMethodInspection */
            /** @phpstan-ignore-next-line */
            $this->paymentHistory->createEntry(
                (int) $paymentData->getPayment()->getEntityId(), /** @phpstan-ignore-line */
                PaymentHistoryInterface::EVENT_CANCEL_CALLED,
                PaymentHistoryInterface::USER_CLIENT
            );

            if ($this->apiPayment->canCancel($paymentData)) {
                $this->apiPayment->cancelPayment($paymentData);
            }
        } catch (Exception $e) {
            $this->log->exception($e);

            /** @noinspection PhpUndefinedMethodInspection */
            /** @phpstan-ignore-next-line */
            $this->paymentHistory->createEntry(
                (int) $paymentData->getPayment()->getEntityId(), /** @phpstan-ignore-line */
                PaymentHistoryInterface::EVENT_CANCEL_FAILED,
                PaymentHistoryInterface::USER_CLIENT
            );

            throw new PaymentException(__('Failed to cancel payment.'));
        }

        return null;
    }
}
