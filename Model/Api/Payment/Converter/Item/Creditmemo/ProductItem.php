<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model\Api\Payment\Converter\Item\Creditmemo;

use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
use Magento\Sales\Model\Order\Item as OrderItem;
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
     */
    public function __construct(
        Config $config,
        ItemFactory $itemFactory,
        Log $log,
        CreditmemoItem $product
    ) {
        $this->product = $product;

        parent::__construct($config, $itemFactory, $log);
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
     */
    public function getUnitAmountWithoutVat(): float
    {
        return $this->sanitizeUnitAmountWithoutVat(
            (float)$this->product->getPrice()
        );
    }

    /**
     * @inheritDoc
     */
    public function getVatPct(): int
    {
        $order = $this->product->getOrderItem();
        $pct = $order instanceof OrderItem ?
            (float)$order->getTaxPercent() :
            0.0;

        return (int)round($pct);
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return Item::TYPE_PRODUCT;
    }
}
