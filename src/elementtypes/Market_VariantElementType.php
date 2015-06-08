<?php
namespace Craft;

require_once(__DIR__ . '/Market_BaseElementType.php');

class Market_VariantElementType extends Market_BaseElementType
{

	public function getName()
	{
		return Craft::t('Variants');
	}

	public function hasContent()
	{
		return true;
	}

	public function hasTitles()
	{
		return false;
	}

	public function hasStatuses()
	{
		return false;
	}

	public function getSources($context = NULL)
	{
		$sources = [

			'*' => [
				'label' => Craft::t('All product\'s variants'),
			]
		];

		return $sources;

	}


	public function defineTableAttributes($source = NULL)
	{
		return [
			'sku'            => Craft::t('sku'),
			'price'          => Craft::t('price'),
			'width'          => Craft::t('width'),
			'height'         => Craft::t('height'),
			'length'         => Craft::t('length'),
			'weight'         => Craft::t('weight'),
			'stock'          => Craft::t('stock'),
			'unlimitedStock' => Craft::t('unlimitedStock'),
			'minQty'         => Craft::t('minQty')

		];
	}

	public function defineSearchableAttributes()
	{
		return ['sku'];
	}


	public function getTableAttributeHtml(BaseElementModel $element, $attribute)
	{
		return parent::getTableAttributeHtml($element, $attribute);
	}


	public function defineSortableAttributes()
	{
		return [
			'sku'       => Craft::t('SKU')
		];
	}


	public function defineCriteriaAttributes()
	{
		return [
			'sku'      =>  AttributeType::Mixed
		];
	}

	public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
	{
		$query
			->addSelect("variants.id,variants.productId,variants.isMaster,variants.sku,variants.price,variants.width,variants.height,variants.length,variants.weight,variants.stock,variants.unlimitedStock,variants.minQty")
			->join('market_variants variants', 'variants.id = elements.id');

		if ($criteria->sku) {
			$query->andWhere(DbHelper::parseDateParam('variants.sku', $criteria->sku, $query->params));
		}

	}

	public function populateElementModel($row)
	{
		return Market_VariantModel::populateModel($row);
	}

	public function saveElement(BaseElementModel $element, $params)
	{
		craft()->market_variant->save($element);
	}

} 