<?php

namespace craft\commerce\helpers;

use Craft;
use craft\commerce\elements\Order as OrderElement;
use yii\base\InvalidArgumentException;

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
                // If a note already exists, merge it.
                if($lineItemsByKey[$key]->note && $lineItem->note)
                {
                    $lineItemsByKey[$key]->note = $lineItemsByKey[$key]->note . ' - ' . $lineItem->note;
                }else{
                    $lineItemsByKey[$key]->note = $lineItem->note;
                }

            } else {
                $lineItemsByKey[$key] = $lineItem;
            }
        }

        $order->setLineItems($lineItemsByKey);

        return $lineItems > $lineItemsByKey;
    }

    /**
     * Merges the contents of otherOrder into the primaryOrder
     *
     * @param OrderElement $primaryOrder The order that the other order will be merged into
     * @param OrderElement $other The other that will merge into the original order
     * @param bool $persist Should the primary order be saved
     * @param bool $deleteOther Should the other order be deleted, you can not delete if the primary order is not persisted.
     */
    public static function mergeOrders(OrderElement $primaryOrder, OrderElement $otherOrder, bool $persistPrimary = true, bool $deleteOther = true)
    {
        $primaryDidPersist = false;

        if ($primaryOrder->isCompleted || $otherOrder->isCompleted) {
            throw new InvalidArgumentException('Merging orders must still be carts.');
        }

        if (!$persistPrimary && $deleteOther) {
            throw new InvalidArgumentException('You can’t delete the other order if the primary order will not be saved.');
        }

        $otherLineItems = [];
        foreach ($otherOrder->getLineItems() as $lineItem) {
            $lineItem->orderId = $primaryOrder->id;
            $lineItem->id = null;
            $otherLineItems[] = $lineItem;
        }
        $primaryLineItems = $primaryOrder->getLineItems();
        $newLineItems = array_merge($primaryLineItems, $otherLineItems);
        $primaryOrder->setLineItems($newLineItems);
        static::mergeDuplicateLineItems($primaryOrder);

        if ($persistPrimary) {
            $primaryDidPersist = Craft::$app->getElements()->saveElement($primaryOrder);
        }

        if ($deleteOther && $primaryDidPersist) {
            Craft::$app->getElements()->deleteElementById($otherOrder->id);
        }
    }
}

