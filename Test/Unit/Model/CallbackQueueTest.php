<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Test\Unit\Model;

use Grpc\Call;
use PHPUnit\Framework\TestCase;
use Resursbank\Ordermanagement\Model\CallbackQueue;
use PHPUnit\Framework\MockObject\MockObject;
use Exception;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Resursbank\Ordermanagement\Api\CallbackQueueInterface;
use Resursbank\Ordermanagement\Model\CallbackQueueFactory;
use Resursbank\Ordermanagement\Model\ResourceModel\CallbackQueue\CollectionFactory as CollectionFactory;
use Resursbank\Core\Helper\Scope;
use Resursbank\Ordermanagement\Helper\Config as ConfigHelper;
use Resursbank\Ordermanagement\Helper\Callback as CallbackHelper;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Helper\CallbackLog;
use Resursbank\Ordermanagement\Model\ResourceModel\CallbackQueue as CallbackQueueResourceModel;

/**
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CallbackQueueTest extends TestCase
{
    /** @var CallbackQueue */
    private CallbackQueue $callbackQueue;

    /** @var Context|MockObject  */
    private $contextMock;

    /** @var Registry|MockObject  */
    private $registryMock;

    /** @var OrderInterface|MockObject  */
    private $orderInterfaceMock;

    /** @var TypeListInterface|MockObject  */
    private $typeListInterfaceMock;

    /** @var MockObject|Scope  */
    private $scopeMock;

    /** @var MockObject|Log  */
    private $logMock;

    /** @var MockObject|CallbackLog  */
    private $callbackLogMock;

    /** @var MockObject|ConfigHelper  */
    private $configHelperMock;

    /** @var MockObject|CallbackHelper  */
    private $callbackHelperMock;

    /** @var MockObject|CallbackQueueFactory  */
    private $callbackQueueFactoryMock;

    /** @var MockObject|CollectionFactory  */
    private $collectionFactoryMock;

    /** @var MockObject|CallbackQueueResourceModel  */
    private $callbackQueueResourceModelMock;

    /** @var AbstractResource|MockObject  */
    private $abstractResourceMock;

    /** @var AbstractDb|MockObject  */
    private $resourceCollectionMock;

    /** @var array */
    private array $data = [
        CallbackQueueInterface::ENTITY_ID => 1,
        CallbackQueueInterface::ENTITY_CREATED_AT => '2022-03-22 12:01:01',
        CallbackQueueInterface::ENTITY_PAYMENT_ID => '0000004',
        CallbackQueueInterface::ENTITY_TYPE => 'booked'
    ];

    /**
     * Set up all the mock objects
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->registryMock = $this->createMock(Registry::class);
        $this->orderInterfaceMock = $this->createMock(OrderInterface::class);
        $this->typeListInterfaceMock = $this->createMock(TypeListInterface::class);
        $this->scopeMock = $this->createMock(Scope::class);
        $this->logMock = $this->createMock(Log::class);
        $this->callbackLogMock = $this->createMock(CallbackLog::class);
        $this->configHelperMock = $this->createMock(ConfigHelper::class);
        $this->callbackHelperMock = $this->createMock(CallbackHelper::class);
        $this->callbackQueueFactoryMock = $this->createMock(CallbackQueueFactory::class);
        $this->collectionFactoryMock = $this->createMock(CollectionFactory::class);
        $this->callbackQueueResourceModelMock = $this->createMock(CallbackQueueResourceModel::class);
        $this->abstractResourceMock = $this->getMockBuilder(AbstractResource::class)
            ->addMethods(['getIdFieldName'])
            ->getMockForAbstractClass();
        $this->resourceCollectionMock = $this->createMock(AbstractDb::class);

        $this->callbackQueue = new CallbackQueue(
            $this->contextMock,
            $this->registryMock,
            $this->orderInterfaceMock,
            $this->typeListInterfaceMock,
            $this->scopeMock,
            $this->logMock,
            $this->callbackLogMock,
            $this->configHelperMock,
            $this->callbackHelperMock,
            $this->callbackQueueFactoryMock,
            $this->collectionFactoryMock,
            $this->callbackQueueResourceModelMock,
            $this->abstractResourceMock,
            $this->resourceCollectionMock
        );
    }

    /**
     * Assert that the data is set when creating a new instance
     *
     * @return void
     */
    public function testCreationOfModelWithData(): void
    {
        $callbackQueue = new CallbackQueue(
            $this->contextMock,
            $this->registryMock,
            $this->orderInterfaceMock,
            $this->typeListInterfaceMock,
            $this->scopeMock,
            $this->logMock,
            $this->callbackLogMock,
            $this->configHelperMock,
            $this->callbackHelperMock,
            $this->callbackQueueFactoryMock,
            $this->collectionFactoryMock,
            $this->callbackQueueResourceModelMock,
            $this->abstractResourceMock,
            $this->resourceCollectionMock,
            $this->data
        );
        self::assertSame($this->data, $callbackQueue->getData());
    }
}
