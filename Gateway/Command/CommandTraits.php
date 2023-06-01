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
use Resursbank\Core\Model\Api\Payment\Item;
use Resursbank\RBEcomPHP\ResursBank;
use function get_class;

/**
 * Common functionality for gateway commands.
 */
trait CommandTraits
{
    /**
     * Use the addOrderLine method in ECom to add payload data while avoiding
     * methods that override supplied data.
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
                get_class($data->getPayment())
            ));
        }

        return $payment;
    }
}
