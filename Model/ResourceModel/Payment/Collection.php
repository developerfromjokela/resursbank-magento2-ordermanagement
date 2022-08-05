<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model\ResourceModel\Payment;

use Resursbank\Ordermanagement\Model\Payment as Model;
use Resursbank\Ordermanagement\Model\ResourceModel\Payment as Resource;
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
