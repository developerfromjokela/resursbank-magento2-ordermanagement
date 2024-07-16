<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Controller\Adminhtml\Callback;

use Exception;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Resursbank\Core\Helper\Scope;
use Resursbank\Ordermanagement\Helper\Callback as CallbackHelper;
use Resursbank\Ordermanagement\Helper\Config;
use Resursbank\Ordermanagement\Helper\Log;

/**
 * Force the "test" callback to be triggered at Resurs Bank, to check whether
 * callbacks are properly accepted by the client.
 */
class Test implements HttpGetActionInterface
{
    /**
     * @var CallbackHelper
     */
    private CallbackHelper $callbackHelper;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Log
     */
    private Log $log;

    /**
     * @var TypeListInterface
     */
    private TypeListInterface $cacheTypeList;

    /**
     * @var Scope
     */
    private Scope $scope;

    /**
     * @var ManagerInterface
     */
    private ManagerInterface $message;

    /**
     * @var ResultFactory
     */
    private ResultFactory $resultFactory;

    /**
     * @var RedirectInterface
     */
    private RedirectInterface $redirect;

    /**
     * @param CallbackHelper $callbackHelper
     * @param Config $config
     * @param Log $log
     * @param TypeListInterface $cacheTypeList
     * @param Scope $scope
     * @param ManagerInterface $message
     * @param ResultFactory $resultFactory
     * @param RedirectInterface $redirect
     */
    public function __construct(
        CallbackHelper $callbackHelper,
        Config $config,
        Log $log,
        TypeListInterface $cacheTypeList,
        Scope $scope,
        ManagerInterface $message,
        ResultFactory $resultFactory,
        RedirectInterface $redirect
    ) {
        $this->callbackHelper = $callbackHelper;
        $this->config = $config;
        $this->log = $log;
        $this->cacheTypeList = $cacheTypeList;
        $this->scope = $scope;
        $this->message = $message;
        $this->redirect = $redirect;
        $this->resultFactory = $resultFactory;
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
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

            /**
             * Clear cache (to reflect the new "test callback triggered at" date
             * when the config page reloads).
             */
            $this->cacheTypeList->cleanType('config');

            $this->message->addSuccessMessage(
                __('rb-test-callback-was-sent')->getText()
            );
        } catch (Exception $e) {
            $this->log->exception($e);

            $this->message->addErrorMessage(
                __('rb-test-callback-could-not-be-triggered')->getText()
            );
        }

        /** @var Redirect $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $result->setUrl($this->redirect->getRefererUrl());
    }
}
