<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Test\Unit\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use PHPUnit\Framework\TestCase;
use Resursbank\Core\Helper\Api;
use Resursbank\Core\Helper\PaymentMethods;
use Resursbank\Ordermanagement\Helper\Admin;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Config;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class ApiPaymentTest extends TestCase
{
    /**
     * @var ApiPayment
     */
    private ApiPayment $apiPayment;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $contextMock = $this->createMock(Context::class);
        $adminMock = $this->createMock(Admin::class);
        $apiMock = $this->createMock(Api::class);
        $configMock = $this->createMock(Config::class);
        $paymentMethodsMock = $this->createMock(PaymentMethods::class);
        $orderRepositoryMock = $this->createMock(OrderRepository::class);

        $paymentMethodsMock->method('isResursBankMethod')->with('resursbank_default')->willReturn(true);

        $this->apiPayment = new ApiPayment(
            $contextMock,
            $adminMock,
            $apiMock,
            $configMock,
            $paymentMethodsMock,
            $orderRepositoryMock
        );
    }

    /**
     * Asserts that validateOrder returns true with correct data.
     */
    public function testValidateOrderReturnsTrueOnValidOrder(): void
    {
        $orderPaymentInterfaceMock = $this->createMock(OrderPaymentInterface::class);
        $orderPaymentInterfaceMock->method('getMethod')->willReturn('resursbank_default');

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getGrandTotal')->willReturn(100.00);
        $orderMock->method('getPayment')->willReturn($orderPaymentInterfaceMock);

        $orderPaymentInterfaceMock->method('getMethod')->willReturn($orderPaymentInterfaceMock);
        self::assertTrue($this->apiPayment->validateOrder($orderMock));
    }

    /**
     * Asserts that validateOrder returns false if grand total is zero.
     */
    public function testValidateOrderReturnsFalseOnZeroTotal(): void
    {
        $orderPaymentInterfaceMock = $this->createMock(OrderPaymentInterface::class);
        $orderPaymentInterfaceMock->method('getMethod')->willReturn('resursbank_default');

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getGrandTotal')->willReturn(0);
        $orderMock->method('getPayment')->willReturn($orderPaymentInterfaceMock);

        $orderPaymentInterfaceMock->method('getMethod')->willReturn($orderPaymentInterfaceMock);
        self::assertFalse($this->apiPayment->validateOrder($orderMock));
    }

    /**
     * Asserts that validateOrder returns false if payment method is not resurs.
     */
    public function testValidateOrderReturnsFalseIfNonResursPayment(): void
    {
        $orderPaymentInterfaceMock = $this->createMock(OrderPaymentInterface::class);
        $orderPaymentInterfaceMock->method('getMethod')->willReturn('checkmo');

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getGrandTotal')->willReturn(0);
        $orderMock->method('getPayment')->willReturn($orderPaymentInterfaceMock);

        $orderPaymentInterfaceMock->method('getMethod')->willReturn($orderPaymentInterfaceMock);
        self::assertFalse($this->apiPayment->validateOrder($orderMock));
    }
}
