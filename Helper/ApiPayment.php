<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Helper;

use Exception;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Resursbank\Core\Exception\PaymentDataException;
use Resursbank\Core\Helper\Api;
use Resursbank\Core\Helper\Order;
use Resursbank\Core\Helper\PaymentMethods;
use Magento\Sales\Model\OrderRepository;
use Resursbank\Ordermanagement\Helper\Admin as AdminHelper;
use Resursbank\RBEcomPHP\ResursBank;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ApiPayment extends AbstractHelper
{
    /**
     * @param Context $context
     * @param AdminHelper $adminHelper
     * @param Api $api
     * @param Config $config
     * @param PaymentMethods $paymentMethods
     * @param OrderRepository $orderRepository
     * @param Order $order
     */
    public function __construct(
        Context $context,
        private readonly AdminHelper $adminHelper,
        private readonly Api $api,
        private readonly Config $config,
        private readonly PaymentMethods $paymentMethods,
        private readonly OrderRepository $orderRepository,
        private readonly Order $order
    ) {
        parent::__construct($context);
    }

    /**
     * Validate command subject data and return API connection if eligible.
     *
     * @param PaymentDataObjectInterface $paymentData
     * @return ResursBank
     * @throws ValidatorException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws PaymentDataException
     */
    public function getConnectionCommandSubject(
        PaymentDataObjectInterface $paymentData
    ): ResursBank {
        /**
         * NOTE: The gateway commands will provide us with an instance of
         * Magento\Payment\Gateway\Data\OrderAdapterInterface. This contract
         * lacks a lot of specifications we require in our business logic for
         * API calls. Because of this, we load the order from the database
         * directly. This is expensive, but for now there is no alternative.
         * This should have a negligible impact on Aftershop calls made from the
         * administration panel when handling the order.
         */
        $order = $this->orderRepository->get($paymentData->getOrder()->getId());

        $connection = (
            $this->order->isLegacyFlow($order) &&
            $this->validateOrder($order) &&
            $this->isAfterShopEnabled($order)
        ) ?
            $this->getConnectionFromOrder($order) :
            null;

        if ($connection === null) {
            throw new PaymentDataException(
                __('Failed to resolve API connection from order information.')
            );
        }

        return $connection;
    }

    /**
     * Retrieve API connection with metadata based on order data.
     *
     * @param OrderInterface $order
     * @return ResursBank
     * @throws ValidatorException
     * @throws Exception
     */
    public function getConnectionFromOrder(
        OrderInterface $order
    ): ResursBank {
        // Establish API connection.
        $connection = $this->api->getConnection(
            $this->api->getCredentialsFromOrder($order)
        );

        // Apply metadata to simplify debugging.
        $connection->setRealClientName('Magento2');

        // Track what user performed Aftershop actions.
        $connection->setLoggedInUser($this->adminHelper->getUserName());

        // Ensure we perform actions on the orders corresponding payment.
        $connection->setPreferredId($order->getIncrementId());

        return $connection;
    }

    /**
     * Perform validation of order.
     *
     * @param OrderInterface $order
     * @return bool
     */
    public function validateOrder(
        OrderInterface $order
    ): bool {
        return (
            (float) $order->getGrandTotal() > 0 &&
            $order->getPayment() instanceof OrderPaymentInterface &&
            $this->paymentMethods->isResursBankMethod(
                (string) $order->getPayment()->getMethod()
            )
        );
    }

    /**
     * Check if Aftershop methods are enabled based on order data.
     *
     * @param OrderInterface $order
     * @return bool
     */
    private function isAfterShopEnabled(
        OrderInterface $order
    ): bool {
        return $this->config->isAfterShopEnabled((string) $order->getStoreId());
    }
}
