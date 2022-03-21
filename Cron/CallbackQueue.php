<?php

namespace ResursBank\Ordermanagement\Cron;

use Prophecy\Call\Call;
use Psr\Log\LoggerInterface as LoggerInterface;
use Resursbank\Ordermanagement\Model\Callback;
use Resursbank\Ordermanagement\Model\CallbackFactory as CallbackFactory;
use Resursbank\Ordermanagement\Model\ResourceModel\CallbackQueue\CollectionFactory as CollectionFactory;

class CallbackQueue {
    protected LoggerInterface $logger;
    private CollectionFactory $collectionFactory;
    private CallbackFactory $callbackFactory;

    public function __construct(
        LoggerInterface $logger,
        CollectionFactory $collectionFactory,
        CallbackFactory $callbackFactory
    ) {
        $this->logger = $logger;
        $this->collectionFactory = $collectionFactory;
        $this->callbackFactory = $callbackFactory;
    }

    public function execute() {
        $this->logger->info("Running CallbackQueue cron job");
        $queuedCallbacks = $this->collectionFactory
            ->create()
            ->setPageSize(3)
            ->setCurPage(1)
            ->setOrder('id', 'DESC')
            ->load();

        foreach ($queuedCallbacks as $queuedCallback) {
            $this->logger->info("Handling queued callback...");
            //$this->logger->info($callback->getId());
            /** @var Callback $callback */
            $callback = $this->callbackFactory->create();
            $method = false;
            switch($queuedCallback->getType()) {
                case 'unfreeze':
                    $method = 'unfreeze';
                    break;
                case 'booked':
                    $method = 'booked';
                    break;
                case 'update':
                    $method = 'update';
                    break;
                case 'test':
                    $method = 'test';
                    break;
                default:
                    break;
            }

            if ($method) {
                $callback->$method();
            }
        }
    }
}
