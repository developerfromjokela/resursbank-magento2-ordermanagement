<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Helper\Ecom;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Resursbank\Core\Helper\Url;

/**
 * Callback helper.
 */
class Callback extends AbstractHelper
{
    /**
     * @param Context $context
     * @param Url $url
     */
    public function __construct(
        Context $context,
        private readonly Url $url
    ) {
        parent::__construct(context: $context);
    }

    /**
     * Retrieve URLs Resurs Bank can use to communicate back to Magento.
     *
     * @param string $type
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getUrl(
        string $type
    ): string {
        return $this->url->getExternalUrl(
            route: "rest/V1/resursbank_ordermanagement/order/$type"
        );
    }
}
