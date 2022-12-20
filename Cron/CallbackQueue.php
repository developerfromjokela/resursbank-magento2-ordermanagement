<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ResursBank\Ordermanagement\Cron;

use Exception;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Model\CallbackFactory as CallbackFactory;
use Resursbank\Ordermanagement\Model\ResourceModel\CallbackQueue as ResourceModel;
use Resursbank\Ordermanagement\Model\ResourceModel\CallbackQueue\CollectionFactory as CollectionFactory;

class CallbackQueue {
    /** @var Log  */
    protected Log $logger;

    /** @var CollectionFactory  */
    private CollectionFactory $collectionFactory;

    /** @var ResourceModel  */
    private ResourceModel $resourceModel;

    /** @var CallbackFactory  */
    private CallbackFactory $callbackFactory;

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

    public function execute() {
        $this->logger->info("Running CallbackQueue cron job");

        // NOTE: Two minute delay to mitigate potential race conditions.
        $queuedCallbacks = $this->collectionFactory
            ->create()
            ->setPageSize(10)
            ->setCurPage(1)
            ->setOrder('id', 'ASC')
            ->addFieldToFilter(
                'created_at',
                ['to' => date('Y-m-d H:i:s', time()-120)]
            )
            ->load();

        foreach ($queuedCallbacks as $queuedCallback) {
            $this->logger->info("Handling queued callback...");

            $callback = $this->callbackFactory->create();

            if (!empty($queuedCallback->getType()) && in_array($queuedCallback->getType(), ['unfreeze', 'booked', 'update', 'test'])) {
                $method = $queuedCallback->getType();
                $callback->$method($queuedCallback->getPaymentId(), $queuedCallback->getDigest());
                try {
                    $this->resourceModel->delete($queuedCallback);
                } catch (Exception $e) {
                    $this->logger->exception($e);
                }
            }
        }
    }
}
