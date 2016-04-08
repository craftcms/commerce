<?php
namespace Craft;

use Commerce\Helpers\CommerceDbHelper;
use Commerce\Interfaces\Purchasable;

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
class Commerce_LineItemsService extends BaseApplicationComponent
{
    /**
     * @param int $id
     *
     * @return Commerce_LineItemModel[]
     */
    public function getAllLineItemsByOrderId($id)
    {
        $lineItems = [];

        if($id){
            $lineItems = $this->_createLineItemsQuery()
                ->where('lineitems.orderId = :orderId', [':orderId' => $id])
                ->queryAll();
        }

        return Commerce_LineItemModel::populateModels($lineItems);
    }

    /**
     * Find line item by order and variant
     *
     * @param int $orderId
     * @param int $purchasableId
     * @param array $options
     *
     * @return Commerce_LineItemModel|null
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
            return Commerce_LineItemModel::populateModel($result);
        }

        return null;
    }


    /**
     * Update line item and recalculate order
     *
     * @param Commerce_OrderModel $order
     * @param Commerce_LineItemModel $lineItem
     * @param string $error
     *
     * @return bool
     * @throws Exception
     */
    public function updateLineItem(Commerce_OrderModel $order, Commerce_LineItemModel $lineItem, &$error = '')
    {
        if (!$lineItem->purchasableId) {
            $this->deleteLineItem($lineItem);
            craft()->commerce_orders->saveOrder($order);
            $error = Craft::t("Item no longer for sale. Removed from cart.");

            return false;
        }

        if ($this->saveLineItem($lineItem)) {
            craft()->commerce_orders->saveOrder($order);

            return true;
        } else {
            $errors = $lineItem->getAllErrors();
            $error = array_pop($errors);

            return false;
        }
    }

    /**
     * @param Commerce_LineItemModel $lineItem
     *
     * @return bool
     * @throws \Exception
     */
    public function saveLineItem(Commerce_LineItemModel $lineItem)
    {

        if ($lineItem->qty <= 0 && $lineItem->id) {
            $this->deleteLineItem($lineItem);

            return true;
        }

	    $isNewLineItem = !$lineItem->id;

        if (!$lineItem->id) {
            $lineItemRecord = new Commerce_LineItemRecord();
        } else {
            $lineItemRecord = Commerce_LineItemRecord::model()->findById($lineItem->id);

            if (!$lineItemRecord) {
                throw new Exception(Craft::t('No line item exists with the ID “{id}”',
                    ['id' => $lineItem->id]));
            }
        }

        $lineItem->total = $lineItem->getTotal();

        $lineItemRecord->purchasableId = $lineItem->purchasableId;
        $lineItemRecord->orderId = $lineItem->orderId;
        $lineItemRecord->taxCategoryId = $lineItem->taxCategoryId;

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

        /** @var \Commerce\Interfaces\Purchasable $purchasable */
        $purchasable = craft()->elements->getElementById($lineItem->purchasableId);
        $purchasable->validateLineItem($lineItem);

        $lineItem->addErrors($lineItemRecord->getErrors());

        if ($lineItem->hasErrors()) {
            return false;
        }

        //raising event
        $event = new Event($this, [
            'lineItem' => $lineItem,
            'isNewLineItem'    => $isNewLineItem,
        ]);
        $this->onBeforeSaveLineItem($event);

        CommerceDbHelper::beginStackedTransaction();
        try {
            if ($event->performAction) {

                $success = $lineItemRecord->save(false);

	            if ($success)
	            {
		            if ($isNewLineItem)
		            {
			            $lineItem->id = $lineItemRecord->id;
		            }

		            CommerceDbHelper::commitStackedTransaction();
	            }

            }else{
	            $success = false;
            }
        } catch (\Exception $e) {
            CommerceDbHelper::rollbackStackedTransaction();
            throw $e;
        }

	    if ($success)
	    {
		    // Fire an 'onSaveLineItem' event
		    $this->onSaveLineItem(new Event($this, [
			    'lineItem' => $lineItem,
			    'isNewLineItem'    => $isNewLineItem,
		    ]));
	    }

	    return $success;
    }

    /**
     * @param int $id
     *
     * @return Commerce_LineItemModel|null
     */
    public function getLineItemById($id)
    {
        $result = $this->_createLineItemsQuery()
            ->where('lineitems.id = :id', [':id' => $id])
            ->queryRow();

        if ($result) {
            return Commerce_LineItemModel::populateModel($result);
        }

        return null;
    }

    /**
     * @param $purchasableId
     * @param $orderId
     * @param $options
     * @param $qty
     * @return Commerce_LineItemModel
     * @throws Exception
     */
    public function createLineItem($purchasableId, $orderId, $options, $qty)
    {
        $lineItem = new Commerce_LineItemModel();
        $lineItem->purchasableId = $purchasableId;
        $lineItem->qty = $qty;
        ksort($options);
        $lineItem->options = $options;
        $lineItem->optionsSignature = md5(json_encode($options));
        $lineItem->orderId = $orderId;

        /** @var \Commerce\Interfaces\Purchasable $purchasable */
        $purchasable = craft()->elements->getElementById($purchasableId);

        if ($purchasable && $purchasable instanceof Purchasable) {
            $lineItem->fillFromPurchasable($purchasable);
        } else {
            throw new Exception(Craft::t('Not a purchasable ID'));
        }

        return $lineItem;
    }

    /**
     * @param Commerce_LineItemModel $lineItem
     *
     * @return int
     */
    public function deleteLineItem($lineItem)
    {
        return Commerce_LineItemRecord::model()->deleteByPk($lineItem->id);
    }

    /**
     * @param int $orderId
     *
     * @return int
     */
    public function deleteAllLineItemsByOrderId($orderId)
    {
        return Commerce_LineItemRecord::model()->deleteAllByAttributes(['orderId' => $orderId]);
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
		if (empty($params['lineItem']) || !($params['lineItem'] instanceof Commerce_LineItemModel))
		{
			throw new Exception('onBeforeSaveLineItem event requires "lineItem" param with Commerce_LineItemModel instance that is being saved.');
		}

		if (!isset($params['isNewLineItem']))
		{
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
		if (empty($params['lineItem']) || !($params['lineItem'] instanceof Commerce_LineItemModel))
		{
			throw new Exception('onSaveLineItem event requires "lineItem" param with Commerce_LineItemModel instance that is being saved.');
		}

		if (!isset($params['isNewLineItem']))
		{
			throw new Exception('onSaveLineItem event requires "isNewLineItem" param with a boolean to determine if the line item is new.');
		}

		$this->raiseEvent('onSaveLineItem', $event);
	}

    /**
     * Returns a DbCommand object prepped for retrieving sections.
     *
     * @return DbCommand
     */
    private function _createLineItemsQuery()
    {

        return craft()->db->createCommand()
            ->select('lineitems.id, lineitems.orderId, lineitems.purchasableId, lineitems.options, lineitems.optionsSignature, lineitems.price, lineitems.saleAmount, lineitems.salePrice, lineitems.tax, lineitems.taxIncluded, lineitems.shippingCost, lineitems.discount, lineitems.weight, lineitems.height, lineitems.length, lineitems.width, lineitems.total, lineitems.qty, lineitems.note, lineitems.snapshot, lineitems.taxCategoryId')
            ->from('commerce_lineitems lineitems')
            ->order('lineitems.id');
    }


}
