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
     * @var ManagerInterface
     */
    private $message;

    /**
     * @var ResultFactory
     */
    private $resultFactory;

    /**
     * @var RedirectInterface
     */
    private $redirect;

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
     * Test callback URLs.
     *
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

            $this->cacheTypeList->cleanType('config');

            // Add success message.
            $this->message->addSuccessMessage(
                __('Test callback was sent')->getText()
            );
        } catch (Exception $e) {
            // Log error.
            $this->log->exception($e);

            // Add error message.
            $this->message->addErrorMessage(
                __('Test callback could not be triggered')->getText()
            );
        }

        /** @var Redirect $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $result->setUrl($this->redirect->getRefererUrl());
    }
}
