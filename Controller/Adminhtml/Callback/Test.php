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
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Resursbank\Core\Helper\Url;
use Resursbank\Ordermanagement\Exception\CallbackException;
use Resursbank\Ordermanagement\Helper\Callback as CallbackHelper;
use Resursbank\Ordermanagement\Helper\Log;

class Test extends Action
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
     * @return void
     */
    public function execute(): void
    {
        try {
            // Trigger the test-callback.
            $this->callbackHelper->test(
                $this->getStore()
            );

            // Add success message.
            $this->getMessageManager()->addSuccessMessage(
                __('Test callback was sent')
            );
        } catch (Exception $e) {
            // Log error.
            $this->log->exception($e);

            // Add error message.
            $this->getMessageManager()->addErrorMessage(
                __('Test callback could not be triggered')
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
     * @return StoreInterface
     * @throws CallbackException
     * @todo Detta borde abstraheras från här och Registration.
     *
     */
    private function getStore(): StoreInterface
    {
        $store = null;

        try {
            $storeId = (int) $this->request->getParam('store');

            $store = $storeId > 0 ?
                $this->storeManager->getStore($storeId) :
                $store = $this->storeManager->getStore();
        } catch (Exception $e) {
            $this->log->exception($e);
        }

        if (!($store instanceof StoreInterface)) {
            throw new CallbackException('Failed to obtain store.');
        }

        return $store;
    }
}
