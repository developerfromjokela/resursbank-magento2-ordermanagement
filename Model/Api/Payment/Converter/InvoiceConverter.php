<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model\Api\Payment\Converter;

use Exception;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Item;
use Magento\Sales\Model\ResourceModel\Order\Tax\ItemFactory as TaxItemResourceFactory;
use Resursbank\Core\Model\Api\Payment\Converter\AbstractConverter;
use Resursbank\Core\Model\Api\Payment\Converter\Item\DiscountItemFactory;
use Resursbank\Core\Model\Api\Payment\Converter\Item\ShippingItemFactory;
use Resursbank\Core\Model\Api\Payment\Item as PaymentItem;
use Resursbank\Core\Helper\Log;
use Resursbank\Ordermanagement\Model\Api\Payment\Converter\Item\Invoice\ProductItemFactory;
use function is_string;

/**
 * Invoice entity conversion for payment payloads.
 */
class InvoiceConverter extends AbstractConverter
{
    /**
     * @var ProductItemFactory
     */
    private ProductItemFactory $productItemFactory;

    /**
     * @param Log $log
     * @param TaxItemResourceFactory $taxItemResourceFact
     * @param ShippingItemFactory $shippingItemFactory
     * @param DiscountItemFactory $discountItemFactory
     * @param ProductItemFactory $productItemFactory
     */
    public function __construct(
        Log $log,
        TaxItemResourceFactory $taxItemResourceFact,
        ShippingItemFactory $shippingItemFactory,
        DiscountItemFactory $discountItemFactory,
        ProductItemFactory $productItemFactory
    ) {
        $this->productItemFactory = $productItemFactory;

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
     * @param Invoice $entity
     * @return PaymentItem[]
     * @throws Exception
     */
    public function convert(
        Invoice $entity
    ): array {
        $shippingMethod = $entity->getOrder()->getShippingMethod();

        return array_merge(
            $this->getProductData($entity),
            array_merge(
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
        );
    }

    /**
     * Extract product information from Invoice entity.
     *
     * @param Invoice $entity
     * @return PaymentItem[]
     * @throws Exception
     * @noinspection DuplicatedCode
     */
    protected function getProductData(
        Invoice $entity
    ): array {
        $result = [];
        $discountItems = [];

        if ($this->includeProductData(entity: $entity)) {
            foreach ($entity->getAllItems() as $product) {
                if ($product->getQty() > 0 &&
                    !$this->hasConfigurableParent(product: $product)
                ) {
                    $item = $this->productItemFactory->create(data: [
                        'product' => $product
                    ]);

                    $result[] = $item->getItem();

                    $this->addDiscountItem(
                        amount: (float) $product->getDiscountAmount(),
                        taxPercent: $product->getDiscountTaxCompensationAmount() > 0 ? $item->getItem()->getVatPct() : 0,
                        productQty: (float) $product->getQty(),
                        items: $discountItems
                    );
                }
            }
        }

        return array_merge($result, $discountItems);
    }

    /**
     * Whether to include product data in payment payload.
     *
     * @param Invoice $entity
     * @return bool
     */
    public function includeProductData(
        Invoice $entity
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
