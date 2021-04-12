<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Controller\Adminhtml\Callback;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\ResponseInterface;
use Resursbank\Core\Helper\Scope;
use Resursbank\Ordermanagement\Helper\Callback as CallbackHelper;
use Resursbank\Ordermanagement\Helper\Config;
use Resursbank\Ordermanagement\Helper\Log;
use Magento\Framework\App\RequestInterface;

class Test extends Action
{
    /**
     * @var CallbackHelper
     */
    private $callbackHelper;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Log
     */
    private $log;

    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @var Scope
     */
    private $scope;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @param Context $context
     * @param CallbackHelper $callbackHelper
     * @param Config $config
     * @param Log $log
     * @param TypeListInterface $cacheTypeList
     */
    public function __construct(
        Context $context,
        CallbackHelper $callbackHelper,
        Config $config,
        Log $log,
        TypeListInterface $cacheTypeList,
        Scope $scope,
        RequestInterface $request
    ) {
        $this->callbackHelper = $callbackHelper;
        $this->config = $config;
        $this->log = $log;
        $this->cacheTypeList = $cacheTypeList;
        $this->scope = $scope;

        parent::__construct($context);
        $this->request = $request;
    }

    /**
     * Test callback URLs.
     *
     * @return ResponseInterface
     */
    public function execute(): ResponseInterface
    {
        /** @noinspection BadExceptionsProcessingInspection */
        try {
            // Trigger the test-callback.
            $this->callbackHelper->test();

            /**
             * NOTE: typecasting should be safe since this is executed from the
             * config where the store/website parameter will always be numeric.
             */
            $this->config->setCallbackTestTriggeredAt(
                (int) $this->scope->getId(),
                $this->scope->getType()
            );

            $this->cacheTypeList->cleanType('config');

            // Add success message.
            $this->getMessageManager()->addSuccessMessage(
                __('Test callback was sent')->getText()
            );
        } catch (Exception $e) {
            // Log error.
            $this->log->exception($e);

            // Add error message.
            $this->getMessageManager()->addErrorMessage(
                __('Test callback could not be triggered')->getText()
            );
        }

        return $this->_redirect($this->_redirect->getRefererUrl());
    }
}
