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
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment;
use Resursbank\Core\Model\Payment\Resursbank;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Command;
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
     * @var Command
     */
    private $command;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Log $log
     * @param ApiPayment $apiPayment
     * @param Config $config
     * @param Command $command
     */
    public function __construct(
        Log $log,
        ApiPayment $apiPayment,
        Config $config,
        Command $command
    ) {
        $this->log = $log;
        $this->apiPayment = $apiPayment;
        $this->config = $config;
        $this->command = $command;
    }

    /**
     * @param array $commandSubject
     * @return ResultInterface|null
     * @throws PaymentException
     */
    public function execute(
        array $commandSubject
    ): ?ResultInterface {
        try {
            $paymentData = $this->command->getPaymentDataObject(
                $commandSubject
            );

            $payment = $paymentData->getPayment();
            $paymentId = $paymentData->getOrder()->getOrderIncrementId();

            if ($payment instanceof Payment &&
                $payment->getCreditmemo() instanceof Creditmemo &&
                !$this->apiPayment->isRefunded($paymentId) &&
                $this->isEnabled($paymentData) &&
                $this->validatePaymentMethod($paymentData->getPayment())
            ) {
                $wasRefunded = $this->apiPayment->refundPayment(
                    $paymentId,
                    $payment->getCreditmemo()
                );

                if (!$wasRefunded) {
                    throw new PaymentException(__(
                        'An error occurred while communicating with the API.'
                    ));
                }
            }
        } catch (Exception $e) {
            $this->log->exception($e);

            $paymentData = $this->command->getPaymentDataObject(
                $commandSubject
            );

            throw new PaymentException(__(
                'Something went wrong when trying to refund the order ' .
                $paymentData->getOrder()->getOrderIncrementId()
            ));
        }

        return null;
    }

    /**
     * Check if gateway commands are enabled.
     *
     * @param PaymentDataObjectInterface $paymentData
     * @return bool
     */
    protected function isEnabled(
        PaymentDataObjectInterface $paymentData
    ): bool {
        return (
            $this->config->isAfterShopEnabled(
                (string)$paymentData->getOrder()->getStoreId()
            ) &&
            $paymentData->getOrder()->getGrandTotalAmount() > 0
        );
    }

    /**
     * @param InfoInterface $orderPayment
     * @return bool
     * @throws LocalizedException
     */
    public function validatePaymentMethod(
        InfoInterface $orderPayment
    ): bool {
        $code = substr(
            $orderPayment->getMethodInstance()->getCode(),
            0,
            strlen(Resursbank::CODE_PREFIX)
        );

        return $code === Resursbank::CODE_PREFIX;
    }
}
