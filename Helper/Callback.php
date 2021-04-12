<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Helper;

use function constant;
use Exception;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Resursbank\Core\Helper\Api;
use Resursbank\Core\Helper\Api\Credentials;
use Resursbank\Core\Helper\Scope;
use stdClass;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
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
     * @var Scope
     */
    private $scope;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

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
        Log $log,
        Scope $scope,
        StoreManagerInterface $storeManager
    ) {
        $this->api = $api;
        $this->credentials = $credentials;
        $this->deploymentConfig = $deploymentConfig;
        $this->request = $request;
        $this->log = $log;
        $this->scope = $scope;
        $this->storeManager = $storeManager;

        parent::__construct($context);
    }

    /**
     * Register all callback methods.
     *
     * @return self
     * @throws ValidatorException
     * @throws Exception
     */
    public function register(): self
    {
        $salt = $this->salt();

        $connection = $this->api->getConnection(
            $this->credentials->resolveFromConfig(
                $this->scope->getId(),
                $this->scope->getType()
            )
        );

        // Callback types.
        $types = ['unfreeze', 'booked', 'update', 'test'];

        foreach ($types as $type) {
            $connection->setRegisterCallback(
                constant(
                    'Resursbank\RBEcomPHP\RESURS_CALLBACK_TYPES::' .
                    strtoupper($type)
                ),
                $this->urlCallbackTemplate($type),
                ['digestSalt' => $salt]
            );
        }

        return $this;
    }

    /**
     * Fetch registered callbacks.
     *
     * @return array<stdClass>
     * @throws Exception
     */
    public function fetch(): array
    {
        $result = [];

        try {
            $credentials = $this->credentials->resolveFromConfig(
                $this->scope->getId(),
                $this->scope->getType()
            );

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
     * @return void
     * @throws ValidatorException
     * @throws Exception
     */
    public function test(): void
    {
        $connection = $this->api->getConnection(
            $this->credentials->resolveFromConfig(
                $this->scope->getId(),
                $this->scope->getType()
            )
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
     * @param string $type
     * @return string
     * @throws NoSuchEntityException
     */
    private function urlCallbackTemplate(
        string $type
    ) : string {
        $suffix = $type === 'test' ?
            'param1/a/param2/b/param3/scopeType/default/scopeId/0' :
            'paymentId/{paymentId}/digest/{digest}';

        /** @noinspection PhpUndefinedMethodInspection */
        return (
            $this->storeManager->getStore( /** @phpstan-ignore-line */
                $this->scope->getId(ScopeInterface::SCOPE_STORES)
            )->getBaseUrl(
                UrlInterface::URL_TYPE_LINK,
                $this->request->isSecure()
            ) . "rest/V1/resursbank_ordermanagement/order/{$type}/{$suffix}"
        );
    }
}
