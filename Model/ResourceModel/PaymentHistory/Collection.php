<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model\ResourceModel\PaymentHistory;

use Resursbank\Ordermanagement\Model\PaymentHistory as Model;
use Resursbank\Ordermanagement\Model\ResourceModel\PaymentHistory as Resource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * Initialize collection model.
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @noinspection MagicMethodsValidityInspection
     * @noinspection PhpMissingParentCallCommonInspection
     */
    protected function _construct(): void
    {
        $this->_init(Model::class, Resource::class);
    }
}
