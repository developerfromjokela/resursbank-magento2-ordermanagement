<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Block\Adminhtml\Sales\Order\View\Info;

use Exception;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\ViewModel\Adminhtml\Sales\Order\View\Info\PaymentInformation as ViewModel;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;

/**
 * Injects custom HTML containing payment information on order/invoice view.
 *
 * The normal way would be to inject a block through an XML file, but in this
 * case it's proven difficult. It seems we would need to overwrite a core
 * PHTML template to make it work, so we settled using a block + plugin approach
 * so that we wouldn't cause issues with third party extensions.
 *
 * See: Plugin\Block\Adminhtml\Sales\Order\View\AppendPaymentInfo
 */
class PaymentInformation extends Template
{
    /**
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepo;

    /**
     * @var Log
     */
    private $log;

    /**
     * @var CreditmemoRepositoryInterface
     */
    private $creditmemoRepo;

    /**
     * @param Context $context
     * @param ViewModel $viewModel
     * @param InvoiceRepositoryInterface $invoiceRepo
     * @param CreditmemoRepositoryInterface $creditmemoRepo
     * @param Log $log
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        ViewModel $viewModel,
        InvoiceRepositoryInterface $invoiceRepo,
        CreditmemoRepositoryInterface $creditmemoRepo,
        Log $log,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->invoiceRepo = $invoiceRepo;
        $this->creditmemoRepo = $creditmemoRepo;
        $this->log = $log;

        $this->setTemplate(
            'Resursbank_Ordermanagement' .
            '::sales/order/view/info/payment_information.phtml'
        );

        $this->setData('view_model', $viewModel);
        $this->assign('view_model', $this->getData('view_model'));
    }

    /**
     * Uses the request to find the order id. Works if the request includes
     * either an "order_id" number or "invoice_id" number.
     *
     * Returns 0 if an id could not be found.
     *
     * @return int
     */
    public function getOrderIdFromRequest(): int
    {
        $result = 0;

        /** @noinspection BadExceptionsProcessingInspection */
        try {
            $request = $this->getRequest();
            $orderId = (int) $request->getParam('order_id');
            $invoiceId = (int) $request->getParam('invoice_id');
            $creditmemoId = (int) $request->getParam('creditmemo_id');

            if ($orderId !== 0) {
                $result = $orderId;
            } elseif ($invoiceId !== 0) {
                $result = (int) $this->invoiceRepo
                    ->get($invoiceId)
                    ->getOrderId();
            } elseif ($creditmemoId !== 0) {
                $result = (int) $this->creditmemoRepo
                    ->get($creditmemoId)
                    ->getOrderId();
            }
        } catch (Exception $e) {
            $this->log->exception($e);
        }

        return $result;
    }
}
