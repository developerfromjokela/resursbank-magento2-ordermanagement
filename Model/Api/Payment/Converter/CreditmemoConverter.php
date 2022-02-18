<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model\Api\Payment\Converter;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Item;
use Magento\Sales\Model\ResourceModel\Order\Tax\ItemFactory as TaxItemResourceFactory;
use Resursbank\Core\Model\Api\Payment\Converter\AbstractConverter;
use Resursbank\Core\Model\Api\Payment\Converter\Item\DiscountItemFactory;
use Resursbank\Core\Model\Api\Payment\Converter\Item\ShippingItemFactory;
use Resursbank\Core\Model\Api\Payment\Item as PaymentItem;
use Resursbank\Core\Helper\Log;
use Resursbank\Core\Model\Api\Payment\ItemFactory;
use Resursbank\Ordermanagement\Model\Api\Payment\Converter\Item\Creditmemo\ProductItemFactory;
use function is_string;

/**
 * Creditmemo entity conversion for payment payloads.
 */
class CreditmemoConverter extends AbstractConverter
{
    /**
     * @var ProductItemFactory
     */
    private ProductItemFactory $productItemFactory;

    /**
     * @var ItemFactory
     */
    private ItemFactory $itemFactory;

    /**
     * @param Log $log
     * @param TaxItemResourceFactory $taxItemResourceFact
     * @param ShippingItemFactory $shippingItemFactory
     * @param DiscountItemFactory $discountItemFactory
     * @param ProductItemFactory $productItemFactory
     * @param ItemFactory $itemFactory
     */
    public function __construct(
        Log $log,
        TaxItemResourceFactory $taxItemResourceFact,
        ShippingItemFactory $shippingItemFactory,
        DiscountItemFactory $discountItemFactory,
        ProductItemFactory $productItemFactory,
        ItemFactory $itemFactory
    ) {
        $this->productItemFactory = $productItemFactory;
        $this->itemFactory = $itemFactory;

        parent::__construct(
            $log,
            $taxItemResourceFact,
            $shippingItemFactory,
            $discountItemFactory,
        );
    }

    /**
     * Convert supplied entity to a collection of PaymentItem instances. These
     * objects can later be mutated into a simple array the API can interpret.
     *
     * @param Creditmemo $entity
     * @return PaymentItem[]
     * @throws Exception
     */
    public function convert(
        Creditmemo $entity
    ): array {
        if ((float) $entity->getGrandTotal() < 0.0) {
            throw new LocalizedException(
                __('Resurs Bank does not support negative credit memos.')
            );
        }

        $shippingMethod = $entity->getOrder()->getShippingMethod();

        return array_merge(
            array_merge(
                $this->getProductData($entity),
                array_merge(
                    $this->getDiscountData(
                        (string) $entity->getOrder()->getCouponCode(),
                        (float) $entity->getDiscountAmount(),
                        (float) $entity->getDiscountTaxCompensationAmount()
                    ),
                    $this->getShippingData(
                        is_string($shippingMethod) ? $shippingMethod : '',
                        (string) $entity->getOrder()->getShippingDescription(),
                        (float) $entity->getShippingInclTax(),
                        $this->getTaxPercentage(
                            (int) $entity->getOrderId(),
                            'shipping'
                        )
                    )
                )
            ),
            $this->getAdjustmentFee($entity)
        );
    }

    /**
     * Extract product information from Creditmemo entity.
     *
     * @param Creditmemo $entity
     * @return PaymentItem[]
     * @throws Exception
     */
    protected function getProductData(
        Creditmemo $entity
    ): array {
        $result = [];

        if ($this->includeProductData($entity)) {
            foreach ($entity->getAllItems() as $product) {
                if ($product->getQty() > 0 &&
                    !$this->hasConfigurableParent($product)
                ) {
                    $item = $this->productItemFactory->create([
                        'product' => $product
                    ]);

                    $result[] = $item->getItem();
                }
            }
        }

        return $result;
    }

    /**
     * Extract adjustment fee information from Creditmemo entity.
     *
     * NOTE: Since there is no corresponding type at Resurs Bank we report this
     * as a product.
     *
     * @param Creditmemo $entity
     * @return PaymentItem[]
     * @throws Exception
     */
    protected function getAdjustmentFee(
        Creditmemo $entity
    ): array {
        $result = [];
        $fee = (float) $entity->getAdjustment();

        if ($fee !== 0.0) {
            $result[] = $this->itemFactory->create([
                PaymentItem::KEY_ART_NO => 'adjustment',
                PaymentItem::KEY_DESCRIPTION => (string) __('Adjustment'),
                PaymentItem::KEY_QUANTITY => 1,
                PaymentItem::KEY_UNIT_MEASURE => 'st',
                PaymentItem::KEY_UNIT_AMOUNT_WITHOUT_VAT => $fee,
                PaymentItem::KEY_VAT_PCT => 0,
                PaymentItem::KEY_TYPE => PaymentItem::TYPE_PRODUCT
            ]);
        }

        return $result;
    }

    /**
     * Whether to include product data in payment payload.
     *
     * @param Creditmemo $entity
     * @return bool
     */
    public function includeProductData(
        Creditmemo $entity
    ): bool {
        $items = $entity->getAllItems();

        return !empty($items);
    }

    /**
     * Whether a product have a configurable product as a parent.
     *
     * @param Item $product
     * @return bool
     */
    private function hasConfigurableParent(
        Item $product
    ): bool {
        $orderItem = $product->getOrderItem();

        return (
            $orderItem instanceof OrderItemInterface &&
            $orderItem->getParentItem() instanceof OrderItemInterface &&
            $orderItem->getParentItem()->getProductType() === 'configurable'
        );
    }
}
