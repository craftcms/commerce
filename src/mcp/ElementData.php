<?php

declare(strict_types=1);

namespace craft\commerce\mcp;

use BackedEnum;
use Craft;
use craft\base\ElementInterface;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Transfer;
use craft\commerce\elements\Variant;
use craft\commerce\management\Access;
use craft\elements\User;
use craft\mcp\events\ElementSerializing;
use DateTimeInterface;
use yii\helpers\ArrayHelper;

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
            $value = ArrayHelper::getValue($element, $attribute);
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
        $actor = Craft::$app->getUser()->getIdentity();
        if (($event->public && ($event->element instanceof Order || $event->element instanceof Transfer))
            || (!$event->public && (!$actor instanceof User || !Access::allows($actor, 'view', $event->element)))) {
            $event->serializedData = [];
            return;
        }
        $customFields = [];
        foreach ($event->element->getFieldLayout()?->getCustomFields() ?? [] as $field) {
            if (array_key_exists($field->handle, $event->serializedData)) {
                $customFields[$field->handle] = $event->serializedData[$field->handle];
            }
        }
        $event->serializedData = [...$customFields, ...self::attributes($event->element)];
        if ($event->public) {
            unset($event->serializedData['inventoryItemId']);
        }
    }
}
