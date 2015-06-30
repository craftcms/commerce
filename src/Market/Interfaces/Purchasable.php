<?php

namespace Market\Interfaces;


interface Purchasable
{

	// Information

	public function getPurchasableId();

	public function getPrice();

	public function getSku();

	public function getDescription();

	// Hooks

	/**
	 * Validates this purchasable for the line item it is on.
	 *
	 * You can add model errors to the line item like this: `$lineItem->addError('qty', $errorText);`
	 *
	 * @param \Craft\Market_LineItemModel $lineItem
	 *
	 * @return mixed
	 */
	public function validateLineItem(\Craft\Market_LineItemModel $lineItem);
}