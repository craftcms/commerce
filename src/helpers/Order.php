<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

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
     * @param OrderElement $order
     *
     * @return bool Were line items merged?
     */
    public static function mergeDuplicateLineItems(OrderElement $order): bool
    {
        $lineItems = $order->getLineItems();
        // Ensure no duplicate line items exist, and if they do, combine them.
        $lineItemsByKey = [];
        foreach ($lineItems as $lineItem) {
            $key = $lineItem->orderId . '-' . $lineItem->purchasableId . '-' . $lineItem->getOptionsSignature();
            if (isset($lineItemsByKey[$key])) {
                $lineItemsByKey[$key]->qty += $lineItem->qty;
                // If a note already exists, merge it.
                if ($lineItemsByKey[$key]->note && $lineItem->note) {
                    $lineItemsByKey[$key]->note = $lineItemsByKey[$key]->note . ' - ' . $lineItem->note;
                } else {
                    $lineItemsByKey[$key]->note = $lineItem->note;
                }
            } else {
                $lineItemsByKey[$key] = $lineItem;
            }
        }

        $order->setLineItems(array_values($lineItemsByKey));

        return $lineItems > $lineItemsByKey;
    }
}

