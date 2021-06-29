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
    protected $product;

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
        return (float)$this->product->getQty();
    }

    /**
     * @inheritDoc
     * @throws PaymentDataException
     */
    public function getUnitAmountWithoutVat(): float
    {
        $result = 0.0;

        $product = $this->getOrderItem();
        $parent = $product->getParentItem();

        if ($product->getProductType() === 'bundle') {
            $result = $this->hasFixedPrice() ?
                (float) $product->getPrice() :
                0.0;
        } elseif ($parent instanceof OrderItemInterface) {
            if ($parent->getProductType() === 'bundle' &&
                $this->hasDynamicPrice()
            ) {
                $result = (float) $product->getPrice();
            }
        } else {
            $result = (float) $product->getPrice();
        }

        return $this->sanitizeUnitAmountWithoutVat($result);
    }

    /**
     * @inheritDoc
     * @throws PaymentDataException
     */
    public function getVatPct(): int
    {
        $result = 0.0;
        $product = $this->getOrderItem();
        $parent = $product->getParentItem();

        if ($product->getProductType() === 'bundle' &&
            $this->hasFixedPrice()
        ) {
            $result = (float)(
                    $product->getTaxAmount() /
                    $product->getPrice()
                ) * 100;
        } elseif ($product->getProductType() === 'configurable') {
            $result = (float) $product->getTaxPercent();
        } elseif (!($parent instanceof OrderItemInterface) ||
            $parent->getProductType() !== 'configurable'
        ) {
            $result = (float) $product->getTaxPercent();
        }

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
     * Checks if the the product has dynamic pricing by its parent's product
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
     * Checks if the the product has fixed pricing by its parent's product
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
     * @return OrderItem
     * @throws PaymentDataException
     */
    private function getOrderItem(): OrderItem
    {
        /** @var OrderItem|null $product */
        $product = $this->product->getOrderItem();

        if ($product === null) {
            throw new PaymentDataException(
                __('Failed to resolve order item from creditmemo item %1',
                $this->product->getId())
            );
        }

        return $product;
    }
}
