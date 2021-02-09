<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\ViewModel\Adminhtml\Sales\Order\View\Info;

use Exception;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Checkout\Helper\Data;
use Resursbank\Core\Model\Api\Credentials;
use Resursbank\Core\Exception\SessionDataException;
use Resursbank\Core\Helper\Config as CoreConfig;
use Resursbank\Ordermanagement\Helper\Log;

class Aftershop implements ArgumentInterface
{
    /**
     * @var Log
     */
    private $log;

    /**
     * @var CoreConfig
     */
    private $coreConfig;

    /**
     * @var Data
     */
    private $checkoutHelper;

    /**
     * @param Log $log
     * @param CoreConfig $coreConfig
     */
    public function __construct(
        Log $log,
        CoreConfig $coreConfig,
        Data $checkoutHelper
    ) {
        $this->log = $log;
        $this->coreConfig = $coreConfig;
        $this->checkoutHelper = $checkoutHelper;
    }

//    /**
//     * Get payment status.
//     *
//     * @return string
//     */
//    public function getStatus()
//    {
//        $result = '';
//
//        $status = $this->getPaymentInformation('status');
//
//        if (is_string($status)) {
//            $result = __($status);
//        } elseif (is_array($status) && count($status)) {
//            foreach ($status as $piece) {
//                $result.= !empty($result) ? (' | ' . __($piece)) : __($piece);
//            }
//        }
//
//        if (empty($result)) {
//            $result = __('PENDING');
//        }
//
//        return $result;
//    }
//
//    /**
//     * Retrieve order reference.
//     *
//     * @return string
//     */
//    public function getPaymentId()
//    {
//        return $this->getPaymentInformation('id');
//    }
//
//    /**
//     * Retrieve payment total.
//     *
//     * @return float
//     */
//    public function getPaymentTotal()
//    {
//        return $this->checkoutHelper->formatPrice(
//            (float) $this->getPaymentInformation('totalAmount')
//        );
//    }
//
//    /**
//     * Retrieve payment limit.
//     *
//     * @return float
//     */
//    public function getPaymentLimit()
//    {
//        return $this->checkoutHelper->formatPrice(
//            (float) $this->getPaymentInformation('limit')
//        );
//    }
//
//    /**
//     * Check if payment is frozen.
//     *
//     * @return bool
//     */
//    public function isFrozen()
//    {
//        return ($this->getPaymentInformation('frozen') === true);
//    }
//
//    /**
//     * Check if payment is fraud marked.
//     *
//     * @return bool
//     */
//    public function isFraud()
//    {
//        return ($this->getPaymentInformation('fraud') === true);
//    }
//
//    /**
//     * Returns the name of the used payment method for the order.
//     *
//     * @return string
//     */
//    public function getPaymentMethodName()
//    {
//        return $this->getPaymentInformation('paymentMethodName');
//    }
//
//    /**
//     * Retrieve full customer name attached to payment.
//     *
//     * @return mixed
//     */
//    public function getCustomerName()
//    {
//        return $this->getCustomerInformation('fullName', true);
//    }
//
//    /**
//     * Retrieve full customer address attached to payment.
//     *
//     * @return mixed
//     */
//    public function getCustomerAddress()
//    {
//        $street = $this->getCustomerInformation('addressRow1', true);
//        $street2 = $this->getCustomerInformation('addressRow2', true);
//        $postal = $this->getCustomerInformation('postalCode', true);
//        $city = $this->getCustomerInformation('postalArea', true);
//        $country = $this->getCustomerInformation('country', true);
//
//        $result = "{$street}<br />";
//
//        if ($street2) {
//            $result.= "{$street2}<br />";
//        }
//
//        $result.= "{$city}<br />";
//        $result.= "{$country} - {$postal}";
//
//        return $result;
//    }
//
//    /**
//     * Retrieve customer telephone number attached to payment.
//     *
//     * @return mixed
//     */
//    public function getCustomerTelephone()
//    {
//        return $this->getCustomerInformation('telephone');
//    }
//
//    /**
//     * Retrieve customer email attached to payment.
//     *
//     * @return mixed
//     */
//    public function getCustomerEmail()
//    {
//        return $this->getCustomerInformation('email');
//    }
//
//    /**
//     * Retrieve customer information from Resursbank payment.
//     *
//     * @param string $key
//     * @param bool $address
//     * @return mixed
//     */
//    public function getCustomerInformation($key = '', $address = false)
//    {
//        $result = (array) $this->getPaymentInformation('customer');
//
//        if ($address) {
//            $result = (is_array($result) && isset($result['address'])) ?
//                (array) $result['address'] :
//                null;
//        }
//
//        if (!empty($key)) {
//            $result = isset($result[$key]) ? $result[$key] : null;
//        }
//
//        return $result;
//    }
//
//    /**
//     * Retrieve payment information from Resurs Bank.
//     *
//     * @param string $key
//     * @return mixed|null|stdClass
//     */
//    public function getPaymentInformation($key = '')
//    {
//        $result = null;
//
//        $key = (string) $key;
//
//        if ($this->paymentInfo === null) {
//            try {
//                $this->paymentInfo = (array) $this->ecom
//                    ->getConnection($this->getApiCredentials($this->getOrder()))
//                    ->getPayment(
//                        $this->orderHelper->getPaymentId($this->getOrder())
//                    );
//            } catch (Exception $e) {
//                // Something went wrong while getting the payment information
//                // from Resurs Bank. This should not prevent the order page from
//                // rendering though.
//                $this->paymentInfo = [];
//            }
//        }
//
//        if (empty($key)) {
//            $result = $this->paymentInfo;
//        } elseif (is_array($this->paymentInfo) &&
//            isset($this->paymentInfo[$key])
//        ) {
//            $result = $this->paymentInfo[$key];
//        }
//
//        return $result;
//    }
//
//    /**
//     * Retrieve order model instance. Method of retrieval varies depending on
//     * location.
//     *
//     * @return Order
//     * @throws Exception
//     */
//    public function getOrder()
//    {
//        $result = $this->registry->registry('current_order');
//
//        if (!$result) {
//            $result = $this->getOrderByControllerName();
//        }
//
//        if (!($result instanceof Order)) {
//            throw new Exception(__(
//                'Failed to locate order object. Cannot render Resursbank' .
//                ' payment information.'
//            ));
//        }
//
//        return $result;
//    }
//
//    /**
//     * @return Order|null
//     */
//    public function getOrderByControllerName()
//    {
//        $result = null;
//
//        switch ($this->_request->getControllerName()) {
//            case 'order_invoice':
//                $result = $this->getOrderByCurrentInvoice();
//                break;
//            case 'order_creditmemo':
//                $result = $this->getOrderByCurrentCreditmemo();
//                break;
//            case 'order_shipment':
//                $result = $this->getOrderByCurrentShipment();
//                break;
//        }
//
//        return $result;
//    }
//
//    /**
//     * @return Order|null
//     */
//    public function getOrderByCurrentInvoice()
//    {
//        $result = null;
//
//        /** @var Invoice $invoice */
//        $invoice = $this->registry->registry('current_invoice');
//
//        if ($invoice) {
//            $result = $invoice->getOrder();
//        }
//
//        return $result;
//    }
//
//    /**
//     * @return Order|null
//     */
//    public function getOrderByCurrentCreditmemo()
//    {
//        $result = null;
//
//        /** @var Creditmemo $creditmemo */
//        $creditmemo = $this->registry->registry('current_creditmemo');
//
//        if ($creditmemo) {
//            $result = $creditmemo->getOrder();
//        }
//
//        return $result;
//    }
//
//    /**
//     * @return Order|null
//     */
//    public function getOrderByCurrentShipment()
//    {
//        $result = null;
//
//        /** @var Shipment $shipment */
//        $shipment = $this->registry->registry('current_shipment');
//
//        if ($shipment) {
//            $result = $shipment->getOrder();
//        }
//
//        return $result;
//    }
//
//    /**
//     * Retrieve API credentials relative to the store th order was placed in.
//     *
//     * @param Order $order
//     * @return Credentials
//     * @throws Exception
//     */
//    private function getApiCredentials(Order $order)
//    {
//        return $this->config->getCredentials(
//            $order->getStore()->getCode()
//        );
//    }
}
