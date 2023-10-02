<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model;

use Exception;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Model\AbstractModel as AbstractModel;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Resursbank\Ordermanagement\Api\CallbackQueueInterface;
use Resursbank\Ordermanagement\Exception\CallbackValidationException;
use Resursbank\Ordermanagement\Exception\OrderNotFoundException;
use Resursbank\Ordermanagement\Model\ResourceModel\CallbackQueue as ResourceModel;
use Resursbank\Ordermanagement\Model\ResourceModel\CallbackQueue\Collection as Collection;
use Resursbank\Ordermanagement\Model\ResourceModel\CallbackQueue\CollectionFactory as CollectionFactory;
use Resursbank\Core\Helper\Scope;
use Resursbank\Ordermanagement\Helper\Config as ConfigHelper;
use Resursbank\Ordermanagement\Helper\Callback as CallbackHelper;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Helper\CallbackLog;
use Resursbank\Ordermanagement\Model\ResourceModel\CallbackQueue as CallbackQueueResourceModel;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CallbackQueue extends AbstractModel implements CallbackQueueInterface
{
    /** @var OrderInterface  */
    private OrderInterface $orderInterface;

    /** @var ConfigHelper  */
    private ConfigHelper $config;

    /** @var Scope  */
    private Scope $scope;

    /** @var Log  */
    private Log $log;

    /** @var CallbackHelper  */
    private CallbackHelper $callbackHelper;

    /** @var CallbackLog  */
    private CallbackLog $callbackLog;

    /** @var TypeListInterface  */
    private TypeListInterface $cacheTypeList;

    /** @var CallbackQueueFactory  */
    private CallbackQueueFactory $callbackQueueFactory;

    /** @var CollectionFactory  */
    private CollectionFactory $cqCollectionFactory;

    /** @var CallbackQueueResourceModel  */
    private CallbackQueueResourceModel $cqResource;

    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }

    /**
     * @param Context $context
     * @param Registry $registry
     * @param OrderInterface $orderInterface
     * @param TypeListInterface $cacheTypeList
     * @param Scope $scope
     * @param Log $log
     * @param CallbackLog $callbackLog
     * @param ConfigHelper $config
     * @param CallbackHelper $callbackHelper
     * @param CallbackQueueFactory $callbackQueueFactory
     * @param CollectionFactory $cqCollectionFactory
     * @param CallbackQueueResourceModel $cqResource
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Registry $registry,
        OrderInterface $orderInterface,
        TypeListInterface $cacheTypeList,
        Scope $scope,
        Log $log,
        CallbackLog $callbackLog,
        ConfigHelper $config,
        CallbackHelper $callbackHelper,
        CallbackQueueFactory $callbackQueueFactory,
        CollectionFactory $cqCollectionFactory,
        CallbackQueueResourceModel $cqResource,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->orderInterface = $orderInterface;
        $this->scope = $scope;
        $this->log = $log;
        $this->config = $config;
        $this->callbackHelper = $callbackHelper;
        $this->callbackLog = $callbackLog;
        $this->cacheTypeList = $cacheTypeList;
        $this->callbackQueueFactory = $callbackQueueFactory;
        $this->cqCollectionFactory = $cqCollectionFactory;
        $this->cqResource = $cqResource;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * @inheritDoc
     */
    public function unfreeze(string $paymentId, string $digest): void
    {
        try {
            $this->checkRequest($paymentId, $digest);
            $this->addToQueue('unfreeze', $paymentId, $digest);
        } catch (OrderNotFoundException $e) {
            $this->handleError($e);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * @inheritDoc
     */
    public function booked(string $paymentId, string $digest): void
    {
        try {
            $this->checkRequest($paymentId, $digest);
            $this->addToQueue('booked', $paymentId, $digest);
        } catch (OrderNotFoundException $e) {
            $this->handleError($e);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * @inheritDoc
     */
    public function update(string $paymentId, string $digest): void
    {
        try {
            $this->checkRequest($paymentId, $digest);
            $this->addToQueue('update', $paymentId, $digest);
        } catch (OrderNotFoundException $e) {
            $this->handleError($e);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * @inheritDoc
     */
    public function test(string $param1, string $param2, string $param3, string $param4, string $param5): void
    {
        try {
            $this->logIncoming('test', '', '');

            $this->config->setCallbackTestReceivedAt(
                (int) $this->scope->getId(),
                $this->scope->getType()
            );

            // Clear the config cache so this value show up.
            $this->cacheTypeList->cleanType('config');
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Performs error checking and validation that we want to do before adding a request to the queue.
     *
     * @param string $paymentId
     * @param string $digest
     * @return void
     * @throws LocalizedException
     * @throws OrderNotFoundException
     * @throws CallbackValidationException
     */
    private function checkRequest(string $paymentId, string $digest): void
    {
        // Required for PHPStan to validate that loadByIncrementId() exists as
        // a method.
        if (!($this->orderInterface instanceof Order)) {
            throw new LocalizedException(
                __('orderInterface not an instance of Order')
            );
        }

        /** @var Order $order */
        $order = $this->orderInterface->loadByIncrementId($paymentId);

        if (!$order->getId()) {
            throw new OrderNotFoundException(
                __('Failed to locate order ' . $paymentId)
            );
        }

        $this->validate($paymentId, $digest);
        $this->logIncoming('unfreeze', $paymentId, $digest);
    }

    /**
     * Validate the digest.
     *
     * @param string $paymentId
     * @param string $digest
     * @throws CallbackValidationException
     * @throws FileSystemException
     * @throws RuntimeException
     */
    private function validate(
        string $paymentId,
        string $digest
    ): void {
        $ourDigest = strtoupper(
            sha1($paymentId . $this->callbackHelper->salt())
        );

        if ($ourDigest !== $digest) {
            throw new CallbackValidationException(
                __("Invalid digest - PaymentId: $paymentId. Digest: $digest")
            );
        }
    }

    /**
     * @param Exception $exception
     * @return void
     * @throws WebapiException
     */
    private function handleError(
        Exception $exception
    ): void {
        $this->log->exception($exception);

        if ($exception instanceof CallbackValidationException) {
            throw new WebapiException(
                __($exception->getMessage()),
                0,
                WebapiException::HTTP_NOT_ACCEPTABLE
            );
        } elseif ($exception instanceof OrderNotFoundException) {
            throw new WebapiException(
                __($exception->getMessage()),
                0,
                410
            );
        }
    }

    /**
     * Add item to callback queue
     *
     * @param string $type
     * @param string $paymentId
     * @param string $digest
     * @return void
     * @throws AlreadyExistsException
     */
    private function addToQueue(string $type, string $paymentId, string $digest): void
    {
        $item = $this->callbackQueueFactory->create();
        $item->setData('type', $type);
        $item->setData('payment_id', $paymentId);
        $item->setData('digest', $digest);

        $this->cqResource->save($item);
    }

    /**
     * Fetches a batch of queued callbacks
     *
     * @param int $count
     * @return Collection
     */
    public function getOldest(int $count): Collection
    {
        /** @var Collection $callbacks */
        return $this->cqCollectionFactory
            ->create()
            ->setPageSize($count)
            ->setCurPage(1)
            ->setOrder('id', 'ASC')
            ->load();
    }

    /**
     * Log incoming callbacks.
     *
     * @param string $type
     * @param string $paymentId
     * @param string $digest
     */
    private function logIncoming(
        string $type,
        string $paymentId,
        string $digest
    ): void {
        $this->callbackLog->info(
            "[$type] - PaymentId: $paymentId. Digest: $digest"
        );
    }
}
