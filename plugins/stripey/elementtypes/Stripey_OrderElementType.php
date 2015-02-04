<?php
namespace Craft;

class Stripey_OrderElementType extends BaseElementType
{

	public function getName()
	{
		return Craft::t('Orders');
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
		return false;
	}

	public function getSources($context = NULL)
	{

		$sources = array(
			'*' => array(
				'label' => Craft::t('All orders'),
			)
		);

		foreach (craft()->stripey_orderType->getAll() as $orderType) {
			$key = 'orderType:' . $orderType->id;

			$sources[$key] = array(
				'label'    => $orderType->name,
				'criteria' => array('typeId' => $orderType->id)
			);
		}

		return $sources;

	}

	public function defineTableAttributes($source = NULL)
	{
		return array(
			'title'       => Craft::t('Name'),
			'availableOn' => Craft::t('Available On')
		);
	}

	public function defineSearchableAttributes()
	{
		return array('title');
	}

	public function getTableAttributeHtml(BaseElementModel $element, $attribute)
	{
		return parent::getTableAttributeHtml($element, $attribute);
	}

	public function defineSortableAttributes()
	{
		return array(
			'number' => Craft::t('Number')
		);
	}


	public function defineCriteriaAttributes()
	{
		return array(
			'typeId' => AttributeType::Mixed,
			'type'   => AttributeType::Mixed,
			'number' => AttributeType::Mixed,
		);
	}


	public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
	{
		$query
			->addSelect("orders.id, orders.typeId")
			->join('stripey_orders orders', 'orders.id = elements.id')
			->join('stripey_ordertypes ordertypes', 'ordertypes.id = orders.typeId');

		if ($criteria->type) {
			if ($criteria->type instanceof Stripey_OrderTypeModel) {
				$criteria->typeId = $criteria->type->id;
				$criteria->type   = NULL;
			} else {
				$query->andWhere(DbHelper::parseParam('ordertypes.handle', $criteria->type, $query->params));
			}
		}

		if ($criteria->typeId) {
			$query->andWhere(DbHelper::parseParam('orders.typeId', $criteria->typeId, $query->params));
		}

		if ($criteria->number) {
			$query->andWhere(DbHelper::parseParam('orders.number', $criteria->number, $query->params));
		}
	}


	public function populateElementModel($row)
	{
		return Stripey_OrderModel::populateModel($row);
	}

} 