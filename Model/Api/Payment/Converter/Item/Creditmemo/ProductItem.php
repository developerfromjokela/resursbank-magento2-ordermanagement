<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model\Api\Payment\Converter\Item\Creditmemo;

use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Model\StoreManagerInterface;
use Resursbank\Core\Exception\PaymentDataException;
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
     * @var CreditmemoItem
     */
    protected CreditmemoItem $product;

    /**
     * @param Config $config
     * @param ItemFactory $itemFactory
     * @param Log $log
     * @param CreditmemoItem $product
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Config $config,
        ItemFactory $itemFactory,
        Log $log,
        CreditmemoItem $product,
        StoreManagerInterface $storeManager
    ) {
        $this->product = $product;

        parent::__construct($config, $itemFactory, $log, $storeManager);
    }

    /**
     * Fetch the actual credit memo item.
     *
     * @return CreditmemoItem
     */
    public function getCreditmemoItem(): CreditmemoItem
    {
        return $this->product;
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
     *
     * @return string
     */
    public function getDescription(): string
    {
        return (string)$this->product->getName();
    }

    /**
     * @inheritDoc
     *
     * @return float
     */
    public function getQuantity(): float
    {
        return (float)$this->product->getQty();
    }

    /**
     * @inheritDoc
     *
     * @throws PaymentDataException
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
     *
     * @throws PaymentDataException
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
     *
     * @return string
     */
    public function getType(): string
    {
        return Item::TYPE_PRODUCT;
    }

    /**
     * Checks if product should be omitted when crediting.
     *
     * @return bool
     * @throws PaymentDataException
     */
    public function omit(): bool
    {
        return $this->isBundle() && $this->hasDynamicPrice();
    }

    /**
     * Check for dynamic price.
     *
     * Checks if the product has dynamic pricing by its parent's product
     * options. If a parent can't be found the product itself will be checked.
     *
     * @return bool
     * @throws PaymentDataException
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
     * @throws PaymentDataException
     */
    private function hasFixedPrice(): bool
    {
        return !$this->getOrderItem()->isChildrenCalculated();
    }

    /**
     * Fetch order item from credit memo item.
     *
     * @return OrderItem
     * @throws PaymentDataException
     */
    private function getOrderItem(): OrderItem
    {
        /** @var OrderItem|null $product */
        $product = $this->product->getOrderItem();

        if ($product === null) {
            throw new PaymentDataException(
                __(
                    'rb-failed-to-resolve-order-item-from-creditmemo-item',
                    $this->product->getId()
                )
            );
        }

        return $product;
    }

    /**
     * Check if product is a bundle.
     *
     * @return bool
     * @throws PaymentDataException
     */
    public function isBundle(): bool
    {
        return $this->getOrderItem()->getProductType() === 'bundle';
    }

    /**
     * @inheritDoc
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
}
