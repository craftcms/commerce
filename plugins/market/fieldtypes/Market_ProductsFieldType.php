<?php
namespace Craft;

class Market_ProductsFieldType extends BaseElementFieldType
{

	protected $elementType = 'Market_Product';

	protected function getAddButtonLabel()
	{
		return Craft::t('Add an Product');
	}
}
