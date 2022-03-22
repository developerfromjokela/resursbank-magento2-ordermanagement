<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Test\Api\Model;

use Magento\TestFramework\TestCase\WebapiAbstract;
use Magento\Framework\Webapi\Rest\Request;
use Exception;

class CallbackQueueTest extends WebapiAbstract
{
    /**
     * Assert that the test endpoint exists
     *
     * @return void
     */
    public function testTest(): void
    {
        $params = [
            'param1' => '001',
            'param2' => '002',
            'param3' => '003',
            'param4' => '004',
            'param5' => '005'
        ];
        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/resursbank_ordermanagement/order/test/param1/'.$params['param1'].'/param2/'
                .$params['param2'].'/param3/'.$params['param3'].'/param4/'.$params['param4'].'/param5/'.$params['param5'],
                'httpMethod' => Request::HTTP_METHOD_GET
            ],
        ];
        try {
            $response = $this->_webApiCall($serviceInfo);
        } catch (Exception $e) {
            $response = false;
        }

        // Our response should be an empty array
        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }
}
