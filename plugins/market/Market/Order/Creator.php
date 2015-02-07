<?php

namespace Market\Order;

use Craft\BaseElementModel;
use Craft\Market_OrderRecord as OrderRecord;


class Creator
{
	/** @var \Craft\BaseElementModel $_charge */
	private $_order;
	private $_isNewOrder;

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

				$number = \Market\Market::app()['hashids']->encode($this->_order->id);
				// If you ever run out of hashids just change the R to something else lol.
				$orderRecord->number = 'R'.$number;
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