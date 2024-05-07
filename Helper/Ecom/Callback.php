<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Helper\Ecom;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Resursbank\Core\Helper\Scope;

/**
 * Callback helper.
 */
class Callback extends AbstractHelper
{
    /**
     * @param Context $context
     * @param RequestInterface $request
     * @param Scope $scope
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        private readonly RequestInterface $request,
        private readonly Scope $scope,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct(context: $context);
    }

    /**
     * Resolve active store.
     *
     * @return Store
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getStore(): Store
    {
        $store = $this->storeManager->getStore(
            storeId: $this->scope->getId(type: ScopeInterface::SCOPE_STORE)
        );

        if (!($store instanceof Store)) {
            throw new LocalizedException(
                phrase: __('$store not an instance of Store')
            );
        }

        return $store;
    }

    /**
     * Retrieve callback URL.
     *
     * @param string $type
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getUrl(
        string $type
    ): string {
        /** @noinspection PhpRedundantOptionalArgumentInspection */
        return $this->getStore()->getBaseUrl(
            type: UrlInterface::URL_TYPE_LINK,
            secure: $this->request->isSecure()
        ) . "rest/V1/resursbank_ordermanagement/order/$type";
    }
}
