<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb as AbstractDb;

class CallbackQueue extends AbstractDb
{
    /**
     * Initialize Resource model.
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @noinspection MagicMethodsValidityInspection
     */
    protected function _construct()
    {
        $this->_init('resursbank_checkout_callback_queue', 'id');
    }
}
