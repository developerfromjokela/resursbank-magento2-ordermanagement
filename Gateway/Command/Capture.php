<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Gateway\Command;

use Exception;
use InvalidArgumentException;
use Magento\Framework\Exception\PaymentException;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Resursbank\Core\Helper\Api;
use Resursbank\Core\Helper\Api\Credentials;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Command;
use Resursbank\Ordermanagement\Helper\Config;
use Resursbank\Ordermanagement\Helper\Log;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Capture implements CommandInterface
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
     * @var Api
     */
    private $api;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Credentials
     */
    private $credentials;

    /**
     * @param Log $log
     * @param Config $config
     * @param Api $api
     * @param Credentials $credentials
     * @param ApiPayment $apiPayment
     * @param Command $command
     */
    public function __construct(
        Log $log,
        ApiPayment $apiPayment,
        Config $config,
        Api $api,
        Credentials $credentials,
        Command $command
    ) {
        $this->log = $log;
        $this->apiPayment = $apiPayment;
        $this->config = $config;
        $this->api = $api;
        $this->credentials = $credentials;
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
            $paymentData = $this->command->getPaymentDataObject($commandSubject);

            if ($this->isEnabled($paymentData)) {
                $connection = $this->api->getConnection(
                    $this->credentials->resolveFromConfig()
                );
                $paymentId = $paymentData->getOrder()->getOrderIncrementId();
                $apiPayment = $connection->getPayment($paymentId);
                $orderPayment = $paymentData->getPayment();

                $this->apiPayment->finalizePayment(
                    $orderPayment,
                    $apiPayment,
                    $connection,
                    $paymentId
                );

                $this->log->info(
                    "Successfully captured payment of order {$paymentId}."
                );
            }
        } catch (Exception $e) {
            $this->log->exception($e);

            throw new PaymentException(__(
                'Something went wrong when trying to place the order. ' .
                'Please try again, or select another payment method. You ' .
                'could also try refreshing the page.'
            ));
        }

        return null;
    }

    /**
     * Check if gateway commands are enabled.
     *
     * @param PaymentDataObjectInterface $orderPayment
     * @return bool
     * @todo Check if payment exist at Resurs Bank before we do this.
     */
    protected function isEnabled(
        PaymentDataObjectInterface $orderPayment
    ): bool {
        return (
            $this->config->isAfterShopEnabled(
                (string)$orderPayment->getOrder()->getStoreId()
            ) &&
            $orderPayment->getOrder()->getGrandTotalAmount() > 0
        );
    }
}
