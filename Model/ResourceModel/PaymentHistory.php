<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PaymentHistory extends AbstractDb
{
    /**
     * Initialize Resource model.
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @noinspection MagicMethodsValidityInspection
     */
    protected function _construct(): void
    {
        $this->_init('resursbank_checkout_payment_history', 'id');
    }
}
