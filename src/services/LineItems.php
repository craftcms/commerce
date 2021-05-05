<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\events\LineItemEvent;
use craft\commerce\helpers\LineItem as LineItemHelper;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\commerce\records\LineItem as LineItemRecord;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use LitEmoji\LitEmoji;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidArgumentException;

/**
 * Line item service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class LineItems extends Component
{
    /**
     * @event LineItemEvent The event that is triggered before a line item is saved.
     *
     * ```php
     * use craft\commerce\events\LineItemEvent;
     * use craft\commerce\services\LineItems;
     * use craft\commerce\models\LineItem;
     * use yii\base\Event;
     *
     * Event::on(
     *     LineItems::class,
     *     LineItems::EVENT_BEFORE_SAVE_LINE_ITEM,
     *     function(LineItemEvent $event) {
     *         // @var LineItem $lineItem
     *         $lineItem = $event->lineItem;
     *         // @var bool $isNew
     *         $isNew = $event->isNew;
     *
     *         // Notify a third party service about changes to an order
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_BEFORE_SAVE_LINE_ITEM = 'beforeSaveLineItem';

    /**
     * @event LineItemEvent The event that is triggeredd after a line item is saved.
     *
     * ```php
     * use craft\commerce\events\LineItemEvent;
     * use craft\commerce\services\LineItems;
     * use craft\commerce\models\LineItem;
     * use yii\base\Event;
     *
     * Event::on(
     *     LineItems::class,
     *     LineItems::EVENT_AFTER_SAVE_LINE_ITEM,
     *     function(LineItemEvent $event) {
     *         // @var LineItem $lineItem
     *         $lineItem = $event->lineItem;
     *         // @var bool $isNew
     *         $isNew = $event->isNew;
     *
     *         // Reserve stock
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_SAVE_LINE_ITEM = 'afterSaveLineItem';

    /**
     * @event LineItemEvent The event that is triggered after a line item has been created from a purchasable.
     *
     * ```php
     * use craft\commerce\events\LineItemEvent;
     * use craft\commerce\services\LineItems;
     * use craft\commerce\models\LineItem;
     * use yii\base\Event;
     *
     * Event::on(
     *     LineItems::class,
     *     LineItems::EVENT_CREATE_LINE_ITEM,
     *     function(LineItemEvent $event) {
     *         // @var LineItem $lineItem
     *         $lineItem = $event->lineItem;
     *         // @var bool $isNew
     *         $isNew = $event->isNew;
     *
     *         // Call a third party service based on the line item options
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_CREATE_LINE_ITEM = 'createLineItem';

    /**
     * @event LineItemEvent The event that is triggered as a line item is being populated from a purchasable.
     *
     * ```php
     * use craft\commerce\events\LineItemEvent;
     * use craft\commerce\services\LineItems;
     * use craft\commerce\models\LineItem;
     * use yii\base\Event;
     *
     * Event::on(
     *     LineItems::class,
     *     LineItems::EVENT_POPULATE_LINE_ITEM,
     *     function(LineItemEvent $event) {
     *         // @var LineItem $lineItem
     *         $lineItem = $event->lineItem;
     *         // @var bool $isNew
     *         $isNew = $event->isNew;
     *
     *         // Modify the price of a line item
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_POPULATE_LINE_ITEM = 'populateLineItem';


    /**
     * @var LineItem[]
     */
    private $_lineItemsByOrderId = [];


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
                ->orderBy('dateCreated DESC')
                ->all();

            $this->_lineItemsByOrderId[$orderId] = [];

            foreach ($results as $result) {
                $result['snapshot'] = Json::decodeIfJson($result['snapshot']);
                $lineItem = new LineItem($result);
                $lineItem->typecastAttributes();
                $this->_lineItemsByOrderId[$orderId][] = $lineItem;
            }
        }

        return $this->_lineItemsByOrderId[$orderId];
    }

    /**
     * Takes an order ID, a purchasable ID, options, and resolves it to a line item.
     *
     * If a line item is found for that order ID with those exact options, that line item is
     * returned. Otherwise, a new line item is returned.
     *
     * @param int $orderId
     * @param int $purchasableId the purchasable's ID
     * @param array $options Options for the line item
     * @return LineItem
     */
    public function resolveLineItem(int $orderId, int $purchasableId, array $options = []): LineItem
    {
        $signature = LineItemHelper::generateOptionsSignature($options);

        $result = $this->_createLineItemQuery()
            ->where([
                'orderId' => $orderId,
                'purchasableId' => $purchasableId,
                'optionsSignature' => $signature
            ])
            ->one();

        if ($result) {
            $lineItem = new LineItem($result);
            $lineItem->typecastAttributes();
        } else {
            $lineItem = $this->createLineItem($orderId, $purchasableId, $options);
        }

        return $lineItem;
    }

    /**
     * Save a line item.
     *
     * @param LineItem $lineItem The line item to save.
     * @param bool $runValidation Whether the Line Item should be validated.
     * @return bool
     * @throws Throwable
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

        // Raise a 'beforeSaveLineItem' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_LINE_ITEM)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_LINE_ITEM, new LineItemEvent([
                'lineItem' => $lineItem,
                'isNew' => $isNewLineItem,
            ]));
        }

        if ($runValidation && !$lineItem->validate()) {
            Craft::info('Line item not saved due to validation error.', __METHOD__);
            return false;
        }

        $lineItemRecord->purchasableId = $lineItem->purchasableId;
        $lineItemRecord->orderId = $lineItem->orderId;
        $lineItemRecord->taxCategoryId = $lineItem->taxCategoryId;
        $lineItemRecord->shippingCategoryId = $lineItem->shippingCategoryId;
        $lineItemRecord->sku = $lineItem->sku;
        $lineItemRecord->description = $lineItem->description;

        $lineItemRecord->options = $lineItem->getOptions();
        $lineItemRecord->optionsSignature = $lineItem->getOptionsSignature();

        $lineItemRecord->qty = $lineItem->qty;
        $lineItemRecord->price = $lineItem->price;

        $lineItemRecord->weight = $lineItem->weight;
        $lineItemRecord->width = $lineItem->width;
        $lineItemRecord->length = $lineItem->length;
        $lineItemRecord->height = $lineItem->height;

        $lineItemRecord->snapshot = $lineItem->snapshot;
        $lineItemRecord->note = LitEmoji::unicodeToShortcode($lineItem->note);
        $lineItemRecord->privateNote = LitEmoji::unicodeToShortcode($lineItem->privateNote ?? '');
        $lineItemRecord->lineItemStatusId = $lineItem->lineItemStatusId;

        $lineItemRecord->saleAmount = $lineItem->saleAmount;
        $lineItemRecord->salePrice = $lineItem->salePrice;
        $lineItemRecord->total = $lineItem->getTotal();
        $lineItemRecord->subtotal = $lineItem->getSubtotal();

        if ($lineItem->uid) {
            $lineItemRecord->uid = $lineItem->uid;
        }

        if (!$lineItem->hasErrors()) {
            $db = Craft::$app->getDb();
            $transaction = $db->beginTransaction();

            try {
                $success = $lineItemRecord->save(false);

                if ($success) {
                    $dateCreated = DateTimeHelper::toDateTime($lineItemRecord->dateCreated);
                    $dateUpdated = DateTimeHelper::toDateTime($lineItemRecord->dateUpdated);
                    $lineItem->dateCreated = $dateCreated;
                    $lineItem->dateUpdated = $dateUpdated;
                    $lineItem->uid = $lineItemRecord->uid;

                    if ($isNewLineItem) {
                        $lineItem->id = $lineItemRecord->id;
                    }

                    $transaction->commit();
                }
            } catch (Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }

            if ($success && $this->hasEventHandlers(self::EVENT_AFTER_SAVE_LINE_ITEM)) {
                $this->trigger(self::EVENT_AFTER_SAVE_LINE_ITEM, new LineItemEvent([
                    'lineItem' => $lineItem,
                    'isNew' => $isNewLineItem,
                ]));
            }

            // Clear cache on save
            if (isset($this->_lineItemsByOrderId[$lineItem->orderId])) {
                unset($this->_lineItemsByOrderId[$lineItem->orderId]);
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

        if ($result) {
            $lineItem = new LineItem($result);
            $lineItem->typecastAttributes();
            return $lineItem;
        }

        return null;
    }

    /**
     * Create a line item.
     *
     * @param int $orderId The order ID the line item is associated with
     * @param int $purchasableId The ID of the purchasable the line item represents
     * @param array $options Options to set on the line item
     * @param int $qty The quantity to set on the line item
     * @param string $note The note on the line item
     * @param Order|null $order Optional, lets the line item created have the right order object assigned to it in memory. You will still need to supply the $orderId param.
     * @param string|null $uid
     * TODO: Refactor method signature so that the 2 order assignment params are not needed.
     * @return LineItem
     * @throws \Exception
     */
    public function createLineItem(int $orderId, int $purchasableId, array $options, int $qty = 1, string $note = '', Order $order = null, string $uid = null): LineItem
    {
        $lineItem = new LineItem();
        $lineItem->qty = $qty;
        $lineItem->setOptions($options);
        $lineItem->note = $note;
        $lineItem->uid = $uid ?: StringHelper::UUID();

        if ($order == null) {
            $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);
        }
        $lineItem->setOrder($order);

        /** @var PurchasableInterface $purchasable */
        $purchasable = Craft::$app->getElements()->getElementById($purchasableId);

        if ($purchasable && ($purchasable instanceof PurchasableInterface)) {
            $lineItem->setPurchasable($purchasable);
            $lineItem->populateFromPurchasable($purchasable);
        } else {
            throw new InvalidArgumentException('Invalid purchasable ID');
        }

        // Raise a 'createLineItem' event
        if ($this->hasEventHandlers(self::EVENT_CREATE_LINE_ITEM)) {
            $this->trigger(self::EVENT_CREATE_LINE_ITEM, new LineItemEvent([
                'lineItem' => $lineItem,
                'isNew' => true,
            ]));
        }

        $lineItem->refreshFromPurchasable();

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

    /**
     * @param array|Order[] $orders
     * @return Order[]
     * @since 3.2.0
     */
    public function eagerLoadLineItemsForOrders(array $orders): array
    {
        $orderIds = ArrayHelper::getColumn($orders, 'id');
        $lineItemsResults = $this->_createLineItemQuery()->andWhere(['orderId' => $orderIds])->all();

        $lineItems = [];

        foreach ($lineItemsResults as $result) {
            $result['snapshot'] = Json::decodeIfJson($result['snapshot']);
            $lineItem = new LineItem($result);
            $lineItem->typecastAttributes();
            $lineItems[$lineItem->orderId] = $lineItems[$lineItem->orderId] ?? [];
            $lineItems[$lineItem->orderId][] = $lineItem;
        }

        foreach ($orders as $key => $order) {
            if (isset($lineItems[$order->id])) {
                $order->setLineItems($lineItems[$order->id]);
                $orders[$key] = $order;
            }
        }

        return $orders;
    }

    /**
     *
     * @param LineItem $lineItem
     * @param Order $order
     * @throws Throwable
     * @since 3.2.5
     */
    public function orderCompleteHandler(LineItem $lineItem, Order $order)
    {
        // Called the after order complete method for the purchasable if there is one
        if ($lineItem->getPurchasable()) {
            $lineItem->getPurchasable()->afterOrderComplete($order, $lineItem);
        }

        // Retrieve the default status for the current line item. This is a chance for
        // developers to hook into an event for finer control
        $defaultStatus = Plugin::getInstance()->getLineItemStatuses()->getDefaultLineItemStatusForLineItem($lineItem);
        if (!$defaultStatus) {
            return;
        }

        // Set the status ID and save the line item
        $lineItem->setLineItemStatus($defaultStatus);
        $this->saveLineItem($lineItem, false);
    }

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
                'price',
                'salePrice',
                'sku',
                'description',
                'weight',
                'length',
                'height',
                'width',
                'qty',
                'snapshot',
                'note',
                'privateNote',
                'purchasableId',
                'orderId',
                'taxCategoryId',
                'shippingCategoryId',
                'lineItemStatusId',
                'dateCreated',
                'dateUpdated',
                'uid',
            ])
            ->from([Table::LINEITEMS . ' lineItems']);
    }
}
