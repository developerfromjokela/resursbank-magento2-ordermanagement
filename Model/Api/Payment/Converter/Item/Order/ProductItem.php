<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model\Api\Payment\Converter\Item\Order;

use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Store\Model\StoreManagerInterface;
use Resursbank\Core\Helper\Config;
use Resursbank\Core\Helper\Log;
use Resursbank\Core\Model\Api\Payment\Converter\Item\AbstractItem;
use Resursbank\Core\Model\Api\Payment\Item;
use Resursbank\Core\Model\Api\Payment\ItemFactory;

/**
 * Product data converter.
 */
class ProductItem extends AbstractItem
{
    /**
     * @param Config $config
     * @param ItemFactory $itemFactory
     * @param Log $log
     * @param StoreManagerInterface $storeManager
     * @param OrderItemInterface $product
     */
    public function __construct(
        Config $config,
        ItemFactory $itemFactory,
        Log $log,
        StoreManagerInterface $storeManager,
        protected readonly OrderItemInterface $product
    ) {
        parent::__construct($config, $itemFactory, $log, $storeManager);
    }

    /**
     * @inheritDoc
     */
    public function getArtNo(): string
    {
        return $this->sanitizeArtNo((string)$this->product->getSku());
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return (string)$this->product->getName();
    }

    /**
     * @inheritDoc
     */
    public function getQuantity(): float
    {
        return (float)$this->product->getQtyOrdered();
    }

    /**
     * @inheritDoc
     */
    public function getUnitAmountWithoutVat(): float
    {
        $product = $this->getOrderItem();

        return $this->sanitizeUnitAmountWithoutVat(
            $this->isBundle() && !$this->hasFixedPrice() ?
                0.0 :
                (float) $product->getPriceInclTax() / (1 + ((float) $product->getTaxPercent() / 100))
        );
    }

    /**
     * @inheritDoc
     */
    public function getVatPct(): int
    {
        $product = $this->getOrderItem();
        $result = $this->isBundle() && !$this->hasFixedPrice() ?
            0.0 :
            (float) $product->getTaxPercent();

        return (int) round($result);
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return Item::TYPE_PRODUCT;
    }

    /**
     * Check for dynamic price.
     *
     * Checks if the product has dynamic pricing by its parent's product
     * options. If a parent can't be found the product itself will be checked.
     *
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function hasDynamicPrice(): bool
    {
        return $this->getOrderItem()->isChildrenCalculated();
    }

    /**
     * Check for fixed price.
     *
     * Checks if the product has fixed pricing by its parent's product
     * options. If a parent can't be found the product itself will be checked.
     *
     * @return bool
     */
    private function hasFixedPrice(): bool
    {
        return !$this->getOrderItem()->isChildrenCalculated();
    }

    /**
     * Check if product is a bundle product.
     *
     * @return bool
     */
    public function isBundle(): bool
    {
        return $this->getOrderItem()->getProductType() === 'bundle';
    }

    /**
     * @inheritDoc
     *
     * @return float
     */
    public function getTotalAmountInclVat(): float
    {
        $result = $this->isBundle() && !$this->hasFixedPrice() ?
            0.0 :
            (float) $this->product->getRowTotalInclTax();
        return round(
            num: $result,
            precision: 2
        );
    }

    /**
     * Fetches the actual order item.
     *
     * @return OrderItemInterface
     */
    public function getOrderItem(): OrderItemInterface
    {
        return $this->product;
    }
}
