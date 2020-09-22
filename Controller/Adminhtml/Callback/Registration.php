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
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
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
     * @var RequestInterface
     */
    private $request;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Registration constructor.
     *
     * @param Log $log
     */
    public function __construct(
        Context $context,
        CallbackHelper $callbackHelper,
        Log $log,
        RequestInterface $request,
        StoreManagerInterface $storeManager
    ) {
        $this->callbackHelper = $callbackHelper;
        $this->log = $log;
        $this->request = $request;
        $this->storeManager = $storeManager;

        parent::__construct($context);
    }

    /**
     * Register callback URLs
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {
        try {
            // Register callback URLs.
            $this->callbackHelper->register(
                $this->getStore()
            );

            // Add success message.
            $this->getMessageManager()->addSuccessMessage(
                'Callback URLs were successfully registered.'
            );
        } catch (Exception $e) {
            var_dump($e); die;
            // Log error.
            $this->log->exception($e);

            // Add error message.
            $this->getMessageManager()->addErrorMessage(
                __('Callback URLs failed to register.')
            );
        }
    }

    /**
     * Get the current store.
     *
     * @return Store
     */
    private function getStore(): Store
    {
        try {
            $storeId = (int) $this->request->getParam('store');

            if ($storeId > 0) {
                $store = $this->storeManager->getStore($storeId);
            } else {
                $store = $this->storeManager->getStore();
            }
        } catch (Exception $e) {
            $this->log->exception($e);
        }

        return $store;
    }
}
