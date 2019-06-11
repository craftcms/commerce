<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\events\LineItemEvent;
use craft\commerce\helpers\LineItem as LineItemHelper;
use craft\commerce\models\LineItem;
use craft\commerce\records\LineItem as LineItemRecord;
use craft\db\Query;
use craft\helpers\Json;
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
    // Constants
    // =========================================================================

    /**
     * @event LineItemEvent The event that is raised before a line item is saved.
     *
     * Plugins can get notified before a line item is being saved
     *
     * ```php
     * use craft\commerce\events\LineItemEvent;
     * use craft\commerce\services\LineItems;
     * use yii\base\Event;
     *
     * Event::on(LineItems::class, LineItems::EVENT_BEFORE_SAVE_LINE_ITEM, function(LineItemEvent $e) {
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
     * use craft\commerce\events\LineItemEvent;
     * use craft\commerce\services\LineItems;
     * use yii\base\Event;
     *
     * Event::on(LineItems::class, LineItems::EVENT_AFTER_SAVE_LINE_ITEM, function(LineItemEvent $e) {
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

            $this->_lineItemsByOrderId[$orderId] = [];

            foreach ($results as $result) {
                $result['snapshot'] = Json::decodeIfJson($result['snapshot']);
                $this->_lineItemsByOrderId[$orderId][] = new LineItem($result);
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

        $lineItemRecord->options = $lineItem->getOptions();
        $lineItemRecord->optionsSignature = $lineItem->getOptionsSignature();

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
     * @param int $orderId The order ID the line item is associated with
     * @param array $options Options to set on the line item
     * @param int $qty The quantity to set on the line item
     * @param string $note The note on the line item
     * @return LineItem
     *
     * @throws InvalidArgumentException if the purchasable ID is not valid
     */
    public function createLineItem(int $orderId, int $purchasableId, array $options, int $qty = 1, string $note = ''): LineItem
    {
        $lineItem = new LineItem();
        $lineItem->qty = $qty;
        $lineItem->setOptions($options);
        $lineItem->orderId = $orderId;
        $lineItem->note = $note;

        /** @var PurchasableInterface $purchasable */
        $purchasable = Craft::$app->getElements()->getElementById($purchasableId);
        $lineItem->setPurchasable($purchasable);

        if ($purchasable && ($purchasable instanceof PurchasableInterface)) {
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
                'price',
                'saleAmount',
                'salePrice',
                'weight',
                'length',
                'height',
                'width',
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
