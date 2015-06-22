<?php

namespace Craft;

use Market\Helpers\MarketDbHelper;

/**
 * Class Market_LineItemService
 *
 * @package Craft
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
                    $lineItem->discountAmount +
                    $lineItem->shippingAmount +
                    $lineItem->saleAmount
                ) * $lineItem->qty)
            + $lineItem->taxAmount;

        $lineItemRecord->purchasableId = $lineItem->purchasableId;
        $lineItemRecord->orderId       = $lineItem->orderId;
        $lineItemRecord->taxCategoryId = $lineItem->taxCategoryId;

        $lineItemRecord->qty         = $lineItem->qty;
        $lineItemRecord->price       = $lineItem->price;
        $lineItemRecord->total       = $lineItem->total;
        $lineItemRecord->weight      = $lineItem->weight;
        $lineItemRecord->optionsJson = $lineItem->optionsJson;

        $lineItemRecord->saleAmount     = $lineItem->saleAmount;
        $lineItemRecord->taxAmount      = $lineItem->taxAmount;
        $lineItemRecord->discountAmount = $lineItem->discountAmount;
        $lineItemRecord->shippingAmount = $lineItem->shippingAmount;

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

        if ($purchasable->id) {
            $lineItem->fillFromPurchasable($purchasable);
        } else {
            $lineItem->addError('purchasableId', 'Purchasable not found');
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