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
use Resursbank\Ordermanagement\Model\CallbackQueueFactory;
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
 *
 */
class CallbackQueueTest extends TestCase
{
    /** @var CallbackQueue */
    private CallbackQueue $callbackQueue;

    /** @var Context|\PHPUnit\Framework\MockObject\MockObject  */
    private $contextMock;

    /** @var Registry|\PHPUnit\Framework\MockObject\MockObject  */
    private $registryMock;

    /** @var OrderInterface|\PHPUnit\Framework\MockObject\MockObject  */
    private $orderInterfaceMock;

    /** @var TypeListInterface|\PHPUnit\Framework\MockObject\MockObject  */
    private $typeListInterfaceMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject|Scope  */
    private $scopeMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject|Log  */
    private $logMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject|CallbackLog  */
    private $callbackLogMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject|ConfigHelper  */
    private $configHelperMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject|CallbackHelper  */
    private $callbackHelperMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject|CallbackQueueFactory  */
    private $callbackQueueFactoryMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject|CollectionFactory  */
    private $collectionFactoryMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject|CallbackQueueResourceModel  */
    private $callbackQueueResourceModelMock;

    /** @var AbstractResource|\PHPUnit\Framework\MockObject\MockObject  */
    private $abstractResourceMock;

    /** @var AbstractDb|\PHPUnit\Framework\MockObject\MockObject  */
    private $resourceCollectionMock;

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
