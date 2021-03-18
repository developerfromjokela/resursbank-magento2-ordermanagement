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
use Magento\Framework\App\ResponseInterface;
use Resursbank\Core\Helper\Store as StoreHelper;
use Resursbank\Ordermanagement\Helper\Callback as CallbackHelper;
use Resursbank\Ordermanagement\Helper\Log;

class Registration extends Action
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
     * @var StoreHelper
     */
    private $storeHelper;

    /**
     * Registration constructor.
     *
     * @param Context $context
     * @param CallbackHelper $callbackHelper
     * @param Log $log
     * @param StoreHelper $storeHelper
     */
    public function __construct(
        Context $context,
        CallbackHelper $callbackHelper,
        Log $log,
        StoreHelper $storeHelper
    ) {
        $this->callbackHelper = $callbackHelper;
        $this->log = $log;
        $this->storeHelper = $storeHelper;

        parent::__construct($context);
    }

    /**
     * Register callback URLs
     *
     * @return ResponseInterface
     */
    public function execute(): ResponseInterface
    {
        try {
            // Register callback URLs.
            $this->callbackHelper->register(
                $this->storeHelper->fromRequest()
            );

            // Add success message.
            $this->getMessageManager()->addSuccessMessage(
                __('Callback URLs were successfully registered.')->getText()
            );
        } catch (Exception $e) {
            // Log error.
            $this->log->exception($e);

            // Add error message.
            $this->getMessageManager()->addErrorMessage(
                __('Callback URLs failed to register.')->getText()
            );
        }

        return $this->_redirect($this->_redirect->getRefererUrl());
    }
}
