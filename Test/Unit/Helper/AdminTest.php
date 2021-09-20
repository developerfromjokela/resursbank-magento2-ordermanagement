<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Test\Unit\Helper;

use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\Helper\Context;
use PHPUnit\Framework\TestCase;
use Resursbank\Ordermanagement\Helper\Admin;

/**
 * Test cases designed for Resursbank\Ordermanagement\Helper\Admin
 */
class AdminTest extends TestCase
{

    /**
     * @var Admin
     */
    private Admin $admin;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $contextMock = $this->createMock(Context::class);
        $sessionMock = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->addMethods(['getUser'])
            ->getMock();

        $this->admin = new Admin(
            $contextMock,
            $sessionMock
        );
    }

    /**
     * Assert that the getUser method will return "Anonymous" if there is no
     * user in the session.
     *
     * @return void
     */
    public function testUsernameWorksWithoutUser(): void
    {
        static::assertEquals('Anonymous', $this->admin->getUserName());
    }
}
