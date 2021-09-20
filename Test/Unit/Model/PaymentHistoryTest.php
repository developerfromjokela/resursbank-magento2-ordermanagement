<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Test\Unit\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Model\PaymentHistory;

class PaymentHistoryTest extends TestCase
{

    /**
     * @var PaymentHistory
     */
    private PaymentHistory $paymentHistory;

    /**
     * @var AbstractResource|mixed|MockObject
     */
    private $resourceMock;

    /**
     * @var Context|mixed|MockObject
     */
    private $contextMock;

    /**
     * @var Registry|mixed|MockObject
     */
    private $registryMock;

    /**
     * @var AbstractDb|mixed|MockObject
     */
    private $resourceCollectionMock;

    private array $data = [
        PaymentHistoryInterface::ENTITY_ID => 1,
        PaymentHistoryInterface::ENTITY_PAYMENT_ID => 10012,
        PaymentHistoryInterface::ENTITY_EVENT => 'event',
        PaymentHistoryInterface::ENTITY_USER => 'Stig Helmer',
        PaymentHistoryInterface::ENTITY_EXTRA => 'extra',
        PaymentHistoryInterface::ENTITY_STATE_FROM => 'Oregon',
        PaymentHistoryInterface::ENTITY_STATE_TO => 'Washington',
        PaymentHistoryInterface::ENTITY_STATUS_FROM => 'idle',
        PaymentHistoryInterface::ENTITY_STATUS_TO => 'processing'
    ];

