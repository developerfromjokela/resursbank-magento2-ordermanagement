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
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Resursbank\Ordermanagement\Helper\Callback as CallbackHelper;
use Resursbank\Ordermanagement\Helper\Log;

/**
 * Register callbacks at Resurs Bank. Callbacks are executed when events occur
 * on a payment.
 */
class Registration implements HttpGetActionInterface
{
    /**
     * @var CallbackHelper
     */
    private $callbackHelper;

    /**
     * @var Log
     */
    private $log;

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
     * @param Log $log
     * @param ManagerInterface $message
     * @param ResultFactory $resultFactory
     * @param RedirectInterface $redirect
     */
    public function __construct(
        CallbackHelper $callbackHelper,
        Log $log,
        ManagerInterface $message,
        ResultFactory $resultFactory,
        RedirectInterface $redirect
    ) {
        $this->callbackHelper = $callbackHelper;
        $this->log = $log;
        $this->message = $message;
        $this->resultFactory = $resultFactory;
        $this->redirect = $redirect;
    }

    /**
     * Register callback URLs
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        try {
            // Register callback URLs.
            $this->callbackHelper->register();

            // Add success message.
            $this->message->addSuccessMessage(
                __('Callback URLs were successfully registered.')->getText()
            );
        } catch (Exception $e) {
            // Log error.
            $this->log->exception($e);

            // Add error message.
            $this->message->addErrorMessage(
                __('Callback URLs failed to register.')->getText()
            );
        }

        /** @var Redirect $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $result->setUrl($this->redirect->getRefererUrl());
    }
}
