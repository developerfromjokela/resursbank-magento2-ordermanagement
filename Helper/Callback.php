<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Helper;

use Exception;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Resursbank\Core\Helper\Api;
use Resursbank\Core\Helper\Api\Credentials;
use Resursbank\Ordermanagement\Helper\Log;
use function constant;

class Callback extends AbstractHelper
{
    /**
     * @var Api
     */
    private $api;

    /**
     * @var Credentials
     */
    private $credentials;

    /**
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var Log
     */
    private $log;

    /**
     * @param Context $context
     * @param Api $api
     * @param Credentials $credentials
     * @param DeploymentConfig $deploymentConfig
     * @param RequestInterface $request
     * @param Log $log
     */
    public function __construct(
        Context $context,
        Api $api,
        Credentials $credentials,
        DeploymentConfig $deploymentConfig,
        RequestInterface $request,
        Log $log
    ) {
        $this->api = $api;
        $this->credentials = $credentials;
        $this->deploymentConfig = $deploymentConfig;
        $this->request = $request;
        $this->log = $log;

        parent::__construct($context);
    }

    /**
     * Register all callback methods.
     *
     * @param StoreInterface $store
     * @return self
     * @throws ValidatorException
     * @throws Exception
     */
    public function register(StoreInterface $store): self
    {
        $salt = $this->salt();

        $connection = $this->api->getConnection(
            $this->credentials->resolveFromConfig($store->getCode())
        );

        // Callback types.
        $types = ['unfreeze', 'booked', 'update', 'test'];

        foreach ($types as $type) {
            $connection->setRegisterCallback(
                constant(
                    'Resursbank\RBEcomPHP\RESURS_CALLBACK_TYPES::' .
                    strtoupper($type)
                ),
                $this->urlCallbackTemplate($store, $type),
                ['digestSalt' => $salt]
            );
        }

        return $this;
    }

    /**
     * Fetch registered callbacks.
     *
     * @return array
     */
    public function fetch(): array
    {
        $result = [];

        try {
            $credentials = $this->credentials->resolveFromConfig();

            if ($this->credentials->hasCredentials($credentials)) {
                $result = $this->api
                    ->getConnection($credentials)
                    ->getCallBacksByRest();
            }
        } catch (Exception $e) {
            $this->log->exception($e);
        }

        return $result;
    }

    /**
     * Trigger the test-callback.
     *
     * @param StoreInterface $store
     * @return void
     * @throws ValidatorException
     * @throws Exception
     */
    public function test(StoreInterface $store): void
    {
        $connection = $this->api->getConnection(
            $this->credentials->resolveFromConfig($store->getCode())
        );

        $connection->triggerCallback();
    }

    /**
     * Get the salt key.
     *
     * @return string
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function salt(): string
    {
        return $this->deploymentConfig->get('crypt/key');
    }

    /**
     * Retrieve callback URL template.
     *
     * @param StoreInterface $store
     * @param string $type
     * @return string
     */
    private function urlCallbackTemplate(
        StoreInterface $store,
        string $type
    ) : string {
        $suffix = $type === 'test' ?
            'param1/a/param2/b/param3/c/param4/d/param5/e/' :
            'paymentId/{paymentId}/digest/{digest}';

        /** @noinspection PhpUndefinedMethodInspection */
        return (
            $store->getBaseUrl( /** @phpstan-ignore-line */
                UrlInterface::URL_TYPE_LINK,
                $this->request->isSecure()
            ) . "rest/V1/resursbank_ordermanagement/order/{$type}/{$suffix}"
        );
    }
}
