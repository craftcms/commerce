<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Helpers;

use Craft;
use craft\commerce\elements\Order as OrderElement;
use craft\commerce\enums\LineItemType;
use craft\commerce\models\OrderNotice;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use CraftCms\Commerce\Purchasable\Purchasables;

class Order
{
    public static function mergeDuplicateLineItems(OrderElement $order): bool
    {
        $lineItems = $order->getLineItems();
        $lineItemsByKey = [];

        foreach ($lineItems as $lineItem) {
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

    public static function normalizeLineItemPurchasableAvailability(OrderElement $order): void
    {
        if ($order->isCompleted) {
            return;
        }

        foreach ($order->getLineItems() as $lineItem) {
            if ($lineItem->type !== LineItemType::Purchasable) {
                continue;
            }

            /** @var PurchasableInterface $purchasable */
            $purchasable = $lineItem->getPurchasable();
            if (!$purchasable || !app(Purchasables::class)->isPurchasableAvailable($purchasable, $order)) {
                $message = t('{description} is no longer available.', ['description' => $lineItem->getDescription()], category: 'commerce');
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
            } elseif ($purchasable::hasInventory() &&
                !$purchasable->getIsOutOfStockPurchasingAllowed() &&
                $purchasable->inventoryTracked &&
                ($lineItem->qty > $purchasable->getStock()) &&
                $purchasable->getStock() > 0
            ) {
                $message = t('{description} only has {stock} in stock.', ['description' => $lineItem->getDescription(), 'stock' => $purchasable->getStock()], category: 'commerce');
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
                $lineItem->qty = $purchasable->getStock();
            }
        }
    }
}
