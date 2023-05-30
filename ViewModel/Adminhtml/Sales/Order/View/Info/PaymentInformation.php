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
 * @noinspection PhpClassHasTooManyDeclaredMembersInspection
 */
class PaymentInformation implements ArgumentInterface
{
    /**
     * @var null|array
     */
    private ?array $paymentInfo = null;

    /**
     * @var null|OrderInterface
     */
    private ?OrderInterface $order = null;

    /**
     * @param Data $checkoutHelper
     * @param Api $api
     * @param PriceCurrencyInterface $priceCurrency
     * @param Log $log
     */
    public function __construct(
        private readonly Data $checkoutHelper,
        private readonly Api $api,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly Log $log
    ) {
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
     * Retrieve payment information from Resurs Bank.
     *
     * @param string $key
     * @return mixed|null|stdClass
     */
    public function getPaymentInformation(
        string $key = ''
    ): mixed {
        $result = null;

        if ($this->paymentInfo === null &&
            $this->order instanceof OrderInterface &&
            is_string(value: $this->order->getIncrementId())
        ) {
            try {
                $paymentData = $this->api->getPayment(order: $this->order);
                $this->paymentInfo = $paymentData !== null ?
                    (array)$paymentData :
                    null;
            } catch (Exception $e) {
                $this->log->exception(error: $e);
            }
        }

        if (empty($key)) {
            $result = $this->paymentInfo;
        } elseif (is_array(value: $this->paymentInfo) &&
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

        $status = $this->getPaymentInformation(key: 'status');

        if (is_string(value: $status)) {
            $result = $status;
        } elseif (is_array(value: $status) && count($status)) {
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
        return $this->getPaymentInformation(key: 'id') ?? '';
    }

    /**
     * @return string
     */
    public function getPaymentTotal(): string
    {
        return (
            $this->formatPrice(
                price: $this->convertPrice(
                    price: $this->getPaymentInformation(key: 'totalAmount')
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
                price: $this->convertPrice(
                    price: $this->getPaymentInformation(key: 'limit')
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
        return $this->getPaymentInformation(key: 'frozen') === true;
    }

    /**
     * @return bool
     */
    public function isFraud(): bool
    {
        return $this->getPaymentInformation(key: 'fraud') === true;
    }

    /**
     * @return string
     */
    public function getPaymentMethodName(): string
    {
        return $this->getPaymentInformation(key: 'paymentMethodName') ?? '';
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
    ): mixed {
        $result = (array) $this->getPaymentInformation(key: 'customer');

        if (!empty($address)) {
            $result = isset($result['address']) ? (array) $result['address'] : null;
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
        return $this->getCustomerInformation(key: 'fullName', address: true);
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
        return $this->getCustomerInformation(key: 'phone') ?? '';
    }

    /**
     * @return string
     */
    public function getCustomerEmail(): string
    {
        return $this->getCustomerInformation(key: 'email') ?? '';
    }

    /**
     * @return string
     */
    public function getCustomerAddressRow1(): string
    {
        return $this->getCustomerInformation(
            key: 'addressRow1',
            address: true
        ) ?? '';
    }

    /**
     * @return string
     */
    public function getCustomerAddressRow2(): string
    {
        return $this->getCustomerInformation(
            key: 'addressRow2',
            address: true
        ) ?? '';
    }

    /**
     * @return string
     */
    public function getCustomerPostalCode(): string
    {
        return $this->getCustomerInformation(
            key: 'postalCode',
            address: true
        ) ?? '';
    }

    /**
     * @return string
     */
    public function getCustomerPostalArea(): string
    {
        return $this->getCustomerInformation(
            key: 'postalArea',
            address: true
        ) ?? '';
    }

    /**
     * @return string
     */
    public function getCustomerCountry(): string
    {
        return $this->getCustomerInformation(
            key: 'country',
            address: true
        ) ?? '';
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
            amount: $price,
            includeContainer: false,
            precision: PriceCurrencyInterface::DEFAULT_PRECISION,
            scope: $this->order->getStoreId()
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
        return $this->checkoutHelper->convertPrice(
            price: (float) $price,
            format: false
        );
    }
}
