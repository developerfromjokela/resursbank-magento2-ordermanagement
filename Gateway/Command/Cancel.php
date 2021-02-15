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
use Resursbank\Core\Helper\PaymentMethods;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Config;
use Resursbank\Ordermanagement\Helper\Log;
use ResursException;

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
     * @param array $subject
     * @return ResultInterface|null
     * @throws PaymentException
     */
    public function execute(
        array $subject
    ): ?ResultInterface {
        /** @noinspection BadExceptionsProcessingInspection */
        try {
            $paymentData = SubjectReader::readPayment($subject);

            if ($this->isEnabled($paymentData)) {
                $this->apiPayment->cancelPayment($paymentData);
            }
        } catch (Exception $e) {
            $this->log->exception($e);

            throw new PaymentException(__('Failed to cancel payment.'));
        }

        return null;
    }

    /**
     * Check if gateway command is enabled.
     *
     * @param PaymentDataObjectInterface $paymentData
     * @return bool
     * @throws ValidatorException
     * @throws ResursException
     * @throws LocalizedException
     */
    protected function isEnabled(
        PaymentDataObjectInterface $paymentData
    ): bool {
        $code = $paymentData->getPayment()->getMethodInstance()->getCode();
        $paymentId = $paymentData->getOrder()->getOrderIncrementId();

        return (
            $this->config->isAfterShopEnabled(
                (string)$paymentData->getOrder()->getStoreId()
            ) &&
            $paymentData->getOrder()->getGrandTotalAmount() > 0 &&
            $this->paymentMethods->isResursBankMethod($code) &&
            $this->apiPayment->exists($paymentId)
        );
    }
}
