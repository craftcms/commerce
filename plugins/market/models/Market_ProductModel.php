<?php

namespace Craft;

/**
 * Class Market_ProductModel
 *
 * @property int                  $id
 * @property DateTime             $availableOn
 * @property DateTime             $expiresOn
 * @property int                  typeId
 * @property int                  authorId
 * @property int                  taxCategoryId
 * @property bool                 enabled
 *
 * @property Market_TaxCategoryModel taxCategory
 * @property Market_VariantModel $masterVariant
 * @property Market_VariantModel[] $variants
 * @package Craft
 */
class Market_ProductModel extends BaseElementModel
{

	const LIVE = 'live';
	const PENDING = 'pending';
	const EXPIRED = 'expired';

	protected $elementType = 'Market_Product';
	protected $modelRecord = 'Market_ProductRecord';
	protected $_variants = NULL;

	private $_masterVariant;

	/**
	 * Setting default taxCategoryId
	 * @param null $attributes
	 */
	public function __construct($attributes = null)
	{
		parent::__construct($attributes);

		if(empty($this->taxCategoryId)) {
			$this->taxCategoryId = craft()->market_taxCategory->getDefaultId();
		}
	}

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

		return UrlHelper::getCpUrl('market/products/' . $productType->handle . '/' . $this->id);
	}

	public function getProductType()
	{
		return craft()->market_productType->getById($this->typeId);
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
			'taxCategoryId' => AttributeType::Number,
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
	 * @return Market_VariantModel[]
	 */
	public function getVariants()
	{
		if (is_null($this->_variants)) {
			$this->_variants = craft()->market_variant->getAllByProductId($this->id, false);
		}

		return $this->_variants;
	}

	/**
	 * @return Market_VariantModel
	 */
	public function getMasterVariant()
	{
		if (!$this->_masterVariant) {
			if ($this->id) {
				$this->_masterVariant = craft()->market_product->getMasterVariant($this->id);
			}
			if (!$this->_masterVariant) {
				$this->_masterVariant = new Market_VariantModel();
			}
		}

		return $this->_masterVariant;
	}

	/**
	 * @return Market_TaxCategoryModel
	 */
	public function getTaxCategory()
	{
		return $this->_variants = craft()->market_taxCategory->getById($this->taxCategoryId);
	}

	/**
	 * @return int[]
	 */
	public function getOptionTypesIds()
	{
		if (!$this->id) {
			return array();
		}

		return array_map(function ($optionType) {
			return $optionType->id;
		}, $this->getOptionTypes());
	}

	/**
	 * @return Market_OptionTypeModel[]
	 */
	public function getOptionTypes()
	{
		return craft()->market_product->getOptionTypes($this->id);
	}
}