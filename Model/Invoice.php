<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model;

use Magento\Sales\Model\Order\Invoice as InvoiceModel;

/**
 * This data model lets us transfer the invoice created during capture to our
 * corresponding command (Magento will not natively supply the invoice created
 * to the capture command, and without it, we cannot support partial debit).
 *
 * This class is injected using DI in our observer of the capture process (which
 * does get the invoice supplied) (see Observer/TrackInvoice) and in our capture
 * command class (see Gateway/Command/Capture). This works since Magento by
 * default handles DI as singletons.
 *
 * Effectively this simply lets us access the invoice created during capture in
 * our command class, allowing us to support partial debit while being compliant
 * with the payment gateway specification.
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
