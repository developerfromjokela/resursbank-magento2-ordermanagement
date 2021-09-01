<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\ViewModel\Adminhtml\Sales\Order\View\Info;

use Exception;
use Magento\Store\Api\Data\StoreInterface;
use function is_array;
use function is_int;
use function is_string;
use Magento\Checkout\Helper\Data;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Resursbank\Core\Helper\Api;
use Resursbank\Core\Helper\PaymentMethods;
use Resursbank\Ordermanagement\Helper\Log;
use RuntimeException;
use stdClass;

/**
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class PaymentInformation implements ArgumentInterface
{
    /**
     * @var Data
     */
    private Data $checkoutHelper;

    /**
     * @var null|array<mixed>
     */
    private ?array $paymentInfo;

    /**
     * @var null|OrderInterface
     */
    private ?OrderInterface $order;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var Api
     */
    private Api $api;

    /**
     * @var PriceCurrencyInterface
     */
    private PriceCurrencyInterface $priceCurrency;

    /**
     * @var Log
     */
    private Log $log;

    /**
     * @var PaymentMethods
     */
    private PaymentMethods $paymentMethods;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param Data $checkoutHelper
     * @param OrderRepositoryInterface $orderRepository
     * @param Api $api
     * @param PriceCurrencyInterface $priceCurrency
     * @param PaymentMethods $paymentMethods
     * @param Log $log
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Data $checkoutHelper,
        OrderRepositoryInterface $orderRepository,
        Api $api,
        PriceCurrencyInterface $priceCurrency,
        PaymentMethods $paymentMethods,
        Log $log,
        StoreManagerInterface $storeManager
    ) {
        $this->checkoutHelper = $checkoutHelper;
        $this->orderRepository = $orderRepository;
        $this->api = $api;
        $this->priceCurrency = $priceCurrency;
        $this->paymentMethods = $paymentMethods;
        $this->log = $log;
        $this->storeManager = $storeManager;
    }

    /**
     * @param OrderInterface $order
     * @return OrderInterface
     */
    public function setOrder(
        OrderInterface $order
    ): OrderInterface {
        $this->order = $order;

        return $this->order;
    }

    /**
     * @param int|null $orderId
     * @return OrderInterface|null
     */
    public function getOrder(
        ?int $orderId = null
    ): ?OrderInterface {
        $result = $this->order;

        if (is_int($orderId) && $orderId > 0) {
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
                $paymentData = $this->api->getPayment($this->order);
                $this->paymentInfo = $paymentData !== null ?
                    (array)$paymentData :
                    null;
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
     * @param OrderInterface $order
     * @return bool
     */
    public function isEnabled(
        OrderInterface $order
    ): bool {
        if (!($order->getPayment() instanceof OrderPaymentInterface)) {
            throw new RuntimeException(
                'Missing payment data on order ' . $order->getIncrementId()
            );
        }

        return $this->paymentMethods->isResursBankMethod(
            $order->getPayment()->getMethod()
        );
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
        $store = $this->getStore();

        return $this->priceCurrency->format(
            $price,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            ($store !== null ? $store->getCode() : null)
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

    /**
     * Get store associated with current order.
     *
     * @return StoreInterface|null
     */
    private function getStore(): ?StoreInterface
    {
        $result = null;

        try {
            $order = $this->getOrder();

            if (($order instanceof OrderInterface) &&
                $order->getStoreId() !== null
            ) {
                $result = $this->storeManager->getStore($order->getStoreId());
            }
        } catch (Exception $e) {
            $this->log->exception($e);
        }

        return $result;
    }
}
