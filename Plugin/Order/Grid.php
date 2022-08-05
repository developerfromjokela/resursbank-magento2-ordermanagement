<?php

namespace Resursbank\Ordermanagement\Plugin\Order;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection;

class Grid
{
    /**
     * @param Collection $subject
     * @return null
     * @throws LocalizedException
     */
    public function beforeLoad(Collection $subject)
    {
//        if (!$subject->isLoaded()) {
//            $primaryKey = $subject->getResource()->getIdFieldName();
//            $tableName = $subject->getResource()->getTable('sales_order_payment');
//            
//            $subject->getSelect()->joinLeft(
//                $tableName,
//                $tableName . '.parent_id = main_table.' . $primaryKey,
//                $tableName . '.entity_id as payment_id'
//            );
//
//            $historyTable = $subject->getResource()->getTable('resursbank_checkout_payment_history');
//
//            $subject->getSelect()->joinLeft(
//                $historyTable,
//                $historyTable . '.payment_id = payment_id',
//                $historyTable . '.status_to as resursbank_status_'
//            );
//        }

        return null;
    }
}
