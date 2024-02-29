<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ResursBank\Ordermanagement\Cron;

use Exception;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Model\CallbackFactory;
use Resursbank\Ordermanagement\Model\ResourceModel\CallbackQueue as ResourceModel;
use Resursbank\Ordermanagement\Model\ResourceModel\CallbackQueue\CollectionFactory;
use function in_array;

/**
 * Handles callback queue processing.
 */
class CallbackQueue
{
    /** @var Log  */
    protected Log $logger;

    /** @var CollectionFactory  */
    private CollectionFactory $collectionFactory;

    /** @var ResourceModel  */
    private ResourceModel $resourceModel;

    /** @var CallbackFactory  */
    private CallbackFactory $callbackFactory;

    /**
     * @param Log $logger
     * @param CollectionFactory $collectionFactory
     * @param ResourceModel $resourceModel
     * @param CallbackFactory $callbackFactory
     */
    public function __construct(
        Log $logger,
        CollectionFactory $collectionFactory,
        ResourceModel $resourceModel,
        CallbackFactory $callbackFactory
    ) {
        $this->logger = $logger;
        $this->collectionFactory = $collectionFactory;
        $this->resourceModel = $resourceModel;
        $this->callbackFactory = $callbackFactory;
    }

    /**
     * Cron job entry point.
     *
     * @return void
     */
    public function execute(): void
    {
        $this->logger->info(text: 'Running CallbackQueue cron job');

        // NOTE: Two minute delay to mitigate potential race conditions.
        $queuedCallbacks = $this->collectionFactory
            ->create()
            ->setPageSize(size: 10)
            ->setCurPage(page: 1)
            ->setOrder(field: 'id', direction: 'ASC')
            ->addFieldToFilter(
                field: 'created_at',
                condition: ['to' => date(format: 'Y-m-d H:i:s', timestamp: time()-120)]
            )
            ->load();

        foreach ($queuedCallbacks as $queuedCallback) {
            $this->logger->info(text: 'Handling queued callback...');

            $callback = $this->callbackFactory->create();

            if (!empty($queuedCallback->getType()) && in_array(
                needle: $queuedCallback->getType(),
                haystack: ['unfreeze', 'booked', 'update', 'test']
            )
            ) {
                $method = $queuedCallback->getType();
                $callback->$method($queuedCallback->getPaymentId(), $queuedCallback->getDigest());
                try {
                    $this->resourceModel->delete(object: $queuedCallback);
                } catch (Exception $e) {
                    $this->logger->exception(error: $e);
                }
            }
        }
    }
}
