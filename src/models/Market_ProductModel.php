<?php

namespace Craft;

use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_ProductModel
 *
 * @property int                      $id
 * @property DateTime                 $availableOn
 * @property DateTime                 $expiresOn
 * @property int                      typeId
 * @property int                      authorId
 * @property int                      taxCategoryId
 * @property bool                     enabled
 *
 * Inherited from record:
 * @property Market_ProductTypeModel  type
 * @property Market_TaxCategoryModel  taxCategory
 * @property Market_VariantModel[]    allVariants
 * @property Market_VariantModel      $master
 *
 * Magic properties:
 * @property Market_VariantModel[]    $variants
 * @property Market_VariantModel[]    $nonMasterVariants
 * @property string                   name
 * @package Craft
 */
class Market_ProductModel extends BaseElementModel
{
	use Market_ModelRelationsTrait;

	const LIVE = 'live';
	const PENDING = 'pending';
	const EXPIRED = 'expired';

	protected $elementType = 'Market_Product';

	// Public Methods
	// =============================================================================

	/**
	 * Setting default taxCategoryId
	 *
	 * @param null $attributes
	 */
	public function __construct($attributes = NULL)
	{
		parent::__construct($attributes);

		if (empty($this->taxCategoryId)) {
			$this->taxCategoryId = craft()->market_taxCategory->getDefaultId();
		}
	}

	/**
	 * @return bool
	 */
	public function isEditable()
	{
		return true;
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		return $this->title;
	}

	/*
	 * Name is an alias to title.
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->title;
	}

	/**
	 * What is the Url Format for this ProductType
	 *
	 * @return string
	 */
	public function getUrlFormat()
	{
		if ($this->typeId) {
			return craft()->market_productType->getById($this->typeId)->urlFormat;
		}

		return NULL;
	}

	/*
	 * Url to edit this Product in the control panel.
	 */
	public function getCpEditUrl()
	{
		if ($this->typeId) {
			$productTypeHandle = craft()->market_productType->getById($this->typeId)->handle;
			return UrlHelper::getCpUrl('market/products/' .$productTypeHandle. '/' . $this->id);
		}

		return NULL;

	}

	/**
	 * @return FieldLayoutModel|null
	 */
	public function getFieldLayout()
	{
		if ($this->typeId) {
			return craft()->market_productType->getById($this->typeId)->getFieldLayout();
		}

		return NULL;
	}

	/**
	 * @return null|string
	 */
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

	public function isLocalized()
	{
		return false;
	}

	/**
	 * Either only master variant if there is only one or all without master
	 *
	 * @return Market_VariantModel[]
	 */
	public function getVariants()
	{
		if (count($this->allVariants) == 1) {
			$variants = $this->allVariants;
		} else {
			$variants = $this->nonMasterVariants;
		}

        craft()->market_variant->applySales($variants, $this);
        return $variants;
	}

	/**
	 * @return Market_VariantModel[]
	 */
	public function getNonMasterVariants()
	{
		return array_filter($this->allVariants, function ($v) {
			return !$v->isMaster;
		});
	}

	// Protected Methods
	// =============================================================================

	/**
	 * @return array
	 */
	protected function defineAttributes()
	{
		return array_merge(parent::defineAttributes(), [
			'typeId'        => AttributeType::Number,
			'authorId'      => AttributeType::Number,
			'taxCategoryId' => AttributeType::Number,
			'availableOn'   => AttributeType::DateTime,
			'expiresOn'     => AttributeType::DateTime
		]);
	}
}