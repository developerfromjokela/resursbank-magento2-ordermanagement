<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model\Api\Payment\Converter\Item\Invoice;

use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order\Invoice\Item as InvoiceItem;
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
     * @var InvoiceItem
     */
    protected InvoiceItem $product;

    /**
     * @param Config $config
     * @param ItemFactory $itemFactory
     * @param Log $log
     * @param InvoiceItem $product
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Config $config,
        ItemFactory $itemFactory,
        Log $log,
        InvoiceItem $product,
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
        $product = $this->getOrderItem();

        return $this->sanitizeUnitAmountWithoutVat(
            $this->isBundle() && !$this->hasFixedPrice() ?
                0.0 :
                (float) $product->getPriceInclTax() / (1 + ((float) $product->getTaxPercent() / 100))
        );
    }

    /**
     * @inheritDoc
     * @throws PaymentDataException
     */
    public function getVatPct(): float
    {
        $product = $this->getOrderItem();
        $result = $this->isBundle() && !$this->hasFixedPrice() ?
            0.0 :
            (float) $product->getTaxPercent();

        return round($result);
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return Item::TYPE_PRODUCT;
    }

    /**
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
                    'Failed to resolve order item from invoice item %1',
                    $this->product->getId()
                )
            );
        }

        return $product;
    }

    /**
     * @return bool
     * @throws PaymentDataException
     */
    public function isBundle(): bool
    {
        return $this->getOrderItem()->getProductType() === 'bundle';
    }
}
