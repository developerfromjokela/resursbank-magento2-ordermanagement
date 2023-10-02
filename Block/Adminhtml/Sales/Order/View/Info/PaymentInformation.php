<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Block\Adminhtml\Sales\Order\View\Info;

use Exception;
use JsonException;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\InputException;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Resursbank\Core\Helper\Order;
use ReflectionException;
use Resursbank\Core\Block\Adminhtml\Template;
use Resursbank\Core\Helper\Config;
use Resursbank\Core\Helper\Mapi;
use Resursbank\Core\Helper\PaymentMethods;
use Resursbank\Core\Helper\Scope;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Module\Payment\Widget\PaymentInformation as PaymentInformationWidget;
use Resursbank\Ecom\Module\PaymentMethod\Enum\CurrencyFormat;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\ViewModel\Adminhtml\Sales\Order\View\Info\PaymentInformation as ViewModel;
use RuntimeException;
use Throwable;

/**
 * Injects custom HTML containing payment information on order/invoice view.
 *
 * The normal way would be to inject a block through an XML file, but in this
 * case it's proven difficult. It seems we would need to overwrite a core
 * PHTML template to make it work, so we settled using a block + plugin approach
 * so that we wouldn't cause issues with third party extensions.
 *
 * See: Plugin\Block\Adminhtml\Sales\Order\View\AppendPaymentInfo
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PaymentInformation extends Template
{
    /**
     * @var OrderInterface
     */
    public OrderInterface $order;

    /**
     * @var PaymentInformationWidget|null
     */
    private ?PaymentInformationWidget $widget = null;

    /**
     * @param Context $context
     * @param ViewModel $viewModel
     * @param InvoiceRepositoryInterface $invoiceRepo
     * @param CreditmemoRepositoryInterface $creditmemoRepo
     * @param Log $log
     * @param Config $config
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentMethods $paymentMethods
     * @param Order $orderHelper
     * @param Scope $scope
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        ViewModel $viewModel,
        private readonly InvoiceRepositoryInterface $invoiceRepo,
        private readonly CreditmemoRepositoryInterface $creditmemoRepo,
        private readonly Log $log,
        private readonly Config $config,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PaymentMethods $paymentMethods,
        private readonly Order $orderHelper,
        private readonly Scope $scope,
        array $data = []
    ) {
        parent::__construct(context: $context, data: $data);

        $order = $this->getOrder();

        if ($order === null || !$this->isEnabled(order: $order)) {
            return;
        }

        $this->order = $order;

        $viewModel->setOrder(order: $this->order);

        $this->setData(key: 'view_model', value: $viewModel);
        $this->assign(
            key: 'view_model',
            value: $this->getData(key: 'view_model')
        );

        $template = $this->config->isMapiActive(
            scopeCode: $this->order->getStoreId()
        ) ? 'mapi' : 'deprecated';

        $this->setTemplate(
            template: 'Resursbank_Ordermanagement' .
            "::sales/order/view/info/payment-information/$template.phtml"
        );
    }

    /**
     * Get payment information widget.
     *
     * @return string
     */
    public function getWidgetHtml(): string
    {
        $result = '';

        try {
            $result = $this->getWidget()->content;
        } catch (Throwable $error) {
            $this->log->exception(error: $error);
        }

        return $result;
    }

    /**
     * Get payment information css.
     *
     * @return string
     */
    public function getWidgetCss(): string
    {
        $result = '';

        try {
            $result = $this->getWidget()->css;
        } catch (Throwable $error) {
            $this->log->exception(error: $error);
        }

        return $result;
    }

    /**
     * Resolve order.
     *
     * @return OrderInterface|null
     */
    private function getOrder(): ?OrderInterface
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

    /**
     * Get the payment information widget.
     *
     * @return PaymentInformationWidget
     * @throws JsonException
     * @throws InputException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws FilesystemException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     */
    private function getWidget(): PaymentInformationWidget
    {
        if ($this->widget !== null) {
            return $this->widget;
        }

        $paymentId = Mapi::getPaymentId(
            order: $this->order,
            orderHelper: $this->orderHelper,
            config: $this->config,
            scope: $this->scope
        );

        $this->widget = new PaymentInformationWidget(
            paymentId: $paymentId,
            currencySymbol: 'kr',
            currencyFormat: CurrencyFormat::SYMBOL_LAST
        );

        return $this->widget;
    }
}
