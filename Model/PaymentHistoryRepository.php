<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Api\PaymentHistoryRepositoryInterface;
use Resursbank\Ordermanagement\Model\ResourceModel\PaymentHistory as ResourceModel;

class PaymentHistoryRepository implements PaymentHistoryRepositoryInterface
{
    /**
     * @var ResourceModel
     */
    private $resourceModel;

    /**
     * @var PaymentHistoryFactory
     */
    private $phFactory;

    /**
     * PaymentHistoryRepository constructor.
     * @param ResourceModel $resourceModel
     * @param PaymentHistoryFactory $phFactory
     */
    public function __construct(
        ResourceModel $resourceModel,
        PaymentHistoryFactory $phFactory
    ) {
        $this->resourceModel = $resourceModel;
        $this->phFactory = $phFactory;
    }

    /**
     * @inheritDoc
     */
    public function save(
        PaymentHistoryInterface $entry
    ): PaymentHistoryInterface {
        $this->resourceModel->save($entry);

        return $entry;
    }

    /**
     * @inheritDoc
     */
    public function delete(PaymentHistoryInterface $entry): bool
    {
        $this->resourceModel->delete($entry);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $identifier): bool
    {
        return $this->delete($this->get($identifier));
    }

    /**
     * @inheritDoc
     */
    public function get(int $identifier): PaymentHistoryInterface
    {
        $history = $this->phFactory->create();
        $history->getResource()->load($history, $identifier);

        if (!$history->getId()) {
            throw new NoSuchEntityException(
                __('Unable to find payment history entry with ID %1', $identifier)
            );
        }

        return $history;
    }
}
