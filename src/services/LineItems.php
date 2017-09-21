<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Order;
use craft\commerce\events\LineItemEvent;
use craft\commerce\models\LineItem;
use craft\commerce\records\LineItem as LineItemRecord;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
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

    // Properties
    // =========================================================================

    /**
     * @var LineItem[][]
     */
    private $_lineItemsByOrderId = [];

    // Public Methods
    // =========================================================================

    /**
     * Get all line items by order id.
     *
     * @param int $orderId The order id.
     *
     * @return LineItem[] An array of all the line items for the matched order.
     */
    public function getAllLineItemsByOrderId(int $orderId): array
    {
        if (!isset($this->_lineItemsByOrderId[$orderId])) {
            $results = $this->_createLineItemQuery()
                ->where(['orderId' => $orderId])
                ->all();
            $lineItems = [];

            foreach ($results as $result) {
                $result['options'] = Json::decodeIfJson($result['options']);
                $result['snapshot'] = Json::decodeIfJson($result['snapshot']);
                $lineItems[] = new LineItem($result);
            }

            $this->_lineItemsByOrderId[$orderId] = $lineItems;
        }


        return $this->_lineItemsByOrderId[$orderId];
    }

    /**
     * Find a line item by order id and purchasable id with optional options set for line item.
     *
     * @param int   $orderId       The order id.
     * @param int   $purchasableId The purchasable id.
     * @param array $options       Optional option array.
     *
     * @return LineItem|null Line item or null if not found.
     */
    public function getLineItemByOrderPurchasableOptions(int $orderId, int $purchasableId, array $options = [])
    {
        ksort($options);
        $signature = md5(json_encode($options));
        $result = $this->_createLineItemQuery()
            ->where([
                'orderId' => $orderId,
                'purchasableId' => $purchasableId,
                'optionsSignature' => $signature
                ])
            ->one();

        return $result ? new LineItem($result) : null;
    }

    /**
     * Update a line item for an order.
     *
     * @param Order    $order    The order that is being updated.
     * @param LineItem $lineItem The line item that is being updated.
     * @param string   $error    This will be populated with an error message, if any.
     *
     * @return bool Whether the update was successful.
     */
    public function updateLineItem(Order $order, LineItem $lineItem, &$error = ''): bool
    {
        if (!$lineItem->purchasableId) {
            $this->deleteLineItemById($lineItem->id);
            Craft::$app->getElements()->saveElement($order);
            $error = Craft::t("commerce", "Item no longer for sale. Removed from cart.");

            return false;
        }

        if ($this->saveLineItem($lineItem)) {
            Craft::$app->getElements()->saveElement($order);

            return true;
        }

        $errors = $lineItem->getFirstErrors();
        $error = array_pop($errors);

        return false;
    }

    /**
     * Delete a line item by it's id.
     * 
     * @param int $lineItemId The id of the line item.
     *
     * @return bool Whether the line item was deleted successfully.
     */
    public function deleteLineItemById(int $lineItemId): bool
    {
        $lineItem = LineItemRecord::findOne($lineItemId);

        if ($lineItem) {
            return (bool)$lineItem->delete();
        }
        
        return false;
    }

    /**
     * Save a line item.
     *
     * @param LineItem $lineItem The line item to save.
     *
     * @return bool
     * @throws \Throwable
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

        // Raise a 'beforeSaveLineItem' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_LINE_ITEM)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_LINE_ITEM, new LineItemEvent([
                'lineItem' => $lineItem,
                'isNew' => $isNewLineItem,
            ]));
        }

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
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        if ($success) {
            // Raise a 'afterSaveLineItem' event
            if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_LINE_ITEM)) {
                $this->trigger(self::EVENT_AFTER_SAVE_LINE_ITEM, new LineItemEvent([
                    'lineItem' => $lineItem,
                    'isNew' => $isNewLineItem,
                ]));
            }
        }

        return $success;
    }

    /**
     * Get a line item by it's id.
     *
     * @param int $id The id of the line item.
     *
     * @return LineItem|null Line item or null, if not found.
     */
    public function getLineItemById($id)
    {
        $result = $this->_createLineItemQuery()
            ->where(['id' => $id])
            ->one();

        return $result ? new LineItem($result) : null;
    }

    /**
     * Create a line item in order by purchasable id, options and quantity.
     *
     * @param int $purchasableId
     * @param Order $order
     * @param array $options
     * @param int $qty
     *
     * @return LineItem
     * @throws Exception if purchasable is not found.
     */
    public function createLineItem(int $purchasableId, Order $order, array $options, int $qty): LineItem
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

        // Raise a 'createLineItem' event
        if ($this->hasEventHandlers(self::EVENT_CREATE_LINE_ITEM)) {
            $this->trigger(self::EVENT_CREATE_LINE_ITEM, new LineItemEvent([
                'lineItem' => $lineItem,
                'purchasable' => $purchasable,
                'isNew' => true,
            ]));
        }

        return $lineItem;
    }

    /**
     * Delete all line items by order id.
     * 
     * @param int $orderId The order id.
     *
     * @return bool Whether any line items were deleted.
     */
    public function deleteAllLineItemsByOrderId(int $orderId): bool
    {
        return (bool) LineItemRecord::deleteAll(['orderId' => $orderId]);
    }

    // Private methods
    // =========================================================================

    /**
     * Returns a Query object prepped for retrieving line items.
     *
     * @return Query The query object.
     */
    private function _createLineItemQuery(): Query
    {

        return (new Query())
            ->select([
                'id',
                'options',
                'optionsSignature',
                'price',
                'saleAmount',
                'salePrice',
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
            ])
            ->from(['{{%commerce_lineitems}} lineItems']);
    }
}
