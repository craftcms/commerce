<?php
namespace Craft;

require_once(__DIR__ . '/Market_BaseElementType.php');

class Market_ProductElementType extends Market_BaseElementType
{

	public function getName()
	{
		return Craft::t('Products');
	}

	public function hasContent()
	{
		return true;
	}

	public function hasTitles()
	{
		return true;
	}

	public function hasStatuses()
	{
		return true;
	}

	public function getSources($context = NULL)
	{
		$sources = [

			'*' => [
				'label' => Craft::t('All products'),
			]
		];

		$sources[] = ['heading' => "Product Types"];

		foreach (craft()->market_productType->getAll() as $productType) {
			$key = 'productType:' . $productType->id;

			$sources[$key] = [
				'label'    => $productType->name,
				'criteria' => ['typeId' => $productType->id]
			];
		}

		return $sources;

	}


	public function defineTableAttributes($source = NULL)
	{
		return [
			'title'       => Craft::t('Name'),
			'availableOn' => Craft::t('Available On'),
			'expiresOn'   => Craft::t('Expires On')
		];
	}

	public function defineSearchableAttributes()
	{
		return ['title'];
	}


	public function getTableAttributeHtml(BaseElementModel $element, $attribute)
	{
		return parent::getTableAttributeHtml($element, $attribute);
	}

	/**
	 * Sortable by
	 *
	 * @return array
	 */
	public function defineSortableAttributes()
	{
		return [
			'title'       => Craft::t('Name'),
			'availableOn' => Craft::t('Available On'),
			'expiresOn'   => Craft::t('Expires On')
		];
	}


	/**
	 * @inheritDoc IElementType::getStatuses()
	 *
	 * @return array|null
	 */
	public function getStatuses()
	{
		return [
			Market_ProductModel::LIVE    => Craft::t('Live'),
			Market_ProductModel::PENDING => Craft::t('Pending'),
			Market_ProductModel::EXPIRED => Craft::t('Expired'),
			BaseElementModel::DISABLED   => Craft::t('Disabled')
		];
	}


	public function defineCriteriaAttributes()
	{
		return [
			'typeId'      => AttributeType::Mixed,
			'type'        => AttributeType::Mixed,
			'availableOn' => AttributeType::Mixed,
			'expiresOn'   => AttributeType::Mixed,
			'after'       => AttributeType::Mixed,
			'before'      => AttributeType::Mixed,
			'status'      => [AttributeType::String, 'default' => Market_ProductModel::LIVE],
		];
	}

	/**
	 * @inheritDoc IElementType::getElementQueryStatusCondition()
	 *
	 * @param DbCommand $query
	 * @param string    $status
	 *
	 * @return array|false|string|void
	 */
	public function getElementQueryStatusCondition(DbCommand $query, $status)
	{
		$currentTimeDb = DateTimeHelper::currentTimeForDb();

		switch ($status) {
			case Market_ProductModel::LIVE: {
				return ['and',
					'elements.enabled = 1',
					'elements_i18n.enabled = 1',
					"products.availableOn <= '{$currentTimeDb}'",
					['or', 'products.expiresOn is null', "products.expiresOn > '{$currentTimeDb}'"]
				];
			}

			case Market_ProductModel::PENDING: {
				return ['and',
					'elements.enabled = 1',
					'elements_i18n.enabled = 1',
					"products.availableOn > '{$currentTimeDb}'"
				];
			}

			case Market_ProductModel::EXPIRED: {
				return ['and',
					'elements.enabled = 1',
					'elements_i18n.enabled = 1',
					'products.expiresOn is not null',
					"products.expiresOn <= '{$currentTimeDb}'"
				];
			}
		}
	}


	public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
	{
		$query
			->addSelect("products.id, products.typeId, products.availableOn, products.expiresOn, products.taxCategoryId, products.authorId")
			->join('market_products products', 'products.id = elements.id')
			->join('market_producttypes producttypes', 'producttypes.id = products.typeId');

		if ($criteria->availableOn) {
			$query->andWhere(DbHelper::parseDateParam('products.availableOn', $criteria->postDate, $query->params));
		} else {
			if ($criteria->after) {
				$query->andWhere(DbHelper::parseDateParam('products.availableOn', '>=' . $criteria->after, $query->params));
			}

			if ($criteria->before) {
				$query->andWhere(DbHelper::parseDateParam('products.availableOn', '<' . $criteria->before, $query->params));
			}
		}

		if ($criteria->expiresOn) {
			$query->andWhere(DbHelper::parseDateParam('products.expiresOn', $criteria->expiryDate, $query->params));
		}

		if ($criteria->type) {
			if ($criteria->type instanceof Market_ProductTypeModel) {
				$criteria->typeId = $criteria->type->id;
				$criteria->type   = NULL;
			} else {
				$query->andWhere(DbHelper::parseParam('producttypes.handle', $criteria->type, $query->params));
			}
		}

		if ($criteria->typeId) {
			$query->andWhere(DbHelper::parseParam('products.typeId', $criteria->typeId, $query->params));
		}
	}


	public function populateElementModel($row)
	{
		return Market_ProductModel::populateModel($row);
	}

	/**
	 * Routes the request when the URI matches a product.
	 *
	 * @param BaseElementModel $element
	 *
	 * @return array|bool|mixed
	 */
	public function routeRequestForMatchedElement(BaseElementModel $element)
	{
		/** @var Market_ProductModel $element */
		if ($element->getStatus() == Market_ProductModel::LIVE)
		{
			$productType = $element->type;

			if ($productType->hasUrls)
			{
				return [
					'action' => 'templates/render',
					'params' => [
						'template' => $productType->template,
						'variables' => [
							'product' => $element
						]
					]
				];
			}
		}

		return false;
	}

	public function saveElement(BaseElementModel $element, $params)
	{
		craft()->market_product->save($element);
	}

} 