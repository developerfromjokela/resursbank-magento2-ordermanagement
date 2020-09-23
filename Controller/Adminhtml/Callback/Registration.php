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
use Magento\Framework\App\ResponseInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Resursbank\Core\Helper\Url;
use Resursbank\Ordermanagement\Helper\Callback as CallbackHelper;
use Resursbank\Ordermanagement\Helper\Log;

/**
 * @package Resursbank\Ordermanagement\Helper
 */
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
     * @var Url
     */
    private $urlHelper;

    /**
     * Registration constructor.
     *
     * @param Context $context
     * @param CallbackHelper $callbackHelper
     * @param Log $log
     * @param RequestInterface $request
     * @param StoreManagerInterface $storeManager
     * @param Url $urlHelper
     */
    public function __construct(
        Context $context,
        CallbackHelper $callbackHelper,
        Log $log,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        Url $urlHelper
    ) {
        $this->callbackHelper = $callbackHelper;
        $this->log = $log;
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->urlHelper = $urlHelper;

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
                $this->getStore()
            );

            // Add success message.
            $this->getMessageManager()->addSuccessMessage(
                __('Callback URLs were successfully registered.')
            );
        } catch (Exception $e) {
            // Log error.
            $this->log->exception($e);

            // Add error message.
            $this->getMessageManager()->addErrorMessage(
                __('Callback URLs failed to register.')
            );
        }

        // Redirect back to the config section.
        $this->_redirect($this->urlHelper->getAdminUrl(
            'admin/system_config/edit/section/payment'
        ));
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
