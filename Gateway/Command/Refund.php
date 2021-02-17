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
use Resursbank\Core\Helper\PaymentMethods;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Config;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Helper\PaymentHistory;

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
     * @var Config
     */
    private $config;

    /**
     * @var PaymentMethods
     */
    private $paymentMethods;

    /**
     * @var PaymentHistory
     */
    private $paymentHistory;

    /**
     * @param Log $log
     * @param ApiPayment $apiPayment
     * @param Config $config
     * @param PaymentMethods $paymentMethods
     * @param PaymentHistory $paymentHistory
     */
    public function __construct(
        Log $log,
        ApiPayment $apiPayment,
        Config $config,
        PaymentMethods $paymentMethods,
        PaymentHistory $paymentHistory
    ) {
        $this->log = $log;
        $this->apiPayment = $apiPayment;
        $this->config = $config;
        $this->paymentMethods = $paymentMethods;
        $this->paymentHistory = $paymentHistory;
    }

    /**
     * @param array $subject
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
                (int) $paymentData->getPayment()->getEntityId(),
                PaymentHistoryInterface::EVENT_REFUND_CALLED,
                PaymentHistoryInterface::USER_CLIENT
            );

            if ($this->isEnabled($paymentData)) {
                if (!$this->apiPayment->refundPayment(
                    $paymentData->getOrder()->getOrderIncrementId(),
                    $paymentData->getPayment()->getCreditmemo(),
                    $paymentData
                )) {
                    throw new PaymentException(__(
                        'An error occurred while communicating with the API.'
                    ));
                }
            }
        } catch (Exception $e) {
            $this->log->exception($e);

            /** @noinspection PhpUndefinedMethodInspection */
            $this->paymentHistory->createEntry(
                (int) $paymentData->getPayment()->getEntityId(),
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
     * @throws ValidatorException
     * @throws LocalizedException
     */
    private function isEnabled(
        PaymentDataObjectInterface $paymentData
    ): bool {
        return (
            $paymentData->getPayment() instanceof Payment &&
            $paymentData->getPayment()->getCreditmemo() instanceof Creditmemo &&
            $this->config->isAfterShopEnabled(
                (string)$paymentData->getOrder()->getStoreId()
            ) &&
            $paymentData->getOrder()->getGrandTotalAmount() > 0 &&
            $this->paymentMethods->isResursBankMethod(
                $paymentData->getPayment()->getMethodInstance()->getCode()
            ) &&
            !$this->apiPayment->isRefunded(
                $paymentData->getOrder()->getOrderIncrementId()
            )
        );
    }
}
