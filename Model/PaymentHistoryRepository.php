<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessor\FilterProcessor;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Api\Data\PaymentHistorySearchResultsInterface;
use Resursbank\Ordermanagement\Api\Data\PaymentHistorySearchResultsInterfaceFactory;
use Resursbank\Ordermanagement\Api\PaymentHistoryRepositoryInterface;
use Resursbank\Ordermanagement\Model\ResourceModel\PaymentHistory as ResourceModel;
use Resursbank\Ordermanagement\Model\ResourceModel\PaymentHistory\CollectionFactory;

class PaymentHistoryRepository implements PaymentHistoryRepositoryInterface
{
    /**
     * @var ResourceModel
     */
    private ResourceModel $resourceModel;

    /**
     * @var PaymentHistoryFactory
     */
    private PaymentHistoryFactory $phFactory;

    /**
     * @var FilterProcessor
     */
    private FilterProcessor $filterProcessor;

    /**
     * @var PaymentHistorySearchResultsInterfaceFactory
     */
    private PaymentHistorySearchResultsInterfaceFactory $srFactory;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @param ResourceModel $resourceModel
     * @param PaymentHistoryFactory $phFactory
     * @param PaymentHistorySearchResultsInterfaceFactory $srFactory
     * @param FilterProcessor $filterProcessor
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        ResourceModel $resourceModel,
        PaymentHistoryFactory $phFactory,
        PaymentHistorySearchResultsInterfaceFactory $srFactory,
        FilterProcessor $filterProcessor,
        CollectionFactory $collectionFactory
    ) {
        $this->resourceModel = $resourceModel;
        $this->phFactory = $phFactory;
        $this->srFactory = $srFactory;
        $this->filterProcessor = $filterProcessor;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @inheritDoc
     */
    public function save(
        PaymentHistoryInterface $entry
    ): PaymentHistoryInterface {
        /** @var PaymentHistory $entry */
        $this->resourceModel->save($entry);

        return $entry;
    }

    /**
     * @inheritDoc
     */
    public function delete(
        PaymentHistoryInterface $entry
    ): bool {
        /** @var PaymentHistory $entry */
        $this->resourceModel->delete($entry);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(
        int $identifier
    ): bool {
        return $this->delete($this->get($identifier));
    }

    /**
     * @inheritDoc
     */
    public function get(
        int $identifier
    ): PaymentHistoryInterface {
        $history = $this->phFactory->create();

        $this->resourceModel->load($history, $identifier);

        if (!$history->getId()) {
            throw new NoSuchEntityException(
                __(
                    'Unable to find payment history entry with ID %1',
                    $identifier
                )
            );
        }

        return $history;
    }

    /**
     * @inheritDoc
     */
    public function getList(
        SearchCriteriaInterface $searchCriteria
    ): PaymentHistorySearchResultsInterface {
        $collection = $this->collectionFactory->create();

        $this->filterProcessor->process($searchCriteria, $collection);

        $collection->load();

        return $this->srFactory->create()
            ->setSearchCriteria($searchCriteria)
            ->setItems($collection->getItems())
            ->setTotalCount($collection->getSize());
    }
}
