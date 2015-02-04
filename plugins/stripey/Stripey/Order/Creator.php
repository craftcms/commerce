<?php

namespace Stripey\Order;

use Craft\BaseElementModel;
use Craft\Stripey_OrderRecord as OrderRecord;

class Creator
{
	/** @var \BaseElementModel $_charge */
	private $_order;

	private $_isNewOrder;

	function __construct()
	{

	}

	public function save(BaseElementModel $order)
	{
		$this->_order      = $order;
		$this->_isNewOrder = !$order->id;

		if ($this->_isNewOrder) {
			return $this->createNewOrder();
		} else {
			return $this->saveOrder();
		}
	}

	private function createNewOrder()
	{

		$orderRecord         = new OrderRecord();
		$orderRecord->typeId = $this->_order->typeId;

		$orderRecord->validate();

		$this->_order->addErrors($orderRecord->getErrors());

		if (!$this->_order->hasErrors()) {
			if (\Craft\craft()->elements->saveElement($this->_order)) {
				$orderRecord->id = $this->_order->id;
				$orderRecord->save(false);

				return true;
			}
		}

		return false;
	}

	private function saveOrder()
	{
		$orderRecord = OrderRecord::model()->findById($this->_order->id);

		if (!$orderRecord) {
			throw new Exception(Craft::t('No order exists with the ID “{id}”', array('id' => $this->_order->id)));
		}

		if (\Craft\craft()->elements->saveElement($this->_order)) {

			$orderRecord->typeId = $this->_order->typeId;
			$orderRecord->save();

			return true;
		}

		return false;
	}

}