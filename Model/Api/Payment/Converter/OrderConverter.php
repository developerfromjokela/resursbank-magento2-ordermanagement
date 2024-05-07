<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model\Api\Payment\Converter;

use Exception;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\ResourceModel\Order\Tax\ItemFactory as TaxItemResourceFactory;
use Resursbank\Core\Model\Api\Payment\Converter\AbstractConverter;
use Resursbank\Core\Model\Api\Payment\Converter\Item\DiscountItemFactory;
use Resursbank\Core\Model\Api\Payment\Converter\Item\ShippingItemFactory;
use Resursbank\Core\Model\Api\Payment\Item as PaymentItem;
use Resursbank\Core\Helper\Log;
use Resursbank\Ordermanagement\Model\Api\Payment\Converter\Item\Order\ProductItemFactory;

use function is_string;

/**
 * Order entity conversion for payment payloads.
 */
class OrderConverter extends AbstractConverter
{
    /**
     * @var OrderInterface|null
     */
    private ?OrderInterface $order = null;

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
        private readonly ProductItemFactory $productItemFactory
    ) {
        parent::__construct(
            $log,
            $taxItemResourceFact,
            $shippingItemFactory,
            $discountItemFactory,
        );
    }

    /**
     * Get order or null if not set.
     *
     * @return OrderInterface|null
     */
    public function getOrder(): OrderInterface|null
    {
        return $this->order;
    }

    /**
     * Convert supplied entity to an array of PaymentItem instances.
     *
     * Convert supplied entity to a collection of PaymentItem instances. These
     * objects can later be mutated into a simple array the API can interpret.
     *
     * @param OrderInterface $entity
     * @return PaymentItem[]
     * @throws Exception
     */
    public function convert(
        OrderInterface $entity
    ): array {
        $this->order = $entity;
        $shippingMethod = $entity->getShippingMethod();

        return array_merge(
            $this->getProductData($entity),
            array_merge(
                $this->getShippingData(
                    is_string($shippingMethod) ? $shippingMethod : '',
                    (string) $entity->getShippingDescription(),
                    (float) $entity->getShippingInclTax(),
                    $this->getTaxPercentage(
                        (int) $entity->getId(),
                        'shipping'
                    )
                )
            )
        );
    }

    /**
     * Extract product information from Order entity.
     *
     * NOTE: This method will act on ordered qty amount, it will not account for
     * cancelled / refunded or invoiced amounts. Reason being this is currently
     * intended to be utilised during checkout to create new payments in the API
     * based on the newly created order. If different use-cases arise this
     * method may need to be modified.
     *
     * @param OrderInterface $entity
     * @return PaymentItem[]
     * @throws Exception
     * @noinspection DuplicatedCode
     */
    protected function getProductData(
        OrderInterface $entity
    ): array {
        $result = [];
        $discountItems = [];

        if ($this->includeProductData(entity: $entity)) {
            foreach ($entity->getAllItems() as $product) {
                if ($product->getQtyOrdered() > 0 &&
                    !$this->hasConfigurableParent(product: $product)
                ) {
                    $item = $this->productItemFactory->create(data: [
                        'product' => $product
                    ]);

                    $result[] = $item->getItem();

                    $this->addDiscountItem(
                        amount: (float) $product->getDiscountAmount(),
                        taxPercent:
                        $product->getDiscountTaxCompensationAmount() > 0 ?
                            $item->getItem()->getVatPct() : 0,
                        productQty: (float) $product->getQtyOrdered(),
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
     * @param OrderInterface $entity
     * @return bool
     */
    public function includeProductData(
        OrderInterface $entity
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
        return (
            $product->getParentItem() instanceof OrderItemInterface &&
            $product->getParentItem()->getProductType() === 'configurable'
        );
    }
}
