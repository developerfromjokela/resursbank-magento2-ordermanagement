<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order\Invoice as InvoiceModel;
use Resursbank\Core\Exception\InvalidDataException;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Model\Invoice;

/**
 * Add created invoice to Magentos registry when it's created. This provides
 * access to the invoice from our capture command, so we can accurately compile
 * data for our outgoing API calls to debit payment.
 */
class TrackInvoice implements ObserverInterface
{
    /**
     * @var Log
     */
    private Log $log;

    /**
     * @var Invoice
     */
    private Invoice $invoice;

    /**
     * @param Log $log
     * @param Invoice $invoice
     */
    public function __construct(
        Log $log,
        Invoice $invoice
    ) {
        $this->log = $log;
        $this->invoice = $invoice;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            $invoice = $observer->getData('invoice');

            if (!($invoice instanceof InvoiceModel)) {
                throw new InvalidDataException(__('Missing invoice data.'));
            }

            $this->invoice->setInvoice($invoice);
        } catch (Exception $e) {
            $this->log->exception($e);
        }
    }
}
