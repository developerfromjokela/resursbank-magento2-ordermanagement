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
use Resursbank\Core\Helper\Api;
use Resursbank\Core\Helper\Api\Credentials;
use Resursbank\Core\Helper\PaymentMethods;
use Magento\Sales\Model\OrderRepository;
use Resursbank\Core\Model\Api\Credentials as CredentialsModel;
use Resursbank\Ordermanagement\Helper\Admin as AdminHelper;
use Resursbank\RBEcomPHP\RESURS_ENVIRONMENTS;
use Resursbank\RBEcomPHP\ResursBank;
use ResursException;
use stdClass;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ApiPayment extends AbstractHelper
{
    /**
     * @var Api
     */
    private $api;

    /**
     * @var Credentials
     */
    private $credentials;

    /**
     * @var AdminHelper
     */
    private $adminHelper;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var PaymentMethods
     */
    private $paymentMethods;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @param Context $context
     * @param AdminHelper $adminHelper
     * @param Api $api
     * @param Credentials $credentials
     * @param Config $config
     * @param PaymentMethods $paymentMethods
     * @param OrderRepository $orderRepository
     */
    public function __construct(
        Context $context,
        AdminHelper $adminHelper,
        Api $api,
        Credentials $credentials,
        Config $config,
        PaymentMethods $paymentMethods,
        OrderRepository $orderRepository
    ) {
        $this->adminHelper = $adminHelper;
        $this->api = $api;
        $this->credentials = $credentials;
        $this->config = $config;
        $this->paymentMethods = $paymentMethods;
        $this->orderRepository = $orderRepository;

        parent::__construct($context);
    }

    /**
     * Validate command subject data and return API connection if eligible.
     *
     * @param PaymentDataObjectInterface $paymentData
     * @return ResursBank|null
     * @throws ValidatorException
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getConnectionCommandSubject(
        PaymentDataObjectInterface $paymentData
    ): ?ResursBank {
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

        return (
            $this->validateOrder($order) &&
            $this->isAfterShopEnabled($order)
        ) ?
            $this->getConnectionFromOrder($order) :
            null;
    }

    /**
     * Retrieve API connection with meta data based on order data.
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
        $connection = $this->api->getConnection($this->getCredentials($order));

        // Apply metadata to simplify debugging.
        $connection->setRealClientName('Magento2');

        // Track what user performed Aftershop actions.
        $connection->setLoggedInUser($this->adminHelper->getUserName());

        // Ensure we perform actions on the orders corresponding payment.
        $connection->setPreferredId($order->getIncrementId());

        return $connection;
    }

    /**
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
     * Retrieve payment from Resurs Bank corresponding to Magento order.
     *
     * @param OrderInterface $order
     * @return array<mixed>
     * @throws ValidatorException
     * @throws ResursException
     */
    public function getPayment(
        OrderInterface $order
    ): array {
        $result = [];
        $payment = $this->getConnectionFromOrder($order)
            ->getPayment($order->getIncrementId());

        if ($payment instanceof stdClass) {
            $result = (array) $payment;
        }

        if (empty($result)) {
            throw new ValidatorException(__('Missing payment data.'));
        }

        return $result;
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

    /**
     * Retrieve API credentials based on order data.
     *
     * @param OrderInterface $order
     * @return CredentialsModel
     * @throws ValidatorException
     */
    private function getCredentials(
        OrderInterface $order
    ): CredentialsModel {
        $credentials = $this->credentials->resolveFromConfig(
            (string) $order->getStoreId()
        );

        /** @phpstan-ignore-next-line */
        $env = (bool) $order->getData('resursbank_is_test');

        $credentials->setEnvironment(
            $env ? RESURS_ENVIRONMENTS::TEST : RESURS_ENVIRONMENTS::PRODUCTION
        );

        return $credentials;
    }
}
