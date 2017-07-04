<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Order;
use craft\commerce\events\LineItemEvent;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\commerce\records\LineItem as LineItemRecord;
use craft\helpers\ArrayHelper;
use yii\base\Component;
use yii\base\Exception;

/**
 * Line item service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class LineItems extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event LineItemEvent The event that is raised before a line item is saved.
     */
    const EVENT_BEFORE_SAVE_LINE_ITEM = 'beforeSaveLineItem';

    /**
     * @event LineItemEvent The event that is raised after a line item is saved.
     */
    const EVENT_AFTER_SAVE_LINE_ITEM = 'afterSaveLineItem';

    /**
     * @event LineItemEvent This event is raised when a new line item is created from a purchasable
     */
    const EVENT_CREATE_LINE_ITEM = 'createLineItem';

    /**
     * @event LineItemEvent This event is raised when a new line item is populated from a purchasable
     */
    const EVENT_POPULATE_LINE_ITEM = 'populateLineItem';

    // Public Methods
    // =========================================================================

    /**
     * @param int $id
     *
     * @return LineItem[]
     */
    public function getAllLineItemsByOrderId($id): array
    {
        $lineItems = [];

        if ($id) {
            $lineItems = LineItemRecord::find()->where('orderId = :orderId', [':orderId' => $id])->all();
        }

        return ArrayHelper::map($lineItems, 'id', function($lineItem) {
            return $this->_createLineItemFromLineItemRecord($lineItem);
        });
    }

    /**
     * @param LineItemRecord $lineItemRecord
     *
     * @return LineItem
     */
    private function _createLineItemFromLineItemRecord($lineItemRecord): LineItem
    {
        return new LineItem($lineItemRecord->toArray([
            'id',
            'options',
            'optionsSignature',
            'price',
            'saleAmount',
            'salePrice',
            'tax',
            'taxIncluded',
            'shippingCost',
            'discount',
            'weight',
            'length',
            'height',
            'width',
            'total',
            'qty',
            'snapshot',
            'note',
            'purchasableId',
            'orderId',
            'taxCategoryId',
            'shippingCategoryId'
        ]));
    }

    /**
     * Find line item by order and variant
     *
     * @param int   $orderId
     * @param int   $purchasableId
     * @param array $options
     *
     * @return LineItem|null
     */
    public function getLineItemByOrderPurchasableOptions($orderId, $purchasableId, array $options = [])
    {
        ksort($options);
        $signature = md5(json_encode($options));
        $result = LineItemRecord::find()
            ->where('orderId = :orderId AND purchasableId = :purchasableId AND optionsSignature = :optionsSignature',
                [':orderId' => $orderId, ':purchasableId' => $purchasableId, ':optionsSignature' => $signature])
            ->one();

        if ($result) {
            return new LineItem($result);
        }

        return null;
    }

    /**
     * Update line item and recalculate order
     *
     * @param Order    $order
     * @param LineItem $lineItem
     * @param string   $error
     *
     * @return bool
     * @throws Exception
     */
    public function updateLineItem(Order $order, LineItem $lineItem, &$error = ''): bool
    {
        if (!$lineItem->purchasableId) {
            $this->deleteLineItem($lineItem);
            Plugin::getInstance()->getOrders()->saveOrder($order);
            $error = Craft::t("commerce", "Item no longer for sale. Removed from cart.");

            return false;
        }

        if ($this->saveLineItem($lineItem)) {
            Plugin::getInstance()->getOrders()->saveOrder($order);

            return true;
        }

        $errors = $lineItem->errors;
        $error = array_pop($errors);

        return false;
    }

    /**
     * @param LineItem $lineItem
     *
     * @return int
     */
    public function deleteLineItem(LineItem $lineItem)
    {
        $lineItem = LineItemRecord::findOne($lineItem->id);

        if ($lineItem) {
            return $lineItem->delete();
        }
    }

    /**
     * @param LineItem $lineItem
     *
     * @return bool
     * @throws \Exception
     */
    public function saveLineItem(LineItem $lineItem): bool
    {
        $isNewLineItem = !$lineItem->id;

        if (!$lineItem->id) {
            $lineItemRecord = new LineItemRecord();
        } else {
            $lineItemRecord = LineItemRecord::findOne($lineItem->id);

            if (!$lineItemRecord) {
                throw new Exception(Craft::t('commerce', 'No line item exists with the ID “{id}”',
                    ['id' => $lineItem->id]));
            }
        }

        $lineItem->total = $lineItem->getTotal();

        //raising event
        $event = new LineItemEvent([
            'lineItem' => $lineItem,
            'isNew' => $isNewLineItem,
        ]);
        $this->trigger(self::EVENT_BEFORE_SAVE_LINE_ITEM, $event);

        $lineItemRecord->purchasableId = $lineItem->purchasableId;
        $lineItemRecord->orderId = $lineItem->orderId;
        $lineItemRecord->taxCategoryId = $lineItem->taxCategoryId;
        $lineItemRecord->shippingCategoryId = $lineItem->shippingCategoryId;

        $lineItemRecord->options = $lineItem->options;
        $lineItemRecord->optionsSignature = $lineItem->optionsSignature;

        $lineItemRecord->qty = $lineItem->qty;
        $lineItemRecord->price = $lineItem->price;

        $lineItemRecord->weight = $lineItem->weight;
        $lineItemRecord->width = $lineItem->width;
        $lineItemRecord->length = $lineItem->length;
        $lineItemRecord->height = $lineItem->height;

        $lineItemRecord->snapshot = $lineItem->snapshot;
        $lineItemRecord->note = $lineItem->note;

        $lineItemRecord->saleAmount = $lineItem->saleAmount;
        $lineItemRecord->salePrice = $lineItem->salePrice;
        $lineItemRecord->tax = $lineItem->tax;
        $lineItemRecord->taxIncluded = $lineItem->taxIncluded;
        $lineItemRecord->discount = $lineItem->discount;
        $lineItemRecord->shippingCost = $lineItem->shippingCost;
        $lineItemRecord->total = $lineItem->total;

        // Cant have discounts making things less than zero.
        if ($lineItemRecord->total < 0) {
            $lineItemRecord->total = 0;
        }

        $lineItemRecord->validate();

        /** @var PurchasableInterface $purchasable */
        $purchasable = Craft::$app->getElements()->getElementById($lineItem->purchasableId);

        if ($purchasable) {
            $purchasable->validateLineItem($lineItem);
        }

        $lineItem->addErrors($lineItemRecord->getErrors());

        if ($lineItem->hasErrors()) {
            return false;
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $success = $lineItemRecord->save(false);

            if ($success) {
                if ($isNewLineItem) {
                    $lineItem->id = $lineItemRecord->id;
                }

                $transaction->commit();
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        if ($success) {
            //raising event
            $event = new LineItemEvent([
                'lineItem' => $lineItem,
                'isNew' => $isNewLineItem,
            ]);
            $this->trigger(self::EVENT_AFTER_SAVE_LINE_ITEM, $event);
        }

        return $success;
    }

    /**
     * @param int $id
     *
     * @return LineItem|null
     */
    public function getLineItemById($id)
    {
        $result = LineItemRecord::findOne($id);

        if ($result) {
            return new LineItem($result);
        }

        return null;
    }

    /**
     * @param $purchasableId
     * @param $order
     * @param $options
     * @param $qty
     *
     * @return LineItem
     */
    public function createLineItem($purchasableId, $order, $options, $qty): LineItem
    {
        $lineItem = new LineItem();
        $lineItem->purchasableId = $purchasableId;
        $lineItem->qty = $qty;
        ksort($options);
        $lineItem->options = $options;
        $lineItem->optionsSignature = md5(json_encode($options));
        $lineItem->setOrder($order);

        /** @var PurchasableInterface $purchasable */
        $purchasable = Craft::$app->getElements()->getElementById($purchasableId);

        if ($purchasable && ($purchasable instanceof PurchasableInterface)) {
            $lineItem->fillFromPurchasable($purchasable);
        } else {
            throw new Exception(Craft::t('commerce', 'Not a purchasable ID'));
        }

        //raising event
        $event = new LineItemEvent([
            'lineItem' => $lineItem,
            'purchasable' => $purchasable,
            'isNew' => true
        ]);
        $this->trigger(self::EVENT_CREATE_LINE_ITEM, $event);

        return $lineItem;
    }

    /**
     * @param int $orderId
     *
     * @return bool
     */
    public function deleteAllLineItemsByOrderId($orderId): bool
    {
        return (bool) LineItemRecord::deleteAll(['orderId' => $orderId]);
    }
}
