<?php
namespace Craft;

/**
 * Class Commerce_CustomerFieldType
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.fieldtypes
 * @since     1.0
 */
class Commerce_CustomerFieldType extends BaseFieldType
{
	// Properties
	// =========================================================================

	/** @var  Commerce_CustomerModel $_customer */
	private $_customer;

	/**
	 * @inheritDoc IComponentType::getName()
	 *
	 * @return string
	 */
	public function getName ()
	{
		return Craft::t('Commerce Customer Info');
	}

	/**
	 * @inheritDoc BaseElementFieldType::defineContentAttribute()
	 * @return bool
	 */
	public function defineContentAttribute ()
	{
		return false;
	}

	/**
	 * @inheritDoc BaseElementFieldType::getInputHtml()
	 * @param string $name
	 * @param mixed  $value
	 *
	 * @return string
	 */
	public function getInputHtml ($name, $value)
	{
		if(!($this->element instanceof UserModel)) {
			return '<span style="color: #da5a47">'. Craft::t('Error. Commerce Customer Info field is for user profiles only.'). '</span>';
		}

		return craft()->templates->render('commerce/_fieldtypes/customer/_input', [
			'customer' => $this->getCustomer()
		]);
	}

	/**
	 * @return BaseModel|Commerce_CustomerModel
	 */
	private function getCustomer ()
	{
		if (!$this->_customer)
		{
			$this->_customer = craft()->commerce_customer->getByUserId($this->element->id);
		}

		return $this->_customer;
	}

	/**
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public function prepValue ($value)
	{
		return $value;
	}
}
