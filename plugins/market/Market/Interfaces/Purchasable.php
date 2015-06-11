<?php

namespace Market\Interfaces;


interface Purchasable
{

	// information

	public function getPurchasablePrice();

	public function getPurchasableSku();

	public function getPurchasableDescription();


	// events

	public function onAddToOrder(\Craft\Market_OrderModel $order);

	public function onRemoveFromOrder(\Craft\Market_OrderModel $order);

	public function onOrderCompleted(\Craft\Market_OrderModel $order);
}