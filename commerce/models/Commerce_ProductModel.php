<?php
namespace Craft;

use Commerce\Traits\Commerce_ModelRelationsTrait;

/**
 * Product model.
 *
 * @property int                       $id
 * @property DateTime                  $availableOn
 * @property DateTime                  $expiresOn
 * @property int                       $typeId
 * @property int                       $authorId
 * @property int                       $taxCategoryId
 * @property bool                      $promotable
 * @property bool                      $freeShipping
 * @property bool                      $enabled
 *
 * @property Commerce_ProductTypeModel $type
 * @property Commerce_TaxCategoryModel $taxCategory
 * @property Commerce_VariantModel[]   $variants
 *
 * @property string                    $name
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_ProductModel extends BaseElementModel
{
	use Commerce_ModelRelationsTrait;

	const LIVE = 'live';
	const PENDING = 'pending';
	const EXPIRED = 'expired';

	/**
	 * @var string
	 */
	protected $elementType = 'Commerce_Product';

	// Public Methods
	// =============================================================================


	/**
	 * @return bool
	 */
	public function isEditable ()
	{
		return true;
	}

	/**
	 * @return string
	 */
	public function __toString ()
	{
		return $this->title;
	}

	/**
	 * Allow the variant to ask the product what data to snapshot
	 *
	 * @return string
	 */
	public function getSnapshot ()
	{
		$data = [
			'title' => $this->getTitle(),
			'name'  => $this->getTitle()
		];

		return array_merge($this->getAttributes(), $data);
	}

	/*
	 * Name is an alias to title.
	 *
	 * @return string
	 */
	/**
	 * @return mixed
	 */
	public function getName ()
	{
		return $this->title;
	}

	/*
	 * Url to edit this Product in the control panel.
	 */

	/**
	 * What is the Url Format for this ProductType
	 *
	 * @return string
	 */
	public function getUrlFormat ()
	{
		$productType = $this->getType();

		if ($productType && $productType->hasUrls)
		{
			$productTypeLocales = $productType->getLocales();

			if (isset($productTypeLocales[$this->locale]))
			{
				return $productTypeLocales[$this->locale]->urlFormat;
			}
		}
	}

	/**
	 * Gets the products type
	 */
	public function getType ()
	{
		if ($this->typeId)
		{
			return craft()->commerce_productType->getById($this->typeId);
		}
	}

	/**
	 * @return null|string
	 */
	public function getCpEditUrl ()
	{
		$productType = $this->getType();

		if ($productType)
		{
			// The slug *might* not be set if this is a Draft and they've deleted it for whatever reason
			$url = UrlHelper::getCpUrl('commerce/products/'.$productType->handle.'/'.$this->id.($this->slug ? '-'.$this->slug : ''));

			if (craft()->isLocalized() && $this->locale != craft()->language)
			{
				$url .= '/'.$this->locale;
			}

			return $url;
		}
	}

	/**
	 * @return FieldLayoutModel|null
	 */
	public function getFieldLayout ()
	{
		if ($this->typeId)
		{
			return craft()->commerce_productType->getById($this->typeId)->asa('productFieldLayout')->getFieldLayout();
		}

		return null;
	}

	/**
	 * @return null|string
	 */
	public function getStatus ()
	{
		$status = parent::getStatus();

		if ($status == static::ENABLED && $this->availableOn)
		{
			$currentTime = DateTimeHelper::currentTimeStamp();
			$availableOn = $this->availableOn->getTimestamp();
			$expiresOn = ($this->expiresOn ? $this->expiresOn->getTimestamp() : null);

			if ($availableOn <= $currentTime && (!$expiresOn || $expiresOn > $currentTime))
			{
				return static::LIVE;
			}
			else
			{
				if ($availableOn > $currentTime)
				{
					return static::PENDING;
				}
				else
				{
					return static::EXPIRED;
				}
			}
		}

		return $status;
	}

	/**
	 * @return bool
	 */
	public function isLocalized ()
	{
		return true;
	}

	/**
	 * Either only implicit variant if there is only one or all without implicit
	 * Applies sales to the product before returning
	 *
	 * @return Commerce_VariantModel[]
	 */
	public function getVariants ()
	{
		$variants = craft()->commerce_variant->getAllByProductId($this->id);
		craft()->commerce_variant->applySales($variants, $this);

		if ($this->type->hasVariants)
		{
			$variants = array_filter($variants, function ($v)
			{
				return !$v->isImplicit;
			});
		}
		else
		{
			$variants = array_filter($variants, function ($v)
			{
				return $v->isImplicit;
			});
		}

		return $variants;
	}

	/**
	 * @return bool|mixed
	 * @throws Exception
	 */
	public function getImplicitVariant ()
	{

		if ($this->id)
		{
			$variants = craft()->commerce_variant->getAllByProductId($this->id);
			craft()->commerce_variant->applySales($variants, $this);

			$implicitVariant = array_filter($variants, function ($v)
			{
				return $v->isImplicit;
			});

			if (count($implicitVariant) == 1)
			{
				return array_shift(array_values($implicitVariant));
			}
			else
			{
				throw new Exception('More than one implicit variant found. Contact Support.');

				return false;
			}
		}

		return false;
	}

	// Protected Methods
	// =============================================================================

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return array_merge(parent::defineAttributes(), [
			'typeId'        => AttributeType::Number,
			'authorId'      => AttributeType::Number,
			'taxCategoryId' => AttributeType::Number,
			'promotable'    => AttributeType::Bool,
			'freeShipping'  => AttributeType::Bool,
			'availableOn'   => AttributeType::DateTime,
			'expiresOn'     => AttributeType::DateTime
		]);
	}
}