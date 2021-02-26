<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Gateway\Command;

use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Helper\PaymentHistory;
use ResursException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Refund implements CommandInterface
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
            /** @noinspection PhpUndefinedMethodInspection */
            $this->paymentHistory->createEntry(
                (int) $paymentData->getPayment()->getEntityId(), /** @phpstan-ignore-line */
                PaymentHistoryInterface::EVENT_REFUND_CALLED,
                PaymentHistoryInterface::USER_CLIENT
            );
            
            if ($this->apiPayment->canRefund($paymentData) &&
                !$this->refund($paymentData)
            ) {
                throw new PaymentException(__(
                    'An error occurred while communicating with the API.'
                ));
            }
        } catch (Exception $e) {
            $this->log->exception($e);

            /** @noinspection PhpUndefinedMethodInspection */
            $this->paymentHistory->createEntry(
                (int) $paymentData->getPayment()->getEntityId(), /** @phpstan-ignore-line */
                PaymentHistoryInterface::EVENT_REFUND_FAILED,
                PaymentHistoryInterface::USER_CLIENT
            );

            throw new PaymentException(__(
                'Something went wrong when trying to issue the refund.'
            ));
        }

        return null;
    }

    /**
     * @param PaymentDataObjectInterface $paymentData
     * @return bool
     * @throws ValidatorException|LocalizedException|ResursException
     */
    private function refund(
        PaymentDataObjectInterface $paymentData
    ): bool {
        $payment = $paymentData->getPayment();

        return $payment instanceof Payment &&
            $payment->getCreditmemo() instanceof Creditmemo &&
            $this->apiPayment->canRefund($paymentData) &&
            $this->apiPayment->refundPayment(
                $paymentData->getOrder()->getOrderIncrementId(),
                $payment->getCreditmemo(),
                $paymentData
            );
    }
}
