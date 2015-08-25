<?php
namespace Craft;

use Market\Helpers\MarketDbHelper;
use Market\Interfaces\Purchasable;
/**
 * Class Market_LineItemService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Market_LineItemService extends BaseApplicationComponent
{
    /**
     * @param int $id
     *
     * @return Market_LineItemModel[]
     */
    public function getAllByOrderId($id)
    {
        $lineItems = Market_LineItemRecord::model()->findAllByAttributes(['orderId' => $id]);

        return Market_LineItemModel::populateModels($lineItems);
    }

    /**
     * Find line item by order and variant
     *
     * @param int $orderId
     * @param int $purchasableId
     *
     * @return Market_LineItemModel
     */
    public function getByOrderPurchasable($orderId, $purchasableId)
    {
        $purchasable = Market_LineItemRecord::model()->findByAttributes([
            'orderId'       => $orderId,
            'purchasableId' => $purchasableId,
        ]);

        return Market_LineItemModel::populateModel($purchasable);
    }


    /**
     * Update line item and recalculate order
     *
     * @TODO check that the line item belongs to the current user
     *
     * @param Market_LineItemModel $lineItem
     * @param string               $error
     *
     * @return bool
     * @throws Exception
     */
    public function update(Market_LineItemModel $lineItem, &$error = '')
    {
        if ($this->save($lineItem)) {
            craft()->market_order->save($lineItem->order);

            return true;
        } else {
            $errors = $lineItem->getAllErrors();
            $error  = array_pop($errors);

            return false;
        }
    }

    /**
     * @param Market_LineItemModel $lineItem
     *
     * @return bool
     * @throws \Exception
     */
    public function save(Market_LineItemModel $lineItem)
    {

        if($lineItem->qty <= 0 && $lineItem->id){
            $this->delete($lineItem);
            return true;
        }

        if (!$lineItem->id) {
            $lineItemRecord = new Market_LineItemRecord();
        } else {
            $lineItemRecord = Market_LineItemRecord::model()->findById($lineItem->id);

            if (!$lineItemRecord) {
                throw new Exception(Craft::t('No line item exists with the ID “{id}”',
                    ['id' => $lineItem->id]));
            }
        }

        $lineItem->total = ((
                    $lineItem->price +
                    $lineItem->discount +
                    $lineItem->shippingCost +
                    $lineItem->saleAmount
                ) * $lineItem->qty)
            + $lineItem->tax;

        $lineItemRecord->purchasableId = $lineItem->purchasableId;
        $lineItemRecord->orderId       = $lineItem->orderId;
        $lineItemRecord->taxCategoryId = $lineItem->taxCategoryId;

        $lineItemRecord->qty           = $lineItem->qty;
        $lineItemRecord->price         = $lineItem->price;

        $lineItemRecord->weight        = $lineItem->weight;
        $lineItemRecord->snapshot      = $lineItem->snapshot;
        $lineItemRecord->note          = $lineItem->note;

        $lineItemRecord->saleAmount    = $lineItem->saleAmount;
        $lineItemRecord->tax           = $lineItem->tax;
        $lineItemRecord->discount      = $lineItem->discount;
        $lineItemRecord->shippingCost  = $lineItem->shippingCost;
        $lineItemRecord->total         = $lineItem->total;

        // Cant have discounts making things less than zero.
        if ($lineItemRecord->total < 0){
            $lineItemRecord->total = 0;
        }

        $lineItemRecord->validate();

        /** @var \Market\Interfaces\Purchasable $purchasable */
        $purchasable = craft()->elements->getElementById($lineItem->purchasableId);
        $purchasable->validateLineItem($lineItem);

        $lineItem->addErrors($lineItemRecord->getErrors());

        MarketDbHelper::beginStackedTransaction();
        try {
            if (!$lineItem->hasErrors()) {
                $lineItemRecord->save(false);
                $lineItemRecord->id = $lineItem->id;

                MarketDbHelper::commitStackedTransaction();

                return true;
            }
        } catch (\Exception $e) {
            MarketDbHelper::rollbackStackedTransaction();
            throw $e;
        }

        return false;
    }

    /**
     * @param int $id
     *
     * @return Market_LineItemModel
     */
    public function getById($id)
    {
        $lineItem = Market_LineItemRecord::model()->findById($id);

        return Market_LineItemModel::populateModel($lineItem);
    }

    /**
     * @param int $purchasableId
     * @param int $orderId
     * @param int $qty
     *
     * @return Market_LineItemModel
     */
    public function create($purchasableId, $orderId, $qty)
    {
        $lineItem                = new Market_LineItemModel();
        $lineItem->purchasableId = $purchasableId;
        $lineItem->qty           = $qty;
        $lineItem->orderId       = $orderId;

        /** @var \Market\Interfaces\Purchasable $purchasable */
        $purchasable = craft()->elements->getElementById($purchasableId);

        if ($purchasable->id && $purchasable instanceof Purchasable) {
            $lineItem->fillFromPurchasable($purchasable);
        } else {
            $lineItem->addError('purchasableId', Craft::t('Item not found or is not a purchasable.'));
        }

        return $lineItem;
    }

    /**
     * @param Market_LineItemModel $lineItem
     *
     * @return int
     */
    public function delete($lineItem)
    {
        return Market_LineItemRecord::model()->deleteByPk($lineItem->id);
    }

    /**
     * @param int $orderId
     *
     * @return int
     */
    public function deleteAllByOrderId($orderId)
    {
        return Market_LineItemRecord::model()->deleteAllByAttributes(['orderId' => $orderId]);
    }
}