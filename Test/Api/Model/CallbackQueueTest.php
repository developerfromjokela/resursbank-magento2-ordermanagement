<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Test\Api\Model;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\RuntimeException;
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
    private $callbackHelper;

    protected function setUp(): void
    {
        $this->callbackHelper = $this->createMock(CallbackHelper::class);
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

    /**
     * Assert that we get correct HTTP status code from the test endpoint
     *
     * @return void
     * @throws LocalizedException
     */
    public function testTestHttpCode(): void
    {
        $parameters = [
            'param1' => '001',
            'param2' => '002',
            'param3' => '003',
            'param4' => '004',
            'param5' => '005'
        ];
        $result = $this->callApi('test', 'GET', $parameters);
        $httpStatusCode = $result['requestInfo']['http_code'];

        $this->assertEquals(200, $httpStatusCode);
    }

    /**
     * Assert that we get correct HTTP response code for missing orders
     *
     * @return void
     * @throws LocalizedException
     */
    public function testMissingOrder(): void
    {
        // Generate URL
        $paymentId = "foobar1234";
        $digest = $this->getDigest($paymentId);
        $parameters = [
            'paymentId' => $paymentId,
            'digest' => $digest
        ];
        $result = $this->callApi('update', 'GET', $parameters);
        $httpStatusCode = $result['requestInfo']['http_code'];

        $this->assertEquals(410, $httpStatusCode);
    }

    /**
     * Makes calls to the API
     *
     * @param string $endpoint
     * @param string $method
     * @param array $parameters
     * @return array
     * @throws LocalizedException
     */
    private function callApi(string $endpoint, string $method, array $parameters): array
    {
        $resourcePath = '/V1/resursbank_ordermanagement/order/'.$endpoint; //.'/paymentId/'.$paymentId.'/digest/'.$digest;
        foreach ($parameters as $name => $value) {
            $resourcePath .= '/'.$name.'/'.$value;
        }
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
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, Request::HTTP_METHOD_POST);
        } elseif ($method == 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, Request::HTTP_METHOD_PUT);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, Request::HTTP_METHOD_GET);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $authHeader);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = [];
        $result['response'] = curl_exec($ch);
        $result['requestInfo'] = curl_getinfo($ch);
        return $result;
    }

    /**
     * Generate digest string
     *
     * @param string $paymentId
     * @return string
     * @throws FileSystemException
     * @throws RuntimeException
     */
    private function getDigest(string $paymentId): string
    {
        return strtoupper(sha1($paymentId.$this->callbackHelper->salt()));
    }
}
