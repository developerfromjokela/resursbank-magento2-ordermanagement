<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\ViewModel\Adminhtml\Sales\Order\View\Info;

use Magento\Checkout\Helper\Data;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Resursbank\Core\Helper\Api;
use Resursbank\Ordermanagement\Helper\Config;
use Resursbank\Ordermanagement\Helper\Log;
use stdClass;
use function is_array;
use function is_int;
use function is_string;

class PaymentInformation implements ArgumentInterface
{
    /**
     * @var Data
     */
    private $checkoutHelper;

    /**
     * @var null|array<mixed>
     */
    private $paymentInfo;

    /**
     * @var null|OrderInterface
     */
    private $order;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var Api
     */
    private $api;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @var Log
     */
    private $log;

    /**
     * @param Data $checkoutHelper
     * @param OrderRepositoryInterface $orderRepository
     * @param Api $api
     * @param Config $config
     * @param PriceCurrencyInterface $priceCurrency
     * @param Log $log
     */
    public function __construct(
        Data $checkoutHelper,
        OrderRepositoryInterface $orderRepository,
        Api $api,
        Config $config,
        PriceCurrencyInterface $priceCurrency,
        Log $log
    ) {
        $this->checkoutHelper = $checkoutHelper;
        $this->orderRepository = $orderRepository;
        $this->api = $api;
        $this->config = $config;
        $this->priceCurrency = $priceCurrency;
        $this->log = $log;
    }

    /**
     * @param OrderInterface $order
     */
    public function setOrder(
        OrderInterface $order
    ): void {
        $this->order = $order;
    }

    /**
     * @param int|null $orderId
     * @return OrderInterface|null
     */
    public function getOrder(
        ?int $orderId
    ): ?OrderInterface {
        $result = $this->order;

        if (is_int($orderId)) {
            $result = $this->orderRepository->get($orderId);
        }

        return $result;
    }

    /**
     * Retrieve payment information from Resurs Bank.
     *
     * @param string $key
     * @return mixed|null|stdClass
     */
    public function getPaymentInformation(
        string $key = ''
    ) {
        $result = null;

        if ($this->paymentInfo === null &&
            $this->order instanceof OrderInterface &&
            is_string($this->order->getIncrementId())
        ) {
            try {
                $this->paymentInfo = (array) $this->api
                    ->getPayment($this->order);
            } catch (Exception $e) {
                $this->log->exception($e);
            }
        }

        if (empty($key)) {
            $result = $this->paymentInfo;
        } elseif (is_array($this->paymentInfo) &&
            isset($this->paymentInfo[$key])
        ) {
            $result = $this->paymentInfo[$key];
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getPaymentStatus(): string
    {
        $result = '';

        $status = $this->getPaymentInformation('status');

        if (is_string($status)) {
            $result = $status;
        } elseif (is_array($status) && count($status)) {
            foreach ($status as $piece) {
                $result.= !empty($result) ? (' | ' . __($piece)) : __($piece);
            }
        }

        if (empty($result)) {
            $result = 'PENDING';
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getPaymentId(): string
    {
        return $this->getPaymentInformation('id') ?? '';
    }

    /**
     * @return string
     */
    public function getPaymentTotal(): string
    {
        return (
            $this->formatPrice(
                $this->convertPrice(
                    $this->getPaymentInformation('totalAmount')
                )
            ) ??
            ''
        );
    }

    /**
     * @return string
     */
    public function getPaymentLimit(): string
    {
        return (
            $this->formatPrice(
                $this->convertPrice(
                    $this->getPaymentInformation('limit')
                )
            ) ??
            ''
        );
    }

    /**
     * @return bool
     */
    public function isFrozen(): bool
    {
        return $this->getPaymentInformation('frozen') === true;
    }

    /**
     * @return bool
     */
    public function isFraud(): bool
    {
        return $this->getPaymentInformation('fraud') === true;
    }

    /**
     * @return string
     */
    public function getPaymentMethodName(): string
    {
        return $this->getPaymentInformation('paymentMethodName') ?? '';
    }

    /**
     * Retrieve customer information from Resurs Bank payment.
     *
     * @param string $key
     * @param bool $address
     * @return mixed
     */
    public function getCustomerInformation(
        string $key = '',
        bool $address = false
    ) {
        $result = (array) $this->getPaymentInformation('customer');

        if (!empty($address)) {
            $result = (is_array($result) && isset($result['address'])) ?
                (array) $result['address'] :
                null;
        }

        if (!empty($key)) {
            $result = $result[$key] ?? null;
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getCustomerName(): string
    {
        return $this->getCustomerInformation('fullName', true);
    }

    /**
     * Returns the customer address as an ordered array.
     *
     * @return string[]
     */
    public function getCustomerAddress(): array
    {
        $street = $this->getCustomerAddressRow1();
        $street2 = $this->getCustomerAddressRow2();
        $postal = $this->getCustomerPostalCode();
        $city = $this->getCustomerPostalArea();
        $country = $this->getCustomerCountry();

        $result = [];
        $result[] = $street;

        if ($street2) {
            $result[] = $street2;
        }

        $result[] = $city;
        $result[] = "{$country} - {$postal}";

        return $result;
    }

    /**
     * @return string
     */
    public function getCustomerTelephone(): string
    {
        return $this->getCustomerInformation('phone') ?? '';
    }

    /**
     * @return string
     */
    public function getCustomerEmail(): string
    {
        return $this->getCustomerInformation('email') ?? '';
    }

    /**
     * @return string
     */
    public function getCustomerAddressRow1(): string
    {
        return $this->getCustomerInformation('addressRow1', true) ?? '';
    }

    /**
     * @return string
     */
    public function getCustomerAddressRow2(): string
    {
        return $this->getCustomerInformation('addressRow2', true) ?? '';
    }

    /**
     * @return string
     */
    public function getCustomerPostalCode(): string
    {
        return $this->getCustomerInformation('postalCode', true) ?? '';
    }

    /**
     * @return string
     */
    public function getCustomerPostalArea(): string
    {
        return $this->getCustomerInformation('postalArea', true) ?? '';
    }

    /**
     * @return string
     */
    public function getCustomerCountry(): string
    {
        return $this->getCustomerInformation('country', true) ?? '';
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isAfterShopEnabled();
    }

    /**
     * Formats a price to include decimals and the configured currency of the
     * store.
     *
     * Example: 123.53 => "123.53,00 kr"
     *
     * @param float $price
     * @return string
     */
    public function formatPrice(
        float $price
    ): string {
        return $this->priceCurrency->format(
            $price,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            $this->checkoutHelper->getQuote()->getStore()
        );
    }

    /**
     * Converts a string price to a float.
     *
     * @param string $price
     * @return float
     */
    public function convertPrice(
        string $price
    ): float {
        return $this->checkoutHelper->convertPrice((float) $price, false);
    }
}
