<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model;

use Magento\Framework\Data\Form\FormKey;
use Magento\Store\Model\StoreManagerInterface;
use phpDocumentor\Reflection\Types\Resource_;
use Resursbank\Ordermanagement\Api\Data\CallbackQueueInterface;
use Resursbank\Ordermanagement\Model\ResourceModel\CallbackQueue as ResourceModel;
//use Resursbank\Ordermanagement\Model\ResourceModel\CallbackQueue\Collection;
//use Resursbank\Ordermanagement\Model\ResourceModel\CallbackQueue\CollectionFactory as CollectionFactory;
use Magento\Framework\Model\AbstractModel as AbstractModel;

class CallbackQueue extends AbstractModel implements CallbackQueueInterface
{
    /**
     * @var CollectionFactory
     */
    //private CollectionFactory $callbackQueueFactory;

    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }

/*    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        CollectionFactory $callbackQueueFactory,
        array $data = [])
    {
        $this->callbackQueueFactory = $callbackQueueFactory;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }*/

    /**
     * Fetch the oldest items from the queue
     *
     * @param int $count
     * @return Collection
     */
    /*public function getOldest(int $count): Collection
    {
        $data = $this->callbackQueueFactory
            ->create()
            ->setPageSize(3)
            ->setCurPage(1)
            ->setOrder('id', 'DESC')
            ->load();
    }*/

}
