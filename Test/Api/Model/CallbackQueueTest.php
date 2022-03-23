<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Test\Api\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Authentication\OauthHelper;
use Magento\TestFramework\Authentication\Rest\OauthClient;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\WebapiAbstract;
use Magento\Framework\Webapi\Rest\Request;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Resursbank\Ordermanagement\Helper\Callback as CallbackHelper;

class CallbackQueueTest extends WebapiAbstract
{
    /** @var CallbackHelper|MockObject  */
    private CallbackHelper|MockObject $callbackHelper;

    protected function setUp(): void
    {
        $this->callbackHelper = $this->createMock(CallbackHelper::class);
    }

    /**
     * Assert that we get correct HTTP response code for missing orders
     *
     * @return void
     * @throws LocalizedException
     * @throws \Magento\Framework\Oauth\Exception
     */
    public function testMissingOrder(): void
    {
        // Generate URL
        $paymentId = "foobar1234";
        $digest = strtoupper(sha1($paymentId.$this->callbackHelper->salt()));
        $resourcePath = '/V1/resursbank_ordermanagement/order/update/paymentId/'.$paymentId.'/digest/'.$digest;
        $storeCode = Bootstrap::getObjectManager()
            ->get(StoreManagerInterface::class)
            ->getStore()
            ->getCode();
        $url = rtrim(TESTS_BASE_URL, '/').'/rest/'.$storeCode.'/'.ltrim($resourcePath, '/');

        // Auth stuff, a bit unclear if this is actually needed in testing
        $accessCredentials = OauthHelper::getApiAccessCredentials();
        /** @var OauthClient $oAuthClient */
        $oAuthClient = $accessCredentials['oauth_client'];
        $authHeader = $oAuthClient->buildOauthAuthorizationHeader(
            $url,
            $accessCredentials['key'],
            $accessCredentials['secret'],
            []
        );
        $authHeader = array_merge($authHeader, ['Accept: application/json', 'Content-Type: application/json']);

        // Call API
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_GET);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $authHeader);
        curl_exec($ch);
        $reqInfo = curl_getinfo($ch);
        $httpStatusCode = $reqInfo['http_code'];
        $this->assertEquals(410, $httpStatusCode);
    }

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
