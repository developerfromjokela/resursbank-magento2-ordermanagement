<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Block\Adminhtml\Sales\Order\View\Info;

use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Resursbank\Core\Helper\Ecom;
use Resursbank\Core\Helper\Order;
use Resursbank\Ecom\Module\Payment\Widget\PaymentInformation as Widget;
use Resursbank\Ecom\Module\PaymentMethod\Enum\CurrencyFormat;
use Throwable;
use Magento\Checkout\Helper\Data;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Resursbank\Core\Helper\Api;
use Resursbank\Core\Helper\PaymentMethods;
use Resursbank\Ordermanagement\Helper\Log;
use stdClass;

use function is_array;
use function is_string;

/**
 * Implementation of payment information widget for order/invoice view.
 *
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @noinspection PhpClassHasTooManyDeclaredMembersInspection
 */
class PaymentInformation extends EcomWidget
{
    /**
     * @var null|array
     */
    private ?array $paymentInfo = null;

    /**
     * @param Context $context
     * @param InvoiceRepositoryInterface $invoiceRepo
     * @param CreditmemoRepositoryInterface $creditmemoRepo
     * @param Log $log
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentMethods $paymentMethods
     * @param Order $orderHelper
     * @param Ecom $ecom
     * @param Data $checkoutHelper
     * @param Api $api
     * @param PriceCurrencyInterface $priceCurrency
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        InvoiceRepositoryInterface $invoiceRepo,
        CreditmemoRepositoryInterface $creditmemoRepo,
        Log $log,
        OrderRepositoryInterface $orderRepository,
        PaymentMethods $paymentMethods,
        Order $orderHelper,
        Ecom $ecom,
        private readonly Data $checkoutHelper,
        private readonly Api $api,
        private readonly PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        parent::__construct(
            context: $context,
            templateDir: 'payment-information',
            invoiceRepo: $invoiceRepo,
            creditmemoRepo: $creditmemoRepo,
            log: $log,
            orderRepository: $orderRepository,
            paymentMethods: $paymentMethods,
            orderHelper: $orderHelper,
            ecom: $ecom,
            data: $data
        );
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
     * Get payment status.
     *
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
     * Get payment Id.
     *
     * @return string
     */
    public function getPaymentInfoId(): string
    {
        return $this->getPaymentInformation(key: 'id') ?? '';
    }

    /**
     * Get payment total.
     *
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
     * Get payment limit.
     *
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
     * Check if payment is frozen.
     *
     * @return bool
     */
    public function isFrozen(): bool
    {
        return $this->getPaymentInformation(key: 'frozen') === true;
    }

    /**
     * Check if payment is flagged as fraud.
     *
     * @return bool
     */
    public function isFraud(): bool
    {
        return $this->getPaymentInformation(key: 'fraud') === true;
    }

    /**
     * Get payment method name.
     *
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
     * Get full customer name.
     *
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
     * Get customer telephone number.
     *
     * @return string
     */
    public function getCustomerTelephone(): string
    {
        return $this->getCustomerInformation(key: 'phone') ?? '';
    }

    /**
     * Get customer email address.
     *
     * @return string
     */
    public function getCustomerEmail(): string
    {
        return $this->getCustomerInformation(key: 'email') ?? '';
    }

    /**
     * Get first address row.
     *
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
     * Get second address row.
     *
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
     * Get customer post code.
     *
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
     * Get Customer postal area.
     *
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
     * Get customer country.
     *
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
     * Performs currency formatting of a float value.
     *
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

    /**
     * Get Ecom widget.
     *
     * @return Widget|null
     */
    public function getWidget(): ?Widget
    {
        try {
            return new Widget(
                paymentId: $this->orderHelper->getPaymentId(order: $this->order),
                currencySymbol: 'kr',
                currencyFormat: CurrencyFormat::SYMBOL_LAST
            );
        } catch (Throwable $error) {
            $this->log->exception(error: $error);
        }

        return null;
    }
}
