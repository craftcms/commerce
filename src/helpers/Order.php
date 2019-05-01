<?php

namespace craft\commerce\helpers;

/**
 * Order helper
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.1
 */
class Order
{
    /**
     * @param $order
     *
     * @return bool Were line items merged?
     */
    public static function mergeDuplicateLineItems($order)
    {
        $lineItems = $order->getLineItems();
        // Ensure no duplicate line items exist, and if they do, combine them.
        $lineItemsByKey = [];
        foreach ($lineItems as $lineItem) {
            $key = $lineItem->orderId . '-' . $lineItem->purchasableId . '-' . $lineItem->getOptionsSignature();
            if (isset($lineItemsByKey[$key])) {
                $lineItemsByKey[$key]->qty += $lineItem->qty;
            } else {
                $lineItemsByKey[$key] = $lineItem;
            }
        }

        $order->setLineItems($lineItemsByKey);

        return $lineItems > $lineItemsByKey;
    }
}

