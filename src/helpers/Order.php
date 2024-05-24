<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use craft\commerce\elements\Order as OrderElement;
use craft\commerce\enums\LineItemType;

/**
 * Order helper
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.1
 */
class Order
{
    /**
     * @return bool Were any line items merged?
     */
    public static function mergeDuplicateLineItems(OrderElement $order): bool
    {
        $lineItems = $order->getLineItems();
        $lineItemsByKey = [];

        foreach ($lineItems as $lineItem) {
            // Generate a key depending on line item type
            if ($lineItem->type === LineItemType::Purchasable) {
                $key = $lineItem->orderId . '-' . LineItemType::Purchasable->value . '-' . $lineItem->purchasableId . '-' . $lineItem->getOptionsSignature();
            } else {
                $key = $lineItem->orderId . '-' . LineItemType::Custom->value . '-' . $lineItem->getSku() . '-' . $lineItem->getOptionsSignature();
            }

            if (!isset($lineItemsByKey[$key])) {
                $lineItemsByKey[$key] = $lineItem;
                continue;
            }

            $lineItemsByKey[$key]->qty += $lineItem->qty;
            $lineItemsByKey[$key]->note = trim(($lineItemsByKey[$key]->note ? $lineItemsByKey[$key]->note . ' - ' : '') . $lineItem->note, ' -');
        }

        $order->setLineItems(array_values($lineItemsByKey));

        return count($lineItems) > count($lineItemsByKey);
    }
}
