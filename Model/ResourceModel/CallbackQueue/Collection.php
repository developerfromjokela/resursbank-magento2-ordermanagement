<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model\ResourceModel\CallbackQueue;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection as AbstractCollection;
use Resursbank\Ordermanagement\Model\CallbackQueue as Model;
use Resursbank\Ordermanagement\Model\ResourceModel\CallbackQueue as Resource;

class Collection extends AbstractCollection
{
    /**
     * Initialize collection model.
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @noinspection MagicMethodsValidityInspection
     * @noinspection PhpMissingParentCallCommonInspection
     */
    protected function _construct()
    {
        $this->_init(
            Model::class,
            Resource::class);
    }
}
