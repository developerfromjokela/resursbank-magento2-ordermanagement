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
use Resursbank\Ordermanagement\Api\Data\PaymentInterface;
use Resursbank\Ordermanagement\Api\PaymentRepositoryInterface;
use Resursbank\Ordermanagement\Model\ResourceModel\Payment as ResourceModel;
use Resursbank\Ordermanagement\Model\ResourceModel\Payment\CollectionFactory;

class PaymentRepository implements PaymentRepositoryInterface
{
    /**
     * @var ResourceModel
     */
    private ResourceModel $resourceModel;

    /**
     * @var PaymentFactory
     */
    private PaymentFactory $phFactory;

    /**
     * @var FilterProcessor
     */
    private FilterProcessor $filterProcessor;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @param ResourceModel $resourceModel
     * @param PaymentFactory $phFactory
     * @param PaymentSearchResultsInterfaceFactory $srFactory
     * @param FilterProcessor $filterProcessor
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        ResourceModel $resourceModel,
        PaymentFactory $phFactory,
        PaymentSearchResultsInterfaceFactory $srFactory,
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
        PaymentInterface $entry
    ): PaymentInterface {
        /** @var Payment $entry */
        $this->resourceModel->save($entry);

        return $entry;
    }

    /**
     * @inheritDoc
     */
    public function delete(
        PaymentInterface $entry
    ): bool {
        /** @var Payment $entry */
        $this->resourceModel->delete($entry);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getByReference(
        string $reference
    ): PaymentInterface {
        return $this->get($this->get($identifier));
    }

    /**
     * @inheritDoc
     */
    public function get(
        int $identifier
    ): PaymentInterface {
        $ = $this->phFactory->create();

        $this->resourceModel->load($, $identifier);

        if (!$->getId()) {
            throw new NoSuchEntityException(
                __(
                    'Unable to find payment  entry with ID %1',
                    $identifier
                )
            );
        }

        return $;
    }

    /**
     * @inheritDoc
     */
    public function getList(
        SearchCriteriaInterface $searchCriteria
    ): PaymentSearchResultsInterface {
        $collection = $this->collectionFactory->create();

        $this->filterProcessor->process($searchCriteria, $collection);

        $collection->load();

        return $this->srFactory->create()
            ->setSearchCriteria($searchCriteria)
            ->setItems($collection->getItems())
            ->setTotalCount($collection->getSize());
    }
}
