<?php
namespace Craft;

class Market_ProductsFieldType extends BaseElementFieldType
{
	// Properties
	// =========================================================================

	/**
	 * The element type this field deals with.
	 *
	 * @var string $elementType
	 */
	protected $elementType = 'Market_Product';

	/**
	 * @inheritDoc IComponentType::getName()
	 *
	 * @return string
	 */
	public function getName ()
	{
		return Craft::t('Commerce Product');
	}

	// Protected Methods
	// =========================================================================

	/**
	 * @inheritDoc BaseElementFieldType::getAddButtonLabel()
	 *
	 * @return string
	 */
	protected function getAddButtonLabel ()
	{
		return Craft::t('Add a Product');
	}
}
