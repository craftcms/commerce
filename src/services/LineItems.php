<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Order;
use craft\commerce\events\LineItemEvent;
use craft\commerce\models\LineItem;
use craft\commerce\records\LineItem as LineItemRecord;
use craft\db\Query;
use craft\helpers\Json;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Line item service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class LineItems extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event LineItemEvent The event that is raised before a line item is saved.
     *
     * Plugins can get notified before a line item is being saved
     *
     * ```php
     * use craft\commerce\events\LineItems;
     * use craft\commerce\services\LineItemEvent;
     * use yii\base\Event;
     *
     * Event::on(LineItems::class, LineItems::EVENT_DEFAULT_ORDER_STATUS, function(LineItemEvent $e) {
     *     // Do something - perhaps let a 3rd party service know about changes to an order
     * });
     * ```
     */
    const EVENT_BEFORE_SAVE_LINE_ITEM = 'beforeSaveLineItem';

    /**
     * @event LineItemEvent The event that is raised after a line item is saved.
     *
     * Plugins can get notified after a line item is being saved
     *
     * ```php
     * use craft\commerce\events\LineItems;
     * use craft\commerce\services\LineItemEvent;
     * use yii\base\Event;
     *
     * Event::on(LineItems::class, LineItems::EVENT_DEFAULT_ORDER_STATUS, function(LineItemEvent $e) {
     *     // Do something - perhaps reserve the stock
     * });
     * ```
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
     * @var LineItem[]
     */
    private $_lineItemsByOrderId = [];

    // Public Methods
    // =========================================================================

    /**
     * Returns an order's line items, per the order's ID.
     *
     * @param int $orderId the order's ID
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
     * Returns a line item with any supplied options, per its order's ID and purchasable's ID
     *
     * @param int $orderId the order's ID
     * @param int $purchasableId the purchasable's ID
     * @param array $options Options for the line item
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
     * @param Order $order The order that is being updated.
     * @param LineItem $lineItem The line item that is being updated.
     * @param string $error This will be populated with an error message, if any.
     * @return bool Whether the update was successful.
     */
    public function updateLineItem(Order $order, LineItem $lineItem, &$error): bool
    {
        if (!$lineItem->purchasableId) {
            $this->deleteLineItemById($lineItem->id);
            Craft::$app->getElements()->saveElement($order);
            $error = Craft::t('commerce', 'Item no longer for sale. Removed from cart.');

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
     * Deletes a line item by its ID.
     *
     * @param int $lineItemId the line item's ID
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
     * @param bool $runValidation Whether the Line Item should be validated.
     * @return bool
     * @throws \Throwable
     */
    public function saveLineItem(LineItem $lineItem, bool $runValidation = true): bool
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
        $lineItemRecord->total = $lineItem->getTotal();
        $lineItemRecord->subtotal = $lineItem->getSubtotal();

        $lineItem->validate();

        if (!$lineItem->hasErrors()) {

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

            if ($success && $this->hasEventHandlers(self::EVENT_AFTER_SAVE_LINE_ITEM)) {
                $this->trigger(self::EVENT_AFTER_SAVE_LINE_ITEM, new LineItemEvent([
                    'lineItem' => $lineItem,
                    'isNew' => $isNewLineItem,
                ]));
            }

            return $success;
        }

        return false;
    }

    /**
     * Get a line item by its ID.
     *
     * @param int $id the line item ID
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
     * Create a line item.
     *
     * @param int $purchasableId The ID of the purchasable the line item represents
     * @param Order $order The order the line item is associated with
     * @param array $options Options to set on the line item
     * @param int $qty The quantity to set on the line item
     * @return LineItem
     * @throws InvalidConfigException if purchasable is not found.
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
            $lineItem->populateFromPurchasable($purchasable);
        } else {
            throw new InvalidConfigException(Craft::t('commerce', 'Not a purchasable ID'));
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
     * Deletes all line items associated with an order, per the order's ID.
     *
     * @param int $orderId the order's ID
     * @return bool whether any line items were deleted
     */
    public function deleteAllLineItemsByOrderId(int $orderId): bool
    {
        return (bool)LineItemRecord::deleteAll(['orderId' => $orderId]);
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
