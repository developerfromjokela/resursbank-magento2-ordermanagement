<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Block\Adminhtml\Sales\Order\View\Info;

use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\InputException;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Resursbank\Core\Block\Adminhtml\Template;
use Resursbank\Core\Helper\Ecom;
use Resursbank\Core\Helper\Order;
use Resursbank\Core\Helper\PaymentMethods;
use Resursbank\Ordermanagement\Helper\Log;
use RuntimeException;
use Throwable;

/**
 * Basic Ecom widget functionality for order view.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class EcomWidget extends Template
{
    /**
     * @var OrderInterface
     */
    public OrderInterface $order;

    /**
     * @param Context $context
     * @param string $templateDir
     * @param InvoiceRepositoryInterface $invoiceRepo
     * @param CreditmemoRepositoryInterface $creditmemoRepo
     * @param Log $log
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentMethods $paymentMethods
     * @param Order $orderHelper
     * @param Ecom $ecom
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        private readonly string $templateDir,
        private readonly InvoiceRepositoryInterface $invoiceRepo,
        private readonly CreditmemoRepositoryInterface $creditmemoRepo,
        public readonly Log $log,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PaymentMethods $paymentMethods,
        public readonly Order $orderHelper,
        private readonly Ecom $ecom,
        array $data = []
    ) {
        parent::__construct(context: $context, data: $data);

        $order = $this->getOrder();

        if ($order === null || !$this->isEnabled(order: $order)) {
            return;
        }

        $this->order = $order;

        if ($this->ecom->canConnect(scopeCode: $order->getStoreId())) {
            $this->ecom->connectAftershop(entity: $order);
        }

        $this->setTemplate(template: $this->getTemplate());
    }

    /**
     * Get payment id from order.
     *
     * @param OrderInterface $order
     * @return string
     * @throws InputException
     */
    public function getPaymentId(OrderInterface $order): string
    {
        return $this->orderHelper->getPaymentId(order: $order);
    }

    /**
     * Resolve template file based on whether we can utilize Ecom.
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return $this->ecom->canConnect(scopeCode: $this->order->getStoreId()) ?
            'Resursbank_Ordermanagement::sales/order/view/info/' . $this->templateDir . '/ecom.phtml' :
            'Resursbank_Ordermanagement::sales/order/view/info/' . $this->templateDir . '/deprecated.phtml';
    }

    /**
     * Resolve order.
     *
     * Made public to be utilised by plugins.
     *
     * @return OrderInterface|null
     */
    public function getOrder(): ?OrderInterface
    {
        $result = null;
        $id = $this->getOrderIdFromRequest();

        try {
            if ($id > 0) {
                $result = $this->orderRepository->get(id: $id);
            }
        } catch (Throwable $error) {
            $this->log->exception(error: $error);
        }

        return $result;
    }

    /**
     * Get order id from request - if request includes "order_id" or "invoice_id" number. Returns 0 if not found.
     *
     * @return int
     */
    private function getOrderIdFromRequest(): int
    {
        $result = 0;

        try {
            $request = $this->getRequest();
            $orderId = (int)$request->getParam(key: 'order_id');
            $invoiceId = (int)$request->getParam(key: 'invoice_id');
            $creditmemoId = (int)$request->getParam(key: 'creditmemo_id');

            if ($orderId !== 0) {
                $result = $orderId;
            } elseif ($invoiceId !== 0) {
                $result = (int)$this->invoiceRepo
                    ->get(id: $invoiceId)
                    ->getOrderId();
            } elseif ($creditmemoId !== 0) {
                $result = (int)$this->creditmemoRepo
                    ->get(id: $creditmemoId)
                    ->getOrderId();
            }
        } catch (Exception $e) {
            $this->log->exception(error: $e);
        }

        return $result;
    }

    /**
     * Check if widget is enabled.
     *
     * @param OrderInterface $order
     * @return bool
     */
    private function isEnabled(
        OrderInterface $order
    ): bool {
        if (!($order->getPayment() instanceof OrderPaymentInterface)) {
            throw new RuntimeException(
                message: 'Missing payment data on order ' . $order->getIncrementId()
            );
        }

        return $this->paymentMethods->isResursBankMethod(
            code: $order->getPayment()->getMethod()
        );
    }
}