    protected function setUp(): void
    {

        $this->contextMock = $this->createMock(Context::class);
        $this->registryMock = $this->createMock(Registry::class);
        $this->resourceMock = $this->getMockBuilder(AbstractResource::class)
            ->addMethods(['getIdFieldName'])
            ->onlyMethods(['getConnection'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->resourceCollectionMock = $this->createMock(AbstractDb::class);
        $this->paymentHistory = new PaymentHistory(
            $this->contextMock,
            $this->registryMock,
            $this->resourceMock,
            $this->resourceCollectionMock
        );
    }

    /**
     * Assert the data is set when creating an entity with the data
     */
    public function testCreationOfModelWithData()
    {
        $paymentHistory = new PaymentHistory(
            $this->contextMock,
            $this->registryMock,
            $this->resourceMock,
            $this->resourceCollectionMock,
            $this->data
        );

        self::assertSame($this->data, $paymentHistory->getData());
    }

    /**
     * Assert that getter returns the value using the setter
     */
    public function testGetIdReturnsCorrectValue()
    {
        $paymentHistory = new PaymentHistory(
            $this->contextMock,
            $this->registryMock,
            $this->resourceMock,
            $this->resourceCollectionMock,
            $this->data
        );

        self::assertSame($this->data[PaymentHistoryInterface::ENTITY_ID], $paymentHistory->getId());
    }

    /**
     * Assert that getter returns the value using the setter
     */
    public function testGetPaymentIdReturnsCorrectValue()
    {
        self::assertInstanceOf(PaymentHistoryInterface::class, $this->paymentHistory->setPaymentId(123432));
        self::assertSame(123432, $this->paymentHistory->getPaymentId());
    }

    /**
     * Assert that getPaymentId returns int
     */
    public function testGetPaymentIdReturnsInt()
    {
        $this->paymentHistory->setData(PaymentHistoryInterface::ENTITY_PAYMENT_ID, "123123");

        self::assertIsInt($this->paymentHistory->getPaymentId());
        self::assertSame(123123, $this->paymentHistory->getPaymentId());
    }

    /**
     * Assert that default is returned when a value is not set
     */
    public function testGetPaymentIdReturnsDefaultValue()
    {
        self::assertSame(123432, $this->paymentHistory->getPaymentId(123432));
    }

    /**
     * Assert that default is NOT returned when a value is set
     */
    public function testGetPaymentIdDoesNotReturnsDefaultValue()
    {
        $this->paymentHistory->setPaymentId(123432);

        self::assertSame(123432, $this->paymentHistory->getPaymentId(98765431));
    }

    /**
     * Assert that getter returns the value using the setter
     */
    public function testGetEventReturnsCorrectValue()
    {
        self::assertInstanceOf(
            PaymentHistoryInterface::class,
            $this->paymentHistory->setEvent(PaymentHistoryInterface::EVENT_LABELS[PaymentHistoryInterface::EVENT_CALLBACK_BOOKED])
        );

        self::assertSame(PaymentHistoryInterface::EVENT_LABELS[PaymentHistoryInterface::EVENT_CALLBACK_BOOKED], $this->paymentHistory->getEvent());
    }


    /**
     * Assert that default is returned when a value is not set
     */
    public function testGetEventReturnsDefaultValue()
    {
        self::assertSame(
            PaymentHistoryInterface::EVENT_LABELS[PaymentHistoryInterface::EVENT_CALLBACK_BOOKED],
            $this->paymentHistory->getEvent(PaymentHistoryInterface::EVENT_LABELS[PaymentHistoryInterface::EVENT_CALLBACK_BOOKED])
        );
    }

    /**
     * Assert that default is NOT returned when a value is set
     */
    public function testGetEventDoesNotReturnsDefaultValue()
    {
        $this->paymentHistory->setEvent(PaymentHistoryInterface::EVENT_LABELS[PaymentHistoryInterface::EVENT_CALLBACK_BOOKED]);

        self::assertSame(
            PaymentHistoryInterface::EVENT_LABELS[PaymentHistoryInterface::EVENT_CALLBACK_BOOKED],
            $this->paymentHistory->getEvent(PaymentHistoryInterface::EVENT_LABELS[PaymentHistoryInterface::EVENT_CALLBACK_UPDATE])
        );
    }

    /**
     * Assert that getter returns the value using the setter
     */
    public function testGetCreatedAtReturnsCorrectValue()
    {
        self::assertInstanceOf(PaymentHistoryInterface::class, $this->paymentHistory->setCreatedAt("2021-01-01 00:00:00"));

        self::assertSame("2021-01-01 00:00:00", $this->paymentHistory->getCreatedAt());
    }

    /**
     * Assert that default is returned when a value is not set
     */
    public function testGetCreatedAtReturnsDefaultValue()
    {
        self::assertSame("2021-01-01 00:00:00", $this->paymentHistory->getCreatedAt("2021-01-01 00:00:00"));
    }

    /**
     * Assert that default is NOT returned when a value is set
     */
    public function testGetCreatedAtDoesNotReturnsDefaultValue()
    {
        $this->paymentHistory->setCreatedAt("2021-01-01 00:00:00");

        self::assertSame("2021-01-01 00:00:00", $this->paymentHistory->getCreatedAt("2021-12-31 00:00:00"));
    }

    /**
     * Assert that getter returns the value using the setter
     */
    public function testGetStateFromReturnsCorrectValue()
    {
        self::assertInstanceOf(PaymentHistoryInterface::class, $this->paymentHistory->setStateFrom("Oregon"));

        self::assertSame("Oregon", $this->paymentHistory->getStateFrom());
    }

    /**
     * Assert that default is returned when a value is not set
     */
    public function testGetStateFromReturnsDefaultValue()
    {
        self::assertSame("Colorado", $this->paymentHistory->getStateFrom("Colorado"));
    }

    /**
     * Assert that default is NOT returned when a value is set
     */
    public function testGetStateFromDoesNotReturnsDefaultValue()
    {
        $this->paymentHistory->setStateFrom("Colorado");

        self::assertSame("Colorado", $this->paymentHistory->getStateFrom("Oregon"));
    }

    /**
     * Assert that getter returns the value using the setter
     */
    public function testGetStateToReturnsCorrectValue()
    {
        self::assertInstanceOf(PaymentHistoryInterface::class, $this->paymentHistory->setStateTo("Washington"));

        self::assertSame("Washington", $this->paymentHistory->getStateTo());
    }

    /**
     * Assert that getter returns the value using the setter
     */
    public function testGetStatusFromReturnsCorrectValue()
    {
        $this->paymentHistory->setStatusFrom("pending");

        self::assertSame("pending", $this->paymentHistory->getStatusFrom());
    }

    /**
     * Assert that default is returned when a value is not set
     */
    public function testGetStateToReturnsDefaultValue()
    {
        self::assertSame("processing", $this->paymentHistory->getStatusFrom("processing"));
    }

    /**
     * Assert that default is NOT returned when a value is set
     */
    public function testGetStateToDoesNotReturnsDefaultValue()
    {
        $this->paymentHistory->setStatusFrom("pending");

        self::assertSame("pending", $this->paymentHistory->getStatusFrom("complete"));
    }

    /**
     * Assert that getter returns the value using the setter
     */
    public function testGetStatusToReturnsCorrectValue()
    {
        self::assertInstanceOf(PaymentHistoryInterface::class, $this->paymentHistory->setStatusTo("complete"));

        self::assertSame("complete", $this->paymentHistory->getStatusTo());
    }

    /**
     * Assert that default is returned when a value is not set
     */
    public function testGetStatusToReturnsDefaultValue()
    {
        self::assertSame("complete", $this->paymentHistory->getStatusTo("complete"));
    }

    /**
     * Assert that default is NOT returned when a value is set
     */
    public function testGetStatusToDoesNotReturnsDefaultValue()
    {
        $this->paymentHistory->setStatusTo("complete");

        self::assertSame("complete", $this->paymentHistory->getStatusTo("canceled"));
    }

    /**
     * Assert that getter returns the value using the setter
     */
    public function testGetUserReturnsCorrectValue()
    {
        self::assertInstanceOf(
            PaymentHistoryInterface::class,
            $this->paymentHistory->setUser(PaymentHistoryInterface::USER_LABELS[PaymentHistoryInterface::USER_CUSTOMER])
        );

        self::assertSame(PaymentHistoryInterface::USER_LABELS[PaymentHistoryInterface::USER_CUSTOMER], $this->paymentHistory->getUser());
    }

    /**
     * Assert that default is returned when a value is not set
     */
    public function testGetUserReturnsDefaultValue()
    {
        self::assertSame(
            PaymentHistoryInterface::USER_LABELS[PaymentHistoryInterface::USER_CUSTOMER],
            $this->paymentHistory->getUser(PaymentHistoryInterface::USER_LABELS[PaymentHistoryInterface::USER_CUSTOMER])
        );
    }

    /**
     * Assert that default is NOT returned when a value is set
     */
    public function testGetUserDoesNotReturnsDefaultValue()
    {
        $this->paymentHistory->setUser(PaymentHistoryInterface::USER_LABELS[PaymentHistoryInterface::USER_CUSTOMER]);

        self::assertSame(
            PaymentHistoryInterface::USER_LABELS[PaymentHistoryInterface::USER_CUSTOMER],
            $this->paymentHistory->getUser(PaymentHistoryInterface::USER_LABELS[PaymentHistoryInterface::USER_RESURS_BANK])
        );
    }

    /**
     * Assert that getter returns the value using the setter
     */
    public function testGetExtraReturnsCorrectValue()
    {
        self::assertInstanceOf(PaymentHistoryInterface::class, $this->paymentHistory->setExtra("EXTRA!"));

        self::assertSame("EXTRA!", $this->paymentHistory->getExtra());
    }

    /**
     * Assert that default is returned when a value is not set
     */
    public function testGetExtraReturnsDefaultValue()
    {
        self::assertSame("EXTRA!EXTRA!", $this->paymentHistory->getExtra("EXTRA!EXTRA!"));
    }

    /**
     * Assert that default is NOT returned when a value is set
     */
    public function testGetExtraDoesNotReturnsDefaultValue()
    {
        $this->paymentHistory->setExtra("EXTRA!");

        self::assertSame("EXTRA!", $this->paymentHistory->getExtra("EXTRA!EXTRA!"));
    }

}
