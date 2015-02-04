<?php

namespace Craft;

/**
 * Class Stripey_ProductModel
 *
 * @property int                  $id
 * @property DateTime             $availableOn
 * @property DateTime             $expiresOn
 * @property int                  typeId
 * @property int                  authorId
 * @property bool                 enabled
 *
 * @property Stripey_VariantModel $masterVariant
 * @package Craft
 */
class Stripey_ProductModel extends BaseElementModel
{

	const LIVE = 'live';
	const PENDING = 'pending';
	const EXPIRED = 'expired';

	protected $elementType = 'Stripey_Product';
	protected $modelRecord = 'Stripey_ProductRecord';
	protected $_variants = NULL;

	private $_masterVariant;

	public function isEditable()
	{
		return true;
	}

	public function __toString()
	{
		return $this->title;
	}

	public function getCpEditUrl()
	{
		$productType = $this->getProductType();

		return UrlHelper::getCpUrl('stripey/products/' . $productType->handle . '/' . $this->id);
	}

	public function getProductType()
	{
		return craft()->stripey_productType->getById($this->typeId);
	}

	public function getFieldLayout()
	{
		if ($this->getProductType()) {
			return $this->productType->getFieldLayout();
		}
	}

	public function getStatus()
	{
		$status = parent::getStatus();

		if ($status == static::ENABLED && $this->availableOn) {
			$currentTime = DateTimeHelper::currentTimeStamp();
			$availableOn = $this->availableOn->getTimestamp();
			$expiresOn   = ($this->expiresOn ? $this->expiresOn->getTimestamp() : NULL);

			if ($availableOn <= $currentTime && (!$expiresOn || $expiresOn > $currentTime)) {
				return static::LIVE;
			} else if ($availableOn > $currentTime) {
				return static::PENDING;
			} else {
				return static::EXPIRED;
			}
		}

		return $status;
	}

	protected function defineAttributes()
	{
		return array_merge(parent::defineAttributes(), array(
			'typeId'      => AttributeType::Number,
			'authorId'    => AttributeType::Number,
			'availableOn' => AttributeType::DateTime,
			'expiresOn'   => AttributeType::DateTime
		));
	}

	public function isLocalized()
	{
		return false;
	}

	public function getType()
	{
		return $this->getProductType();
	}

	/**
	 * @return Stripey_VariantModel[]
	 */
	public function getVariants()
	{
		if (is_null($this->_variants)) {
			$this->_variants = craft()->stripey_variant->getAllByProductId($this->id, false);
		}

		return $this->_variants;
	}

	/**
	 * @return BaseModel|Stripey_VariantModel
	 */
	public function getMasterVariant()
	{
		if (!$this->_masterVariant) {
			if ($this->id) {
				$this->_masterVariant = craft()->stripey_product->getMasterVariant($this->id);
			}
			if (!$this->_masterVariant) {
				$this->_masterVariant = new Stripey_VariantModel();
			}
		}

		return $this->_masterVariant;
	}

	public function getOptionTypesIds()
	{
		if (!$this->id) {
			return array();
		}

		return array_map(function ($optionType) {
			return $optionType->id;
		}, $this->getOptionTypes());
	}

	public function getOptionTypes()
	{
		return craft()->stripey_product->getOptionTypes($this->id);

	}

}