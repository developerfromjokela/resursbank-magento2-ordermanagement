<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model\Api\Payment\Converter\Item\Invoice;

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
     * @throws PaymentDataException
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
     * @throws PaymentDataException
     */
    private function hasFixedPrice(): bool
    {
        return !$this->getOrderItem()->isChildrenCalculated();
    }

    /**
     * Fetch order item.
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
                    'rb-failed-to-resolve-order-item-from-invoice-item',
                    $this->product->getId()
                )
            );
        }

        return $product;
    }

    /**
     * Check if product is a bundle product.
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
     *
     * @return float
     * @throws PaymentDataException
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
     * Fetches the actual invoice item.
     *
     * @return InvoiceItem
     */
    public function getInvoiceItem(): InvoiceItem
    {
        return $this->product;
    }
}
