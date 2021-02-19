<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Gateway\Command;

use Exception;
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
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Config;
use Resursbank\Ordermanagement\Helper\Log;

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
     * @param Log $log
     * @param ApiPayment $apiPayment
     * @param Config $config
     * @param PaymentMethods $paymentMethods
     */
    public function __construct(
        Log $log,
        ApiPayment $apiPayment,
        Config $config,
        PaymentMethods $paymentMethods
    ) {
        $this->log = $log;
        $this->apiPayment = $apiPayment;
        $this->config = $config;
        $this->paymentMethods = $paymentMethods;
    }

    /**
     * @param array<mixed> $subject
     * @return ResultInterface|null
     * @throws PaymentException
     */
    public function execute(
        array $subject
    ): ?ResultInterface {
        try {
            $paymentData = SubjectReader::readPayment($subject);

            if ($this->isEnabled($paymentData)) {
                if (!$this->apiPayment->refundPayment(
                    $paymentData->getOrder()->getOrderIncrementId(),
                    $paymentData->getPayment()->getCreditmemo()  /** @phpstan-ignore-line */
                )) {
                    throw new PaymentException(__(
                        'An error occurred while communicating with the API.'
                    ));
                }
            }
        } catch (Exception $e) {
            $this->log->exception($e);

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
