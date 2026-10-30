<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Mcp;

use BackedEnum;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Mcp\Events\ElementSerializing;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use DateTimeInterface;

class ElementData
{
    /** @return array<string, mixed> */
    public static function attributes(ElementInterface $element): array
    {
        $attributes = ['id', 'uid', 'siteId', 'title', 'enabled', 'dateCreated', 'dateUpdated'];
        $attributes = [...$attributes, ...match (true) {
            $element instanceof Product => ['typeId', 'slug', 'postDate', 'expiryDate', 'defaultVariantId'],
            $element instanceof Variant => ['productId', 'sku', 'basePrice', 'basePromotionalPrice', 'price', 'promotionalPrice', 'isDefault', 'width', 'height', 'length', 'weight', 'inventoryItemId', 'inventoryTracked', 'availableForPurchase', 'allowOutOfStockPurchases', 'taxCategoryId', 'shippingCategoryId'],
            $element instanceof Order => ['reference', 'storeId', 'isCompleted', 'orderStatusId', 'customerId', 'email', 'currency', 'paymentCurrency', 'message', 'couponCode', 'dateOrdered', 'datePaid', 'totalPrice', 'totalPaid', 'totalQty', 'totalTax', 'totalShippingCost', 'totalDiscount', 'outstandingBalance'],
            $element instanceof Transfer => ['transferStatus', 'originLocationId', 'destinationLocationId', 'totalQuantity', 'totalAccepted', 'totalRejected'],
            default => [],
        }];
        $data = ['type' => $element::class];
        foreach ($attributes as $attribute) {
            $value = $element->$attribute;
            $data[$attribute] = match (true) {
                $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
                $value instanceof BackedEnum => $value->value,
                default => $value,
            };
        }
        return $data;
    }

    /** Protects generic search, lifecycle and public serialization, which can bypass adapters. */
    public function handle(ElementSerializing $event): void
    {
        if (!$event->element instanceof Product && !$event->element instanceof Variant && !$event->element instanceof Order && !$event->element instanceof Transfer) {
            return;
        }
        if ($event->public && ($event->element instanceof Order || $event->element instanceof Transfer)) {
            $event->data = [];
            return;
        }
        $customFields = [];
        foreach ($event->element->getFieldLayout()?->getCustomFields() ?? [] as $field) {
            if (array_key_exists($field->handle, $event->data)) {
                $customFields[$field->handle] = $event->data[$field->handle];
            }
        }
        $event->data = [...$customFields, ...self::attributes($event->element)];
        if ($event->public) {
            unset($event->data['inventoryItemId']);
        }
    }
}
