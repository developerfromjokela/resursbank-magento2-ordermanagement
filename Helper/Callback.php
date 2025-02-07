<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Helper;

use Magento\Framework\Exception\LocalizedException;
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
use Magento\Store\Model\Store;
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
    private Api $api;

    /**
     * @var Credentials
     */
    private Credentials $credentials;

    /**
     * @var DeploymentConfig
     */
    private DeploymentConfig $deploymentConfig;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var Log
     */
    private Log $log;

    /**
     * @var Scope
     */
    private Scope $scope;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param Context $context
     * @param Api $api
     * @param Credentials $credentials
     * @param DeploymentConfig $deploymentConfig
     * @param RequestInterface $request
     * @param Log $log
     * @param Scope $scope
     * @param StoreManagerInterface $storeManager
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

        // Unregister annulment, automatic_fraud_control and finalization.
        $connection->unregisterEventCallback(14, true);

        foreach ($types as $type) {
            $connection->setRegisterCallback(
                constant(
                    'Resursbank\Ecommerce\Types\Callback::' .
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

        // NOTE: The three 's','a','d' values exist because the API expects five
        // values.
        $connection->triggerCallback([
            $this->scope->getId(),
            $this->scope->getType(),
            's',
            'a',
            'd'
        ]);
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
     * @throws LocalizedException
     */
    private function urlCallbackTemplate(
        string $type
    ) : string {
        $store = $this->storeManager->getStore(
            $this->scope->getId(ScopeInterface::SCOPE_STORE)
        );

        if (!($store instanceof Store)) {
            throw new LocalizedException(__('$store not an instance of Store'));
        }

        $suffix = $type === 'test' ?
            'param1/a/param2/b/param3/c/param4/d/param5/e' :
            'paymentId/{paymentId}/digest/{digest}';

        return (
            $store->getBaseUrl(
                UrlInterface::URL_TYPE_LINK,
                $this->request->isSecure()
            ) . "rest/V1/resursbank_ordermanagement/order/$type/$suffix"
        );
    }
}
