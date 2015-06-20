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
		return ['sku', 'price', 'width', 'height', 'length', 'weight', 'stock', 'unlimitedStock', 'minQty'];

	}


	public function getTableAttributeHtml(BaseElementModel $element, $attribute)
	{
		return parent::getTableAttributeHtml($element, $attribute);
	}


	public function defineSortableAttributes()
	{
		return [
			'sku'            => Craft::t('SKU'),
			'price'          => Craft::t('Price'),
			'width'          => Craft::t('Width'),
			'height'         => Craft::t('Height'),
			'length'         => Craft::t('Length'),
			'weight'         => Craft::t('Weight'),
			'stock'          => Craft::t('Stock'),
			'unlimitedStock' => Craft::t('Unlimited Stock'),
			'minQty'         => Craft::t('Min Qty')
		];
	}


	public function defineCriteriaAttributes()
	{
		return [
			'sku'       => AttributeType::Mixed,
			'product'   => AttributeType::Mixed,
			'productId' => AttributeType::Mixed,
			'isMaster' => AttributeType::Mixed,
		];
	}

	public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
	{
		$query
			->addSelect("variants.id,variants.productId,variants.isMaster,variants.sku,variants.price,variants.width,variants.height,variants.length,variants.weight,variants.stock,variants.unlimitedStock,variants.minQty")
			->join('market_variants variants', 'variants.id = elements.id');

		if ($criteria->sku) {
			$query->andWhere(DbHelper::parseParam('variants.sku', $criteria->sku, $query->params));
		}

		if ($criteria->product) {
			if ($criteria->product instanceof Market_ProductModel) {
				$criteria->productId = $criteria->product->id;
				$criteria->product   = NULL;
			} else {
				$query->andWhere(DbHelper::parseParam('variants.productId', $criteria->product, $query->params));
			}
		}

		if ($criteria->productId) {
			$query->andWhere(DbHelper::parseParam('variants.productId', $criteria->productId, $query->params));
		}

		if ($criteria->isMaster) {
			$query->andWhere(DbHelper::parseParam('variants.isMaster', $criteria->isMaster, $query->params));
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