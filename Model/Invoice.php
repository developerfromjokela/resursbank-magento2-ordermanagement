<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model;

use Magento\Sales\Model\Order\Invoice as InvoiceModel;

/**
 * Data model to keep an invoice object in memory. We utilise this to transfer
 * an invoice using DI during its creation to our capture command from the event
 * where we can collect it.
 */
class Invoice
{
    /**
     * @var null|InvoiceModel
     */
    private ?InvoiceModel $invoice = null;

    /**
     * @param InvoiceModel $invoice
     * @return void
     */
    public function setInvoice(InvoiceModel $invoice): void
    {
        $this->invoice = $invoice;
    }

    /**
     * @return InvoiceModel|null
     */
    public function getInvoice(): ?InvoiceModel
    {
        return $this->invoice;
    }
}
