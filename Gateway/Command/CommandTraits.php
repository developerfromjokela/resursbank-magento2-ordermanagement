<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Gateway\Command;

use Exception;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Payment;
use Resursbank\Core\Exception\PaymentDataException;
use Resursbank\Core\Model\Api\Payment\Converter\Item\ItemInterface;
use Resursbank\Core\Model\Api\Payment\Item;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLine;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLineCollection;
use Resursbank\Ecom\Lib\Order\OrderLineType;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\RBEcomPHP\ResursBank;
use Throwable;
use function get_class;

/**
 * Common functionality for gateway commands.
 */
trait CommandTraits
{
    /**
     * Use the addOrderLine method in ECom to add payload data while avoiding methods that override supplied data.
     *
     * @param ResursBank $connection
     * @param array<Item> $data
     * @throws Exception
     */
    public function addOrderLines(
        ResursBank $connection,
        array $data
    ): void {
        foreach ($data as $item) {
            // Ecom wrongly specifies some arguments as int when they should
            // be floats.
            $connection->addOrderLine(
                articleNumberOrId: $item->getArtNo(),
                description: $item->getDescription(),
                unitAmountWithoutVat: $item->getUnitAmountWithoutVat(),
                vatPct: $item->getVatPct(),
                unitMeasure: $item->getUnitMeasure(),
                articleType: $item->getType(),
                quantity: $item->getQuantity()
            );
        }
    }

    /**
     * Get the payment.
     *
     * @param PaymentDataObjectInterface $data
     * @return Payment
     * @throws PaymentDataException
     */
    public function getPayment(
        PaymentDataObjectInterface $data
    ): Payment {
        $payment = $data->getPayment();

        if (!$payment instanceof Payment) {
            throw new PaymentDataException(phrase: __(
                'Unexpected payment entity. Expected %1 but got %2.',
                Payment::class,
                get_class(object: $data->getPayment())
            ));
        }

        return $payment;
    }

    /**
     * Check if MAPI is activated to handle orders.
     *
     * @param array $commandSubject
     * @return bool
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function isMapiActive(
        array $commandSubject
    ): bool {
        return $this->config->isMapiActive(scopeCode: ($this->getOrder(commandSubject: $commandSubject))->getStoreId());
    }

    /**
     * Get the order.
     *
     * @param array $commandSubject
     * @return OrderInterface
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getOrder(array $commandSubject): OrderInterface
    {
        $data = SubjectReader::readPayment(subject: $commandSubject);
        return $this->orderRepo->get(id: $data->getOrder()->getId());
    }

    /**
     * Search for legacy payments at Resurs.
     *
     * @param string $paymentId
     * @return string
     */
    public function findPaymentIdForLegacyOrder(string $paymentId): string
    {
        try {
            $result = Repository::search(
                storeId: $this->config->getStore(
                    scopeCode: $this->scope->getId(),
                    scopeType: $this->scope->getType()
                ),
                orderReference: $paymentId
            );
            return $result->count() > 0 ? $result->getData()[0]->id : '';
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Get the payment id depending on which flow the order has been created with.
     *
     * @param OrderInterface $order
     * @return string
     * @throws InputException
     */
    public function getPaymentId(OrderInterface $order): string
    {
        $id = $this->orderHelper->getPaymentId(order: $order);
        $paymentMethod = $order->getPayment()->getMethod();

        // Check if payment has been created with simplified by checking the name of the method.
        if (str_starts_with(haystack: $paymentMethod, needle: 'resursbank_')) {
            $searchLegacyPaymentId = $this->findPaymentIdForLegacyOrder(paymentId: $id);
            if ($searchLegacyPaymentId !== '' && $id !== $searchLegacyPaymentId) {
                $id = $searchLegacyPaymentId;
            }
        }

        return $id;
    }

    /**
     * OrderLine renderer for capture and refund.
     *
     * @param array $items
     * @return OrderLineCollection
     * @throws IllegalTypeException
     * @throws IllegalValueException '
     */
    public function getOrderLines(array $items): OrderLineCollection
    {
        $data = [];

        /** @var ItemInterface $item */
        foreach ($items as $item) {
            $data[] = new OrderLine(
                quantity: $item->getQuantity(),
                quantityUnit: (string)__('rb-default-quantity-unit'),
                vatRate: $item->getVatPct(),
                totalAmountIncludingVat: $item->getTotalAmountInclVat(),
                description: $item->getDescription(),
                reference: $item->getArtNo(),
                type: match ($item->getType()) {
                    Item::TYPE_DISCOUNT => OrderLineType::DISCOUNT,
                    Item::TYPE_SHIPPING => OrderLineType::SHIPPING,
                    default => OrderLineType::NORMAL
                }
            );
        }

        return new OrderLineCollection(data: $data);
    }
}
