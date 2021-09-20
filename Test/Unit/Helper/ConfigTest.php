<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Test\Unit\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Resursbank\Ordermanagement\Helper\Config;
use function PHPUnit\Framework\assertTrue;

class ConfigTest extends TestCase
{

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var MockObject|ScopeConfigInterface
     */
    private $scopeConfigInterfaceMock;

    protected function setUp(): void
    {
        $contextMock = $this->createMock(Context::class);
        $writerInterfaceMock = $this->createMock(WriterInterface::class);
        $this->scopeConfigInterfaceMock = $this->createMock(ScopeConfigInterface::class);

        $this->config = new Config(
            $this->scopeConfigInterfaceMock,
            $writerInterfaceMock,
            $contextMock
        );
    }

    /**
     * Assert that isAfterShopEnabled return the correct value
     */
    public function testIsAfterShopEnabled()
    {
        $this->scopeConfigInterfaceMock->method('isSetFlag')->with('resursbank/aftershop/enabled')->willReturn(true);

        self::assertTrue($this->config->isAfterShopEnabled(""));
    }

    /**
     * Assert that isAfterShopEnabled returns true on store specific store if enabled
     */
    public function testIsAfterShopEnabledReturnsTrueForSpecificStore()
    {
        $this->scopeConfigInterfaceMock->method('isSetFlag')->with('resursbank/aftershop/enabled',ScopeInterface::SCOPE_STORES,  "en")->willReturn(true);

        self::assertTrue($this->config->isAfterShopEnabled("en"));
    }

    /**
     * Assert that isAfterShopEnabled returns false if disabled on specific store
     */
    public function testIsAfterShopEnabledReturnsFalseForSpecificStore()
    {
        $this->scopeConfigInterfaceMock->method('isSetFlag')->with('resursbank/aftershop/enabled',ScopeInterface::SCOPE_STORES,  "se")->willReturn(false);

        self::assertFalse($this->config->isAfterShopEnabled("se"));
    }

    /**
     * Assert that isAfterShopEnabled returns true for specific store if disabled on other
     */
    public function testIsAfterShopEnabledReturnsFalseForSpecificStoreIfEnableOnOther()
    {
        $this->scopeConfigInterfaceMock->method('isSetFlag')->withConsecutive(
            ['resursbank/aftershop/enabled',ScopeInterface::SCOPE_STORES,  "en"],
            ['resursbank/aftershop/enabled',ScopeInterface::SCOPE_STORES,  "se"],
        )->willReturnOnConsecutiveCalls(true, false);

        self::assertTrue($this->config->isAfterShopEnabled("en"));
        self::assertFalse($this->config->isAfterShopEnabled("se"));
    }

    /**
     * Assert that isAfterShopEnabled return the correct value
     */
    public function testGetCallbackTestTriggeredAt()
    {
        $this->scopeConfigInterfaceMock->method('getValue')->with('resursbank/callbacks/test_triggered_at')->willReturn("2021-01-01 00:00:00");

        self::assertEquals("2021-01-01 00:00:00", $this->config->getCallbackTestTriggeredAt(""));
    }

    /**
     * Assert that isAfterShopEnabled returns true for specific store if disabled on other
     */
    public function testGetCallbackTestTriggeredAtReturnsCorrectValueForSpecificStores()
    {
        $this->scopeConfigInterfaceMock->method('getValue')->withConsecutive(
            ['resursbank/callbacks/test_triggered_at',ScopeInterface::SCOPE_STORES,  "en"],
            ['resursbank/callbacks/test_triggered_at',ScopeInterface::SCOPE_STORES,  "se"],
        )->willReturnOnConsecutiveCalls("2021-01-01 00:00:00", "2021-01-01 23:59:59");

        self::assertEquals("2021-01-01 00:00:00", $this->config->getCallbackTestTriggeredAt("en"));
        self::assertEquals("2021-01-01 23:59:59", $this->config->getCallbackTestTriggeredAt("se"));
    }

    /**
     * Assert that isAfterShopEnabled return the correct value
     */
    public function testGetCallbackTestReceivedAt()
    {
        $this->scopeConfigInterfaceMock->method('getValue')->with('resursbank/callbacks/test_received_at')->willReturn("2021-01-01 00:00:00");

        self::assertEquals("2021-01-01 00:00:00", $this->config->getCallbackTestReceivedAt(""));
    }

    /**
     * Assert that isAfterShopEnabled returns true for specific store if disabled on other
     */
    public function testGetCallbackTestReceivedAtReturnsCorrectValueForSpecificStores()
    {
        $this->scopeConfigInterfaceMock->method('getValue')->withConsecutive(
            ['resursbank/callbacks/test_received_at',ScopeInterface::SCOPE_STORES,  "en"],
            ['resursbank/callbacks/test_received_at',ScopeInterface::SCOPE_STORES,  "se"],
        )->willReturnOnConsecutiveCalls("2021-01-01 00:00:00", "2021-01-01 23:59:59");

        self::assertEquals("2021-01-01 00:00:00", $this->config->getCallbackTestReceivedAt("en"));
        self::assertEquals("2021-01-01 23:59:59", $this->config->getCallbackTestReceivedAt("se"));
    }
}
