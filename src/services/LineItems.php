<?php
namespace craft\commerce\services;

use Commerce\Interfaces\Purchasable;
use Craft;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Db;
use craft\commerce\models\LineItem;
use craft\commerce\records\LineItem as LineItemRecord;
use yii\base\Component;

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
    /**
     * @param int $id
     *
     * @return LineItem[]
     */
    public function getAllLineItemsByOrderId($id)
    {
        $lineItems = [];

        if ($id) {
            $lineItems = $this->_createLineItemsQuery()
                ->where('lineitems.orderId = :orderId', [':orderId' => $id])
                ->queryAll();
        }

        return LineItem::populateModels($lineItems);
    }

    /**
     * Returns a DbCommand object prepped for retrieving sections.
     *
     * @return DbCommand
     */
    private function _createLineItemsQuery()
    {

        return Craft::$app->getDb()->createCommand()
            ->select('lineitems.id, lineitems.orderId, lineitems.purchasableId, lineitems.options, lineitems.optionsSignature, lineitems.price, lineitems.saleAmount, lineitems.salePrice, lineitems.tax, lineitems.taxIncluded, lineitems.shippingCost, lineitems.discount, lineitems.weight, lineitems.height, lineitems.length, lineitems.width, lineitems.total, lineitems.qty, lineitems.note, lineitems.snapshot, lineitems.taxCategoryId, lineitems.shippingCategoryId')
            ->from('commerce_lineitems lineitems')
            ->order('lineitems.id');
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
    public function getLineItemByOrderPurchasableOptions($orderId, $purchasableId, $options = [])
    {
        ksort($options);
        $signature = md5(json_encode($options));
        $result = $this->_createLineItemsQuery()
            ->where('lineitems.orderId = :orderId AND lineitems.purchasableId = :purchasableId AND lineitems.optionsSignature = :optionsSignature',
                [':orderId' => $orderId, ':purchasableId' => $purchasableId, ':optionsSignature' => $signature])
            ->queryRow();

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
    public function updateLineItem(Order $order, LineItem $lineItem, &$error = '')
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
        } else {
            $errors = $lineItem->getAllErrors();
            $error = array_pop($errors);

            return false;
        }
    }

    /**
     * @param LineItem $lineItem
     *
     * @return int
     */
    public function deleteLineItem(LineItem $lineItem)
    {
        $lineItem = LineItemRecord::findOne($lineItem->id);

        if ($lineItem)
        {
            return $lineItem->delete();
        }
    }

    /**
     * @param LineItem $lineItem
     *
     * @return bool
     * @throws \Exception
     */
    public function saveLineItem(LineItem $lineItem)
    {
        $isNewLineItem = !$lineItem->id;

        if (!$lineItem->id) {
            $lineItemRecord = new LineItem();
        } else {
            $lineItemRecord = LineItemRecord::findOne($lineItem->id);

            if (!$lineItemRecord) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No line item exists with the ID “{id}”',
                    ['id' => $lineItem->id]));
            }
        }

        $lineItem->total = $lineItem->getTotal();

        //raising event
        $event = new Event($this, [
            'lineItem' => $lineItem,
            'isNewLineItem' => $isNewLineItem,
        ]);
        $this->onBeforeSaveLineItem($event);

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

        Db::beginStackedTransaction();
        try {
            if ($event->performAction) {

                $success = $lineItemRecord->save(false);

                if ($success) {
                    if ($isNewLineItem) {
                        $lineItem->id = $lineItemRecord->id;
                    }

                    Db::commitStackedTransaction();
                }
            } else {
                $success = false;
            }
        } catch (\Exception $e) {
            Db::rollbackStackedTransaction();
            throw $e;
        }

        if ($success) {
            // Fire an 'onSaveLineItem' event
            $this->onSaveLineItem(new Event($this, [
                'lineItem' => $lineItem,
                'isNewLineItem' => $isNewLineItem,
            ]));
        }

        return $success;
    }

    /**
     * This event is raised before a line item is saved
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onBeforeSaveLineItem(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['lineItem']) || !($params['lineItem'] instanceof LineItem)) {
            throw new Exception('onBeforeSaveLineItem event requires "lineItem" param with LineItem instance that is being saved.');
        }

        if (!isset($params['isNewLineItem'])) {
            throw new Exception('onBeforeSaveLineItem event requires "isNewLineItem" param with a boolean to determine if the line item is new.');
        }

        $this->raiseEvent('onBeforeSaveLineItem', $event);
    }

    /**
     * This event is raised after a line item has been successfully saved
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onSaveLineItem(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['lineItem']) || !($params['lineItem'] instanceof LineItem)) {
            throw new Exception('onSaveLineItem event requires "lineItem" param with LineItem instance that is being saved.');
        }

        if (!isset($params['isNewLineItem'])) {
            throw new Exception('onSaveLineItem event requires "isNewLineItem" param with a boolean to determine if the line item is new.');
        }

        $this->raiseEvent('onSaveLineItem', $event);
    }

    /**
     * @param int $id
     *
     * @return LineItem|null
     */
    public function getLineItemById($id)
    {
        $result = $this->_createLineItemsQuery()
            ->where('lineitems.id = :id', [':id' => $id])
            ->queryRow();

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
     * @throws Exception
     */
    public function createLineItem($purchasableId, $order, $options, $qty)
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

        if ($purchasable && $purchasable instanceof Purchasable) {
            $lineItem->fillFromPurchasable($purchasable);
        } else {
            throw new Exception(Craft::t('commerce', 'commerce', 'Not a purchasable ID'));
        }

        //raising event
        $event = new Event($this, [
            'lineItem' => $lineItem
        ]);
        $this->onCreateLineItem($event);

        return $lineItem;
    }

    /**
     * This event is raised when a new line item is created generated from a purchasable
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onCreateLineItem(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['lineItem']) || !($params['lineItem'] instanceof LineItem)) {
            throw new Exception('onCreateLineItem event requires "lineItem" param with LineItem instance that is being created.');
        }

        $this->raiseEvent('onCreateLineItem', $event);
    }

    /**
     * @param int $orderId
     *
     * @return int
     */
    public function deleteAllLineItemsByOrderId($orderId)
    {
        return LineItemRecord::deleteAll(['orderId' => $orderId]);
    }

    /**
     * This event is raised when a new line has been populated from a purchasable
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onPopulateLineItem(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['lineItem']) || !($params['lineItem'] instanceof LineItem)) {
            throw new Exception('onPopulateLineItem event requires "lineItem" param with LineItem instance that is being populated from the purchasable.');
        }

        if (empty($params['purchasable']) || !($params['purchasable'] instanceof Purchasable)) {
            throw new Exception('onPopulateLineItem event requires "purchasable" param with a Purchasable.');
        }

        $this->raiseEvent('onPopulateLineItem', $event);
    }


}
