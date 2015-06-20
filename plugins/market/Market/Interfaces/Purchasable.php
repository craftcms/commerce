<?php

namespace Market\Interfaces;


interface Purchasable
{

	// information

	public function getPurchasablePrice();

	public function getPurchasableSku();

	public function getPurchasableDescription();

	// hooks

	public function validateLineItem(\Craft\Market_LineItemModel $lineItem);
}