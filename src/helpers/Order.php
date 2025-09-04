<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\elements\Order as OrderElement;
use craft\commerce\enums\LineItemType;
use craft\commerce\elements\Variant;
use craft\commerce\models\OrderNotice;
use craft\commerce\Plugin;
use yii\base\InvalidConfigException;

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

    /**
     * Removes any line items from the cart that are no longer available.
     * If a line item is available but the quantity is more than the available stock,
     * the quantity will be reduced to the available stock.
     * A notice will be added to the cart for each change.
     *
     * @param OrderElement $order
     * @return void
     * @throws InvalidConfigException
     * @since 4.9.3
     */
    public static function normalizeLineItemPurchasableAvailability(OrderElement $order): void
    {
        if ($order->isCompleted) {
            return;
        }

        foreach ($order->getLineItems() as $lineItem) {
            if ($lineItem->type !== LineItemType::Purchasable) {
                continue;
            }

            /* @var $purchasable Purchasable */
            $purchasable = $lineItem->getPurchasable();
            if (!$purchasable || !Plugin::getInstance()->getPurchasables()->isPurchasableAvailable($purchasable, $order)) {
                $message = Craft::t('commerce', '{description} is no longer available.', ['description' => $lineItem->getDescription()]);
                /** @var OrderNotice $notice */
                $notice = Craft::createObject([
                    'class' => OrderNotice::class,
                    'attributes' => [
                        'message' => $message,
                        'type' => 'lineItemRemoved',
                        'attribute' => 'lineItems',
                    ],
                ]);
                $order->addNotice($notice);
                $order->removeLineItem($lineItem);
            } elseif ($purchasable instanceof Variant && ($lineItem->qty > $purchasable->stock) && !$purchasable->hasUnlimitedStock && $purchasable->stock > 0) {
                $message = Craft::t('commerce', '{description} only has {stock} in stock.', ['description' => $lineItem->getDescription(), 'stock' => $purchasable->stock]);
                /** @var OrderNotice $notice */
                $notice = Craft::createObject([
                    'class' => OrderNotice::class,
                    'attributes' => [
                        'type' => 'lineItemSalePriceChanged',
                        'attribute' => "lineItems.$lineItem->id.qty",
                        'message' => $message,
                    ],
                ]);
                $order->addNotice($notice);
                $lineItem->qty = $purchasable->stock;
            }
        }
    }
}
