<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Helper;

use constant;
use Exception;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Resursbank\Core\Helper\Api;
use Resursbank\Core\Helper\Api\Credentials;

/**
 * @package Resursbank\Ordermanagement\Helper
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
     * Callback constructor.
     * @param Context $context
     * @param Api $api
     * @param Credentials $credentials
     * @param DeploymentConfig $deploymentConfig
     * @param RequestInterface $request
     */
    public function __construct(
        Context $context,
        Api $api,
        Credentials $credentials,
        DeploymentConfig $deploymentConfig,
        RequestInterface $request
    ) {
        $this->api = $api;
        $this->credentials = $credentials;
        $this->deploymentConfig = $deploymentConfig;
        $this->request = $request;

        parent::__construct($context);
    }

    /**
     * Register all callback methods.
     *
     * @param Store $store
     * @return self
     * @throws Exception
     */
    public function register(Store $store): self
    {
        $salt = $this->deploymentConfig->get('crypt/key');

        $connection = $this->api->getConnection(
            $this->credentials->resolveFromConfig($store->getCode())
        );

        // Callback types.
        $types = [
            'unfreeze',
            'booked',
            'update',
            'test'
        ];

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
     * Retrieve callback URL template.
     *
     * @param Store $store
     * @param string $type
     * @return string
     * @throws Exception
     */
    private function urlCallbackTemplate(Store $store, string $type) : string
    {
        $suffix = $type === 'test' ?
            'param1/Alpha/param2/Bravo/param3/Charlie/param4/Delta/param5/Echo/' :
            'paymentId/{paymentId}/digest/{digest}';

        return (
            $store->getBaseUrl(UrlInterface::URL_TYPE_LINK, $this->request->isSecure()) .
            "rest/v1/resursbank_ordermanagement/order/{$type}/{$suffix}"
        );
    }
}
